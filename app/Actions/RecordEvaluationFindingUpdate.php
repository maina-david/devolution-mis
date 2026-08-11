<?php

namespace App\Actions;

use App\Models\AssessmentDocument;
use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class RecordEvaluationFindingUpdate
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    public function handle(EvaluationFinding $finding, AssessmentDocument $document, User $actor, float $progress, string $narrative): EvaluationFindingUpdate
    {
        abort_unless($finding->status === 'open', 409, 'Only open findings accept responses.');
        abort_unless($actor->id === $finding->accountable_owner_id, 403);
        abort_unless($document->county_id === $finding->county_id && $document->scan_status === 'clean' && $document->record_status === 'active', 409, 'Response evidence must be clean, active and within the finding scope.');
        abort_unless($document->links()->where('subject_type', $finding->getMorphClass())->where('subject_id', $finding->id)->exists(), 409, 'Response evidence must be retained against this finding.');
        abort_if($progress <= (float) $finding->progress_percentage || $progress > 100, 409, 'Progress must increase monotonically and cannot exceed 100%.');
        $snapshot = ['finding_id' => $finding->id, 'document_id' => $document->id, 'submitted_by' => $actor->id, 'progress_percentage' => $progress, 'narrative' => $narrative];
        $update = DB::transaction(function () use ($finding, $document, $actor, $progress, $narrative, $snapshot): EvaluationFindingUpdate {
            $locked = EvaluationFinding::query()->lockForUpdate()->findOrFail($finding->id);
            abort_if($locked->updates()->where('status', 'pending_verification')->exists(), 409, 'The pending response must be decided first.');

            return EvaluationFindingUpdate::create(['evaluation_finding_id' => $locked->id, 'assessment_document_id' => $document->id, 'submitted_by' => $actor->id, 'progress_percentage' => $progress, 'narrative' => $narrative, 'submitted_at' => now(), 'checksum' => $this->canonicalJson->checksum($snapshot)]);
        });
        $this->auditLogger->record($actor, $update, 'evaluation.finding.response_submitted', "Response submitted for {$finding->reference}.", $finding->county_id, ['checksum' => $update->checksum]);

        return $update;
    }
}
