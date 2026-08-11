<?php

namespace App\Services;

use App\Models\AssessmentResultPublication;
use App\Models\CitizenCase;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\EvaluationFinding;
use App\Models\IndicatorObservation;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class AnalyticsMetricCatalogue
{
    public function __construct(
        private ProgrammeCountyScope $countyScope,
        private MonitoringEvaluationResults $monitoringEvaluationResults,
    ) {}

    /** @return array<string, string> */
    public function options(): array
    {
        return [
            'counties.total' => 'Counties in scope',
            'projects.active' => 'Active projects',
            'assessments.published' => 'Published assessment results',
            'citizen-cases.open' => 'Open citizen cases',
            'indicators.verified' => 'Verified indicator observations',
            'indicators.target-attainment' => 'Average verified target attainment',
            'evaluation-findings.overdue' => 'Overdue evaluation recommendations',
            'evaluation-findings.closed' => 'Closed evaluation recommendations',
            'training.competent' => 'Competent training completions',
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{value: int|float|null, unit: string, provenance: string, measured_at: string, series: list<array{county: array<string, mixed>, value: int|float|null}>}
     */
    public function evaluate(User $user, string $metricKey, array $filters = [], ?string $disaggregation = null): array
    {
        if (! array_key_exists($metricKey, $this->options())) {
            throw new InvalidArgumentException('Unsupported governed analytics metric.');
        }
        $countyIds = $this->countyScope->query($user)->pluck('id')->map(fn (mixed $id): string => (string) $id)->values();
        $requestedCountyId = is_string($filters['county_id'] ?? null) ? $filters['county_id'] : null;
        if ($requestedCountyId !== null) {
            $countyIds = $countyIds->filter(fn (string $countyId): bool => $countyId === $requestedCountyId)->values();
        }
        $from = is_string($filters['from'] ?? null) ? $filters['from'] : null;
        $to = is_string($filters['to'] ?? null) ? $filters['to'] : null;
        $authorizedCountyIds = array_values($countyIds->all());
        $value = $this->value($user, $metricKey, $authorizedCountyIds, $from, $to);
        $series = $disaggregation === 'county' ? array_values(County::query()->whereKey($countyIds)->orderBy('code')->get()->map(fn (County $county): array => ['county' => $county->identityCell(), 'value' => $this->value($user, $metricKey, [$county->id], $from, $to)])->all()) : [];

        return ['value' => $value, 'unit' => $this->unit($metricKey), 'provenance' => $this->options()[$metricKey].' · authorized PostgreSQL records · county scope applied before aggregation', 'measured_at' => now()->toIso8601String(), 'series' => $series];
    }

    /** @param list<string> $countyIds */
    private function value(User $user, string $metricKey, array $countyIds, ?string $from, ?string $to): int|float|null
    {
        if ($countyIds === []) {
            return $metricKey === 'indicators.target-attainment' ? null : 0;
        }

        if ($metricKey === 'indicators.target-attainment') {
            $results = $this->monitoringEvaluationResults->forUser($user, new WorkspaceFilters($from, $to, '', 15, countyId: count($countyIds) === 1 ? $countyIds[0] : null));
            $attainment = data_get($results, 'performance.summary.averageAttainment');

            return is_numeric($attainment) ? (float) $attainment : null;
        }

        $query = match ($metricKey) {
            'counties.total' => County::query()->whereKey($countyIds),
            'projects.active' => DevolutionProject::query()->whereIn('lead_county_id', $countyIds)->whereNotIn('status', ['closed', 'cancelled']),
            'assessments.published' => AssessmentResultPublication::query()->whereIn('county_id', $countyIds),
            'citizen-cases.open' => CitizenCase::query()->whereIn('county_id', $countyIds)->whereNotIn('status', ['resolved', 'closed']),
            'indicators.verified' => IndicatorObservation::query()->whereIn('county_id', $countyIds)->where('verification_status', 'verified'),
            'evaluation-findings.overdue' => EvaluationFinding::query()->whereIn('county_id', $countyIds)->where('status', '!=', 'closed')->whereDate('due_at', '<', today()),
            'evaluation-findings.closed' => EvaluationFinding::query()->whereIn('county_id', $countyIds)->where('status', 'closed'),
            'training.competent' => TrainingParticipant::query()->whereIn('county_id', $countyIds)->whereNotNull('completed_at'),
            default => throw new InvalidArgumentException('Unsupported governed analytics metric.'),
        };
        if ($metricKey !== 'counties.total') {
            $dateColumn = match ($metricKey) {
                'evaluation-findings.overdue' => 'due_at',
                'evaluation-findings.closed' => 'closed_at',
                default => 'created_at',
            };
            $query->when($from, fn (Builder $query, string $from) => $query->whereDate($dateColumn, '>=', $from))->when($to, fn (Builder $query, string $to) => $query->whereDate($dateColumn, '<=', $to));
        }

        return $query->count();
    }

    private function unit(string $metricKey): string
    {
        return $metricKey === 'indicators.target-attainment' ? 'percent' : 'records';
    }
}
