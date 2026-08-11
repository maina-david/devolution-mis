<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IgrForum;
use App\Models\IgrForumMeeting;
use App\Models\User;
use App\Services\AuditLogger;

class CreateIgrForumMeeting
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): IgrForumMeeting
    {
        abort_unless($actor->can(ProgrammePermission::ManageIgrResolutions->value), 403);
        $forum = IgrForum::query()->whereKey($attributes['igr_forum_id'])->where('status', 'active')->firstOrFail();
        $meeting = $forum->meetings()->create([...$attributes, 'created_by' => $actor->id]);
        $this->auditLogger->record($actor, $meeting, 'igr.forum.meeting_recorded', "Formal meeting {$meeting->reference} recorded for {$forum->code}.");

        return $meeting;
    }
}
