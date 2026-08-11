<?php

namespace App\Models;

use Database\Factories\PerformanceGoalVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $version
 * @property array<string, string|null> $definition_snapshot
 * @property string|null $predecessor_checksum
 * @property string $version_checksum
 * @property Carbon $effective_at
 */
#[Fillable(['performance_goal_id', 'version', 'definition_snapshot', 'predecessor_checksum', 'version_checksum', 'created_by', 'effective_at'])]
class PerformanceGoalVersion extends Model
{
    /** @use HasFactory<PerformanceGoalVersionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['definition_snapshot' => 'array', 'effective_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PerformanceGoal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoal::class, 'performance_goal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
