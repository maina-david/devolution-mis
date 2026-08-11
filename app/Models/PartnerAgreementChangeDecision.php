<?php

namespace App\Models;

use Database\Factories\PartnerAgreementChangeDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $partner_agreement_change_request_id
 * @property string $decision
 * @property string $decision_note
 * @property string $decided_by
 * @property Carbon $decided_at
 * @property string $evidence_checksum
 * @property string $decision_checksum
 * @property array<string, mixed> $snapshot
 * @property-read PartnerAgreementChangeRequest $changeRequest
 * @property-read User $decider
 */
#[Fillable(['partner_agreement_change_request_id', 'decision', 'decision_note', 'decided_by', 'decided_at', 'evidence_checksum', 'decision_checksum', 'snapshot'])]
class PartnerAgreementChangeDecision extends Model
{
    /** @use HasFactory<PartnerAgreementChangeDecisionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime', 'snapshot' => 'array'];
    }

    /** @return BelongsTo<PartnerAgreementChangeRequest, $this> */
    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(PartnerAgreementChangeRequest::class, 'partner_agreement_change_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
