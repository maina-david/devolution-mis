<?php

namespace App\Models;

use Database\Factories\UatFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uat_execution_id', 'raised_by', 'owner_id', 'resolved_by', 'verified_by', 'severity', 'title', 'description', 'status', 'due_on', 'resolution', 'resolved_at', 'verified_at'])]
class UatFinding extends Model
{
    /** @use HasFactory<UatFindingFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'resolved_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime'];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(UatExecution::class, 'uat_execution_id');
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
