<?php

namespace App\Actions;

use App\Models\RolloutWave;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TransitionRolloutWave
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(RolloutWave $wave, User $actor, array $attributes): RolloutWave
    {
        if ($wave->created_by === $actor->id) {
            throw new HttpException(403, 'The wave author cannot independently approve rollout readiness.');
        }
        $wave->loadCount(['counties', 'cohorts']);
        $completed = $wave->cohorts()->withCount(['participants as competent_count' => fn ($query) => $query->whereNotNull('completed_at')])->get()->sum('competent_count');
        if (! $wave->help_desk_rehearsed || ! $wave->training_materials_approved || $wave->counties_count === 0 || $wave->cohorts_count === 0 || $completed < $wave->planned_participants) {
            throw ValidationException::withMessages(['status' => 'Readiness approval requires rehearsed help desk, approved materials, target counties, cohorts and competent completion evidence for every planned participant.']);
        }
        $wave->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'readiness_notes' => $attributes['readiness_notes']]);
        $this->auditLogger->record($actor, $wave, 'change-readiness.wave.approved', "Rollout wave {$wave->code} independently approved against recorded evidence.");

        return $wave;
    }
}
