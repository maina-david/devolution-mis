<?php

namespace App\Models;

use Database\Factories\ProgrammeCountyCoverageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $programme_id
 * @property string $county_id
 * @property string|null $implementation_lead_id
 * @property string $created_by
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string $status
 * @property string|null $funding_allocation
 * @property string $currency
 * @property string $source_reference
 * @property string|null $notes
 * @property-read Programme $programme
 * @property-read County $county
 * @property-read Organization|null $implementationLead
 * @property-read User $creator
 */
#[Fillable(['programme_id', 'county_id', 'implementation_lead_id', 'created_by', 'starts_on', 'ends_on', 'status', 'funding_allocation', 'currency', 'source_reference', 'notes'])]
class ProgrammeCountyCoverage extends Model
{
    /** @use HasFactory<ProgrammeCountyCoverageFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'funding_allocation' => 'decimal:2'];
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function implementationLead(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'implementation_lead_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
