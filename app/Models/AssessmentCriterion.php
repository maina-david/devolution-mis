<?php

namespace App\Models;

use Database\Factories\AssessmentCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $assessment_standard_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $weight
 * @property string $maximum_score
 * @property string $scoring_method
 * @property array<string, mixed> $formula
 * @property list<array<string, mixed>> $thresholds
 * @property bool $is_mandatory
 * @property int $sequence
 */
#[Fillable(['assessment_standard_id', 'code', 'name', 'description', 'weight', 'maximum_score', 'scoring_method', 'formula', 'thresholds', 'is_mandatory', 'sequence'])]
class AssessmentCriterion extends Model
{
    /** @use HasFactory<AssessmentCriterionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['maximum_score' => 100, 'scoring_method' => 'scale', 'is_mandatory' => true];

    protected function casts(): array
    {
        return ['weight' => 'decimal:4', 'maximum_score' => 'decimal:4', 'formula' => 'array', 'thresholds' => 'array', 'is_mandatory' => 'boolean'];
    }

    /** @return BelongsTo<AssessmentStandard, $this> */
    public function standard(): BelongsTo
    {
        return $this->belongsTo(AssessmentStandard::class, 'assessment_standard_id');
    }

    /** @return HasMany<CriterionEvidenceRequirement, $this> */
    public function evidenceRequirements(): HasMany
    {
        return $this->hasMany(CriterionEvidenceRequirement::class);
    }
}
