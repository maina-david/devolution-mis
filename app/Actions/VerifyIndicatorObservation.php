<?php

namespace App\Actions;

use App\Models\IndicatorObservation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class VerifyIndicatorObservation
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, IndicatorObservation $observation, array $attributes): IndicatorObservation
    {
        abort_unless($actor->canAccessCounty($observation->county), 403);

        if ($actor->id === $observation->submitted_by) {
            throw ValidationException::withMessages(['verification_status' => 'The submitter cannot verify their own observation.']);
        }

        if (($observation->provenance['project_verification_actor_id'] ?? null) === $actor->id) {
            throw ValidationException::withMessages(['verification_status' => 'The project verifier cannot also perform the M&E data-quality verification.']);
        }

        if ($observation->verification_status === 'verified') {
            throw ValidationException::withMessages(['verification_status' => 'A verified observation cannot be changed.']);
        }

        $observation->update([
            'verification_status' => $attributes['verification_status'],
            'quality_status' => $attributes['quality_status'],
            'quality_issues' => $attributes['quality_issues'] ?? null,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);
        $this->auditLogger->record($actor, $observation, 'indicator.observation.verified', 'Indicator observation verification decision recorded.', $observation->county_id, ['rationale' => $attributes['rationale'], 'decision' => $attributes['verification_status']]);

        return $observation->refresh();
    }
}
