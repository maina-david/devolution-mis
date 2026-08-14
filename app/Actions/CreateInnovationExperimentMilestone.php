<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
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
        abort_unless($actor->can(ProgrammePermission::ManageKnowledge->value), 403, __('knowledge.errors.innovation_milestone_create_unauthorized'));

        return DB::transaction(function () use ($innovation, $actor, $attributes): InnovationExperimentMilestone {
            $innovation = DevolutionInnovation::query()->lockForUpdate()->findOrFail($innovation->id);
            abort_unless($actor->canAccessCounty($innovation->county), 403);
            if ($innovation->status !== 'incubating') {
                throw ValidationException::withMessages(['innovation' => __('knowledge.errors.innovation_milestone_incubation_only')]);
            }
            $owner = User::query()->whereKey($attributes['owner_id'])->sole();
            abort_unless($owner->canAccessCounty($innovation->county), 422, __('knowledge.errors.innovation_milestone_owner_scope'));
            $milestone = InnovationExperimentMilestone::create([...$attributes, 'devolution_innovation_id' => $innovation->id, 'status' => 'planned', 'verification_decision' => 'pending']);
            $this->auditLogger->record($actor, $milestone, 'knowledge.innovation.milestone-created', __('knowledge.audit.innovation_milestone_created', ['title' => $milestone->title, 'reference' => $innovation->reference]), $innovation->county_id, ['owner_id' => $owner->id, 'due_at' => $milestone->due_at?->toDateString()]);

            return $milestone->refresh();
        });
    }
}
