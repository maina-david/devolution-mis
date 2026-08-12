<?php

namespace App\Actions;

use App\Models\UatCampaign;
use App\Models\UatScenario;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UatAccess;
use Illuminate\Validation\ValidationException;

class CreateUatScenario
{
    public function __construct(private UatAccess $access, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(UatCampaign $campaign, User $actor, array $attributes): UatScenario
    {
        $this->access->authorize($actor, $campaign);
        if ($campaign->status !== 'planning') {
            throw ValidationException::withMessages(['uat_campaign_id' => __('change-readiness.uat_errors.scenario_planning_only')]);
        }

        $scenario = $campaign->scenarios()->create([...$attributes, 'created_by' => $actor->id, 'status' => 'ready']);
        $this->auditLogger->record($actor, $scenario, 'change-readiness.uat.scenario.created', "UAT scenario {$scenario->code} added to {$campaign->code}.", metadata: ['campaign_id' => $campaign->id, 'module' => $scenario->module, 'actor_role' => $scenario->actor_role, 'required' => $scenario->required]);

        return $scenario;
    }
}
