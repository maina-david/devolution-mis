<?php

namespace App\Models;

use Database\Factories\AssessmentStandardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $id @property string $assessment_thematic_area_id @property string $code @property string $name @property string|null $description @property string|null $norm_reference @property string $weight @property int $sequence */
#[Fillable(['assessment_thematic_area_id', 'code', 'name', 'description', 'norm_reference', 'weight', 'sequence'])]
class AssessmentStandard extends Model
{
    /** @use HasFactory<AssessmentStandardFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['weight' => 'decimal:4'];
    }

    /** @return BelongsTo<AssessmentThematicArea, $this> */
    public function thematicArea(): BelongsTo
    {
        return $this->belongsTo(AssessmentThematicArea::class, 'assessment_thematic_area_id');
    }

    /** @return HasMany<AssessmentCriterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(AssessmentCriterion::class);
    }
}
