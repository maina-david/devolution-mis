<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeDiscussionSubscription;
use App\Models\KnowledgePost;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateKnowledgePost
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(KnowledgeDiscussion $discussion, User $author, string $body): KnowledgePost
    {
        abort_unless($author->can(ProgrammePermission::ContributeKnowledge->value), 403, __('knowledge.errors.post_create_unauthorized'));

        $post = DB::transaction(function () use ($discussion, $author, $body): KnowledgePost {
            $lockedDiscussion = KnowledgeDiscussion::query()->with('county')->lockForUpdate()->findOrFail($discussion->id);
            abort_unless($lockedDiscussion->county_id === null || ($lockedDiscussion->county !== null && $author->canAccessCounty($lockedDiscussion->county)), 403, __('knowledge.errors.community_county_outside_scope'));
            abort_unless($lockedDiscussion->status === 'open', 409, __('knowledge.errors.discussion_closed'));
            $post = KnowledgePost::create(['knowledge_discussion_id' => $lockedDiscussion->id, 'author_id' => $author->id, 'body' => $body, 'is_moderated' => false, 'moderation_status' => 'visible', 'posted_at' => now()]);
            $lockedDiscussion->update(['last_posted_at' => now()]);
            $this->auditLogger->record($author, $post, 'knowledge.discussion.posted', __('knowledge.audit.post_created', ['title' => $lockedDiscussion->title]), $lockedDiscussion->county_id);

            return $post;
        });

        $discussion->subscriptions()->with('user')->where('user_id', '!=', $author->id)->each(function (KnowledgeDiscussionSubscription $subscription) use ($discussion): void {
            $subscription->user->notify(ProgrammeAlert::translated('knowledge.notifications.new_contribution_title', 'knowledge.notifications.new_contribution_message', 'knowledge', messageParameters: ['title' => $discussion->title], url: route('knowledge.index')));
        });

        return $post;
    }
}
