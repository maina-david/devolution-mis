<?php

namespace Tests\Feature;

use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsFilterView;
use App\Models\AnalyticsWidget;
use App\Models\Assessment;
use App\Models\AssessmentAppeal;
use App\Models\AssessmentAttestation;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentCriterionResult;
use App\Models\AssessmentCycle;
use App\Models\AssessmentDocument;
use App\Models\AssessmentFinding;
use App\Models\AssessmentFunction;
use App\Models\AssessmentResultPublication;
use App\Models\AssessmentScorecard;
use App\Models\AssessmentScorecardVersion;
use App\Models\AssessmentStandard;
use App\Models\AssessmentThematicArea;
use App\Models\AuditEvent;
use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use App\Models\County;
use App\Models\CountyGrant;
use App\Models\CriterionEvidenceRequirement;
use App\Models\DatabaseNotification;
use App\Models\DocumentExtractionAttempt;
use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\EvaluationFindingActionUpdate;
use App\Models\EvaluationFindingUpdate;
use App\Models\IgrForumMeeting;
use App\Models\IgrGapCategory;
use App\Models\IgrResolutionDependency;
use App\Models\IgrResolutionGap;
use App\Models\IntegrationExchangeAttempt;
use App\Models\LearningQuestionBank;
use App\Models\LearningQuestionBankItem;
use App\Models\Organization;
use App\Models\PartnerCollaborationAction;
use App\Models\PartnerCollaborationPlan;
use App\Models\PartnerOperationalAlert;
use App\Models\Passkey;
use App\Models\PerformanceGoalAmendment;
use App\Models\PerformanceGoalAmendmentDecision;
use App\Models\PerformanceGoalVersion;
use App\Models\PerformanceTestRun;
use App\Models\Permission;
use App\Models\PlatformSetting;
use App\Models\Programme;
use App\Models\ProjectIndicatorResult;
use App\Models\QueueRecoveryAttempt;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\Role;
use App\Models\Sector;
use App\Models\ServiceDeskPolicy;
use App\Models\ServiceDeskRosterMember;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowEscalation;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UuidAndSoftDeleteSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_schema_uses_only_owning_table_migrations(): void
    {
        $incrementalMigrations = glob(database_path('migrations/*_add_*')) ?: [];

        $this->assertSame([], $incrementalMigrations, 'Development schema changes must be folded into their owning create-table migrations.');
        $this->assertTrue(Schema::hasColumns('support_tickets', [
            'service_desk_policy_id',
            'service_desk_policy_checksum',
        ]));
    }

    public function test_application_models_use_uuid_primary_keys_and_soft_deletes(): void
    {
        $models = [
            User::class,
            Passkey::class,
            County::class,
            Assessment::class,
            AssessmentDocument::class,
            CountyGrant::class,
            Role::class,
            Permission::class,
            DatabaseNotification::class,
            DocumentExtractionAttempt::class,
            PlatformSetting::class,
            Organization::class,
            Sector::class,
            Programme::class,
            EvaluationFinding::class,
            EvaluationFindingAction::class,
            EvaluationFindingActionUpdate::class,
            EvaluationFindingUpdate::class,
            ProjectIndicatorResult::class,
            WorkflowDefinition::class,
            WorkflowVersion::class,
            WorkflowInstance::class,
            WorkflowEscalation::class,
            BusinessCalendar::class,
            BusinessCalendarHoliday::class,
            AssessmentCycle::class,
            AssessmentScorecard::class,
            AssessmentScorecardVersion::class,
            AssessmentFunction::class,
            AssessmentThematicArea::class,
            AssessmentStandard::class,
            AssessmentCriterion::class,
            CriterionEvidenceRequirement::class,
            AssessmentCriterionResult::class,
            AssessmentFinding::class,
            AssessmentAttestation::class,
            AssessmentAppeal::class,
            AnalyticsDashboard::class,
            AnalyticsFilterView::class,
            AnalyticsWidget::class,
            ReportSchedule::class,
            PartnerOperationalAlert::class,
            PartnerCollaborationPlan::class,
            PartnerCollaborationAction::class,
            IgrForumMeeting::class,
            IgrGapCategory::class,
            IgrResolutionDependency::class,
            IgrResolutionGap::class,
            ServiceDeskPolicy::class,
            ServiceDeskRosterMember::class,
            LearningQuestionBank::class,
            LearningQuestionBankItem::class,
        ];

        foreach ($models as $modelClass) {
            $traits = class_uses_recursive($modelClass);

            $this->assertArrayHasKey(HasUuids::class, $traits, "{$modelClass} must use UUIDs.");
            $this->assertArrayHasKey(SoftDeletes::class, $traits, "{$modelClass} must use soft deletes.");

            $model = new $modelClass;
            $this->assertFalse($model->getIncrementing());
            $this->assertSame('string', $model->getKeyType());
            $this->assertTrue(Schema::hasColumn($model->getTable(), 'deleted_at'));
        }
    }

    public function test_audit_events_use_uuids_and_append_only_storage_instead_of_soft_deletes(): void
    {
        $traits = class_uses_recursive(AuditEvent::class);
        $auditEvent = new AuditEvent;

        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayNotHasKey(SoftDeletes::class, $traits);
        $this->assertFalse($auditEvent->getIncrementing());
        $this->assertSame('string', $auditEvent->getKeyType());
        $this->assertFalse(Schema::hasColumn($auditEvent->getTable(), 'deleted_at'));
        $this->assertTrue(Schema::hasColumn($auditEvent->getTable(), 'event_hash'));
    }

    public function test_workflow_transition_history_uses_uuid_and_immutable_storage(): void
    {
        $traits = class_uses_recursive(WorkflowTransition::class);
        $transition = new WorkflowTransition;

        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayNotHasKey(SoftDeletes::class, $traits);
        $this->assertFalse($transition->getIncrementing());
        $this->assertSame('string', $transition->getKeyType());
        $this->assertFalse(Schema::hasColumn($transition->getTable(), 'deleted_at'));
    }

    public function test_published_assessment_results_use_uuid_and_immutable_storage(): void
    {
        $traits = class_uses_recursive(AssessmentResultPublication::class);
        $publication = new AssessmentResultPublication;

        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayNotHasKey(SoftDeletes::class, $traits);
        $this->assertFalse($publication->getIncrementing());
        $this->assertSame('string', $publication->getKeyType());
        $this->assertFalse(Schema::hasColumn($publication->getTable(), 'deleted_at'));
    }

    public function test_report_runs_use_uuid_and_retained_artifact_evidence_storage(): void
    {
        $traits = class_uses_recursive(ReportRun::class);
        $run = new ReportRun;

        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayNotHasKey(SoftDeletes::class, $traits);
        $this->assertFalse($run->getIncrementing());
        $this->assertSame('string', $run->getKeyType());
        $this->assertFalse(Schema::hasColumn($run->getTable(), 'deleted_at'));
    }

    public function test_integration_exchange_attempts_use_uuid_and_immutable_evidence_storage(): void
    {
        $traits = class_uses_recursive(IntegrationExchangeAttempt::class);
        $attempt = new IntegrationExchangeAttempt;

        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayNotHasKey(SoftDeletes::class, $traits);
        $this->assertFalse($attempt->getIncrementing());
        $this->assertSame('string', $attempt->getKeyType());
        $this->assertFalse(Schema::hasColumn($attempt->getTable(), 'deleted_at'));
    }

    public function test_queue_recovery_attempts_use_uuid_and_immutable_evidence_storage(): void
    {
        $traits = class_uses_recursive(QueueRecoveryAttempt::class);
        $attempt = new QueueRecoveryAttempt;

        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayNotHasKey(SoftDeletes::class, $traits);
        $this->assertFalse($attempt->getIncrementing());
        $this->assertSame('string', $attempt->getKeyType());
        $this->assertFalse(Schema::hasColumn($attempt->getTable(), 'deleted_at'));
    }

    public function test_performance_test_runs_use_uuid_and_immutable_evidence_storage(): void
    {
        $traits = class_uses_recursive(PerformanceTestRun::class);
        $run = new PerformanceTestRun;

        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayNotHasKey(SoftDeletes::class, $traits);
        $this->assertFalse($run->getIncrementing());
        $this->assertSame('string', $run->getKeyType());
        $this->assertFalse(Schema::hasColumn($run->getTable(), 'deleted_at'));
    }

    public function test_performance_goal_governance_history_uses_uuid_and_immutable_evidence_storage(): void
    {
        foreach ([PerformanceGoalVersion::class, PerformanceGoalAmendment::class, PerformanceGoalAmendmentDecision::class] as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $model = new $modelClass;

            $this->assertArrayHasKey(HasUuids::class, $traits);
            $this->assertArrayNotHasKey(SoftDeletes::class, $traits);
            $this->assertFalse($model->getIncrementing());
            $this->assertSame('string', $model->getKeyType());
            $this->assertFalse(Schema::hasColumn($model->getTable(), 'deleted_at'));
        }
    }

    public function test_created_application_records_receive_uuid_version_seven_keys(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id]);
        $grant = CountyGrant::factory()->create(['county_id' => $county->id]);

        foreach ([$county, $user, $assessment, $document, $grant] as $model) {
            $this->assertTrue(Str::isUuid($model->getKey()));
            $this->assertSame('7', $model->getKey()[14]);
        }
    }

    public function test_deleted_domain_records_are_retained_for_audit_and_hidden_by_default(): void
    {
        $county = County::factory()->create();
        $county->delete();

        $this->assertNull(County::query()->find($county->id));
        $this->assertNotNull(County::withTrashed()->find($county->id));
        $this->assertSoftDeleted($county);
    }
}
