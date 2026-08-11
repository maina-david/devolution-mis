<?php

namespace App\Models;

use Database\Factories\ProgrammeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $lead_organization_id
 * @property string|null $sector_id
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string $status
 * @property string|null $budget_amount
 * @property string $currency
 */
#[Fillable(['code', 'name', 'description', 'lead_organization_id', 'sector_id', 'starts_on', 'ends_on', 'status', 'budget_amount', 'currency'])]
class Programme extends Model
{
    /** @use HasFactory<ProgrammeFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'budget_amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function leadOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'lead_organization_id');
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** @return HasMany<ProgrammeCountyCoverage, $this> */
    public function countyCoverages(): HasMany
    {
        return $this->hasMany(ProgrammeCountyCoverage::class);
    }
}
