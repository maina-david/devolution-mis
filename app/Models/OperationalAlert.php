<?php

namespace App\Models;

use Database\Factories\OperationalAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $service
 * @property string $metric
 * @property string $severity
 * @property string $status
 * @property int $occurrence_count
 * @property int|null $events_count
 * @property Carbon $first_detected_at
 * @property Carbon $last_detected_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $recovered_at
 */
#[Fillable(['initial_measurement_id', 'latest_measurement_id', 'recovery_measurement_id', 'service', 'metric', 'severity', 'status', 'latest_value', 'threshold', 'unit', 'occurrence_count', 'first_detected_at', 'last_detected_at', 'acknowledged_by', 'acknowledged_at', 'acknowledgement_note', 'recovered_at', 'evidence_checksum'])]
class OperationalAlert extends Model
{
    /** @use HasFactory<OperationalAlertFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'open', 'occurrence_count' => 1];

    /** @return BelongsTo<ServiceLevelMeasurement, $this> */
    public function initialMeasurement(): BelongsTo
    {
        return $this->belongsTo(ServiceLevelMeasurement::class, 'initial_measurement_id');
    }

    /** @return BelongsTo<ServiceLevelMeasurement, $this> */
    public function latestMeasurement(): BelongsTo
    {
        return $this->belongsTo(ServiceLevelMeasurement::class, 'latest_measurement_id');
    }

    /** @return BelongsTo<ServiceLevelMeasurement, $this> */
    public function recoveryMeasurement(): BelongsTo
    {
        return $this->belongsTo(ServiceLevelMeasurement::class, 'recovery_measurement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @return HasMany<OperationalAlertEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OperationalAlertEvent::class);
    }

    protected function casts(): array
    {
        return ['latest_value' => 'decimal:4', 'threshold' => 'decimal:4', 'occurrence_count' => 'integer', 'first_detected_at' => 'immutable_datetime', 'last_detected_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime', 'recovered_at' => 'immutable_datetime'];
    }
}
