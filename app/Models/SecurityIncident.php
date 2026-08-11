<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SecurityIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reported_by
 * @property string $incident_lead_id
 * @property string|null $closed_by
 * @property string $reference
 * @property string $record_type
 * @property string $playbook
 * @property string $title
 * @property string $summary
 * @property array<int, string> $affected_services
 * @property string $data_exposure
 * @property string $severity
 * @property string $status
 * @property string|null $business_impact
 * @property string|null $external_reference
 * @property array<int, string>|null $exercise_objectives
 * @property string $exercise_outcome
 * @property CarbonImmutable $detected_at
 * @property CarbonImmutable $acknowledgement_due_at
 * @property CarbonImmutable $containment_due_at
 * @property CarbonImmutable|null $acknowledged_at
 * @property CarbonImmutable|null $contained_at
 * @property CarbonImmutable|null $eradicated_at
 * @property CarbonImmutable|null $recovered_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable $last_transition_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $escalated_at
 * @property CarbonImmutable|null $next_exercise_due_at
 * @property string|null $root_cause
 * @property string|null $corrective_actions
 * @property string|null $lessons_learned
 * @property User $reporter
 * @property User $incidentLead
 * @property User|null $closer
 * @property Collection<int, SecurityIncidentEvent> $events
 * @property Collection<int, DocumentLink> $documentLinks
 * @property int $events_count
 */
#[Fillable(['reported_by', 'incident_lead_id', 'closed_by', 'reference', 'record_type', 'playbook', 'title', 'summary', 'affected_services', 'data_exposure', 'severity', 'status', 'business_impact', 'external_reference', 'exercise_objectives', 'exercise_outcome', 'detected_at', 'acknowledgement_due_at', 'containment_due_at', 'acknowledged_at', 'contained_at', 'eradicated_at', 'recovered_at', 'closed_at', 'last_transition_at', 'reminder_sent_at', 'escalated_at', 'next_exercise_due_at', 'root_cause', 'corrective_actions', 'lessons_learned'])]
class SecurityIncident extends Model
{
    /** @use HasFactory<SecurityIncidentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'detected', 'exercise_outcome' => 'not_assessed'];

    protected function casts(): array
    {
        return [
            'summary' => 'encrypted',
            'business_impact' => 'encrypted',
            'root_cause' => 'encrypted',
            'corrective_actions' => 'encrypted',
            'lessons_learned' => 'encrypted',
            'affected_services' => 'array',
            'exercise_objectives' => 'array',
            'detected_at' => 'immutable_datetime',
            'acknowledgement_due_at' => 'immutable_datetime',
            'containment_due_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
            'contained_at' => 'immutable_datetime',
            'eradicated_at' => 'immutable_datetime',
            'recovered_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'last_transition_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
            'next_exercise_due_at' => 'immutable_datetime',
        ];
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
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<SecurityIncidentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SecurityIncidentEvent::class)->orderBy('occurred_at');
    }

    /** @return MorphMany<DocumentLink, $this> */
    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'subject');
    }
}
