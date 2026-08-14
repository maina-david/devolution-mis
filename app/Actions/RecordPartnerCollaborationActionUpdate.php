<?php

namespace App\Actions;

use App\Models\PartnerCollaborationAction;
use App\Models\PartnerCollaborationActionUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class RecordPartnerCollaborationActionUpdate
{
    public function __construct(private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PartnerCollaborationAction $action, User $submitter, array $attributes): PartnerCollaborationActionUpdate
    {
        abort_unless($action->accountable_user_id === $submitter->id, 403);
        abort_unless($action->plan->status === 'active' && $action->status !== 'completed', 409, __('partner-coordination.lifecycle.errors.active_incomplete_action_required'));
        $progress = (float) $attributes['progress_percentage'];
        abort_if($progress < (float) $action->progress_percentage, 422, __('partner-coordination.lifecycle.errors.progress_regression'));

        $update = DB::transaction(function () use ($action, $submitter, $attributes, $progress): PartnerCollaborationActionUpdate {
            $locked = PartnerCollaborationAction::query()->lockForUpdate()->findOrFail($action->id);
            abort_if($locked->updates()->whereDoesntHave('decision')->exists(), 409, __('partner-coordination.lifecycle.errors.progress_decision_pending'));
            $documents = $locked->documentLinks()->where('purpose', 'partner-collaboration-action-evidence')->whereHas('document', fn ($query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->with('document:id,content_checksum')->get();
            abort_if($progress >= 100 && $documents->isEmpty(), 422, __('partner-coordination.lifecycle.errors.completion_evidence_required'));
            $submittedAt = now();
            $evidenceChecksum = $documents->isEmpty() ? null : $this->canonicalJson->checksum($documents->pluck('document.content_checksum')->sort()->values()->all());
            $snapshot = ['action_id' => $locked->id, 'progress_percentage' => number_format($progress, 2, '.', ''), 'narrative' => (string) $attributes['narrative'], 'submitted_by' => $submitter->id, 'submitted_at' => $submittedAt->toIso8601String(), 'evidence_checksum' => $evidenceChecksum];

            return $locked->updates()->create([...$snapshot, 'submitted_at' => $submittedAt, 'update_checksum' => $this->canonicalJson->checksum($snapshot)]);
        }, attempts: 3);

        $this->auditLogger->record($submitter, $action, 'partner.collaboration_action.update_submitted', __('partner-coordination.lifecycle.audit.progress_submitted'), $action->county_id, ['update_id' => $update->id, 'update_checksum' => $update->update_checksum]);

        return $update;
    }
}
