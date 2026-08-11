<?php

namespace App\Http\Controllers;

use App\Actions\CreateAssessmentScorecardVersion;
use App\Actions\PublishAssessmentScorecardVersion;
use App\Enums\ProgrammePermission;
use App\Http\Requests\StoreAssessmentCycleRequest;
use App\Http\Requests\StoreAssessmentScorecardRequest;
use App\Http\Requests\StoreAssessmentScorecardVersionRequest;
use App\Models\AssessmentCycle;
use App\Models\AssessmentScorecard;
use App\Models\AssessmentScorecardVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentConfigurationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(ProgrammePermission::ManageAssessmentConfiguration->value);
        $search = $request->string('search')->trim()->toString();
        $scorecards = AssessmentScorecard::query()
            ->with(['versions' => fn ($query) => $query->withCount('functions')->latest('version')])
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")))
            ->latest()
            ->paginate(10, pageName: 'scorecards_page')
            ->withQueryString();
        $cycles = AssessmentCycle::query()->with('scorecardVersion.scorecard:id,name')->latest('period_start')->paginate(10, pageName: 'cycles_page')->withQueryString();

        return Inertia::render('assessment-configuration/index', [
            'filters' => ['search' => $search],
            'scorecards' => $scorecards->through(fn (AssessmentScorecard $scorecard): array => [
                'id' => $scorecard->id,
                'code' => $scorecard->code,
                'name' => $scorecard->name,
                'description' => $scorecard->description,
                'status' => $scorecard->status,
                'versions' => $scorecard->versions->map(fn (AssessmentScorecardVersion $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'status' => $version->status,
                    'calculationMethod' => $version->calculation_method,
                    'functionCount' => $version->functions_count,
                    'checksum' => $version->checksum,
                    'publishedAt' => $version->published_at?->toIso8601String(),
                ])->values()->all(),
            ]),
            'cycles' => $cycles->through(fn (AssessmentCycle $cycle): array => [
                'id' => $cycle->id,
                'code' => $cycle->code,
                'name' => $cycle->name,
                'scorecard' => $cycle->scorecardVersion ? "{$cycle->scorecardVersion->scorecard->name} v{$cycle->scorecardVersion->version}" : null,
                'scorecardVersionId' => $cycle->assessment_scorecard_version_id,
                'periodStart' => $cycle->period_start->toDateString(),
                'periodEnd' => $cycle->period_end->toDateString(),
                'submissionOpensAt' => $cycle->submission_opens_at?->toIso8601String(),
                'submissionClosesAt' => $cycle->submission_closes_at?->toIso8601String(),
                'status' => $cycle->status,
            ]),
            'publishedVersions' => AssessmentScorecardVersion::query()
                ->whereIn('status', ['published', 'retired'])
                ->with('scorecard:id,name')
                ->latest('version')
                ->get()
                ->map(fn (AssessmentScorecardVersion $version): array => ['id' => $version->id, 'label' => "{$version->scorecard->name} v{$version->version} ({$version->status})"]),
        ]);
    }

    public function storeScorecard(StoreAssessmentScorecardRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $scorecard = AssessmentScorecard::create($request->validated());
        $this->audit($request, $auditLogger, $scorecard, 'assessment.scorecard.created', 'Assessment scorecard definition created.');

        return $this->success('Assessment scorecard created.');
    }

    public function storeVersion(StoreAssessmentScorecardVersionRequest $request, string $currentTeam, AssessmentScorecard $assessmentScorecard, CreateAssessmentScorecardVersion $createVersion, AuditLogger $auditLogger): RedirectResponse
    {
        $version = $createVersion->handle($assessmentScorecard, $request->validated());
        $this->audit($request, $auditLogger, $version, 'assessment.scorecard.version.created', "Assessment scorecard version {$version->version} drafted.");

        return $this->success("Scorecard version {$version->version} created as a draft.");
    }

    public function publishVersion(Request $request, string $currentTeam, AssessmentScorecard $assessmentScorecard, AssessmentScorecardVersion $scorecardVersion, PublishAssessmentScorecardVersion $publishVersion, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageAssessmentConfiguration->value);
        abort_unless($scorecardVersion->assessment_scorecard_id === $assessmentScorecard->id, 404);
        $published = $publishVersion->handle($scorecardVersion, $this->user($request));
        $this->audit($request, $auditLogger, $published, 'assessment.scorecard.version.published', "Assessment scorecard version {$published->version} published.");

        return $this->success("Scorecard version {$published->version} published.");
    }

    public function storeCycle(StoreAssessmentCycleRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $cycle = AssessmentCycle::create($request->validated());
        $this->audit($request, $auditLogger, $cycle, 'assessment.cycle.created', 'Assessment cycle created and linked to an immutable scorecard version.');

        return $this->success('Assessment cycle created.');
    }

    private function audit(Request $request, AuditLogger $auditLogger, AssessmentScorecard|AssessmentScorecardVersion|AssessmentCycle $subject, string $action, string $description): void
    {
        $auditLogger->record($this->user($request), $subject, $action, $description);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
