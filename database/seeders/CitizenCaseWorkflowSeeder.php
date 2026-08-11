<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class CitizenCaseWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publisher = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        if (! $publisher) {
            return;
        }
        $this->publish($publisher, 'FEEDBACK-CASE-LIFECYCLE', 'Citizen feedback case lifecycle', 240);
        $this->publish($publisher, 'GRIEVANCE-CASE-LIFECYCLE', 'Grievance redress case lifecycle', 336);
    }

    private function publish(User $publisher, string $code, string $name, int $implementationSlaHours): void
    {
        $definition = WorkflowDefinition::query()->updateOrCreate(['code' => $code], ['name' => $name, 'module' => 'citizen-accountability', 'description' => $name.' with SLA escalation and independent resolution approval.', 'status' => 'active']);
        if ($definition->versions()->where('status', 'published')->exists()) {
            return;
        }
        $version = $definition->versions()->create(['version' => 1, 'configuration' => ['initial_state' => 'new', 'states' => ['new', 'triaged', 'in_progress', 'escalated', 'resolution_review', 'resolved', 'closed'], 'terminal_states' => ['closed'], 'state_slas' => ['new' => 24, 'triaged' => 48, 'in_progress' => $implementationSlaHours, 'escalated' => 72, 'resolution_review' => 72, 'resolved' => 168], 'start_permission' => ProgrammePermission::ManageCitizenCases->value, 'transitions' => [
            ['name' => 'triage', 'from' => 'new', 'to' => 'triaged', 'permission' => ProgrammePermission::ManageCitizenCases->value],
            ['name' => 'start', 'from' => 'triaged', 'to' => 'in_progress', 'permission' => ProgrammePermission::RespondCitizenCases->value],
            ['name' => 'escalate', 'from' => 'in_progress', 'to' => 'escalated', 'permission' => ProgrammePermission::RespondCitizenCases->value],
            ['name' => 'resume', 'from' => 'escalated', 'to' => 'in_progress', 'permission' => ProgrammePermission::ManageCitizenCases->value],
            ['name' => 'submit_resolution', 'from' => 'in_progress', 'to' => 'resolution_review', 'permission' => ProgrammePermission::RespondCitizenCases->value, 'rules' => [['field' => 'resolution_summary_present', 'operator' => 'eq', 'value' => true]], 'sla_hours' => 72],
            ['name' => 'approve_resolution', 'from' => 'resolution_review', 'to' => 'resolved', 'permission' => ProgrammePermission::ResolveCitizenCases->value, 'separation_from' => ['submit_resolution']],
            ['name' => 'reject_resolution', 'from' => 'resolution_review', 'to' => 'in_progress', 'permission' => ProgrammePermission::ResolveCitizenCases->value, 'separation_from' => ['submit_resolution']],
            ['name' => 'close', 'from' => 'resolved', 'to' => 'closed', 'permission' => ProgrammePermission::ManageCitizenCases->value, 'terminal' => true],
        ], 'rules' => []]]);
        app(PublishWorkflowVersion::class)->handle($version, $publisher);
    }
}
