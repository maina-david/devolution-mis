<?php

namespace App\Models;

use Database\Factories\TravelRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $departure_date
 * @property Carbon $return_date
 * @property Carbon|null $decision_due_at
 */
#[Fillable(['workflow_instance_id', 'reference', 'requester_id', 'county_id', 'organization_id', 'sector_id', 'travel_type', 'purpose', 'justification', 'destination_country', 'destination_county', 'destination_city', 'departure_date', 'return_date', 'estimated_cost', 'currency', 'funding_source', 'cost_centre', 'hris_employee_reference', 'finance_commitment_reference', 'integration_status', 'integration_metadata', 'status', 'priority', 'submitted_at', 'decision_due_at', 'reminder_sent_at', 'escalated_at', 'decided_at', 'created_by', 'reference_data_release_id'])]
class TravelRequest extends Model
{
    /** @use HasFactory<TravelRequestFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['departure_date' => 'immutable_date', 'return_date' => 'immutable_date', 'estimated_cost' => 'decimal:2', 'integration_metadata' => 'array', 'submitted_at' => 'immutable_datetime', 'decision_due_at' => 'immutable_datetime', 'reminder_sent_at' => 'immutable_datetime', 'escalated_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** @return BelongsTo<ReferenceDataRelease, $this> */
    public function referenceDataRelease(): BelongsTo
    {
        return $this->belongsTo(ReferenceDataRelease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return HasMany<TravelItinerary, $this> */
    public function itineraries(): HasMany
    {
        return $this->hasMany(TravelItinerary::class);
    }

    /** @return HasMany<TravelApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(TravelApproval::class);
    }

    /** @return HasMany<DocumentLink, $this> */
    public function documentLinks(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'subject_id')->where('subject_type', $this->getMorphClass());
    }
}
