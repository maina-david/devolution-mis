<?php

namespace Tests\Feature;

use App\Actions\AttestAssessment;
use App\Actions\CalculateAssessmentScore;
use App\Actions\DecideAssessmentAppeal;
use App\Actions\OverrideCriterionScore;
use App\Actions\RecordAssessmentFinding;
use App\Actions\ResolveAssessmentFinding;
use App\Actions\RespondToAssessmentFinding;
use App\Actions\SubmitAssessmentAppeal;
use App\Actions\SubmitCriterionScore;
use App\Actions\VerifyAssessmentEvidence;
use App\Actions\VerifyCriterionScore;
use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentAppeal;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentCriterionResult;
use App\Models\AssessmentCycle;
use App\Models\AssessmentDocument;
use App\Models\AssessmentFinding;
use App\Models\AssessmentFunction;
use App\Models\AssessmentScorecardVersion;
use App\Models\AssessmentStandard;
use App\Models\AssessmentThematicArea;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\CriterionEvidenceRequirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssessmentRuntimeGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_score_is_reproducibly_calculated_only_from_verified_results_and_complete_evidence(): void
    {
        [$assessment, $criterion, $requirement, $actor] = $this->governedAssessment();
        AssessmentCriterionResult::factory()->create(['assessment_id' => $assessment->id, 'assessment_criterion_id' => $criterion->id, 'verified_score' => 80, 'verified_by' => $actor->id, 'verified_at' => now()]);
        AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $assessment->county_id, 'assessment_criterion_id' => $criterion->id, 'criterion_evidence_requirement_id' => $requirement->id, 'verification_status' => 'pending']);

        try {
            app(CalculateAssessmentScore::class)->handle($assessment, $actor);
            $this->fail('Unverified evidence must not satisfy completeness.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('evidence', $exception->errors());
        }

        AssessmentDocument::query()->update(['verification_status' => 'verified']);
        $calculated = app(CalculateAssessmentScore::class)->handle($assessment->refresh(), $actor);

        $this->assertSame('80.00', $calculated->score);
        $this->assertSame('100.00', $calculated->completeness_percentage);
        $this->assertSame('80.000000', AssessmentCriterionResult::query()->sole()->weighted_score);
        $this->assertSame(80, AssessmentCriterionResult::query()->sole()->calculation_snapshot['effective_score']);
    }

    public function test_county_attestation_requires_complete_evidence(): void
    {
        [$assessment] = $this->governedAssessment();
        $actor = User::factory()->countyAdmin($assessment->county)->create();

        $this->expectException(ValidationException::class);
        app(AttestAssessment::class)->handle($assessment, $actor, 'County Secretary', 'I attest this submission.');
    }

    public function test_county_user_uploads_evidence_against_governed_criterion_requirement(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$assessment, $criterion, $requirement] = $this->governedAssessment();
        $official = User::factory()->countyOfficial($assessment->county)->create();
        $assessment->update(['status' => AssessmentStatus::EvidenceCollection]);

        $this->actingAs($official)->post(route('evidence.store', [$assessment]), [
            'title' => 'Verified sector policy',
            'category' => 'policy',
            'source_type' => 'digital',
            'assessment_criterion_id' => $criterion->id,
            'criterion_evidence_requirement_id' => $requirement->id,
            'document' => UploadedFile::fake()->create('policy.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('assessment_documents', ['assessment_id' => $assessment->id, 'assessment_criterion_id' => $criterion->id, 'criterion_evidence_requirement_id' => $requirement->id, 'category' => 'policy']);
    }

    public function test_criterion_scoring_enforces_independent_verification_and_reasoned_override(): void
    {
        [$assessment, $criterion] = $this->governedAssessment();
        $submitter = User::factory()->assessor()->create();
        $verifier = User::factory()->assessor()->create();
        $submitter->assignedCounties()->attach($assessment->county_id);
        $verifier->assignedCounties()->attach($assessment->county_id);
        $approver = User::factory()->topManagement()->create();
        $result = app(SubmitCriterionScore::class)->handle($assessment, $criterion, $submitter, 72, 'Score supported by the submitted primary records.');

        try {
            app(VerifyCriterionScore::class)->handle($result, $submitter, 72, 'Self verification attempt.');
            $this->fail('The score submitter must not verify their own score.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('actor', $exception->errors());
        }

        app(VerifyCriterionScore::class)->handle($result->refresh(), $verifier, 74, 'Independently checked against verified primary records.');
        $overridden = app(OverrideCriterionScore::class)->handle($result->refresh(), $approver, 76, 'Approved correction following documented quality assurance review.');

        $this->assertSame('74.0000', $overridden->verified_score);
        $this->assertSame('76.0000', $overridden->override_score);
        $this->assertSame($approver->id, $overridden->overridden_by);
        $this->assertDatabaseHas('audit_events', ['action' => 'assessment.criterion_overridden']);
    }

    public function test_complete_assessment_can_be_attested_and_supports_findings_and_appeals(): void
    {
        [$assessment, $criterion] = $this->governedAssessment();
        $attestor = User::factory()->countyAdmin($assessment->county)->create();
        $reviewer = User::factory()->assessor()->create();
        $reviewer->assignedCounties()->attach($assessment->county_id);
        $assessment->update(['completeness_percentage' => 100, 'score' => 75, 'status' => AssessmentStatus::Assessed]);

        $attestation = app(AttestAssessment::class)->handle($assessment->refresh(), $attestor, 'County Secretary', 'I attest this submission.');
        $finding = app(RecordAssessmentFinding::class)->handle($assessment, $reviewer, ['assessment_criterion_id' => $criterion->id, 'code' => 'FIND-001', 'severity' => 'major', 'title' => 'Clarification required', 'description' => 'Provide the signed source register.']);
        $appeal = app(SubmitAssessmentAppeal::class)->handle($assessment->refresh(), $attestor, 'The verified record was omitted.', 'Reconsider criterion score.', $criterion->id);

        $this->assertSame(64, strlen($attestation->content_checksum));
        $this->assertSame('attested', $assessment->fresh()?->attestation_status);
        $this->assertSame('open', $finding->status);
        $this->assertSame('submitted', $appeal->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'assessment.attested']);
        $this->assertDatabaseHas('audit_events', ['action' => 'assessment.finding_raised']);
        $this->assertDatabaseHas('audit_events', ['action' => 'assessment.appeal_submitted']);
    }

    public function test_assessment_calculation_and_override_boundaries_follow_the_active_locale(): void
    {
        [$assessment, $criterion, , $actor] = $this->governedAssessment();
        $result = AssessmentCriterionResult::factory()->create(['assessment_id' => $assessment->id, 'assessment_criterion_id' => $criterion->id]);
        App::setLocale('fr');

        try {
            app(OverrideCriterionScore::class)->handle($result, $actor, 50, 'Justification documentée pour la correction du score.');
            $this->fail('Expected an unverified score override to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(trans('assessment-record.errors.override_verified_only', locale: 'fr'), $exception->errors()['score'][0]);
        }

        $result->update(['verified_score' => 72, 'verified_by' => $actor->id, 'verified_at' => now()]);
        App::setLocale('sw');
        app(OverrideCriterionScore::class)->handle($result->refresh(), $actor, 74, 'Marekebisho yameidhinishwa baada ya ukaguzi huru wa ushahidi.');

        $event = AuditEvent::query()->where('subject_id', $result->id)->where('action', 'assessment.criterion_overridden')->sole();
        $this->assertSame(trans('assessment-record.audit.criterion_overridden', ['criterion' => $criterion->code], 'sw'), $event->description);

        $verifier = User::factory()->assessor()->create();
        $verifier->assignedCounties()->attach($assessment->county_id);
        App::setLocale('fr');

        try {
            app(VerifyCriterionScore::class)->handle($result->refresh(), $verifier, 101, 'Vérification indépendante fondée sur les pièces justificatives.');
            $this->fail('Expected an out-of-range verified score to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(trans('assessment-record.errors.criterion_score_range', ['maximum' => $criterion->maximum_score], 'fr'), $exception->errors()['score'][0]);
        }

        App::setLocale('sw');
        app(VerifyCriterionScore::class)->handle($result->refresh(), $verifier, 75, 'Uhakiki huru umefanywa kwa kutumia nyaraka halisi zilizowasilishwa.');
        $verificationEvent = AuditEvent::query()->where('subject_id', $result->id)->where('action', 'assessment.criterion_verified')->sole();
        $this->assertSame(trans('assessment-record.audit.criterion_verified', ['criterion' => $criterion->code], 'sw'), $verificationEvent->description);
    }

    public function test_assessment_action_boundary_denies_wrong_roles_and_out_of_scope_users_without_side_effects(): void
    {
        [$assessment, $criterion, $requirement, $administrator] = $this->governedAssessment();
        $assessment->update(['status' => AssessmentStatus::Assessed, 'score' => 75, 'completeness_percentage' => 100]);
        $result = AssessmentCriterionResult::factory()->create(['assessment_id' => $assessment->id, 'assessment_criterion_id' => $criterion->id, 'scored_by' => $administrator->id]);
        $finding = AssessmentFinding::factory()->create(['assessment_id' => $assessment->id, 'raised_by' => $administrator->id, 'county_response' => 'A governed county response is present.']);
        $appeal = AssessmentAppeal::factory()->create(['assessment_id' => $assessment->id, 'appellant_id' => $administrator->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $assessment->county_id, 'assessment_criterion_id' => $criterion->id, 'criterion_evidence_requirement_id' => $requirement->id, 'scan_status' => 'clean', 'verification_status' => 'pending']);
        $wrongRole = User::factory()->developmentPartner()->create();
        $outOfScopeAssessor = User::factory()->assessor()->create();
        $outOfScopeCountyAdmin = User::factory()->countyAdmin()->create();
        $outOfScopeApprover = User::factory()->topManagement()->create();
        $auditCount = AuditEvent::query()->count();

        $actions = [
            fn () => app(AttestAssessment::class)->handle($assessment, $wrongRole, 'Partner representative', 'This actor must never be able to attest a county assessment.'),
            fn () => app(SubmitCriterionScore::class)->handle($assessment, $criterion, $wrongRole, 70, 'This actor must never be able to score a governed criterion.'),
            fn () => app(RecordAssessmentFinding::class)->handle($assessment, $wrongRole, ['code' => 'DENIED-001', 'severity' => 'major', 'title' => 'Unauthorized finding', 'description' => 'This finding must not be persisted by an unauthorized actor.']),
            fn () => app(SubmitAssessmentAppeal::class)->handle($assessment, $wrongRole, 'Unauthorized appeal grounds must not be stored.', 'No remedy may be requested.'),
            fn () => app(RespondToAssessmentFinding::class)->handle($finding, $outOfScopeCountyAdmin, 'An out-of-scope county response must not be stored.'),
            fn () => app(ResolveAssessmentFinding::class)->handle($finding, $outOfScopeAssessor, 'An out-of-scope assessor must not resolve this finding.'),
            fn () => app(VerifyCriterionScore::class)->handle($result, $outOfScopeAssessor, 72, 'An out-of-scope assessor must not verify this result.'),
            fn () => app(VerifyAssessmentEvidence::class)->handle($document, 'verified', $outOfScopeAssessor),
            fn () => app(DecideAssessmentAppeal::class)->handle($appeal, $outOfScopeApprover, 'rejected', 'An out-of-scope approver must not decide this appeal.'),
        ];

        foreach ($actions as $action) {
            try {
                $action();
                $this->fail('The assessment action should have been denied.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertSame($auditCount, AuditEvent::query()->count());
        $this->assertDatabaseMissing('assessment_findings', ['code' => 'DENIED-001']);
        $this->assertDatabaseCount('assessment_attestations', 0);
        $this->assertSame('pending', $document->fresh()?->verification_status);
        $this->assertSame('open', $finding->fresh()?->status);
        $this->assertSame('submitted', $appeal->fresh()?->status);
        $this->assertNull($result->fresh()?->verified_score);
    }

    /** @return array{Assessment, AssessmentCriterion, CriterionEvidenceRequirement, User} */
    private function governedAssessment(): array
    {
        $version = AssessmentScorecardVersion::factory()->create(['status' => 'draft']);
        $function = AssessmentFunction::factory()->create(['assessment_scorecard_version_id' => $version->id, 'weight' => 100]);
        $theme = AssessmentThematicArea::factory()->create(['assessment_function_id' => $function->id, 'weight' => 100]);
        $standard = AssessmentStandard::factory()->create(['assessment_thematic_area_id' => $theme->id, 'weight' => 100]);
        $criterion = AssessmentCriterion::factory()->create(['assessment_standard_id' => $standard->id, 'weight' => 100, 'maximum_score' => 100]);
        $requirement = CriterionEvidenceRequirement::factory()->create(['assessment_criterion_id' => $criterion->id, 'minimum_documents' => 1]);
        $version->update(['status' => 'published', 'checksum' => fake()->sha256(), 'published_at' => now(), 'effective_from' => now()]);
        $cycle = AssessmentCycle::factory()->create(['assessment_scorecard_version_id' => $version->id]);
        $county = County::factory()->create();
        $actor = User::factory()->devolutionAdmin()->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'assessment_cycle_id' => $cycle->id, 'assessment_scorecard_version_id' => $version->id, 'cycle' => $cycle->code, 'status' => AssessmentStatus::UnderAssessment]);

        return [$assessment, $criterion, $requirement, $actor];
    }
}
