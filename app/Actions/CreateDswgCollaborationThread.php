<?php

namespace App\Actions;

use App\Models\DswgCollaborationThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateDswgCollaborationThread
{
    /** @param array{title: string, topic: string} $attributes */
    public function handle(string $workingGroupId, User $actor, array $attributes): DswgCollaborationThread
    {
        return DB::transaction(function () use ($workingGroupId, $actor, $attributes): DswgCollaborationThread {
            $postedAt = now();
            $thread = DswgCollaborationThread::query()->create([
                'dswg_working_group_id' => $workingGroupId,
                'created_by' => $actor->id,
                'title' => $attributes['title'],
                'topic' => $attributes['topic'],
                'last_activity_at' => $postedAt,
            ]);
            $thread->messages()->create([
                'author_id' => $actor->id,
                'body' => $attributes['topic'],
                'posted_at' => $postedAt,
                'checksum' => $this->checksum($thread->id, $actor->id, $attributes['topic'], $postedAt->toIso8601String()),
            ]);

            return $thread;
        });
    }

    public function checksum(string $threadId, string $authorId, string $body, string $postedAt): string
    {
        return hash('sha256', json_encode([$threadId, $authorId, $body, $postedAt], JSON_THROW_ON_ERROR));
    }
}
