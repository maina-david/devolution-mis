<?php

namespace App\Models;

use Database\Factories\ReconciliationExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon|null $resolved_at */
#[Fillable(['reconciliation_run_id', 'integration_exchange_id', 'county_id', 'assigned_to', 'resolved_by', 'external_reference', 'local_reference', 'exception_type', 'field_name', 'severity', 'expected_value', 'actual_value', 'description', 'status', 'resolution', 'resolved_at'])]
class ReconciliationException extends Model
{
    /** @use HasFactory<ReconciliationExceptionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['expected_value' => 'encrypted', 'actual_value' => 'encrypted', 'resolved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ReconciliationRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    /** @return BelongsTo<IntegrationExchange, $this> */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(IntegrationExchange::class, 'integration_exchange_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
