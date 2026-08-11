<?php

namespace App\Actions;

use App\Models\County;
use App\Models\DswgAction;
use App\Models\DswgMeeting;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDswgAction
{
    public function __construct(private StartWorkflow $startWorkflow, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DswgMeeting $meeting, User $actor, array $attributes): DswgAction
    {
        $meeting->loadMissing('workingGroup.members');
        $accountableUserId = (string) $attributes['accountable_user_id'];
        if (! $meeting->workingGroup->members->contains('id', $accountableUserId)) {
            throw ValidationException::withMessages(['accountable_user_id' => 'The accountable person must be an active member of this working group.']);
        }
        if (($attributes['county_id'] ?? null) !== null) {
            $county = County::query()->whereKey($attributes['county_id'])->firstOrFail();
            abort_unless($actor->canAccessCounty($county), 403);
            abort_unless($meeting->workingGroup->counties()->whereKey($county)->exists(), 422, 'The action county is outside the working group portfolio.');
        }
        if (($attributes['dswg_decision_id'] ?? null) !== null) {
            abort_unless($meeting->decisions()->whereKey($attributes['dswg_decision_id'])->exists(), 422, 'The selected decision does not belong to this meeting.');
        }

        return DB::transaction(function () use ($meeting, $actor, $attributes, $accountableUserId): DswgAction {
            $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
            $organizationId = is_string($attributes['accountable_organization_id'] ?? null) ? $attributes['accountable_organization_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forDswgAction($countyId, $organizationId, now());
            $action = $meeting->actions()->create([...$attributes, 'reference_data_release_id' => $referenceDataRelease->id, 'created_by' => $actor->id]);
            $definition = WorkflowDefinition::query()->where('code', 'DSWG-ACTION-LIFECYCLE')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $action, $actor, ['progress_percentage' => 0, 'completion_evidence_present' => false], $action->county_id);
            $action->update(['workflow_instance_id' => $instance->id, 'status' => $instance->current_state]);
            $this->auditLogger->record($actor, $action, 'dswg.action.created', "DSWG action {$action->code} assigned.", $action->county_id, ['due_on' => $action->due_on->toDateString(), 'accountable_user_id' => $accountableUserId, 'accountable_organization_id' => $organizationId, 'reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $action->refresh();
        });
    }
}
