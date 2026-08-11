<?php

namespace App\Models;

use Database\Factories\PerformanceCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'period_start', 'period_end', 'goal_setting_deadline', 'midterm_review_deadline', 'final_review_deadline', 'status', 'created_by'])]
class PerformanceCycle extends Model
{
    /** @use HasFactory<PerformanceCycleFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'goal_setting_deadline' => 'immutable_date', 'midterm_review_deadline' => 'immutable_date', 'final_review_deadline' => 'immutable_date'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PerformancePlan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(PerformancePlan::class);
    }
}
