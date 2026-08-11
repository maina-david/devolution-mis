<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class TravelWorkflowSeeder extends Seeder
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
        $definition = WorkflowDefinition::query()->updateOrCreate(['code' => 'TRAVEL-CLEARANCE-LIFECYCLE'], ['name' => 'Official travel clearance lifecycle', 'module' => 'travel-clearance', 'description' => 'Request, management approval and independent finance clearance with SLA controls.', 'status' => 'active']);
        if ($definition->versions()->where('status', 'published')->exists()) {
            return;
        }
        $version = $definition->versions()->create(['version' => 1, 'configuration' => [
            'initial_state' => 'draft',
            'states' => ['draft', 'manager_review', 'finance_review', 'approved', 'rejected', 'cancelled'],
            'terminal_states' => ['approved', 'rejected', 'cancelled'],
            'state_slas' => ['manager_review' => 48, 'finance_review' => 48],
            'start_permission' => ProgrammePermission::SubmitTravelRequests->value,
            'transitions' => [
                ['name' => 'submit', 'from' => 'draft', 'to' => 'manager_review', 'permission' => ProgrammePermission::SubmitTravelRequests->value, 'rules' => [['field' => 'itinerary_count', 'operator' => 'gte', 'value' => 1]]],
                ['name' => 'manager_approve', 'from' => 'manager_review', 'to' => 'finance_review', 'permission' => ProgrammePermission::ApproveTravelRequests->value, 'separation_from' => ['submit']],
                ['name' => 'manager_reject', 'from' => 'manager_review', 'to' => 'rejected', 'permission' => ProgrammePermission::ApproveTravelRequests->value, 'separation_from' => ['submit'], 'terminal' => true],
                ['name' => 'finance_clear', 'from' => 'finance_review', 'to' => 'approved', 'permission' => ProgrammePermission::FinanceClearTravel->value, 'separation_from' => ['submit', 'manager_approve'], 'rules' => [['field' => 'finance_reference_present', 'operator' => 'eq', 'value' => true]], 'terminal' => true],
                ['name' => 'finance_reject', 'from' => 'finance_review', 'to' => 'rejected', 'permission' => ProgrammePermission::FinanceClearTravel->value, 'separation_from' => ['submit', 'manager_approve'], 'terminal' => true],
                ['name' => 'cancel', 'from' => 'draft', 'to' => 'cancelled', 'permission' => ProgrammePermission::SubmitTravelRequests->value, 'terminal' => true],
            ],
            'rules' => [],
        ]]);
        app(PublishWorkflowVersion::class)->handle($version, $publisher);
    }
}
