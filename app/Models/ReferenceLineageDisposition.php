<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReferenceLineageDispositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $record_type
 * @property string $record_id
 * @property string $decision
 * @property array<string, mixed> $record_snapshot
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $applied_at
 */
#[Fillable(['reference', 'record_type', 'record_id', 'decision', 'reference_data_release_id', 'successor_record_type', 'successor_record_id', 'record_snapshot', 'record_checksum', 'business_reason', 'source_reference', 'status', 'proposed_by', 'reviewed_by', 'review_notes', 'reviewed_at', 'applied_by', 'applied_at', 'decision_checksum'])]
class ReferenceLineageDisposition extends Model
{
    /** @use HasFactory<ReferenceLineageDispositionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'proposed'];

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    protected function casts(): array
    {
        return [
            'record_snapshot' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
        ];
    }
}
