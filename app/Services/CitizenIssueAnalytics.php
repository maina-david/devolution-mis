<?php

namespace App\Services;

use App\Models\CitizenCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CitizenIssueAnalytics
{
    /**
     * @param  Builder<CitizenCase>|null  $scope
     * @return array<string, mixed>
     */
    public function report(?Builder $scope = null, bool $public = false): array
    {
        $query = $scope ?? CitizenCase::query();
        $minimum = $public ? 3 : 1;

        $groups = function (string $column) use ($query, $minimum): array {
            $expression = match ($column) {
                'category' => 'category as label, count(*) as total',
                'channel' => 'channel as label, count(*) as total',
                default => throw new \InvalidArgumentException('Unsupported citizen analytics dimension.'),
            };

            return (clone $query)
                ->selectRaw($expression)
                ->whereNotNull($column)
                ->groupBy($column)
                ->havingRaw('count(*) >= ?', [$minimum])
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn (Model $case): array => ['label' => (string) $case->getAttribute('label'), 'total' => (int) $case->getAttribute('total')])
                ->all();
        };

        $monthly = (clone $query)
            ->where('created_at', '>=', now()->startOfMonth()->subMonths(11))
            ->selectRaw("to_char(date_trunc('month', created_at), 'YYYY-MM') as month, count(*) as total, count(*) filter (where status in ('resolved', 'closed')) as resolved")
            ->groupByRaw("date_trunc('month', created_at)")
            ->orderByRaw("date_trunc('month', created_at)")
            ->get()
            ->map(fn (Model $case): array => ['month' => (string) $case->getAttribute('month'), 'total' => (int) $case->getAttribute('total'), 'resolved' => (int) $case->getAttribute('resolved')])
            ->all();

        return [
            'categories' => $groups('category'),
            'channels' => $groups('channel'),
            'monthlyTrend' => $monthly,
            'overdue' => (clone $query)->whereNotIn('status', ['resolved', 'closed'])->where('resolution_due_at', '<', now())->count(),
            'averageResolutionHours' => round((float) ((clone $query)->whereNotNull('resolved_at')->selectRaw('avg(extract(epoch from (resolved_at - created_at)) / 3600) as aggregate')->value('aggregate') ?? 0), 1),
            'minimumPublishedCount' => $minimum,
        ];
    }
}
