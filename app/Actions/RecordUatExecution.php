<?php

namespace App\Actions;

use App\Models\County;
use App\Models\UatExecution;
use App\Models\UatScenario;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UatAccess;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordUatExecution
{
    public function __construct(private UatAccess $access, private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(UatScenario $scenario, User $actor, array $attributes): UatExecution
    {
        return DB::transaction(function () use ($scenario, $actor, $attributes): UatExecution {
            $scenario = UatScenario::query()->with('campaign.counties')->lockForUpdate()->findOrFail($scenario->id);
            $campaign = $scenario->campaign;
            $this->access->authorize($actor, $campaign);
            if (! in_array($campaign->status, ['planning', 'executing', 'rejected'], true) || $scenario->status !== 'ready') {
                throw ValidationException::withMessages(['outcome' => __('change-readiness.uat_errors.execution_closed')]);
            }
            if ($scenario->actor_role !== $actor->programmeRole()->value) {
                throw ValidationException::withMessages(['outcome' => __('change-readiness.uat_errors.actor_role')]);
            }
            $county = County::query()->findOrFail($attributes['county_id']);
            abort_unless($actor->canAccessCounty($county), 403, __('change-readiness.uat_errors.execution_county_scope'));
            abort_unless($campaign->counties->contains('id', $county->id), 422, __('change-readiness.uat_errors.execution_county_campaign'));

            $evidence = [
                'scenario_id' => $scenario->id,
                'county_id' => $county->id,
                'tested_by' => $actor->id,
                'environment' => $attributes['environment'],
                'outcome' => $attributes['outcome'],
                'actual_result' => $attributes['actual_result'],
                'evidence_references' => $attributes['evidence_references'],
                'started_at' => $attributes['started_at'],
                'completed_at' => $attributes['completed_at'],
            ];
            $execution = UatExecution::create([...$evidence, 'uat_scenario_id' => $scenario->id, 'checksum' => $this->canonicalJson->checksum($evidence)]);

            if ($attributes['outcome'] !== 'pass') {
                $findingOwner = User::query()->whereNull('access_revoked_at')->findOrFail($attributes['finding_owner_id']);
                abort_unless($findingOwner->canAccessCounty($county), 422, __('change-readiness.uat_errors.finding_owner_scope'));
                $execution->findings()->create([
                    'raised_by' => $actor->id,
                    'owner_id' => $findingOwner->id,
                    'severity' => $attributes['finding_severity'],
                    'title' => $attributes['finding_title'],
                    'description' => $attributes['finding_description'],
                    'due_on' => $attributes['finding_due_on'],
                    'status' => 'open',
                ]);
            }

            $campaign->update(['status' => 'executing']);
            $campaign->counties()->updateExistingPivot($county->id, ['participation_status' => 'executed']);
            $this->auditLogger->record($actor, $execution, 'change-readiness.uat.execution.recorded', "UAT execution for {$scenario->code} recorded as {$execution->outcome}.", metadata: ['campaign_id' => $campaign->id, 'county_id' => $county->id, 'checksum' => $execution->checksum]);

            return $execution;
        });
    }
}
