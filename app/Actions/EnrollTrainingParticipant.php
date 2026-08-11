<?php

namespace App\Actions;

use App\Models\TrainingCohort;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollTrainingParticipant
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): TrainingParticipant
    {
        return DB::transaction(function () use ($actor, $attributes): TrainingParticipant {
            $cohort = TrainingCohort::query()->lockForUpdate()->whereKey($attributes['training_cohort_id'])->firstOrFail();
            if ($cohort->participants()->count() >= $cohort->seat_capacity) {
                throw ValidationException::withMessages(['training_cohort_id' => 'This cohort has reached its approved seat capacity.']);
            }
            if ($cohort->county_id && $attributes['county_id'] !== $cohort->county_id) {
                throw ValidationException::withMessages(['county_id' => 'The participant must belong to the cohort county.']);
            }
            $participant = TrainingParticipant::create($attributes);
            $this->auditLogger->record($actor, $participant, 'change-readiness.participant.registered', 'Training participant registered; this is not attendance or competency evidence.', $participant->county_id);

            return $participant;
        });
    }
}
