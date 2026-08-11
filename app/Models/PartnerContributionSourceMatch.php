<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PartnerContributionSourceMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $reconciliation_run_id
 * @property string $integration_exchange_id
 * @property string|null $partner_contribution_id
 * @property string|null $county_id
 * @property string|null $matched_by
 * @property string $matched_by_name
 * @property string|null $external_reference
 * @property string|null $local_reference
 * @property string $outcome
 * @property numeric-string|null $source_committed_amount
 * @property numeric-string|null $source_disbursed_amount
 * @property numeric-string|null $source_in_kind_value
 * @property numeric-string|null $local_committed_amount
 * @property numeric-string|null $local_disbursed_amount
 * @property numeric-string|null $local_in_kind_value
 * @property numeric-string|null $disbursement_variance
 * @property string|null $source_currency
 * @property string|null $local_currency
 * @property string $source_checksum
 * @property string $match_checksum
 * @property array<string, mixed> $snapshot
 * @property CarbonImmutable $matched_at
 * @property-read ReconciliationRun $run
 * @property-read IntegrationExchange $exchange
 * @property-read PartnerContribution|null $contribution
 * @property-read County|null $county
 * @property-read User|null $matcher
 */
#[Fillable(['reconciliation_run_id', 'integration_exchange_id', 'partner_contribution_id', 'county_id', 'matched_by', 'matched_by_name', 'external_reference', 'local_reference', 'outcome', 'source_committed_amount', 'source_disbursed_amount', 'source_in_kind_value', 'local_committed_amount', 'local_disbursed_amount', 'local_in_kind_value', 'disbursement_variance', 'source_currency', 'local_currency', 'source_checksum', 'match_checksum', 'snapshot', 'matched_at'])]
class PartnerContributionSourceMatch extends Model
{
    /** @use HasFactory<PartnerContributionSourceMatchFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'source_committed_amount' => 'decimal:2',
            'source_disbursed_amount' => 'decimal:2',
            'source_in_kind_value' => 'decimal:2',
            'local_committed_amount' => 'decimal:2',
            'local_disbursed_amount' => 'decimal:2',
            'local_in_kind_value' => 'decimal:2',
            'disbursement_variance' => 'decimal:2',
            'snapshot' => 'array',
            'matched_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ReconciliationRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    /** @return BelongsTo<IntegrationExchange, $this> */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(IntegrationExchange::class, 'integration_exchange_id');
    }

    /** @return BelongsTo<PartnerContribution, $this> */
    public function contribution(): BelongsTo
    {
        return $this->belongsTo(PartnerContribution::class, 'partner_contribution_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
