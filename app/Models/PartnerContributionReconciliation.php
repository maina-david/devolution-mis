<?php

namespace App\Models;

use Database\Factories\PartnerContributionReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $version
 * @property string $decision
 * @property string $verified_committed_amount
 * @property string $verified_disbursed_amount
 * @property string $verified_in_kind_value
 * @property string $disbursement_variance
 * @property string $source_reference
 * @property string $review_note
 * @property Carbon $reviewed_at
 * @property string $evidence_checksum
 * @property string|null $predecessor_checksum
 * @property string $decision_checksum
 * @property-read User $reviewer
 */
#[Fillable(['partner_contribution_id', 'version', 'decision', 'verified_committed_amount', 'verified_disbursed_amount', 'verified_in_kind_value', 'disbursement_variance', 'source_reference', 'review_note', 'reviewed_by', 'reviewed_at', 'evidence_checksum', 'predecessor_checksum', 'decision_checksum', 'snapshot'])]
class PartnerContributionReconciliation extends Model
{
    /** @use HasFactory<PartnerContributionReconciliationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['verified_committed_amount' => 'decimal:2', 'verified_disbursed_amount' => 'decimal:2', 'verified_in_kind_value' => 'decimal:2', 'disbursement_variance' => 'decimal:2', 'reviewed_at' => 'immutable_datetime', 'snapshot' => 'array'];
    }

    /** @return BelongsTo<PartnerContribution, $this> */
    public function contribution(): BelongsTo
    {
        return $this->belongsTo(PartnerContribution::class, 'partner_contribution_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
