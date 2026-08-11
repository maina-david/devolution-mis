<?php

namespace App\Models;

use Database\Factories\IgrResolutionAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $igr_resolution_id
 * @property string|null $user_id
 * @property string|null $organization_id
 * @property string|null $county_id
 * @property string $responsibility_role
 * @property bool $is_lead
 * @property-read User|null $user
 * @property-read Organization|null $organization
 * @property-read County|null $county
 */
#[Fillable(['igr_resolution_id', 'user_id', 'organization_id', 'county_id', 'responsibility_role', 'is_lead', 'status'])]
class IgrResolutionAssignment extends Model
{
    /** @use HasFactory<IgrResolutionAssignmentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['is_lead' => 'boolean'];
    }

    /** @return BelongsTo<IgrResolution, $this> */
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(IgrResolution::class, 'igr_resolution_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }
}
