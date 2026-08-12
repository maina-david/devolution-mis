<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, CountySeeder::class]);
        $this->call(LocalAccessSeeder::class);
        $this->call(AssessmentScorecardSeeder::class);
        $this->call(DemoProgrammeSeeder::class);
        $this->call(ReferenceDataReleaseSeeder::class);
        $this->call(PlatformSettingSeeder::class);
        $this->call(ProjectWorkflowSeeder::class);
        $this->call(ProgrammeEvaluationWorkflowSeeder::class);
        $this->call(PartnerAgreementWorkflowSeeder::class);
        $this->call(PartnerCoordinationSeeder::class);
        $this->call(DswgWorkflowSeeder::class);
        $this->call(DswgCoordinationSeeder::class);
        $this->call(ProgrammeSeeder::class);
        $this->call(ReferenceDataReleaseSeeder::class);
        $this->call(IgrWorkflowSeeder::class);
        $this->call(IgrResolutionSeeder::class);
        $this->call(CitizenCaseWorkflowSeeder::class);
        $this->call(CitizenCaseSeeder::class);
        $this->call(TravelWorkflowSeeder::class);
        $this->call(TravelClearanceSeeder::class);
        $this->call(PerformanceWorkflowSeeder::class);
        $this->call(DepartmentalPerformanceSeeder::class);
        $this->call(PerformanceGoalVersionBackfillSeeder::class);
        $this->call(LearningWorkflowSeeder::class);
        $this->call(LearningSeeder::class);
        $this->call(KnowledgeWorkflowSeeder::class);
        $this->call(KnowledgeManagementSeeder::class);
        $this->call(IntegrationCatalogueSeeder::class);
        $this->call(DataGovernanceSeeder::class);
        $this->call(SecurityThreatSeeder::class);
        $this->call(AnalyticsReportingSeeder::class);
        $this->call(SupportDeskSeeder::class);
        $this->call(EnterpriseScenarioSeeder::class);
        $this->call(ServiceDeskPolicySeeder::class);
    }
}
