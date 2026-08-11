<?php

namespace App\Models;

use Database\Factories\ExchequerEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $exchequer_request_id
 * @property string|null $integration_exchange_id
 * @property string $recorded_by
 * @property string $source_system
 * @property string $event_type
 * @property string $source_event_reference
 * @property Carbon $occurred_at
 * @property Carbon $received_at
 * @property int $elapsed_from_previous_minutes
 * @property int $elapsed_total_minutes
 * @property string|null $notes
 * @property string $evidence_checksum
 */
#[Fillable(['exchequer_request_id', 'integration_exchange_id', 'recorded_by', 'source_system', 'event_type', 'source_event_reference', 'occurred_at', 'received_at', 'elapsed_from_previous_minutes', 'elapsed_total_minutes', 'notes', 'evidence_checksum'])]
class ExchequerEvent extends Model
{
    /** @use HasFactory<ExchequerEventFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ExchequerRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ExchequerRequest::class, 'exchequer_request_id');
    }

    /** @return BelongsTo<IntegrationExchange, $this> */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(IntegrationExchange::class, 'integration_exchange_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
