<?php

namespace Database\Seeders;

use App\Actions\CreateDswgMeetingSeries;
use App\Models\DswgMeetingSeries;
use App\Models\DswgWorkingGroup;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

class DswgMeetingSeriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CreateDswgMeetingSeries $createSeries): void
    {
        $existingSeries = DswgMeetingSeries::query()->where('reference_prefix', 'DSWG-WASH-DELIVERY')->first();
        if ($existingSeries !== null) {
            $this->reconcileNairobiWallClock($existingSeries);

            return;
        }

        $group = DswgWorkingGroup::query()
            ->where('code', 'DSWG-WASH-01')
            ->with(['secretariat', 'members'])
            ->first();

        if ($group === null || $group->members->isEmpty()) {
            return;
        }

        $businessTimezone = (string) config('app.business_timezone');
        $firstMeeting = now($businessTimezone)->addMonth()->startOfMonth()->next(CarbonInterface::TUESDAY)->setTime(10, 0);
        $inviteeIds = $group->members->pluck('id')->values()->all();

        $createSeries->handle($group, $group->secretariat, [
            'reference_prefix' => 'DSWG-WASH-DELIVERY',
            'title' => 'Quarterly water, sanitation and climate resilience delivery review',
            'frequency' => 'quarterly',
            'interval' => 1,
            'first_starts_at' => $firstMeeting->toIso8601String(),
            'ends_on' => $firstMeeting->copy()->addYear()->toDateString(),
            'duration_minutes' => 150,
            'timezone' => $businessTimezone,
            'meeting_mode' => 'hybrid',
            'venue' => 'State Department for Devolution coordination room',
            'virtual_link' => 'https://meet.example.org/dswg/wash-delivery',
            'agenda' => 'Review county delivery progress, financing and implementation exceptions, adopt decisions, and assign evidence-backed accountable actions.',
            'quorum_required' => min(2, count($inviteeIds)),
            'generation_horizon_days' => 120,
            'invitee_ids' => $inviteeIds,
        ]);
    }

    private function reconcileNairobiWallClock(DswgMeetingSeries $series): void
    {
        if ($series->next_occurrence_at->setTimezone($series->timezone)->hour !== 13) {
            return;
        }

        $untouchedMeetings = $series->meetings()
            ->where('status', 'scheduled')
            ->whereDoesntHave('decisions')
            ->whereDoesntHave('actions')
            ->get();

        if ($untouchedMeetings->count() !== $series->meetings()->count()) {
            return;
        }

        $untouchedMeetings->each(fn ($meeting) => $meeting->update([
            'starts_at' => $meeting->starts_at->subHours(3),
            'ends_at' => $meeting->ends_at->subHours(3),
        ]));
        $series->update(['next_occurrence_at' => $series->next_occurrence_at->subHours(3)]);
    }
}
