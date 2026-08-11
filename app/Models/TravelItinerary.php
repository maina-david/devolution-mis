<?php

namespace App\Models;

use Database\Factories\TravelItineraryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $departs_at
 * @property Carbon $arrives_at
 */
#[Fillable(['travel_request_id', 'sequence', 'origin', 'destination', 'departs_at', 'arrives_at', 'transport_mode', 'carrier', 'booking_reference', 'estimated_cost', 'metadata'])]
class TravelItinerary extends Model
{
    /** @use HasFactory<TravelItineraryFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['departs_at' => 'immutable_datetime', 'arrives_at' => 'immutable_datetime', 'estimated_cost' => 'decimal:2', 'metadata' => 'array'];
    }

    /** @return BelongsTo<TravelRequest, $this> */
    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }
}
