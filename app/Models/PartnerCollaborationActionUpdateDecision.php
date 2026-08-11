<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PartnerCollaborationActionUpdateDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $partner_collaboration_action_update_id
 * @property string $decision
 * @property string $verification_note
 * @property string $verified_by
 * @property string $decision_checksum
 * @property CarbonImmutable $verified_at
 * @property-read PartnerCollaborationActionUpdate $actionUpdate
 * @property-read User $verifier
 */
#[Fillable(['partner_collaboration_action_update_id', 'decision', 'verification_note', 'verified_by', 'verified_at', 'decision_checksum'])]
class PartnerCollaborationActionUpdateDecision extends Model
{
    /** @use HasFactory<PartnerCollaborationActionUpdateDecisionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PartnerCollaborationActionUpdate, $this> */
    public function actionUpdate(): BelongsTo
    {
        return $this->belongsTo(PartnerCollaborationActionUpdate::class, 'partner_collaboration_action_update_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
