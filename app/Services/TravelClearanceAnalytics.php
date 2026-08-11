<?php

namespace App\Services;

use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Builder;

class TravelClearanceAnalytics
{
    /**
     * @param  Builder<TravelRequest>  $visibleRequests
     * @return array<string, mixed>
     */
    public function summarize(Builder $visibleRequests): array
    {
        $summary = $visibleRequests->clone()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("count(case when status = 'approved' then 1 end) as approved")
            ->selectRaw("count(case when status = 'rejected' then 1 end) as rejected")
            ->selectRaw('avg(case when submitted_at is not null and decided_at is not null then extract(epoch from (decided_at - submitted_at)) / 3600 end) as average_turnaround_hours')
            ->first();

        $costs = $visibleRequests->clone()->toBase()->select(['currency'])
            ->selectRaw('count(*) as requests')
            ->selectRaw('sum(estimated_cost) as total_estimated_cost')
            ->selectRaw('avg(estimated_cost) as average_estimated_cost')
            ->groupBy('currency')->orderByDesc('total_estimated_cost')->get()
            ->map(fn (object $row): array => ['id' => (string) $row->currency, 'currency' => (string) $row->currency, 'requests' => (int) $row->requests, 'totalCost' => round((float) $row->total_estimated_cost, 2), 'averageCost' => round((float) $row->average_estimated_cost, 2)])->values();

        $destinations = $visibleRequests->clone()->toBase()->select(['destination_city', 'destination_country', 'currency'])
            ->selectRaw('count(*) as requests')->selectRaw('sum(estimated_cost) as total_estimated_cost')
            ->groupBy('destination_city', 'destination_country', 'currency')->orderByDesc('requests')->orderByDesc('total_estimated_cost')->limit(20)->get()
            ->map(fn (object $row): array => ['id' => sha1("{$row->destination_city}|{$row->destination_country}|{$row->currency}"), 'destination' => "{$row->destination_city}, {$row->destination_country}", 'currency' => (string) $row->currency, 'requests' => (int) $row->requests, 'totalCost' => round((float) $row->total_estimated_cost, 2)])->values();

        $statuses = $visibleRequests->clone()->toBase()->select(['status'])->selectRaw('count(*) as requests')->groupBy('status')->orderByDesc('requests')->get()
            ->map(fn (object $row): array => ['id' => (string) $row->status, 'status' => (string) $row->status, 'requests' => (int) $row->requests])->values();

        return [
            'summary' => ['total' => (int) ($summary->total ?? 0), 'approved' => (int) ($summary->approved ?? 0), 'rejected' => (int) ($summary->rejected ?? 0), 'averageTurnaroundHours' => $summary?->average_turnaround_hours === null ? null : round((float) $summary->average_turnaround_hours, 1)],
            'costs' => $costs,
            'destinations' => $destinations,
            'statuses' => $statuses,
        ];
    }
}
