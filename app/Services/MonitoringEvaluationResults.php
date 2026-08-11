<?php

namespace App\Services;

use App\Models\IndicatorDefinition;
use App\Models\IndicatorObservation;
use App\Models\User;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MonitoringEvaluationResults
{
    public function __construct(private ProgrammeCountyScope $countyScope) {}

    /** @return array<string, mixed> */
    public function forUser(User $user, WorkspaceFilters $filters): array
    {
        $base = $this->scopedQuery($user, $filters);
        $statusCounts = (clone $base)->selectRaw('verification_status, COUNT(*) AS aggregate')->groupBy('verification_status')->pluck('aggregate', 'verification_status');
        $numeric = (clone $base)->where('verification_status', 'verified')->where('measure_type', 'actual')->whereNotNull('numeric_value');

        $indicatorAggregates = (clone $numeric)->toBase()
            ->selectRaw('indicator_definition_id, COUNT(*) AS observations_count, AVG(numeric_value) AS average_value, MIN(numeric_value) AS minimum_value, MAX(numeric_value) AS maximum_value, MAX(period_end) AS latest_period_end')
            ->groupBy('indicator_definition_id')
            ->orderByDesc('observations_count')
            ->limit(20)
            ->get()
            ->map(fn (object $aggregate): array => get_object_vars($aggregate));
        $disaggregations = (clone $numeric)->toBase()
            ->where('dimension_key', '!=', 'total')
            ->selectRaw("indicator_definition_id, REGEXP_REPLACE(dimension_key, '^project:[^:]+:', '') AS display_dimension, COUNT(*) AS observations_count, AVG(numeric_value) AS average_value, MAX(period_end) AS latest_period_end")
            ->groupBy('indicator_definition_id')
            ->groupByRaw("REGEXP_REPLACE(dimension_key, '^project:[^:]+:', '')")
            ->orderByDesc('observations_count')
            ->limit(50)
            ->get()
            ->map(fn (object $aggregate): array => get_object_vars($aggregate));
        $definitionIds = $indicatorAggregates->pluck('indicator_definition_id')->merge($disaggregations->pluck('indicator_definition_id'))->unique();
        $definitions = IndicatorDefinition::query()->whereKey($definitionIds)->get(['id', 'code', 'name', 'results_level', 'unit_of_measure'])->keyBy('id');

        $projectContributions = (clone $base)
            ->whereNotNull('source_project_indicator_result_id')
            ->with([
                'indicator:id,code,name,unit_of_measure',
                'county:id,name,code,logo_path',
                'sourceProjectResult:id,project_progress_update_id',
                'sourceProjectResult.progressUpdate:id,devolution_project_id,reporting_date,verification_status',
                'sourceProjectResult.progressUpdate.project:id,code,title',
            ])
            ->latest('period_end')
            ->limit(20)
            ->get();
        $disaggregationRows = $disaggregations->map(function (array $aggregate) use ($definitions): array {
            $definition = $definitions->get($aggregate['indicator_definition_id']);

            return [
                'indicatorId' => $aggregate['indicator_definition_id'],
                'code' => $definition?->code,
                'name' => $definition?->name,
                'dimension' => $aggregate['display_dimension'],
                'observations' => (int) $aggregate['observations_count'],
                'average' => round((float) $aggregate['average_value'], 2),
                'latestPeriodEnd' => $aggregate['latest_period_end'],
            ];
        })->values();
        $performance = $this->targetPerformance($base);

        return [
            'summary' => [
                'total' => (int) $statusCounts->sum(),
                'submitted' => (int) $statusCounts->get('submitted', 0),
                'verified' => (int) $statusCounts->get('verified', 0),
                'rejected' => (int) $statusCounts->get('rejected', 0),
                'projectSourced' => (clone $base)->whereNotNull('source_project_indicator_result_id')->count(),
            ],
            'indicators' => $indicatorAggregates->map(function (array $aggregate) use ($definitions): array {
                $definition = $definitions->get($aggregate['indicator_definition_id']);

                return [
                    'id' => $aggregate['indicator_definition_id'],
                    'code' => $definition?->code,
                    'name' => $definition?->name,
                    'resultsLevel' => $definition?->results_level,
                    'unit' => $definition?->unit_of_measure,
                    'observations' => (int) $aggregate['observations_count'],
                    'average' => round((float) $aggregate['average_value'], 2),
                    'minimum' => round((float) $aggregate['minimum_value'], 2),
                    'maximum' => round((float) $aggregate['maximum_value'], 2),
                    'latestPeriodEnd' => $aggregate['latest_period_end'],
                ];
            })->values(),
            'disaggregations' => $disaggregationRows,
            'performance' => $performance,
            'projectContributions' => $projectContributions->map(fn (IndicatorObservation $observation): array => [
                'id' => $observation->id,
                'indicator' => ['code' => $observation->indicator->code, 'name' => $observation->indicator->name, 'unit' => $observation->indicator->unit_of_measure],
                'county' => $observation->county->identityCell(),
                'project' => ['id' => $observation->sourceProjectResult?->progressUpdate?->project?->id, 'code' => $observation->sourceProjectResult?->progressUpdate?->project?->code, 'title' => $observation->sourceProjectResult?->progressUpdate?->project?->title],
                'periodEnd' => $observation->period_end->toDateString(),
                'dimension' => str($observation->dimension_key)->afterLast(':')->toString(),
                'value' => $observation->numeric_value ?? $observation->narrative_value,
                'verificationStatus' => $observation->verification_status,
                'qualityStatus' => $observation->quality_status,
            ])->values(),
        ];
    }

    /** @param Builder<IndicatorObservation> $base
     * @return array<string, mixed>
     */
    private function targetPerformance(Builder $base): array
    {
        $relations = ['indicator:id,code,name,direction,unit_of_measure,calculation_formula', 'county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'programme:id,name'];
        $actuals = (clone $base)->where('verification_status', 'verified')->where('measure_type', 'actual')->whereNotNull('numeric_value')->with($relations)->orderByDesc('period_end')->limit(500)->get();
        $targets = (clone $base)->where('verification_status', 'verified')->where('measure_type', 'target')->whereNotNull('numeric_value')->with($relations)->orderByDesc('period_end')->limit(1000)->get();
        $latestActuals = $actuals->unique(fn (IndicatorObservation $observation): string => $this->seriesKey($observation))->take(50);
        $rows = $latestActuals->map(function (IndicatorObservation $actual) use ($targets): array {
            $target = $this->applicableTarget($actual, $targets);

            return $this->performanceRow($actual, $target);
        })->values();
        $matched = $rows->whereNotNull('target');
        $trends = $actuals->sortBy('period_end')->groupBy(fn (IndicatorObservation $observation): string => $this->seriesKey($observation))
            ->filter(fn (Collection $observations): bool => $observations->count() >= 2)
            ->take(8)
            ->map(function (Collection $observations) use ($targets): array {
                /** @var IndicatorObservation $latest */
                $latest = $observations->last();

                return [
                    'key' => $this->seriesKey($latest),
                    'indicator' => ['id' => $latest->indicator->id, 'code' => $latest->indicator->code, 'name' => $latest->indicator->name, 'unit' => $latest->indicator->unit_of_measure],
                    'county' => $latest->county->identityCell(),
                    'dimension' => str($latest->dimension_key)->afterLast(':')->toString(),
                    'points' => $observations->take(-12)->map(function (IndicatorObservation $actual) use ($targets): array {
                        $target = $this->applicableTarget($actual, $targets);
                        $targetValue = $target?->numeric_value;

                        return ['period' => $actual->period_end->toDateString(), 'actual' => round((float) $actual->numeric_value, 2), 'target' => $targetValue === null ? null : round((float) $targetValue, 2)];
                    })->values()->all(),
                ];
            })->values()->all();

        return [
            'summary' => [
                'series' => $rows->count(),
                'withTarget' => $matched->count(),
                'met' => $matched->where('status', 'met')->count(),
                'offTrack' => $matched->where('status', 'off_track')->count(),
                'averageAttainment' => $matched->isEmpty() ? null : round((float) $matched->avg('attainment'), 2),
            ],
            'rows' => $rows->all(),
            'trends' => $trends,
            'methodology' => 'Each latest verified actual is matched to the latest verified target for the same indicator version, county, programme and dimension whose reporting period overlaps the actual period; otherwise the latest prior target is used.',
        ];
    }

    /** @param Collection<int, IndicatorObservation> $targets */
    private function applicableTarget(IndicatorObservation $actual, Collection $targets): ?IndicatorObservation
    {
        $candidates = $targets->filter(fn (IndicatorObservation $target): bool => $this->seriesKey($target) === $this->seriesKey($actual));

        return $candidates->first(fn (IndicatorObservation $target): bool => $target->period_start->lte($actual->period_end) && $target->period_end->gte($actual->period_start))
            ?? $candidates->first(fn (IndicatorObservation $target): bool => $target->period_end->lte($actual->period_end));
    }

    /** @return array<string, mixed> */
    private function performanceRow(IndicatorObservation $actual, ?IndicatorObservation $target): array
    {
        $actualValue = (float) $actual->numeric_value;
        $targetValue = $target === null ? null : (float) $target->numeric_value;
        $variance = $targetValue === null ? null : $actualValue - $targetValue;
        $variancePercentage = $targetValue === null || $targetValue === 0.0 ? null : ($variance / abs($targetValue)) * 100;
        $direction = $actual->indicator->direction;
        $tolerance = (float) ($actual->indicator->calculation_formula['tolerance_percentage'] ?? 0);
        $met = match ($direction) {
            'decrease' => $targetValue !== null && $actualValue <= $targetValue,
            'maintain' => $variancePercentage !== null && abs($variancePercentage) <= $tolerance,
            default => $targetValue !== null && $actualValue >= $targetValue,
        };
        $attainment = match ($direction) {
            'decrease' => $targetValue === null ? null : ($actualValue <= 0.0 ? 100.0 : ($targetValue / $actualValue) * 100),
            'maintain' => $variancePercentage === null ? null : max(0, 100 - abs($variancePercentage)),
            default => $targetValue === null || $targetValue == 0.0 ? null : ($actualValue / $targetValue) * 100,
        };

        return [
            'id' => $actual->id,
            'indicator' => ['id' => $actual->indicator->id, 'code' => $actual->indicator->code, 'name' => $actual->indicator->name, 'unit' => $actual->indicator->unit_of_measure, 'direction' => $direction],
            'county' => $actual->county->identityCell(),
            'programme' => $actual->programme?->name,
            'dimension' => str($actual->dimension_key)->afterLast(':')->toString(),
            'periodEnd' => $actual->period_end->toDateString(),
            'actual' => round($actualValue, 2),
            'target' => $targetValue === null ? null : round($targetValue, 2),
            'targetObservationId' => $target?->id,
            'variance' => $variance === null ? null : round($variance, 2),
            'variancePercentage' => $variancePercentage === null ? null : round($variancePercentage, 2),
            'attainment' => $attainment === null ? null : round($attainment, 2),
            'status' => $targetValue === null ? 'target_missing' : ($met ? 'met' : 'off_track'),
        ];
    }

    private function seriesKey(IndicatorObservation $observation): string
    {
        return implode('|', [$observation->indicator_definition_id, $observation->county_id, $observation->programme_id ?? '', $observation->dimension_key]);
    }

    /** @return Builder<IndicatorObservation> */
    private function scopedQuery(User $user, WorkspaceFilters $filters): Builder
    {
        return IndicatorObservation::query()
            ->whereIn('county_id', $this->countyScope->query($user)->select('id'))
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('period_end', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('period_start', '<=', $to))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
            ->when($filters->sectorId, fn (Builder $query, string $sectorId) => $query->whereHas('indicator', fn (Builder $indicator) => $indicator->where('sector_id', $sectorId)))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('verification_status', $status))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('source_reference', 'ilike', '%'.$filters->search.'%')->orWhereHas('indicator', fn (Builder $indicator) => $indicator->where('name', 'ilike', '%'.$filters->search.'%')->orWhere('code', 'ilike', '%'.$filters->search.'%'))));
    }
}
