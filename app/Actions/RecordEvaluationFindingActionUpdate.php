<?php

namespace App\Actions;

use App\Models\AssessmentDocument;
use App\Models\EvaluationFindingAction;
use App\Models\EvaluationFindingActionUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class RecordEvaluationFindingActionUpdate
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    public function handle(EvaluationFindingAction $action, AssessmentDocument $document, User $actor, float $progress, string $narrative): EvaluationFindingActionUpdate
    {
        $finding = $action->finding;
        abort_unless($finding->status === 'open' && $action->status !== 'completed', 409, __('evaluation-findings.errors.action_update_closed'));
        abort_unless($action->accountable_owner_id === $actor->id, 403);
        abort_unless($document->county_id === $finding->county_id && $document->scan_status === 'clean' && $document->record_status === 'active', 409, __('evaluation-findings.errors.action_evidence_scope'));
        abort_unless($document->links()->where('subject_type', $action->getMorphClass())->where('subject_id', $action->id)->exists(), 409, __('evaluation-findings.errors.action_evidence_link'));
        abort_if($progress <= (float) $action->progress_percentage || $progress > 100, 409, __('evaluation-findings.errors.monotonic_progress'));
        $snapshot = ['action_id' => $action->id, 'document_id' => $document->id, 'submitted_by' => $actor->id, 'progress_percentage' => $progress, 'narrative' => $narrative];

        $update = DB::transaction(function () use ($action, $document, $actor, $progress, $narrative, $snapshot): EvaluationFindingActionUpdate {
            $locked = EvaluationFindingAction::query()->lockForUpdate()->findOrFail($action->id);
            abort_if($locked->updates()->where('status', 'pending_verification')->exists(), 409, __('evaluation-findings.errors.pending_action_update_first'));

            return $locked->updates()->create(['assessment_document_id' => $document->id, 'submitted_by' => $actor->id, 'progress_percentage' => $progress, 'narrative' => $narrative, 'submitted_at' => now(), 'checksum' => $this->canonicalJson->checksum($snapshot)]);
        });
        $this->auditLogger->record($actor, $update, 'evaluation.finding_action.progress_submitted', __('evaluation-findings.audit.action_progress_submitted', ['action' => $action->code]), $finding->county_id, ['progress' => $progress, 'document_id' => $document->id, 'checksum' => $update->checksum]);

        return $update;
    }
}
