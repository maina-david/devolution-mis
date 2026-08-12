<?php

namespace App\Models;

use Database\Factories\UatCampaignFactory;
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
 * @property string $reference_data_release_id
 * @property string $created_by
 * @property string $code
 * @property string $name
 * @property string $objective
 * @property string $environment
 * @property string $status
 * @property list<string> $acceptance_criteria
 * @property list<string> $required_roles
 * @property int $minimum_counties
 * @property-read User $creator
 * @property-read ReferenceDataRelease $referenceDataRelease
 * @property-read Collection<int, County> $counties
 */
#[Fillable(['reference_data_release_id', 'created_by', 'code', 'name', 'objective', 'environment', 'starts_on', 'ends_on', 'status', 'acceptance_criteria', 'required_roles', 'minimum_counties'])]
class UatCampaign extends Model
{
    /** @use HasFactory<UatCampaignFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'planning', 'minimum_counties' => 1];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'acceptance_criteria' => 'array', 'required_roles' => 'array', 'minimum_counties' => 'integer'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class)->withPivot(['participation_status', 'participation_note'])->withTimestamps();
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(UatScenario::class);
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(UatAcceptance::class);
    }
}
