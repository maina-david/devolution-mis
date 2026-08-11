<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InnovationFundingDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $devolution_innovation_id
 * @property int $decision_version
 * @property string $decision
 * @property string $amount
 * @property string $currency
 * @property string $funding_type
 * @property string $decision_reference
 * @property string $rationale
 * @property string $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $previous_checksum
 * @property string $evidence_checksum
 */
#[Fillable(['devolution_innovation_id', 'decision_version', 'decision', 'amount', 'currency', 'funding_type', 'decision_reference', 'rationale', 'decided_by', 'decided_at', 'previous_checksum', 'evidence_checksum'])]
class InnovationFundingDecision extends Model
{
    /** @use HasFactory<InnovationFundingDecisionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<DevolutionInnovation, $this> */
    public function innovation(): BelongsTo
    {
        return $this->belongsTo(DevolutionInnovation::class, 'devolution_innovation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
