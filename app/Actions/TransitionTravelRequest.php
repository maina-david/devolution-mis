<?php

namespace App\Actions;

use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TransitionTravelRequest
{
    public function __construct(private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(TravelRequest $travelRequest, User $actor, array $attributes): TravelRequest
    {
        return DB::transaction(function () use ($travelRequest, $actor, $attributes): TravelRequest {
            $name = (string) $attributes['transition'];
            $financeReference = $attributes['finance_commitment_reference'] ?? $travelRequest->finance_commitment_reference;
            $instance = $this->transitionWorkflow->handle($travelRequest->workflowInstance()->firstOrFail(), $name, $actor, ['finance_reference_present' => filled($financeReference)], (string) $attributes['rationale']);
            $travelRequest->update(['status' => $instance->current_state, 'submitted_at' => $name === 'submit' ? now() : $travelRequest->submitted_at, 'decision_due_at' => $instance->due_at, 'decided_at' => in_array($instance->current_state, ['approved', 'rejected', 'cancelled'], true) ? now() : null, 'finance_commitment_reference' => $financeReference, 'integration_status' => $name === 'finance_clear' ? 'confirmed' : $travelRequest->integration_status]);
            if (in_array($name, ['manager_approve', 'manager_reject', 'finance_clear', 'finance_reject'], true)) {
                $travelRequest->approvals()->create(['actor_id' => $actor->id, 'stage' => str_starts_with($name, 'manager') ? 'manager' : 'finance', 'decision' => str_contains($name, 'reject') ? 'rejected' : 'approved', 'rationale' => $attributes['rationale'], 'approved_cost' => $attributes['approved_cost'] ?? null, 'source_system' => 'idmis', 'external_reference' => $financeReference, 'decided_at' => now()]);
            }
            $travelRequest->requester->notify(ProgrammeAlert::translated(
                'travel-clearance.notifications.updated_title',
                'travel-clearance.notifications.status_'.$instance->current_state,
                'travel-clearance',
                messageParameters: ['reference' => $travelRequest->reference],
            ));
            $this->auditLogger->record($actor, $travelRequest, 'travel.request.transitioned', __('travel-clearance.audit.transitioned', ['reference' => $travelRequest->reference, 'status' => __('travel-clearance.value_'.$instance->current_state)]), $travelRequest->county_id, ['transition' => $name]);

            return $travelRequest->refresh();
        });
    }
}
