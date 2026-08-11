<?php

namespace App\Actions;

use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeDiscussionSubscription;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class UpdateKnowledgeDiscussionSubscription
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(KnowledgeDiscussion $discussion, User $user, bool $subscribed): ?KnowledgeDiscussionSubscription
    {
        return DB::transaction(function () use ($discussion, $user, $subscribed): ?KnowledgeDiscussionSubscription {
            $lockedDiscussion = KnowledgeDiscussion::query()->lockForUpdate()->findOrFail($discussion->id);
            abort_unless($lockedDiscussion->status === 'open', 409, 'Only open discussions can be followed.');

            $subscription = KnowledgeDiscussionSubscription::withTrashed()
                ->where('knowledge_discussion_id', $lockedDiscussion->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($subscribed) {
                if ($subscription === null) {
                    $subscription = KnowledgeDiscussionSubscription::create(['knowledge_discussion_id' => $lockedDiscussion->id, 'user_id' => $user->id, 'delivery_frequency' => 'instant', 'subscribed_at' => now()]);
                } elseif ($subscription->trashed()) {
                    $subscription->restore();
                    $subscription->forceFill(['subscribed_at' => now()])->save();
                }
            } elseif ($subscription !== null && ! $subscription->trashed()) {
                $subscription->delete();
            }

            $this->auditLogger->record($user, $lockedDiscussion, $subscribed ? 'knowledge.discussion.subscribed' : 'knowledge.discussion.unsubscribed', $subscribed ? "Subscribed to {$lockedDiscussion->title}." : "Unsubscribed from {$lockedDiscussion->title}.", $lockedDiscussion->county_id);

            return $subscribed ? $subscription : null;
        });
    }
}
