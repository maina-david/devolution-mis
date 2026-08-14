<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class AdvanceDataSubjectRequest
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DataSubjectRequest $dataSubjectRequest, User $actor, array $attributes): DataSubjectRequest
    {
        abort_unless($actor->can(ProgrammePermission::ManageDataGovernance->value), 403, __('data-governance.privacy.errors.request_advance_unauthorized'));

        return DB::transaction(function () use ($dataSubjectRequest, $actor, $attributes): DataSubjectRequest {
            $request = DataSubjectRequest::query()->lockForUpdate()->findOrFail($dataSubjectRequest->id);
            $transition = (string) $attributes['transition'];
            $allowed = ['verify_identity' => ['received'], 'start_review' => ['identity_verified'], 'complete' => ['in_progress'], 'reject' => ['identity_verified', 'in_progress']];
            abort_unless(array_key_exists($transition, $allowed), 422, __('data-governance.privacy.errors.request_unknown_transition'));
            abort_unless(in_array($request->status, $allowed[$transition], true), 409, __('data-governance.privacy.errors.request_invalid_state'));

            $changes = match ($transition) {
                'verify_identity' => ['identity_status' => 'verified', 'identity_verified_by' => $actor->id, 'identity_evidence_reference' => $attributes['identity_evidence_reference'], 'status' => 'identity_verified', 'acknowledged_at' => now()],
                'start_review' => ['assigned_to' => $actor->id, 'status' => 'in_progress'],
                'complete', 'reject' => $this->decisionChanges($request, $actor, $attributes, $transition),
            };

            $request->update($changes);
            $this->auditLogger->record($actor, $request, 'privacy.data-subject-request.'.$transition, __('data-governance.privacy.audit.request_advanced', ['reference' => $request->reference, 'status' => __('data-governance.privacy.statuses.'.$request->status)]), metadata: ['transition' => $transition]);

            return $request->refresh();
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function decisionChanges(DataSubjectRequest $request, User $actor, array $attributes, string $transition): array
    {
        abort_if($request->identity_verified_by === $actor->id, 403, __('data-governance.privacy.errors.request_decision_independence'));

        return ['decided_by' => $actor->id, 'status' => $transition === 'complete' ? 'completed' : 'rejected', 'decided_at' => now(), 'decision' => $attributes['decision'], 'decision_reason' => $attributes['decision_reason'], 'response_evidence_reference' => $attributes['response_evidence_reference'] ?? null];
    }
}
