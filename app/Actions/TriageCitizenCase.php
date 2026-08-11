<?php

namespace App\Actions;

use App\Models\CitizenCase;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;

class TriageCitizenCase
{
    public function __construct(private StartWorkflow $startWorkflow, private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(CitizenCase $case, User $actor, array $attributes): CitizenCase
    {
        abort_unless($case->status === 'received' && $case->workflow_instance_id === null, 409, 'Only newly received cases may be triaged.');
        abort_unless($actor->canAccessCounty($case->county), 403, 'You are not authorized to triage cases for this county.');

        return DB::transaction(function () use ($case, $actor, $attributes): CitizenCase {
            $referenceDataRelease = $this->referenceDataResolver->forCitizenCaseTriage(is_string($attributes['assigned_organization_id'] ?? null) ? $attributes['assigned_organization_id'] : null, is_string($attributes['sector_id'] ?? null) ? $attributes['sector_id'] : null, now());
            $definition = WorkflowDefinition::query()->where('code', $case->case_type === 'grievance' ? 'GRIEVANCE-CASE-LIFECYCLE' : 'FEEDBACK-CASE-LIFECYCLE')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $case, $actor, ['resolution_summary_present' => false], $case->county_id);
            $instance = $this->transitionWorkflow->handle($instance, 'triage', $actor, [], (string) $attributes['triage_note']);
            $case->update([...collect($attributes)->except('triage_note')->all(), 'triage_reference_data_release_id' => $referenceDataRelease->id, 'workflow_instance_id' => $instance->id, 'status' => $instance->current_state]);
            $this->auditLogger->record($actor, $case, 'citizen_case.triaged', "Case {$case->reference} triaged and assigned.", $case->county_id, ['assigned_to' => $attributes['assigned_to'], 'priority' => $attributes['priority'], 'reference_data_release_id' => $referenceDataRelease->id, 'reference_data_version' => $referenceDataRelease->version, 'reference_data_checksum' => $referenceDataRelease->checksum]);

            return $case->refresh();
        });
    }
}
