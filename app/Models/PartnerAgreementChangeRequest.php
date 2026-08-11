<?php

namespace App\Models;

use Database\Factories\PartnerAgreementChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $partner_agreement_id
 * @property int $version
 * @property string $change_type
 * @property array<string, mixed> $proposed_changes
 * @property string $reason
 * @property Carbon $effective_on
 * @property string $requested_by
 * @property Carbon $requested_at
 * @property string|null $predecessor_checksum
 * @property string $request_checksum
 * @property-read PartnerAgreement $agreement
 * @property-read User $requester
 * @property-read PartnerAgreementChangeDecision|null $decision
 */
#[Fillable(['partner_agreement_id', 'version', 'change_type', 'proposed_changes', 'reason', 'effective_on', 'requested_by', 'requested_at', 'predecessor_checksum', 'request_checksum'])]
class PartnerAgreementChangeRequest extends Model
{
    /** @use HasFactory<PartnerAgreementChangeRequestFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['proposed_changes' => 'array', 'effective_on' => 'immutable_date', 'requested_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PartnerAgreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PartnerAgreement::class, 'partner_agreement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasOne<PartnerAgreementChangeDecision, $this> */
    public function decision(): HasOne
    {
        return $this->hasOne(PartnerAgreementChangeDecision::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
