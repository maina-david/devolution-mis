<?php

namespace App\Models;

use Database\Factories\DswgWorkingGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string $code
 * @property string $name
 * @property-read Organization|null $leadOrganization
 * @property-read ReferenceDataRelease|null $referenceDataRelease
 * @property-read User $secretariat
 */
#[Fillable(['code', 'name', 'mandate', 'scope', 'lead_organization_id', 'secretariat_user_id', 'meeting_frequency', 'status', 'created_by', 'reference_data_release_id'])]
class DswgWorkingGroup extends Model
{
    /** @use HasFactory<DswgWorkingGroupFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['scope' => 'national', 'status' => 'active'];

    /** @return BelongsTo<Organization, $this> */
    public function leadOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'lead_organization_id');
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function secretariat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretariat_user_id');
    }

    /** @return BelongsToMany<County, $this> */
    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class, 'dswg_working_group_county')->withTimestamps();
    }

    /** @return BelongsToMany<Sector, $this> */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'dswg_working_group_sector')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dswg_working_group_user')->withPivot(['membership_role', 'status'])->withTimestamps();
    }

    /** @return HasMany<DswgMeeting, $this> */
    public function meetings(): HasMany
    {
        return $this->hasMany(DswgMeeting::class);
    }

    /** @return HasMany<DswgMeetingSeries, $this> */
    public function meetingSeries(): HasMany
    {
        return $this->hasMany(DswgMeetingSeries::class);
    }
}
