<?php

namespace App\Models;

use Database\Factories\IgrForumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'forum_type', 'mandate', 'secretariat_user_id', 'status', 'created_by'])]
class IgrForum extends Model
{
    /** @use HasFactory<IgrForumFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function secretariat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretariat_user_id');
    }

    /** @return HasMany<IgrResolution, $this> */
    public function resolutions(): HasMany
    {
        return $this->hasMany(IgrResolution::class);
    }

    /** @return HasMany<IgrForumMeeting, $this> */
    public function meetings(): HasMany
    {
        return $this->hasMany(IgrForumMeeting::class);
    }
}
