<?php

namespace Tests\Feature;

use App\Models\AssessmentDocument;
use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEvent;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityIncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_exercise_follows_response_playbook_with_independent_evidence_gated_closure(): void
    {
        Storage::fake(config('filesystems.default'));
        $reporter = User::factory()->devolutionAdmin()->create();
        $lead = User::factory()->platformAdmin()->create();
        $closer = User::factory()->devolutionAdmin()->create();
        $countyUser = User::factory()->countyAdmin()->create();
        $detectedAt = now()->subMinutes(5)->startOfMinute();

        $this->actingAs($countyUser)->post(route('security-governance.incidents.store', $countyUser->currentTeam->slug), $this->exercisePayload($lead, $detectedAt))->assertForbidden();
        $this->actingAs($reporter)->post(route('security-governance.incidents.store', $reporter->currentTeam->slug), $this->exercisePayload($lead, $detectedAt))->assertRedirect();
        $incident = SecurityIncident::query()->sole();

        $this->assertTrue(Str::isUuid($incident->id));
        $this->assertStringStartsWith('SEC-EXR-', $incident->reference);
        $this->assertSame('exercise', $incident->record_type);
        $this->assertSame(['identity', 'application gateway'], $incident->affected_services);
        $this->assertSame(15, (int) $incident->detected_at->diffInMinutes($incident->acknowledgement_due_at));
        $this->assertSame(60, (int) $incident->detected_at->diffInMinutes($incident->containment_due_at));
        $this->assertStringNotContainsString('simulated privileged credential', (string) SecurityIncident::query()->toBase()->where('id', $incident->id)->value('summary'));
        $this->assertSame('detect', $incident->events()->sole()->transition);

        $this->actingAs($reporter)->patch(route('security-governance.incidents.transition', [$reporter->currentTeam->slug, $incident]), $this->transition('acknowledge'))->assertForbidden();
        $this->actingAs($lead)->patch(route('security-governance.incidents.transition', [$lead->currentTeam->slug, $incident]), $this->transition('acknowledge'))->assertRedirect();
        $this->actingAs($lead)->patch(route('security-governance.incidents.transition', [$lead->currentTeam->slug, $incident]), $this->transition('contain'))->assertRedirect();
        $this->actingAs($lead)->patch(route('security-governance.incidents.transition', [$lead->currentTeam->slug, $incident]), $this->transition('eradicate', 'DMS-SEC-ERADICATE-001'))->assertRedirect();
        $this->actingAs($lead)->patch(route('security-governance.incidents.transition', [$lead->currentTeam->slug, $incident]), $this->transition('recover', 'MONITORING-RECOVERY-001'))->assertRedirect();
        $this->assertSame('recovered', $incident->refresh()->status);

        $closure = [...$this->transition('close'), 'root_cause' => 'The exercise found that a stale privileged credential could remain active beyond an approved role change.', 'corrective_actions' => 'Reconciled privileged identities, revoked the test credential and assigned automated joiner mover leaver control actions.', 'lessons_learned' => 'The playbook must identify the identity authority, session revocation owner and evidence custodian before containment.', 'exercise_outcome' => 'partially_effective', 'next_exercise_due_at' => now()->addMonths(6)->toIso8601String()];
        $this->actingAs($closer)->patch(route('security-governance.incidents.transition', [$closer->currentTeam->slug, $incident]), $closure)->assertStatus(409);
        $this->actingAs($lead)->post(route('security-governance.incidents.documents.store', [$lead->currentTeam->slug, $incident]), ['record_purpose' => 'closure', 'title' => 'Credential compromise exercise closure pack', 'category' => 'Security incident evidence', 'source_type' => 'scanned', 'document' => UploadedFile::fake()->create('exercise-closure.pdf', 32, 'application/pdf')])->assertRedirect();
        $document = AssessmentDocument::query()->sole();
        $this->assertSame('clean', $document->scan_status);
        $this->actingAs($closer)->get(route('evidence.preview', [$closer->currentTeam->slug, $document]))->assertOk();
        $this->actingAs($lead)->patch(route('security-governance.incidents.transition', [$lead->currentTeam->slug, $incident]), $closure)->assertForbidden();
        $this->actingAs($closer)->patch(route('security-governance.incidents.transition', [$closer->currentTeam->slug, $incident]), $closure)->assertRedirect();

        $incident->refresh();
        $this->assertSame('closed', $incident->status);
        $this->assertSame($closer->id, $incident->closed_by);
        $this->assertSame('partially_effective', $incident->exercise_outcome);
        $this->assertCount(6, $incident->events);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $incident->id, 'action' => 'security.incident.close']);
        $this->actingAs($reporter)->get(route('security-governance.index', $reporter->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page->where('securityIncidents.total', 1)->where('securityIncidents.data.0.documents.0.id', $document->id)->where('securityIncidents.data.0.events.5.transition', 'close'));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($reporter)->get(route('workspace.export', [$reporter->currentTeam->slug, 'security-incidents', $format]))->assertDownload();
        }

        $event = SecurityIncidentEvent::query()->latest('occurred_at')->firstOrFail();
        $this->expectException(QueryException::class);
        $event->update(['to_status' => 'tampered']);
    }

    public function test_overdue_response_escalation_is_idempotent_and_retains_system_event(): void
    {
        Notification::fake();
        $lead = User::factory()->platformAdmin()->create();
        $incident = SecurityIncident::factory()->create(['incident_lead_id' => $lead->id, 'status' => 'detected', 'detected_at' => now()->subHour(), 'acknowledgement_due_at' => now()->subMinutes(30), 'containment_due_at' => now()->addHours(2)]);

        $this->assertSame(0, Artisan::call('security:escalate-incidents'));
        $this->assertNotNull($incident->refresh()->escalated_at);
        Notification::assertSentTo($lead, ProgrammeAlert::class);
        $this->assertDatabaseHas('security_incident_events', ['security_incident_id' => $incident->id, 'transition' => 'sla_escalated', 'actor_name' => 'system:security-incident-monitor']);
        $this->assertSame(0, Artisan::call('security:escalate-incidents'));
        $this->assertSame(1, $incident->events()->where('transition', 'sla_escalated')->count());
        Notification::assertSentToTimes($lead, ProgrammeAlert::class, 1);
    }

    /** @return array<string, mixed> */
    private function exercisePayload(User $lead, mixed $detectedAt): array
    {
        return ['incident_lead_id' => $lead->id, 'record_type' => 'exercise', 'playbook' => 'credential_compromise', 'title' => 'Privileged identity compromise tabletop exercise', 'summary' => 'A simulated privileged credential is used to test detection, session revocation, evidence custody and controlled recovery.', 'affected_services' => 'identity, application gateway', 'data_exposure' => 'none', 'severity' => 'sev1', 'business_impact' => 'The controlled scenario tests whether administrators can contain identity misuse before material service or data impact.', 'external_reference' => 'EXERCISE-2026-001', 'exercise_objectives' => 'detect compromised session, revoke credential, preserve evidence, recover service', 'detected_at' => $detectedAt->toIso8601String()];
    }

    /** @return array<string, mixed> */
    private function transition(string $transition, ?string $reference = null): array
    {
        return ['transition' => $transition, 'narrative' => ucfirst($transition).' actions were completed under the assigned playbook with accountable ownership and retained evidence.', 'evidence_reference' => $reference];
    }
}
