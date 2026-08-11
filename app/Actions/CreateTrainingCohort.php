<?php

namespace App\Actions;

use App\Models\RolloutWave;
use App\Models\TrainingCohort;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTrainingCohort
{
    public function __construct(private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): TrainingCohort
    {
        return DB::transaction(function () use ($actor, $attributes): TrainingCohort {
            $wave = RolloutWave::query()->lockForUpdate()->with('counties:id')->whereKey($attributes['rollout_wave_id'])->firstOrFail();
            if ($wave->reference_data_release_id === null) {
                throw ValidationException::withMessages(['rollout_wave_id' => 'A rollout wave with verified reference-data lineage is required.']);
            }
            $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
            if ($countyId !== null && ! $wave->counties->contains('id', $countyId)) {
                throw ValidationException::withMessages(['county_id' => 'The cohort county must be included in the rollout wave.']);
            }
            if ($countyId !== null && ! $actor->canAccessCounty($wave->counties->firstWhere('id', $countyId))) {
                throw ValidationException::withMessages(['county_id' => 'The cohort county is outside your authorized scope.']);
            }
            $plannedSeats = (int) $wave->cohorts()->sum('seat_capacity');
            if ($plannedSeats + (int) $attributes['seat_capacity'] > $wave->planned_participants) {
                throw ValidationException::withMessages(['seat_capacity' => 'Cohort seats cannot exceed the wave participant plan.']);
            }
            $referenceDataRelease = $this->referenceDataReleaseResolver->forTrainingCohort($countyId, now());
            $cohort = TrainingCohort::create([...$attributes, 'reference_data_release_id' => $referenceDataRelease->id]);
            $this->auditLogger->record($actor, $cohort, 'change-readiness.cohort.created', "Training cohort {$cohort->code} planned.", $cohort->county_id, ['seat_capacity' => $cohort->seat_capacity, 'reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $cohort;
        });
    }
}
