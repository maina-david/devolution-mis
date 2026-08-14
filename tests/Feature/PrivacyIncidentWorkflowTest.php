<?php

namespace Tests\Feature;

use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DataAsset;
use App\Models\PrivacyIncident;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivacyIncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifiable_breach_requires_independent_assessment_notification_evidence_and_independent_closure(): void
    {
        Storage::fake(config('filesystems.default'));
        $reporter = User::factory()->devolutionAdmin()->create();
        $assessor = User::factory()->platformAdmin()->create();
        $closer = User::factory()->devolutionAdmin()->create();
        $this->withSession(['locale' => 'sw']);
        $county = County::factory()->create();
        $asset = DataAsset::factory()->create(['contains_sensitive_personal_data' => true]);
        $discoveredAt = now()->subHours(2)->startOfMinute();

        $this->actingAs($reporter)->post(route('data-governance.privacy-incidents.store'), [
            'data_asset_id' => $asset->id,
            'county_id' => $county->id,
            'incident_lead_id' => $reporter->id,
            'title' => 'Unauthorized citizen-case repository access',
            'controller_role' => 'controller',
            'breach_type' => 'confidentiality',
            'description' => 'A compromised credential was used to access protected citizen-case records outside the approved county scope.',
            'personal_data_categories' => 'identity, contact, case narrative',
            'estimated_data_subjects' => 42,
            'contains_sensitive_data' => true,
            'occurred_at' => $discoveredAt->copy()->subHour()->toIso8601String(),
            'discovered_at' => $discoveredAt->toIso8601String(),
        ])->assertRedirect();

        $incident = PrivacyIncident::query()->sole();
        $this->assertTrue(Str::isUuid($incident->id), "Incident ID {$incident->id} must be a UUID.");
        $this->assertSame(['identity', 'contact', 'case narrative'], $incident->personal_data_categories);
        $this->assertSame(72, (int) $incident->discovered_at->diffInHours($incident->regulator_notification_due_at));
        $this->assertNull($incident->controller_notification_due_at);
        $this->assertStringNotContainsString('compromised credential', (string) PrivacyIncident::query()->toBase()->where('id', $incident->id)->value('description'));

        $this->actingAs($reporter)->patch(route('data-governance.privacy-incidents.advance', [$incident]), ['transition' => 'contain', 'containment_actions' => 'Revoked the compromised credential, invalidated sessions and isolated the affected service for forensic review.'])->assertRedirect();
        $assessment = ['transition' => 'assess', 'severity' => 'high', 'real_risk_of_harm' => 'yes', 'risk_assessment' => 'Identity and case narratives were exposed to an unauthorized actor, creating a credible risk of discrimination, distress and further misuse.'];
        $this->actingAs($reporter)->patch(route('data-governance.privacy-incidents.advance', [$incident]), $assessment)->assertForbidden();
        $this->actingAs($assessor)->patch(route('data-governance.privacy-incidents.advance', [$incident]), $assessment)->assertRedirect();
        $this->assertSame('notification_required', $incident->refresh()->status);
        $this->assertSame($assessor->id, $incident->assessed_by);

        $notification = ['transition' => 'record_notifications', 'regulator_notified_at' => now()->toIso8601String(), 'regulator_notification_reference' => 'ODPC-PBI-2026-001', 'subject_notification_decision' => 'notified', 'data_subjects_notified_at' => now()->toIso8601String()];
        $this->actingAs($reporter)->patch(route('data-governance.privacy-incidents.advance', [$incident]), $notification)->assertStatus(409);
        $this->actingAs($reporter)->post(route('data-governance.privacy-incidents.documents.store', [$incident]), ['record_purpose' => 'notification', 'title' => 'ODPC and affected-person notification record', 'category' => 'Personal data breach evidence', 'source_type' => 'digital', 'document' => UploadedFile::fake()->create('notification.pdf', 24, 'application/pdf')])->assertRedirect();
        $this->actingAs($reporter)->patch(route('data-governance.privacy-incidents.advance', [$incident]), $notification)->assertRedirect();
        $closure = ['transition' => 'close', 'root_cause' => 'A privileged credential remained active after the responsible operator changed duties.', 'remediation_actions' => 'Rotated privileged credentials, reviewed access grants, introduced expiry controls and completed affected-record reconciliation.', 'closure_evidence_reference' => 'DMS-PBI-CLOSURE-2026-001'];
        $this->actingAs($assessor)->patch(route('data-governance.privacy-incidents.advance', [$incident]), $closure)->assertForbidden();
        $this->actingAs($closer)->patch(route('data-governance.privacy-incidents.advance', [$incident]), $closure)->assertStatus(409);
        $this->actingAs($reporter)->post(route('data-governance.privacy-incidents.documents.store', [$incident]), ['record_purpose' => 'closure', 'title' => 'Verified breach remediation closure pack', 'category' => 'Personal data breach evidence', 'source_type' => 'scanned', 'document' => UploadedFile::fake()->create('closure.pdf', 32, 'application/pdf')])->assertRedirect();
        $document = AssessmentDocument::query()->latest()->firstOrFail();
        $this->assertSame(2, AssessmentDocument::query()->count());
        $this->assertSame('clean', $document->scan_status);
        $this->actingAs($reporter)->get(route('evidence.preview', [$document]))->assertOk();
        $this->actingAs($closer)->patch(route('data-governance.privacy-incidents.advance', [$incident]), $closure)->assertRedirect();

        $this->assertSame('closed', $incident->refresh()->status);
        $this->assertSame($closer->id, $incident->closed_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $incident->id, 'action' => 'privacy.incident.reported', 'description' => "Tukio la faragha {$incident->reference} limeripotiwa."]);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $incident->id, 'action' => 'privacy.incident.close', 'description' => "Tukio la faragha {$incident->reference} limehamishwa hadi imefungwa."]);
    }

    public function test_processor_deadline_non_notifiable_path_escalation_scope_and_exports_are_controlled(): void
    {
        Notification::fake();
        $reporter = User::factory()->devolutionAdmin()->create();
        $assessor = User::factory()->platformAdmin()->create();
        $countyUser = User::factory()->countyAdmin()->create();
        $county = County::factory()->create();
        $discoveredAt = now()->subHours(73)->startOfMinute();
        $incident = PrivacyIncident::factory()->create(['county_id' => $county->id, 'reported_by' => $reporter->id, 'incident_lead_id' => $reporter->id, 'controller_role' => 'processor', 'discovered_at' => $discoveredAt, 'controller_notification_due_at' => $discoveredAt->copy()->addHours(48), 'regulator_notification_due_at' => $discoveredAt->copy()->addHours(72), 'status' => 'contained', 'contained_at' => $discoveredAt->copy()->addHour()]);

        $this->assertTrue($incident->controller_notification_due_at?->equalTo($discoveredAt->copy()->addHours(48)));
        $this->actingAs($assessor)->patch(route('data-governance.privacy-incidents.advance', [$incident]), ['transition' => 'assess', 'severity' => 'low', 'real_risk_of_harm' => 'no', 'risk_assessment' => 'The encrypted backup fragment was unavailable briefly but no unauthorized person acquired or accessed personal data.'])->assertRedirect();
        $this->assertSame('remediation', $incident->refresh()->status);
        $this->assertSame('undetermined', $incident->subject_notification_decision);

        $overdue = PrivacyIncident::factory()->create(['county_id' => $county->id, 'reported_by' => $reporter->id, 'incident_lead_id' => $reporter->id, 'discovered_at' => $discoveredAt, 'regulator_notification_due_at' => $discoveredAt->copy()->addHours(72), 'status' => 'notification_required', 'severity' => 'high', 'real_risk_of_harm' => 'yes']);
        $this->assertSame(0, Artisan::call('privacy:escalate-breach-deadlines'));
        $this->assertNull($incident->refresh()->escalated_at);
        $this->assertNotNull($overdue->refresh()->escalated_at);
        Notification::assertSentTo($reporter, ProgrammeAlert::class);
        $this->assertSame(0, Artisan::call('privacy:escalate-breach-deadlines'));
        Notification::assertSentToTimes($reporter, ProgrammeAlert::class, 1);

        $this->actingAs($countyUser)->get(route('data-governance.index'))->assertForbidden();
        $this->actingAs($reporter)->get(route('data-governance.index'))->assertOk()->assertInertia(fn ($page) => $page->where('privacyIncidents.total', 2));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($reporter)->get(route('workspace.export', ['privacy-incidents', $format]))->assertDownload();
        }
    }
}
