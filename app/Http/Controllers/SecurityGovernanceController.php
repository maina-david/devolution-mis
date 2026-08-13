<?php

namespace App\Http\Controllers;

use App\Actions\CreateAccessDelegation;
use App\Actions\CreateSecurityIncident;
use App\Actions\DecideAccessDelegation;
use App\Actions\DecideAccessReviewItem;
use App\Actions\LaunchAccessReviewCampaign;
use App\Actions\ReinstateUserAccess;
use App\Actions\ReviewEmergencyAccess;
use App\Actions\ReviewSecurityThreat;
use App\Actions\RevokeAccessDelegation;
use App\Actions\TransitionSecurityIncident;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Http\Requests\DecideAccessDelegationRequest;
use App\Http\Requests\DecideAccessReviewItemRequest;
use App\Http\Requests\LaunchAccessReviewRequest;
use App\Http\Requests\ReinstateUserAccessRequest;
use App\Http\Requests\ReviewEmergencyAccessRequest;
use App\Http\Requests\ReviewSecurityThreatRequest;
use App\Http\Requests\RevokeAccessDelegationRequest;
use App\Http\Requests\StoreAccessDelegationRequest;
use App\Http\Requests\StoreSecurityIncidentRequest;
use App\Http\Requests\StoreSecurityThreatRequest;
use App\Http\Requests\TransitionSecurityIncidentRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\AccessDelegation;
use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\IdentityLifecycleRequest;
use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEvent;
use App\Models\SecurityThreat;
use App\Models\SupplyChainScan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\ProgrammeCountyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityGovernanceController extends Controller
{
    public function __construct(private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver, private ProgrammeCountyScope $countyScope) {}

    public function index(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ViewSecurityGovernance->value);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $referenceDataRelease = $this->referenceDataReleaseResolver->availableForSelection(now());
        $governedCountyIds = collect($referenceDataRelease?->snapshot['counties'] ?? [])->pluck('id')->filter()->all();
        $threats = SecurityThreat::query()->with(['owner:id,name', 'submitter:id,name', 'reviewer:id,name'])->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('reference', 'ilike', "%{$search}%")->orWhere('title', 'ilike', "%{$search}%")->orWhere('asset', 'ilike', "%{$search}%")))->when($status, fn (Builder $query, string $status) => $query->where(fn (Builder $query) => $query->where('status', $status)->orWhere('treatment_status', $status)))->when($request->filled('from'), fn (Builder $query) => $query->whereDate('submitted_at', '>=', $request->date('from')))->when($request->filled('to'), fn (Builder $query) => $query->whereDate('submitted_at', '<=', $request->date('to')))->latest('submitted_at')->paginate($request->integer('per_page', 10), ['*'], 'threat_page')->withQueryString();
        $items = AccessReviewItem::query()
            ->with(['campaign:id,reference,name,status,reviewer_id,due_at,evidence_checksum', 'user:id,name,email,access_revoked_at,two_factor_confirmed_at', 'reviewer:id,name', 'reinstater:id,name', 'homeCounty:id,name,code,logo_path'])
            ->when($search, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('role_name', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))
                    ->orWhereHas('campaign', fn (Builder $campaign) => $campaign->where('reference', 'ilike', "%{$search}%"));
            }))
            ->when($status, fn (Builder $query, string $status) => $query->where('decision', $status))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate($request->integer('per_page', 10), ['*'], 'access_page')
            ->withQueryString();
        $delegations = AccessDelegation::query()->with(['referenceDataRelease:id,version,effective_from,checksum', 'requester:id,name', 'beneficiary:id,name,email,two_factor_confirmed_at', 'beneficiary.passkeys', 'approver:id,name', 'revoker:id,name', 'reviewer:id,name'])->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('reference', 'ilike', "%{$search}%")->orWhere('incident_reference', 'ilike', "%{$search}%")->orWhereHas('beneficiary', fn (Builder $user) => $user->where('name', 'ilike', "%{$search}%"))))->when($status, fn (Builder $query, string $status) => $query->where('status', $status))->when($request->filled('county_id'), fn (Builder $query) => $query->whereJsonContains('county_scope_snapshot', [['id' => $request->string('county_id')->toString()]]))->when($request->filled('from'), fn (Builder $query) => $query->whereDate('starts_at', '>=', $request->date('from')))->when($request->filled('to'), fn (Builder $query) => $query->whereDate('starts_at', '<=', $request->date('to')))->latest()->paginate($request->integer('per_page', 10), ['*'], 'delegations_page')->withQueryString();
        $supplyChainScans = SupplyChainScan::query()
            ->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('id', 'ilike', "%{$search}%")->orWhere('source_revision', 'ilike', "%{$search}%")->orWhere('initiated_by_name', 'ilike', "%{$search}%")))
            ->when($status, fn (Builder $query, string $status) => $query->where('outcome', $status))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('started_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('started_at', '<=', $request->date('to')))
            ->latest('started_at')
            ->paginate($request->integer('per_page', 10), ['*'], 'scan_page')
            ->withQueryString();
        $securityIncidents = SecurityIncident::query()
            ->with(['reporter:id,name', 'incidentLead:id,name', 'closer:id,name', 'events:id,security_incident_id,actor_id,actor_name,transition,from_status,to_status,narrative,evidence_reference,occurred_at,evidence_checksum', 'documentLinks.document.currentVersion'])
            ->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('reference', 'ilike', "%{$search}%")->orWhere('title', 'ilike', "%{$search}%")->orWhere('playbook', 'ilike', "%{$search}%")->orWhere('external_reference', 'ilike', "%{$search}%")))
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('detected_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('detected_at', '<=', $request->date('to')))
            ->latest('detected_at')
            ->paginate($request->integer('per_page', 10), ['*'], 'incident_page')
            ->withQueryString();
        $permanentPermissions = $request->user()?->getAllPermissions()->pluck('name') ?? collect();
        $identityLifecycle = IdentityLifecycleRequest::query()
            ->with(['user:id,name,email,county_id,access_revoked_at', 'requester:id,name', 'decider:id,name', 'applier:id,name', 'proposedHomeCounty:id,name,code,logo_path'])
            ->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('source_system', 'ilike', "%{$search}%")->orWhere('source_event_id', 'ilike', "%{$search}%")->orWhereHas('user', fn (Builder $user) => $user->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))))
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('effective_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('effective_at', '<=', $request->date('to')))
            ->latest()
            ->paginate($request->integer('per_page', 10), ['*'], 'identity_page')
            ->withQueryString();
        $delegationReferenceData = $delegations->getCollection()->mapWithKeys(fn (AccessDelegation $delegation): array => [$delegation->id => $delegation->referenceDataRelease === null ? null : ['version' => $delegation->referenceDataRelease->version, 'effectiveFrom' => $delegation->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $delegation->referenceDataRelease->checksum]]);

        return Inertia::render('security-governance/index', [
            'identityLifecycle' => $identityLifecycle->through(fn (IdentityLifecycleRequest $change): array => ['id' => $change->id, 'sourceSystem' => $change->source_system, 'sourceEventId' => $change->source_event_id, 'sourceEvidenceReference' => $change->source_evidence_reference, 'sourceChecksum' => $change->source_checksum, 'eventType' => $change->event_type, 'effectiveAt' => $change->effective_at->toIso8601String(), 'currentAccess' => $change->current_access_snapshot, 'proposedRole' => $change->proposed_role, 'proposedHomeCounty' => $change->proposedHomeCounty?->identityCell(), 'proposedAssignedCountyIds' => $change->proposed_assigned_county_ids, 'businessReason' => $change->business_reason, 'status' => $change->status, 'decisionRationale' => $change->decision_rationale, 'decidedAt' => $change->decided_at?->toIso8601String(), 'appliedAt' => $change->applied_at?->toIso8601String(), 'applicationAttempts' => $change->application_attempts, 'lastApplicationAttemptAt' => $change->last_application_attempt_at?->toIso8601String(), 'applicationErrorCode' => $change->application_error_code, 'sessionsRevoked' => $change->sessions_revoked, 'evidenceChecksum' => $change->evidence_checksum, 'user' => ['id' => $change->user->id, 'name' => $change->user->name, 'email' => $change->user->email, 'accessRevokedAt' => $change->user->access_revoked_at?->toIso8601String()], 'requester' => $change->requester->name, 'decider' => $change->decider?->name, 'applier' => $change->applier?->name]),
            'threats' => $threats->through(fn (SecurityThreat $threat): array => ['id' => $threat->id, 'reference' => $threat->reference, 'title' => $threat->title, 'category' => $threat->stride_category, 'asset' => $threat->asset, 'scenario' => $threat->scenario, 'threatActor' => $threat->threat_actor, 'entryPoints' => $threat->entry_points, 'likelihood' => $threat->likelihood, 'impact' => $threat->impact, 'inherentRiskScore' => $threat->inherent_risk_score, 'existingControls' => $threat->existing_controls, 'treatmentPlan' => $threat->treatment_plan, 'treatmentStatus' => $threat->treatment_status, 'residualRiskScore' => $threat->residual_risk_score, 'riskAcceptanceReference' => $threat->risk_acceptance_reference, 'status' => $threat->status, 'submittedAt' => $threat->submitted_at->toIso8601String(), 'reviewedAt' => $threat->reviewed_at?->toIso8601String(), 'reviewDueAt' => $threat->review_due_at->toDateString(), 'evidenceReferences' => $threat->evidence_references ?? [], 'owner' => $threat->owner?->name, 'submitter' => $threat->submitter?->name, 'reviewer' => $threat->reviewer?->name]),
            'campaigns' => AccessReviewCampaign::query()->with(['launcher:id,name', 'reviewer:id,name'])->latest('launched_at')->limit(12)->get()->map(fn (AccessReviewCampaign $campaign): array => ['id' => $campaign->id, 'reference' => $campaign->reference, 'name' => $campaign->name, 'scope' => $campaign->scope, 'roleScope' => $campaign->role_scope, 'status' => $campaign->status, 'periodFrom' => $campaign->period_from->toDateString(), 'periodTo' => $campaign->period_to->toDateString(), 'dueAt' => $campaign->due_at->toIso8601String(), 'launchedAt' => $campaign->launched_at->toIso8601String(), 'completedAt' => $campaign->completed_at?->toIso8601String(), 'itemCount' => $campaign->item_count, 'retainedCount' => $campaign->retained_count, 'revokedCount' => $campaign->revoked_count, 'remediationCount' => $campaign->remediation_count, 'checksum' => $campaign->evidence_checksum, 'launcher' => $campaign->launcher?->name, 'reviewer' => $campaign->reviewer?->name])->values(),
            'accessItems' => $items->through(fn (AccessReviewItem $item): array => ['id' => $item->id, 'campaign' => ['id' => $item->campaign->id, 'reference' => $item->campaign->reference, 'name' => $item->campaign->name, 'status' => $item->campaign->status, 'reviewerId' => $item->campaign->reviewer_id, 'dueAt' => $item->campaign->due_at->toIso8601String(), 'checksum' => $item->campaign->evidence_checksum], 'user' => ['id' => $item->user->id, 'name' => $item->user->name, 'email' => $item->user->email, 'accessRevokedAt' => $item->user->access_revoked_at?->toIso8601String()], 'role' => $item->role_name, 'permissions' => $item->permission_snapshot, 'homeCounty' => $item->homeCounty?->identityCell(), 'assignedCounties' => $item->assigned_county_snapshot, 'mfaEnabled' => $item->mfa_enabled, 'passkeyEnabled' => $item->passkey_enabled, 'lastAuthenticatedAt' => $item->last_authenticated_at?->toIso8601String(), 'decision' => $item->decision, 'rationale' => $item->rationale, 'remediationAction' => $item->remediation_action, 'remediationDueAt' => $item->remediation_due_at?->toDateString(), 'reviewedAt' => $item->reviewed_at?->toIso8601String(), 'revokedAt' => $item->revoked_at?->toIso8601String(), 'sessionsRevoked' => $item->sessions_revoked, 'reviewer' => $item->reviewer?->name, 'reinstatedAt' => $item->reinstated_at?->toIso8601String(), 'reinstater' => $item->reinstater?->name, 'reinstatementRationale' => $item->reinstatement_rationale]),
            'delegations' => $delegations->through(fn (AccessDelegation $delegation): array => ['id' => $delegation->id, 'reference' => $delegation->reference, 'accessType' => $delegation->access_type, 'scopeType' => $delegation->scope_type, 'permissions' => $delegation->permission_scope, 'counties' => $delegation->county_scope_snapshot, 'businessJustification' => $delegation->business_justification, 'incidentReference' => $delegation->incident_reference, 'compensatingControls' => $delegation->compensating_controls, 'status' => $delegation->status, 'startsAt' => $delegation->starts_at->toIso8601String(), 'expiresAt' => $delegation->expires_at->toIso8601String(), 'approvedAt' => $delegation->approved_at?->toIso8601String(), 'activatedAt' => $delegation->activated_at?->toIso8601String(), 'expiredAt' => $delegation->expired_at?->toIso8601String(), 'revokedAt' => $delegation->revoked_at?->toIso8601String(), 'reviewedAt' => $delegation->reviewed_at?->toIso8601String(), 'decisionRationale' => $delegation->decision_rationale, 'revocationReason' => $delegation->revocation_reason, 'postUseOutcome' => $delegation->post_use_outcome, 'postUseFindings' => $delegation->post_use_findings, 'approvalChecksum' => $delegation->approval_checksum, 'requester' => $delegation->requester->name, 'beneficiary' => ['id' => $delegation->beneficiary->id, 'name' => $delegation->beneficiary->name, 'email' => $delegation->beneficiary->email, 'strongAuth' => $delegation->beneficiary->two_factor_confirmed_at !== null || $delegation->beneficiary->passkeys()->exists()], 'approver' => $delegation->approver?->name, 'revoker' => $delegation->revoker?->name, 'reviewer' => $delegation->reviewer?->name]),
            'delegationReferenceData' => $delegationReferenceData,
            'supplyChainScans' => $supplyChainScans->through(fn (SupplyChainScan $scan): array => ['id' => $scan->id, 'environment' => $scan->environment, 'sourceRevision' => $scan->source_revision, 'sourceState' => $scan->source_state, 'composerLockChecksum' => $scan->composer_lock_checksum, 'javascriptLockChecksum' => $scan->javascript_lock_checksum, 'javascriptLockfile' => $scan->javascript_lockfile, 'composerComponentCount' => $scan->composer_component_count, 'javascriptComponentCount' => $scan->javascript_component_count, 'composerAdvisoryCount' => $scan->composer_advisory_count, 'npmInfoCount' => $scan->npm_info_count, 'npmLowCount' => $scan->npm_low_count, 'npmModerateCount' => $scan->npm_moderate_count, 'npmHighCount' => $scan->npm_high_count, 'npmCriticalCount' => $scan->npm_critical_count, 'findingCodes' => $scan->finding_codes, 'toolVersions' => $scan->tool_versions, 'sbomFormat' => $scan->sbom_format, 'sbomSpecVersion' => $scan->sbom_spec_version, 'sizeBytes' => $scan->size_bytes, 'artifactChecksum' => $scan->artifact_checksum, 'evidenceChecksum' => $scan->evidence_checksum, 'outcome' => $scan->outcome, 'failureCategory' => $scan->failure_category, 'initiatedBy' => $scan->initiated_by_name, 'startedAt' => $scan->started_at->toIso8601String(), 'completedAt' => $scan->completed_at->toIso8601String(), 'downloadable' => $scan->path !== null]),
            'securityIncidents' => $securityIncidents->through(fn (SecurityIncident $incident): array => ['id' => $incident->id, 'reference' => $incident->reference, 'recordType' => $incident->record_type, 'playbook' => $incident->playbook, 'title' => $incident->title, 'summary' => $incident->summary, 'affectedServices' => $incident->affected_services, 'dataExposure' => $incident->data_exposure, 'severity' => $incident->severity, 'status' => $incident->status, 'businessImpact' => $incident->business_impact, 'externalReference' => $incident->external_reference, 'exerciseObjectives' => $incident->exercise_objectives, 'exerciseOutcome' => $incident->exercise_outcome, 'detectedAt' => $incident->detected_at->toIso8601String(), 'acknowledgementDueAt' => $incident->acknowledgement_due_at->toIso8601String(), 'containmentDueAt' => $incident->containment_due_at->toIso8601String(), 'acknowledgedAt' => $incident->acknowledged_at?->toIso8601String(), 'containedAt' => $incident->contained_at?->toIso8601String(), 'eradicatedAt' => $incident->eradicated_at?->toIso8601String(), 'recoveredAt' => $incident->recovered_at?->toIso8601String(), 'closedAt' => $incident->closed_at?->toIso8601String(), 'escalatedAt' => $incident->escalated_at?->toIso8601String(), 'nextExerciseDueAt' => $incident->next_exercise_due_at?->toIso8601String(), 'rootCause' => $incident->root_cause, 'correctiveActions' => $incident->corrective_actions, 'lessonsLearned' => $incident->lessons_learned, 'reporter' => ['id' => $incident->reported_by, 'name' => $incident->reporter->name], 'incidentLead' => ['id' => $incident->incident_lead_id, 'name' => $incident->incidentLead->name], 'closer' => $incident->closer?->name, 'events' => $incident->events->map(fn (SecurityIncidentEvent $event): array => ['id' => $event->id, 'actorName' => $event->actor_name, 'transition' => $event->transition, 'fromStatus' => $event->from_status, 'toStatus' => $event->to_status, 'narrative' => $event->narrative, 'evidenceReference' => $event->evidence_reference, 'occurredAt' => $event->occurred_at->toIso8601String(), 'evidenceChecksum' => $event->evidence_checksum])->values()->all(), 'documents' => $incident->documentLinks->map(fn ($link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'purpose' => $link->purpose, 'sourceType' => $link->document->source_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status, 'checksum' => $link->document->content_checksum, 'mimeType' => $link->document->currentVersion?->mime_type, 'originalName' => $link->document->currentVersion?->original_name])->values()->all()]),
            'users' => User::query()->whereNull('access_revoked_at')->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [UserRole::DevolutionAdmin->value, UserRole::PlatformAdmin->value]))->orderBy('name')->get(['id', 'name'])->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])->values(),
            'delegationUsers' => User::query()->whereNull('access_revoked_at')->orderBy('name')->get(['id', 'name', 'two_factor_confirmed_at'])->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name, 'strongAuth' => $user->two_factor_confirmed_at !== null || $user->passkeys()->exists()])->values(),
            'identityUsers' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'access_revoked_at'])->map(function (User $user): array {
                $role = $user->roles()->value('name');

                return ['value' => $user->id, 'label' => "{$user->name} · {$user->email}", 'revoked' => $user->access_revoked_at !== null, 'role' => is_string($role) ? $role : null];
            })->values(),
            'delegablePermissions' => collect(ProgrammePermission::cases())->reject(fn (ProgrammePermission $permission): bool => in_array($permission, [ProgrammePermission::ManageUserAccess, ProgrammePermission::ConfigurePlatform, ProgrammePermission::ManageSecurityGovernance, ProgrammePermission::CertifyAccess], true))->filter(fn (ProgrammePermission $permission): bool => $permanentPermissions->contains($permission->value))->map(fn (ProgrammePermission $permission): array => ['value' => $permission->value, 'label' => $permission->label()])->values(),
            'counties' => $this->countyScope->query($this->user($request))->whereIn('id', $governedCountyIds)->orderBy('code')->get()->map->identityCell()->values(),
            'referenceDataCatalogue' => $referenceDataRelease === null ? ['available' => false] : ['available' => true, 'version' => $referenceDataRelease->version, 'effectiveFrom' => $referenceDataRelease->effective_from?->toDateString(), 'checksum' => $referenceDataRelease->checksum],
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()])->values(),
            'filters' => $request->safe()->only(['search', 'from', 'to', 'status', 'county_id', 'per_page']),
            'capabilities' => ['manage' => $request->user()?->can(ProgrammePermission::ManageSecurityGovernance->value) === true, 'certify' => $request->user()?->can(ProgrammePermission::CertifyAccess->value) === true, 'userId' => $request->user()?->id],
        ]);
    }

    public function storeIncident(StoreSecurityIncidentRequest $request, CreateSecurityIncident $action): RedirectResponse
    {
        $action->handle($this->user($request), $request->validated());

        return back()->with('success', __('security.outcomes.incident_created'));
    }

    public function transitionIncident(TransitionSecurityIncidentRequest $request, SecurityIncident $securityIncident, TransitionSecurityIncident $action): RedirectResponse
    {
        $action->handle($securityIncident, $this->user($request), $request->validated());

        return back()->with('success', __('security.outcomes.incident_transitioned'));
    }

    public function downloadSupplyChainArtifact(Request $request, SupplyChainScan $supplyChainScan): StreamedResponse
    {
        Gate::authorize(ProgrammePermission::ViewSecurityGovernance->value);
        abort_unless($supplyChainScan->path !== null && $supplyChainScan->artifact_checksum !== null, 404);
        $disk = Storage::disk($supplyChainScan->disk);
        abort_unless($disk->exists($supplyChainScan->path), 404);

        $stream = $disk->readStream($supplyChainScan->path);
        abort_unless(is_resource($stream), 404);
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);
        abort_unless(hash_equals($supplyChainScan->artifact_checksum, hash_final($context)), 409, 'The retained SBOM failed its integrity check.');

        $this->auditLogger->record($this->user($request), $supplyChainScan, 'security.supply-chain.artifact-downloaded', "Supply-chain artifact {$supplyChainScan->id} downloaded after checksum verification.");

        return $disk->download($supplyChainScan->path, 'idmis-'.$supplyChainScan->id.'.cdx.json', ['Content-Type' => $supplyChainScan->mime_type]);
    }

    public function storeThreat(StoreSecurityThreatRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $attributes = $request->validated();
        $threat = SecurityThreat::create([...$attributes, 'submitted_by' => $user->id, 'entry_points' => $this->csv($attributes['entry_points']), 'existing_controls' => $this->csv($attributes['existing_controls']), 'evidence_references' => $this->csv($attributes['evidence_references'] ?? null), 'inherent_risk_score' => (int) $attributes['likelihood'] * (int) $attributes['impact'], 'status' => 'submitted', 'submitted_at' => now()]);
        $this->auditLogger->record($user, $threat, 'security.threat.submitted', "Threat {$threat->reference} submitted for independent review.");

        return back()->with('success', __('security.outcomes.threat_submitted'));
    }

    public function reviewThreat(ReviewSecurityThreatRequest $request, SecurityThreat $securityThreat, ReviewSecurityThreat $action): RedirectResponse
    {
        $action->handle($securityThreat, $this->user($request), ['decision' => (string) $request->validated('decision'), 'treatment_status' => (string) $request->validated('treatment_status'), 'residual_likelihood' => (int) $request->validated('residual_likelihood'), 'residual_impact' => (int) $request->validated('residual_impact'), 'risk_acceptance_reference' => $request->validated('risk_acceptance_reference'), 'review_note' => (string) $request->validated('review_note'), 'evidence_references' => $request->validated('evidence_references')]);

        return back()->with('success', __('security.outcomes.threat_reviewed'));
    }

    public function launchAccessReview(LaunchAccessReviewRequest $request, LaunchAccessReviewCampaign $action): RedirectResponse
    {
        $attributes = $request->validated();
        $action->handle($this->user($request), ['reviewer_id' => (string) $attributes['reviewer_id'], 'reference' => (string) $attributes['reference'], 'name' => (string) $attributes['name'], 'scope' => (string) $attributes['scope'], 'role_scope' => array_values($attributes['role_scope']), 'period_from' => (string) $attributes['period_from'], 'period_to' => (string) $attributes['period_to'], 'due_at' => (string) $attributes['due_at']]);

        return back()->with('success', __('security.outcomes.campaign_launched'));
    }

    public function decideAccess(DecideAccessReviewItemRequest $request, AccessReviewItem $accessReviewItem, DecideAccessReviewItem $action): RedirectResponse
    {
        $action->handle($accessReviewItem, $this->user($request), ['decision' => (string) $request->validated('decision'), 'rationale' => (string) $request->validated('rationale'), 'remediation_action' => $request->validated('remediation_action'), 'remediation_due_at' => $request->validated('remediation_due_at')]);

        return back()->with('success', __('security.outcomes.certification_recorded'));
    }

    public function reinstateAccess(ReinstateUserAccessRequest $request, AccessReviewItem $accessReviewItem, ReinstateUserAccess $action): RedirectResponse
    {
        $action->handle($accessReviewItem, $this->user($request), ['rationale' => (string) $request->validated('rationale'), 'approval_reference' => (string) $request->validated('approval_reference')]);

        return back()->with('success', __('security.outcomes.access_reinstated'));
    }

    public function storeDelegation(StoreAccessDelegationRequest $request, CreateAccessDelegation $action): RedirectResponse
    {
        $action->handle($this->user($request), $request->validated());

        return back()->with('success', __('security.outcomes.temporary_access_submitted'));
    }

    public function decideDelegation(DecideAccessDelegationRequest $request, AccessDelegation $accessDelegation, DecideAccessDelegation $action): RedirectResponse
    {
        $action->handle($accessDelegation, $this->user($request), ['decision' => (string) $request->validated('decision'), 'decision_rationale' => (string) $request->validated('decision_rationale')]);

        return back()->with('success', __('security.outcomes.temporary_access_decided'));
    }

    public function revokeDelegation(RevokeAccessDelegationRequest $request, AccessDelegation $accessDelegation, RevokeAccessDelegation $action): RedirectResponse
    {
        $action->handle($accessDelegation, $this->user($request), (string) $request->validated('revocation_reason'));

        return back()->with('success', __('security.outcomes.temporary_access_revoked'));
    }

    public function reviewEmergencyAccess(ReviewEmergencyAccessRequest $request, AccessDelegation $accessDelegation, ReviewEmergencyAccess $action): RedirectResponse
    {
        $action->handle($accessDelegation, $this->user($request), ['post_use_outcome' => (string) $request->validated('post_use_outcome'), 'post_use_findings' => (string) $request->validated('post_use_findings')]);

        return back()->with('success', __('security.outcomes.emergency_review_recorded'));
    }

    /** @return list<string> */
    private function csv(?string $value): array
    {
        return array_values(array_unique(array_filter(array_map(trim(...), explode(',', $value ?? '')), fn (string $item): bool => $item !== '')));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
