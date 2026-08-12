<?php

namespace App\Models;

use Database\Factories\UatExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uat_scenario_id', 'county_id', 'tested_by', 'environment', 'outcome', 'actual_result', 'evidence_references', 'started_at', 'completed_at', 'checksum'])]
class UatExecution extends Model
{
    /** @use HasFactory<UatExecutionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['evidence_references' => 'array', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(UatScenario::class, 'uat_scenario_id');
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(UatFinding::class);
    }
}
