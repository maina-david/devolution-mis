<?php

namespace App\Models;

use Database\Factories\AssessmentThematicAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $id @property string $assessment_function_id @property string $code @property string $name @property string|null $description @property string $weight @property int $sequence */
#[Fillable(['assessment_function_id', 'code', 'name', 'description', 'weight', 'sequence'])]
class AssessmentThematicArea extends Model
{
    /** @use HasFactory<AssessmentThematicAreaFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['weight' => 'decimal:4'];
    }

    /** @return BelongsTo<AssessmentFunction, $this> */
    public function function(): BelongsTo
    {
        return $this->belongsTo(AssessmentFunction::class, 'assessment_function_id');
    }

    /** @return HasMany<AssessmentStandard, $this> */
    public function standards(): HasMany
    {
        return $this->hasMany(AssessmentStandard::class);
    }
}
