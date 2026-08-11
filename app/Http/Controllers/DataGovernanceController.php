<?php

namespace App\Http\Controllers;

use App\Actions\AdvanceDataSubjectRequest;
use App\Actions\AdvancePrivacyIncident;
use App\Actions\ReviewProcessingActivity;
use App\Enums\ProgrammePermission;
use App\Http\Requests\AdvanceDataSubjectRequestRequest;
use App\Http\Requests\AdvancePrivacyIncidentRequest;
use App\Http\Requests\ReviewProcessingActivityRequest;
use App\Http\Requests\StoreDataAssetRequest;
use App\Http\Requests\StoreDataSubjectRequestRequest;
use App\Http\Requests\StorePrivacyIncidentRequest;
use App\Http\Requests\StoreProcessingActivityRequest;
use App\Http\Requests\StoreRetentionScheduleRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\County;
use App\Models\DataAsset;
use App\Models\DataSubjectRequest;
use App\Models\PrivacyIncident;
use App\Models\ProcessingActivity;
use App\Models\RetentionSchedule;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DataGovernanceController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ViewDataGovernance->value);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $canManage = $request->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
        $activities = ProcessingActivity::query()->with(['dataAsset:id,code,name,classification,contains_personal_data,contains_sensitive_personal_data', 'retentionSchedule:id,code,record_class,status', 'submitter:id,name', 'reviewer:id,name'])->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('reference', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")->orWhere('purpose', 'ilike', "%{$search}%")))->when($status, fn (Builder $query, string $status) => $query->where('status', $status))->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))->latest()->paginate($request->integer('per_page', 10), ['*'], 'activities_page')->withQueryString();
        $dataRequests = DataSubjectRequest::query()->with(['assignee:id,name', 'identityVerifier:id,name', 'decisionMaker:id,name'])->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('reference', 'ilike', "%{$search}%")->orWhere('scope', 'ilike', "%{$search}%")))->when($status, fn (Builder $query, string $status) => $query->where('status', $status))->when($request->filled('from'), fn (Builder $query) => $query->whereDate('received_at', '>=', $request->date('from')))->when($request->filled('to'), fn (Builder $query) => $query->whereDate('received_at', '<=', $request->date('to')))->latest('received_at')->paginate($request->integer('per_page', 10), ['*'], 'requests_page')->withQueryString();
        $incidents = PrivacyIncident::query()->with(['dataAsset:id,code,name,classification', 'county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'reporter:id,name', 'incidentLead:id,name', 'assessor:id,name', 'closer:id,name', 'documentLinks.document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status,record_status'])->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query->where('reference', 'ilike', "%{$search}%")->orWhere('title', 'ilike', "%{$search}%")))->when($status, fn (Builder $query, string $status) => $query->where('status', $status))->when($request->filled('county_id'), fn (Builder $query) => $query->where('county_id', $request->string('county_id')->toString()))->when($request->filled('from'), fn (Builder $query) => $query->whereDate('discovered_at', '>=', $request->date('from')))->when($request->filled('to'), fn (Builder $query) => $query->whereDate('discovered_at', '<=', $request->date('to')))->latest('discovered_at')->paginate($request->integer('per_page', 10), ['*'], 'incidents_page')->withQueryString();

        return Inertia::render('data-governance/index', [
            'assets' => DataAsset::query()->with(['dataOwner:id,name', 'steward:id,name'])->withCount('processingActivities')->orderBy('code')->get()->map(fn (DataAsset $asset): array => ['id' => $asset->id, 'code' => $asset->code, 'name' => $asset->name, 'description' => $asset->description, 'module' => $asset->module, 'authoritativeSource' => $asset->authoritative_source, 'classification' => $asset->classification, 'containsPersonalData' => $asset->contains_personal_data, 'containsSensitivePersonalData' => $asset->contains_sensitive_personal_data, 'personalDataCategories' => $asset->personal_data_categories ?? [], 'dataSubjectCategories' => $asset->data_subject_categories ?? [], 'storageLocations' => $asset->storage_locations, 'residencyCountry' => $asset->residency_country, 'qualityStandard' => $asset->quality_standard, 'status' => $asset->status, 'owner' => $asset->dataOwner?->name, 'steward' => $asset->steward?->name, 'processingActivityCount' => $asset->processing_activities_count])->values(),
            'retentionSchedules' => RetentionSchedule::query()->with('approver:id,name')->orderBy('code')->get()->map(fn (RetentionSchedule $schedule): array => ['id' => $schedule->id, 'code' => $schedule->code, 'recordClass' => $schedule->record_class, 'triggerEvent' => $schedule->trigger_event, 'retentionMonths' => $schedule->retention_months, 'dispositionAction' => $schedule->disposition_action, 'legalAuthority' => $schedule->legal_authority, 'legalHoldRule' => $schedule->legal_hold_rule, 'status' => $schedule->status, 'effectiveFrom' => $schedule->effective_from?->toIso8601String(), 'nextReviewAt' => $schedule->next_review_at?->toDateString(), 'approver' => $schedule->approver?->name])->values(),
            'activities' => $activities->through(fn (ProcessingActivity $activity): array => ['id' => $activity->id, 'reference' => $activity->reference, 'name' => $activity->name, 'purpose' => $activity->purpose, 'lawfulBasis' => $activity->lawful_basis, 'lawfulBasisReference' => $activity->lawful_basis_reference, 'controllerName' => $activity->controller_name, 'processorNames' => $activity->processor_names ?? [], 'recipientCategories' => $activity->recipient_categories ?? [], 'processingOperations' => $activity->processing_operations, 'automatedDecisionMaking' => $activity->automated_decision_making, 'crossBorderTransfer' => $activity->cross_border_transfer, 'transferCountries' => $activity->transfer_countries ?? [], 'transferSafeguards' => $activity->transfer_safeguards, 'dpiaStatus' => $activity->dpia_status, 'dpiaReference' => $activity->dpia_reference, 'riskSummary' => $activity->risk_summary, 'securityMeasures' => $activity->security_measures, 'status' => $activity->status, 'submittedAt' => $activity->submitted_at?->toIso8601String(), 'reviewedAt' => $activity->reviewed_at?->toIso8601String(), 'nextReviewAt' => $activity->next_review_at?->toDateString(), 'asset' => ['id' => $activity->dataAsset->id, 'code' => $activity->dataAsset->code, 'name' => $activity->dataAsset->name, 'classification' => $activity->dataAsset->classification, 'personal' => $activity->dataAsset->contains_personal_data, 'sensitive' => $activity->dataAsset->contains_sensitive_personal_data], 'retentionSchedule' => ['id' => $activity->retentionSchedule?->id, 'code' => $activity->retentionSchedule?->code, 'name' => $activity->retentionSchedule?->record_class], 'submitter' => $activity->submitter?->name, 'reviewer' => $activity->reviewer?->name]),
            'dataSubjectRequests' => $dataRequests->through(fn (DataSubjectRequest $privacyRequest): array => ['id' => $privacyRequest->id, 'reference' => $privacyRequest->reference, 'requestType' => $privacyRequest->request_type, 'requesterName' => $canManage ? $privacyRequest->requester_name : Str::mask($privacyRequest->requester_name, '*', 1), 'requesterContact' => $canManage ? $privacyRequest->requester_contact : 'Restricted', 'contactChannel' => $privacyRequest->contact_channel, 'scope' => $privacyRequest->scope, 'identityStatus' => $privacyRequest->identity_status, 'identityEvidenceReference' => $privacyRequest->identity_evidence_reference, 'status' => $privacyRequest->status, 'receivedAt' => $privacyRequest->received_at->toIso8601String(), 'dueAt' => $privacyRequest->due_at->toIso8601String(), 'acknowledgedAt' => $privacyRequest->acknowledged_at?->toIso8601String(), 'decidedAt' => $privacyRequest->decided_at?->toIso8601String(), 'decision' => $privacyRequest->decision, 'decisionReason' => $privacyRequest->decision_reason, 'responseEvidenceReference' => $privacyRequest->response_evidence_reference, 'assignee' => $privacyRequest->assignee?->name, 'identityVerifier' => $privacyRequest->identityVerifier?->name, 'decisionMaker' => $privacyRequest->decisionMaker?->name, 'overdue' => $privacyRequest->due_at->isPast() && ! in_array($privacyRequest->status, ['completed', 'rejected'], true)]),
            'privacyIncidents' => $incidents->through(fn (PrivacyIncident $incident): array => $this->privacyIncidentPayload($incident, $canManage)),
            'counties' => County::query()->orderBy('code')->get(['id', 'name', 'code', 'logo_path', 'logo_source_authority', 'logo_verified_at'])->map(fn (County $county): array => ['value' => $county->id, 'label' => "{$county->code} · {$county->name}", 'county' => $county->identityCell()])->values(),
            'users' => User::query()->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['devolution-admin', 'platform-admin']))->orderBy('name')->get(['id', 'name'])->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])->values(),
            'filters' => $request->safe()->only(['search', 'from', 'to', 'status', 'county_id', 'per_page']),
            'capabilities' => ['manage' => $canManage],
            'targets' => ['dataSubjectRequestDays' => config('privacy.data_subject_request_target_days'), 'processingReviewMonths' => config('privacy.processing_review_months'), 'controllerNotificationHours' => config('privacy.controller_notification_hours'), 'regulatorNotificationHours' => config('privacy.regulator_notification_hours')],
        ]);
    }

    public function storeAsset(StoreDataAssetRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $asset = DataAsset::create([...$attributes, 'personal_data_categories' => $this->csv($attributes['personal_data_categories'] ?? null), 'data_subject_categories' => $this->csv($attributes['data_subject_categories'] ?? null), 'storage_locations' => $this->csv($attributes['storage_locations']), 'residency_country' => mb_strtoupper($attributes['residency_country']), 'status' => 'active', 'reviewed_at' => now()]);
        $this->auditLogger->record($this->user($request), $asset, 'privacy.data-asset.registered', "Data asset {$asset->code} registered.");

        return back()->with('success', 'Data asset registered.');
    }

    public function storeRetentionSchedule(StoreRetentionScheduleRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $schedule = RetentionSchedule::create([...$request->validated(), 'approved_by' => $user->id, 'status' => 'approved', 'approved_at' => now(), 'effective_from' => now()]);
        $this->auditLogger->record($user, $schedule, 'privacy.retention-schedule.approved', "Retention schedule {$schedule->code} approved.");

        return back()->with('success', 'Retention schedule approved and recorded.');
    }

    public function storeProcessingActivity(StoreProcessingActivityRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $attributes = $request->validated();
        $activity = ProcessingActivity::create([...$attributes, 'processor_names' => $this->csv($attributes['processor_names'] ?? null), 'recipient_categories' => $this->csv($attributes['recipient_categories'] ?? null), 'processing_operations' => $this->csv($attributes['processing_operations']), 'transfer_countries' => $this->csv($attributes['transfer_countries'] ?? null), 'submitted_by' => $user->id, 'status' => 'submitted', 'submitted_at' => now()]);
        $this->auditLogger->record($user, $activity, 'privacy.processing-activity.submitted', "Processing activity {$activity->reference} submitted for independent review.");

        return back()->with('success', 'Processing activity submitted for independent review.');
    }

    public function reviewProcessingActivity(ReviewProcessingActivityRequest $request, string $currentTeam, ProcessingActivity $processingActivity, ReviewProcessingActivity $action): RedirectResponse
    {
        $action->handle($processingActivity, $this->user($request), ['decision' => (string) $request->validated('decision'), 'review_note' => (string) $request->validated('review_note')]);

        return back()->with('success', 'Processing activity review recorded.');
    }

    public function storeDataSubjectRequest(StoreDataSubjectRequestRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $receivedAt = $request->date('received_at');
        $privacyRequest = DataSubjectRequest::create([...$request->validated(), 'reference' => 'DSR-'.now()->format('Y').'-'.mb_strtoupper(Str::random(8)), 'received_at' => $receivedAt, 'due_at' => $receivedAt->copy()->addDays((int) config('privacy.data_subject_request_target_days')), 'status' => 'received', 'metadata' => ['intake_actor_id' => $user->id]]);
        $this->auditLogger->record($user, $privacyRequest, 'privacy.data-subject-request.received', "Privacy request {$privacyRequest->reference} received.");

        return back()->with('success', 'Privacy request recorded with a controlled due date.');
    }

    public function advanceDataSubjectRequest(AdvanceDataSubjectRequestRequest $request, string $currentTeam, DataSubjectRequest $dataSubjectRequest, AdvanceDataSubjectRequest $action): RedirectResponse
    {
        $action->handle($dataSubjectRequest, $this->user($request), $request->validated());

        return back()->with('success', 'Privacy request workflow advanced.');
    }

    public function storePrivacyIncident(StorePrivacyIncidentRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $attributes = $request->validated();
        $discoveredAt = $request->date('discovered_at');
        $incident = PrivacyIncident::create([...$attributes, 'reported_by' => $user->id, 'reference' => 'PBI-'.now()->format('Y').'-'.mb_strtoupper(Str::random(8)), 'personal_data_categories' => $this->csv((string) $attributes['personal_data_categories']), 'discovered_at' => $discoveredAt, 'controller_notification_due_at' => $attributes['controller_role'] === 'processor' ? $discoveredAt->copy()->addHours((int) config('privacy.controller_notification_hours')) : null, 'regulator_notification_due_at' => $discoveredAt->copy()->addHours((int) config('privacy.regulator_notification_hours'))]);
        $this->auditLogger->record($user, $incident, 'privacy.incident.reported', "Privacy incident {$incident->reference} reported.", metadata: ['controller_role' => $incident->controller_role, 'regulator_due_at' => $incident->regulator_notification_due_at->toIso8601String()]);

        return back()->with('success', 'Privacy incident recorded with controlled notification deadlines.');
    }

    public function advancePrivacyIncident(AdvancePrivacyIncidentRequest $request, string $currentTeam, PrivacyIncident $privacyIncident, AdvancePrivacyIncident $action): RedirectResponse
    {
        $action->handle($privacyIncident, $this->user($request), $request->validated());

        return back()->with('success', 'Privacy incident workflow advanced.');
    }

    /** @return list<string> */
    private function csv(?string $value): array
    {
        $items = array_map(trim(...), explode(',', $value ?? ''));

        return array_values(array_unique(array_filter($items, fn (string $item): bool => $item !== '')));
    }

    /** @return array<string, mixed> */
    private function privacyIncidentPayload(PrivacyIncident $incident, bool $canManage): array
    {
        return [
            'id' => $incident->id, 'reference' => $incident->reference, 'title' => $incident->title, 'controllerRole' => $incident->controller_role, 'breachType' => $incident->breach_type,
            'description' => $canManage ? $incident->description : 'Restricted incident narrative', 'personalDataCategories' => $incident->personal_data_categories, 'estimatedDataSubjects' => $incident->estimated_data_subjects, 'containsSensitiveData' => $incident->contains_sensitive_data,
            'status' => $incident->status, 'severity' => $incident->severity, 'realRiskOfHarm' => $incident->real_risk_of_harm, 'occurredAt' => $incident->occurred_at?->toIso8601String(), 'discoveredAt' => $incident->discovered_at->toIso8601String(),
            'controllerNotificationDueAt' => $incident->controller_notification_due_at?->toIso8601String(), 'regulatorNotificationDueAt' => $incident->regulator_notification_due_at->toIso8601String(), 'containedAt' => $incident->contained_at?->toIso8601String(), 'assessedAt' => $incident->assessed_at?->toIso8601String(),
            'regulatorNotifiedAt' => $incident->regulator_notified_at?->toIso8601String(), 'dataSubjectsNotifiedAt' => $incident->data_subjects_notified_at?->toIso8601String(), 'closedAt' => $incident->closed_at?->toIso8601String(), 'containmentActions' => $incident->containment_actions,
            'riskAssessment' => $incident->risk_assessment, 'regulatorNotificationReference' => $incident->regulator_notification_reference, 'regulatorDelayReason' => $incident->regulator_delay_reason, 'subjectNotificationDecision' => $incident->subject_notification_decision,
            'subjectNotificationRationale' => $incident->subject_notification_rationale, 'rootCause' => $incident->root_cause, 'remediationActions' => $incident->remediation_actions, 'closureEvidenceReference' => $incident->closure_evidence_reference,
            'overdue' => $incident->real_risk_of_harm === 'yes' && $incident->regulator_notified_at === null && $incident->regulator_notification_due_at->isPast(),
            'dataAsset' => $incident->dataAsset ? ['id' => $incident->dataAsset->id, 'code' => $incident->dataAsset->code, 'name' => $incident->dataAsset->name, 'classification' => $incident->dataAsset->classification] : null,
            'county' => $incident->county?->identityCell(), 'reporter' => $incident->reporter->name, 'incidentLead' => $incident->incidentLead->name, 'assessor' => $incident->assessor?->name, 'closer' => $incident->closer?->name,
            'documents' => $incident->documentLinks->filter(fn ($link): bool => $link->document->record_status === 'active')->map(fn ($link): array => ['id' => $link->document->id, 'purpose' => $link->purpose, 'title' => $link->document->title, 'category' => $link->document->category, 'sourceType' => $link->document->source_type, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status])->values()->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
