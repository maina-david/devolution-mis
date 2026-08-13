<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DswgCollaborationMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $dswg_collaboration_thread_id
 * @property string $author_id
 * @property string $body
 * @property string $checksum
 * @property CarbonImmutable $posted_at
 * @property-read User $author
 */
#[Fillable(['dswg_collaboration_thread_id', 'author_id', 'body', 'checksum', 'posted_at'])]
class DswgCollaborationMessage extends Model
{
    /** @use HasFactory<DswgCollaborationMessageFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['posted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DswgCollaborationThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(DswgCollaborationThread::class, 'dswg_collaboration_thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
