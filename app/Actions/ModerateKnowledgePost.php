<?php

namespace App\Actions;

use App\Models\KnowledgePost;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ModerateKnowledgePost
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(KnowledgePost $post, User $moderator, string $moderationStatus, string $moderationReason): KnowledgePost
    {
        return DB::transaction(function () use ($post, $moderator, $moderationStatus, $moderationReason): KnowledgePost {
            $lockedPost = KnowledgePost::query()->with('discussion')->lockForUpdate()->findOrFail($post->id);
            abort_if($lockedPost->author_id === $moderator->id, 403, 'Authors cannot moderate their own contributions.');
            abort_if($lockedPost->moderation_status === $moderationStatus, 409, 'The contribution already has that moderation status.');

            $beforeStatus = $lockedPost->moderation_status;
            $lockedPost->forceFill(['is_moderated' => true, 'moderation_status' => $moderationStatus, 'moderated_by' => $moderator->id, 'moderated_at' => now(), 'moderation_reason' => $moderationReason])->save();
            $this->auditLogger->record($moderator, $lockedPost, 'knowledge.post.moderated', "Contribution moderation changed from {$beforeStatus} to {$moderationStatus}. Reason: {$moderationReason}", $lockedPost->discussion->county_id);

            return $lockedPost;
        });
    }
}
