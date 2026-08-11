<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class IgrWorkflowSeeder extends Seeder
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
        $definition = WorkflowDefinition::query()->updateOrCreate(['code' => 'IGR-RESOLUTION-LIFECYCLE'], ['name' => 'IGR resolution lifecycle', 'module' => 'igr', 'description' => 'Resolution implementation, evidence review and independent closure.', 'status' => 'active']);
        if ($definition->versions()->where('status', 'published')->exists()) {
            return;
        }
        $version = $definition->versions()->create(['version' => 1, 'configuration' => [
            'initial_state' => 'open', 'states' => ['open', 'in_progress', 'closure_review', 'closed'], 'terminal_states' => ['closed'],
            'state_slas' => ['open' => 168, 'in_progress' => 720, 'closure_review' => 120], 'start_permission' => ProgrammePermission::ManageIgrResolutions->value,
            'transitions' => [
                ['name' => 'start', 'from' => 'open', 'to' => 'in_progress', 'permission' => ProgrammePermission::UpdateIgrResolutions->value],
                ['name' => 'submit_closure', 'from' => 'in_progress', 'to' => 'closure_review', 'permission' => ProgrammePermission::UpdateIgrResolutions->value, 'rules' => [['field' => 'progress_percentage', 'operator' => 'gte', 'value' => 100], ['field' => 'closure_evidence_present', 'operator' => 'eq', 'value' => true]], 'sla_hours' => 120],
                ['name' => 'approve_closure', 'from' => 'closure_review', 'to' => 'closed', 'permission' => ProgrammePermission::CloseIgrResolutions->value, 'separation_from' => ['submit_closure'], 'terminal' => true],
                ['name' => 'reject_closure', 'from' => 'closure_review', 'to' => 'in_progress', 'permission' => ProgrammePermission::CloseIgrResolutions->value, 'separation_from' => ['submit_closure']],
            ], 'rules' => [],
        ]]);
        app(PublishWorkflowVersion::class)->handle($version, $publisher);
    }
}
