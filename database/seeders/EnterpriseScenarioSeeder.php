<?php

namespace Database\Seeders;

use App\Actions\ActivateReportSchedule;
use App\Actions\CreatePartnerAgreementChange;
use App\Actions\CreateReferenceDataRelease;
use App\Actions\DecidePartnerAgreementChange;
use App\Actions\DecidePerformanceGoalAmendment;
use App\Actions\GenerateLearningOfflinePackage;
use App\Actions\PublishReferenceDataRelease;
use App\Actions\ReconcilePartnerContribution;
use App\Actions\ReconcilePartnerContributionExchanges;
use App\Actions\RequestPerformanceGoalAmendment;
use App\Actions\TransitionPerformancePlan;
use App\Models\AccessDelegation;
use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\Assessment;
use App\Models\AssessmentAppeal;
use App\Models\AssessmentAttestation;
use App\Models\AssessmentCorrectiveAction;
use App\Models\AssessmentCorrectivePlan;
use App\Models\AssessmentCorrectiveUpdate;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentCriterionResult;
use App\Models\AssessmentDocument;
use App\Models\AssessmentFinding;
use App\Models\AssessmentResultPublication;
use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use App\Models\CitizenCase;
use App\Models\CitizenCaseAttachment;
use App\Models\County;
use App\Models\DataSubjectRequest;
use App\Models\DevolutionProject;
use App\Models\DocumentDisposition;
use App\Models\DocumentLegalHold;
use App\Models\DocumentLink;
use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\EvaluationFindingActionUpdate;
use App\Models\EvaluationFindingUpdate;
use App\Models\IgrForum;
use App\Models\IgrForumMeeting;
use App\Models\IgrGapCategory;
use App\Models\IgrResolution;
use App\Models\IgrResolutionDependency;
use App\Models\IgrResolutionGap;
use App\Models\IgrResolutionUpdate;
use App\Models\IndicatorDefinition;
use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use App\Models\IntegrationSystem;
use App\Models\KnowledgeCommunityReport;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeDiscussionSubscription;
use App\Models\KnowledgePost;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningOfflinePackage;
use App\Models\Organization;
use App\Models\PartnerAgreement;
use App\Models\PartnerAgreementChangeRequest;
use App\Models\PartnerCollaborationAction;
use App\Models\PartnerCollaborationActionUpdate;
use App\Models\PartnerCollaborationActionUpdateDecision;
use App\Models\PartnerCollaborationPlan;
use App\Models\PartnerContribution;
use App\Models\PartnerContributionReconciliation;
use App\Models\PartnerContributionSourceMatch;
use App\Models\PartnerOperationalAlert;
use App\Models\PartnerProfile;
use App\Models\PerformanceGoalAmendment;
use App\Models\PerformancePlan;
use App\Models\PerformanceReview;
use App\Models\Programme;
use App\Models\ProgrammeEvaluation;
use App\Models\ProjectIndicatorResult;
use App\Models\ProjectProcurement;
use App\Models\ProjectProgressUpdate;
use App\Models\ReferenceDataRelease;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEvent;
use App\Models\TrainingAssessment;
use App\Models\TrainingCohort;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Models\VirtualClassroom;
use App\Models\VirtualClassroomAttendance;
use App\Models\WorkflowEscalation;
use App\Models\WorkflowInstance;
use App\Services\ScheduledReportGenerator;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class EnterpriseScenarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $administrator = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $countyAdministrator = User::query()->where('email', 'county.admin@idmis.test')->first();

        if (! $administrator || ! $countyAdministrator) {
            return;
        }

        $this->seedBusinessCalendar($administrator);
        $this->seedAssessmentGovernance($administrator, $countyAdministrator);
        $this->seedAccessGovernance($administrator, $countyAdministrator);
        $this->seedProgrammeEvaluation($administrator, $countyAdministrator);
        $this->seedPartnerCoordination($administrator, $countyAdministrator);
        $this->seedPartnerGovernance($administrator);
        $this->seedPartnerSourceReconciliation();
        $this->seedRecordsAndPrivacyRequests($administrator, $countyAdministrator);
        $this->seedPerformanceReview($administrator);
        $this->seedPerformanceGoalAmendment($administrator);
        $this->seedSecurityExercise($administrator);
        $this->seedReferenceDataRelease($administrator);
        $this->seedScheduledReport($administrator);
        $this->seedLearningAndKnowledge($administrator, $countyAdministrator);
        $this->seedLearningOfflinePackage($administrator);
        $this->seedProjectDelivery();
        $this->seedIntergovernmentalRelations($administrator, $countyAdministrator);
    }

    private function seedPerformanceGoalAmendment(User $administrator): void
    {
        $plan = PerformancePlan::query()->with(['goals.versions', 'supervisor'])->first();
        if (! $plan || ! $plan->supervisor) {
            return;
        }

        if ($plan->status === 'draft') {
            $document = $this->governanceDocument(
                $administrator,
                'Signed departmental performance goal plan',
                'departmental-performance/signed-goal-plan.txt',
                "IDMIS GOVERNED DEMONSTRATION RECORD\nThis training record is not represented as an executed staff appraisal.\nPlan: {$plan->id}\nGoals: KPI-01 programme reporting timeliness; KPI-02 county implementation support.\nControl: employee submission and independent supervisor approval.\n",
            );
            DocumentLink::query()->firstOrCreate(
                ['assessment_document_id' => $document->id, 'subject_type' => $plan->getMorphClass(), 'subject_id' => $plan->id],
                ['purpose' => 'performance-goal-plan', 'created_by' => $administrator->id],
            );
            app(TransitionPerformancePlan::class)->handle($plan, $administrator, [
                'transition' => 'submit_goals',
                'rationale' => 'Submit the signed baseline goals for independent supervisor agreement.',
            ]);
            $plan = app(TransitionPerformancePlan::class)->handle($plan->refresh(), $plan->supervisor, [
                'transition' => 'approve_goals',
                'rationale' => 'The signed goals are measurable, aligned to the programme work plan and total one hundred percent.',
            ]);
        }

        if ($plan->status !== 'active' || PerformanceGoalAmendment::query()->where('performance_plan_id', $plan->id)->exists()) {
            return;
        }

        $goal = $plan->goals()->with('versions')->orderBy('code')->firstOrFail();
        $baseVersion = $goal->versions->firstOrFail();
        $amendment = app(RequestPerformanceGoalAmendment::class)->handle($plan, $goal, $administrator, [
            ...$baseVersion->definition_snapshot,
            'target_value' => 97,
            'reason' => 'The approved annual reporting target increased after a documented executive delivery commitment.',
        ]);
        app(DecidePerformanceGoalAmendment::class)->handle($amendment, $plan->supervisor, [
            'decision' => 'approved',
            'rationale' => 'The revised target is measurable and preserves the approved one-hundred-percent goal weighting.',
        ]);
    }

    private function seedPartnerSourceReconciliation(): void
    {
        $actor = User::query()->where('email', 'platform.admin@idmis.test')->first();
        $sourceOperator = User::query()->where('email', 'partner@idmis.test')->first();
        $contribution = PartnerContribution::query()->with('project')->orderBy('id')->first();

        if (! $actor || ! $sourceOperator || ! $contribution || PartnerContributionSourceMatch::query()->exists()) {
            return;
        }

        $system = IntegrationSystem::query()->updateOrCreate(
            ['code' => 'PARTNER-CONTRIBUTION-SANDBOX'],
            [
                'registered_by' => $actor->id,
                'name' => 'Partner contribution statement sandbox',
                'purpose' => 'Validate governed partner contribution exchange and source reconciliation without production connectivity.',
                'system_owner' => 'State Department for Devolution partner coordination unit',
                'environment' => 'sandbox',
                'transport' => 'fixture',
                'auth_scheme' => 'none',
                'direction' => 'inbound',
                'data_classification' => 'official',
                'status' => 'active',
                'health_status' => 'healthy',
                'last_health_check_at' => now(),
                'metadata' => ['production_data' => false, 'purpose' => 'training_and_contract_validation'],
            ],
        );
        $schema = [
            'type' => 'object',
            'required' => ['partner_contribution_id', 'external_reference', 'committed_amount', 'disbursed_amount', 'in_kind_value', 'currency'],
            'properties' => collect(['partner_contribution_id', 'external_reference', 'committed_amount', 'disbursed_amount', 'in_kind_value', 'currency'])->mapWithKeys(fn (string $field): array => [$field => ['type' => 'string']])->all(),
        ];
        $contract = IntegrationContract::query()->updateOrCreate(
            ['integration_system_id' => $system->id, 'version' => 1],
            [
                'submitted_by' => $sourceOperator->id,
                'approved_by' => $actor->id,
                'name' => 'Partner contribution statement exchange',
                'resource_name' => ReconcilePartnerContributionExchanges::ResourceName,
                'http_method' => 'POST',
                'path' => '/sandbox/v1/partner-contributions',
                'request_schema' => $schema,
                'response_schema' => ['type' => 'object', 'required' => ['accepted']],
                'field_mappings' => ['partner_contribution_id' => 'partner_contributions.id'],
                'required_headers' => ['X-Correlation-ID', 'Idempotency-Key'],
                'idempotency_field' => 'external_reference',
                'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => [60, 300, 1800]],
                'rate_limit_per_minute' => 60,
                'status' => 'published',
                'content_checksum' => hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)),
                'source_owner_approval_reference' => 'PARTNER-SBX-APPROVAL-2026-001',
                'data_sharing_agreement_reference' => 'PARTNER-SBX-DSA-2026-001',
                'effective_from' => now()->subDay(),
                'published_at' => now()->subDay(),
            ],
        );
        $payload = [
            'partner_contribution_id' => $contribution->id,
            'external_reference' => 'PARTNER-SBX-SOURCE-2026-001',
            'committed_amount' => (string) $contribution->committed_amount,
            'disbursed_amount' => (string) $contribution->disbursed_amount,
            'in_kind_value' => (string) $contribution->in_kind_value,
            'currency' => $contribution->currency,
        ];
        IntegrationExchange::query()->updateOrCreate(
            ['idempotency_key' => 'PARTNER-SBX-SOURCE-2026-001-v1'],
            [
                'integration_contract_id' => $contract->id,
                'county_id' => $contribution->project->lead_county_id,
                'created_by' => $sourceOperator->id,
                'direction' => 'inbound',
                'correlation_id' => '018f9d58-7a1f-7d31-9d91-2de9c7eb1001',
                'external_reference' => 'PARTNER-SBX-SOURCE-2026-001',
                'request_payload' => $payload,
                'response_payload' => ['accepted' => true],
                'request_headers' => ['X-Correlation-ID' => '018f9d58-7a1f-7d31-9d91-2de9c7eb1001'],
                'payload_checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                'status' => 'succeeded',
                'http_status' => 202,
                'attempt_count' => 1,
                'source_occurred_at' => now()->subHour(),
                'accepted_at' => now()->subHour(),
                'processed_at' => now()->subHour()->addMinute(),
                'completed_at' => now()->subHour()->addMinute(),
            ],
        );

        app(ReconcilePartnerContributionExchanges::class)->handle($contract, $actor, now()->subDay(), now());
    }

    private function seedScheduledReport(User $administrator): void
    {
        $schedule = ReportSchedule::query()->first();
        $approver = User::query()->where('email', 'platform.admin@idmis.test')->first();

        if (! $schedule || ! $approver) {
            return;
        }

        if ($schedule->status === 'draft') {
            app(ActivateReportSchedule::class)->handle($schedule, $approver);
        }

        if (ReportRun::query()->where('report_schedule_id', $schedule->id)->exists()) {
            return;
        }

        $run = $schedule->runs()->create([
            'triggered_by' => $administrator->id,
            'status' => 'queued',
            'filter_snapshot' => $schedule->filters,
            'period_from' => $schedule->filters['from'] ?? null,
            'period_to' => $schedule->filters['to'] ?? null,
        ]);
        app(ScheduledReportGenerator::class)->generate($run);
    }

    private function seedLearningOfflinePackage(User $administrator): void
    {
        $course = LearningCourse::query()->where('status', 'published')->first();
        if (! $course || LearningOfflinePackage::query()->where('learning_course_id', $course->id)->exists()) {
            return;
        }

        app(GenerateLearningOfflinePackage::class)->handle($course, $administrator);
    }

    private function seedRecordsAndPrivacyRequests(User $administrator, User $countyAdministrator): void
    {
        DataSubjectRequest::query()->updateOrCreate(
            ['reference' => 'DSR-DEMO-2026-001'],
            [
                'assigned_to' => $administrator->id,
                'identity_verified_by' => $administrator->id,
                'decided_by' => $administrator->id,
                'request_type' => 'access',
                'requester_name' => 'IDMIS Training Persona',
                'requester_contact' => 'privacy.training@idmis.test',
                'contact_channel' => 'email',
                'scope' => 'Request for a copy of the personal profile and training attendance records held for the governed training persona.',
                'identity_status' => 'verified',
                'identity_evidence_reference' => 'DSR-DEMO-IDV-2026-001',
                'status' => 'completed',
                'received_at' => '2026-07-01 09:00:00+03',
                'due_at' => '2026-07-22 17:00:00+03',
                'acknowledged_at' => '2026-07-01 11:00:00+03',
                'decided_at' => '2026-07-08 14:30:00+03',
                'decision' => 'Access granted for the verified training-persona profile and attendance records.',
                'decision_reason' => 'Identity and scope were verified, and no lawful restriction applied to the requested demonstration records.',
                'response_evidence_reference' => 'DSR-DEMO-RESPONSE-2026-001',
                'metadata' => ['record_class' => 'governed_demonstration', 'official_request' => false],
            ],
        );

        $citizenCase = CitizenCase::query()->first();
        if ($citizenCase) {
            $path = 'citizen-cases/demonstration/site-observation-note.txt';
            $contents = "IDMIS GOVERNED DEMONSTRATION ATTACHMENT\nThis is a training record, not a real citizen submission.\nCase: {$citizenCase->reference}\nObservation: A sampled service point requires clearer public signage showing the responsible office and feedback channel.\n";
            Storage::put($path, $contents);
            CitizenCaseAttachment::query()->updateOrCreate(
                ['citizen_case_id' => $citizenCase->id, 'path' => $path],
                [
                    'title' => 'Demonstration site observation note',
                    'original_name' => 'site-observation-note.txt',
                    'mime_type' => 'text/plain',
                    'size_bytes' => Storage::size($path),
                    'checksum_sha256' => hash('sha256', $contents),
                    'source_type' => 'soft_copy',
                    'scan_status' => 'clean',
                    'scan_details' => ['engine' => 'demonstration-seed', 'result' => 'no executable content'],
                    'ocr_status' => 'not_required',
                    'uploaded_by' => $countyAdministrator->id,
                ],
            );
        }

        $document = AssessmentDocument::query()->where('title', 'Partner agreement renewal decision pack')->first();
        if ($document) {
            DocumentLegalHold::query()->updateOrCreate(
                ['reference' => 'HOLD-DEMO-2026-001'],
                [
                    'assessment_document_id' => $document->id,
                    'reason' => 'Temporary preservation during the controlled partner agreement change review.',
                    'authority' => 'IDMIS demonstration records-control exercise',
                    'placed_by' => $administrator->id,
                    'placed_at' => '2026-07-20 09:00:00+03',
                    'released_by' => $administrator->id,
                    'released_at' => '2026-07-28 15:00:00+03',
                    'release_reason' => 'Independent decision evidence was retained and the temporary review hold was no longer required.',
                ],
            );
            DocumentDisposition::query()->updateOrCreate(
                ['assessment_document_id' => $document->id, 'authority_reference' => 'RET-DEMO-2026-001'],
                [
                    'requested_by' => $administrator->id,
                    'reviewed_by' => $countyAdministrator->id,
                    'action' => 'secure_destroy',
                    'reason' => 'Demonstration request used to validate independent retention review controls.',
                    'retention_due_at' => '2033-08-01',
                    'scheduled_for' => '2033-08-02',
                    'status' => 'rejected',
                    'decision_reason' => 'Rejected because the approved seven-year retention period has not elapsed.',
                    'reviewed_at' => '2026-08-02 10:00:00+03',
                ],
            );
        }

        $workflow = WorkflowInstance::query()->first();
        if ($workflow) {
            WorkflowEscalation::query()->updateOrCreate(
                ['workflow_instance_id' => $workflow->id, 'reason' => 'sla_warning'],
                [
                    'level' => 1,
                    'status' => 'resolved',
                    'escalated_to' => $administrator->id,
                    'state_entered_at' => '2026-07-10 09:00:00+03',
                    'due_at' => '2026-07-12 17:00:00+03',
                    'triggered_at' => '2026-07-12 09:00:00+03',
                    'acknowledged_at' => '2026-07-12 09:20:00+03',
                    'resolved_at' => '2026-07-12 11:00:00+03',
                    'metadata' => ['resolution' => 'The accountable officer supplied the missing workflow evidence before the SLA deadline.'],
                ],
            );
        }
    }

    private function seedPartnerGovernance(User $administrator): void
    {
        $approver = User::query()->where('email', 'management@idmis.test')->first();
        $agreement = PartnerAgreement::query()->where('status', 'active')->orderBy('reference')->first();
        $contribution = PartnerContribution::query()->orderBy('id')->first();

        if (! $approver || ! $agreement || ! $contribution) {
            return;
        }

        $changeRequest = PartnerAgreementChangeRequest::query()->where('partner_agreement_id', $agreement->id)->first();
        if (! $changeRequest) {
            $changeRequest = app(CreatePartnerAgreementChange::class)->handle($agreement, $administrator, [
                'change_type' => 'renewal',
                'title' => $agreement->title,
                'summary' => 'Renewed cooperation framework with quarterly evidence-quality clinics and explicit data-governance deliverables.',
                'ends_on' => '2030-06-30',
                'committed_value' => '425000000.00',
                'reason' => 'Extend coordinated county capacity support through the final KDSP II implementation and handover period.',
                'effective_on' => '2029-07-01',
            ]);
        }

        if (! $changeRequest->decision()->exists()) {
            $document = $this->governanceDocument(
                $administrator,
                'Partner agreement renewal decision pack',
                'partner-governance/agreement-renewal-decision-pack.txt',
                "IDMIS GOVERNED DEMONSTRATION RECORD\nThis training record is not represented as an executed government agreement.\nAgreement: {$agreement->reference}\nChange: Extend the cooperation framework to 30 June 2030.\nControls: independent approval, retained checksum, effective date and immutable decision history.\n",
            );
            DocumentLink::query()->firstOrCreate(
                ['assessment_document_id' => $document->id, 'subject_type' => $changeRequest->getMorphClass(), 'subject_id' => $changeRequest->id],
                ['purpose' => 'partner-agreement-change-evidence', 'created_by' => $administrator->id],
            );
            app(DecidePartnerAgreementChange::class)->handle($changeRequest, $approver, [
                'decision' => 'approved',
                'decision_note' => 'Approved after confirming continuity with KDSP II priorities, funding limits and independent evidence controls.',
            ]);
        }

        if (! PartnerContributionReconciliation::query()->where('partner_contribution_id', $contribution->id)->exists()) {
            $document = $this->governanceDocument(
                $administrator,
                'Certified partner contribution reconciliation statement',
                'partner-governance/contribution-reconciliation-statement.txt',
                "IDMIS GOVERNED DEMONSTRATION RECORD\nThis training record is not represented as an official bank statement.\nContribution: {$contribution->id}\nCommitted: KES 425,000,000.00\nDisbursed: KES 85,000,000.00\nControl: source totals independently reconciled to the recorded partner contribution.\n",
            );
            DocumentLink::query()->firstOrCreate(
                ['assessment_document_id' => $document->id, 'subject_type' => $contribution->getMorphClass(), 'subject_id' => $contribution->id],
                ['purpose' => 'partner-contribution-reconciliation-evidence', 'created_by' => $administrator->id],
            );
            app(ReconcilePartnerContribution::class)->handle($contribution, $administrator, [
                'decision' => 'verified',
                'verified_committed_amount' => '425000000.00',
                'verified_disbursed_amount' => '85000000.00',
                'verified_in_kind_value' => '0.00',
                'source_reference' => 'IDMIS-DEMO-RECON-2026-001',
                'review_note' => 'The governed demonstration statement agrees with the recorded commitment and disbursement totals.',
            ]);
        }
    }

    private function governanceDocument(User $uploader, string $title, string $path, string $contents): AssessmentDocument
    {
        $assessment = Assessment::query()->firstOrFail();
        Storage::put($path, $contents);

        return AssessmentDocument::query()->updateOrCreate(
            ['assessment_id' => $assessment->id, 'title' => $title],
            [
                'county_id' => $assessment->county_id,
                'category' => 'governance_record',
                'source_type' => 'soft_copy',
                'path' => $path,
                'original_name' => basename($path),
                'mime_type' => 'text/plain',
                'size_bytes' => Storage::size($path),
                'content_checksum' => hash('sha256', $contents),
                'scan_status' => 'clean',
                'ocr_status' => 'not_required',
                'security_classification' => 'official',
                'record_status' => 'active',
                'description' => 'Governed demonstration evidence retained for workflow validation and training.',
                'document_date' => '2026-08-01',
                'version' => 1,
                'tags' => ['KDSP II', 'partner governance', 'demonstration evidence'],
                'retention_until' => '2033-08-01',
                'verification_status' => 'verified',
                'uploaded_by' => $uploader->id,
            ],
        );
    }

    private function seedReferenceDataRelease(User $administrator): void
    {
        if (ReferenceDataRelease::query()->count() >= 2) {
            return;
        }

        $approver = User::query()->where('email', 'platform.admin@idmis.test')->first();
        if (! $approver) {
            return;
        }

        $release = app(CreateReferenceDataRelease::class)->handle(
            $administrator,
            'Initial controlled publication of county, organization, sector and programme reference data for IDMIS interoperability.',
        );
        app(PublishReferenceDataRelease::class)->handle($release, $approver, [
            'approval_reference' => 'REFDATA-CCB-2026-001',
            'effective_from' => '2026-08-01',
        ]);
    }

    private function seedAccessGovernance(User $administrator, User $countyAdministrator): void
    {
        $platformAdministrator = User::query()->where('email', 'platform.admin@idmis.test')->first() ?? $administrator;
        $county = $countyAdministrator->homeCounty ?? County::query()->where('name', 'Mombasa')->first();

        if (! $county) {
            return;
        }

        AccessDelegation::query()->updateOrCreate(
            ['reference' => 'ACCESS-DELEGATION-2026-001'],
            [
                'requested_by' => $administrator->id,
                'beneficiary_id' => $countyAdministrator->id,
                'approved_by' => $platformAdministrator->id,
                'revoked_by' => $administrator->id,
                'access_type' => 'delegated',
                'scope_type' => 'county_portfolio',
                'permission_scope' => ['projects.manage'],
                'county_scope_snapshot' => [['id' => $county->id, 'name' => $county->name]],
                'business_justification' => 'Temporary project reporting coverage was required while the designated county officer attended mandatory training.',
                'status' => 'revoked',
                'starts_at' => '2026-07-20 08:00:00+03',
                'expires_at' => '2026-07-24 17:00:00+03',
                'approved_at' => '2026-07-19 15:00:00+03',
                'activated_at' => '2026-07-20 08:00:00+03',
                'revoked_at' => '2026-07-24 16:30:00+03',
                'decision_rationale' => 'Independent approval confirmed least-privilege access limited to one county and the approved coverage period.',
                'revocation_reason' => 'The designated county officer resumed duty and temporary coverage ended.',
                'approval_checksum' => hash('sha256', 'ACCESS-DELEGATION-2026-001-approved'),
            ],
        );

        $campaign = AccessReviewCampaign::query()->updateOrCreate(
            ['reference' => 'ACCESS-REVIEW-2026-Q2'],
            [
                'launched_by' => $platformAdministrator->id,
                'reviewer_id' => $administrator->id,
                'name' => 'Quarterly county portfolio access certification',
                'scope' => 'Certify active county administrator and official access against current duty assignments and MFA controls.',
                'role_scope' => ['county-admin', 'county-official'],
                'status' => 'completed',
                'period_from' => '2026-04-01',
                'period_to' => '2026-06-30',
                'due_at' => '2026-07-15 17:00:00+03',
                'launched_at' => '2026-07-01 09:00:00+03',
                'completed_at' => '2026-07-10 15:30:00+03',
                'item_count' => 1,
                'retained_count' => 1,
                'revoked_count' => 0,
                'remediation_count' => 0,
                'evidence_checksum' => hash('sha256', 'ACCESS-REVIEW-2026-Q2-complete'),
            ],
        );

        AccessReviewItem::query()->updateOrCreate(
            ['access_review_campaign_id' => $campaign->id, 'user_id' => $countyAdministrator->id],
            [
                'reviewed_by' => $administrator->id,
                'role_name' => 'county-admin',
                'permission_snapshot' => $countyAdministrator->programmePermissionValues(),
                'home_county_id' => $county->id,
                'assigned_county_snapshot' => [['id' => $county->id, 'name' => $county->name]],
                'mfa_enabled' => filled($countyAdministrator->two_factor_confirmed_at),
                'passkey_enabled' => false,
                'last_authenticated_at' => '2026-07-09 08:12:00+03',
                'decision' => 'retain',
                'rationale' => 'Role, county scope and active duty assignment were confirmed by the national programme administrator.',
                'reviewed_at' => '2026-07-10 14:45:00+03',
            ],
        );
    }

    private function seedPartnerCoordination(User $administrator, User $countyAdministrator): void
    {
        $partner = PartnerProfile::query()->first();
        $county = County::query()->where('name', 'Mombasa')->first() ?? County::query()->first();
        $organization = Organization::query()->where('type', 'development_partner')->first();

        if (! $partner || ! $county) {
            return;
        }

        $plan = PartnerCollaborationPlan::query()->updateOrCreate(
            ['reference' => 'PARTNER-COLLAB-2026-001'],
            [
                'partner_profile_id' => $partner->id,
                'title' => 'County evidence quality and grant-reporting collaboration plan',
                'objective' => 'Coordinate partner technical assistance with county reporting priorities and avoid duplicated data-quality support.',
                'starts_on' => '2026-07-01',
                'ends_on' => '2027-06-30',
                'status' => 'approved',
                'created_by' => $administrator->id,
                'submitted_by' => $administrator->id,
                'submitted_at' => '2026-06-20 10:00:00+03',
                'approved_by' => $countyAdministrator->id,
                'approved_at' => '2026-06-25 14:30:00+03',
                'decision_note' => 'Approved with quarterly progress verification and evidence references.',
            ],
        );

        $action = PartnerCollaborationAction::query()->updateOrCreate(
            ['partner_collaboration_plan_id' => $plan->id, 'code' => 'COLLAB-ACT-001'],
            [
                'county_id' => $county->id,
                'title' => 'Deliver a joint county data-quality clinic',
                'description' => 'Review grant reconciliation, indicator provenance and evidence-indexing controls with county finance, planning and M&E teams.',
                'accountable_user_id' => $countyAdministrator->id,
                'accountable_organization_id' => $organization?->id,
                'due_on' => '2026-09-15',
                'progress_percentage' => 60,
                'status' => 'in_progress',
                'created_by' => $administrator->id,
            ],
        );

        $updateChecksum = hash('sha256', 'COLLAB-ACT-001-update-60');
        $update = PartnerCollaborationActionUpdate::query()->firstOrCreate(
            ['update_checksum' => $updateChecksum],
            [
                'partner_collaboration_action_id' => $action->id,
                'progress_percentage' => 60,
                'narrative' => 'The diagnostic review and facilitator preparation are complete. The county clinic and post-clinic validation remain scheduled.',
                'submitted_by' => $countyAdministrator->id,
                'submitted_at' => '2026-08-06 11:00:00+03',
                'evidence_checksum' => hash('sha256', 'COLLAB-ACT-001-diagnostic-pack'),
            ],
        );

        PartnerCollaborationActionUpdateDecision::query()->firstOrCreate(
            ['partner_collaboration_action_update_id' => $update->id],
            [
                'decision' => 'verified',
                'verification_note' => 'The diagnostic attendance register, agenda and issue log support the reported preparation milestone.',
                'verified_by' => $administrator->id,
                'verified_at' => '2026-08-07 15:20:00+03',
                'decision_checksum' => hash('sha256', 'COLLAB-ACT-001-update-60-verified'),
            ],
        );

        PartnerOperationalAlert::query()->updateOrCreate(
            ['fingerprint' => hash('sha256', 'PARTNER-COLLAB-2026-001-evidence-due')],
            [
                'partner_profile_id' => $partner->id,
                'county_id' => $county->id,
                'subject_type' => PartnerCollaborationAction::class,
                'subject_id' => $action->id,
                'alert_type' => 'evidence_due',
                'severity' => 'medium',
                'summary' => 'Post-clinic validation evidence is due before the collaboration action can be completed.',
                'due_on' => '2026-09-15',
                'status' => 'open',
                'detected_at' => '2026-08-08 08:00:00+03',
                'notified_at' => '2026-08-08 08:05:00+03',
            ],
        );
    }

    private function seedPerformanceReview(User $administrator): void
    {
        $plan = PerformancePlan::query()->first();

        if (! $plan) {
            return;
        }

        PerformanceReview::query()->updateOrCreate(
            ['performance_plan_id' => $plan->id, 'stage' => 'mid_year'],
            [
                'reviewer_id' => $administrator->id,
                'rating' => 72,
                'comments' => 'Delivery is broadly on track, with strong assessment-cycle coverage and slower progress on automated source-system reconciliation.',
                'capacity_gaps' => 'Additional county data-stewardship and integration support is required for consistent source-reference validation.',
                'development_actions' => 'Complete the county data-quality clinic series and establish monthly reconciliation exception reviews.',
                'reviewed_at' => '2026-07-15 14:00:00+03',
            ],
        );
    }

    private function seedSecurityExercise(User $administrator): void
    {
        $incident = SecurityIncident::query()->updateOrCreate(
            ['reference' => 'SEC-EXERCISE-2026-001'],
            [
                'reported_by' => $administrator->id,
                'incident_lead_id' => $administrator->id,
                'closed_by' => $administrator->id,
                'record_type' => 'exercise',
                'playbook' => 'credential_compromise',
                'title' => 'Tabletop exercise: compromised county administrator account',
                'summary' => 'Controlled exercise validating account containment, session revocation, county-scope review and stakeholder notification procedures.',
                'affected_services' => ['identity', 'county_workspace', 'audit_stream'],
                'data_exposure' => 'none_simulated',
                'severity' => 'high',
                'status' => 'closed',
                'business_impact' => 'No live service impact; the scenario assumed attempted access to one county workspace.',
                'exercise_objectives' => ['Acknowledge within SLA', 'Revoke sessions', 'Confirm county isolation', 'Preserve audit evidence'],
                'exercise_outcome' => 'partially_met',
                'detected_at' => '2026-07-30 10:00:00+03',
                'acknowledgement_due_at' => '2026-07-30 10:15:00+03',
                'containment_due_at' => '2026-07-30 11:00:00+03',
                'acknowledged_at' => '2026-07-30 10:08:00+03',
                'contained_at' => '2026-07-30 10:42:00+03',
                'eradicated_at' => '2026-07-30 11:10:00+03',
                'recovered_at' => '2026-07-30 11:35:00+03',
                'closed_at' => '2026-07-31 15:00:00+03',
                'last_transition_at' => '2026-07-31 15:00:00+03',
                'next_exercise_due_at' => '2027-01-30 10:00:00+03',
                'root_cause' => 'Exercise scenario assumed credential capture through a convincing phishing message.',
                'corrective_actions' => 'Add a passkey adoption campaign and automate confirmation that all active sessions are revoked during containment.',
                'lessons_learned' => 'County isolation checks were effective; the stakeholder notification checklist needs clearer ownership.',
            ],
        );

        SecurityIncidentEvent::query()->firstOrCreate(
            ['evidence_checksum' => hash('sha256', 'SEC-EXERCISE-2026-001-closed')],
            [
                'security_incident_id' => $incident->id,
                'actor_id' => $administrator->id,
                'actor_name' => $administrator->name,
                'transition' => 'close_exercise',
                'from_status' => 'recovered',
                'to_status' => 'closed',
                'narrative' => 'The exercise lead confirmed recovery evidence, recorded lessons learned and assigned follow-up actions.',
                'evidence_reference' => 'SEC-EXERCISE-2026-001-AAR',
                'occurred_at' => '2026-07-31 15:00:00+03',
            ],
        );
    }

    private function seedLearningAndKnowledge(User $administrator, User $countyAdministrator): void
    {
        $cohort = TrainingCohort::query()->first();
        $county = County::query()->where('name', 'Mombasa')->first() ?? County::query()->first();

        if ($cohort && $county) {
            $participant = TrainingParticipant::query()->updateOrCreate(
                ['training_cohort_id' => $cohort->id, 'user_id' => $countyAdministrator->id],
                [
                    'county_id' => $county->id,
                    'participant_reference' => 'KSG-IDMIS-2026-MSA-001',
                    'role_title' => 'County IDMIS Administrator',
                    'attended_hours' => 12,
                    'attendance_status' => 'completed',
                    'competency_status' => 'competent',
                    'completed_at' => '2026-07-24 16:30:00+03',
                ],
            );

            TrainingAssessment::query()->updateOrCreate(
                ['training_participant_id' => $participant->id, 'assessment_type' => 'post_training'],
                [
                    'assessed_by' => $administrator->id,
                    'score' => 84,
                    'outcome' => 'competent',
                    'feedback' => 'Demonstrated accurate evidence indexing, cycle filtering and escalation of incomplete county submissions.',
                    'evidence_references' => ['KSG-IDMIS-2026-PRACTICAL-01', 'KSG-IDMIS-2026-QUIZ-01'],
                    'assessed_at' => '2026-07-24 15:45:00+03',
                ],
            );
        }

        $classroom = VirtualClassroom::query()->first();
        $enrollment = LearningEnrollment::query()->first();
        if ($classroom && $enrollment) {
            VirtualClassroomAttendance::query()->updateOrCreate(
                ['virtual_classroom_id' => $classroom->id, 'learning_enrollment_id' => $enrollment->id],
                [
                    'user_id' => $enrollment->user_id,
                    'attendance_status' => 'present',
                    'joined_at' => '2026-07-22 09:02:00+03',
                    'left_at' => '2026-07-22 10:31:00+03',
                    'attended_minutes' => 89,
                    'source' => 'provider_import',
                    'provider_event_id' => 'KSG-VC-2026-07-22-001',
                    'payload_checksum' => hash('sha256', 'KSG-VC-2026-07-22-001-attendance'),
                    'notes' => 'Attendance reconciled against the facilitator’s session register.',
                    'recorded_by' => $administrator->id,
                    'recorded_at' => '2026-07-22 11:00:00+03',
                ],
            );
        }

        $discussion = KnowledgeDiscussion::query()->first();
        if ($discussion) {
            KnowledgeDiscussionSubscription::query()->updateOrCreate(
                ['knowledge_discussion_id' => $discussion->id, 'user_id' => $countyAdministrator->id],
                ['delivery_frequency' => 'daily_digest', 'subscribed_at' => '2026-07-20 08:15:00+03'],
            );
        }

        $post = KnowledgePost::query()->first();
        if ($post && $county) {
            KnowledgeCommunityReport::query()->updateOrCreate(
                ['reference' => 'KM-MOD-2026-001'],
                [
                    'knowledge_post_id' => $post->id,
                    'county_id' => $county->id,
                    'reported_by' => $countyAdministrator->id,
                    'triaged_by' => $administrator->id,
                    'decided_by' => $administrator->id,
                    'category' => 'other',
                    'severity' => 'low',
                    'description' => 'The post appeared to duplicate an earlier guidance note and was submitted for a metadata and version check.',
                    'status' => 'resolved',
                    'resolution' => 'The newer post contains revised ACPA cycle guidance. Cross-links and version labels were added; no content removal was required.',
                    'post_action' => 'keep_visible',
                    'triaged_at' => '2026-07-21 10:00:00+03',
                    'decided_at' => '2026-07-21 14:20:00+03',
                ],
            );
        }
    }

    private function seedAssessmentGovernance(User $administrator, User $countyAdministrator): void
    {
        $assessor = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'assessor'))->first();
        $assessment = Assessment::query()->where('status', 'approved')->first();
        $criterion = AssessmentCriterion::query()->first();

        if (! $assessor || ! $assessment || ! $criterion) {
            return;
        }

        AssessmentCriterionResult::query()->updateOrCreate(
            ['assessment_id' => $assessment->id, 'assessment_criterion_id' => $criterion->id],
            [
                'submitted_score' => 78,
                'verified_score' => 75,
                'weighted_score' => 75,
                'submission_rationale' => 'The county supplied its approved annual development plan, quarterly implementation reports and budget execution extracts.',
                'verification_rationale' => 'The independent review reconciled the reported milestone against the signed evidence register.',
                'scored_by' => $countyAdministrator->id,
                'verified_by' => $assessor->id,
                'verified_at' => '2026-06-18 14:30:00+03',
                'calculation_snapshot' => ['submitted_score' => 78, 'verified_score' => 75, 'basis' => 'verified_score'],
            ],
        );

        AssessmentAttestation::query()->updateOrCreate(
            ['assessment_id' => $assessment->id, 'attested_by' => $countyAdministrator->id],
            [
                'attestor_title' => 'County Secretary',
                'statement' => 'I attest that the evidence submitted for this ACPA review is complete, authentic and approved for verification.',
                'signature_method' => 'authenticated_account',
                'content_checksum' => hash('sha256', $assessment->id.'-county-attestation'),
                'attested_at' => '2026-06-15 16:20:00+03',
            ],
        );

        $finding = AssessmentFinding::query()->updateOrCreate(
            ['assessment_id' => $assessment->id, 'code' => 'ACPA-FIND-2026-001'],
            [
                'assessment_criterion_id' => $criterion->id,
                'severity' => 'moderate',
                'status' => 'response_received',
                'title' => 'Quarterly public participation feedback register is incomplete',
                'description' => 'Two quarterly reports omit the consolidated response matrix showing how citizen submissions informed county decisions.',
                'county_response' => 'The missing matrices have been retrieved and a quarterly sign-off control established under the civic education unit.',
                'raised_by' => $assessor->id,
                'assigned_to' => $countyAdministrator->id,
                'response_due_at' => '2026-07-15 17:00:00+03',
                'responded_at' => '2026-07-11 11:00:00+03',
            ],
        );

        $plan = AssessmentCorrectivePlan::query()->updateOrCreate(
            ['reference' => 'CAP-ACPA-2026-001'],
            [
                'assessment_id' => $assessment->id,
                'county_id' => $assessment->county_id,
                'assessment_finding_id' => $finding->id,
                'submitted_by' => $countyAdministrator->id,
                'reviewed_by' => $assessor->id,
                'title' => 'Strengthen the public participation feedback evidence trail',
                'root_cause' => 'Departments used separate reporting templates and did not maintain a central response matrix.',
                'expected_outcome' => 'Every quarterly report has an approved response matrix linked to the relevant planning or budget decision.',
                'status' => 'approved',
                'due_at' => '2026-09-30 17:00:00+03',
                'submitted_at' => '2026-07-12 10:00:00+03',
                'reviewed_at' => '2026-07-15 15:00:00+03',
                'review_note' => 'Approved subject to monthly evidence uploads and independent verification.',
                'checksum' => hash('sha256', 'CAP-ACPA-2026-001'),
            ],
        );

        $action = AssessmentCorrectiveAction::query()->updateOrCreate(
            ['assessment_corrective_plan_id' => $plan->id, 'code' => 'CAP-ACT-001'],
            [
                'accountable_owner_id' => $countyAdministrator->id,
                'title' => 'Adopt and backfill the quarterly citizen feedback matrix',
                'description' => 'Approve one county-wide template, backfill two missing quarters and record the responsible department’s response.',
                'success_indicator' => 'Four signed quarterly matrices are indexed in the evidence repository.',
                'target' => '4 approved quarterly response matrices',
                'due_at' => '2026-09-15 17:00:00+03',
                'progress_percentage' => 50,
                'status' => 'in_progress',
            ],
        );

        $document = AssessmentDocument::query()->where('assessment_id', $assessment->id)->orderBy('id')->first();
        if ($document) {
            AssessmentCorrectiveUpdate::query()->updateOrCreate(
                ['assessment_corrective_action_id' => $action->id, 'progress_percentage' => 50],
                [
                    'assessment_document_id' => $document->id,
                    'submitted_by' => $countyAdministrator->id,
                    'narrative' => 'The standard template is approved and the first two quarterly matrices are indexed. Historical records remain under validation.',
                    'status' => 'pending_verification',
                    'submitted_at' => '2026-08-05 10:30:00+03',
                    'checksum' => hash('sha256', 'CAP-ACT-001-progress-50'),
                ],
            );
        }

        AssessmentAppeal::query()->updateOrCreate(
            ['assessment_id' => $assessment->id, 'assessment_criterion_id' => $criterion->id],
            [
                'appellant_id' => $countyAdministrator->id,
                'grounds' => 'The initial verification did not consider a signed supplementary implementation report uploaded during the clarification window.',
                'requested_remedy' => 'Reconsider the criterion score using the supplementary report and retain the decision trail.',
                'status' => 'decided',
                'reviewer_id' => $administrator->id,
                'decision' => 'Partially upheld. The report supports a three-point adjustment rather than the full amount requested.',
                'submitted_at' => '2026-06-20 09:00:00+03',
                'decided_at' => '2026-06-27 15:45:00+03',
            ],
        );

        AssessmentResultPublication::query()->firstOrCreate(
            ['assessment_id' => $assessment->id],
            [
                'assessment_cycle_id' => $assessment->assessment_cycle_id,
                'assessment_scorecard_version_id' => $assessment->assessment_scorecard_version_id,
                'county_id' => $assessment->county_id,
                'score' => $assessment->score ?? 75,
                'performance_band' => 'Meets minimum performance conditions',
                'function_profile' => ['planning' => 75, 'public_financial_management' => 72, 'civic_education' => 79],
                'calculation_snapshot' => ['source' => 'approved_assessment', 'assessment_id' => $assessment->id],
                'checksum' => hash('sha256', $assessment->id.'-publication-v1'),
                'published_by' => $administrator->id,
                'published_at' => '2026-07-01 09:00:00+03',
            ],
        );
    }

    private function seedProgrammeEvaluation(User $administrator, User $countyAdministrator): void
    {
        $programme = Programme::query()->first();
        $county = County::query()->where('name', 'Mombasa')->first() ?? County::query()->first();
        $assessor = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'assessor'))->first();

        if (! $programme || ! $county || ! $assessor) {
            return;
        }

        $evaluation = ProgrammeEvaluation::query()->updateOrCreate(
            ['code' => 'KDSP2-MIDTERM-2026'],
            [
                'programme_id' => $programme->id,
                'county_id' => $county->id,
                'title' => 'KDSP II Mid-Term Process and Results Evaluation',
                'evaluation_type' => 'midline',
                'period_start' => '2024-07-01',
                'period_end' => '2026-06-30',
                'status' => 'in_progress',
                'terms_of_reference' => 'Assess implementation fidelity, institutional capacity results, grant-flow efficiency and the use of ACPA evidence in planning and budgeting.',
                'methodology' => 'Mixed-method review of administrative records, verified indicators, key-informant interviews and sampled county evidence.',
                'executive_summary' => 'Initial evidence indicates stronger reporting discipline, while reconciliation delays and uneven public participation feedback remain material constraints.',
                'findings' => 'Counties with named control owners close evidence gaps faster. Grant reconciliation remains partly manual.',
                'recommendations' => 'Institutionalise monthly data-quality review and link corrective actions to named owners and evidence deadlines.',
                'lead_evaluator_id' => $assessor->id,
                'created_by' => $administrator->id,
            ],
        );

        $finding = EvaluationFinding::query()->updateOrCreate(
            ['reference' => 'EVAL-F-2026-001'],
            [
                'programme_evaluation_id' => $evaluation->id,
                'county_id' => $county->id,
                'accountable_owner_id' => $countyAdministrator->id,
                'created_by' => $assessor->id,
                'title' => 'Grant reconciliation evidence is not consistently filed within the reporting month',
                'finding' => 'Three sampled returns were timely, but Treasury, OCoB and bank reconciliation references were indexed after cut-off.',
                'recommendation' => 'Introduce a monthly reconciliation checklist with named preparer and reviewer controls.',
                'severity' => 'high',
                'status' => 'in_progress',
                'due_at' => '2026-09-30',
                'progress_percentage' => 40,
                'checksum' => hash('sha256', 'EVAL-F-2026-001'),
            ],
        );

        $action = EvaluationFindingAction::query()->updateOrCreate(
            ['evaluation_finding_id' => $finding->id, 'code' => 'EVAL-ACT-001'],
            [
                'accountable_owner_id' => $countyAdministrator->id,
                'created_by' => $assessor->id,
                'title' => 'Implement the monthly grant reconciliation control',
                'description' => 'Reconcile Treasury advice, OCoB authorisation, bank receipt and county ledger posting before submission.',
                'success_indicator' => 'Monthly returns contain all four source references and independent reviewer sign-off.',
                'target' => 'Complete reconciliation packs for three consecutive months',
                'due_at' => '2026-09-30 17:00:00+03',
                'weight_percentage' => 100,
                'progress_percentage' => 40,
                'status' => 'in_progress',
                'checksum' => hash('sha256', 'EVAL-ACT-001'),
            ],
        );

        $document = AssessmentDocument::query()->orderBy('id')->first();
        if ($document) {
            EvaluationFindingUpdate::query()->updateOrCreate(
                ['evaluation_finding_id' => $finding->id, 'progress_percentage' => 40],
                [
                    'assessment_document_id' => $document->id,
                    'submitted_by' => $countyAdministrator->id,
                    'verified_by' => $assessor->id,
                    'narrative' => 'The county approved the reconciliation checklist and completed the first controlled monthly review.',
                    'status' => 'verified',
                    'decision_note' => 'Checklist approval and the July review record were verified; sustained operation remains under observation.',
                    'submitted_at' => '2026-08-06 09:30:00+03',
                    'verified_at' => '2026-08-07 16:00:00+03',
                    'checksum' => hash('sha256', 'EVAL-F-2026-001-update-40'),
                ],
            );

            EvaluationFindingActionUpdate::query()->updateOrCreate(
                ['evaluation_finding_action_id' => $action->id, 'progress_percentage' => 40],
                [
                    'assessment_document_id' => $document->id,
                    'submitted_by' => $countyAdministrator->id,
                    'narrative' => 'The checklist was approved and used for July. Two more consecutive months are required before effectiveness can be verified.',
                    'status' => 'pending_verification',
                    'submitted_at' => '2026-08-07 14:00:00+03',
                    'checksum' => hash('sha256', 'EVAL-ACT-001-update-40'),
                ],
            );
        }
    }

    private function seedBusinessCalendar(User $administrator): void
    {
        $calendar = BusinessCalendar::query()->firstOrCreate(
            ['code' => 'KENYA-NATIONAL-2026', 'version' => 2],
            [
                'name' => 'Kenya National Government Business Calendar 2026',
                'timezone' => ReferenceCatalogue::defaultTimezone(),
                'working_days' => [1, 2, 3, 4, 5],
                'workday_starts_at' => '08:00:00',
                'workday_ends_at' => '17:00:00',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'status' => 'draft',
                'created_by' => $administrator->id,
                'checksum' => hash('sha256', 'KENYA-NATIONAL-2026-v2'),
            ],
        );

        foreach ([
            ['2026-01-01', 'New Year’s Day'],
            ['2026-05-01', 'Labour Day'],
            ['2026-06-01', 'Madaraka Day'],
            ['2026-10-20', 'Mashujaa Day'],
            ['2026-12-12', 'Jamhuri Day'],
            ['2026-12-25', 'Christmas Day'],
            ['2026-12-26', 'Boxing Day'],
        ] as [$date, $name]) {
            BusinessCalendarHoliday::query()->firstOrCreate(
                ['business_calendar_id' => $calendar->id, 'holiday_date' => $date],
                ['name' => $name, 'category' => 'public_holiday', 'source_reference' => 'Public Holidays Act (Cap. 110)', 'created_by' => $administrator->id],
            );
        }

        if ($calendar->status === 'draft') {
            $calendar->update([
                'status' => 'published',
                'published_by' => $administrator->id,
                'published_at' => '2025-12-15 09:00:00+03',
            ]);
        }
    }

    private function seedProjectDelivery(): void
    {
        $project = DevolutionProject::query()->first();
        $progress = ProjectProgressUpdate::query()->where('devolution_project_id', $project?->id)->first();
        $indicator = IndicatorDefinition::query()->first();
        $county = County::query()->where('name', 'Mombasa')->first() ?? County::query()->first();

        if (! $project) {
            return;
        }

        ProjectProcurement::query()->updateOrCreate(
            ['devolution_project_id' => $project->id, 'reference' => 'PPRA-KDSP2-2026-001'],
            [
                'title' => 'County data-quality and evidence digitisation support services',
                'method' => 'request_for_proposals',
                'status' => 'contract_awarded',
                'estimated_value' => 18500000,
                'contract_value' => 17640000,
                'currency' => ReferenceCatalogue::defaultCurrency(),
                'planned_notice_date' => '2026-02-02',
                'award_date' => '2026-05-18',
                'supplier_name' => 'Consortium for County Data Services',
                'contract_reference' => 'SDD/KDSP2/RFP/04/2025-2026',
            ],
        );

        if ($progress && $indicator && $county) {
            ProjectIndicatorResult::query()->updateOrCreate(
                [
                    'project_progress_update_id' => $progress->id,
                    'indicator_definition_id' => $indicator->id,
                    'dimension_key' => 'county_total',
                ],
                [
                    'county_id' => $county->id,
                    'period_start' => '2026-04-01',
                    'period_end' => '2026-06-30',
                    'disaggregation' => ['reporting_level' => 'county', 'verification_status' => 'verified'],
                    'numeric_value' => 1,
                    'narrative_value' => 'Mombasa County submitted a complete quarterly implementation return by the reporting deadline.',
                ],
            );
        }
    }

    private function seedIntergovernmentalRelations(User $administrator, User $countyAdministrator): void
    {
        $forum = IgrForum::query()->first();
        $resolution = IgrResolution::query()->first();
        $county = County::query()->where('name', 'Mombasa')->first() ?? County::query()->first();

        if (! $forum || ! $resolution || ! $county) {
            return;
        }

        $meeting = IgrForumMeeting::query()->updateOrCreate(
            ['reference' => 'IGR-SUMMIT-2026-01'],
            [
                'igr_forum_id' => $forum->id,
                'title' => 'Intergovernmental consultation on conditional grant reporting',
                'held_on' => '2026-06-05',
                'venue' => 'Kenya School of Government, Lower Kabete',
                'chair_user_id' => $administrator->id,
                'quorum_confirmed' => true,
                'minutes_reference' => 'MIN/IGR/SUMMIT/2026/01',
                'created_by' => $administrator->id,
            ],
        );

        $resolution->update(['igr_forum_meeting_id' => $meeting->id]);

        $prerequisite = IgrResolution::query()->updateOrCreate(
            ['resolution_number' => 'IGR/SUMMIT/2026/002'],
            [
                'igr_forum_id' => $forum->id,
                'igr_forum_meeting_id' => $meeting->id,
                'title' => 'Adopt an intergovernmental grant-data exchange protocol',
                'resolution_text' => 'National and county institutions shall use agreed reference fields, ownership rules and validation responses when exchanging conditional grant data.',
                'resolved_on' => '2026-06-05',
                'due_on' => '2026-08-31',
                'priority' => 'high',
                'status' => 'in_progress',
                'progress_percentage' => 70,
                'created_by' => $administrator->id,
            ],
        );

        IgrResolutionDependency::query()->updateOrCreate(
            ['dependent_resolution_id' => $resolution->id, 'prerequisite_resolution_id' => $prerequisite->id],
            [
                'dependency_type' => 'blocks',
                'rationale' => 'The common reporting schedule cannot be automated until the exchange protocol defines required identifiers and validation responses.',
                'created_by' => $administrator->id,
            ],
        );

        IgrResolutionUpdate::query()->updateOrCreate(
            ['igr_resolution_id' => $resolution->id, 'progress_percentage' => 55],
            [
                'narrative' => 'A common reconciliation template has been circulated and piloted with eight counties. Treasury and OCoB reference fields are mandatory in the pilot return.',
                'implementation_gap' => 'County ledger extracts use different segments, so automated validation requires an approved transitional mapping table.',
                'evidence_reference' => 'IGR/KDSP2/PILOT/2026-Q2',
                'reported_by' => $administrator->id,
                'reported_at' => '2026-08-03 09:30:00+03',
            ],
        );

        $category = IgrGapCategory::query()->updateOrCreate(
            ['code' => 'IGR-GAP-DATA-QUALITY'],
            [
                'name' => 'Data quality and interoperability',
                'description' => 'Gaps caused by inconsistent definitions, source references, exchange formats or reconciliation controls across institutions.',
                'default_severity' => 'high',
                'is_active' => true,
                'created_by' => $administrator->id,
            ],
        );

        IgrResolutionGap::query()->updateOrCreate(
            [
                'igr_resolution_id' => $resolution->id,
                'igr_gap_category_id' => $category->id,
                'county_id' => $county->id,
            ],
            [
                'owner_user_id' => $countyAdministrator->id,
                'title' => 'County ledger extract requires mapping to the common grant-return structure',
                'description' => 'The pilot ledger groups two conditional grant transactions under a local chart-of-accounts segment absent from the common template.',
                'impact' => 'Automated reconciliation cannot confirm completeness until the local segment is mapped and approved.',
                'severity' => 'high',
                'status' => 'mitigating',
                'due_on' => '2026-08-31',
                'mitigation_plan' => 'County finance and national programme teams will approve a crosswalk and validate it against the July ledger extract.',
                'reported_by' => $administrator->id,
                'mitigation_started_at' => '2026-08-04 10:00:00+03',
            ],
        );
    }
}
