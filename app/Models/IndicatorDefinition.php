<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IndicatorDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $results_level
 * @property string $value_type
 * @property string $status
 * @property int $version
 * @property string $created_by
 * @property string|null $supersedes_id
 * @property string|null $sector_id
 * @property string|null $programme_id
 * @property string|null $reference_data_release_id
 * @property CarbonImmutable|null $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property array<string, mixed>|null $calculation_formula
 */
#[Fillable(['supersedes_id', 'code', 'name', 'description', 'sector_id', 'programme_id', 'reference_data_release_id', 'results_level', 'unit_of_measure', 'value_type', 'direction', 'frequency', 'disaggregation_dimensions', 'calculation_formula', 'data_source', 'verification_method', 'version', 'change_summary', 'status', 'effective_from', 'effective_to', 'created_by', 'approved_by', 'approved_at'])]
class IndicatorDefinition extends Model
{
    /** @use HasFactory<IndicatorDefinitionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft', 'version' => 1, 'value_type' => 'number', 'direction' => 'increase'];

    protected function casts(): array
    {
        return ['disaggregation_dimensions' => 'array', 'calculation_formula' => 'array', 'effective_from' => 'immutable_datetime', 'effective_to' => 'immutable_datetime', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return HasMany<IndicatorObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(IndicatorObservation::class);
    }

    /** @return BelongsTo<IndicatorDefinition, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return HasMany<IndicatorDefinition, $this> */
    public function successors(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    /** @return BelongsToMany<DevolutionProject, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(DevolutionProject::class, 'devolution_project_indicator')->withPivot('is_primary')->withTimestamps();
    }

    public function isCurrentApprovedVersion(): bool
    {
        return $this->status === 'approved'
            && ($this->effective_from === null || $this->effective_from->isPast())
            && ($this->effective_to === null || $this->effective_to->isFuture())
            && ! self::query()
                ->where('code', $this->code)
                ->where('status', 'approved')
                ->where('version', '>', $this->version)
                ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
                ->exists();
    }
}
