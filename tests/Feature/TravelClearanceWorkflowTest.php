<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\County;
use App\Models\DocumentLink;
use App\Models\Organization;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Support\CanonicalJson;
use Database\Seeders\TravelWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TravelClearanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_uses_itinerary_workflow_and_three_person_separation_of_duties(): void
    {
        $county = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $finance = User::factory()->topManagement()->create();
        $finance->assignedCounties()->attach($county);
        $organization = Organization::factory()->create();
        $sector = Sector::factory()->create();
        $release = $this->publishedReferenceRelease([$county], [$sector], [$organization], $manager);
        $this->seed(TravelWorkflowSeeder::class);

        $payload = $this->validRequest($county);
        $payload['organization_id'] = $organization->id;
        $payload['sector_id'] = $sector->id;
        $this->actingAs($requester)->post(route('travel-clearance.store', $requester->currentTeam->slug), $payload)->assertRedirect();
        $travelRequest = TravelRequest::query()->with('itineraries')->sole();
        $this->assertTrue(Str::isUuid($travelRequest->id));
        $this->assertCount(2, $travelRequest->itineraries);
        $this->assertSame('draft', $travelRequest->status);
        $this->assertNotNull($travelRequest->workflow_instance_id);
        $this->assertSame($release->id, $travelRequest->reference_data_release_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $travelRequest->id, 'action' => 'travel.request.created']);
        $event = AuditEvent::query()->where('subject_id', $travelRequest->id)->where('action', 'travel.request.created')->sole();
        $this->assertSame($release->id, $event->metadata['reference_data_release_id']);
        $this->assertSame($release->checksum, $event->metadata['reference_data_release_checksum']);

        $this->actingAs($requester)->get(route('travel-clearance.index', $requester->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.data.0.referenceRelease', "v{$release->version} · {$release->effective_from?->toDateString()}")
                ->where('requests.data.0.referenceChecksum', $release->checksum));
        foreach (['json', 'csv'] as $format) {
            $export = $this->actingAs($requester)->get(route('workspace.export', [$requester->currentTeam->slug, 'travel-clearance', $format]))
                ->assertOk()
                ->streamedContent();
            $this->assertStringContainsString('Reference release', $export);
            $this->assertStringContainsString("v{$release->version}", $export);
            $this->assertStringContainsString($release->checksum, $export);
        }
        $this->actingAs($requester)->get(route('workspace.export', [$requester->currentTeam->slug, 'travel-clearance', 'xlsx']))->assertOk()->assertDownload();
        $this->actingAs($requester)->get(route('workspace.export', [$requester->currentTeam->slug, 'travel-clearance', 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($requester)->patch(route('travel-clearance.transition', [$requester->currentTeam->slug, $travelRequest]), ['transition' => 'submit', 'rationale' => 'Complete official itinerary submitted.'])->assertRedirect();
        $this->assertSame('manager_review', $travelRequest->refresh()->status);
        $this->actingAs($manager)->patch(route('travel-clearance.transition', [$manager->currentTeam->slug, $travelRequest]), ['transition' => 'manager_approve', 'rationale' => 'Travel is necessary and work-plan aligned.', 'approved_cost' => 72000])->assertRedirect();
        $this->assertSame('finance_review', $travelRequest->refresh()->status);

        $this->actingAs($manager)->patch(route('travel-clearance.transition', [$manager->currentTeam->slug, $travelRequest]), ['transition' => 'finance_clear', 'rationale' => 'Attempted self-clearance.', 'finance_commitment_reference' => 'IFMIS-INVALID'])->assertForbidden();
        $this->actingAs($finance)->patch(route('travel-clearance.transition', [$finance->currentTeam->slug, $travelRequest]), ['transition' => 'finance_clear', 'rationale' => 'Budget and commitment independently reconciled.', 'approved_cost' => 70000, 'finance_commitment_reference' => 'IFMIS-COMMIT-2026-001'])->assertRedirect();

        $travelRequest->refresh();
        $this->assertSame('approved', $travelRequest->status);
        $this->assertSame('confirmed', $travelRequest->integration_status);
        $this->assertSame('IFMIS-COMMIT-2026-001', $travelRequest->finance_commitment_reference);
        $this->assertCount(2, $travelRequest->approvals);
        $this->assertDatabaseHas('travel_approvals', ['travel_request_id' => $travelRequest->id, 'actor_id' => $manager->id, 'stage' => 'manager']);
        $this->assertDatabaseHas('travel_approvals', ['travel_request_id' => $travelRequest->id, 'actor_id' => $finance->id, 'stage' => 'finance']);
    }

    public function test_travel_creation_fails_closed_without_a_complete_effective_reference_release(): void
    {
        $county = County::factory()->create();
        $organization = Organization::factory()->create();
        $sector = Sector::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $approver = User::factory()->devolutionAdmin()->create();
        $this->seed(TravelWorkflowSeeder::class);
        $payload = $this->validRequest($county);
        $payload['organization_id'] = $organization->id;
        $payload['sector_id'] = $sector->id;

        $this->actingAs($requester)->post(route('travel-clearance.store', $requester->currentTeam->slug), $payload)->assertStatus(409);
        $this->assertDatabaseCount('travel_requests', 0);

        $this->publishedReferenceRelease([$county], [$sector], [], $approver);
        $this->actingAs($requester)->post(route('travel-clearance.store', $requester->currentTeam->slug), $payload)->assertSessionHasErrors('organization_id');
        $this->assertDatabaseCount('travel_requests', 0);

        $release = $this->publishedReferenceRelease([$county], [$sector], [$organization], $approver);
        $this->actingAs($requester)->post(route('travel-clearance.store', $requester->currentTeam->slug), $payload)->assertRedirect();
        $this->assertSame($release->id, TravelRequest::query()->sole()->reference_data_release_id);
    }

    public function test_county_requester_cannot_create_travel_for_an_outside_county(): void
    {
        $home = County::factory()->create();
        $outside = County::factory()->create();
        $requester = User::factory()->countyOfficial($home)->create();
        $approver = User::factory()->devolutionAdmin()->create();
        $this->publishedReferenceRelease([$home, $outside], [], [], $approver);
        $this->seed(TravelWorkflowSeeder::class);

        $this->actingAs($requester)->post(route('travel-clearance.store', $requester->currentTeam->slug), $this->validRequest($outside))->assertForbidden();
        $this->assertDatabaseCount('travel_requests', 0);
    }

    public function test_itinerary_validation_rejects_cost_and_date_boundary_failures(): void
    {
        $county = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $this->seed(TravelWorkflowSeeder::class);
        $payload = $this->validRequest($county);
        $payload['estimated_cost'] = 100;
        $payload['itineraries'][0]['departs_at'] = today()->addDay()->setTime(8, 0)->toIso8601String();

        $this->actingAs($requester)->post(route('travel-clearance.store', $requester->currentTeam->slug), $payload)
            ->assertSessionHasErrors(['estimated_cost', 'itineraries.0.departs_at']);
        $this->assertDatabaseCount('travel_requests', 0);
    }

    public function test_county_scope_protects_page_export_and_direct_transition(): void
    {
        $home = County::factory()->create(['name' => 'Visible County']);
        $other = County::factory()->create(['name' => 'Hidden County']);
        $user = User::factory()->countyOfficial($home)->create();
        $visible = TravelRequest::factory()->create(['requester_id' => $user->id, 'created_by' => $user->id, 'county_id' => $home->id, 'reference' => 'TRV-VISIBLE']);
        $hidden = TravelRequest::factory()->create(['county_id' => $other->id, 'reference' => 'TRV-HIDDEN']);

        $this->actingAs($user)->get(route('travel-clearance.index', $user->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('travel-clearance/index')->where('requests.total', 1)->where('requests.data.0.id', $visible->id));
        $content = $this->actingAs($user)->get(route('workspace.export', [$user->currentTeam->slug, 'travel-clearance', 'json']))->assertOk()->streamedContent();
        $this->assertStringContainsString('TRV-VISIBLE', $content);
        $this->assertStringNotContainsString('TRV-HIDDEN', $content);
        $this->actingAs($user)->patch(route('travel-clearance.transition', [$user->currentTeam->slug, $hidden]), ['transition' => 'cancel', 'rationale' => 'Cross-county mutation attempt.'])->assertForbidden();
    }

    public function test_authorized_register_exports_all_required_formats(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyOfficial($county)->create();
        TravelRequest::factory()->create(['requester_id' => $user->id, 'created_by' => $user->id, 'county_id' => $county->id]);

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($user)
                ->get(route('workspace.export', [$user->currentTeam->slug, 'travel-clearance', $format]))
                ->assertOk()
                ->assertDownload();
        }
    }

    public function test_travel_analytics_cover_cost_frequency_destination_and_turnaround_without_scope_leakage(): void
    {
        $home = County::factory()->create(['name' => 'Home County']);
        $other = County::factory()->create(['name' => 'Other County']);
        $user = User::factory()->countyOfficial($home)->create();
        TravelRequest::factory()->create([
            'requester_id' => $user->id,
            'created_by' => $user->id,
            'county_id' => $home->id,
            'reference' => 'TRV-ANALYTICS-1',
            'status' => 'approved',
            'destination_city' => 'Nairobi',
            'destination_country' => 'Kenya',
            'estimated_cost' => 120000,
            'currency' => 'KES',
            'submitted_at' => now()->subHours(12),
            'decided_at' => now(),
        ]);
        TravelRequest::factory()->create([
            'county_id' => $other->id,
            'reference' => 'TRV-HIDDEN-ANALYTICS',
            'status' => 'approved',
            'destination_city' => 'London',
            'destination_country' => 'United Kingdom',
            'estimated_cost' => 900000,
            'currency' => 'KES',
            'submitted_at' => now()->subHours(100),
            'decided_at' => now(),
        ]);

        $this->actingAs($user)->get(route('travel-clearance.index', [$user->currentTeam->slug, 'status' => 'approved']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('analytics.summary.total', 1)
            ->where('analytics.summary.approved', 1)
            ->where('analytics.summary.averageTurnaroundHours', 12)
            ->where('analytics.costs.0.totalCost', 120000)
            ->where('analytics.destinations.0.destination', 'Nairobi, Kenya')
            ->missing('analytics.destinations.1'));
    }

    public function test_travel_deadline_reminders_are_idempotent_and_overdue_decisions_are_audited(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $reviewer = User::factory()->topManagement()->create();
        $reviewer->assignedCounties()->attach($county);
        $travelRequest = TravelRequest::factory()->create([
            'requester_id' => $requester->id,
            'created_by' => $requester->id,
            'county_id' => $county->id,
            'status' => 'manager_review',
            'decision_due_at' => now()->subHour(),
        ]);

        $this->artisan('travel-clearance:send-reminders')->assertSuccessful();
        $this->assertNotNull($travelRequest->refresh()->reminder_sent_at);
        $this->assertNotNull($travelRequest->escalated_at);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $travelRequest->id, 'action' => 'travel.request.escalated']);
        Notification::assertSentToTimes($requester, ProgrammeAlert::class, 1);
        Notification::assertSentToTimes($reviewer, ProgrammeAlert::class, 1);

        $this->artisan('travel-clearance:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($requester, ProgrammeAlert::class, 1);
        Notification::assertSentToTimes($reviewer, ProgrammeAlert::class, 1);
    }

    public function test_travel_requester_can_upload_scanned_or_digital_records_with_private_scoped_preview(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $outsider = User::factory()->countyOfficial($otherCounty)->create();
        $travelRequest = TravelRequest::factory()->create(['requester_id' => $requester->id, 'created_by' => $requester->id, 'county_id' => $county->id, 'status' => 'draft']);

        $this->actingAs($requester)->post(route('travel-clearance.documents.store', [$requester->currentTeam->slug, $travelRequest]), [
            'title' => 'Signed travel authorization',
            'category' => 'Authorization',
            'source_type' => 'scanned',
            'document' => UploadedFile::fake()->image('authorization.jpg'),
        ])->assertRedirect();

        $link = DocumentLink::query()->with('document.currentVersion')->sole();
        $this->assertTrue(Str::isUuid($link->id));
        $this->assertSame($travelRequest->id, $link->subject_id);
        $this->assertNull($link->document->assessment_id);
        $this->assertSame('scanned', $link->document->source_type);
        Storage::disk('local')->assertExists($link->document->path);
        $this->assertNotNull($link->document->currentVersion);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $link->document->id, 'action' => 'document.linked_uploaded']);

        $this->actingAs($requester)->get(route('evidence.preview', [$requester->currentTeam->slug, $link->document]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($requester)->get(route('evidence.index', $requester->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->has('workspace.rows', 1)->where('workspace.rows.0.id', $link->document->id));
        $this->actingAs($outsider)->get(route('evidence.preview', [$outsider->currentTeam->slug, $link->document]))->assertForbidden();

        $travelRequest->update(['status' => 'manager_review']);
        $this->actingAs($requester)->post(route('travel-clearance.documents.store', [$requester->currentTeam->slug, $travelRequest]), ['title' => 'Late document', 'category' => 'Authorization', 'source_type' => 'digital', 'document' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf')])->assertStatus(409);
        $this->assertDatabaseCount('document_links', 1);
    }

    public function test_national_travel_record_remains_visible_only_to_the_requester_and_national_roles(): void
    {
        Storage::fake('local');
        $requesterCounty = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $requester = User::factory()->countyOfficial($requesterCounty)->create();
        $outsider = User::factory()->countyOfficial($outsideCounty)->create();
        $nationalViewer = User::factory()->devolutionAdmin()->create();
        $travelRequest = TravelRequest::factory()->create(['requester_id' => $requester->id, 'created_by' => $requester->id, 'county_id' => null, 'status' => 'draft']);

        $this->actingAs($requester)->post(route('travel-clearance.documents.store', [$requester->currentTeam->slug, $travelRequest]), [
            'title' => 'National mission brief',
            'category' => 'Authorization',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('mission-brief.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        $document = DocumentLink::query()->with('document')->sole()->document;
        $this->assertNull($document->county_id);
        $this->actingAs($requester)->get(route('evidence.index', $requester->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.pagination.total', 1)
            ->where('workspace.rows.0.cells.1', 'National'));
        $this->actingAs($nationalViewer)->get(route('evidence.preview', [$nationalViewer->currentTeam->slug, $document]))->assertOk();
        $this->actingAs($outsider)->get(route('evidence.preview', [$outsider->currentTeam->slug, $document]))->assertForbidden();
        $this->actingAs($outsider)->get(route('evidence.index', $outsider->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.pagination.total', 0));
    }

    /** @return array<string, mixed> */
    private function validRequest(County $county): array
    {
        $departure = today()->addWeeks(2);

        return [
            'county_id' => $county->id, 'travel_type' => 'domestic', 'purpose' => 'County delivery review', 'justification' => 'Review programme implementation, financial controls and delivery evidence.',
            'destination_country' => 'Kenya', 'destination_county' => 'Nairobi', 'destination_city' => 'Nairobi', 'departure_date' => $departure->toDateString(), 'return_date' => $departure->copy()->addDays(2)->toDateString(),
            'estimated_cost' => 72000, 'currency' => 'KES', 'funding_source' => 'Approved programme budget', 'cost_centre' => 'KDSP-OPS', 'hris_employee_reference' => 'IPPD-123456', 'priority' => 'normal',
            'itineraries' => [
                ['origin' => 'County headquarters', 'destination' => 'Nairobi', 'departs_at' => $departure->copy()->setTime(8, 0)->toIso8601String(), 'arrives_at' => $departure->copy()->setTime(10, 0)->toIso8601String(), 'transport_mode' => 'road', 'carrier' => 'Government transport', 'estimated_cost' => 30000],
                ['origin' => 'Nairobi', 'destination' => 'County headquarters', 'departs_at' => $departure->copy()->addDays(2)->setTime(16, 0)->toIso8601String(), 'arrives_at' => $departure->copy()->addDays(2)->setTime(18, 0)->toIso8601String(), 'transport_mode' => 'road', 'carrier' => 'Government transport', 'estimated_cost' => 30000],
            ],
        ];
    }

    /**
     * @param  list<County>  $counties
     * @param  list<Sector>  $sectors
     * @param  list<Organization>  $organizations
     */
    private function publishedReferenceRelease(array $counties, array $sectors, array $organizations, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => collect($organizations)->map(fn (Organization $organization): array => ['id' => $organization->id])->all(),
            'sectors' => collect($sectors)->map(fn (Sector $sector): array => ['id' => $sector->id])->all(),
            'programmes' => [],
            'programme_county_coverages' => [],
        ];
        $version = ((int) ReferenceDataRelease::query()->max('version')) + 1;

        return ReferenceDataRelease::factory()->create([
            'version' => $version,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-TRAVEL-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
