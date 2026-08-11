<?php

namespace App\Models;

use Database\Factories\EvaluationFindingActionUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $evaluation_finding_action_id
 * @property string $assessment_document_id
 * @property string $submitted_by
 * @property string|null $verified_by
 * @property string $progress_percentage
 * @property string $narrative
 * @property string $status
 * @property string|null $decision_note
 * @property Carbon $submitted_at
 * @property Carbon|null $verified_at
 * @property string $checksum
 * @property-read EvaluationFindingAction $action
 * @property-read AssessmentDocument $document
 * @property-read User $submitter
 * @property-read User|null $verifier
 */
#[Fillable(['evaluation_finding_action_id', 'assessment_document_id', 'submitted_by', 'verified_by', 'progress_percentage', 'narrative', 'status', 'decision_note', 'submitted_at', 'verified_at', 'checksum'])]
class EvaluationFindingActionUpdate extends Model
{
    /** @use HasFactory<EvaluationFindingActionUpdateFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'pending_verification'];

    protected function casts(): array
    {
        return ['progress_percentage' => 'decimal:2', 'submitted_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<EvaluationFindingAction, $this> */
    public function action(): BelongsTo
    {
        return $this->belongsTo(EvaluationFindingAction::class, 'evaluation_finding_action_id');
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
