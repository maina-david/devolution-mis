<?php

namespace App\Models;

use Database\Factories\AssessmentScorecardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $id @property string $code @property string $name @property string|null $description @property string $status */
#[Fillable(['code', 'name', 'description', 'status'])]
class AssessmentScorecard extends Model
{
    /** @use HasFactory<AssessmentScorecardFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    /** @return HasMany<AssessmentScorecardVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(AssessmentScorecardVersion::class);
    }
}
