<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PrivacyIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $data_asset_id
 * @property string|null $county_id
 * @property string $reported_by
 * @property string $incident_lead_id
 * @property string|null $assessed_by
 * @property string|null $closed_by
 * @property string $reference
 * @property string $title
 * @property string $controller_role
 * @property string $breach_type
 * @property string $description
 * @property array<int, string> $personal_data_categories
 * @property int|null $estimated_data_subjects
 * @property bool $contains_sensitive_data
 * @property string $status
 * @property string $severity
 * @property string $real_risk_of_harm
 * @property CarbonImmutable|null $occurred_at
 * @property CarbonImmutable $discovered_at
 * @property CarbonImmutable|null $controller_notification_due_at
 * @property CarbonImmutable $regulator_notification_due_at
 * @property CarbonImmutable|null $contained_at
 * @property CarbonImmutable|null $assessed_at
 * @property CarbonImmutable|null $regulator_notified_at
 * @property CarbonImmutable|null $data_subjects_notified_at
 * @property CarbonImmutable|null $closed_at
 * @property string|null $containment_actions
 * @property string|null $risk_assessment
 * @property string|null $regulator_notification_reference
 * @property string|null $regulator_delay_reason
 * @property string $subject_notification_decision
 * @property string|null $subject_notification_rationale
 * @property string|null $root_cause
 * @property string|null $remediation_actions
 * @property string|null $closure_evidence_reference
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $escalated_at
 * @property DataAsset|null $dataAsset
 * @property County|null $county
 * @property User $reporter
 * @property User $incidentLead
 * @property User|null $assessor
 * @property User|null $closer
 */
#[Fillable(['data_asset_id', 'county_id', 'reported_by', 'incident_lead_id', 'assessed_by', 'closed_by', 'reference', 'title', 'controller_role', 'breach_type', 'description', 'personal_data_categories', 'estimated_data_subjects', 'contains_sensitive_data', 'status', 'severity', 'real_risk_of_harm', 'occurred_at', 'discovered_at', 'controller_notification_due_at', 'regulator_notification_due_at', 'contained_at', 'assessed_at', 'regulator_notified_at', 'data_subjects_notified_at', 'closed_at', 'containment_actions', 'risk_assessment', 'regulator_notification_reference', 'regulator_delay_reason', 'subject_notification_decision', 'subject_notification_rationale', 'root_cause', 'remediation_actions', 'closure_evidence_reference', 'reminder_sent_at', 'escalated_at'])]
class PrivacyIncident extends Model
{
    /** @use HasFactory<PrivacyIncidentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'reported',
        'severity' => 'unassessed',
        'real_risk_of_harm' => 'undetermined',
        'subject_notification_decision' => 'undetermined',
    ];

    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'personal_data_categories' => 'array',
            'contains_sensitive_data' => 'boolean',
            'occurred_at' => 'immutable_datetime',
            'discovered_at' => 'immutable_datetime',
            'controller_notification_due_at' => 'immutable_datetime',
            'regulator_notification_due_at' => 'immutable_datetime',
            'contained_at' => 'immutable_datetime',
            'assessed_at' => 'immutable_datetime',
            'regulator_notified_at' => 'immutable_datetime',
            'data_subjects_notified_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<DataAsset, $this> */
    public function dataAsset(): BelongsTo
    {
        return $this->belongsTo(DataAsset::class);
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function incidentLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incident_lead_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return MorphMany<DocumentLink, $this> */
    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'subject');
    }
}
