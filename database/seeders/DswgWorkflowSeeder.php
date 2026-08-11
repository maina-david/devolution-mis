<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class DswgWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $publisher = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        if (! $publisher) {
            return;
        }

        $this->publish($publisher, 'DSWG-MEETING-LIFECYCLE', 'DSWG meeting lifecycle', [
            'initial_state' => 'scheduled', 'states' => ['scheduled', 'minutes_pending', 'closed'], 'terminal_states' => ['closed'],
            'state_slas' => ['scheduled' => 720, 'minutes_pending' => 120], 'start_permission' => ProgrammePermission::ManageDswg->value,
            'transitions' => [
                ['name' => 'record_outcomes', 'from' => 'scheduled', 'to' => 'minutes_pending', 'permission' => ProgrammePermission::ManageDswg->value, 'rules' => [['field' => 'minutes_present', 'operator' => 'eq', 'value' => true], ['field' => 'quorum_met', 'operator' => 'eq', 'value' => true]], 'sla_hours' => 120],
                ['name' => 'approve_minutes', 'from' => 'minutes_pending', 'to' => 'closed', 'permission' => ProgrammePermission::ManageDswg->value, 'separation_from' => ['record_outcomes'], 'terminal' => true],
            ], 'rules' => [],
        ]);
        $this->publish($publisher, 'DSWG-ACTION-LIFECYCLE', 'DSWG accountable action lifecycle', [
            'initial_state' => 'open', 'states' => ['open', 'in_progress', 'completion_review', 'completed'], 'terminal_states' => ['completed'],
            'state_slas' => ['open' => 168, 'in_progress' => 720, 'completion_review' => 120], 'start_permission' => ProgrammePermission::ManageDswgActions->value,
            'transitions' => [
                ['name' => 'start', 'from' => 'open', 'to' => 'in_progress', 'permission' => ProgrammePermission::ManageDswgActions->value],
                ['name' => 'update_progress', 'from' => 'in_progress', 'to' => 'in_progress', 'permission' => ProgrammePermission::ManageDswgActions->value],
                ['name' => 'submit_completion', 'from' => 'in_progress', 'to' => 'completion_review', 'permission' => ProgrammePermission::ManageDswgActions->value, 'rules' => [['field' => 'progress_percentage', 'operator' => 'gte', 'value' => 100], ['field' => 'completion_evidence_present', 'operator' => 'eq', 'value' => true]], 'sla_hours' => 120],
                ['name' => 'verify', 'from' => 'completion_review', 'to' => 'completed', 'permission' => ProgrammePermission::VerifyDswgActions->value, 'separation_from' => ['submit_completion'], 'terminal' => true],
                ['name' => 'reject', 'from' => 'completion_review', 'to' => 'in_progress', 'permission' => ProgrammePermission::VerifyDswgActions->value, 'separation_from' => ['submit_completion']],
            ], 'rules' => [],
        ]);
    }

    /** @param array<string, mixed> $configuration */
    private function publish(User $publisher, string $code, string $name, array $configuration): void
    {
        $definition = WorkflowDefinition::query()->updateOrCreate(['code' => $code], ['name' => $name, 'module' => 'dswg', 'description' => $name.' with configured SLAs, rules and separation of duties.', 'status' => 'active']);
        if ($definition->versions()->where('status', 'published')->exists()) {
            return;
        }
        $version = $definition->versions()->create(['version' => 1, 'configuration' => $configuration]);
        app(PublishWorkflowVersion::class)->handle($version, $publisher);
    }
}
