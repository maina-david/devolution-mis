<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BusinessCalendarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property int $version
 * @property string $name
 * @property string $timezone
 * @property list<int> $working_days
 * @property string $workday_starts_at
 * @property string $workday_ends_at
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string $status
 * @property string $created_by
 * @property string|null $published_by
 * @property CarbonImmutable|null $published_at
 * @property string|null $checksum
 */
#[Fillable(['code', 'version', 'name', 'timezone', 'working_days', 'workday_starts_at', 'workday_ends_at', 'effective_from', 'effective_to', 'status', 'created_by', 'published_by', 'published_at', 'checksum'])]
class BusinessCalendar extends Model
{
    /** @use HasFactory<BusinessCalendarFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['working_days' => 'array', 'version' => 'integer', 'effective_from' => 'immutable_date', 'effective_to' => 'immutable_date', 'published_at' => 'immutable_datetime'];
    }

    /** @return HasMany<BusinessCalendarHoliday, $this> */
    public function holidays(): HasMany
    {
        return $this->hasMany(BusinessCalendarHoliday::class)->orderBy('holiday_date');
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
}
