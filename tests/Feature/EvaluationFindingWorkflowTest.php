<?php

namespace Tests\Feature;

use App\Actions\CloseEvaluationFinding;
use App\Actions\CreateEvaluationFinding;
use App\Actions\RecordEvaluationFindingUpdate;
use App\Actions\VerifyEvaluationFindingUpdate;
use App\Enums\ProgrammePermission;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DocumentLink;
use App\Models\EvaluationFinding;
use App\Models\ProgrammeEvaluation;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EvaluationFindingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_evaluation_has_independent_evidence_backed_follow_up_and_closure(): void
    {
        [$county, $issuer, $owner, $evaluation] = $this->context();
        $verifier = User::factory()->assessor()->create();
        $verifier->assignedCounties()->attach($county);
        $closer = User::factory()->devolutionAdmin()->create();
        $finding = $this->finding($evaluation, $issuer, $owner, 'EVAL-F-2026-001');
        $this->assertSame('open', $finding->status);
        $document = $this->evidence($finding, $owner, $county);
        $update = app(RecordEvaluationFindingUpdate::class)->handle($finding, $document, $owner, 100, 'Escalation routing deployed and evidenced.');
        $this->assertSame('pending_verification', $update->status);

        $verified = app(VerifyEvaluationFindingUpdate::class)->handle($update, $verifier, 'verified', 'Evidence confirms operational deployment.');
        $this->assertSame('verified', $verified->status);
        $closed = app(CloseEvaluationFinding::class)->handle($finding->refresh(), $closer, 'Independent closure accepted after full verification.');

        $this->assertTrue(Str::isUuid($finding->id));
        $this->assertTrue(Str::isUuid($update->id));
        $this->assertSame('verified', $verified->status);
        $this->assertSame('closed', $closed->status);
        $this->assertSame('100.00', $closed->progress_percentage);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $finding->id, 'action' => 'evaluation.finding.closed']);
    }

    public function test_submitter_cannot_verify_own_response(): void
    {
        [$county, $issuer, $owner, $evaluation] = $this->context();
        $finding = $this->finding($evaluation, $issuer, $owner, 'EVAL-F-2026-002');
        $update = app(RecordEvaluationFindingUpdate::class)->handle($finding, $this->evidence($finding, $owner, $county), $owner, 100, 'Control deployed.');

        $this->expectException(HttpException::class);
        app(VerifyEvaluationFindingUpdate::class)->handle($update, $owner, 'verified', 'Self verification attempted.');
    }

    public function test_finding_register_is_limited_to_the_users_authorized_county(): void
    {
        [$county, $issuer, $owner, $evaluation] = $this->context();
        $this->finding($evaluation, $issuer, $owner, 'VISIBLE-FINDING');
        $otherCounty = County::factory()->create();
        $otherOwner = User::factory()->countyAdmin($otherCounty)->create();
        $otherEvaluation = ProgrammeEvaluation::factory()->create(['county_id' => $otherCounty->id, 'status' => 'approved', 'approved_by' => User::factory()->assessor()->create()->id, 'approved_at' => now()]);
        $this->finding($otherEvaluation, $issuer, $otherOwner, 'HIDDEN-FINDING');

        $this->actingAs($owner)->get(route('monitoring-evaluation.index', $owner->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.findings', 1)
                ->where('options.findings.0.reference', 'VISIBLE-FINDING')
                ->where('options.findings.0.county.name', $county->name)
                ->where('options.findings.0.reminderSentAt', null)
                ->where('options.findings.0.escalatedAt', null)
                ->has('options.findingOwners', 1)
                ->where('options.findingOwners.0.id', $owner->id));
    }

    public function test_deadline_reminders_and_scope_safe_escalations_are_idempotent(): void
    {
        $this->travelTo('2026-08-10 09:00:00');
        Notification::fake();
        config()->set('monitoring-evaluation.finding_reminder_days_before_due', 7);
        [$county, $issuer, $upcomingOwner, $evaluation] = $this->context();
        $overdueOwner = User::factory()->countyAdmin($county)->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $unassignedAssessor = User::factory()->assessor()->create();
        $unassignedAssessor->assignedCounties()->attach(County::factory()->create());
        $unassignedAssessor->givePermissionTo(ProgrammePermission::ManageIndicators->value);

        $upcoming = $this->finding($evaluation, $issuer, $upcomingOwner, 'EVAL-F-UPCOMING', today()->addDays(2)->toDateString());
        $overdue = $this->finding($evaluation, $issuer, $overdueOwner, 'EVAL-F-OVERDUE', today()->subDay()->toDateString());
        $future = $this->finding($evaluation, $issuer, $upcomingOwner, 'EVAL-F-FUTURE', today()->addDays(30)->toDateString());
        EvaluationFinding::factory()->create([
            'programme_evaluation_id' => $evaluation->id,
            'county_id' => $county->id,
            'accountable_owner_id' => $upcomingOwner->id,
            'created_by' => $issuer->id,
            'reference' => 'EVAL-F-CLOSED',
            'status' => 'closed',
            'due_at' => today()->subDay(),
        ]);

        $this->assertSame(0, Artisan::call('monitoring-evaluation:send-finding-reminders'));
        $this->assertStringContainsString('Processed 2 evaluation recommendation alert(s).', Artisan::output());

        $this->assertNotNull($upcoming->refresh()->reminder_sent_at);
        $this->assertNull($upcoming->escalated_at);
        $this->assertNotNull($overdue->refresh()->reminder_sent_at);
        $this->assertNotNull($overdue->escalated_at);
        $this->assertNull($future->refresh()->reminder_sent_at);
        Notification::assertSentToTimes($upcomingOwner, ProgrammeAlert::class, 1);
        Notification::assertSentToTimes($overdueOwner, ProgrammeAlert::class, 1);
        Notification::assertSentToTimes($issuer, ProgrammeAlert::class, 1);
        Notification::assertSentToTimes($manager, ProgrammeAlert::class, 1);
        Notification::assertNotSentTo($unassignedAssessor, ProgrammeAlert::class);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $upcoming->id, 'action' => 'evaluation.finding.reminded']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $overdue->id, 'action' => 'evaluation.finding.escalated']);

        $this->travel(3)->days();
        $this->assertSame(0, Artisan::call('monitoring-evaluation:send-finding-reminders'));
        $this->assertStringContainsString('Processed 1 evaluation recommendation alert(s).', Artisan::output());
        $this->assertNotNull($upcoming->refresh()->escalated_at);
        Notification::assertSentToTimes($upcomingOwner, ProgrammeAlert::class, 2);
        Notification::assertSentToTimes($overdueOwner, ProgrammeAlert::class, 1);
        Notification::assertSentToTimes($issuer, ProgrammeAlert::class, 2);
        Notification::assertSentToTimes($manager, ProgrammeAlert::class, 2);

        $this->assertSame(0, Artisan::call('monitoring-evaluation:send-finding-reminders'));
        $this->assertStringContainsString('Processed 0 evaluation recommendation alert(s).', Artisan::output());
        Notification::assertSentToTimes($upcomingOwner, ProgrammeAlert::class, 2);
        Notification::assertSentToTimes($overdueOwner, ProgrammeAlert::class, 1);
        Notification::assertSentToTimes($issuer, ProgrammeAlert::class, 2);
        Notification::assertSentToTimes($manager, ProgrammeAlert::class, 2);
        $this->travelBack();
    }

    /** @return array{County, User, User, ProgrammeEvaluation} */
    private function context(): array
    {
        $county = County::factory()->create();
        $issuer = User::factory()->devolutionAdmin()->create();
        $owner = User::factory()->countyAdmin($county)->create();
        $evaluation = ProgrammeEvaluation::factory()->create(['county_id' => $county->id, 'status' => 'approved', 'approved_by' => User::factory()->assessor()->create()->id, 'approved_at' => now()]);

        return [$county, $issuer, $owner, $evaluation];
    }

    private function finding(ProgrammeEvaluation $evaluation, User $issuer, User $owner, string $reference, ?string $dueAt = null): EvaluationFinding
    {
        return app(CreateEvaluationFinding::class)->handle($evaluation, $issuer, ['reference' => $reference, 'title' => 'Delayed exchequer processing', 'finding' => 'Processing exceeded the approved service level.', 'recommendation' => 'Implement automated exception escalation.', 'severity' => 'high', 'accountable_owner_id' => $owner->id, 'due_at' => $dueAt ?? now()->addMonth()->toDateString()]);
    }

    private function evidence(EvaluationFinding $finding, User $owner, County $county): AssessmentDocument
    {
        $document = AssessmentDocument::factory()->create(['assessment_id' => null, 'county_id' => $county->id, 'record_status' => 'active', 'scan_status' => 'clean']);
        DocumentLink::create(['assessment_document_id' => $document->id, 'subject_type' => $finding->getMorphClass(), 'subject_id' => $finding->id, 'purpose' => 'evaluation-finding-response-evidence', 'created_by' => $owner->id]);

        return $document;
    }
}
