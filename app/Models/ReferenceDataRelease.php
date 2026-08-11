<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReferenceDataReleaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property int $version
 * @property string $submitted_by
 * @property string|null $approved_by
 * @property string $status
 * @property string $change_summary
 * @property array<string, list<array<string, mixed>>> $snapshot
 * @property string $checksum
 * @property string|null $approval_reference
 * @property CarbonImmutable|null $effective_from
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $published_at
 * @property-read User $submitter
 * @property-read User|null $approver
 */
#[Fillable(['version', 'submitted_by', 'approved_by', 'status', 'change_summary', 'snapshot', 'checksum', 'approval_reference', 'effective_from', 'submitted_at', 'published_at'])]
class ReferenceDataRelease extends Model
{
    /** @use HasFactory<ReferenceDataReleaseFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'effective_from' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
