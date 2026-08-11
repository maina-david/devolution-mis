<?php

namespace Database\Seeders;

use App\Actions\CreateDevolutionProject;
use App\Enums\UserRole;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\Organization;
use App\Models\PartnerProfile;
use App\Models\Sector;
use App\Models\User;
use App\Services\PartnerOverlapAnalyzer;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Seeder;

class PartnerCoordinationSeeder extends Seeder
{
    public function run(CreateDevolutionProject $createProject, PartnerOverlapAnalyzer $analyzer): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $administrator = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        $representative = User::query()->where('email', 'partner@idmis.test')->first();
        if (! $administrator || ! $representative) {
            return;
        }

        $this->call(ProjectWorkflowSeeder::class);

        $sector = Sector::query()->firstOrCreate(['code' => 'WASH'], ['name' => 'Water, sanitation and irrigation', 'description' => 'Water security and county service delivery.', 'is_active' => true]);
        $counties = County::query()->whereIn('name', ['Mombasa', 'Kwale', 'Kilifi'])->get();
        if ($counties->isEmpty()) {
            return;
        }

        $project = DevolutionProject::query()->where('code', 'PIM-COAST-WASH-01')->first();
        if (! $project) {
            $leadCounty = $counties->firstOrFail();
            $project = $createProject->handle($administrator, [
                'code' => 'PIM-COAST-WASH-01',
                'title' => 'Coastal county water resilience programme',
                'description' => 'Multi-county investment in climate-resilient water services and institutional capacity.',
                'sector_id' => $sector->id,
                'lead_county_id' => $leadCounty->id,
                'county_ids' => $counties->pluck('id')->all(),
                'planned_start_date' => '2026-09-01',
                'planned_end_date' => '2029-06-30',
                'approved_budget' => 850000000,
                'currency' => ReferenceCatalogue::defaultCurrency(),
                'funding_source' => 'Development cooperation',
                'indicator_ids' => [],
                'climate_risk_screening' => ['rating' => 'high', 'notes' => 'Drought, saline intrusion and flood exposure.'],
            ]);
        }

        $partners = collect([
            ['code' => 'ORG-DP-001', 'name' => 'County Resilience Development Partnership', 'type' => 'multilateral', 'email' => 'coordination@crdp.example.org'],
            ['code' => 'ORG-DP-002', 'name' => 'Coastal Services Cooperation Facility', 'type' => 'bilateral', 'email' => 'programme@cscf.example.org'],
        ])->map(function (array $definition) use ($administrator, $representative, $sector, $counties): PartnerProfile {
            $organization = Organization::query()->firstOrCreate(['code' => $definition['code']], ['name' => $definition['name'], 'type' => 'development_partner', 'email' => $definition['email'], 'status' => 'active']);
            $partner = PartnerProfile::query()->firstOrCreate(['organization_id' => $organization->id], [
                'partner_type' => $definition['type'], 'country' => ReferenceCatalogue::defaultCountryName(), 'focal_point_name' => 'Partnership Coordination Lead', 'focal_point_email' => $definition['email'],
                'strategic_priorities' => 'Climate resilience, institutional capacity and inclusive county services.', 'modalities' => ['grant', 'technical_assistance'], 'status' => 'active', 'created_by' => $administrator->id,
            ]);
            $partner->counties()->syncWithoutDetaching($counties->pluck('id'));
            $partner->sectors()->syncWithoutDetaching([$sector->id]);
            $partner->users()->syncWithoutDetaching([$representative->id => ['relationship_role' => 'authorized_representative']]);

            return $partner;
        });

        foreach ($partners as $index => $partner) {
            $partner->agreements()->firstOrCreate(['reference' => 'MOU-COAST-'.($index + 1)], [
                'title' => 'Coastal resilience cooperation framework', 'agreement_type' => 'mou', 'starts_on' => '2026-07-01', 'ends_on' => '2029-06-30',
                'committed_value' => 425000000, 'currency' => ReferenceCatalogue::defaultCurrency(), 'summary' => 'Cooperation framework for coordinated investment, technical support and learning.', 'status' => 'active', 'created_by' => $administrator->id,
            ]);
            $partner->contributions()->firstOrCreate([
                'devolution_project_id' => $project->id, 'financial_year' => '2026/2027', 'contribution_type' => $index === 0 ? 'grant' : 'technical_assistance',
            ], [
                'committed_amount' => 425000000, 'disbursed_amount' => 85000000, 'in_kind_value' => $index === 0 ? 0 : 15000000, 'currency' => ReferenceCatalogue::defaultCurrency(),
                'description' => 'Initial financing and technical delivery contribution.', 'status' => 'disbursing', 'reported_by' => $representative->id,
                'provenance' => ['source_system' => 'IDMIS demonstration baseline', 'captured_at' => now()->toIso8601String(), 'captured_by' => $representative->id],
            ]);
        }

        $analyzer->analyze();
    }
}
