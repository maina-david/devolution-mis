<?php

namespace App\Actions;

use App\Models\UatAcceptance;
use App\Models\UatExecution;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UatAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideUatCampaign
{
    public function __construct(private UatAccess $access, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(UatAcceptance $acceptance, User $actor, array $attributes): UatAcceptance
    {
        return DB::transaction(function () use ($acceptance, $actor, $attributes): UatAcceptance {
            $acceptance = UatAcceptance::query()->with('campaign')->lockForUpdate()->findOrFail($acceptance->id);
            $campaign = $acceptance->campaign;
            $this->access->authorize($actor, $campaign);
            if ($acceptance->decision !== 'pending' || $campaign->status !== 'review') {
                throw ValidationException::withMessages(['decision' => __('change-readiness.uat_errors.decision_state')]);
            }
            $testedCampaign = UatExecution::query()->where('tested_by', $actor->id)->whereHas('scenario', fn ($query) => $query->where('uat_campaign_id', $campaign->id))->exists();
            if (in_array($actor->id, [$campaign->created_by, $acceptance->submitted_by], true) || $testedCampaign) {
                throw ValidationException::withMessages(['decision' => __('change-readiness.uat_errors.decision_separation')]);
            }

            $acceptance->update(['decision' => $attributes['decision'], 'decision_reason' => $attributes['decision_reason'], 'decided_by' => $actor->id, 'decided_at' => now()]);
            $campaign->update(['status' => $attributes['decision']]);
            $this->auditLogger->record($actor, $acceptance, 'change-readiness.uat.acceptance.'.$attributes['decision'], "UAT campaign {$campaign->code} independently {$attributes['decision']}.", metadata: ['campaign_id' => $campaign->id, 'checksum' => $acceptance->checksum]);

            return $acceptance;
        });
    }
}
