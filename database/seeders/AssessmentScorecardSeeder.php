<?php

namespace Database\Seeders;

use App\Actions\PublishAssessmentScorecardVersion;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentCycle;
use App\Models\AssessmentFunction;
use App\Models\AssessmentScorecard;
use App\Models\AssessmentScorecardVersion;
use App\Models\AssessmentStandard;
use App\Models\AssessmentThematicArea;
use App\Models\CriterionEvidenceRequirement;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AssessmentScorecardSeeder extends Seeder
{
    /**
     * Seed the governed ACPA scorecard, its fourteen constitutional functions,
     * evidence requirements and the KDSP II assessment calendar.
     */
    public function run(PublishAssessmentScorecardVersion $publishVersion): void
    {
        $publisher = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        if (! $publisher) {
            throw new RuntimeException('The devolution administrator must be seeded before the ACPA scorecard.');
        }

        $scorecard = AssessmentScorecard::query()->updateOrCreate(
            ['code' => 'KDSP-II-ACPA'],
            [
                'name' => 'KDSP II Annual Capacity and Performance Assessment',
                'description' => 'Digital assessment baseline for minimum conditions and county performance under the Second Kenya Devolution Support Program (P180935).',
                'status' => 'active',
            ],
        );

        $version = AssessmentScorecardVersion::query()->firstOrCreate(
            ['assessment_scorecard_id' => $scorecard->id, 'version' => 1],
            [
                'status' => 'draft',
                'change_notes' => 'Initial governed baseline covering all fourteen county functions and KDSP II evidence controls.',
                'calculation_method' => 'mcda',
                'mcda_configuration' => ['normalization' => 'percentage', 'aggregation' => 'weighted_sum', 'missing_data' => 'incomplete'],
                'performance_thresholds' => [
                    ['label' => 'Exceeds standard', 'minimum' => 85, 'maximum' => 100],
                    ['label' => 'Meets standard', 'minimum' => 70, 'maximum' => 84.9999],
                    ['label' => 'Needs improvement', 'minimum' => 0, 'maximum' => 69.9999],
                ],
                'effective_from' => '2024-07-01 00:00:00+03',
            ],
        );

        if ($version->status === 'draft') {
            $this->seedStructure($version);
            $publishVersion->handle($version, $publisher);
        }

        $this->seedCycles($version->refresh());
    }

    private function seedStructure(AssessmentScorecardVersion $version): void
    {
        foreach ($this->devolvedFunctions() as $index => $definition) {
            $number = $index + 1;
            $code = sprintf('F%02d', $number);
            $function = AssessmentFunction::query()->updateOrCreate(
                ['assessment_scorecard_version_id' => $version->id, 'code' => $code],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'function_type' => 'devolved',
                    'weight' => $number === 14 ? 7.1423 : 7.1429,
                    'sequence' => $number,
                ],
            );
            $theme = AssessmentThematicArea::query()->updateOrCreate(
                ['assessment_function_id' => $function->id, 'code' => "{$code}-KRA"],
                [
                    'name' => $definition['theme'],
                    'description' => 'Institutional capacity, accountable resource use and demonstrated service-delivery results.',
                    'weight' => 100,
                    'sequence' => 1,
                ],
            );
            $standard = AssessmentStandard::query()->updateOrCreate(
                ['assessment_thematic_area_id' => $theme->id, 'code' => "{$code}-S01"],
                [
                    'name' => 'Approved plan, budget implementation and results reporting',
                    'description' => 'Confirms that the county has approved instruments, implementation records and traceable results for the assessed function.',
                    'norm_reference' => 'Constitution of Kenya 2010, Fourth Schedule; County Governments Act 2012; Public Finance Management Act 2012; applicable KDSP II Project Operations Manual and verification protocol.',
                    'weight' => 100,
                    'sequence' => 1,
                ],
            );
            $criterion = AssessmentCriterion::query()->updateOrCreate(
                ['assessment_standard_id' => $standard->id, 'code' => "{$code}-C01"],
                [
                    'name' => 'Verified institutional compliance and service-delivery result',
                    'description' => 'Primary evidence demonstrates an approved plan, budget execution, public accountability and an attributable service result.',
                    'weight' => 100,
                    'maximum_score' => 100,
                    'scoring_method' => 'scale',
                    'formula' => ['type' => 'linear', 'minimum' => 0, 'maximum' => 100],
                    'thresholds' => [['label' => 'Meets standard', 'minimum' => 70]],
                    'is_mandatory' => true,
                    'sequence' => 1,
                ],
            );
            CriterionEvidenceRequirement::query()->updateOrCreate(
                ['assessment_criterion_id' => $criterion->id, 'code' => "{$code}-E01"],
                [
                    'name' => $definition['evidence'],
                    'description' => 'Signed or otherwise authenticated primary county record, with document date, responsible office and verification trail.',
                    'minimum_documents' => 1,
                    'allowed_categories' => ['policy', 'plan', 'budget', 'report', 'audit', 'register', 'minutes'],
                    'accepted_mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'],
                    'requires_verification' => true,
                    'is_mandatory' => true,
                ],
            );
        }
    }

    private function seedCycles(AssessmentScorecardVersion $version): void
    {
        $cycles = [
            ['ACPA-2023-24', 'FY 2023/24 ACPA baseline', '2023-07-01', '2024-06-30', '2024-01-08 08:00:00+03', '2024-03-29 17:00:00+03', 'closed'],
            ['ACPA-2024-25', 'FY 2024/25 Annual Performance Assessment', '2024-07-01', '2025-06-30', '2025-01-06 08:00:00+03', '2025-03-31 17:00:00+03', 'closed'],
            ['ACPA-2025-26', 'FY 2025/26 Annual Performance Assessment', '2025-07-01', '2026-06-30', '2026-01-05 08:00:00+03', '2026-08-31 17:00:00+03', 'open'],
            ['ACPA-2026-27', 'FY 2026/27 Annual Performance Assessment', '2026-07-01', '2027-06-30', '2027-01-04 08:00:00+03', '2027-03-31 17:00:00+03', 'planned'],
        ];

        foreach ($cycles as [$code, $name, $start, $end, $opens, $closes, $status]) {
            AssessmentCycle::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => 'KDSP II county assessment cycle for minimum conditions, performance measures, supporting evidence and independent verification.',
                    'assessment_scorecard_version_id' => $version->id,
                    'period_start' => $start,
                    'period_end' => $end,
                    'submission_opens_at' => $opens,
                    'submission_closes_at' => $closes,
                    'status' => $status,
                ],
            );
        }
    }

    /** @return list<array{name: string, description: string, theme: string, evidence: string}> */
    private function devolvedFunctions(): array
    {
        return [
            ['name' => 'Agriculture', 'description' => 'Crop and animal husbandry, livestock sale yards, county abattoirs, plant and animal disease control and fisheries.', 'theme' => 'Agricultural productivity and accountable extension services', 'evidence' => 'Approved agriculture work plan, extension register and results report'],
            ['name' => 'County health services', 'description' => 'County health facilities, pharmacies, ambulance services, primary health care and food licensing.', 'theme' => 'Accessible, accountable county health services', 'evidence' => 'Health sector plan, facility service register and performance report'],
            ['name' => 'Pollution and public nuisance control', 'description' => 'Control of air, noise and other public nuisances within county jurisdiction.', 'theme' => 'Environmental health compliance and enforcement', 'evidence' => 'Inspection register, enforcement record and compliance report'],
            ['name' => 'Cultural activities and public amenities', 'description' => 'Cultural activities, public entertainment and county recreational amenities.', 'theme' => 'Inclusive cultural services and public amenities', 'evidence' => 'Approved programme, participation register and facility report'],
            ['name' => 'County transport', 'description' => 'County roads, street lighting, traffic and parking, public road transport and ferries.', 'theme' => 'Safe and reliable county transport infrastructure', 'evidence' => 'Roads work plan, contract progress certificate and completion report'],
            ['name' => 'Animal control and welfare', 'description' => 'Licensing of dogs and facilities for the accommodation, care and burial of animals.', 'theme' => 'Animal welfare regulation and service coverage', 'evidence' => 'Licensing register, inspection report and service record'],
            ['name' => 'Trade development and regulation', 'description' => 'Markets, trade licences, fair trading, local tourism and cooperative societies.', 'theme' => 'Transparent trade regulation and market development', 'evidence' => 'Trade licensing register, market plan and revenue reconciliation'],
            ['name' => 'County planning and development', 'description' => 'Statistics, land survey and mapping, boundaries, housing and electricity and gas reticulation.', 'theme' => 'Integrated planning, budgeting and monitoring', 'evidence' => 'Approved CIDP or ADP, budget linkage matrix and implementation report'],
            ['name' => 'Pre-primary education and vocational training', 'description' => 'Pre-primary education, village polytechnics, homecraft centres and childcare facilities.', 'theme' => 'Quality early learning and vocational skills delivery', 'evidence' => 'Education plan, institution register and enrolment or completion report'],
            ['name' => 'Natural resources and environmental conservation', 'description' => 'Implementation of national policies on soil and water conservation and forestry.', 'theme' => 'Climate-resilient natural resource management', 'evidence' => 'Environmental action plan, safeguard screening and implementation report'],
            ['name' => 'County public works and services', 'description' => 'Storm-water management in built-up areas and county water and sanitation services.', 'theme' => 'Reliable public works, water and sanitation services', 'evidence' => 'Works plan, inspection certificate and service coverage report'],
            ['name' => 'Firefighting and disaster management', 'description' => 'Firefighting services and disaster management within the county.', 'theme' => 'Preparedness, response capacity and business continuity', 'evidence' => 'Disaster plan, equipment inventory and incident or drill report'],
            ['name' => 'Control of drugs and pornography', 'description' => 'County responsibilities for local control and public protection measures.', 'theme' => 'Coordinated prevention, compliance and public protection', 'evidence' => 'Approved prevention plan, activity register and outcome report'],
            ['name' => 'Community participation in county governance', 'description' => 'Ensuring and coordinating participation of communities and locations in county governance.', 'theme' => 'Civic education, public participation and accountability', 'evidence' => 'Public notice, attendance register, minutes and participation feedback report'],
        ];
    }
}
