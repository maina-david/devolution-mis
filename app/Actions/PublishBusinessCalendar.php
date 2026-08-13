<?php

namespace App\Actions;

use App\Models\BusinessCalendar;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class PublishBusinessCalendar
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(BusinessCalendar $businessCalendar, User $publisher): BusinessCalendar
    {
        return DB::transaction(function () use ($businessCalendar, $publisher): BusinessCalendar {
            $calendar = BusinessCalendar::query()->with('holidays')->lockForUpdate()->whereKey($businessCalendar)->sole();
            abort_unless($calendar->status === 'draft', 409, __('workflow-management.calendar.errors.draft_required'));
            abort_if($calendar->created_by === $publisher->id, 403, __('workflow-management.calendar.errors.independent_publisher'));
            $overlap = BusinessCalendar::query()->where('code', $calendar->code)->where('status', 'published')->whereKeyNot($calendar)->whereDate('effective_from', '<=', $calendar->effective_to ?? '9999-12-31')->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>', $calendar->effective_from))->exists();
            abort_if($overlap, 409, __('workflow-management.calendar.errors.overlapping_versions'));
            $payload = ['id' => $calendar->id, 'code' => $calendar->code, 'version' => $calendar->version, 'timezone' => $calendar->timezone, 'working_days' => $calendar->working_days, 'workday_starts_at' => $calendar->workday_starts_at, 'workday_ends_at' => $calendar->workday_ends_at, 'effective_from' => $calendar->effective_from->toDateString(), 'effective_to' => $calendar->effective_to?->toDateString(), 'holidays' => $calendar->holidays->map(fn ($holiday): array => ['date' => $holiday->holiday_date->toDateString(), 'name' => $holiday->name, 'category' => $holiday->category, 'source_reference' => $holiday->source_reference])->values()->all()];
            $calendar->update(['status' => 'published', 'published_by' => $publisher->id, 'published_at' => now(), 'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))]);
            $this->auditLogger->record($publisher, $calendar, 'workflow.business-calendar.published', __('workflow-management.calendar.audit.published', ['code' => $calendar->code, 'version' => $calendar->version]), metadata: ['checksum' => $calendar->checksum]);

            return $calendar->refresh();
        }, attempts: 3);
    }
}
