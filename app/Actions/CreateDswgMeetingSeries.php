<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\DswgMeetingSeries;
use App\Models\DswgWorkingGroup;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateDswgMeetingSeries
{
    public function __construct(private GenerateDswgRecurringMeetings $generateMeetings, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DswgWorkingGroup $group, User $actor, array $attributes): DswgMeetingSeries
    {
        abort_unless($actor->can(ProgrammePermission::ManageDswg->value), 403, __('dswg.meeting_series_create_unauthorized'));

        $inviteeIds = $this->inviteeIds($attributes);
        abort_unless($group->members()->whereIn('users.id', $inviteeIds)->count() === $inviteeIds->count(), 422, __('dswg.recurring_invitee_member_required'));
        abort_if((int) $attributes['quorum_required'] > $inviteeIds->count(), 422, __('dswg.recurring_quorum_exceeded'));

        $series = DB::transaction(function () use ($group, $actor, $attributes, $inviteeIds): DswgMeetingSeries {
            $series = $group->meetingSeries()->create([
                ...Arr::except($attributes, ['first_starts_at', 'invitee_ids']),
                'next_occurrence_at' => CarbonImmutable::parse((string) $attributes['first_starts_at'], (string) $attributes['timezone'])->utc(),
                'created_by' => $actor->id,
            ]);
            $series->invitees()->sync($inviteeIds->all());

            return $series;
        });

        $this->auditLogger->record($actor, $series, 'dswg.meeting_series.created', __('dswg.audit_meeting_series_created', ['reference' => $series->reference_prefix]), metadata: ['frequency' => $series->frequency, 'invitees' => $inviteeIds->all()]);
        $this->generateMeetings->handle($series, $actor);

        return $series->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, string>
     */
    private function inviteeIds(array $attributes): Collection
    {
        return collect(Arr::wrap(Arr::get($attributes, 'invitee_ids')))
            ->filter(fn (mixed $inviteeId): bool => is_string($inviteeId))
            ->values();
    }
}
