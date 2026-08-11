<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DocumentDispositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $assessment_document_id
 * @property string $requested_by
 * @property string|null $reviewed_by
 * @property string|null $executed_by
 * @property string $action
 * @property string $reason
 * @property string $authority_reference
 * @property CarbonImmutable $retention_due_at
 * @property CarbonImmutable $scheduled_for
 * @property string $status
 * @property string|null $decision_reason
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $execution_started_at
 * @property CarbonImmutable|null $executed_at
 * @property list<array{disk: string, path: string, checksum: string, size_bytes: int}>|null $object_manifest
 * @property string|null $manifest_checksum
 * @property int $object_count
 * @property int $total_bytes
 * @property string|null $execution_error
 * @property-read AssessmentDocument $document
 * @property-read User $requester
 * @property-read User|null $reviewer
 * @property-read User|null $executor
 */
#[Fillable(['assessment_document_id', 'requested_by', 'reviewed_by', 'executed_by', 'action', 'reason', 'authority_reference', 'retention_due_at', 'scheduled_for', 'status', 'decision_reason', 'reviewed_at', 'execution_started_at', 'executed_at', 'object_manifest', 'manifest_checksum', 'object_count', 'total_bytes', 'execution_error'])]

class DocumentDisposition extends Model
{
    /** @use HasFactory<DocumentDispositionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['retention_due_at' => 'date', 'scheduled_for' => 'date', 'reviewed_at' => 'immutable_datetime', 'execution_started_at' => 'immutable_datetime', 'executed_at' => 'immutable_datetime', 'object_manifest' => 'array', 'object_count' => 'integer', 'total_bytes' => 'integer'];
    }

    /** @return BelongsTo<AssessmentDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AssessmentDocument::class, 'assessment_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
