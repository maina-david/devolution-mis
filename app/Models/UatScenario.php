<?php

namespace App\Models;

use Database\Factories\UatScenarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uat_campaign_id', 'created_by', 'code', 'module', 'title', 'actor_role', 'priority', 'journey', 'preconditions', 'steps', 'expected_result', 'accessibility_needs', 'low_connectivity_variant', 'required', 'status'])]
class UatScenario extends Model
{
    /** @use HasFactory<UatScenarioFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['priority' => 'normal', 'required' => true, 'status' => 'ready'];

    protected function casts(): array
    {
        return ['preconditions' => 'array', 'steps' => 'array', 'required' => 'boolean'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(UatCampaign::class, 'uat_campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(UatExecution::class);
    }
}
