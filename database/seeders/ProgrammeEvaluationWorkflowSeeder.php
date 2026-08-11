<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class ProgrammeEvaluationWorkflowSeeder extends Seeder
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
        $definition = WorkflowDefinition::query()->updateOrCreate(['code' => 'PROGRAMME-EVALUATION-LIFECYCLE'], ['name' => 'Programme evaluation lifecycle', 'module' => 'monitoring-evaluation', 'description' => 'Evidence-gated programme evaluation delivery and independent approval.', 'status' => 'active']);
        if ($definition->versions()->where('status', 'published')->exists()) {
            return;
        }
        $version = $definition->versions()->create(['version' => 1, 'configuration' => [
            'initial_state' => 'planned', 'states' => ['planned', 'in_progress', 'review', 'approved'], 'terminal_states' => ['approved'],
            'state_slas' => ['planned' => 168, 'in_progress' => 720, 'review' => 120], 'start_permission' => ProgrammePermission::ManageIndicators->value,
            'transitions' => [
                ['name' => 'start', 'from' => 'planned', 'to' => 'in_progress', 'permission' => ProgrammePermission::ManageIndicators->value, 'rules' => [['field' => 'terms_of_reference_present', 'operator' => 'eq', 'value' => true]], 'sla_hours' => 720],
                ['name' => 'submit_review', 'from' => 'in_progress', 'to' => 'review', 'permission' => ProgrammePermission::ManageIndicators->value, 'rules' => [['field' => 'evaluation_report_present', 'operator' => 'eq', 'value' => true]], 'sla_hours' => 120],
                ['name' => 'approve', 'from' => 'review', 'to' => 'approved', 'permission' => ProgrammePermission::VerifyIndicatorData->value, 'separation_from' => ['submit_review'], 'terminal' => true],
                ['name' => 'return', 'from' => 'review', 'to' => 'in_progress', 'permission' => ProgrammePermission::VerifyIndicatorData->value, 'separation_from' => ['submit_review']],
            ], 'rules' => [],
        ]]);
        app(PublishWorkflowVersion::class)->handle($version, $publisher);
    }
}
