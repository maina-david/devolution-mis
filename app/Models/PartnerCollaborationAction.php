<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PartnerCollaborationActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $partner_collaboration_plan_id
 * @property string $county_id
 * @property string $code
 * @property string $title
 * @property string $description
 * @property string $accountable_user_id
 * @property string|null $accountable_organization_id
 * @property string|null $reference_data_release_id
 * @property string $status
 * @property string $progress_percentage
 * @property string $created_by
 * @property CarbonImmutable $due_on
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $escalated_at
 * @property int $evidence_count
 * @property-read PartnerCollaborationPlan $plan
 * @property-read County $county
 * @property-read User $accountableUser
 * @property-read Organization|null $accountableOrganization
 * @property-read ReferenceDataRelease|null $referenceDataRelease
 * @property-read Collection<int, PartnerCollaborationActionUpdate> $updates
 * @property-read Collection<int, DocumentLink> $documentLinks
 */
#[Fillable(['partner_collaboration_plan_id', 'county_id', 'code', 'title', 'description', 'accountable_user_id', 'accountable_organization_id', 'reference_data_release_id', 'due_on', 'progress_percentage', 'status', 'created_by', 'verified_at', 'reminder_sent_at', 'escalated_at'])]
class PartnerCollaborationAction extends Model
{
    /** @use HasFactory<PartnerCollaborationActionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'open', 'progress_percentage' => 0];

    protected function casts(): array
    {
        return ['due_on' => 'immutable_date', 'progress_percentage' => 'decimal:2', 'verified_at' => 'immutable_datetime', 'reminder_sent_at' => 'immutable_datetime', 'escalated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PartnerCollaborationPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PartnerCollaborationPlan::class, 'partner_collaboration_plan_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function accountableUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountable_user_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function accountableOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'accountable_organization_id');
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return HasMany<PartnerCollaborationActionUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(PartnerCollaborationActionUpdate::class)->latest('submitted_at');
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
