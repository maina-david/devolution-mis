<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SecurityThreatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $owner_id
 * @property string|null $submitted_by
 * @property string|null $reviewed_by
 * @property string $reference
 * @property string $title
 * @property string $stride_category
 * @property string $asset
 * @property string $scenario
 * @property string|null $threat_actor
 * @property list<string> $entry_points
 * @property int $likelihood
 * @property int $impact
 * @property int $inherent_risk_score
 * @property list<string> $existing_controls
 * @property string $treatment_plan
 * @property string $treatment_status
 * @property int|null $residual_likelihood
 * @property int|null $residual_impact
 * @property int|null $residual_risk_score
 * @property string|null $risk_acceptance_reference
 * @property string $status
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable $review_due_at
 * @property list<string>|null $evidence_references
 * @property User|null $owner
 * @property User|null $submitter
 * @property User|null $reviewer
 */
#[Fillable(['owner_id', 'submitted_by', 'reviewed_by', 'reference', 'title', 'stride_category', 'asset', 'scenario', 'threat_actor', 'entry_points', 'likelihood', 'impact', 'inherent_risk_score', 'existing_controls', 'treatment_plan', 'treatment_status', 'residual_likelihood', 'residual_impact', 'residual_risk_score', 'risk_acceptance_reference', 'status', 'submitted_at', 'reviewed_at', 'review_due_at', 'evidence_references'])]
class SecurityThreat extends Model
{
    /** @use HasFactory<SecurityThreatFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['treatment_status' => 'planned', 'status' => 'submitted'];

    protected function casts(): array
    {
        return ['entry_points' => 'array', 'likelihood' => 'integer', 'impact' => 'integer', 'inherent_risk_score' => 'integer', 'existing_controls' => 'array', 'residual_likelihood' => 'integer', 'residual_impact' => 'integer', 'residual_risk_score' => 'integer', 'submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'review_due_at' => 'date', 'evidence_references' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
