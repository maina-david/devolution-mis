<?php

namespace App\Services;

use App\Models\PerformancePlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DepartmentalPerformanceAnalytics
{
    /**
     * @param  Builder<PerformancePlan>  $visiblePlans
     * @return array<string, mixed>
     */
    public function summarize(Builder $visiblePlans): array
    {
        $plans = $visiblePlans->clone()->with(['cycle:id,name,period_start', 'organization:id,name'])
            ->where('status', 'finalized')->get(['id', 'performance_cycle_id', 'organization_id', 'final_score', 'capacity_gap_summary']);
        $trends = $plans->groupBy('performance_cycle_id')->map(function ($cyclePlans): array {
            /** @var PerformancePlan $first */
            $first = $cyclePlans->first();

            return ['id' => $first->performance_cycle_id, 'cycle' => $first->cycle->name, 'periodStart' => Carbon::parse($first->cycle->period_start)->toDateString(), 'completed' => $cyclePlans->count(), 'averageScore' => round((float) $cyclePlans->avg('final_score'), 2)];
        })->sortBy('periodStart')->values();
        $organizations = $plans->groupBy(fn (PerformancePlan $plan): string => $plan->organization_id === null ? 'Unassigned' : $plan->organization->name)->map(fn ($organizationPlans, string $name): array => ['id' => sha1($name), 'organization' => $name, 'completed' => $organizationPlans->count(), 'averageScore' => round((float) $organizationPlans->avg('final_score'), 2)])->sortByDesc('averageScore')->values();
        $capacityGaps = $plans->filter(fn (PerformancePlan $plan): bool => filled($plan->capacity_gap_summary))->groupBy(fn (PerformancePlan $plan): string => trim((string) $plan->capacity_gap_summary))->map(fn ($gapPlans, string $gap): array => ['id' => sha1($gap), 'gap' => $gap, 'affectedPlans' => $gapPlans->count()])->sortByDesc('affectedPlans')->values();

        return ['summary' => ['finalized' => $plans->count(), 'averageScore' => $plans->isEmpty() ? null : round((float) $plans->avg('final_score'), 2), 'capacityGaps' => $capacityGaps->count()], 'trends' => $trends, 'organizations' => $organizations, 'capacityGaps' => $capacityGaps];
    }
}
