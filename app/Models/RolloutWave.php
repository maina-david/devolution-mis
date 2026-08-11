<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RolloutWaveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $created_by
 * @property string|null $approved_by
 * @property string|null $reference_data_release_id
 * @property string $code
 * @property string $name
 * @property string $objective
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $planned_participants
 * @property string $status
 * @property list<string> $entry_criteria
 * @property list<string> $support_channels
 * @property bool $help_desk_rehearsed
 * @property bool $training_materials_approved
 * @property string|null $readiness_notes
 * @property CarbonImmutable|null $approved_at
 * @property int $cohorts_count
 * @property int $completed_participants_count
 * @property-read User $creator
 * @property-read User|null $approver
 * @property-read Collection<int, County> $counties
 */
#[Fillable(['created_by', 'approved_by', 'reference_data_release_id', 'code', 'name', 'objective', 'starts_on', 'ends_on', 'planned_participants', 'status', 'entry_criteria', 'support_channels', 'help_desk_rehearsed', 'training_materials_approved', 'readiness_notes', 'approved_at'])]
class RolloutWave extends Model
{
    /** @use HasFactory<RolloutWaveFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'planning', 'help_desk_rehearsed' => false, 'training_materials_approved' => false];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'planned_participants' => 'integer', 'entry_criteria' => 'array', 'support_channels' => 'array', 'help_desk_rehearsed' => 'boolean', 'training_materials_approved' => 'boolean', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsToMany<County, $this> */
    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class)->withPivot(['readiness_status', 'readiness_note'])->withTimestamps();
    }

    /** @return HasMany<TrainingCohort, $this> */
    public function cohorts(): HasMany
    {
        return $this->hasMany(TrainingCohort::class);
    }
}
