<?php

namespace App\Http\Controllers;

use App\Actions\ApproveIndicatorDefinition;
use App\Actions\CloseEvaluationFinding;
use App\Actions\CreateEvaluationFinding;
use App\Actions\CreateEvaluationFindingAction;
use App\Actions\CreateIndicatorDefinition;
use App\Actions\CreateProgrammeEvaluation;
use App\Actions\RecordEvaluationFindingActionUpdate;
use App\Actions\RecordEvaluationFindingUpdate;
use App\Actions\RecordIndicatorObservation;
use App\Actions\SupersedeIndicatorDefinition;
use App\Actions\TransitionWorkflow;
use App\Actions\VerifyEvaluationFindingActionUpdate;
use App\Actions\VerifyEvaluationFindingUpdate;
use App\Actions\VerifyIndicatorObservation;
use App\Enums\ProgrammePermission;
use App\Http\Requests\CloseEvaluationFindingRequest;
use App\Http\Requests\StoreEvaluationFindingActionRequest;
use App\Http\Requests\StoreEvaluationFindingActionUpdateRequest;
use App\Http\Requests\StoreEvaluationFindingRequest;
use App\Http\Requests\StoreEvaluationFindingUpdateRequest;
use App\Http\Requests\StoreIndicatorDefinitionRequest;
use App\Http\Requests\StoreIndicatorObservationRequest;
use App\Http\Requests\StoreProgrammeEvaluationRequest;
use App\Http\Requests\SupersedeIndicatorDefinitionRequest;
use App\Http\Requests\TransitionProgrammeEvaluationRequest;
use App\Http\Requests\VerifyEvaluationFindingActionUpdateRequest;
use App\Http\Requests\VerifyEvaluationFindingUpdateRequest;
use App\Http\Requests\VerifyIndicatorObservationRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\AssessmentDocument;
use App\Models\DocumentLink;
use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\EvaluationFindingActionUpdate;
use App\Models\EvaluationFindingUpdate;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorObservation;
use App\Models\Programme;
use App\Models\ProgrammeEvaluation;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\MonitoringEvaluationResults;
use App\Services\ProgrammeCountyScope;
use App\Services\ProgrammeWorkspaceData;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringEvaluationController extends Controller
{
    public function index(WorkspaceIndexRequest $request, ProgrammeWorkspaceData $workspaceData, MonitoringEvaluationResults $results, ProgrammeCountyScope $countyScope, EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver): Response
    {
        Gate::authorize(ProgrammePermission::ViewMonitoringEvaluation->value);
        $user = $this->user($request);
        $filters = WorkspaceFilters::fromRequest($request);
        if ($filters->countyId !== null) {
            abort_unless($countyScope->query($user)->whereKey($filters->countyId)->exists(), 403);
        }
        $referenceDataRelease = $referenceDataReleaseResolver->availableForSelection(now());
        $governedProgrammeIds = collect($referenceDataRelease?->snapshot['programmes'] ?? [])->pluck('id')->filter()->all();
        $governedSectorIds = collect($referenceDataRelease?->snapshot['sectors'] ?? [])->pluck('id')->filter()->all();

        return Inertia::render('monitoring-evaluation/index', [
            'workspace' => $workspaceData->monitoringEvaluation($user, $filters),
            'results' => $results->forUser($user, $filters),
            'filters' => $filters,
            'capabilities' => [
                'manageIndicators' => $user->can(ProgrammePermission::ManageIndicators->value),
                'submitData' => $user->can(ProgrammePermission::SubmitIndicatorData->value),
                'verifyData' => $user->can(ProgrammePermission::VerifyIndicatorData->value),
                'manageEvaluations' => $user->can(ProgrammePermission::ManageIndicators->value),
                'approveEvaluations' => $user->can(ProgrammePermission::VerifyIndicatorData->value),
            ],
            'options' => [
                'indicators' => IndicatorDefinition::query()->where('status', 'approved')->where(fn (Builder $query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))->whereNotExists(fn ($query) => $query->selectRaw('1')->from('indicator_definitions as newer')->whereColumn('newer.code', 'indicator_definitions.code')->where('newer.status', 'approved')->whereColumn('newer.version', '>', 'indicator_definitions.version')->whereNull('newer.deleted_at')->where(fn ($query) => $query->whereNull('newer.effective_from')->orWhere('newer.effective_from', '<=', now()))->where(fn ($query) => $query->whereNull('newer.effective_to')->orWhere('newer.effective_to', '>', now())))->orderBy('code')->get(['id', 'code', 'name', 'value_type', 'unit_of_measure']),
                'definitions' => IndicatorDefinition::query()->with(['sector:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'successors:id,supersedes_id,status'])->latest()->limit(50)->get()->map(fn (IndicatorDefinition $indicator): array => [
                    'id' => $indicator->id,
                    'code' => $indicator->code,
                    'name' => $indicator->name,
                    'resultsLevel' => $indicator->results_level,
                    'version' => $indicator->version,
                    'status' => $indicator->status,
                    'sector' => $indicator->sector?->name,
                    'createdBy' => $indicator->created_by,
                    'supersedesId' => $indicator->supersedes_id,
                    'changeSummary' => $indicator->change_summary,
                    'canSupersede' => $referenceDataRelease !== null && $indicator->isCurrentApprovedVersion() && $indicator->successors->isEmpty(),
                    'isCurrentApproved' => $indicator->isCurrentApprovedVersion(),
                    'hasSuccessor' => $indicator->successors->isNotEmpty(),
                    'description' => $indicator->description,
                    'unitOfMeasure' => $indicator->unit_of_measure,
                    'valueType' => $indicator->value_type,
                    'direction' => $indicator->direction,
                    'frequency' => $indicator->frequency,
                    'dataSource' => $indicator->data_source,
                    'verificationMethod' => $indicator->verification_method,
                    'referenceData' => $indicator->referenceDataRelease === null ? null : ['version' => $indicator->referenceDataRelease->version, 'effectiveFrom' => $indicator->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $indicator->referenceDataRelease->checksum],
                ]),
                'counties' => $countyScope->query($user)->orderBy('code')->get()->map->identityCell()->values(),
                'programmes' => Programme::query()->whereIn('id', $governedProgrammeIds)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
                'sectors' => Sector::query()->whereIn('id', $governedSectorIds)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'verificationStatuses' => collect(['submitted', 'verified', 'rejected', 'clarification_requested'])->map(fn (string $status): array => ['id' => $status, 'name' => str($status)->replace('_', ' ')->headline()->toString()]),
                'evaluations' => ProgrammeEvaluation::query()
                    ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('lead_evaluator_id', $user->id)->orWhereIn('county_id', $countyScope->query($user)->select('id'))))
                    ->with(['programme:id,name', 'county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'referenceDataRelease:id,version,effective_from,checksum', 'documentLinks.document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status'])
                    ->latest()
                    ->limit(50)
                    ->get()
                    ->map(fn (ProgrammeEvaluation $evaluation): array => [
                        'id' => $evaluation->id,
                        'code' => $evaluation->code,
                        'title' => $evaluation->title,
                        'type' => $evaluation->evaluation_type,
                        'status' => $evaluation->status,
                        'programme' => $evaluation->programme?->name,
                        'county' => $evaluation->county?->identityCell(),
                        'period' => $evaluation->period_start->toDateString().' – '.$evaluation->period_end->toDateString(),
                        'referenceRelease' => $evaluation->referenceDataRelease ? "v{$evaluation->referenceDataRelease->version} · {$evaluation->referenceDataRelease->effective_from?->toDateString()}" : 'Legacy unpinned',
                        'referenceChecksum' => $evaluation->referenceDataRelease?->checksum,
                        'documents' => $evaluation->documentLinks->map(fn (DocumentLink $link): array => ['id' => $link->document->id, 'purpose' => $link->purpose, 'title' => $link->document->title, 'category' => $link->document->category, 'sourceType' => $link->document->source_type, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status])->values()->all(),
                    ]),
                'findingOwners' => User::query()
                    ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $countyScope->query($user)->select('id')))
                    ->whereNull('access_revoked_at')->orderBy('name')->get(['id', 'name', 'county_id'])->map(fn (User $owner): array => ['id' => $owner->id, 'name' => $owner->name, 'countyId' => $owner->county_id]),
                'findings' => EvaluationFinding::query()
                    ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $countyScope->query($user)->select('id')))
                    ->with(['evaluation:id,code,title', 'county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'owner:id,name', 'updates' => fn ($query) => $query->with(['submitter:id,name', 'verifier:id,name'])->latest('submitted_at'), 'documentLinks.document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status,record_status', 'actions' => fn ($query) => $query->with(['owner:id,name', 'updates' => fn ($query) => $query->with(['submitter:id,name', 'verifier:id,name'])->latest('submitted_at'), 'documentLinks.document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status,record_status'])->orderBy('due_at')])
                    ->latest()->limit(100)->get()->map(fn (EvaluationFinding $finding): array => [
                        'id' => $finding->id, 'evaluationId' => $finding->programme_evaluation_id, 'evaluation' => $finding->evaluation->code.' · '.$finding->evaluation->title,
                        'reference' => $finding->reference, 'title' => $finding->title, 'finding' => $finding->finding, 'recommendation' => $finding->recommendation,
                        'severity' => $finding->severity, 'status' => $finding->status, 'dueAt' => $finding->due_at->toDateString(), 'progress' => (float) $finding->progress_percentage,
                        'reminderSentAt' => $finding->reminder_sent_at?->toIso8601String(), 'escalatedAt' => $finding->escalated_at?->toIso8601String(),
                        'ownerId' => $finding->accountable_owner_id, 'owner' => $finding->owner->name, 'county' => $finding->county?->identityCell(),
                        'documents' => $finding->documentLinks->map(fn (DocumentLink $link): array => ['id' => $link->document->id, 'purpose' => $link->purpose, 'title' => $link->document->title, 'category' => $link->document->category, 'sourceType' => $link->document->source_type, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status])->values()->all(),
                        'updates' => $finding->updates->map(fn (EvaluationFindingUpdate $update): array => ['id' => $update->id, 'progress' => (float) $update->progress_percentage, 'narrative' => $update->narrative, 'status' => $update->status, 'submittedBy' => $update->submitter->name, 'verifiedBy' => $update->verifier?->name, 'submittedAt' => $update->submitted_at->toIso8601String()])->all(),
                        'actions' => $finding->actions->map(fn (EvaluationFindingAction $action): array => [
                            'id' => $action->id, 'code' => $action->code, 'title' => $action->title, 'description' => $action->description, 'successIndicator' => $action->success_indicator, 'target' => $action->target,
                            'ownerId' => $action->accountable_owner_id, 'owner' => $action->owner->name, 'dueAt' => $action->due_at->toDateString(), 'weight' => (float) $action->weight_percentage, 'progress' => (float) $action->progress_percentage, 'status' => $action->status,
                            'documents' => $action->documentLinks->filter(fn (DocumentLink $link): bool => $link->document->record_status === 'active')->map(fn (DocumentLink $link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'category' => $link->document->category, 'sourceType' => $link->document->source_type, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status])->values()->all(),
                            'updates' => $action->updates->map(fn (EvaluationFindingActionUpdate $update): array => ['id' => $update->id, 'progress' => (float) $update->progress_percentage, 'narrative' => $update->narrative, 'status' => $update->status, 'submittedBy' => $update->submitter->name, 'verifiedBy' => $update->verifier?->name, 'submittedAt' => $update->submitted_at->toIso8601String()])->all(),
                        ])->values()->all(),
                    ]),
            ],
            'catalogue' => $referenceDataRelease === null ? ['available' => false] : ['available' => true, 'version' => $referenceDataRelease->version, 'effectiveFrom' => $referenceDataRelease->effective_from?->toDateString(), 'checksum' => $referenceDataRelease->checksum],
        ]);
    }

    public function storeIndicator(StoreIndicatorDefinitionRequest $request, CreateIndicatorDefinition $create): RedirectResponse
    {
        $create->handle($this->user($request), $request->validated());

        return $this->success('Indicator definition created as a draft.');
    }

    public function supersedeIndicator(SupersedeIndicatorDefinitionRequest $request, IndicatorDefinition $indicator, SupersedeIndicatorDefinition $supersede): RedirectResponse
    {
        $supersede->handle($this->user($request), $indicator, $request->validated());

        return $this->success('A successor indicator version was created as a draft.');
    }

    public function approveIndicator(Request $request, IndicatorDefinition $indicator, ApproveIndicatorDefinition $approve): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageIndicators->value);
        $approve->handle($this->user($request), $indicator);

        return $this->success('Indicator definition approved.');
    }

    public function storeObservation(StoreIndicatorObservationRequest $request, RecordIndicatorObservation $record): RedirectResponse
    {
        $record->handle($this->user($request), $request->validated());

        return $this->success('Indicator observation submitted for verification.');
    }

    public function verifyObservation(VerifyIndicatorObservationRequest $request, IndicatorObservation $observation, VerifyIndicatorObservation $verify): RedirectResponse
    {
        $verify->handle($this->user($request), $observation, $request->validated());

        return $this->success('Verification decision recorded.');
    }

    public function storeEvaluation(StoreProgrammeEvaluationRequest $request, CreateProgrammeEvaluation $createEvaluation): RedirectResponse
    {
        $createEvaluation->handle($this->user($request), $request->validated());

        return $this->success('Programme evaluation created.');
    }

    public function transitionEvaluation(TransitionProgrammeEvaluationRequest $request, ProgrammeEvaluation $evaluation, TransitionWorkflow $transitionWorkflow, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeEvaluation($user, $evaluation);
        $instance = $evaluation->workflowInstance;
        abort_unless($instance instanceof WorkflowInstance, 409, __('evaluation-findings.errors.evaluation_workflow_unavailable'));
        $transition = $request->validated('transition');
        $hasCleanTermsOfReference = $evaluation->documentLinks()->where('purpose', 'programme-evaluation-tor')->whereHas('document', fn (Builder $query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists();
        $hasCleanReport = $evaluation->documentLinks()->where('purpose', 'programme-evaluation-report')->whereHas('document', fn (Builder $query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->exists();
        $transitioned = $transitionWorkflow->handle($instance, $transition, $user, ['terms_of_reference_present' => $hasCleanTermsOfReference, 'evaluation_report_present' => $hasCleanReport], $request->validated('comment'));
        $evaluation->update(['status' => $transitioned->current_state, 'approved_by' => $transition === 'approve' ? $user->id : $evaluation->approved_by, 'approved_at' => $transition === 'approve' ? now() : $evaluation->approved_at]);
        $auditLogger->record($user, $evaluation, 'programme.evaluation.transitioned', __('evaluation-findings.audit.evaluation_transitioned', ['code' => $evaluation->code, 'state' => $transitioned->current_state]), $evaluation->county_id);

        return $this->success(__('evaluation-findings.flash.evaluation_transitioned'));
    }

    public function storeFinding(StoreEvaluationFindingRequest $request, ProgrammeEvaluation $evaluation, CreateEvaluationFinding $create): RedirectResponse
    {
        $create->handle($evaluation, $this->user($request), $request->validated());

        return $this->success(__('evaluation-findings.flash.created'));
    }

    public function storeFindingUpdate(StoreEvaluationFindingUpdateRequest $request, EvaluationFinding $finding, RecordEvaluationFindingUpdate $record): RedirectResponse
    {
        $document = AssessmentDocument::query()->whereKey($request->validated('assessment_document_id'))->firstOrFail();
        $record->handle($finding, $document, $this->user($request), (float) $request->validated('progress_percentage'), $request->validated('narrative'));

        return $this->success(__('evaluation-findings.flash.response_submitted'));
    }

    public function storeFindingAction(StoreEvaluationFindingActionRequest $request, EvaluationFinding $finding, CreateEvaluationFindingAction $create): RedirectResponse
    {
        $create->handle($finding, $this->user($request), $request->payload());

        return $this->success(__('evaluation-findings.flash.action_created'));
    }

    public function storeFindingActionUpdate(StoreEvaluationFindingActionUpdateRequest $request, EvaluationFindingAction $action, RecordEvaluationFindingActionUpdate $record): RedirectResponse
    {
        $document = AssessmentDocument::query()->whereKey($request->validated('assessment_document_id'))->firstOrFail();
        $record->handle($action, $document, $this->user($request), (float) $request->validated('progress_percentage'), $request->validated('narrative'));

        return $this->success(__('evaluation-findings.flash.action_progress_submitted'));
    }

    public function verifyFindingActionUpdate(VerifyEvaluationFindingActionUpdateRequest $request, EvaluationFindingActionUpdate $update, VerifyEvaluationFindingActionUpdate $verify): RedirectResponse
    {
        $verify->handle($update, $this->user($request), $request->validated('decision'), $request->validated('note'));

        return $this->success(__('evaluation-findings.flash.action_progress_decided'));
    }

    public function verifyFindingUpdate(VerifyEvaluationFindingUpdateRequest $request, EvaluationFindingUpdate $update, VerifyEvaluationFindingUpdate $verify): RedirectResponse
    {
        $verify->handle($update, $this->user($request), $request->validated('decision'), $request->validated('note'));

        return $this->success(__('evaluation-findings.flash.response_decided'));
    }

    public function closeFinding(CloseEvaluationFindingRequest $request, EvaluationFinding $finding, CloseEvaluationFinding $close): RedirectResponse
    {
        $close->handle($finding, $this->user($request), $request->validated('note'));

        return $this->success(__('evaluation-findings.flash.closed'));
    }

    private function authorizeEvaluation(User $user, ProgrammeEvaluation $evaluation): void
    {
        abort_unless($user->programmeRole()->hasNationalScope() || $evaluation->lead_evaluator_id === $user->id || ($evaluation->county_id !== null && $user->canAccessCounty($evaluation->county)), 403);
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
