<?php

namespace App\Http\Controllers;

use App\Actions\CreatePartnerAgreement;
use App\Actions\CreatePartnerAgreementChange;
use App\Actions\CreatePartnerCollaborationAction;
use App\Actions\CreatePartnerProfile;
use App\Actions\DecidePartnerAgreementChange;
use App\Actions\ReconcilePartnerContribution;
use App\Actions\RecordPartnerCollaborationActionUpdate;
use App\Actions\TransitionPartnerAgreement;
use App\Actions\VerifyPartnerCollaborationActionUpdate;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Http\Requests\DecidePartnerAgreementChangeRequest;
use App\Http\Requests\ReconcilePartnerContributionRequest;
use App\Http\Requests\ResolvePartnerCollaborationAlertRequest;
use App\Http\Requests\ResolvePartnerOperationalAlertRequest;
use App\Http\Requests\StorePartnerAgreementChangeRequest;
use App\Http\Requests\StorePartnerAgreementRequest;
use App\Http\Requests\StorePartnerCollaborationActionRequest;
use App\Http\Requests\StorePartnerCollaborationActionUpdateRequest;
use App\Http\Requests\StorePartnerCollaborationPlanRequest;
use App\Http\Requests\StorePartnerContributionRequest;
use App\Http\Requests\StorePartnerProfileRequest;
use App\Http\Requests\TransitionPartnerAgreementRequest;
use App\Http\Requests\TransitionPartnerCollaborationPlanRequest;
use App\Http\Requests\VerifyPartnerCollaborationActionUpdateRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\DocumentLink;
use App\Models\Organization;
use App\Models\PartnerAgreement;
use App\Models\PartnerAgreementChangeRequest;
use App\Models\PartnerCollaborationAction;
use App\Models\PartnerCollaborationActionUpdate;
use App\Models\PartnerCollaborationAlert;
use App\Models\PartnerCollaborationPlan;
use App\Models\PartnerContribution;
use App\Models\PartnerOperationalAlert;
use App\Models\PartnerProfile;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\PartnerOverlapAnalyzer;
use App\Services\ProgrammeCountyScope;
use App\Services\ProgrammeWorkspaceData;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PartnerCoordinationController extends Controller
{
    public function index(WorkspaceIndexRequest $request, ProgrammeWorkspaceData $workspaceData, ProgrammeCountyScope $countyScope, EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver): Response
    {
        Gate::authorize(ProgrammePermission::ViewPartnerCoordination->value);
        $user = $this->user($request);
        $filters = WorkspaceFilters::fromRequest($request);
        $visibleCounties = $countyScope->query($user)->orderBy('code')->get();
        $referenceDataRelease = $referenceDataReleaseResolver->availableForSelection(now());
        $governedCountyIds = collect($referenceDataRelease?->snapshot['counties'] ?? [])->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all();
        $governedOrganizationIds = collect($referenceDataRelease?->snapshot['organizations'] ?? [])->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all();
        $portfolioPartners = $this->visiblePartners($user, $countyScope)->with(['counties:id', 'agreements:id,partner_profile_id,status', 'contributions:id,partner_profile_id,committed_amount,disbursed_amount'])->get();
        $showFullCountryMap = in_array($user->programmeRole(), [UserRole::Assessor, UserRole::DevelopmentPartner, UserRole::DevolutionAdmin], true);
        $portfolioMap = $visibleCounties->map(function (County $county) use ($portfolioPartners): array {
            $partners = $portfolioPartners->filter(fn (PartnerProfile $partner): bool => $partner->counties->contains('id', $county->id));
            $committed = (float) $partners->flatMap->contributions->sum('committed_amount');
            $disbursed = (float) $partners->flatMap->contributions->sum('disbursed_amount');

            return [...$county->identityCell(), 'assessmentStatus' => $partners->isEmpty() ? 'not_started' : 'assessed', 'mapTone' => $partners->isEmpty() ? 'inactive' : 'active', 'mapLabel' => $partners->count().' partner(s) · KES '.number_format($disbursed, 0).' disbursed', 'partnerCount' => $partners->count(), 'activeAgreementCount' => $partners->flatMap->agreements->where('status', 'active')->count(), 'committedAmount' => $committed, 'disbursedAmount' => $disbursed];
        })->values();
        $visiblePartnerIds = $this->visiblePartners($user, $countyScope)->select('partner_profiles.id');
        $alerts = PartnerCollaborationAlert::query()
            ->whereIn('primary_partner_id', $visiblePartnerIds)
            ->whereIn('related_partner_id', $this->visiblePartners($user, $countyScope)->select('partner_profiles.id'))
            ->with(['primaryPartner.organization:id,name', 'relatedPartner.organization:id,name'])
            ->latest('detected_at')
            ->limit(50)
            ->get();
        $operationalAlerts = PartnerOperationalAlert::query()
            ->whereIn('partner_profile_id', $this->visiblePartners($user, $countyScope)->select('partner_profiles.id'))
            ->where(fn (Builder $query) => $query->whereNull('county_id')->orWhereIn('county_id', $countyScope->query($user)->select('id')))
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('detected_at', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('detected_at', '<=', $to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('alert_type', 'ilike', '%'.$filters->search.'%')->orWhere('summary', 'ilike', '%'.$filters->search.'%')->orWhere('status', 'ilike', '%'.$filters->search.'%')->orWhereHas('partner.organization', fn (Builder $organization) => $organization->where('name', 'ilike', '%'.$filters->search.'%'))))
            ->with(['partner.organization:id,name', 'county'])
            ->latest('detected_at')->limit(100)->get()->map(fn (PartnerOperationalAlert $alert): array => [
                'id' => $alert->id, 'type' => $alert->alert_type, 'severity' => $alert->severity, 'status' => $alert->status, 'summary' => $alert->summary, 'partner' => $alert->partner->organization->name, 'county' => $alert->county?->identityCell(), 'dueOn' => $alert->due_on?->toDateString(), 'detectedAt' => $alert->detected_at->toIso8601String(), 'resolution' => $alert->resolution,
            ])->values();
        $agreements = PartnerAgreement::query()
            ->whereIn('partner_profile_id', $this->visiblePartners($user, $countyScope)->select('partner_profiles.id'))
            ->with([
                'partner.organization:id,name',
                'partner.users:id',
                'workflow:id,current_state,due_at,status',
                'documentLinks' => fn ($query) => $query->whereHas('document', fn (Builder $document) => $document->whereNull('deleted_at'))->with(['document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status,record_status,created_at']),
                'changeRequests.requester:id,name',
                'changeRequests.decision.decider:id,name',
                'changeRequests.documentLinks' => fn ($query) => $query->with('document:id,title,mime_type,scan_status,record_status'),
            ])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (PartnerAgreement $agreement): array => [
                'id' => $agreement->id,
                'partner' => $agreement->partner->organization->name,
                'reference' => $agreement->reference,
                'title' => $agreement->title,
                'type' => $agreement->agreement_type,
                'startsOn' => $agreement->starts_on->toDateString(),
                'endsOn' => $agreement->ends_on?->toDateString(),
                'status' => $agreement->status,
                'workflowState' => $agreement->workflow?->current_state,
                'dueAt' => $agreement->workflow?->due_at?->toIso8601String(),
                'canUpload' => $agreement->status === 'draft' && ($user->can(ProgrammePermission::ManagePartners->value) || $agreement->partner->users->contains($user)),
                'documents' => $agreement->documentLinks->map(fn (DocumentLink $link): array => [
                    'id' => $link->document->id,
                    'title' => $link->document->title,
                    'category' => $link->document->category,
                    'sourceType' => $link->document->source_type,
                    'originalName' => $link->document->original_name,
                    'mimeType' => $link->document->mime_type,
                    'scanStatus' => $link->document->scan_status,
                    'ocrStatus' => $link->document->ocr_status,
                ])->values()->all(),
                'changeRequests' => $agreement->changeRequests->map(fn (PartnerAgreementChangeRequest $change): array => [
                    'id' => $change->id, 'version' => $change->version, 'type' => $change->change_type, 'proposedChanges' => $change->proposed_changes, 'reason' => $change->reason, 'effectiveOn' => $change->effective_on->toDateString(), 'requester' => $change->requester->name, 'requestedAt' => $change->requested_at->toIso8601String(), 'requestChecksum' => $change->request_checksum,
                    'decision' => $change->decision ? ['result' => $change->decision->decision, 'note' => $change->decision->decision_note, 'decider' => $change->decision->decider->name, 'decidedAt' => $change->decision->decided_at->toIso8601String(), 'checksum' => $change->decision->decision_checksum] : null,
                    'documents' => $change->documentLinks->filter(fn (DocumentLink $link): bool => $link->document !== null)->map(fn (DocumentLink $link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'recordStatus' => $link->document->record_status])->values()->all(),
                    'canUpload' => $change->decision === null && ($user->can(ProgrammePermission::ManagePartners->value) || $change->requested_by === $user->id),
                    'canDecide' => $change->decision === null && $change->requested_by !== $user->id && $user->can(ProgrammePermission::ApprovePartnerAgreements->value),
                ])->values()->all(),
                'canRequestChange' => in_array($agreement->status, ['active', 'suspended'], true) && $user->can(ProgrammePermission::ManagePartners->value) && ! $agreement->changeRequests->contains(fn (PartnerAgreementChangeRequest $change): bool => $change->decision === null),
            ])->values();
        $contributions = PartnerContribution::query()
            ->whereIn('partner_profile_id', $this->visiblePartners($user, $countyScope)->select('partner_profiles.id'))
            ->whereHas('project.counties', fn (Builder $query) => $query->whereIn('counties.id', $countyScope->query($user)->select('id')))
            ->with(['partner.organization:id,name', 'project:id,code,title,lead_county_id', 'project.leadCounty', 'reconciliations.reviewer:id,name', 'documentLinks' => fn ($query) => $query->where('purpose', 'partner-contribution-reconciliation-evidence')->with('document')])
            ->latest()->limit(100)->get()
            ->map(function (PartnerContribution $contribution) use ($user): array {
                $documents = $contribution->documentLinks->filter(fn (DocumentLink $link): bool => $link->document !== null && $link->document->deleted_at === null)->map(fn (DocumentLink $link): array => [
                    'id' => $link->document->id, 'title' => $link->document->title, 'category' => $link->document->category, 'sourceType' => $link->document->source_type, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status, 'recordStatus' => $link->document->record_status, 'checksum' => $link->document->content_checksum,
                ])->values();

                return [
                    'id' => $contribution->id, 'partner' => $contribution->partner->organization->name,
                    'project' => ['id' => $contribution->project->id, 'code' => $contribution->project->code, 'title' => $contribution->project->title],
                    'county' => $contribution->project->leadCounty?->identityCell(), 'financialYear' => $contribution->financial_year, 'type' => $contribution->contribution_type, 'currency' => $contribution->currency,
                    'committedAmount' => $contribution->committed_amount, 'disbursedAmount' => $contribution->disbursed_amount, 'inKindValue' => $contribution->in_kind_value, 'status' => $contribution->status, 'provenance' => $contribution->provenance,
                    'documents' => $documents->all(),
                    'reconciliations' => $contribution->reconciliations->map(fn ($item): array => ['id' => $item->id, 'version' => $item->version, 'decision' => $item->decision, 'committedAmount' => $item->verified_committed_amount, 'disbursedAmount' => $item->verified_disbursed_amount, 'inKindValue' => $item->verified_in_kind_value, 'variance' => $item->disbursement_variance, 'sourceReference' => $item->source_reference, 'reviewNote' => $item->review_note, 'reviewer' => $item->reviewer->name, 'reviewedAt' => $item->reviewed_at->toIso8601String(), 'evidenceChecksum' => $item->evidence_checksum, 'predecessorChecksum' => $item->predecessor_checksum, 'decisionChecksum' => $item->decision_checksum])->values()->all(),
                    'canUpload' => $user->can(ProgrammePermission::ManagePartners->value) || $contribution->reported_by === $user->id || $contribution->partner->users()->whereKey($user)->exists(),
                    'canReconcile' => $user->can(ProgrammePermission::ManagePartners->value) && $contribution->reported_by !== $user->id && $documents->contains(fn (array $document): bool => $document['scanStatus'] === 'clean' && $document['recordStatus'] === 'active'),
                ];
            })->values();
        $alertRows = [];
        foreach ($alerts as $alert) {
            $alertRows[] = [
                'id' => $alert->id,
                'type' => $alert->alert_type,
                'severity' => $alert->severity,
                'status' => $alert->status,
                'summary' => $alert->summary,
                'primaryPartner' => $alert->primaryPartner->organization->name,
                'relatedPartner' => $alert->relatedPartner->organization->name,
                'detectedAt' => $alert->detected_at?->toIso8601String(),
                'resolution' => $alert->resolution,
            ];
        }
        $collaborationPlans = PartnerCollaborationPlan::query()
            ->whereIn('partner_profile_id', $this->visiblePartners($user, $countyScope)->select('partner_profiles.id'))
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('ends_on', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('starts_on', '<=', $to))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->whereHas('partner.counties', fn (Builder $counties) => $counties->whereKey($countyId)))
            ->when($filters->sectorId, fn (Builder $query, string $sectorId) => $query->whereHas('partner.sectors', fn (Builder $sectors) => $sectors->whereKey($sectorId)))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('reference', 'ilike', '%'.$filters->search.'%')->orWhere('title', 'ilike', '%'.$filters->search.'%')->orWhere('status', 'ilike', '%'.$filters->search.'%')->orWhereHas('partner.organization', fn (Builder $organization) => $organization->where('name', 'ilike', '%'.$filters->search.'%'))))
            ->with(['partner.organization:id,name', 'creator:id,name', 'actions' => fn ($query) => $query->whereIn('county_id', $countyScope->query($user)->select('id'))->with(['county', 'accountableUser:id,name', 'accountableOrganization:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'updates.submitter:id,name', 'updates.decision.verifier:id,name', 'documentLinks.document:id,title,mime_type,scan_status,record_status'])])
            ->latest()->limit(100)->get()->map(fn (PartnerCollaborationPlan $plan): array => [
                'id' => $plan->id, 'partner' => $plan->partner->organization->name, 'reference' => $plan->reference, 'title' => $plan->title, 'objective' => $plan->objective, 'startsOn' => $plan->starts_on->toDateString(), 'endsOn' => $plan->ends_on->toDateString(), 'status' => $plan->status, 'creator' => $plan->creator->name, 'submittedBy' => $plan->submitted_by, 'approvedBy' => $plan->approved_by, 'decisionNote' => $plan->decision_note,
                'canSubmit' => $plan->status === 'draft' && $user->can(ProgrammePermission::ManagePartners->value), 'canApprove' => $plan->status === 'pending_approval' && $plan->submitted_by !== $user->id && $user->can(ProgrammePermission::ApprovePartnerAgreements->value), 'canAddAction' => $plan->status === 'active' && $user->can(ProgrammePermission::ManagePartners->value), 'canComplete' => $plan->status === 'active' && $plan->actions->isNotEmpty() && $plan->actions->every(fn (PartnerCollaborationAction $action): bool => $action->status === 'completed') && $user->can(ProgrammePermission::ManagePartners->value),
                'actions' => $plan->actions->map(fn (PartnerCollaborationAction $action): array => ['id' => $action->id, 'code' => $action->code, 'title' => $action->title, 'description' => $action->description, 'county' => $action->county->identityCell(), 'owner' => $action->accountableUser->name, 'ownerId' => $action->accountable_user_id, 'ownerOrganization' => $action->accountableOrganization?->name, 'referenceData' => $action->referenceDataRelease ? ['version' => $action->referenceDataRelease->version, 'effectiveFrom' => $action->referenceDataRelease->effective_from?->toIso8601String(), 'checksum' => $action->referenceDataRelease->checksum] : null, 'dueOn' => $action->due_on->toDateString(), 'progress' => (float) $action->progress_percentage, 'status' => $action->status, 'canUpdate' => $action->accountable_user_id === $user->id && $action->status !== 'completed' && ! $action->updates->contains(fn (PartnerCollaborationActionUpdate $update): bool => $update->decision === null), 'canUpload' => ($action->accountable_user_id === $user->id || $user->can(ProgrammePermission::ManagePartners->value)) && $action->status !== 'completed', 'documents' => $action->documentLinks->map(fn (DocumentLink $link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'recordStatus' => $link->document->record_status])->values()->all(), 'updates' => $action->updates->map(fn (PartnerCollaborationActionUpdate $update): array => ['id' => $update->id, 'progress' => (float) $update->progress_percentage, 'narrative' => $update->narrative, 'submitter' => $update->submitter->name, 'submittedAt' => $update->submitted_at->toIso8601String(), 'updateChecksum' => $update->update_checksum, 'decision' => $update->decision ? ['result' => $update->decision->decision, 'note' => $update->decision->verification_note, 'verifier' => $update->decision->verifier->name, 'checksum' => $update->decision->decision_checksum] : null, 'canVerify' => $update->decision === null && $update->submitted_by !== $user->id && $user->can(ProgrammePermission::ApprovePartnerAgreements->value)])->values()->all()])->values()->all(),
            ])->values();

        return Inertia::render('partners/index', [
            'workspace' => $workspaceData->partners($user, $filters),
            'filters' => $filters,
            'capabilities' => [
                'manage' => $user->can(ProgrammePermission::ManagePartners->value),
                'submitData' => $user->can(ProgrammePermission::SubmitPartnerData->value) || $user->can(ProgrammePermission::ManagePartners->value),
                'resolveAlerts' => $user->can(ProgrammePermission::ResolveCollaborationAlerts->value),
                'approveAgreements' => $user->can(ProgrammePermission::ApprovePartnerAgreements->value),
            ],
            'alerts' => $alertRows,
            'operationalAlerts' => $operationalAlerts,
            'portfolioMap' => ['showFullCountry' => $showFullCountryMap, 'counties' => $portfolioMap],
            'collaborationPlans' => $collaborationPlans,
            'agreements' => $agreements,
            'contributions' => $contributions,
            'catalogue' => ['available' => $referenceDataRelease !== null, 'version' => $referenceDataRelease?->version, 'effectiveFrom' => $referenceDataRelease?->effective_from?->toIso8601String()],
            'options' => [
                'organizations' => Organization::query()->where('status', 'active')->whereDoesntHave('partnerProfile')->orderBy('name')->get(['id', 'name']),
                'counties' => $visibleCounties->whereIn('id', $governedCountyIds)->map(fn (County $county): array => ['id' => $county->id, 'name' => $county->name])->values(),
                'actionOrganizations' => Organization::query()->where('status', 'active')->whereIn('id', $governedOrganizationIds)->orderBy('name')->get(['id', 'name']),
                'sectors' => Sector::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'users' => User::query()->whereHas('roles', fn (Builder $query) => $query->where('name', 'development-partner'))->orderBy('name')->get(['id', 'name', 'email']),
                'actionUsers' => User::permission(ProgrammePermission::ViewPartnerCoordination->value)->orderBy('name')->get(['id', 'name', 'email', 'county_id'])->filter(fn (User $candidate): bool => $visibleCounties->contains(fn (County $county): bool => $candidate->canAccessCounty($county)))->values(),
                'partners' => $this->visiblePartners($user, $countyScope)->with('organization:id,name')->orderBy('id')->get()->map(fn (PartnerProfile $partner): array => ['id' => $partner->id, 'name' => $partner->organization->name]),
                'projects' => DevolutionProject::query()
                    ->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $countyScope->query($user)->select('id')))
                    ->orderBy('title')->get(['id', 'code', 'title']),
            ],
        ]);
    }

    public function storeProfile(StorePartnerProfileRequest $request, CreatePartnerProfile $createPartnerProfile, PartnerOverlapAnalyzer $analyzer): RedirectResponse
    {
        $user = $this->user($request);
        $createPartnerProfile->handle($user, $request->validated());
        $analyzer->analyze();

        return $this->success(__('partner-coordination.outcomes.profile_created'));
    }

    public function storeAgreement(StorePartnerAgreementRequest $request, CreatePartnerAgreement $createAgreement): RedirectResponse
    {
        $user = $this->user($request);
        $partner = PartnerProfile::query()->whereKey($request->validated('partner_profile_id'))->firstOrFail();
        $createAgreement->handle($partner, $user, $request->validated());

        return $this->success(__('partner-coordination.outcomes.agreement_created'));
    }

    public function storeCollaborationPlan(StorePartnerCollaborationPlanRequest $request, ProgrammeCountyScope $countyScope, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $partner = PartnerProfile::query()->whereKey($request->validated('partner_profile_id'))->firstOrFail();
        abort_unless($this->visiblePartners($user, $countyScope)->whereKey($partner)->exists(), 403);
        $plan = $partner->collaborationPlans()->create([...$request->safe()->except('partner_profile_id'), 'status' => 'draft', 'created_by' => $user->id]);
        $auditLogger->record($user, $plan, 'partner.collaboration_plan.created', __('partner-coordination.audit.plan_created', ['reference' => $plan->reference]));

        return $this->success(__('partner-coordination.outcomes.plan_created'));
    }

    public function transitionCollaborationPlan(TransitionPartnerCollaborationPlanRequest $request, PartnerCollaborationPlan $plan, ProgrammeCountyScope $countyScope, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePartners($user, $countyScope)->whereKey($plan->partner_profile_id)->exists(), 403);
        $transition = $request->string('transition')->toString();
        DB::transaction(function () use ($plan, $user, $transition, $request): void {
            $locked = PartnerCollaborationPlan::query()->with('actions')->lockForUpdate()->findOrFail($plan->id);
            if ($transition === 'submit') {
                abort_unless($user->can(ProgrammePermission::ManagePartners->value) && $locked->status === 'draft', 409);
                $locked->update(['status' => 'pending_approval', 'submitted_by' => $user->id, 'submitted_at' => now()]);
            } elseif (in_array($transition, ['approve', 'reject'], true)) {
                abort_unless($user->can(ProgrammePermission::ApprovePartnerAgreements->value) && $locked->status === 'pending_approval', 403);
                abort_if($locked->submitted_by === $user->id, 403, __('partner-coordination.errors.plan_self_approval'));
                $locked->update(['status' => $transition === 'approve' ? 'active' : 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'decision_note' => $request->string('decision_note')->toString()]);
            } else {
                abort_unless($user->can(ProgrammePermission::ManagePartners->value) && $locked->status === 'active', 409);
                abort_if($locked->actions->isEmpty() || $locked->actions->contains(fn (PartnerCollaborationAction $action): bool => $action->status !== 'completed'), 409, __('partner-coordination.errors.actions_not_complete'));
                $locked->update(['status' => 'completed', 'decision_note' => $request->string('decision_note')->toString()]);
            }
        }, attempts: 3);
        $auditLogger->record($user, $plan, 'partner.collaboration_plan.'.$transition, __('partner-coordination.audit.plan_transition', ['transition' => $transition]), metadata: ['decision_note' => $request->validated('decision_note')]);

        return $this->success(__('partner-coordination.outcomes.plan_transitioned'));
    }

    public function storeCollaborationAction(StorePartnerCollaborationActionRequest $request, PartnerCollaborationPlan $plan, CreatePartnerCollaborationAction $createAction, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePartners($user, $countyScope)->whereKey($plan->partner_profile_id)->exists(), 403);
        $createAction->handle($plan, $user, $request->validated());

        return $this->success(__('partner-coordination.outcomes.action_created'));
    }

    public function storeCollaborationActionUpdate(StorePartnerCollaborationActionUpdateRequest $request, PartnerCollaborationAction $action, RecordPartnerCollaborationActionUpdate $recordUpdate, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($countyScope->query($user)->whereKey($action->county_id)->exists(), 403);
        $recordUpdate->handle($action, $user, $request->validated());

        return $this->success(__('partner-coordination.outcomes.progress_submitted'));
    }

    public function verifyCollaborationActionUpdate(VerifyPartnerCollaborationActionUpdateRequest $request, PartnerCollaborationActionUpdate $update, VerifyPartnerCollaborationActionUpdate $verifyUpdate, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($countyScope->query($user)->whereKey($update->action->county_id)->exists(), 403);
        $verifyUpdate->handle($update, $user, $request->validated());

        return $this->success(__('partner-coordination.outcomes.progress_verified'));
    }

    public function transitionAgreement(TransitionPartnerAgreementRequest $request, PartnerAgreement $agreement, TransitionPartnerAgreement $transitionAgreement, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePartners($user, $countyScope)->whereKey($agreement->partner_profile_id)->exists(), 403);
        $transitionAgreement->handle($agreement, $user, $request->string('transition')->toString(), $request->string('comment')->toString() ?: null);

        return $this->success(__('partner-coordination.outcomes.agreement_transitioned'));
    }

    public function storeAgreementChange(StorePartnerAgreementChangeRequest $request, PartnerAgreement $agreement, CreatePartnerAgreementChange $createChange, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePartners($user, $countyScope)->whereKey($agreement->partner_profile_id)->exists(), 403);
        $createChange->handle($agreement, $user, $request->validated());

        return $this->success(__('partner-coordination.outcomes.change_requested'));
    }

    public function decideAgreementChange(DecidePartnerAgreementChangeRequest $request, PartnerAgreementChangeRequest $changeRequest, DecidePartnerAgreementChange $decideChange, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePartners($user, $countyScope)->whereKey($changeRequest->agreement->partner_profile_id)->exists(), 403);
        $decideChange->handle($changeRequest, $user, $request->validated());

        return $this->success(__('partner-coordination.outcomes.change_decided'));
    }

    public function storeContribution(StorePartnerContributionRequest $request, ProgrammeCountyScope $countyScope, PartnerOverlapAnalyzer $analyzer, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $partner = PartnerProfile::query()->whereKey($request->validated('partner_profile_id'))->firstOrFail();
        $this->authorizePartnerMutation($user, $partner);
        $project = DevolutionProject::query()->whereKey($request->validated('devolution_project_id'))->firstOrFail();
        abort_unless($project->counties()->whereIn('counties.id', $countyScope->query($user)->select('id'))->exists(), 403);
        abort_unless($project->counties()->whereIn('counties.id', $partner->counties()->select('counties.id'))->exists(), 422, __('partner-coordination.errors.project_outside_coverage'));

        $contribution = $partner->contributions()->create([
            ...$request->safe()->except('partner_profile_id'),
            'reported_by' => $user->id,
            'provenance' => [...$request->validated('provenance'), 'captured_by' => $user->id],
        ]);
        $analyzer->analyze();
        $auditLogger->record($user, $contribution, 'partner.contribution.reported', __('partner-coordination.audit.contribution_reported', ['code' => $project->code]), $project->lead_county_id, ['provenance' => $contribution->provenance]);

        return $this->success(__('partner-coordination.outcomes.contribution_recorded'));
    }

    public function analyze(Request $request, PartnerOverlapAnalyzer $analyzer, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManagePartners->value);
        $alerts = $analyzer->analyze();
        $user = $this->user($request);
        $auditLogger->record($user, $user, 'partner.coverage.analyzed', __('partner-coordination.audit.coverage_analyzed'), metadata: ['alerts' => $alerts->count()]);

        return $this->success(trans_choice('partner-coordination.outcomes.analysis_completed', $alerts->count(), ['count' => $alerts->count()]));
    }

    public function reconcileContribution(ReconcilePartnerContributionRequest $request, PartnerContribution $contribution, ReconcilePartnerContribution $reconcileContribution, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePartners($user, $countyScope)->whereKey($contribution->partner_profile_id)->exists(), 403);
        abort_unless($contribution->project->counties()->whereIn('counties.id', $countyScope->query($user)->select('id'))->exists(), 403);
        $reconcileContribution->handle($contribution, $user, $request->validated());

        return $this->success(__('partner-coordination.outcomes.contribution_reconciled'));
    }

    public function resolveAlert(ResolvePartnerCollaborationAlertRequest $request, PartnerCollaborationAlert $alert, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $alert->update([
            ...$request->validated(),
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);
        $auditLogger->record($user, $alert, 'partner.alert.resolved', __('partner-coordination.audit.alert_resolved', ['status' => $alert->status]), metadata: ['resolution' => $alert->resolution]);

        return $this->success(__('partner-coordination.outcomes.alert_resolved'));
    }

    public function resolveOperationalAlert(ResolvePartnerOperationalAlertRequest $request, PartnerOperationalAlert $alert, AuditLogger $auditLogger, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visiblePartners($user, $countyScope)->whereKey($alert->partner_profile_id)->exists(), 403);
        abort_if($alert->status !== 'open', 409, __('partner-coordination.errors.operational_alert_closed'));
        $alert->update(['status' => $request->string('status')->toString(), 'resolution' => $request->string('resolution')->toString(), 'resolved_by' => $user->id, 'resolved_at' => now()]);
        $auditLogger->record($user, $alert, 'partner.operational_alert.resolved', __('partner-coordination.audit.operational_alert_resolved'), $alert->county_id, ['status' => $alert->status, 'resolution' => $alert->resolution]);

        return $this->success(__('partner-coordination.outcomes.operational_alert_resolved'));
    }

    /** @return Builder<PartnerProfile> */
    private function visiblePartners(User $user, ProgrammeCountyScope $countyScope): Builder
    {
        return PartnerProfile::query()->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $countyScope->query($user)->select('id')));
    }

    private function authorizePartnerMutation(User $user, PartnerProfile $partner): void
    {
        if ($user->can(ProgrammePermission::ManagePartners->value)) {
            return;
        }

        abort_unless($partner->users()->whereKey($user)->exists(), 403);
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
