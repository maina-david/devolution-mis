<?php

namespace App\Models;

use Database\Factories\OperationalAlertEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_type
 * @property string $status
 * @property string $narrative
 * @property Carbon $occurred_at
 * @property string $evidence_checksum
 * @property-read User|null $actor
 */
#[Fillable(['operational_alert_id', 'measurement_id', 'actor_id', 'event_type', 'status', 'narrative', 'occurred_at', 'evidence_checksum'])]
class OperationalAlertEvent extends Model
{
    /** @use HasFactory<OperationalAlertEventFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<OperationalAlert, $this> */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(OperationalAlert::class, 'operational_alert_id');
    }

    /** @return BelongsTo<ServiceLevelMeasurement, $this> */
    public function measurement(): BelongsTo
    {
        return $this->belongsTo(ServiceLevelMeasurement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}
