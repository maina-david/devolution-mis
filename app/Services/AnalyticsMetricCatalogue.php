<?php

namespace App\Services;

use App\Models\AssessmentResultPublication;
use App\Models\CitizenCase;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\EvaluationFinding;
use App\Models\IndicatorObservation;
use App\Models\User;
use App\Support\WorkspaceFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
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
        ];
    }

    /** @param array<string, mixed> $filters
     * @return array{value: int|float|null, unit: string, provenance: string, measured_at: string, series: list<array{county: array<string, mixed>, value: int|float|null}>, trend: list<array{period: string, label: string, value: int|float|null}>}
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
        $timeGrain = is_string($filters['time_grain'] ?? null) ? $filters['time_grain'] : null;
        $trend = $timeGrain === null ? [] : $this->trend($user, $metricKey, $authorizedCountyIds, $from, $to, $timeGrain);

        return ['value' => $value, 'unit' => $this->unit($metricKey), 'provenance' => $this->options()[$metricKey].' · authorized PostgreSQL records · county scope applied before aggregation', 'measured_at' => now()->toIso8601String(), 'series' => $series, 'trend' => $trend];
    }

    /** @param list<string> $countyIds
     * @return list<array{period: string, label: string, value: int|float|null}>
     */
    private function trend(User $user, string $metricKey, array $countyIds, ?string $from, ?string $to, string $timeGrain): array
    {
        $end = $to === null ? CarbonImmutable::today() : CarbonImmutable::parse($to)->endOfDay();
        $start = $from === null ? match ($timeGrain) {
            'year' => $end->startOfYear()->subYears(4),
            'quarter' => $end->startOfQuarter()->subQuarters(7),
            default => $end->startOfMonth()->subMonths(11),
        } : CarbonImmutable::parse($from)->startOfDay();

        $maximumStart = match ($timeGrain) {
            'year' => $end->startOfYear()->subYears(9),
            'quarter' => $end->startOfQuarter()->subQuarters(19),
            default => $end->startOfMonth()->subMonths(35),
        };
        $start = $start->max($maximumStart);
        $cacheKey = 'analytics-trend:'.hash('sha256', json_encode([$user->id, $metricKey, $countyIds, $start->toDateString(), $end->toDateString(), $timeGrain], JSON_THROW_ON_ERROR));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user, $metricKey, $countyIds, $start, $end, $timeGrain): array {
            $cursor = match ($timeGrain) {
                'year' => $start->startOfYear(),
                'quarter' => $start->startOfQuarter(),
                default => $start->startOfMonth(),
            };
            $points = [];
            while ($cursor->lessThanOrEqualTo($end)) {
                $bucketEnd = (match ($timeGrain) {
                    'year' => $cursor->endOfYear(),
                    'quarter' => $cursor->endOfQuarter(),
                    default => $cursor->endOfMonth(),
                })->min($end);
                $points[] = [
                    'period' => $cursor->toDateString(),
                    'label' => match ($timeGrain) {
                        'year' => $cursor->format('Y'),
                        'quarter' => 'Q'.$cursor->quarter.' '.$cursor->format('Y'),
                        default => $cursor->format('Y-m'),
                    },
                    'value' => $this->value($user, $metricKey, $countyIds, $cursor->max($start)->toDateString(), $bucketEnd->toDateString()),
                ];
                $cursor = match ($timeGrain) {
                    'year' => $cursor->addYear(),
                    'quarter' => $cursor->addQuarter(),
                    default => $cursor->addMonth(),
                };
            }

            return $points;
        });
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
