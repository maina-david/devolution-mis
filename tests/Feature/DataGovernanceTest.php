<?php

namespace Tests\Feature;

use App\Actions\AdvanceDataSubjectRequest;
use App\Actions\AdvancePrivacyIncident;
use App\Models\DataAsset;
use App\Models\DataSubjectRequest;
use App\Models\PrivacyIncident;
use App\Models\ProcessingActivity;
use App\Models\RetentionSchedule;
use App\Models\RetentionScheduleApproval;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DataGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_inventory_and_retention_schedule_are_governed_uuid_records(): void
    {
        $manager = User::factory()->devolutionAdmin()->create();
        $owner = User::factory()->platformAdmin()->create();
        $steward = User::factory()->devolutionAdmin()->create();
        $retentionReviewer = User::factory()->platformAdmin()->create();
        $countyUser = User::factory()->countyAdmin()->create();

        $this->actingAs($countyUser)->get(route('data-governance.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('data-governance.assets.store'), $this->assetPayload($owner, $steward))->assertRedirect();
        $asset = DataAsset::query()->sole();
        $this->assertTrue(Str::isUuid($asset->id));
        $this->assertSame(['name', 'official_email', 'employee_reference'], $asset->personal_data_categories);
        $this->assertSame(['postgresql', 'private_object_storage'], $asset->storage_locations);

        $this->actingAs($manager)->post(route('data-governance.retention-schedules.store'), $this->retentionPayload())->assertRedirect();
        $schedule = RetentionSchedule::query()->sole();
        $approval = RetentionScheduleApproval::query()->sole();
        $this->assertTrue(Str::isUuid($schedule->id));
        $this->assertTrue(Str::isUuid($approval->id));
        $this->assertSame('submitted', $schedule->status);
        $this->assertSame($manager->id, $approval->submitted_by);
        $this->actingAs($manager)->patch(route('data-governance.retention-schedules.review', [$schedule]), ['decision' => 'approved', 'decision_reason' => 'The submitting officer cannot approve this schedule.'])->assertForbidden();
        $this->actingAs($retentionReviewer)->patch(route('data-governance.retention-schedules.review', [$schedule]), ['decision' => 'approved', 'decision_reason' => 'The retention trigger, authority, hold rule and disposition have been independently reviewed.'])->assertRedirect();
        $this->assertSame('approved', $schedule->refresh()->status);
        $this->assertSame($retentionReviewer->id, $schedule->approved_by);
        $this->assertSame('approved', $approval->refresh()->status);
        $this->assertSame($retentionReviewer->id, $approval->reviewed_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $asset->id, 'action' => 'privacy.data-asset.registered']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $approval->id, 'action' => 'privacy.retention-schedule.submitted']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $approval->id, 'action' => 'privacy.retention-schedule.approved']);
        $this->actingAs($retentionReviewer)->get(route('data-governance.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('retentionSchedules.0.status', 'approved')
            ->where('retentionSchedules.0.submission.submitter', $manager->name)
            ->where('retentionSchedules.0.submission.reviewer', $retentionReviewer->name)
            ->where('retentionSchedules.0.submission.snapshotChecksum', $approval->snapshot_checksum));
        $asset->delete();
        $this->assertSoftDeleted($asset);
    }

    public function test_retention_review_detects_post_submission_change_and_terminal_evidence_is_immutable(): void
    {
        $submitter = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $this->actingAs($submitter)->post(route('data-governance.retention-schedules.store'), $this->retentionPayload())->assertRedirect();
        $schedule = RetentionSchedule::query()->sole();
        $approval = RetentionScheduleApproval::query()->sole();
        $asset = DataAsset::factory()->create();
        $this->actingAs($submitter)->post(route('data-governance.processing-activities.store'), $this->processingPayload($asset, $schedule))->assertSessionHasErrors('retention_schedule_id');

        $schedule->update(['retention_months' => 96]);
        $this->actingAs($reviewer)->patch(route('data-governance.retention-schedules.review', [$schedule]), ['decision' => 'approved', 'decision_reason' => 'This changed schedule must fail its submission checksum.'])->assertStatus(409);
        $schedule->update(['retention_months' => 84]);
        $this->actingAs($reviewer)->patch(route('data-governance.retention-schedules.review', [$schedule]), ['decision' => 'rejected', 'decision_reason' => 'The records authority reference remains a draft and is not sufficient for approval.'])->assertRedirect();
        $this->assertSame('rejected', $schedule->refresh()->status);
        $this->assertSame('rejected', $approval->refresh()->status);

        $this->expectException(QueryException::class);
        DB::table('retention_schedule_approvals')->where('id', $approval->id)->update(['decision_reason' => 'Tampered terminal decision']);
    }

    public function test_retention_approval_factory_and_database_guard_preserve_submission_evidence(): void
    {
        $approval = RetentionScheduleApproval::factory()->create();

        $this->assertTrue(Str::isUuid($approval->id));
        $this->assertSame('submitted', $approval->retentionSchedule->status);
        $this->assertSame(
            app(CanonicalJson::class)->checksum($approval->retentionSchedule->approvalSnapshot()),
            $approval->snapshot_checksum,
        );

        $this->expectException(QueryException::class);
        DB::table('retention_schedule_approvals')->where('id', $approval->id)->delete();
    }

    public function test_retention_governance_catalogues_and_operator_contract_remain_synchronized(): void
    {
        $english = require lang_path('en/data-governance.php');
        $swahili = require lang_path('sw/data-governance.php');
        $french = require lang_path('fr/data-governance.php');

        $this->assertSame(array_keys($english), array_keys($swahili));
        $this->assertSame(array_keys($english), array_keys($french));

        $page = file_get_contents(resource_path('js/pages/data-governance/index.tsx'));
        $this->assertIsString($page);
        $this->assertStringContainsString('reviewRetentionSchedule([', $page);
        $this->assertStringContainsString('schedule.status === \'submitted\'', $page);
        $this->assertStringContainsString("schedule.status === 'approved'", $page);
        $this->assertStringContainsString('governanceCopy.retention_empty_title', $page);
        $this->assertStringNotContainsString('Approve retention schedule', $page);
    }

    public function test_sensitive_processing_requires_completed_dpia_and_independent_review(): void
    {
        $submitter = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $asset = DataAsset::factory()->create(['contains_sensitive_personal_data' => true]);
        $schedule = RetentionSchedule::factory()->create(['status' => 'approved']);

        $this->withSession(['locale' => 'fr']);
        $this->actingAs($submitter)->post(route('data-governance.processing-activities.store'), $this->processingPayload($asset, $schedule))->assertRedirect();
        $activity = ProcessingActivity::query()->sole();
        $this->assertSame('submitted', $activity->status);
        $this->assertTrue(Str::isUuid($activity->id));
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $activity->id,
            'action' => 'privacy.processing-activity.submitted',
            'description' => "Activité de traitement {$activity->reference} soumise à un examen indépendant.",
        ]);
        $this->actingAs($submitter)->patch(route('data-governance.processing-activities.review', [$activity]), ['decision' => 'approved', 'review_note' => 'The submitter must not approve this processing activity.'])->assertForbidden();
        $this->actingAs($reviewer)->patch(route('data-governance.processing-activities.review', [$activity]), ['decision' => 'approved', 'review_note' => 'Sensitive processing has not completed the mandatory DPIA review.'])->assertStatus(409);
        $activity->update(['dpia_status' => 'completed', 'dpia_reference' => 'DPIA-IDMIS-2026-001']);
        $this->actingAs($reviewer)->patch(route('data-governance.processing-activities.review', [$activity]), ['decision' => 'approved', 'review_note' => 'DPIA, retention, transfer and technical safeguards have been independently reviewed.'])->assertRedirect();
        $this->assertSame('approved', $activity->refresh()->status);
        $this->assertSame($reviewer->id, $activity->reviewed_by);
        $this->assertStringContainsString('Examen indépendant : DPIA, retention, transfer and technical safeguards have been independently reviewed.', (string) $activity->risk_summary);
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $activity->id,
            'action' => 'privacy.processing-activity.reviewed',
            'description' => "Activité de traitement {$activity->reference} approuvée.",
        ]);
    }

    public function test_data_subject_request_identity_and_decision_are_separated_encrypted_and_exports_exclude_identifiers(): void
    {
        $identityVerifier = User::factory()->devolutionAdmin()->create();
        $decisionMaker = User::factory()->platformAdmin()->create();
        $viewer = User::factory()->topManagement()->create();
        $this->withSession(['locale' => 'fr']);

        $this->actingAs($identityVerifier)->post(route('data-governance.data-subject-requests.store'), ['assigned_to' => $decisionMaker->id, 'request_type' => 'access', 'requester_name' => 'Protected Citizen', 'requester_contact' => 'citizen@example.test', 'contact_channel' => 'email', 'scope' => 'All personal information connected to citizen feedback reference CFM-2026-001.', 'received_at' => now()->subHour()->toIso8601String()])->assertRedirect();
        $privacyRequest = DataSubjectRequest::query()->sole();
        $this->assertTrue(Str::isUuid($privacyRequest->id));
        $this->assertSame('Protected Citizen', $privacyRequest->requester_name);
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $privacyRequest->id,
            'action' => 'privacy.data-subject-request.received',
            'description' => "Demande de confidentialité {$privacyRequest->reference} reçue.",
        ]);
        $raw = DataSubjectRequest::query()->toBase()->where('id', $privacyRequest->id)->first();
        $this->assertStringNotContainsString('Protected Citizen', (string) $raw?->requester_name);
        $this->assertStringNotContainsString('citizen@example.test', (string) $raw?->requester_contact);

        $this->actingAs($identityVerifier)->patch(route('data-governance.data-subject-requests.advance', [$privacyRequest]), ['transition' => 'verify_identity', 'identity_evidence_reference' => 'IDV-SEALED-2026-001'])->assertRedirect();
        $this->actingAs($decisionMaker)->patch(route('data-governance.data-subject-requests.advance', [$privacyRequest]), ['transition' => 'start_review'])->assertRedirect();
        $decision = ['transition' => 'complete', 'decision' => 'A protected export was supplied through the approved response channel.', 'decision_reason' => 'Identity and scope were verified and no lawful restriction prevented access.', 'response_evidence_reference' => 'DSR-RESPONSE-2026-001'];
        $this->actingAs($identityVerifier)->patch(route('data-governance.data-subject-requests.advance', [$privacyRequest]), $decision)->assertForbidden();
        $this->actingAs($decisionMaker)->patch(route('data-governance.data-subject-requests.advance', [$privacyRequest]), $decision)->assertRedirect();
        $this->assertSame('completed', $privacyRequest->refresh()->status);
        $this->assertSame($decisionMaker->id, $privacyRequest->decided_by);
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $privacyRequest->id,
            'action' => 'privacy.data-subject-request.complete',
            'description' => "Demande de confidentialité {$privacyRequest->reference} passée à l’état terminée.",
        ]);

        ProcessingActivity::factory()->create();
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $response = $this->actingAs($viewer)->get(route('workspace.export', ['data-governance', $format]));
            $response->assertOk()->assertDownload();
            if ($format === 'json') {
                $this->assertStringNotContainsString('Protected Citizen', $response->streamedContent());
            }
        }
        $this->actingAs($viewer)->get(route('data-governance.index'))->assertOk()->assertInertia(fn ($page) => $page->where('capabilities.manage', false)->where('dataSubjectRequests.data.0.requesterContact', 'Restreint'));
    }

    public function test_privacy_actions_enforce_permissions_and_reject_unknown_transitions_before_mutation(): void
    {
        $unauthorizedActor = User::factory()->create();
        $unauthorizedActor->syncRoles([]);
        $manager = User::factory()->platformAdmin()->create();
        $privacyRequest = DataSubjectRequest::factory()->create(['status' => 'received']);
        $incident = PrivacyIncident::factory()->create(['status' => 'reported']);

        app()->setLocale('fr');
        $this->assertHttpAction(
            fn () => app(AdvanceDataSubjectRequest::class)->handle($privacyRequest, $unauthorizedActor, []),
            403,
            'Vous n’êtes pas autorisé à faire progresser les demandes des personnes concernées.',
        );
        $this->assertHttpAction(
            fn () => app(AdvancePrivacyIncident::class)->handle($incident, $unauthorizedActor, []),
            403,
            'Vous n’êtes pas autorisé à faire progresser les incidents de confidentialité.',
        );
        $this->assertHttpAction(
            fn () => app(AdvanceDataSubjectRequest::class)->handle($privacyRequest, $manager, ['transition' => 'invalid']),
            422,
            'Transition de demande de personne concernée inconnue.',
        );
        $this->assertHttpAction(
            fn () => app(AdvancePrivacyIncident::class)->handle($incident, $manager, ['transition' => 'invalid']),
            422,
            'Transition d’incident de confidentialité inconnue.',
        );

        $this->assertSame('received', $privacyRequest->refresh()->status);
        $this->assertSame('reported', $incident->refresh()->status);
        $this->assertDatabaseCount('audit_events', 0);
    }

    /** @return array<string, mixed> */
    private function assetPayload(User $owner, User $steward): array
    {
        return ['data_owner_id' => $owner->id, 'steward_id' => $steward->id, 'code' => 'DA-CITIZEN-CASES', 'name' => 'Citizen feedback and grievance register', 'description' => 'Purpose-bound citizen submissions, workflow decisions, correspondence and service performance metadata.', 'module' => 'Citizen Feedback and Grievance Redress', 'authoritative_source' => 'Citizen submission and State Department case workflow', 'classification' => 'confidential', 'contains_personal_data' => true, 'contains_sensitive_personal_data' => false, 'personal_data_categories' => 'name, official_email, employee_reference', 'data_subject_categories' => 'citizens, county_officials', 'storage_locations' => 'postgresql, private_object_storage', 'residency_country' => 'KE', 'quality_standard' => 'Complete, accurate, time-stamped and traceable to the intake source.'];
    }

    /** @return array<string, mixed> */
    private function retentionPayload(): array
    {
        return ['code' => 'RET-CITIZEN-001', 'record_class' => 'Citizen case records', 'trigger_event' => 'Final closure of the citizen case and any associated appeal', 'retention_months' => 84, 'disposition_action' => 'review_then_destroy', 'legal_authority' => 'Draft schedule pending State Department records authority approval', 'legal_hold_rule' => 'Suspend disposition while litigation, investigation, audit or a lawful hold is active.', 'next_review_at' => now()->addYear()->toDateString()];
    }

    /** @return array<string, mixed> */
    private function processingPayload(DataAsset $asset, RetentionSchedule $schedule): array
    {
        return ['data_asset_id' => $asset->id, 'retention_schedule_id' => $schedule->id, 'reference' => 'ROPA-IDMIS-CFM-001', 'name' => 'Citizen case intake and resolution', 'purpose' => 'Receive, route, investigate and resolve citizen feedback and grievances about devolution services.', 'lawful_basis' => 'public_task', 'lawful_basis_reference' => 'Official-authority assessment reference LEG-IDMIS-001 pending DPO validation.', 'controller_name' => 'State Department for Devolution', 'processor_names' => 'Approved Konza hosting operator', 'recipient_categories' => 'authorized case officers, independent resolution approvers', 'processing_operations' => 'collect, store, classify, route, investigate, respond', 'automated_decision_making' => false, 'cross_border_transfer' => false, 'dpia_status' => 'required', 'risk_summary' => 'Citizen narratives may include sensitive data and require strict purpose limitation and access controls.', 'security_measures' => 'Encryption, county-scoped RBAC, private files, malware quarantine and immutable audit.', 'next_review_at' => now()->addYear()->toDateString()];
    }

    /** @param callable(): mixed $action */
    private function assertHttpAction(callable $action, int $status, string $message): void
    {
        try {
            $action();
            $this->fail('The privacy action did not enforce its guarded boundary.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
