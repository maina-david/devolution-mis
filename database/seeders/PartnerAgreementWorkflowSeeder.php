<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class PartnerAgreementWorkflowSeeder extends Seeder
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
        $definition = WorkflowDefinition::query()->updateOrCreate(['code' => 'PARTNER-AGREEMENT-LIFECYCLE'], ['name' => 'Partner agreement approval lifecycle', 'module' => 'partner-coordination', 'description' => 'Document-backed submission and independent approval of partner agreements.', 'status' => 'active']);
        if ($definition->versions()->where('status', 'published')->exists()) {
            return;
        }
        $version = $definition->versions()->create(['version' => 1, 'configuration' => [
            'initial_state' => 'draft', 'states' => ['draft', 'pending_approval', 'active', 'rejected'], 'terminal_states' => ['active', 'rejected'],
            'state_slas' => ['draft' => 120, 'pending_approval' => 72],
            'start_permission' => ProgrammePermission::ManagePartners->value, 'escalation_permission' => ProgrammePermission::ManageWorkflows->value,
            'transitions' => [
                ['name' => 'submit', 'from' => 'draft', 'to' => 'pending_approval', 'permission' => ProgrammePermission::ManagePartners->value, 'rules' => [['field' => 'document_count', 'operator' => 'gte', 'value' => 1]]],
                ['name' => 'approve', 'from' => 'pending_approval', 'to' => 'active', 'permission' => ProgrammePermission::ApprovePartnerAgreements->value, 'separation_from' => ['submit'], 'terminal' => true],
                ['name' => 'reject', 'from' => 'pending_approval', 'to' => 'rejected', 'permission' => ProgrammePermission::ApprovePartnerAgreements->value, 'separation_from' => ['submit'], 'terminal' => true],
            ], 'rules' => [],
        ]]);
        app(PublishWorkflowVersion::class)->handle($version, $publisher);
    }
}
