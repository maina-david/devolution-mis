<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProjectScheduleBaselineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $devolution_project_id
 * @property int $version
 * @property string $status
 * @property list<array<string, mixed>> $schedule_snapshot
 * @property array<string, mixed> $critical_path_analysis
 * @property string $snapshot_checksum
 * @property string $baseline_reason
 * @property string $requested_by
 * @property string|null $decided_by
 * @property string|null $decision_rationale
 * @property string|null $decision_checksum
 * @property CarbonImmutable|null $decided_at
 */
#[Fillable(['devolution_project_id', 'version', 'status', 'schedule_snapshot', 'critical_path_analysis', 'snapshot_checksum', 'baseline_reason', 'requested_by', 'decided_by', 'decision_rationale', 'decision_checksum', 'decided_at'])]
class ProjectScheduleBaseline extends Model
{
    /** @use HasFactory<ProjectScheduleBaselineFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'schedule_snapshot' => 'array', 'critical_path_analysis' => 'array', 'decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<DevolutionProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DevolutionProject::class, 'devolution_project_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
