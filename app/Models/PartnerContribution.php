<?php

namespace App\Models;

use Database\Factories\PartnerContributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $partner_profile_id
 * @property string $devolution_project_id
 * @property string $financial_year
 * @property string $contribution_type
 * @property numeric-string $committed_amount
 * @property numeric-string $disbursed_amount
 * @property numeric-string $in_kind_value
 * @property string $currency
 * @property string $status
 * @property string $reported_by
 * @property array<string, mixed> $provenance
 * @property-read PartnerProfile $partner
 * @property-read DevolutionProject $project
 */
#[Fillable(['partner_profile_id', 'devolution_project_id', 'financial_year', 'contribution_type', 'committed_amount', 'disbursed_amount', 'in_kind_value', 'currency', 'description', 'status', 'reported_by', 'provenance'])]
class PartnerContribution extends Model
{
    /** @use HasFactory<PartnerContributionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return ['committed_amount' => 'decimal:2', 'disbursed_amount' => 'decimal:2', 'in_kind_value' => 'decimal:2', 'provenance' => 'array'];
    }

    /** @return BelongsTo<PartnerProfile, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }

    /** @return BelongsTo<DevolutionProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DevolutionProject::class, 'devolution_project_id');
    }

    /** @return HasMany<PartnerContributionReconciliation, $this> */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(PartnerContributionReconciliation::class)->latest('version');
    }

    /** @return HasMany<PartnerContributionSourceMatch, $this> */
    public function sourceMatches(): HasMany
    {
        return $this->hasMany(PartnerContributionSourceMatch::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
