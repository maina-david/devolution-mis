<?php

namespace App\Actions;

use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class VerifyEvaluationFindingUpdate
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(EvaluationFindingUpdate $update, User $actor, string $decision, string $note): EvaluationFindingUpdate
    {
        $finding = $update->finding;
        abort_unless($actor->programmeRole()->hasNationalScope() || ($finding->county_id !== null && $actor->canAccessCounty($finding->county)), 403);
        abort_if(in_array($actor->id, [$finding->created_by, $update->submitted_by], true), 409, 'Response verification must be independent of finding issuance and response submission.');
        $update = DB::transaction(function () use ($update, $actor, $decision, $note): EvaluationFindingUpdate {
            $locked = EvaluationFindingUpdate::query()->lockForUpdate()->findOrFail($update->id);
            abort_unless($locked->status === 'pending_verification', 409, 'This response has already been decided.');
            $locked->update(['status' => $decision, 'verified_by' => $actor->id, 'verified_at' => now(), 'decision_note' => $note]);
            if ($decision === 'verified') {
                EvaluationFinding::query()->whereKey($locked->evaluation_finding_id)->update(['progress_percentage' => $locked->progress_percentage]);
            }

            return $locked->refresh();
        });
        $this->auditLogger->record($actor, $update, "evaluation.finding.response_{$decision}", "Finding response {$decision}.", $finding->county_id, ['decision_note' => $note]);

        return $update;
    }
}
