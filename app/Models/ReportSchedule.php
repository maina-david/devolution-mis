<?php

namespace App\Models;

use Database\Factories\ReportScheduleFactory;
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
 * @property string|null $approved_by
 * @property string|null $county_id
 * @property string|null $reference_data_release_id
 * @property string $code
 * @property string $name
 * @property string $workspace
 * @property string $format
 * @property string $frequency
 * @property array<string, mixed> $filters
 * @property list<string> $recipient_user_ids
 * @property string $status
 * @property Carbon $next_run_at
 * @property Carbon|null $approved_at
 * @property-read User $creator
 * @property-read User|null $approver
 * @property-read County|null $county
 * @property-read ReferenceDataRelease|null $referenceDataRelease
 * @property-read Collection<int, ReportRun> $runs
 */
#[Fillable(['created_by', 'approved_by', 'county_id', 'reference_data_release_id', 'code', 'name', 'workspace', 'format', 'frequency', 'filters', 'recipient_user_ids', 'status', 'next_run_at', 'approved_at'])]
class ReportSchedule extends Model
{
    /** @use HasFactory<ReportScheduleFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'recipient_user_ids' => 'array', 'next_run_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    /** @return HasMany<ReportRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }
}
