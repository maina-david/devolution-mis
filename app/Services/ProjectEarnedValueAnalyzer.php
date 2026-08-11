<?php

namespace App\Services;

use App\Models\DevolutionProject;
use App\Models\ProjectScheduleBaseline;
use Carbon\CarbonImmutable;

class ProjectEarnedValueAnalyzer
{
    /**
     * @return array{available:bool,as_of:string,method:string,baseline_version:int|null,budget_at_completion:float,planned_value:float|null,earned_value:float,actual_cost:float,cost_performance_index:float|null,schedule_performance_index:float|null,estimate_at_completion:float|null,estimate_to_complete:float|null,variance_at_completion:float|null,to_complete_performance_index:float|null,planned_completion_percent:float|null}
     */
    public function analyze(DevolutionProject $project, ?ProjectScheduleBaseline $baseline, CarbonImmutable $asOf): array
    {
        $budget = (float) $project->approved_budget;
        $actualCost = (float) $project->actual_expenditure;
        $earnedValue = round($budget * ((float) $project->physical_progress / 100), 2);
        $base = [
            'available' => $baseline !== null && $budget > 0,
            'as_of' => $asOf->toDateString(),
            'method' => 'CPI-only earned value forecast using approved weighted schedule baseline',
            'baseline_version' => $baseline?->version,
            'budget_at_completion' => round($budget, 2),
            'earned_value' => $earnedValue,
            'actual_cost' => round($actualCost, 2),
        ];
        if ($baseline === null || $budget <= 0) {
            return $base + ['planned_value' => null, 'cost_performance_index' => null, 'schedule_performance_index' => null, 'estimate_at_completion' => null, 'estimate_to_complete' => null, 'variance_at_completion' => null, 'to_complete_performance_index' => null, 'planned_completion_percent' => null];
        }

        $plannedCompletion = 0.0;
        foreach ($baseline->schedule_snapshot as $milestone) {
            $start = CarbonImmutable::parse((string) $milestone['planned_start_date'])->startOfDay();
            $end = CarbonImmutable::parse((string) $milestone['planned_end_date'])->startOfDay();
            $fraction = match (true) {
                $asOf->startOfDay()->lessThan($start) => 0.0,
                $asOf->startOfDay()->greaterThanOrEqualTo($end) => 1.0,
                default => ((int) $start->diffInDays($asOf->startOfDay()) + 1) / ((int) $start->diffInDays($end) + 1),
            };
            $plannedCompletion += ((float) $milestone['weight'] / 100) * $fraction;
        }
        $plannedValue = round($budget * min(1, $plannedCompletion), 2);
        $cpi = $actualCost > 0 ? $earnedValue / $actualCost : null;
        $spi = $plannedValue > 0 ? $earnedValue / $plannedValue : null;
        $eac = $cpi !== null && $cpi > 0 ? $budget / $cpi : null;
        $remainingBudget = $budget - $actualCost;
        $tcpi = $remainingBudget > 0 ? ($budget - $earnedValue) / $remainingBudget : null;

        return $base + [
            'planned_value' => $plannedValue,
            'cost_performance_index' => $cpi === null ? null : round($cpi, 4),
            'schedule_performance_index' => $spi === null ? null : round($spi, 4),
            'estimate_at_completion' => $eac === null ? null : round($eac, 2),
            'estimate_to_complete' => $eac === null ? null : round(max(0, $eac - $actualCost), 2),
            'variance_at_completion' => $eac === null ? null : round($budget - $eac, 2),
            'to_complete_performance_index' => $tcpi === null ? null : round($tcpi, 4),
            'planned_completion_percent' => round(min(1, $plannedCompletion) * 100, 2),
        ];
    }
}
