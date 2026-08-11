<?php

namespace App\Models;

use Database\Factories\TravelApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** @property Carbon $decided_at */
#[Fillable(['travel_request_id', 'actor_id', 'stage', 'decision', 'rationale', 'approved_cost', 'source_system', 'external_reference', 'decided_at'])]
class TravelApproval extends Model
{
    /** @use HasFactory<TravelApprovalFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['approved_cost' => 'decimal:2', 'decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<TravelRequest, $this> */
    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
