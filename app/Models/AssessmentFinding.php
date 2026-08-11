<?php

namespace App\Models;

use Database\Factories\AssessmentFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $assessment_id
 * @property string $code
 * @property string $severity
 * @property string $status
 * @property string|null $county_response
 */
#[Fillable(['assessment_id', 'assessment_criterion_id', 'code', 'severity', 'status', 'title', 'description', 'county_response', 'resolution', 'raised_by', 'assigned_to', 'resolved_by', 'response_due_at', 'responded_at', 'resolved_at'])]
class AssessmentFinding extends Model
{
    /** @use HasFactory<AssessmentFindingFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['response_due_at' => 'immutable_datetime', 'responded_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
