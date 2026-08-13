<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SubCountyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $classification
 * @property string $source_authority
 * @property-read County $county
 * @property-read Collection<int, Ward> $wards
 */
#[Fillable(['county_id', 'code', 'name', 'slug', 'classification', 'source_authority', 'source_reference', 'source_checksum_sha256', 'boundary_geojson', 'boundary_checksum_sha256', 'effective_from', 'effective_to'])]
class SubCounty extends Model
{
    /** @use HasFactory<SubCountyFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return HasMany<Ward, $this> */
    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['boundary_geojson' => 'array', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
