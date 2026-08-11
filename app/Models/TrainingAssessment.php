<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TrainingAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $training_participant_id
 * @property string $assessed_by
 * @property string $assessment_type
 * @property float $score
 * @property string $outcome
 * @property string $feedback
 * @property list<string> $evidence_references
 * @property CarbonImmutable $assessed_at
 * @property-read TrainingParticipant $participant
 * @property-read User $assessor
 */
#[Fillable(['training_participant_id', 'assessed_by', 'assessment_type', 'score', 'outcome', 'feedback', 'evidence_references', 'assessed_at'])]
class TrainingAssessment extends Model
{
    /** @use HasFactory<TrainingAssessmentFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'evidence_references' => 'array', 'assessed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<TrainingParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TrainingParticipant::class, 'training_participant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
