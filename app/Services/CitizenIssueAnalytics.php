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

        $rated = (clone $query)->whereNotNull('satisfaction_rating');
        $ratingCount = (clone $rated)->count();
        $resolvedCount = (clone $query)->whereIn('status', ['resolved', 'closed'])->count();
        $satisfactionVisible = $ratingCount >= $minimum;
        $satisfactionGroups = function (string $column) use ($rated, $minimum, $satisfactionVisible): array {
            if (! $satisfactionVisible) {
                return [];
            }

            $expression = match ($column) {
                'category' => 'category as label, count(*) as responses, round(avg(satisfaction_rating)::numeric, 2) as average_rating',
                'channel' => 'channel as label, count(*) as responses, round(avg(satisfaction_rating)::numeric, 2) as average_rating',
                default => throw new \InvalidArgumentException('Unsupported satisfaction analytics dimension.'),
            };

            return (clone $rated)
                ->selectRaw($expression)
                ->whereNotNull($column)
                ->groupBy($column)
                ->havingRaw('count(*) >= ?', [$minimum])
                ->orderByDesc('responses')
                ->limit(10)
                ->get()
                ->map(fn (Model $case): array => [
                    'label' => (string) $case->getAttribute('label'),
                    'responses' => (int) $case->getAttribute('responses'),
                    'averageRating' => (float) $case->getAttribute('average_rating'),
                ])->all();
        };
        $correlationSamples = 0;
        $correlationCoefficient = null;
        if ($satisfactionVisible) {
            $correlation = (clone $rated)
                ->whereNotNull('resolved_at')
                ->selectRaw('count(*) as samples, corr(satisfaction_rating::numeric, extract(epoch from (resolved_at - created_at)) / 3600) as coefficient')
                ->first();
            $correlationSamples = (int) $correlation->getAttribute('samples');
            if ($correlationSamples >= $minimum && $correlation->getAttribute('coefficient') !== null) {
                $correlationCoefficient = round((float) $correlation->getAttribute('coefficient'), 3);
            }
        }

        return [
            'categories' => $groups('category'),
            'channels' => $groups('channel'),
            'monthlyTrend' => $monthly,
            'overdue' => (clone $query)->whereNotIn('status', ['resolved', 'closed'])->where('resolution_due_at', '<', now())->count(),
            'averageResolutionHours' => round((float) ((clone $query)->whereNotNull('resolved_at')->selectRaw('avg(extract(epoch from (resolved_at - created_at)) / 3600) as aggregate')->value('aggregate') ?? 0), 1),
            'minimumPublishedCount' => $minimum,
            'satisfaction' => [
                'responses' => $satisfactionVisible ? $ratingCount : null,
                'responseRate' => $satisfactionVisible && $resolvedCount > 0 ? round(($ratingCount / $resolvedCount) * 100, 1) : null,
                'averageRating' => $satisfactionVisible ? round((float) ((clone $rated)->avg('satisfaction_rating') ?? 0), 2) : null,
                'distribution' => $satisfactionVisible
                    ? (clone $rated)->selectRaw('satisfaction_rating as rating, count(*) as total')->groupBy('satisfaction_rating')->havingRaw('count(*) >= ?', [$minimum])->orderBy('satisfaction_rating')->get()->map(fn (Model $case): array => ['rating' => (int) $case->getAttribute('rating'), 'total' => (int) $case->getAttribute('total')])->all()
                    : [],
                'byCategory' => $satisfactionGroups('category'),
                'byChannel' => $satisfactionGroups('channel'),
                'resolutionTimeCorrelation' => [
                    'samples' => $correlationCoefficient === null ? null : $correlationSamples,
                    'coefficient' => $correlationCoefficient,
                ],
            ],
        ];
    }
}
