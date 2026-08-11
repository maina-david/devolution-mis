<?php

namespace App\Models;

use Database\Factories\EvaluationFindingActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $evaluation_finding_id
 * @property string $accountable_owner_id
 * @property string $created_by
 * @property string $code
 * @property string $title
 * @property string $description
 * @property string $success_indicator
 * @property string $target
 * @property Carbon $due_at
 * @property string $weight_percentage
 * @property string $progress_percentage
 * @property string $status
 * @property string $checksum
 * @property-read EvaluationFinding $finding
 * @property-read User $owner
 */
#[Fillable(['evaluation_finding_id', 'accountable_owner_id', 'created_by', 'code', 'title', 'description', 'success_indicator', 'target', 'due_at', 'weight_percentage', 'progress_percentage', 'status', 'checksum'])]
class EvaluationFindingAction extends Model
{
    /** @use HasFactory<EvaluationFindingActionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['progress_percentage' => 0, 'status' => 'open'];

    protected function casts(): array
    {
        return ['due_at' => 'date', 'weight_percentage' => 'decimal:2', 'progress_percentage' => 'decimal:2'];
    }

    /** @return BelongsTo<EvaluationFinding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(EvaluationFinding::class, 'evaluation_finding_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountable_owner_id');
    }

    /** @return HasMany<EvaluationFindingActionUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(EvaluationFindingActionUpdate::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
