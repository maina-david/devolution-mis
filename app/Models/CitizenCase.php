<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CitizenCaseFactory;
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
 * @property string|null $workflow_instance_id
 * @property string $reference
 * @property string $tracking_token_hash
 * @property string|null $intake_reference_data_release_id
 * @property string|null $triage_reference_data_release_id
 * @property string $case_type
 * @property string $category
 * @property string $channel
 * @property string $county_id
 * @property string|null $sector_id
 * @property string $subject
 * @property string $description
 * @property string|null $citizen_name
 * @property string|null $citizen_email
 * @property string|null $citizen_phone
 * @property bool $is_anonymous
 * @property string $preferred_contact
 * @property string|null $accessibility_needs
 * @property bool $consent_given
 * @property string $priority
 * @property string $status
 * @property bool $is_sensitive
 * @property string|null $assigned_to
 * @property CarbonImmutable $first_response_due_at
 * @property CarbonImmutable $resolution_due_at
 * @property CarbonImmutable|null $first_responded_at
 * @property string|null $resolution_summary
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $satisfaction_rating
 * @property CarbonImmutable|null $satisfaction_recorded_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $escalated_at
 * @property int $messages_count
 * @property-read County $county
 * @property-read Sector|null $sector
 * @property-read User|null $assignee
 * @property-read WorkflowInstance|null $workflowInstance
 * @property-read ReferenceDataRelease|null $intakeReferenceDataRelease
 * @property-read ReferenceDataRelease|null $triageReferenceDataRelease
 * @property-read Collection<int, CitizenCaseMessage> $messages
 * @property-read Collection<int, CitizenCaseAttachment> $attachments
 */
#[Fillable(['workflow_instance_id', 'reference', 'tracking_token_hash', 'intake_reference_data_release_id', 'triage_reference_data_release_id', 'case_type', 'category', 'channel', 'county_id', 'sector_id', 'subject', 'description', 'citizen_name', 'citizen_email', 'citizen_phone', 'is_anonymous', 'preferred_contact', 'accessibility_needs', 'consent_given', 'consent_recorded_at', 'privacy_notice_version', 'priority', 'status', 'is_sensitive', 'assigned_to', 'assigned_organization_id', 'first_response_due_at', 'resolution_due_at', 'first_responded_at', 'resolution_summary', 'resolved_at', 'satisfaction_rating', 'satisfaction_comment', 'satisfaction_recorded_at', 'source_metadata', 'reminder_sent_at', 'escalated_at', 'created_by'])]
class CitizenCase extends Model
{
    /** @use HasFactory<CitizenCaseFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $hidden = ['tracking_token_hash', 'citizen_name', 'citizen_email', 'citizen_phone', 'source_metadata'];

    protected $attributes = ['status' => 'received', 'priority' => 'medium', 'is_anonymous' => false, 'is_sensitive' => false];

    protected function casts(): array
    {
        return ['citizen_name' => 'encrypted', 'citizen_email' => 'encrypted', 'citizen_phone' => 'encrypted', 'is_anonymous' => 'boolean', 'consent_given' => 'boolean', 'consent_recorded_at' => 'immutable_datetime', 'is_sensitive' => 'boolean', 'first_response_due_at' => 'immutable_datetime', 'resolution_due_at' => 'immutable_datetime', 'first_responded_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime', 'satisfaction_rating' => 'integer', 'satisfaction_recorded_at' => 'immutable_datetime', 'source_metadata' => 'array', 'reminder_sent_at' => 'immutable_datetime', 'escalated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<Organization, $this> */
    public function assignedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'assigned_organization_id');
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function intakeReferenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class, 'intake_reference_data_release_id');
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function triageReferenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class, 'triage_reference_data_release_id');
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return HasMany<CitizenCaseMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(CitizenCaseMessage::class);
    }

    /** @return HasMany<CitizenCaseAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(CitizenCaseAttachment::class);
    }
}
