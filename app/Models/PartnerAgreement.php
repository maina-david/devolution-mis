<?php

namespace App\Models;

use Database\Factories\PartnerAgreementFactory;
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
 * @property string $partner_profile_id
 * @property string|null $workflow_instance_id
 * @property string $status
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $approved_at
 * @property-read Collection<int, PartnerAgreementChangeRequest> $changeRequests
 */
#[Fillable(['partner_profile_id', 'workflow_instance_id', 'reference', 'title', 'agreement_type', 'starts_on', 'ends_on', 'committed_value', 'currency', 'summary', 'document_reference', 'status', 'created_by', 'approved_by', 'approved_at'])]
class PartnerAgreement extends Model
{
    /** @use HasFactory<PartnerAgreementFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'committed_value' => 'decimal:2', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PartnerProfile, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }

    /** @return HasMany<PartnerAgreementChangeRequest, $this> */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(PartnerAgreementChangeRequest::class)->latest('version');
    }
}
