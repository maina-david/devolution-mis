<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AssessmentCorrectiveUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $assessment_corrective_action_id
 * @property string $assessment_document_id
 * @property string $submitted_by
 * @property string|null $verified_by
 * @property string $progress_percentage
 * @property string $narrative
 * @property string $status
 * @property string|null $decision_note
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $verified_at
 * @property string $checksum
 * @property-read AssessmentCorrectiveAction $action
 * @property-read AssessmentDocument $document
 * @property-read User $submitter
 * @property-read User|null $verifier
 */
#[Fillable(['assessment_corrective_action_id', 'assessment_document_id', 'submitted_by', 'verified_by', 'progress_percentage', 'narrative', 'status', 'decision_note', 'submitted_at', 'verified_at', 'checksum'])]
class AssessmentCorrectiveUpdate extends Model
{
    /** @use HasFactory<AssessmentCorrectiveUpdateFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['progress_percentage' => 'decimal:2', 'submitted_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AssessmentCorrectiveAction, $this> */
    public function action(): BelongsTo
    {
        return $this->belongsTo(AssessmentCorrectiveAction::class, 'assessment_corrective_action_id');
    }

    /** @return BelongsTo<AssessmentDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(AssessmentDocument::class, 'assessment_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
