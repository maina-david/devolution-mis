<?php

namespace App\Models;

use Database\Factories\DswgDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $title
 * @property-read DswgMeeting $meeting
 */
#[Fillable(['dswg_meeting_id', 'code', 'title', 'decision_text', 'decision_type', 'status', 'decided_at', 'created_by'])]
class DswgDecision extends Model
{
    /** @use HasFactory<DswgDecisionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['decision_type' => 'resolution', 'status' => 'adopted'];

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DswgMeeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(DswgMeeting::class, 'dswg_meeting_id');
    }

    /** @return HasMany<DswgAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(DswgAction::class);
    }
}
