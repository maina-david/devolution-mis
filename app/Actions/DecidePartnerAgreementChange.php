<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\PartnerAgreementChangeDecision;
use App\Models\PartnerAgreementChangeRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class DecidePartnerAgreementChange
{
    public function __construct(private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PartnerAgreementChangeRequest $changeRequest, User $decider, array $attributes): PartnerAgreementChangeDecision
    {
        abort_unless($decider->can(ProgrammePermission::ApprovePartnerAgreements->value), 403);
        abort_if($changeRequest->requested_by === $decider->id, 403, __('partner-coordination.lifecycle.errors.change_requester_separation'));

        $decisionName = (string) $attributes['decision'];
        $decisionNote = (string) $attributes['decision_note'];
        $decision = DB::transaction(function () use ($changeRequest, $decider, $decisionName, $decisionNote): PartnerAgreementChangeDecision {
            $locked = PartnerAgreementChangeRequest::query()->lockForUpdate()->with('agreement')->findOrFail($changeRequest->id);
            abort_if($locked->decision()->exists(), 409, __('partner-coordination.lifecycle.errors.change_already_decided'));
            $documents = $locked->documentLinks()->where('purpose', 'partner-agreement-change-evidence')->whereHas('document', fn ($query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->with('document:id,content_checksum')->get();
            abort_if($documents->isEmpty(), 422, __('partner-coordination.lifecycle.errors.clean_change_evidence_required'));
            $decidedAt = now();
            $evidenceChecksum = $this->canonicalJson->checksum($documents->pluck('document.content_checksum')->sort()->values()->all());
            $snapshot = ['request_checksum' => $locked->request_checksum, 'decision' => $decisionName, 'decision_note' => $decisionNote, 'decided_by' => $decider->id, 'decided_at' => $decidedAt->toIso8601String(), 'evidence_checksum' => $evidenceChecksum];
            $decision = $locked->decision()->create([
                'decision' => $decisionName,
                'decision_note' => $decisionNote,
                'decided_by' => $decider->id,
                'decided_at' => $decidedAt,
                'evidence_checksum' => $evidenceChecksum,
                'decision_checksum' => $this->canonicalJson->checksum($snapshot),
                'snapshot' => $snapshot,
            ]);

            if ($decisionName === 'approved') {
                $agreementChanges = match ($locked->change_type) {
                    'suspension' => ['status' => 'suspended'],
                    'termination' => ['status' => 'terminated', 'ends_on' => $locked->effective_on],
                    'renewal' => ['status' => 'active', ...$locked->proposed_changes],
                    default => $locked->proposed_changes,
                };
                $locked->agreement->update($agreementChanges);
            }

            return $decision;
        }, attempts: 3);

        $this->auditLogger->record($decider, $changeRequest->agreement, 'partner.agreement.change_decided', __('partner-coordination.lifecycle.audit.change_decided', ['type' => $changeRequest->change_type, 'decision' => $decision->decision]), metadata: ['change_request_id' => $changeRequest->id, 'decision_id' => $decision->id, 'decision_checksum' => $decision->decision_checksum]);

        return $decision;
    }
}
