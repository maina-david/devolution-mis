<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string $id
 * @property string $code
 * @property string $name
 * @property int $registered_voters_2022
 * @property-read SubCounty $subCounty
 */
#[Fillable(['sub_county_id', 'code', 'name', 'slug', 'source_authority', 'source_reference', 'source_checksum_sha256', 'boundary_geojson', 'boundary_checksum_sha256', 'registered_voters_2022', 'effective_from', 'effective_to'])]
class Ward extends Model
{
    /** @use HasFactory<WardFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @return BelongsTo<SubCounty, $this> */
    public function subCounty(): BelongsTo
    {
        return $this->belongsTo(SubCounty::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['boundary_geojson' => 'array', 'registered_voters_2022' => 'integer', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date'];
    }
}
