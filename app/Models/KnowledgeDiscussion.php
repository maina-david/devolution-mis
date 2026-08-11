<?php

namespace App\Models;

use Database\Factories\KnowledgeDiscussionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** @property Carbon|null $last_posted_at */
#[Fillable(['knowledge_item_id', 'county_id', 'created_by', 'title', 'prompt', 'status', 'visibility', 'last_posted_at'])]
class KnowledgeDiscussion extends Model
{
    /** @use HasFactory<KnowledgeDiscussionFactory> */
    use HasFactory,HasUuids,SoftDeletes;

    protected function casts(): array
    {
        return ['last_posted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<KnowledgeItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class, 'knowledge_item_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<KnowledgePost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(KnowledgePost::class);
    }

    /** @return HasMany<KnowledgeDiscussionSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(KnowledgeDiscussionSubscription::class);
    }
}
