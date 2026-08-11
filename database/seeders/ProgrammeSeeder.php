<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorObservation;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProjectBudgetLine;
use App\Models\ProjectMilestone;
use App\Models\ProjectProgressUpdate;
use App\Models\ProjectRisk;
use App\Models\Sector;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $administrator = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $organization = Organization::query()->where('name', 'State Department for Devolution — WASH Secretariat')->first();
        $sector = Sector::query()->where('name', 'Water, sanitation and irrigation')->first();
        $project = DevolutionProject::query()->where('code', 'PIM-COAST-WASH-01')->first();
        if (! $administrator || ! $organization || ! $sector || ! $project) {
            throw new RuntimeException('Partner coordination reference data must be seeded before the programme scenario.');
        }

        $programme = Programme::query()->updateOrCreate(['code' => 'KDSP-II'], [
            'name' => 'Second Kenya Devolution Support Program',
            'description' => 'Four-year programme strengthening county performance in financing, management, coordination and accountability for resources.',
            'lead_organization_id' => $organization->id,
            'sector_id' => $sector->id,
            'starts_on' => '2024-07-01',
            'ends_on' => '2028-06-30',
            'status' => 'active',
            'budget_amount' => 84_000_000_000,
            'currency' => ReferenceCatalogue::defaultCurrency(),
        ]);
        $project->update(['programme_id' => $programme->id]);

        ProjectMilestone::query()->updateOrCreate(['devolution_project_id' => $project->id, 'code' => 'MS-01'], ['title' => 'County safeguards and engineering designs approved', 'description' => 'Environmental and social screening, designs and county approvals completed.', 'planned_start_date' => '2025-07-01', 'planned_end_date' => '2025-12-31', 'actual_start_date' => '2025-07-08', 'actual_end_date' => '2025-12-19', 'weight' => 30, 'progress' => 100, 'status' => 'completed', 'owner_id' => $administrator->id, 'dependencies' => []]);
        ProjectMilestone::query()->updateOrCreate(['devolution_project_id' => $project->id, 'code' => 'MS-02'], ['title' => 'Priority water infrastructure delivered', 'description' => 'Contracted works completed, inspected and recorded in the investment register.', 'planned_start_date' => '2026-01-01', 'planned_end_date' => '2026-12-31', 'actual_start_date' => '2026-01-12', 'weight' => 50, 'progress' => 64, 'status' => 'in_progress', 'owner_id' => $administrator->id, 'dependencies' => ['MS-01']]);
        ProjectMilestone::query()->updateOrCreate(['devolution_project_id' => $project->id, 'code' => 'MS-03'], ['title' => 'Operations, maintenance and beneficiary monitoring established', 'description' => 'Asset registers, maintenance arrangements and service monitoring operational.', 'planned_start_date' => '2026-10-01', 'planned_end_date' => '2027-06-30', 'weight' => 20, 'progress' => 15, 'status' => 'in_progress', 'owner_id' => $administrator->id, 'dependencies' => ['MS-02']]);

        foreach ([
            ['WORKS-2526', 'works', 'Water infrastructure construction and rehabilitation', 420_000_000, 318_000_000, 201_000_000, '2025/26'],
            ['SAFEGUARDS-2526', 'consulting', 'Environmental, social and occupational safety assurance', 24_000_000, 18_600_000, 15_200_000, '2025/26'],
            ['METERING-2627', 'goods', 'Bulk meters, telemetry and service monitoring equipment', 86_000_000, 41_500_000, 12_000_000, '2026/27'],
        ] as [$code, $category, $description, $approved, $committed, $actual, $year]) {
            ProjectBudgetLine::query()->updateOrCreate(['devolution_project_id' => $project->id, 'code' => $code, 'financial_year' => $year], ['category' => $category, 'description' => $description, 'approved_amount' => $approved, 'committed_amount' => $committed, 'actual_amount' => $actual, 'currency' => ReferenceCatalogue::defaultCurrency(), 'funding_source' => 'KDSP II Institutional and Service Delivery Investment Grant']);
        }

        ProjectRisk::query()->updateOrCreate(['devolution_project_id' => $project->id, 'code' => 'RSK-01'], ['category' => 'delivery', 'description' => 'Rainfall and access constraints delay works in remote project locations.', 'probability' => 4, 'impact' => 4, 'residual_probability' => 2, 'residual_impact' => 3, 'mitigation' => 'Sequence works by access window, maintain approved catch-up plans and monitor critical-path milestones monthly.', 'status' => 'mitigating', 'owner_id' => $administrator->id, 'review_due_date' => '2026-09-30']);
        ProjectRisk::query()->updateOrCreate(['devolution_project_id' => $project->id, 'code' => 'RSK-02'], ['category' => 'safeguards', 'description' => 'Community grievances or incomplete land-access documentation affect work sites.', 'probability' => 3, 'impact' => 5, 'residual_probability' => 2, 'residual_impact' => 3, 'mitigation' => 'Verify site access before mobilization, disclose grievance channels and close safeguards actions before certification.', 'status' => 'mitigating', 'owner_id' => $administrator->id, 'review_due_date' => '2026-09-30']);

        ProjectProgressUpdate::query()->updateOrCreate(['devolution_project_id' => $project->id, 'reporting_date' => '2026-06-30'], ['physical_progress' => 58, 'financial_progress' => 44, 'narrative' => 'Design and safeguard packages are complete; priority works are progressing across approved sites.', 'achievements' => 'All first-phase designs approved and contractor mobilization completed.', 'challenges' => 'Weather-related access constraints affected two sites.', 'next_steps' => 'Complete delayed civil works and commission bulk meters.', 'provenance' => ['source_system' => 'IDMIS county project register', 'captured_at' => '2026-07-10T10:00:00+03:00'], 'verification_status' => 'verified', 'verification_rationale' => 'Progress certificates and site-monitoring records reconciled.', 'submitted_by' => $administrator->id, 'verified_by' => $administrator->id, 'verified_at' => '2026-07-15 11:00:00+03']);

        $indicator = IndicatorDefinition::query()->updateOrCreate(['code' => 'KDSP-ISDIG-SERVICE-COVERAGE'], ['name' => 'County investment grant service-delivery completion', 'description' => 'Percentage of planned service-delivery investment outputs completed and independently evidenced.', 'sector_id' => $sector->id, 'programme_id' => $programme->id, 'results_level' => 'outcome', 'unit_of_measure' => 'percent', 'value_type' => 'number', 'direction' => 'increase', 'frequency' => 'quarterly', 'disaggregation_dimensions' => ['county'], 'calculation_formula' => ['numerator' => 'completed outputs', 'denominator' => 'planned outputs', 'multiplier' => 100], 'data_source' => 'County project registers, progress certificates and IDMIS evidence repository', 'verification_method' => 'Document review, source reconciliation and sampled physical verification', 'version' => 1, 'change_summary' => 'Initial KDSP II programme results indicator.', 'status' => 'approved', 'effective_from' => '2025-07-01 00:00:00+03', 'created_by' => $administrator->id, 'approved_by' => $administrator->id, 'approved_at' => '2025-07-01 09:00:00+03']);
        $project->indicators()->syncWithoutDetaching([$indicator->id => ['is_primary' => true]]);

        County::query()->orderBy('code')->each(function (County $county) use ($indicator, $programme, $administrator): void {
            IndicatorObservation::query()->updateOrCreate(['indicator_definition_id' => $indicator->id, 'county_id' => $county->id, 'programme_id' => $programme->id, 'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'measure_type' => 'actual', 'dimension_key' => 'total'], ['numeric_value' => 48 + ($county->code % 39), 'source_reference' => "IDMIS-KDSP-Q4-{$county->code}", 'provenance' => ['source_system' => 'IDMIS county results register', 'captured_at' => '2026-07-10T10:00:00+03:00'], 'quality_status' => 'passed', 'quality_issues' => [], 'verification_status' => 'verified', 'submitted_by' => $administrator->id, 'verified_by' => $administrator->id, 'submitted_at' => '2026-07-10 10:00:00+03', 'verified_at' => '2026-07-18 10:00:00+03']);
        });
    }
}
