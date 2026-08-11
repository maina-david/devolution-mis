<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PartnerCollaborationActionUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $partner_collaboration_action_id
 * @property string $progress_percentage
 * @property string $narrative
 * @property string $submitted_by
 * @property string|null $evidence_checksum
 * @property string $update_checksum
 * @property CarbonImmutable $submitted_at
 * @property-read PartnerCollaborationAction $action
 * @property-read User $submitter
 * @property-read PartnerCollaborationActionUpdateDecision|null $decision
 */
#[Fillable(['partner_collaboration_action_id', 'progress_percentage', 'narrative', 'submitted_by', 'submitted_at', 'evidence_checksum', 'update_checksum'])]
class PartnerCollaborationActionUpdate extends Model
{
    /** @use HasFactory<PartnerCollaborationActionUpdateFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['progress_percentage' => 'decimal:2', 'submitted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PartnerCollaborationAction, $this> */
    public function action(): BelongsTo
    {
        return $this->belongsTo(PartnerCollaborationAction::class, 'partner_collaboration_action_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return HasOne<PartnerCollaborationActionUpdateDecision, $this> */
    public function decision(): HasOne
    {
        return $this->hasOne(PartnerCollaborationActionUpdateDecision::class);
    }
}
