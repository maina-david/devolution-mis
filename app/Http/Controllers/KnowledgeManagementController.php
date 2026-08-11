<?php

namespace App\Http\Controllers;

use App\Actions\CreateDevolutionInnovation;
use App\Actions\CreateInnovationExperimentMilestone;
use App\Actions\CreateKnowledgeCommunityReport;
use App\Actions\CreateKnowledgeItem;
use App\Actions\CreateKnowledgePost;
use App\Actions\ModerateKnowledgePost;
use App\Actions\RecordInnovationFundingDecision;
use App\Actions\RecordInnovationPanelReview;
use App\Actions\TransitionDevolutionInnovation;
use App\Actions\TransitionKnowledgeCommunityReport;
use App\Actions\TransitionKnowledgeItem;
use App\Actions\UpdateInnovationExperimentMilestone;
use App\Actions\UpdateKnowledgeDiscussionSubscription;
use App\Actions\VerifyInnovationExperimentMilestone;
use App\Enums\ProgrammePermission;
use App\Http\Requests\ModerateKnowledgePostRequest;
use App\Http\Requests\StoreDevolutionInnovationRequest;
use App\Http\Requests\StoreInnovationExperimentMilestoneRequest;
use App\Http\Requests\StoreInnovationFundingDecisionRequest;
use App\Http\Requests\StoreInnovationPanelReviewRequest;
use App\Http\Requests\StoreKnowledgeCommunityReportRequest;
use App\Http\Requests\StoreKnowledgeDiscussionRequest;
use App\Http\Requests\StoreKnowledgeItemRequest;
use App\Http\Requests\StoreKnowledgePostRequest;
use App\Http\Requests\TransitionDevolutionInnovationRequest;
use App\Http\Requests\TransitionKnowledgeCommunityReportRequest;
use App\Http\Requests\TransitionKnowledgeItemRequest;
use App\Http\Requests\UpdateInnovationExperimentMilestoneRequest;
use App\Http\Requests\UpdateKnowledgeDiscussionSubscriptionRequest;
use App\Http\Requests\VerifyInnovationExperimentMilestoneRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\AssessmentDocument;
use App\Models\DevolutionInnovation;
use App\Models\InnovationExperimentMilestone;
use App\Models\KnowledgeCommunityReport;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeItem;
use App\Models\KnowledgePost;
use App\Models\LearningCourse;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\KnowledgeSearch;
use App\Services\ProgrammeCountyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeManagementController extends Controller
{
    public function __construct(private ProgrammeCountyScope $countyScope, private AuditLogger $auditLogger, private KnowledgeSearch $knowledgeSearch, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    public function index(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ViewKnowledge->value);
        $user = $this->user($request);
        $countyIds = $this->countyIds($user);
        $referenceDataRelease = $this->referenceDataReleaseResolver->availableForSelection(now());
        $governedCountyIds = $this->snapshotIds($referenceDataRelease?->snapshot['counties'] ?? []);
        $governedSectorIds = $this->snapshotIds($referenceDataRelease?->snapshot['sectors'] ?? []);
        $canCurate = $user->canAny([ProgrammePermission::CurateKnowledge->value, ProgrammePermission::ManageKnowledge->value]);
        $items = KnowledgeItem::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(function (Builder $query) use ($user, $countyIds, $canCurate): void {
                $query->where(function (Builder $published) use ($countyIds): void {
                    $published->where('status', 'published')->where(fn (Builder $scope) => $scope->whereNull('county_id')->orWhereIn('county_id', $countyIds));
                })->orWhere('author_id', $user->id);
                if ($canCurate) {
                    $query->orWhereIn('county_id', $countyIds);
                }
            }))
            ->when(! $canCurate, fn (Builder $query) => $query->where(fn (Builder $visible) => $visible->where('status', 'published')->orWhere('author_id', $user->id)))
            ->with(['county:id,name,code,logo_path', 'sector:id,name', 'author:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'document:id,title,county_id,mime_type,original_name,scan_status,record_status,current_version_id', 'document.currentVersion:id,assessment_document_id', 'document.currentVersion.extraction:id,document_version_id,status,extracted_text', 'courses:id,code,title', 'discussions' => fn ($query) => $query->where('status', 'open')->latest('last_posted_at'), 'discussions.creator:id,name', 'discussions.subscriptions' => fn ($query) => $query->where('user_id', $user->id), 'discussions.posts' => fn ($query) => $query->when(! $canCurate, fn ($posts) => $posts->where('moderation_status', 'visible'))->with(['author:id,name', 'moderator:id,name'])->orderBy('posted_at')])
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('county_id'), fn (Builder $query) => $query->where('county_id', $request->string('county_id')))
            ->when($request->filled('sector_id'), fn (Builder $query) => $query->where('sector_id', $request->string('sector_id')))
            ->when($request->filled('item_type'), fn (Builder $query) => $query->where('item_type', $request->string('item_type')))
            ->when($request->filled('tag'), fn (Builder $query) => $query->whereJsonContains('tags', mb_strtolower($request->string('tag')->toString())))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $this->knowledgeSearch->apply($query, $search);
            })->when(! $request->filled('search'), fn (Builder $query) => $query->latest())->paginate($request->integer('per_page', 10))->withQueryString();

        $innovations = DevolutionInnovation::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->where('submitted_by', $user->id)->orWhereIn('county_id', $countyIds)))
            ->with(['county:id,name,code,logo_path', 'sector:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'submitter:id,name', 'reviewer:id,name', 'panelReviews.reviewer:id,name', 'fundingDecisions.decisionMaker:id,name', 'experimentMilestones.owner:id,name', 'experimentMilestones.submitter:id,name', 'experimentMilestones.verifier:id,name', 'experimentMilestones.document:id,title,original_name,mime_type'])
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('county_id'), fn (Builder $query) => $query->where('county_id', $request->string('county_id')))
            ->when($request->filled('sector_id'), fn (Builder $query) => $query->where('sector_id', $request->string('sector_id')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(fn (Builder $searchQuery) => $searchQuery->where('reference', 'ilike', "%{$search}%")->orWhere('title', 'ilike', "%{$search}%")->orWhere('problem_statement', 'ilike', "%{$search}%")->orWhere('proposed_solution', 'ilike', "%{$search}%"));
            })->latest()->paginate($request->integer('per_page', 10), ['*'], 'innovation_page')->withQueryString();

        $reports = KnowledgeCommunityReport::query()
            ->when(! $canCurate, fn (Builder $query) => $query->where('reported_by', $user->id))
            ->when($canCurate && ! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $countyIds))
            ->with(['post:id,knowledge_discussion_id,author_id,body,moderation_status', 'post.author:id,name', 'post.discussion:id,title', 'county:id,name,code,logo_path', 'reporter:id,name', 'triager:id,name', 'decisionMaker:id,name', 'workflowInstance:id,due_at,current_state,status'])
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('report_status'), fn (Builder $query) => $query->where('status', $request->string('report_status')))
            ->when($request->filled('report_search'), function (Builder $query) use ($request): void {
                $search = $request->string('report_search')->trim()->toString();
                $query->where(fn (Builder $searchQuery) => $searchQuery->where('reference', 'ilike', "%{$search}%")->orWhere('description', 'ilike', "%{$search}%")->orWhere('category', 'ilike', "%{$search}%"));
            })->latest()->paginate(10, ['*'], 'report_page')->withQueryString();

        return Inertia::render('knowledge/index', [
            'items' => $items->through(fn (KnowledgeItem $item): array => $this->itemPayload($item, $request->string('search')->trim()->toString(), $user)),
            'innovations' => $innovations->through(fn (DevolutionInnovation $innovation): array => $this->innovationPayload($innovation)),
            'reports' => $reports->through(fn (KnowledgeCommunityReport $report): array => $this->communityReportPayload($report)),
            'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'county_id', 'sector_id', 'item_type', 'tag', 'per_page', 'report_status', 'report_search']),
            'capabilities' => ['contribute' => $user->can(ProgrammePermission::ContributeKnowledge->value), 'curate' => $user->can(ProgrammePermission::CurateKnowledge->value), 'manage' => $user->can(ProgrammePermission::ManageKnowledge->value)],
            'catalogue' => ['available' => $referenceDataRelease !== null, 'version' => $referenceDataRelease?->version, 'effectiveFrom' => $referenceDataRelease?->effective_from?->toIso8601String()],
            'options' => [
                'counties' => $this->countyScope->query($user)->whereIn('id', $governedCountyIds)->orderBy('name')->get()->map->identityCell()->values(),
                'sectors' => Sector::query()->whereIn('id', $governedSectorIds)->orderBy('name')->get(['id', 'name']),
                'documents' => AssessmentDocument::query()->whereIn('county_id', $this->countyScope->query($user)->select('id'))->where('scan_status', 'clean')->where('record_status', 'active')->orderBy('title')->get(['id', 'title', 'county_id'])->map(fn ($document): array => ['id' => $document->id, 'label' => $document->title]),
                'milestoneOwners' => User::query()->whereNull('access_revoked_at')->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->whereIn('county_id', $countyIds)->orWhereHas('assignedCounties', fn (Builder $assigned) => $assigned->whereIn('counties.id', $countyIds))))->orderBy('name')->get(['id', 'name']),
                'courses' => LearningCourse::query()->where('status', 'published')->orderBy('title')->get(['id', 'code', 'title'])->map(fn ($course): array => ['id' => $course->id, 'label' => "{$course->code} · {$course->title}"]),
                'tags' => KnowledgeItem::query()->whereNotNull('tags')->pluck('tags')->flatten()->unique()->sort()->values(),
            ],
        ]);
    }

    public function store(StoreKnowledgeItemRequest $request, CreateKnowledgeItem $action): RedirectResponse
    {
        $item = $action->handle($this->user($request), $request->validated());

        return back()->with('success', "Knowledge item {$item->reference} created.");
    }

    public function transition(TransitionKnowledgeItemRequest $request, string $currentTeam, KnowledgeItem $item, TransitionKnowledgeItem $action): RedirectResponse
    {
        $this->authorizeItem($this->user($request), $item);
        $action->handle($item, $this->user($request), $request->validated());

        return back()->with('success', 'Knowledge publication workflow updated.');
    }

    public function storeDiscussion(StoreKnowledgeDiscussionRequest $request, UpdateKnowledgeDiscussionSubscription $subscriptions): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validated();
        $knowledgeItemId = $data['knowledge_item_id'] ?? null;
        if (is_string($knowledgeItemId)) {
            $this->authorizeItem($user, KnowledgeItem::query()->findOrFail($knowledgeItemId));
        }
        $discussion = KnowledgeDiscussion::create([...$data, 'created_by' => $user->id, 'status' => 'open', 'last_posted_at' => now()]);
        $subscriptions->handle($discussion, $user, true);
        $this->auditLogger->record($user, $discussion, 'knowledge.discussion.created', "Community discussion {$discussion->title} opened.", $discussion->county_id);

        return back()->with('success', 'Community discussion opened.');
    }

    public function storePost(StoreKnowledgePostRequest $request, string $currentTeam, KnowledgeDiscussion $discussion, CreateKnowledgePost $action): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($discussion->status === 'open' && $this->canSeeCounty($user, $discussion->county_id), 403);
        $action->handle($discussion, $user, $request->validated('body'));

        return back()->with('success', 'Contribution posted.');
    }

    public function updateDiscussionSubscription(UpdateKnowledgeDiscussionSubscriptionRequest $request, string $currentTeam, KnowledgeDiscussion $discussion, UpdateKnowledgeDiscussionSubscription $action): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->canSeeCounty($user, $discussion->county_id), 403);
        $action->handle($discussion, $user, $request->boolean('subscribed'));

        return back()->with('success', $request->boolean('subscribed') ? 'Discussion notifications enabled.' : 'Discussion notifications disabled.');
    }

    public function moderatePost(ModerateKnowledgePostRequest $request, string $currentTeam, KnowledgePost $post, ModerateKnowledgePost $action): RedirectResponse
    {
        $user = $this->user($request);
        $post->loadMissing('discussion');
        abort_unless($this->canSeeCounty($user, $post->discussion->county_id), 403);
        $action->handle($post, $user, $request->string('moderation_status')->toString(), $request->string('moderation_reason')->toString());

        return back()->with('success', 'Contribution moderation updated.');
    }

    public function storeCommunityReport(StoreKnowledgeCommunityReportRequest $request, string $currentTeam, KnowledgePost $post, CreateKnowledgeCommunityReport $action): RedirectResponse
    {
        $user = $this->user($request);
        $post->loadMissing('discussion');
        abort_unless($this->canSeeCounty($user, $post->discussion->county_id), 403);
        $action->handle($post, $user, [
            'category' => $request->string('category')->toString(),
            'severity' => $request->string('severity')->toString(),
            'description' => $request->string('description')->toString(),
        ]);

        return back()->with('success', 'Contribution report entered the governed moderation queue.');
    }

    public function transitionCommunityReport(TransitionKnowledgeCommunityReportRequest $request, string $currentTeam, KnowledgeCommunityReport $report, TransitionKnowledgeCommunityReport $action): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->canSeeCounty($user, $report->county_id), 403);
        $action->handle($report, $user, $request->string('transition')->toString(), $request->string('rationale')->toString(), $request->filled('resolution') ? $request->string('resolution')->toString() : null, $request->filled('post_action') ? $request->string('post_action')->toString() : null);

        return back()->with('success', 'Community report workflow updated.');
    }

    public function storeInnovation(StoreDevolutionInnovationRequest $request, CreateDevolutionInnovation $action): RedirectResponse
    {
        $innovation = $action->handle($this->user($request), $request->validated());

        return back()->with('success', "Innovation {$innovation->reference} created.");
    }

    public function transitionInnovation(TransitionDevolutionInnovationRequest $request, string $currentTeam, DevolutionInnovation $innovation, TransitionDevolutionInnovation $action): RedirectResponse
    {
        abort_unless($this->canSeeCounty($this->user($request), $innovation->county_id), 403);
        $action->handle($innovation, $this->user($request), $request->validated());

        return back()->with('success', 'Innovation incubation workflow updated.');
    }

    public function storeInnovationPanelReview(StoreInnovationPanelReviewRequest $request, string $currentTeam, DevolutionInnovation $innovation, RecordInnovationPanelReview $action): RedirectResponse
    {
        $action->handle($innovation, $this->user($request), $request->validated());

        return back()->with('success', 'Immutable innovation panel review recorded.');
    }

    public function storeInnovationFundingDecision(StoreInnovationFundingDecisionRequest $request, string $currentTeam, DevolutionInnovation $innovation, RecordInnovationFundingDecision $action): RedirectResponse
    {
        $action->handle($innovation, $this->user($request), $request->validated());

        return back()->with('success', 'Versioned innovation funding decision recorded.');
    }

    public function storeInnovationMilestone(StoreInnovationExperimentMilestoneRequest $request, string $currentTeam, DevolutionInnovation $innovation, CreateInnovationExperimentMilestone $action): RedirectResponse
    {
        $action->handle($innovation, $this->user($request), $request->validated());

        return back()->with('success', 'Pilot experiment milestone defined.');
    }

    public function updateInnovationMilestone(UpdateInnovationExperimentMilestoneRequest $request, string $currentTeam, DevolutionInnovation $innovation, InnovationExperimentMilestone $milestone, UpdateInnovationExperimentMilestone $action): RedirectResponse
    {
        abort_unless($milestone->devolution_innovation_id === $innovation->id, 404);
        $action->handle($milestone, $this->user($request), $request->validated());

        return back()->with('success', 'Pilot milestone evidence updated.');
    }

    public function verifyInnovationMilestone(VerifyInnovationExperimentMilestoneRequest $request, string $currentTeam, DevolutionInnovation $innovation, InnovationExperimentMilestone $milestone, VerifyInnovationExperimentMilestone $action): RedirectResponse
    {
        abort_unless($milestone->devolution_innovation_id === $innovation->id, 404);
        $action->handle($milestone, $this->user($request), $request->validated());

        return back()->with('success', 'Independent milestone verification recorded.');
    }

    private function authorizeItem(User $user, KnowledgeItem $item): void
    {
        abort_unless($item->author_id === $user->id || $this->canSeeCounty($user, $item->county_id), 403);
    }

    private function canSeeCounty(User $user, ?string $countyId): bool
    {
        return $countyId === null || $user->programmeRole()->hasNationalScope() || in_array($countyId, $this->countyIds($user), true);
    }

    /** @return list<string> */
    private function countyIds(User $user): array
    {
        return array_values(array_filter($this->countyScope->query($user)->pluck('id')->all(), is_string(...)));
    }

    /** @return array<string, mixed> */
    private function itemPayload(KnowledgeItem $item, string $search, User $user): array
    {
        return ['id' => $item->id, 'reference' => $item->reference, 'type' => $item->item_type, 'title' => $item->title, 'summary' => $item->summary, 'content' => $item->content_body, 'searchExcerpt' => $this->searchExcerpt($item, $search), 'tags' => $item->tags ?? [], 'visibility' => $item->visibility, 'status' => $item->status, 'publishedOn' => $item->published_on?->toDateString(), 'reviewDueAt' => $item->review_due_at?->toIso8601String(), 'sourceOrganization' => $item->source_organization, 'externalUrl' => $item->external_url, 'language' => $item->language, 'county' => $item->county?->identityCell(), 'sector' => $item->sector?->name, 'referenceData' => $item->referenceDataRelease ? ['version' => $item->referenceDataRelease->version, 'effectiveFrom' => $item->referenceDataRelease->effective_from?->toIso8601String(), 'checksum' => $item->referenceDataRelease->checksum] : null, 'author' => $item->author->name, 'document' => $item->document ? ['id' => $item->document->id, 'title' => $item->document->title, 'mimeType' => $item->document->mime_type, 'originalName' => $item->document->original_name] : null, 'courses' => $item->courses->map(fn ($course): array => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title])->values(), 'discussions' => $item->discussions->map(fn (KnowledgeDiscussion $discussion): array => $this->discussionPayload($discussion, $user))->values(), 'createdAt' => $item->created_at->toIso8601String()];
    }

    private function searchExcerpt(KnowledgeItem $item, string $search): ?string
    {
        if ($search === '') {
            return null;
        }
        $text = $item->document?->currentVersion?->extraction?->extracted_text;
        if (! is_string($text) || ! str($text)->lower()->contains(str($search)->lower())) {
            return null;
        }

        return str($text)->squish()->limit(240)->toString();
    }

    /** @return array<string, mixed> */
    private function discussionPayload(KnowledgeDiscussion $discussion, User $user): array
    {
        return [
            'id' => $discussion->id,
            'title' => $discussion->title,
            'prompt' => $discussion->prompt,
            'creator' => $discussion->creator->name,
            'subscribed' => $discussion->subscriptions->isNotEmpty(),
            'posts' => $discussion->posts->map(fn (KnowledgePost $post): array => $this->postPayload($post, $user))->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function postPayload(KnowledgePost $post, User $user): array
    {
        return ['id' => $post->id, 'body' => $post->body, 'author' => $post->author->name, 'postedAt' => $post->posted_at?->toIso8601String(), 'moderationStatus' => $post->moderation_status, 'moderationReason' => $post->moderation_reason, 'moderator' => $post->moderator?->name, 'moderatedAt' => $post->moderated_at?->toIso8601String(), 'canReport' => $post->author_id !== $user->id && $post->moderation_status === 'visible'];
    }

    /** @return array<string, mixed> */
    private function innovationPayload(DevolutionInnovation $innovation): array
    {
        $panelAverage = $innovation->panelReviews->isEmpty() ? null : round((float) $innovation->panelReviews->avg('weighted_score'), 2);
        $latestFunding = $innovation->fundingDecisions->sortByDesc('decision_version')->first();
        $pilotVerified = $innovation->experimentMilestones->isNotEmpty() && $innovation->experimentMilestones->every(fn ($milestone): bool => $milestone->status === 'completed' && $milestone->verification_decision === 'verified');

        return ['id' => $innovation->id, 'reference' => $innovation->reference, 'title' => $innovation->title, 'problem' => $innovation->problem_statement, 'solution' => $innovation->proposed_solution, 'impact' => $innovation->expected_impact, 'maturity' => $innovation->maturity_level, 'stage' => $innovation->stage, 'status' => $innovation->status, 'incubationSupport' => $innovation->incubation_support, 'evidenceReference' => $innovation->evidence_reference, 'county' => $innovation->county?->identityCell(), 'sector' => $innovation->sector?->name, 'referenceData' => $innovation->referenceDataRelease ? ['version' => $innovation->referenceDataRelease->version, 'effectiveFrom' => $innovation->referenceDataRelease->effective_from?->toIso8601String(), 'checksum' => $innovation->referenceDataRelease->checksum] : null, 'submitter' => $innovation->submitter->name, 'reviewer' => $innovation->reviewer?->name, 'submittedAt' => $innovation->submitted_at?->toIso8601String(), 'decisionDueAt' => $innovation->decision_due_at?->toIso8601String(), 'createdAt' => $innovation->created_at->toIso8601String(),
            'panelSummary' => ['count' => $innovation->panelReviews->count(), 'average' => $panelAverage, 'advanceCount' => $innovation->panelReviews->where('recommendation', 'advance')->count(), 'ready' => $innovation->panelReviews->count() >= 2 && $innovation->panelReviews->where('recommendation', 'advance')->count() >= 2 && $panelAverage !== null && $panelAverage >= 70],
            'panelReviews' => $innovation->panelReviews->map(fn ($review): array => ['id' => $review->id, 'reviewer' => $review->reviewer->name, 'strategicFit' => (float) $review->strategic_fit_score, 'feasibility' => (float) $review->feasibility_score, 'inclusion' => (float) $review->inclusion_score, 'evidence' => (float) $review->evidence_score, 'weightedScore' => (float) $review->weighted_score, 'recommendation' => $review->recommendation, 'rationale' => $review->rationale, 'rubricCode' => $review->rubric_code, 'rubricChecksum' => $review->rubric_checksum, 'evidenceChecksum' => $review->evidence_checksum, 'reviewedAt' => $review->reviewed_at?->toIso8601String()])->values(),
            'fundingDecisions' => $innovation->fundingDecisions->sortByDesc('decision_version')->map(fn ($decision): array => ['id' => $decision->id, 'version' => $decision->decision_version, 'decision' => $decision->decision, 'amount' => (float) $decision->amount, 'currency' => $decision->currency, 'fundingType' => $decision->funding_type, 'reference' => $decision->decision_reference, 'rationale' => $decision->rationale, 'decisionMaker' => $decision->decisionMaker->name, 'decidedAt' => $decision->decided_at?->toIso8601String(), 'previousChecksum' => $decision->previous_checksum, 'evidenceChecksum' => $decision->evidence_checksum])->values(),
            'fundingReady' => $latestFunding !== null && in_array($latestFunding->decision, ['approved', 'not_required'], true),
            'milestones' => $innovation->experimentMilestones->map(fn ($milestone): array => ['id' => $milestone->id, 'title' => $milestone->title, 'hypothesis' => $milestone->hypothesis, 'successMetric' => $milestone->success_metric, 'baselineValue' => $milestone->baseline_value, 'targetValue' => $milestone->target_value, 'actualValue' => $milestone->actual_value, 'dueAt' => $milestone->due_at?->toDateString(), 'status' => $milestone->status, 'outcomeSummary' => $milestone->outcome_summary, 'owner' => $milestone->owner->name, 'submitter' => $milestone->submitter?->name, 'submittedAt' => $milestone->submitted_at?->toIso8601String(), 'verificationDecision' => $milestone->verification_decision, 'verificationRationale' => $milestone->verification_rationale, 'verifier' => $milestone->verifier?->name, 'verifiedAt' => $milestone->verified_at?->toIso8601String(), 'document' => $milestone->document ? ['id' => $milestone->document->id, 'title' => $milestone->document->title, 'originalName' => $milestone->document->original_name, 'mimeType' => $milestone->document->mime_type] : null])->values(),
            'pilotVerified' => $pilotVerified,
        ];
    }

    /** @return array<string, mixed> */
    private function communityReportPayload(KnowledgeCommunityReport $report): array
    {
        return [
            'id' => $report->id,
            'reference' => $report->reference,
            'postId' => $report->knowledge_post_id,
            'discussion' => $report->post->discussion->title,
            'postBody' => $report->post->body,
            'postAuthor' => $report->post->author->name,
            'postModerationStatus' => $report->post->moderation_status,
            'county' => $report->county?->identityCell(),
            'reporter' => $report->reporter->name,
            'category' => $report->category,
            'severity' => $report->severity,
            'description' => $report->description,
            'status' => $report->status,
            'triager' => $report->triager?->name,
            'decisionMaker' => $report->decisionMaker?->name,
            'resolution' => $report->resolution,
            'postAction' => $report->post_action,
            'dueAt' => $report->workflowInstance?->due_at?->toIso8601String(),
            'createdAt' => $report->created_at->toIso8601String(),
            'decidedAt' => $report->decided_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<string>
     */
    private function snapshotIds(array $records): array
    {
        return array_values(collect($records)->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all());
    }
}
