<?php

namespace App\Services;

use App\Models\AssessmentResultPublication;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AssessmentAnalyticsService
{
    public function __construct(private ProgrammeCountyScope $countyScope, private AssessmentBenchmarkService $benchmarkService) {}

    /**
     * @param  array{from?: string|null, to?: string|null, cycle_id?: string|null, county_id?: string|null, function_page?: int, ranking_page?: int, per_page?: int}  $filters
     * @return array<string, mixed>
     */
    public function report(User $user, array $filters): array
    {
        $countyIds = $this->authorizedCountyIds($user);
        $selectedCountyId = $filters['county_id'] ?? null;
        abort_if($selectedCountyId !== null && ! in_array($selectedCountyId, $countyIds, true), 403);

        $publications = $this->query($countyIds, $filters)
            ->with(['county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'cycle:id,code,name,period_start,period_end'])
            ->get();

        $cycleSeries = $publications->groupBy('assessment_cycle_id')->map(function (Collection $items): array {
            /** @var AssessmentResultPublication $first */
            $first = $items->first();
            $scores = $items->map(fn (AssessmentResultPublication $publication): float => (float) $publication->score);

            return [
                'id' => $first->assessment_cycle_id,
                'code' => $first->cycle->code,
                'name' => $first->cycle->name,
                'periodStart' => $first->cycle->period_start->toDateString(),
                'periodEnd' => $first->cycle->period_end->toDateString(),
                'average' => round($scores->average(), 2),
                'minimum' => round($scores->min(), 2),
                'maximum' => round($scores->max(), 2),
                'publications' => $items->count(),
            ];
        })->sortBy('periodStart')->values()->all();

        $countySeries = $publications->groupBy('county_id')->map(function (Collection $items): array {
            /** @var AssessmentResultPublication $first */
            $first = $items->first();

            return [
                'county' => $first->county->identityCell(),
                'results' => $items->sortBy(fn (AssessmentResultPublication $publication): string => $publication->cycle->period_start->toDateString())->map(fn (AssessmentResultPublication $publication): array => [
                    'assessmentId' => $publication->assessment_id,
                    'cycleId' => $publication->assessment_cycle_id,
                    'cycle' => $publication->cycle->code,
                    'score' => (float) $publication->score,
                    'performanceBand' => $publication->performance_band,
                    'checksum' => $publication->checksum,
                ])->values()->all(),
            ];
        })->sortBy(fn (array $item): int => (int) $item['county']['code'])->values()->all();

        $functionSeries = $this->functionSeries($publications);
        $selectedCycleId = $filters['cycle_id'] ?? ($publications->sortByDesc(fn (AssessmentResultPublication $publication): string => $publication->cycle->period_start->toDateString())->first()?->assessment_cycle_id);
        $perPage = $filters['per_page'] ?? 10;
        $rankings = $selectedCycleId ? $this->benchmarkService->rankings($selectedCycleId, $selectedCountyId ? [$selectedCountyId] : $countyIds) : [];

        return [
            'summary' => [
                'publications' => $publications->count(),
                'counties' => $publications->pluck('county_id')->unique()->count(),
                'cycles' => $publications->pluck('assessment_cycle_id')->unique()->count(),
                'averageScore' => $publications->isEmpty() ? null : round($publications->average(fn (AssessmentResultPublication $publication): float => (float) $publication->score), 2),
            ],
            'cycles' => $cycleSeries,
            'counties' => $countySeries,
            'functions' => $this->paginate($functionSeries, $filters['function_page'] ?? 1, $perPage, 'function_page'),
            'rankings' => $this->paginate($rankings, $filters['ranking_page'] ?? 1, $perPage, 'ranking_page'),
            'options' => $this->options($countyIds),
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null, cycle_id?: string|null, county_id?: string|null, function_page?: int, ranking_page?: int, per_page?: int}  $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(User $user, array $filters): array
    {
        $report = $this->report($user, $filters);
        $rows = [];
        foreach ($report['counties'] as $countySeries) {
            foreach ($countySeries['results'] as $result) {
                $rows[] = [
                    'county' => $countySeries['county'],
                    'cycle' => $result['cycle'],
                    'score' => $result['score'],
                    'performance_band' => $result['performanceBand'],
                    'publication_checksum' => $result['checksum'],
                    'assessment_id' => $result['assessmentId'],
                ];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private function authorizedCountyIds(User $user): array
    {
        $countyIds = [];
        foreach ($this->countyScope->query($user)->pluck('id') as $countyId) {
            $countyIds[] = (string) $countyId;
        }

        return $countyIds;
    }

    /**
     * @param  list<string>  $countyIds
     * @param  array{from?: string|null, to?: string|null, cycle_id?: string|null, county_id?: string|null, function_page?: int, ranking_page?: int, per_page?: int}  $filters
     * @return Builder<AssessmentResultPublication>
     */
    private function query(array $countyIds, array $filters): Builder
    {
        return AssessmentResultPublication::query()
            ->whereIn('county_id', $countyIds)
            ->when($filters['county_id'] ?? null, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
            ->when($filters['cycle_id'] ?? null, fn (Builder $query, string $cycleId) => $query->where('assessment_cycle_id', $cycleId))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('published_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('published_at', '<=', $to))
            ->orderBy('published_at');
    }

    /**
     * @param  Collection<int, AssessmentResultPublication>  $publications
     * @return list<array<string, mixed>>
     */
    private function functionSeries(Collection $publications): array
    {
        $groups = [];
        foreach ($publications as $publication) {
            foreach ($publication->function_profile as $function) {
                $code = (string) ($function['code'] ?? 'Uncoded');
                $key = $publication->assessment_cycle_id.':'.$code;
                $groups[$key] ??= ['cycleId' => $publication->assessment_cycle_id, 'cycle' => $publication->cycle->code, 'periodStart' => $publication->cycle->period_start->toDateString(), 'code' => $code, 'name' => (string) ($function['name'] ?? $code), 'scores' => []];
                $groups[$key]['scores'][] = (float) ($function['score'] ?? 0);
            }
        }

        return array_values(collect($groups)->map(fn (array $group): array => [
            'cycleId' => $group['cycleId'],
            'cycle' => $group['cycle'],
            'code' => $group['code'],
            'name' => $group['name'],
            'average' => round(collect($group['scores'])->average(), 2),
        ])->sortBy(fn (array $group): string => $group['cycle'].'-'.$group['code'])->values()->all());
    }

    /**
     * @param  list<string>  $countyIds
     * @return array{cycles: list<array{id: string, name: string}>, counties: list<array{id: string, name: string, logoUrl: string|null}>}
     */
    private function options(array $countyIds): array
    {
        $cycles = array_values(AssessmentResultPublication::query()->whereIn('county_id', $countyIds)->select('assessment_cycle_id')->distinct()->with('cycle:id,code,name,period_start')->get()->sortByDesc(fn (AssessmentResultPublication $publication): string => $publication->cycle->period_start->toDateString())->map(fn (AssessmentResultPublication $publication): array => ['id' => $publication->assessment_cycle_id, 'name' => "{$publication->cycle->name} ({$publication->cycle->code})"])->values()->all());
        $counties = array_values(County::query()->whereIn('id', $countyIds)->orderBy('code')->get(['id', 'name', 'logo_path'])->map(fn (County $county): array => ['id' => $county->id, 'name' => $county->name, 'logoUrl' => $county->logo_path])->values()->all());

        return ['cycles' => $cycles, 'counties' => $counties];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{rows: list<array<string, mixed>>, pagination: array{currentPage: int, lastPage: int, perPage: int, total: int, pageName: string}}
     */
    private function paginate(array $items, int $page, int $perPage, string $pageName): array
    {
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $lastPage);

        return [
            'rows' => array_slice($items, ($currentPage - 1) * $perPage, $perPage),
            'pagination' => ['currentPage' => $currentPage, 'lastPage' => $lastPage, 'perPage' => $perPage, 'total' => $total, 'pageName' => $pageName],
        ];
    }
}
