<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BusinessCalendarHolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $business_calendar_id
 * @property CarbonImmutable $holiday_date
 * @property string $name
 * @property string $category
 * @property string $source_reference
 * @property string $created_by
 */
#[Fillable(['business_calendar_id', 'holiday_date', 'name', 'category', 'source_reference', 'created_by'])]
class BusinessCalendarHoliday extends Model
{
    /** @use HasFactory<BusinessCalendarHolidayFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['category' => 'public_holiday'];

    protected function casts(): array
    {
        return ['holiday_date' => 'immutable_date'];
    }

    /** @return BelongsTo<BusinessCalendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(BusinessCalendar::class, 'business_calendar_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
