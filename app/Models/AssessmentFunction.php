<?php

namespace App\Models;

use Database\Factories\AssessmentFunctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $id @property string $assessment_scorecard_version_id @property string $code @property string $name @property string|null $description @property string $function_type @property string $weight @property int $sequence */
#[Fillable(['assessment_scorecard_version_id', 'code', 'name', 'description', 'function_type', 'weight', 'sequence'])]
class AssessmentFunction extends Model
{
    /** @use HasFactory<AssessmentFunctionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['weight' => 'decimal:4'];
    }

    /** @return BelongsTo<AssessmentScorecardVersion, $this> */
    public function scorecardVersion(): BelongsTo
    {
        return $this->belongsTo(AssessmentScorecardVersion::class, 'assessment_scorecard_version_id');
    }

    /** @return HasMany<AssessmentThematicArea, $this> */
    public function thematicAreas(): HasMany
    {
        return $this->hasMany(AssessmentThematicArea::class);
    }
}
