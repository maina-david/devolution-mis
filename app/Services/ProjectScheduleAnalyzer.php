<?php

namespace App\Services;

use App\Models\ProjectMilestone;
use App\Models\ProjectScheduleBaseline;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProjectScheduleAnalyzer
{
    /**
     * @param  Collection<int, ProjectMilestone>  $milestones
     * @return array{as_of:string, baseline_version:int|null, baseline_finish:string|null, current_finish:string, forecast_finish:string, planned_variance_days:int|null, forecast_variance_days:int|null, critical_path_ids:list<string>, critical_path_codes:list<string>, milestones:list<array{id:string,code:string,is_critical:bool,total_float_days:int,baseline_end:string|null,current_end:string,forecast_end:string,planned_variance_days:int|null,forecast_variance_days:int|null}>}
     */
    public function variance(Collection $milestones, ?ProjectScheduleBaseline $baseline, CarbonImmutable $asOf): array
    {
        $analysis = $this->analyze($milestones);
        $analysisById = collect($analysis['milestones'])->keyBy('id');
        $baselineById = collect($baseline->schedule_snapshot ?? [])->keyBy('id');
        $rows = [];
        foreach ($milestones->sortBy('code') as $milestone) {
            $baselineRow = $baselineById->get($milestone->id);
            $baselineEnd = is_array($baselineRow) && is_string($baselineRow['planned_end_date'] ?? null) ? $baselineRow['planned_end_date'] : null;
            $currentEnd = $milestone->planned_end_date->toDateString();
            $forecastEnd = $this->forecastEnd($milestone, $asOf)->toDateString();
            $timing = $analysisById->get($milestone->id);

            $rows[] = [
                'id' => $milestone->id,
                'code' => $milestone->code,
                'is_critical' => is_array($timing) && $timing['is_critical'],
                'total_float_days' => is_array($timing) ? $timing['total_float_days'] : 0,
                'baseline_end' => $baselineEnd,
                'current_end' => $currentEnd,
                'forecast_end' => $forecastEnd,
                'planned_variance_days' => $baselineEnd === null ? null : (int) CarbonImmutable::parse($baselineEnd)->diffInDays(CarbonImmutable::parse($currentEnd), false),
                'forecast_variance_days' => $baselineEnd === null ? null : (int) CarbonImmutable::parse($baselineEnd)->diffInDays(CarbonImmutable::parse($forecastEnd), false),
            ];
        }
        $forecastFinish = CarbonImmutable::parse((string) collect($rows)->max('forecast_end'));
        $baselineProjectFinish = $baseline->critical_path_analysis['project_finish'] ?? null;
        $baselineFinish = is_string($baselineProjectFinish) ? $baselineProjectFinish : null;

        return [
            'as_of' => $asOf->toDateString(),
            'baseline_version' => $baseline?->version,
            'baseline_finish' => $baselineFinish,
            'current_finish' => $analysis['project_finish'],
            'forecast_finish' => $forecastFinish->toDateString(),
            'planned_variance_days' => $baselineFinish === null ? null : (int) CarbonImmutable::parse($baselineFinish)->diffInDays(CarbonImmutable::parse($analysis['project_finish']), false),
            'forecast_variance_days' => $baselineFinish === null ? null : (int) CarbonImmutable::parse($baselineFinish)->diffInDays($forecastFinish, false),
            'critical_path_ids' => $analysis['critical_path_ids'],
            'critical_path_codes' => $analysis['critical_path_codes'],
            'milestones' => $rows,
        ];
    }

    /**
     * @param  Collection<int, ProjectMilestone>  $milestones
     * @return list<array{id:string,code:string,title:string,planned_start_date:string,planned_end_date:string,weight:float,dependencies:list<string>}>
     */
    public function snapshot(Collection $milestones): array
    {
        $snapshot = [];
        foreach ($milestones->sortBy('code') as $milestone) {
            $dependencies = $milestone->dependencies ?? [];
            sort($dependencies);
            $snapshot[] = [
                'id' => $milestone->id,
                'code' => $milestone->code,
                'title' => $milestone->title,
                'planned_start_date' => $milestone->planned_start_date->toDateString(),
                'planned_end_date' => $milestone->planned_end_date->toDateString(),
                'weight' => (float) $milestone->weight,
                'dependencies' => $dependencies,
            ];
        }

        return $snapshot;
    }

    /**
     * @param  list<array<string, mixed>>  $snapshot
     * @param  array<string, mixed>  $analysis
     */
    public function checksum(array $snapshot, array $analysis): string
    {
        return hash('sha256', json_encode(['schedule' => $snapshot, 'analysis' => $analysis], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  Collection<int, ProjectMilestone>  $milestones
     * @return array{project_start:string, project_finish:string, duration_days:int, critical_path_ids:list<string>, critical_path_codes:list<string>, milestones:list<array{id:string,code:string,earliest_start:string,earliest_finish:string,latest_start:string,latest_finish:string,total_float_days:int,is_critical:bool}>}
     */
    public function analyze(Collection $milestones): array
    {
        if ($milestones->isEmpty()) {
            throw ValidationException::withMessages(['baseline_reason' => 'At least one milestone is required before a schedule baseline can be captured.']);
        }

        $ordered = $milestones->sortBy(fn (ProjectMilestone $milestone): string => $milestone->code.'-'.$milestone->id)->values();
        $byId = $ordered->keyBy('id');
        $firstMilestone = $ordered->firstOrFail();
        $projectStart = CarbonImmutable::parse($firstMilestone->planned_start_date)->startOfDay();
        foreach ($ordered as $milestone) {
            $milestoneStart = CarbonImmutable::parse($milestone->planned_start_date);
            if ($milestoneStart->lessThan($projectStart)) {
                $projectStart = $milestoneStart->startOfDay();
            }
        }
        /** @var array<string, list<string>> $dependencies */
        $dependencies = [];
        /** @var array<string, list<string>> $successors */
        $successors = array_fill_keys($byId->keys()->all(), []);
        foreach ($ordered as $milestone) {
            $ids = $milestone->dependencies ?? [];
            foreach ($ids as $dependencyId) {
                if (! $byId->has($dependencyId)) {
                    throw ValidationException::withMessages(['baseline_reason' => "Milestone {$milestone->code} references a dependency outside the current project schedule."]);
                }
                $successors[$dependencyId][] = $milestone->id;
            }
            $dependencies[$milestone->id] = $ids;
        }

        $topologicalIds = $this->topologicalIds($dependencies);
        /** @var array<string, array{duration:int, earliest_start:int, earliest_finish:int}> $forward */
        $forward = [];
        $projectDuration = 0;
        foreach ($topologicalIds as $milestoneId) {
            $milestone = $byId->get($milestoneId);
            if (! $milestone instanceof ProjectMilestone) {
                throw ValidationException::withMessages(['baseline_reason' => 'The milestone dependency graph references a missing schedule item.']);
            }
            $plannedOffset = (int) $projectStart->diffInDays(CarbonImmutable::parse($milestone->planned_start_date), false);
            $dependencyFinish = 0;
            foreach ($dependencies[$milestoneId] as $dependencyId) {
                $dependencyFinish = max($dependencyFinish, $forward[$dependencyId]['earliest_finish']);
            }
            $earliestStart = max($plannedOffset, $dependencyFinish);
            $duration = max(1, (int) CarbonImmutable::parse($milestone->planned_start_date)->diffInDays(CarbonImmutable::parse($milestone->planned_end_date)) + 1);
            $forward[$milestoneId] = ['duration' => $duration, 'earliest_start' => $earliestStart, 'earliest_finish' => $earliestStart + $duration];
            $projectDuration = max($projectDuration, $forward[$milestoneId]['earliest_finish']);
        }

        /** @var array<string, array{latest_start:int, latest_finish:int}> $backward */
        $backward = [];
        foreach (array_reverse($topologicalIds) as $milestoneId) {
            $latestFinish = $projectDuration;
            foreach ($successors[$milestoneId] as $successorId) {
                $latestFinish = min($latestFinish, $backward[$successorId]['latest_start']);
            }
            $backward[$milestoneId] = ['latest_finish' => $latestFinish, 'latest_start' => $latestFinish - $forward[$milestoneId]['duration']];
        }

        $rows = [];
        $criticalPathIds = [];
        $criticalPathCodes = [];
        foreach ($topologicalIds as $milestoneId) {
            $milestone = $byId->get($milestoneId);
            if (! $milestone instanceof ProjectMilestone) {
                throw ValidationException::withMessages(['baseline_reason' => 'The milestone dependency graph references a missing schedule item.']);
            }
            $float = $backward[$milestoneId]['latest_start'] - $forward[$milestoneId]['earliest_start'];
            $isCritical = $float === 0;
            if ($isCritical) {
                $criticalPathIds[] = $milestoneId;
                $criticalPathCodes[] = $milestone->code;
            }

            $rows[] = [
                'id' => $milestoneId,
                'code' => $milestone->code,
                'earliest_start' => $projectStart->addDays($forward[$milestoneId]['earliest_start'])->toDateString(),
                'earliest_finish' => $projectStart->addDays($forward[$milestoneId]['earliest_finish'] - 1)->toDateString(),
                'latest_start' => $projectStart->addDays($backward[$milestoneId]['latest_start'])->toDateString(),
                'latest_finish' => $projectStart->addDays($backward[$milestoneId]['latest_finish'] - 1)->toDateString(),
                'total_float_days' => $float,
                'is_critical' => $isCritical,
            ];
        }

        return [
            'project_start' => $projectStart->toDateString(),
            'project_finish' => $projectStart->addDays($projectDuration - 1)->toDateString(),
            'duration_days' => $projectDuration,
            'critical_path_ids' => $criticalPathIds,
            'critical_path_codes' => $criticalPathCodes,
            'milestones' => $rows,
        ];
    }

    /**
     * @param  array<string, list<string>>  $dependencies
     * @return list<string>
     */
    private function topologicalIds(array $dependencies): array
    {
        $pending = array_fill_keys(array_keys($dependencies), true);
        $resolved = [];
        while ($pending !== []) {
            $available = collect(array_keys($pending))->filter(fn (string $id): bool => collect($dependencies[$id])->every(fn (string $dependencyId): bool => in_array($dependencyId, $resolved, true)))->sort()->values()->all();
            if ($available === []) {
                throw ValidationException::withMessages(['baseline_reason' => 'The milestone dependency graph contains a cycle.']);
            }
            foreach ($available as $id) {
                $resolved[] = $id;
                unset($pending[$id]);
            }
        }

        return $resolved;
    }

    private function forecastEnd(ProjectMilestone $milestone, CarbonImmutable $asOf): CarbonImmutable
    {
        if ($milestone->actual_end_date !== null) {
            return CarbonImmutable::parse($milestone->actual_end_date);
        }
        $progress = (float) $milestone->progress;
        if ($milestone->actual_start_date === null || $progress <= 0) {
            return CarbonImmutable::parse($milestone->planned_end_date);
        }

        $actualStart = CarbonImmutable::parse($milestone->actual_start_date);
        $forecastAsOf = $asOf->greaterThan($actualStart) ? $asOf : $actualStart;
        $elapsedDays = max(1, (int) $actualStart->diffInDays($forecastAsOf) + 1);
        $estimatedDuration = max($elapsedDays, (int) ceil($elapsedDays / ($progress / 100)));

        return $actualStart->addDays($estimatedDuration - 1);
    }
}
