<?php

namespace App\Models;

use Database\Factories\EvaluationFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $programme_evaluation_id
 * @property string|null $county_id
 * @property string $accountable_owner_id
 * @property string $created_by
 * @property string $reference
 * @property string $title
 * @property string $finding
 * @property string $recommendation
 * @property string $severity
 * @property string $status
 * @property string $progress_percentage
 * @property Carbon $due_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $escalated_at
 * @property-read ProgrammeEvaluation $evaluation
 * @property-read County|null $county
 * @property-read User $owner
 * @property-read Collection<int, EvaluationFindingAction> $actions
 */
#[Fillable(['programme_evaluation_id', 'county_id', 'accountable_owner_id', 'created_by', 'closed_by', 'reference', 'title', 'finding', 'recommendation', 'severity', 'status', 'due_at', 'reminder_sent_at', 'escalated_at', 'progress_percentage', 'closure_note', 'closed_at', 'checksum'])]
class EvaluationFinding extends Model
{
    /** @use HasFactory<EvaluationFindingFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'open', 'progress_percentage' => 0];

    protected function casts(): array
    {
        return ['due_at' => 'date', 'reminder_sent_at' => 'immutable_datetime', 'escalated_at' => 'immutable_datetime', 'progress_percentage' => 'decimal:2', 'closed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ProgrammeEvaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ProgrammeEvaluation::class, 'programme_evaluation_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountable_owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<EvaluationFindingUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(EvaluationFindingUpdate::class);
    }

    /** @return HasMany<EvaluationFindingAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(EvaluationFindingAction::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
