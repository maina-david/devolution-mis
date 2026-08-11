<?php

namespace App\Models;

use Database\Factories\PartnerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $reference_data_release_id
 * @property string $partner_type
 * @property string $status
 * @property int $agreements_count
 * @property int $contributions_count
 * @property int $collaboration_plans_count
 * @property int $reconciled_contributions_count
 * @property int $open_operational_alerts_count
 * @property string|null $committed_total
 * @property string|null $disbursed_total
 * @property-read Organization $organization
 * @property-read ReferenceDataRelease|null $referenceDataRelease
 * @property-read Collection<int, County> $counties
 * @property-read Collection<int, Sector> $sectors
 */
#[Fillable(['organization_id', 'partner_type', 'country', 'website', 'focal_point_name', 'focal_point_email', 'focal_point_phone', 'strategic_priorities', 'modalities', 'status', 'created_by', 'reference_data_release_id'])]
class PartnerProfile extends Model
{
    /** @use HasFactory<PartnerProfileFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['modalities' => 'array'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsToMany<County, $this> */
    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class, 'partner_profile_county')->withTimestamps();
    }

    /** @return BelongsToMany<Sector, $this> */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'partner_profile_sector')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'partner_profile_user')->withPivot('relationship_role')->withTimestamps();
    }

    /** @return HasMany<PartnerAgreement, $this> */
    public function agreements(): HasMany
    {
        return $this->hasMany(PartnerAgreement::class);
    }

    /** @return HasMany<PartnerContribution, $this> */
    public function contributions(): HasMany
    {
        return $this->hasMany(PartnerContribution::class);
    }

    /** @return HasMany<PartnerOperationalAlert, $this> */
    public function operationalAlerts(): HasMany
    {
        return $this->hasMany(PartnerOperationalAlert::class);
    }

    /** @return HasMany<PartnerCollaborationPlan, $this> */
    public function collaborationPlans(): HasMany
    {
        return $this->hasMany(PartnerCollaborationPlan::class);
    }
}
