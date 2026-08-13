<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DswgCollaborationThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $dswg_working_group_id
 * @property string $created_by
 * @property string $title
 * @property string $topic
 * @property string $status
 * @property CarbonImmutable $last_activity_at
 * @property int $messages_count
 * @property-read DswgWorkingGroup $workingGroup
 * @property-read User $creator
 */
#[Fillable(['dswg_working_group_id', 'created_by', 'title', 'topic', 'status', 'last_activity_at'])]
class DswgCollaborationThread extends Model
{
    /** @use HasFactory<DswgCollaborationThreadFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DswgWorkingGroup, $this> */
    public function workingGroup(): BelongsTo
    {
        return $this->belongsTo(DswgWorkingGroup::class, 'dswg_working_group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DswgCollaborationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(DswgCollaborationMessage::class);
    }
}
