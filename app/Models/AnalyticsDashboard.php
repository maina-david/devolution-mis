<?php

namespace App\Models;

use Database\Factories\AnalyticsDashboardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $created_by
 * @property string|null $published_by
 * @property string|null $county_id
 * @property string|null $reference_data_release_id
 * @property string $code
 * @property string $name
 * @property string $description
 * @property list<string> $audience_roles
 * @property string $status
 * @property string|null $checksum
 * @property Carbon|null $published_at
 * @property-read User $creator
 * @property-read User|null $publisher
 * @property-read County|null $county
 * @property-read ReferenceDataRelease|null $referenceDataRelease
 * @property-read Collection<int, AnalyticsWidget> $widgets
 */
#[Fillable(['created_by', 'published_by', 'county_id', 'reference_data_release_id', 'code', 'name', 'description', 'audience_roles', 'status', 'checksum', 'published_at'])]
class AnalyticsDashboard extends Model
{
    /** @use HasFactory<AnalyticsDashboardFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['audience_roles' => 'array', 'published_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return HasMany<AnalyticsWidget, $this> */
    public function widgets(): HasMany
    {
        return $this->hasMany(AnalyticsWidget::class);
    }
}
