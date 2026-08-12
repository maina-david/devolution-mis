<?php

namespace App\Actions;

use App\Models\UatAcceptance;
use App\Models\UatCampaign;
use App\Models\UatExecution;
use App\Models\UatFinding;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UatAccess;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitUatCampaign
{
    public function __construct(private UatAccess $access, private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    public function handle(UatCampaign $campaign, User $actor): UatAcceptance
    {
        return DB::transaction(function () use ($campaign, $actor): UatAcceptance {
            $campaign = UatCampaign::query()->with(['counties:id', 'scenarios' => fn ($query) => $query->where('required', true)->where('status', 'ready')])->lockForUpdate()->findOrFail($campaign->id);
            $this->access->authorize($actor, $campaign);
            if (! in_array($campaign->status, ['executing', 'rejected'], true) || $campaign->acceptances()->where('decision', 'pending')->exists()) {
                throw ValidationException::withMessages(['status' => __('change-readiness.uat_errors.submit_state')]);
            }
            if ($campaign->counties->count() < $campaign->minimum_counties || $campaign->scenarios->isEmpty()) {
                throw ValidationException::withMessages(['status' => __('change-readiness.uat_errors.submit_coverage')]);
            }

            $latestExecutions = UatExecution::query()
                ->whereIn('uat_scenario_id', $campaign->scenarios->pluck('id'))
                ->whereIn('county_id', $campaign->counties->pluck('id'))
                ->orderByDesc('completed_at')
                ->get()
                ->unique(fn (UatExecution $execution): string => $execution->uat_scenario_id.'|'.$execution->county_id);
            $requiredPairs = $campaign->scenarios->count() * $campaign->counties->count();
            $passingPairs = $latestExecutions->filter(fn (UatExecution $execution): bool => $execution->outcome === 'pass')->count();
            $coveredRoles = $campaign->scenarios->pluck('actor_role')->unique()->values();
            $missingRoles = collect($campaign->required_roles)->diff($coveredRoles)->values();
            $openFindings = UatFinding::query()->whereHas('execution.scenario', fn ($query) => $query->where('uat_campaign_id', $campaign->id))->where('status', '!=', 'verified')->count();

            if ($latestExecutions->count() !== $requiredPairs || $passingPairs !== $requiredPairs || $missingRoles->isNotEmpty() || $openFindings > 0) {
                throw ValidationException::withMessages(['status' => __('change-readiness.uat_errors.submit_evidence')]);
            }

            $coverage = ['county_ids' => $campaign->counties->pluck('id')->sort()->values()->all(), 'scenario_ids' => $campaign->scenarios->pluck('id')->sort()->values()->all(), 'required_pairs' => $requiredPairs, 'passing_pairs' => $passingPairs, 'roles' => $coveredRoles->sort()->values()->all()];
            $snapshot = ['campaign_id' => $campaign->id, 'criteria' => $campaign->acceptance_criteria, 'coverage' => $coverage, 'open_findings_count' => $openFindings, 'submitted_by' => $actor->id, 'submitted_at' => now()->toIso8601String()];
            $acceptance = UatAcceptance::create(['uat_campaign_id' => $campaign->id, 'submitted_by' => $actor->id, 'criteria_snapshot' => $campaign->acceptance_criteria, 'coverage_snapshot' => $coverage, 'open_findings_count' => $openFindings, 'checksum' => $this->canonicalJson->checksum($snapshot), 'submitted_at' => $snapshot['submitted_at'], 'decision' => 'pending']);
            $campaign->update(['status' => 'review']);
            $this->auditLogger->record($actor, $acceptance, 'change-readiness.uat.acceptance.submitted', "UAT campaign {$campaign->code} submitted for independent acceptance.", metadata: ['campaign_id' => $campaign->id, 'checksum' => $acceptance->checksum, ...$coverage]);

            return $acceptance;
        });
    }
}
