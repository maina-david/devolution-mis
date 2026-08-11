<?php

namespace App\Services;

use App\Models\County;
use App\Models\IgrResolutionGap;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IgrGapAnalytics
{
    /**
     * @param  Builder<IgrResolutionGap>  $query
     * @return array{
     *   summary: array{total:int,open:int,mitigating:int,awaitingAcceptance:int,overdue:int,critical:int,affectedResolutions:int,activeAffectedResolutions:int,averageResolutionDays:float|null},
     *   categories: list<array{name:string,total:int}>,
     *   severities: list<array{name:string,total:int}>,
     *   aging: list<array{name:string,total:int}>,
     *   trend: list<array{period:string,label:string,reported:int,accepted:int}>,
     *   counties: list<array{county:array<string,mixed>|null,total:int,active:int,overdue:int}>
     * }
     */
    public function report(Builder $query): array
    {
        $summary = (clone $query)->toBase()->selectRaw(<<<'SQL'
            count(*) as total,
            count(*) filter (where status = 'open') as open,
            count(*) filter (where status = 'mitigating') as mitigating,
            count(*) filter (where status = 'resolved') as awaiting_acceptance,
            count(*) filter (where status != 'accepted' and due_on < current_date) as overdue,
            count(*) filter (where severity = 'critical' and status != 'accepted') as critical,
            count(distinct igr_resolution_id) as affected_resolutions,
            count(distinct igr_resolution_id) filter (where status != 'accepted') as active_affected_resolutions,
            avg(extract(epoch from (accepted_at - created_at)) / 86400.0) filter (where status = 'accepted' and accepted_at is not null) as average_resolution_days
        SQL)->first();

        $categories = (clone $query)
            ->join('igr_gap_categories', 'igr_gap_categories.id', '=', 'igr_resolution_gaps.igr_gap_category_id')
            ->selectRaw('igr_gap_categories.name, count(*) as total')
            ->groupBy('igr_gap_categories.id', 'igr_gap_categories.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn (IgrResolutionGap $row): array => ['name' => (string) $row->getAttribute('name'), 'total' => (int) $row->getAttribute('total')])
            ->values()
            ->all();

        $severities = (clone $query)->toBase()
            ->selectRaw('severity as name, count(*) as total')
            ->groupBy('severity')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => ['name' => (string) $row->name, 'total' => (int) $row->total])
            ->values()
            ->all();

        $aging = (clone $query)->toBase()->selectRaw(<<<'SQL'
            count(*) filter (where status != 'accepted' and created_at >= current_date - interval '7 days') as up_to_7,
            count(*) filter (where status != 'accepted' and created_at < current_date - interval '7 days' and created_at >= current_date - interval '30 days') as days_8_30,
            count(*) filter (where status != 'accepted' and created_at < current_date - interval '30 days' and created_at >= current_date - interval '90 days') as days_31_90,
            count(*) filter (where status != 'accepted' and created_at < current_date - interval '90 days') as over_90
        SQL)->first();

        $countyRows = (clone $query)->toBase()
            ->whereNotNull('county_id')
            ->selectRaw("county_id, count(*) as total, count(*) filter (where status != 'accepted') as active, count(*) filter (where status != 'accepted' and due_on < current_date) as overdue")
            ->groupBy('county_id')
            ->orderByDesc('active')
            ->orderByDesc('overdue')
            ->limit(10)
            ->get();
        $counties = County::query()->whereKey($countyRows->pluck('county_id'))->get()->keyBy('id');

        return [
            'summary' => [
                'total' => (int) $summary->total,
                'open' => (int) $summary->open,
                'mitigating' => (int) $summary->mitigating,
                'awaitingAcceptance' => (int) $summary->awaiting_acceptance,
                'overdue' => (int) $summary->overdue,
                'critical' => (int) $summary->critical,
                'affectedResolutions' => (int) $summary->affected_resolutions,
                'activeAffectedResolutions' => (int) $summary->active_affected_resolutions,
                'averageResolutionDays' => $summary->average_resolution_days === null ? null : round((float) $summary->average_resolution_days, 1),
            ],
            'categories' => array_values($categories),
            'severities' => array_values($severities),
            'aging' => [
                ['name' => 'Up to 7 days', 'total' => (int) $aging->up_to_7],
                ['name' => '8–30 days', 'total' => (int) $aging->days_8_30],
                ['name' => '31–90 days', 'total' => (int) $aging->days_31_90],
                ['name' => 'Over 90 days', 'total' => (int) $aging->over_90],
            ],
            'trend' => $this->trend($query),
            'counties' => array_values($countyRows->map(function (object $row) use ($counties): array {
                $county = $counties->get($row->county_id);

                return ['county' => $county?->identityCell(), 'total' => (int) $row->total, 'active' => (int) $row->active, 'overdue' => (int) $row->overdue];
            })->values()->all()),
        ];
    }

    /**
     * @param  Builder<IgrResolutionGap>  $query
     * @return list<array{period:string,label:string,reported:int,accepted:int}>
     */
    private function trend(Builder $query): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(5);
        $reported = $this->monthlyCounts($query, 'created_at', $start);
        $accepted = $this->monthlyCounts($query, 'accepted_at', $start);

        return array_values(collect(range(0, 5))->map(function (int $offset) use ($start, $reported, $accepted): array {
            $month = $start->addMonths($offset);
            $period = $month->format('Y-m');

            return ['period' => $period, 'label' => $month->format('M Y'), 'reported' => $reported->get($period, 0), 'accepted' => $accepted->get($period, 0)];
        })->all());
    }

    /**
     * @param  Builder<IgrResolutionGap>  $query
     * @param  'created_at'|'accepted_at'  $column
     * @return Collection<string, int>
     */
    private function monthlyCounts(Builder $query, string $column, CarbonImmutable $start): Collection
    {
        $monthlyQuery = (clone $query)->toBase()
            ->whereNotNull($column)
            ->where($column, '>=', $start);

        $monthlyQuery = $column === 'accepted_at'
            ? $monthlyQuery->selectRaw("to_char(date_trunc('month', accepted_at), 'YYYY-MM') as period, count(*) as total")->groupByRaw("date_trunc('month', accepted_at)")
            : $monthlyQuery->selectRaw("to_char(date_trunc('month', created_at), 'YYYY-MM') as period, count(*) as total")->groupByRaw("date_trunc('month', created_at)");

        return $monthlyQuery
            ->pluck('total', 'period')
            ->map(fn (mixed $total): int => (int) $total);
    }
}
