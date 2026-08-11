<?php

namespace App\Models;

use Database\Factories\ExchequerRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $county_grant_id
 * @property string $county_id
 * @property string|null $reference_data_release_id
 * @property string $created_by
 * @property string $request_reference
 * @property string $tranche_reference
 * @property string $financial_year
 * @property string $amount
 * @property string $currency
 * @property string $current_stage
 * @property string $status
 * @property Carbon|null $stage_due_at
 * @property Carbon|null $last_event_at
 * @property Carbon|null $credited_at
 */
#[Fillable(['county_grant_id', 'county_id', 'reference_data_release_id', 'created_by', 'request_reference', 'tranche_reference', 'financial_year', 'amount', 'currency', 'current_stage', 'status', 'stage_due_at', 'last_event_at', 'credited_at'])]
class ExchequerRequest extends Model
{
    /** @use HasFactory<ExchequerRequestFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'stage_due_at' => 'immutable_datetime', 'last_event_at' => 'immutable_datetime', 'credited_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<CountyGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(CountyGrant::class, 'county_grant_id');
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ExchequerEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ExchequerEvent::class)->orderBy('occurred_at');
    }
}
