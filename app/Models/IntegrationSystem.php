<?php

namespace App\Models;

use Database\Factories\IntegrationSystemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Passport\Client;

/**
 * @property string $id
 * @property string|null $oauth_client_id
 * @property string|null $reference_data_release_id
 * @property string $code
 * @property string $environment
 * @property string $direction
 * @property string $status
 * @property string|null $production_approval_reference
 * @property Carbon|null $production_approved_at
 * @property-read Client|null $oauthClient
 */
#[Fillable(['owner_organization_id', 'reference_data_release_id', 'registered_by', 'oauth_client_id', 'code', 'name', 'purpose', 'system_owner', 'environment', 'transport', 'auth_scheme', 'credential_reference', 'base_url', 'direction', 'data_classification', 'status', 'production_approval_reference', 'production_approved_at', 'health_status', 'last_health_check_at', 'metadata'])]
class IntegrationSystem extends Model
{
    /** @use HasFactory<IntegrationSystemFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['metadata' => 'array', 'production_approved_at' => 'immutable_datetime', 'last_health_check_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function ownerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'owner_organization_id');
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** @return HasMany<IntegrationContract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(IntegrationContract::class);
    }

    /** @return HasMany<ReconciliationRun, $this> */
    public function reconciliationRuns(): HasMany
    {
        return $this->hasMany(ReconciliationRun::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function oauthClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'oauth_client_id');
    }
}
