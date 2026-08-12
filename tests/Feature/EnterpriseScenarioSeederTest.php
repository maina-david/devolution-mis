<?php

namespace Tests\Feature;

use App\Models\AccessDelegation;
use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\AssessmentAppeal;
use App\Models\AssessmentCorrectivePlan;
use App\Models\BusinessCalendarHoliday;
use App\Models\CitizenCaseAttachment;
use App\Models\DataSubjectRequest;
use App\Models\DocumentDisposition;
use App\Models\DocumentLegalHold;
use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingUpdate;
use App\Models\IgrResolutionDependency;
use App\Models\IgrResolutionGap;
use App\Models\KnowledgeCommunityReport;
use App\Models\LearningOfflinePackage;
use App\Models\PartnerAgreementChangeDecision;
use App\Models\PartnerAgreementChangeRequest;
use App\Models\PartnerCollaborationActionUpdateDecision;
use App\Models\PartnerContributionReconciliation;
use App\Models\PartnerContributionSourceMatch;
use App\Models\PartnerOperationalAlert;
use App\Models\PerformanceGoalAmendment;
use App\Models\PerformanceGoalAmendmentDecision;
use App\Models\PerformanceReview;
use App\Models\ProgrammeEvaluation;
use App\Models\ProjectProcurement;
use App\Models\ReferenceDataRelease;
use App\Models\ReportRun;
use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEvent;
use App\Models\WorkflowEscalation;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EnterpriseScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_linked_realistic_enterprise_scenarios_idempotently(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->seed(DatabaseSeeder::class);

        $expectedModels = [
            AccessDelegation::class,
            AccessReviewCampaign::class,
            AccessReviewItem::class,
            AssessmentAppeal::class,
            AssessmentCorrectivePlan::class,
            BusinessCalendarHoliday::class,
            CitizenCaseAttachment::class,
            DataSubjectRequest::class,
            DocumentDisposition::class,
            DocumentLegalHold::class,
            EvaluationFinding::class,
            EvaluationFindingUpdate::class,
            IgrResolutionGap::class,
            IgrResolutionDependency::class,
            KnowledgeCommunityReport::class,
            LearningOfflinePackage::class,
            PartnerAgreementChangeDecision::class,
            PartnerAgreementChangeRequest::class,
            PartnerCollaborationActionUpdateDecision::class,
            PartnerContributionReconciliation::class,
            PartnerContributionSourceMatch::class,
            PartnerOperationalAlert::class,
            PerformanceReview::class,
            PerformanceGoalAmendment::class,
            PerformanceGoalAmendmentDecision::class,
            ProgrammeEvaluation::class,
            ProjectProcurement::class,
            ReferenceDataRelease::class,
            ReportRun::class,
            SecurityIncident::class,
            SecurityIncidentEvent::class,
            WorkflowEscalation::class,
        ];

        $initialCounts = collect($expectedModels)->mapWithKeys(fn (string $model): array => [$model => $model::query()->count()]);
        $this->seed(EnterpriseScenarioSeeder::class);

        foreach ($expectedModels as $model) {
            $this->assertGreaterThan(0, $initialCounts[$model], class_basename($model).' scenario was not seeded.');
            $this->assertSame($initialCounts[$model], $model::query()->count(), class_basename($model).' scenario was not seeded idempotently.');
        }

        $this->assertSame('exercise', SecurityIncident::query()->sole()->record_type);
        $this->assertSame('revoked', AccessDelegation::query()->sole()->status);
        $this->assertSame('verified', PartnerCollaborationActionUpdateDecision::query()->sole()->decision);
    }
}
