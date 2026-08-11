<?php

namespace App\Actions;

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
        $post = DB::transaction(function () use ($discussion, $author, $body): KnowledgePost {
            $lockedDiscussion = KnowledgeDiscussion::query()->lockForUpdate()->findOrFail($discussion->id);
            abort_unless($lockedDiscussion->status === 'open', 409, 'This discussion is closed.');
            $post = KnowledgePost::create(['knowledge_discussion_id' => $lockedDiscussion->id, 'author_id' => $author->id, 'body' => $body, 'is_moderated' => false, 'moderation_status' => 'visible', 'posted_at' => now()]);
            $lockedDiscussion->update(['last_posted_at' => now()]);
            $this->auditLogger->record($author, $post, 'knowledge.discussion.posted', "Contribution posted to {$lockedDiscussion->title}.", $lockedDiscussion->county_id);

            return $post;
        });

        $discussion->subscriptions()->with('user.currentTeam')->where('user_id', '!=', $author->id)->each(function (KnowledgeDiscussionSubscription $subscription) use ($discussion): void {
            $teamSlug = $subscription->user->currentTeam?->slug;
            $subscription->user->notify(new ProgrammeAlert('New knowledge discussion contribution', "A new contribution was posted to {$discussion->title}.", 'knowledge', is_string($teamSlug) ? route('knowledge.index', $teamSlug) : null));
        });

        return $post;
    }
}
