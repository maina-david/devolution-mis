<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\PartnerCollaborationActionUpdate;
use App\Models\PartnerCollaborationActionUpdateDecision;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class VerifyPartnerCollaborationActionUpdate
{
    public function __construct(private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PartnerCollaborationActionUpdate $update, User $verifier, array $attributes): PartnerCollaborationActionUpdateDecision
    {
        abort_unless($verifier->can(ProgrammePermission::ApprovePartnerAgreements->value), 403);
        abort_if($update->submitted_by === $verifier->id, 403, __('partner-coordination.lifecycle.errors.progress_verifier_separation'));
        $decisionName = (string) $attributes['decision'];
        $note = (string) $attributes['verification_note'];

        $decision = DB::transaction(function () use ($update, $verifier, $decisionName, $note): PartnerCollaborationActionUpdateDecision {
            $locked = PartnerCollaborationActionUpdate::query()->with('action')->lockForUpdate()->findOrFail($update->id);
            abort_if($locked->decision()->exists(), 409, __('partner-coordination.lifecycle.errors.progress_already_decided'));
            $verifiedAt = now();
            $snapshot = ['update_checksum' => $locked->update_checksum, 'decision' => $decisionName, 'verification_note' => $note, 'verified_by' => $verifier->id, 'verified_at' => $verifiedAt->toIso8601String()];
            $decision = $locked->decision()->create([...$snapshot, 'verified_at' => $verifiedAt, 'decision_checksum' => $this->canonicalJson->checksum($snapshot)]);
            if ($decisionName === 'verified') {
                $progress = (float) $locked->progress_percentage;
                $locked->action->update(['progress_percentage' => $progress, 'status' => $progress >= 100 ? 'completed' : 'in_progress', 'verified_at' => $verifiedAt]);
            }

            return $decision;
        }, attempts: 3);

        $this->auditLogger->record($verifier, $update->action, 'partner.collaboration_action.update_decided', __('partner-coordination.lifecycle.audit.progress_decided', ['decision' => $decision->decision]), $update->action->county_id, ['update_id' => $update->id, 'decision_id' => $decision->id, 'decision_checksum' => $decision->decision_checksum]);

        return $decision;
    }
}
