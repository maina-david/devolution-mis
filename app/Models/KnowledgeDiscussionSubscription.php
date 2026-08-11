<?php

namespace App\Models;

use Database\Factories\KnowledgeDiscussionSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** @property Carbon|null $subscribed_at */
#[Fillable(['knowledge_discussion_id', 'user_id', 'delivery_frequency', 'subscribed_at'])]
class KnowledgeDiscussionSubscription extends Model
{
    /** @use HasFactory<KnowledgeDiscussionSubscriptionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['delivery_frequency' => 'instant'];

    protected function casts(): array
    {
        return ['subscribed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<KnowledgeDiscussion, $this> */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDiscussion::class, 'knowledge_discussion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
