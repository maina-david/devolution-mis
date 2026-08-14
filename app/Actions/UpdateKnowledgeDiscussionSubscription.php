<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
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
        abort_unless($user->can(ProgrammePermission::ViewKnowledge->value), 403, __('knowledge.errors.discussion_subscription_unauthorized'));

        return DB::transaction(function () use ($discussion, $user, $subscribed): ?KnowledgeDiscussionSubscription {
            $lockedDiscussion = KnowledgeDiscussion::query()->with('county')->lockForUpdate()->findOrFail($discussion->id);
            abort_unless($lockedDiscussion->county_id === null || ($lockedDiscussion->county !== null && $user->canAccessCounty($lockedDiscussion->county)), 403, __('knowledge.errors.community_county_outside_scope'));
            abort_unless($lockedDiscussion->status === 'open', 409, __('knowledge.errors.discussion_follow_open_only'));

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

            $this->auditLogger->record($user, $lockedDiscussion, $subscribed ? 'knowledge.discussion.subscribed' : 'knowledge.discussion.unsubscribed', __($subscribed ? 'knowledge.audit.discussion_subscribed' : 'knowledge.audit.discussion_unsubscribed', ['title' => $lockedDiscussion->title]), $lockedDiscussion->county_id);

            return $subscribed ? $subscription : null;
        });
    }
}
