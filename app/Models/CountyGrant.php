<?php

namespace App\Models;

use Database\Factories\CountyGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $county_id
 * @property string $financial_year
 * @property string $allocated_amount
 * @property string $disbursed_amount
 */
#[Fillable(['county_id', 'programme', 'financial_year', 'allocated_amount', 'disbursed_amount', 'status'])]
class CountyGrant extends Model
{
    /** @use HasFactory<CountyGrantFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['allocated_amount' => 0, 'disbursed_amount' => 0, 'status' => 'planned'];

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return HasMany<ExchequerRequest, $this> */
    public function exchequerRequests(): HasMany
    {
        return $this->hasMany(ExchequerRequest::class);
    }

    protected function casts(): array
    {
        return ['allocated_amount' => 'decimal:2', 'disbursed_amount' => 'decimal:2'];
    }
}
