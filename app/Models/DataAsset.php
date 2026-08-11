<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DataAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $data_owner_id
 * @property string|null $steward_id
 * @property string $code
 * @property string $name
 * @property string $description
 * @property string $module
 * @property string $authoritative_source
 * @property string $classification
 * @property bool $contains_personal_data
 * @property bool $contains_sensitive_personal_data
 * @property list<string>|null $personal_data_categories
 * @property list<string>|null $data_subject_categories
 * @property list<string> $storage_locations
 * @property string $residency_country
 * @property string|null $quality_standard
 * @property string $status
 * @property CarbonImmutable|null $reviewed_at
 * @property User|null $dataOwner
 * @property User|null $steward
 * @property int $processing_activities_count
 */
#[Fillable(['data_owner_id', 'steward_id', 'code', 'name', 'description', 'module', 'authoritative_source', 'classification', 'contains_personal_data', 'contains_sensitive_personal_data', 'personal_data_categories', 'data_subject_categories', 'storage_locations', 'residency_country', 'quality_standard', 'status', 'reviewed_at'])]
class DataAsset extends Model
{
    /** @use HasFactory<DataAssetFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['classification' => 'official', 'contains_personal_data' => false, 'contains_sensitive_personal_data' => false, 'status' => 'draft'];

    protected function casts(): array
    {
        return ['contains_personal_data' => 'boolean', 'contains_sensitive_personal_data' => 'boolean', 'personal_data_categories' => 'array', 'data_subject_categories' => 'array', 'storage_locations' => 'array', 'reviewed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function dataOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'data_owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function steward(): BelongsTo
    {
        return $this->belongsTo(User::class, 'steward_id');
    }

    /** @return HasMany<ProcessingActivity, $this> */
    public function processingActivities(): HasMany
    {
        return $this->hasMany(ProcessingActivity::class);
    }
}
