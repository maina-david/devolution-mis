<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TrainingCohortFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $rollout_wave_id
 * @property string|null $county_id
 * @property string|null $reference_data_release_id
 * @property string|null $facilitator_id
 * @property string $code
 * @property string $name
 * @property string $audience_role
 * @property string $delivery_mode
 * @property string $language
 * @property string|null $venue
 * @property int $seat_capacity
 * @property float $minimum_attendance_hours
 * @property float $passing_score
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $status
 * @property int $participants_count
 * @property int $completed_count
 * @property-read RolloutWave $wave
 * @property-read County|null $county
 * @property-read User|null $facilitator
 * @property-read Collection<int, TrainingParticipant> $participants
 */
#[Fillable(['rollout_wave_id', 'county_id', 'reference_data_release_id', 'facilitator_id', 'code', 'name', 'audience_role', 'delivery_mode', 'language', 'venue', 'seat_capacity', 'minimum_attendance_hours', 'passing_score', 'starts_at', 'ends_at', 'status'])]
class TrainingCohort extends Model
{
    /** @use HasFactory<TrainingCohortFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'planned', 'minimum_attendance_hours' => 6, 'passing_score' => 70];

    protected function casts(): array
    {
        return ['seat_capacity' => 'integer', 'minimum_attendance_hours' => 'decimal:2', 'passing_score' => 'decimal:2', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<RolloutWave, $this> */
    public function wave(): BelongsTo
    {
        return $this->belongsTo(RolloutWave::class, 'rollout_wave_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    /** @return HasMany<TrainingParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }
}
