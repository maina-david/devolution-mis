<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\KnowledgePost;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ModerateKnowledgePost
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(KnowledgePost $post, User $moderator, string $moderationStatus, string $moderationReason): KnowledgePost
    {
        abort_unless($moderator->canAny([ProgrammePermission::CurateKnowledge->value, ProgrammePermission::ManageKnowledge->value]), 403, __('knowledge.errors.post_moderate_unauthorized'));

        return DB::transaction(function () use ($post, $moderator, $moderationStatus, $moderationReason): KnowledgePost {
            $lockedPost = KnowledgePost::query()->with('discussion.county')->lockForUpdate()->findOrFail($post->id);
            abort_unless($lockedPost->discussion->county_id === null || ($lockedPost->discussion->county !== null && $moderator->canAccessCounty($lockedPost->discussion->county)), 403, __('knowledge.errors.community_county_outside_scope'));
            abort_if($lockedPost->author_id === $moderator->id, 403, __('knowledge.errors.post_moderate_own'));
            abort_if($lockedPost->moderation_status === $moderationStatus, 409, __('knowledge.errors.post_moderate_same_status'));

            $beforeStatus = $lockedPost->moderation_status;
            $lockedPost->forceFill(['is_moderated' => true, 'moderation_status' => $moderationStatus, 'moderated_by' => $moderator->id, 'moderated_at' => now(), 'moderation_reason' => $moderationReason])->save();
            $this->auditLogger->record($moderator, $lockedPost, 'knowledge.post.moderated', __('knowledge.audit.post_moderated', ['from' => $beforeStatus, 'to' => $moderationStatus, 'reason' => $moderationReason]), $lockedPost->discussion->county_id);

            return $lockedPost;
        });
    }
}
