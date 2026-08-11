<?php

namespace Tests\Feature;

use App\Models\DataAsset;
use App\Models\DataSubjectRequest;
use App\Models\ProcessingActivity;
use App\Models\RetentionSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_inventory_and_retention_schedule_are_governed_uuid_records(): void
    {
        $manager = User::factory()->devolutionAdmin()->create();
        $owner = User::factory()->platformAdmin()->create();
        $steward = User::factory()->devolutionAdmin()->create();
        $countyUser = User::factory()->countyAdmin()->create();

        $this->actingAs($countyUser)->get(route('data-governance.index', $countyUser->currentTeam->slug))->assertForbidden();
        $this->actingAs($manager)->post(route('data-governance.assets.store', $manager->currentTeam->slug), $this->assetPayload($owner, $steward))->assertRedirect();
        $asset = DataAsset::query()->sole();
        $this->assertTrue(Str::isUuid($asset->id));
        $this->assertSame(['name', 'official_email', 'employee_reference'], $asset->personal_data_categories);
        $this->assertSame(['postgresql', 'private_object_storage'], $asset->storage_locations);

        $this->actingAs($manager)->post(route('data-governance.retention-schedules.store', $manager->currentTeam->slug), $this->retentionPayload())->assertRedirect();
        $schedule = RetentionSchedule::query()->sole();
        $this->assertTrue(Str::isUuid($schedule->id));
        $this->assertSame('approved', $schedule->status);
        $this->assertSame($manager->id, $schedule->approved_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $asset->id, 'action' => 'privacy.data-asset.registered']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $schedule->id, 'action' => 'privacy.retention-schedule.approved']);
        $asset->delete();
        $this->assertSoftDeleted($asset);
    }

    public function test_sensitive_processing_requires_completed_dpia_and_independent_review(): void
    {
        $submitter = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $asset = DataAsset::factory()->create(['contains_sensitive_personal_data' => true]);
        $schedule = RetentionSchedule::factory()->create(['status' => 'approved']);

        $this->actingAs($submitter)->post(route('data-governance.processing-activities.store', $submitter->currentTeam->slug), $this->processingPayload($asset, $schedule))->assertRedirect();
        $activity = ProcessingActivity::query()->sole();
        $this->assertSame('submitted', $activity->status);
        $this->assertTrue(Str::isUuid($activity->id));
        $this->actingAs($submitter)->patch(route('data-governance.processing-activities.review', [$submitter->currentTeam->slug, $activity]), ['decision' => 'approved', 'review_note' => 'The submitter must not approve this processing activity.'])->assertForbidden();
        $this->actingAs($reviewer)->patch(route('data-governance.processing-activities.review', [$reviewer->currentTeam->slug, $activity]), ['decision' => 'approved', 'review_note' => 'Sensitive processing has not completed the mandatory DPIA review.'])->assertStatus(409);
        $activity->update(['dpia_status' => 'completed', 'dpia_reference' => 'DPIA-IDMIS-2026-001']);
        $this->actingAs($reviewer)->patch(route('data-governance.processing-activities.review', [$reviewer->currentTeam->slug, $activity]), ['decision' => 'approved', 'review_note' => 'DPIA, retention, transfer and technical safeguards have been independently reviewed.'])->assertRedirect();
        $this->assertSame('approved', $activity->refresh()->status);
        $this->assertSame($reviewer->id, $activity->reviewed_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $activity->id, 'action' => 'privacy.processing-activity.reviewed']);
    }

    public function test_data_subject_request_identity_and_decision_are_separated_encrypted_and_exports_exclude_identifiers(): void
    {
        $identityVerifier = User::factory()->devolutionAdmin()->create();
        $decisionMaker = User::factory()->platformAdmin()->create();
        $viewer = User::factory()->topManagement()->create();

        $this->actingAs($identityVerifier)->post(route('data-governance.data-subject-requests.store', $identityVerifier->currentTeam->slug), ['assigned_to' => $decisionMaker->id, 'request_type' => 'access', 'requester_name' => 'Protected Citizen', 'requester_contact' => 'citizen@example.test', 'contact_channel' => 'email', 'scope' => 'All personal information connected to citizen feedback reference CFM-2026-001.', 'received_at' => now()->subHour()->toIso8601String()])->assertRedirect();
        $privacyRequest = DataSubjectRequest::query()->sole();
        $this->assertTrue(Str::isUuid($privacyRequest->id));
        $this->assertSame('Protected Citizen', $privacyRequest->requester_name);
        $raw = DataSubjectRequest::query()->toBase()->where('id', $privacyRequest->id)->first();
        $this->assertStringNotContainsString('Protected Citizen', (string) $raw?->requester_name);
        $this->assertStringNotContainsString('citizen@example.test', (string) $raw?->requester_contact);

        $this->actingAs($identityVerifier)->patch(route('data-governance.data-subject-requests.advance', [$identityVerifier->currentTeam->slug, $privacyRequest]), ['transition' => 'verify_identity', 'identity_evidence_reference' => 'IDV-SEALED-2026-001'])->assertRedirect();
        $this->actingAs($decisionMaker)->patch(route('data-governance.data-subject-requests.advance', [$decisionMaker->currentTeam->slug, $privacyRequest]), ['transition' => 'start_review'])->assertRedirect();
        $decision = ['transition' => 'complete', 'decision' => 'A protected export was supplied through the approved response channel.', 'decision_reason' => 'Identity and scope were verified and no lawful restriction prevented access.', 'response_evidence_reference' => 'DSR-RESPONSE-2026-001'];
        $this->actingAs($identityVerifier)->patch(route('data-governance.data-subject-requests.advance', [$identityVerifier->currentTeam->slug, $privacyRequest]), $decision)->assertForbidden();
        $this->actingAs($decisionMaker)->patch(route('data-governance.data-subject-requests.advance', [$decisionMaker->currentTeam->slug, $privacyRequest]), $decision)->assertRedirect();
        $this->assertSame('completed', $privacyRequest->refresh()->status);
        $this->assertSame($decisionMaker->id, $privacyRequest->decided_by);

        ProcessingActivity::factory()->create();
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $response = $this->actingAs($viewer)->get(route('workspace.export', [$viewer->currentTeam->slug, 'data-governance', $format]));
            $response->assertOk()->assertDownload();
            if ($format === 'json') {
                $this->assertStringNotContainsString('Protected Citizen', $response->streamedContent());
            }
        }
        $this->actingAs($viewer)->get(route('data-governance.index', $viewer->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page->where('capabilities.manage', false)->where('dataSubjectRequests.data.0.requesterContact', 'Restricted'));
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
}
