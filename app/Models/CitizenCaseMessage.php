<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CitizenCaseMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $direction
 * @property string $visibility
 * @property string $channel
 * @property string $body
 * @property CarbonImmutable $posted_at
 */
#[Fillable(['citizen_case_id', 'sender_user_id', 'direction', 'visibility', 'channel', 'body', 'delivery_status', 'posted_at'])]
class CitizenCaseMessage extends Model
{
    /** @use HasFactory<CitizenCaseMessageFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['posted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<CitizenCase, $this> */
    public function citizenCase(): BelongsTo
    {
        return $this->belongsTo(CitizenCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /** @return HasMany<CitizenCaseAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(CitizenCaseAttachment::class);
    }
}
