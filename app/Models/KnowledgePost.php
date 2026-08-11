<?php

namespace App\Models;

use Database\Factories\KnowledgePostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $posted_at
 * @property Carbon|null $moderated_at
 */
#[Fillable(['knowledge_discussion_id', 'author_id', 'body', 'is_moderated', 'moderation_status', 'moderated_by', 'moderated_at', 'moderation_reason', 'posted_at'])]
class KnowledgePost extends Model
{
    /** @use HasFactory<KnowledgePostFactory> */
    use HasFactory,HasUuids,SoftDeletes;

    protected function casts(): array
    {
        return ['is_moderated' => 'boolean', 'posted_at' => 'immutable_datetime', 'moderated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<KnowledgeDiscussion, $this> */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDiscussion::class, 'knowledge_discussion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /** @return HasMany<KnowledgeCommunityReport, $this> */
    public function communityReports(): HasMany
    {
        return $this->hasMany(KnowledgeCommunityReport::class);
    }
}
