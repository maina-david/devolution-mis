<?php

namespace App\Models;

use Database\Factories\IgrForumMeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** @property Carbon $held_on */
#[Fillable(['igr_forum_id', 'reference', 'title', 'held_on', 'venue', 'chair_user_id', 'quorum_confirmed', 'minutes_reference', 'created_by'])]
class IgrForumMeeting extends Model
{
    /** @use HasFactory<IgrForumMeetingFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['held_on' => 'immutable_date', 'quorum_confirmed' => 'boolean'];
    }

    /** @return BelongsTo<IgrForum, $this> */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(IgrForum::class, 'igr_forum_id');
    }

    /** @return BelongsTo<User, $this> */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_user_id');
    }

    /** @return HasMany<IgrResolution, $this> */
    public function resolutions(): HasMany
    {
        return $this->hasMany(IgrResolution::class);
    }
}
