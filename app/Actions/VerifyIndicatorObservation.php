<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IndicatorObservation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyIndicatorObservation
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, IndicatorObservation $observation, array $attributes): IndicatorObservation
    {
        abort_unless($actor->can(ProgrammePermission::VerifyIndicatorData->value), 403, __('monitoring-results.observation.errors.verify_unauthorized'));

        return DB::transaction(function () use ($actor, $observation, $attributes): IndicatorObservation {
            $observation = IndicatorObservation::query()->with('county')->lockForUpdate()->findOrFail($observation->id);
            abort_unless($actor->canAccessCounty($observation->county), 403, __('monitoring-results.observation.errors.county_scope'));

            if ($actor->id === $observation->submitted_by) {
                throw ValidationException::withMessages(['verification_status' => __('monitoring-results.observation.errors.submitter_cannot_verify')]);
            }

            if (($observation->provenance['project_verification_actor_id'] ?? null) === $actor->id) {
                throw ValidationException::withMessages(['verification_status' => __('monitoring-results.observation.errors.project_verifier_separation')]);
            }

            if ($observation->verification_status === 'verified') {
                throw ValidationException::withMessages(['verification_status' => __('monitoring-results.observation.errors.verified_immutable')]);
            }

            $observation->update([
                'verification_status' => $attributes['verification_status'],
                'quality_status' => $attributes['quality_status'],
                'quality_issues' => $attributes['quality_issues'] ?? null,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);
            $this->auditLogger->record($actor, $observation, 'indicator.observation.verified', __('monitoring-results.observation.audit.verified'), $observation->county_id, ['rationale' => $attributes['rationale'], 'decision' => $attributes['verification_status']]);

            return $observation->refresh();
        });
    }
}
