<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InnovationPanelReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $devolution_innovation_id
 * @property string $reviewer_id
 * @property string $rubric_code
 * @property string $rubric_checksum
 * @property string $strategic_fit_score
 * @property string $feasibility_score
 * @property string $inclusion_score
 * @property string $evidence_score
 * @property string $weighted_score
 * @property string $recommendation
 * @property string $rationale
 * @property CarbonImmutable|null $reviewed_at
 * @property string $evidence_checksum
 */
#[Fillable(['devolution_innovation_id', 'reviewer_id', 'rubric_code', 'rubric_checksum', 'strategic_fit_score', 'feasibility_score', 'inclusion_score', 'evidence_score', 'weighted_score', 'recommendation', 'rationale', 'reviewed_at', 'evidence_checksum'])]
class InnovationPanelReview extends Model
{
    /** @use HasFactory<InnovationPanelReviewFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime', 'strategic_fit_score' => 'decimal:2', 'feasibility_score' => 'decimal:2', 'inclusion_score' => 'decimal:2', 'evidence_score' => 'decimal:2', 'weighted_score' => 'decimal:2'];
    }

    /** @return BelongsTo<DevolutionInnovation, $this> */
    public function innovation(): BelongsTo
    {
        return $this->belongsTo(DevolutionInnovation::class, 'devolution_innovation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
