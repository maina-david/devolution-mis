<?php

namespace App\Models;

use Database\Factories\AssessmentAppealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $assessment_id @property string $status */
#[Fillable(['assessment_id', 'assessment_criterion_id', 'appellant_id', 'grounds', 'requested_remedy', 'status', 'reviewer_id', 'decision', 'submitted_at', 'decided_at'])]
class AssessmentAppeal extends Model
{
    /** @use HasFactory<AssessmentAppealFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
