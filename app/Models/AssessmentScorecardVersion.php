<?php

namespace App\Models;

use Database\Factories\AssessmentScorecardVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $assessment_scorecard_id
 * @property int $version
 * @property string $status
 * @property string|null $change_notes
 * @property string $calculation_method
 * @property array<string, mixed> $mcda_configuration
 * @property list<array<string, mixed>> $performance_thresholds
 * @property string|null $checksum
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $published_by
 * @property Carbon|null $published_at
 */
#[Fillable(['assessment_scorecard_id', 'version', 'status', 'change_notes', 'calculation_method', 'mcda_configuration', 'performance_thresholds', 'checksum', 'effective_from', 'effective_to', 'published_by', 'published_at'])]
class AssessmentScorecardVersion extends Model
{
    /** @use HasFactory<AssessmentScorecardVersionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft', 'calculation_method' => 'weighted_sum'];

    protected function casts(): array
    {
        return ['mcda_configuration' => 'array', 'performance_thresholds' => 'array', 'effective_from' => 'immutable_datetime', 'effective_to' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AssessmentScorecard, $this> */
    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(AssessmentScorecard::class, 'assessment_scorecard_id');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<AssessmentFunction, $this> */
    public function functions(): HasMany
    {
        return $this->hasMany(AssessmentFunction::class);
    }

    /** @return HasMany<AssessmentCycle, $this> */
    public function cycles(): HasMany
    {
        return $this->hasMany(AssessmentCycle::class);
    }
}
