<?php

namespace App\Actions;

use App\Models\DswgMeetingSeries;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GenerateDswgRecurringMeetings
{
    public function __construct(private StartWorkflow $startWorkflow, private AuditLogger $auditLogger) {}

    public function handle(DswgMeetingSeries $series, User $actor): int
    {
        $generated = [];

        DB::transaction(function () use ($series, $actor, &$generated): void {
            $lockedSeries = DswgMeetingSeries::query()->whereKey($series)->lockForUpdate()->firstOrFail();
            if ($lockedSeries->status !== 'active') {
                return;
            }

            $definition = WorkflowDefinition::query()->where('code', 'DSWG-MEETING-LIFECYCLE')->firstOrFail();
            $horizon = now()->addDays($lockedSeries->generation_horizon_days);
            $seriesEnd = $lockedSeries->ends_on->endOfDay();

            while ($lockedSeries->next_occurrence_at->lessThanOrEqualTo($horizon) && $lockedSeries->next_occurrence_at->lessThanOrEqualTo($seriesEnd)) {
                $startsAt = $lockedSeries->next_occurrence_at;
                $sequence = $lockedSeries->next_sequence;
                $meeting = $lockedSeries->meetings()->create([
                    'dswg_working_group_id' => $lockedSeries->dswg_working_group_id,
                    'occurrence_sequence' => $sequence,
                    'reference' => sprintf('%s-%03d', $lockedSeries->reference_prefix, $sequence),
                    'title' => $lockedSeries->title,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->addMinutes($lockedSeries->duration_minutes),
                    'meeting_mode' => $lockedSeries->meeting_mode,
                    'venue' => $lockedSeries->venue,
                    'virtual_link' => $lockedSeries->virtual_link,
                    'agenda' => $lockedSeries->agenda,
                    'quorum_required' => $lockedSeries->quorum_required,
                    'organized_by' => $lockedSeries->created_by,
                ]);
                $instance = $this->startWorkflow->handle($definition, $meeting, $actor, ['minutes_present' => false, 'quorum_met' => false]);
                $meeting->update(['workflow_instance_id' => $instance->id, 'status' => $instance->current_state]);
                $pivot = $lockedSeries->invitees()->pluck('users.id')->mapWithKeys(fn (string $id): array => [$id => ['invitation_status' => 'pending', 'attendance_status' => 'not_recorded', 'meeting_role' => 'participant', 'invited_at' => now()]])->all();
                $meeting->invitees()->sync($pivot);
                $generated[] = $meeting->load('invitees');
                $lockedSeries->next_occurrence_at = $this->nextOccurrence($startsAt, $lockedSeries->frequency, $lockedSeries->interval);
                $lockedSeries->next_sequence = $sequence + 1;
            }

            if ($lockedSeries->next_occurrence_at->greaterThan($seriesEnd)) {
                $lockedSeries->status = 'completed';
            }
            $lockedSeries->save();
        }, 3);

        foreach ($generated as $meeting) {
            $meeting->invitees->each(fn (User $invitee) => $invitee->notify(new ProgrammeAlert('DSWG recurring meeting invitation', "You are invited to {$meeting->title} on {$meeting->starts_at->toDayDateTimeString()}.", 'dswg')));
            $this->auditLogger->record($actor, $meeting, 'dswg.meeting.generated', "Recurring DSWG meeting {$meeting->reference} generated.", metadata: ['series_id' => $series->id, 'occurrence_sequence' => $meeting->occurrence_sequence]);
        }

        return count($generated);
    }

    private function nextOccurrence(CarbonImmutable $current, string $frequency, int $interval): CarbonImmutable
    {
        return match ($frequency) {
            'weekly' => $current->addWeeks($interval),
            'monthly' => $current->addMonthsNoOverflow($interval),
            'quarterly' => $current->addMonthsNoOverflow($interval * 3),
            default => throw new \LogicException("Unsupported DSWG recurrence frequency: {$frequency}"),
        };
    }
}
