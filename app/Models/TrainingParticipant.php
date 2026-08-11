<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TrainingParticipantFactory;
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
 * @property string $training_cohort_id
 * @property string|null $user_id
 * @property string|null $county_id
 * @property string $participant_reference
 * @property string $role_title
 * @property float $attended_hours
 * @property string $attendance_status
 * @property string $competency_status
 * @property CarbonImmutable|null $completed_at
 * @property-read TrainingCohort $cohort
 * @property-read User|null $user
 * @property-read County|null $county
 * @property-read Collection<int, TrainingAssessment> $assessments
 */
#[Fillable(['training_cohort_id', 'user_id', 'county_id', 'participant_reference', 'role_title', 'attended_hours', 'attendance_status', 'competency_status', 'completed_at'])]
class TrainingParticipant extends Model
{
    /** @use HasFactory<TrainingParticipantFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['attended_hours' => 0, 'attendance_status' => 'registered', 'competency_status' => 'not_assessed'];

    protected function casts(): array
    {
        return ['attended_hours' => 'decimal:2', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<TrainingCohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(TrainingCohort::class, 'training_cohort_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return HasMany<TrainingAssessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(TrainingAssessment::class);
    }
}
