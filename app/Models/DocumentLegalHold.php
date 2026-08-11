<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DocumentLegalHoldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $assessment_document_id
 * @property string $reference
 * @property string $reason
 * @property string $authority
 * @property string|null $placed_by
 * @property CarbonImmutable $placed_at
 * @property string|null $released_by
 * @property CarbonImmutable|null $released_at
 * @property string|null $release_reason
 * @property-read User|null $placer
 * @property-read User|null $releaser
 */
#[Fillable(['assessment_document_id', 'reference', 'reason', 'authority', 'placed_by', 'placed_at', 'released_by', 'released_at', 'release_reason'])]
class DocumentLegalHold extends Model
{
    /** @use HasFactory<DocumentLegalHoldFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['placed_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AssessmentDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AssessmentDocument::class, 'assessment_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function placer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
