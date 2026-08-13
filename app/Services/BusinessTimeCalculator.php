<?php

namespace App\Services;

use App\Models\BusinessCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RuntimeException;

class BusinessTimeCalculator
{
    public function addHours(BusinessCalendar $calendar, CarbonInterface $enteredAt, float $hours): CarbonImmutable
    {
        abort_unless($calendar->status === 'published', 409, __('workflow-management.calendar.errors.sla_calendar_published'));
        abort_unless($hours > 0, 422, __('workflow-management.calendar.errors.positive_sla_hours'));
        $calendar->loadMissing('holidays:id,business_calendar_id,holiday_date');
        $holidayDates = $calendar->holidays->pluck('holiday_date')->map->toDateString()->flip();
        $remainingSeconds = (int) round($hours * 3600);
        $cursor = CarbonImmutable::instance($enteredAt)->setTimezone($calendar->timezone);

        for ($daysInspected = 0; $daysInspected < 4000; $daysInspected++) {
            $date = $cursor->startOfDay();
            if (in_array($date->isoWeekday(), $calendar->working_days, true) && ! $holidayDates->has($date->toDateString())) {
                $startsAt = CarbonImmutable::parse($date->toDateString().' '.$calendar->workday_starts_at, $calendar->timezone);
                $endsAt = CarbonImmutable::parse($date->toDateString().' '.$calendar->workday_ends_at, $calendar->timezone);
                $segmentStartsAt = $cursor->greaterThan($startsAt) ? $cursor : $startsAt;

                if ($segmentStartsAt->lessThan($endsAt)) {
                    $availableSeconds = (int) $segmentStartsAt->diffInSeconds($endsAt);
                    if ($remainingSeconds <= $availableSeconds) {
                        return $segmentStartsAt->addSeconds($remainingSeconds)->utc();
                    }
                    $remainingSeconds -= $availableSeconds;
                }
            }

            $cursor = $date->addDay();
        }

        throw new RuntimeException(__('workflow-management.calendar.errors.planning_horizon_exceeded'));
    }
}
