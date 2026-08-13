<?php

namespace App\Models;

use Database\Factories\CountyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;

/**
 * @property string $id
 * @property int $code
 * @property string $name
 * @property string $slug
 * @property string|null $region
 * @property string|null $logo_path
 * @property string|null $logo_source_url
 * @property string|null $official_website_url
 * @property string|null $logo_source_authority
 * @property string|null $logo_source_kind
 * @property string|null $logo_checksum_sha256
 * @property string|null $logo_source_checksum_sha256
 * @property Carbon|null $logo_verified_at
 * @property float $map_x
 * @property float $map_y
 * @property int $documents_count
 * @property-read Collection<int, Assessment> $assessments
 * @property-read Collection<int, CountyGrant> $grants
 */
#[Fillable(['code', 'name', 'slug', 'region', 'logo_path', 'logo_source_url', 'official_website_url', 'logo_source_authority', 'logo_source_kind', 'logo_checksum_sha256', 'logo_source_checksum_sha256', 'logo_verified_at', 'map_x', 'map_y'])]
class County extends Model
{
    /** @use HasFactory<CountyFactory> */
    use HasFactory, HasUuids, Searchable, SoftDeletes;

    /** @return array<string, int|string|null> */
    #[SearchUsingPrefix(['code', 'name'])]
    public function toSearchableArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'region' => $this->region,
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** @return HasMany<Assessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** @return HasMany<AssessmentDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(AssessmentDocument::class);
    }

    /** @return HasMany<CountyGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(CountyGrant::class);
    }

    /** @return HasMany<ProgrammeCountyCoverage, $this> */
    public function programmeCoverages(): HasMany
    {
        return $this->hasMany(ProgrammeCountyCoverage::class);
    }

    /** @return HasMany<SubCounty, $this> */
    public function subCounties(): HasMany
    {
        return $this->hasMany(SubCounty::class);
    }

    protected function casts(): array
    {
        return ['code' => 'integer', 'logo_verified_at' => 'immutable_date', 'map_x' => 'float', 'map_y' => 'float'];
    }

    /** @return array{kind: string, id: string, code: int, name: string, logoUrl: string|null, officialWebsiteUrl: string|null, logoSourceUrl: string|null, logoSourceAuthority: string|null, logoSourceChecksum: string|null, logoChecksum: string|null, logoVerifiedAt: string|null} */
    public function identityCell(): array
    {
        return [
            'kind' => 'county',
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'logoUrl' => $this->logo_path,
            'officialWebsiteUrl' => $this->official_website_url,
            'logoSourceUrl' => $this->logo_source_url,
            'logoSourceAuthority' => $this->logo_source_authority,
            'logoSourceChecksum' => $this->logo_source_checksum_sha256,
            'logoChecksum' => $this->logo_checksum_sha256,
            'logoVerifiedAt' => $this->logo_verified_at?->toDateString(),
        ];
    }
}
