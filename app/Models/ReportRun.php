<?php

namespace App\Models;

use Database\Factories\ReportRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $report_schedule_id
 * @property string|null $triggered_by
 * @property string $status
 * @property array<string, mixed> $filter_snapshot
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $sha256
 * @property int|null $record_count
 * @property string|null $error_detail
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read ReportSchedule $schedule
 * @property-read User|null $trigger
 */
#[Fillable(['report_schedule_id', 'triggered_by', 'status', 'filter_snapshot', 'period_from', 'period_to', 'disk', 'path', 'mime_type', 'size_bytes', 'sha256', 'record_count', 'error_detail', 'started_at', 'completed_at'])]
class ReportRun extends Model
{
    /** @use HasFactory<ReportRunFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'queued'];

    protected function casts(): array
    {
        return ['filter_snapshot' => 'array', 'period_from' => 'immutable_datetime', 'period_to' => 'immutable_datetime', 'size_bytes' => 'integer', 'record_count' => 'integer', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ReportSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'report_schedule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
