<?php

namespace App\Actions;

use App\Models\DevolutionInnovation;
use App\Models\InnovationExperimentMilestone;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInnovationExperimentMilestone
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DevolutionInnovation $innovation, User $actor, array $attributes): InnovationExperimentMilestone
    {
        return DB::transaction(function () use ($innovation, $actor, $attributes): InnovationExperimentMilestone {
            $innovation = DevolutionInnovation::query()->lockForUpdate()->findOrFail($innovation->id);
            abort_unless($actor->canAccessCounty($innovation->county), 403);
            if ($innovation->status !== 'incubating') {
                throw ValidationException::withMessages(['innovation' => 'Pilot milestones must be defined during incubation.']);
            }
            $owner = User::query()->whereKey($attributes['owner_id'])->sole();
            abort_unless($owner->canAccessCounty($innovation->county), 422, 'The milestone owner is outside the innovation county scope.');
            $milestone = InnovationExperimentMilestone::create([...$attributes, 'devolution_innovation_id' => $innovation->id, 'status' => 'planned', 'verification_decision' => 'pending']);
            $this->auditLogger->record($actor, $milestone, 'knowledge.innovation.milestone-created', "Pilot milestone {$milestone->title} defined for {$innovation->reference}.", $innovation->county_id, ['owner_id' => $owner->id, 'due_at' => $milestone->due_at?->toDateString()]);

            return $milestone->refresh();
        });
    }
}
