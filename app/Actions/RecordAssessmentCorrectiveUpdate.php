<?php

namespace App\Actions;

use App\Models\AssessmentCorrectiveAction;
use App\Models\AssessmentCorrectiveUpdate;
use App\Models\AssessmentDocument;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class RecordAssessmentCorrectiveUpdate
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    public function handle(AssessmentCorrectiveAction $action, AssessmentDocument $document, User $actor, float $progress, string $narrative): AssessmentCorrectiveUpdate
    {
        $plan = $action->plan;
        abort_unless($actor->canAccessCounty($plan->county), 403);
        abort_unless($plan->status === 'active', 409, __('assessment-record.corrective.errors.active_plan_required'));
        abort_unless($document->assessment_id === $plan->assessment_id && $document->county_id === $plan->county_id, 409, __('assessment-record.corrective.errors.evidence_scope_mismatch'));
        abort_unless($document->scan_status === 'clean' && $document->verification_status === 'verified' && $document->record_status !== 'disposed', 409, __('assessment-record.corrective.errors.governed_evidence_required'));
        abort_if($progress <= (float) $action->progress_percentage || $progress > 100, 409, __('assessment-record.corrective.errors.progress_monotonic'));

        $snapshot = ['action_id' => $action->id, 'document_id' => $document->id, 'submitted_by' => $actor->id, 'progress_percentage' => $progress, 'narrative' => $narrative];
        $update = DB::transaction(function () use ($action, $document, $actor, $progress, $narrative, $snapshot): AssessmentCorrectiveUpdate {
            $locked = AssessmentCorrectiveAction::query()->lockForUpdate()->findOrFail($action->id);
            abort_if($locked->updates()->where('status', 'pending_verification')->exists(), 409, __('assessment-record.corrective.errors.pending_update_first'));

            return $locked->updates()->create(['assessment_document_id' => $document->id, 'submitted_by' => $actor->id, 'progress_percentage' => $progress, 'narrative' => $narrative, 'submitted_at' => now(), 'checksum' => $this->canonicalJson->checksum($snapshot)]);
        });
        $this->auditLogger->record($actor, $update, 'assessment.corrective_progress_submitted', __('assessment-record.corrective.audit.progress_submitted', ['code' => $action->code]), $plan->county_id, ['progress' => $progress, 'document_id' => $document->id, 'checksum' => $update->checksum]);

        return $update;
    }
}
