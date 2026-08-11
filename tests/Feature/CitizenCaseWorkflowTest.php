<?php

namespace Tests\Feature;

use App\Models\CitizenCase;
use App\Models\County;
use App\Models\Organization;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Support\CanonicalJson;
use Database\Seeders\CitizenCaseSeeder;
use Database\Seeders\CitizenCaseWorkflowSeeder;
use Database\Seeders\CountySeeder;
use Database\Seeders\LocalAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CitizenCaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_seed_creates_idempotent_county_scoped_feedback_and_grievance_cases(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->seed([CountySeeder::class, LocalAccessSeeder::class]);
        $administrator = User::query()->where('email', 'devolution.admin@idmis.test')->firstOrFail();
        $this->publishedReferenceRelease(County::query()->get()->all(), $administrator);
        $this->seed([CitizenCaseSeeder::class, CitizenCaseSeeder::class]);

        $this->assertSame(2, CitizenCase::query()->count());
        $this->assertSame(1, CitizenCase::query()->where('case_type', 'feedback')->count());
        $this->assertSame(1, CitizenCase::query()->where('case_type', 'grievance')->count());
        $this->assertTrue(CitizenCase::query()->get()->every(fn (CitizenCase $case): bool => $case->status === 'triaged' && $case->workflow_instance_id !== null && $case->assigned_to !== null));
    }

    public function test_case_is_triaged_assigned_responded_and_independently_resolved(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $handler = User::factory()->countyOfficial($county)->create();
        $reviewer = User::factory()->topManagement()->create();
        $reviewer->assignedCounties()->attach($county);
        $organization = Organization::factory()->create();
        $sector = Sector::factory()->create();
        $release = $this->publishedReferenceRelease([$county], $administrator, [$organization], [$sector]);
        $this->seed(CitizenCaseWorkflowSeeder::class);
        $case = CitizenCase::factory()->create(['county_id' => $county->id, 'case_type' => 'grievance']);

        $this->actingAs($administrator)->patch(route('citizen-cases.triage', [$administrator->currentTeam->slug, $case]), ['assigned_to' => $handler->id, 'assigned_organization_id' => $organization->id, 'sector_id' => $sector->id, 'priority' => 'high', 'is_sensitive' => false, 'triage_note' => 'Validated jurisdiction and assigned the county service-delivery focal point.'])->assertRedirect();
        $this->assertSame('triaged', $case->refresh()->status);
        $this->assertSame($release->id, $case->triage_reference_data_release_id);
        $this->assertSame($organization->id, $case->assigned_organization_id);
        $this->assertSame($sector->id, $case->sector_id);
        $this->assertNotNull($case->workflow_instance_id);
        $this->actingAs($handler)->patch(route('citizen-cases.transition', [$handler->currentTeam->slug, $case]), ['transition' => 'start', 'comment' => 'County records review and stakeholder consultation started.'])->assertRedirect();
        $this->actingAs($handler)->post(route('citizen-cases.messages.store', [$handler->currentTeam->slug, $case]), ['body' => 'We have started reviewing the records and will publish the outcome here.', 'visibility' => 'public'])->assertRedirect();
        $this->assertNotNull($case->refresh()->first_responded_at);
        $this->actingAs($handler)->patch(route('citizen-cases.transition', [$handler->currentTeam->slug, $case]), ['transition' => 'submit_resolution', 'resolution_summary' => 'The delayed service request was validated, corrected in the county register and assigned a confirmed delivery date.', 'comment' => 'Evidence-backed grievance resolution submitted for independent approval.'])->assertRedirect();
        $this->actingAs($handler)->patch(route('citizen-cases.transition', [$handler->currentTeam->slug, $case]), ['transition' => 'approve_resolution', 'comment' => 'Self-approval must be rejected by separation of duties.'])->assertForbidden();
        $this->actingAs($reviewer)->patch(route('citizen-cases.transition', [$reviewer->currentTeam->slug, $case]), ['transition' => 'approve_resolution', 'comment' => 'Resolution and supporting case history independently verified.'])->assertRedirect();
        $this->assertSame('resolved', $case->refresh()->status);
        $this->assertNotNull($case->resolved_at);
    }

    public function test_county_and_sensitive_case_scope_protects_page_export_and_mutations(): void
    {
        $home = County::factory()->create(['name' => 'Visible County', 'logo_path' => '/images/counties/mombasa.webp']);
        $other = County::factory()->create(['name' => 'Hidden County']);
        $official = User::factory()->countyOfficial($home)->create();
        $release = $this->publishedReferenceRelease([$home], User::factory()->devolutionAdmin()->create());
        $visible = CitizenCase::factory()->create(['county_id' => $home->id, 'reference' => 'CFM-VISIBLE', 'intake_reference_data_release_id' => $release->id, 'triage_reference_data_release_id' => $release->id]);
        $sensitive = CitizenCase::factory()->create(['county_id' => $home->id, 'reference' => 'CFM-SENSITIVE', 'is_sensitive' => true]);
        $hidden = CitizenCase::factory()->create(['county_id' => $other->id, 'reference' => 'CFM-HIDDEN']);

        $this->actingAs($official)->get(route('citizen-cases.index', $official->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->component('citizen-cases/index')->where('workspace.pagination.total', 1)->where('workspace.rows.0.id', $visible->id)->where('workspace.rows.0.cells.5', "v{$release->version} · {$release->effective_from?->toDateString()}")->where('workspace.rows.0.cells.6', $release->checksum)->where('cases.0.county.kind', 'county')->where('cases.0.county.logoUrl', '/images/counties/mombasa.webp')->where('cases.0.intakeReferenceData.checksum', $release->checksum)->where('cases.0.triageReferenceData.checksum', $release->checksum));
        $content = $this->actingAs($official)->get(route('workspace.export', [$official->currentTeam->slug, 'citizen-cases', 'json']))->assertOk()->streamedContent();
        $this->assertStringContainsString('CFM-VISIBLE', $content);
        $this->assertStringContainsString($release->checksum, $content);
        $this->assertStringNotContainsString('CFM-SENSITIVE', $content);
        $this->assertStringNotContainsString('CFM-HIDDEN', $content);
        $this->actingAs($official)->post(route('citizen-cases.messages.store', [$official->currentTeam->slug, $hidden]), ['body' => 'Attempted cross-county response.', 'visibility' => 'public'])->assertForbidden();
        $this->assertModelExists($sensitive);
        $this->assertModelExists($hidden);
    }

    public function test_sla_reminders_are_idempotent_and_mark_overdue_cases_escalated(): void
    {
        $county = County::factory()->create();
        $handler = User::factory()->countyOfficial($county)->create();
        User::factory()->countyAdmin($county)->create();
        $case = CitizenCase::factory()->create(['county_id' => $county->id, 'assigned_to' => $handler->id, 'status' => 'in_progress', 'first_response_due_at' => now()->subHour(), 'resolution_due_at' => now()->subHour()]);
        Notification::fake();
        $this->artisan('citizen-cases:send-sla-reminders')->assertSuccessful();
        Notification::assertSentTo($handler, ProgrammeAlert::class);
        $this->assertNotNull($case->refresh()->reminder_sent_at);
        $this->assertNotNull($case->escalated_at);
        $this->artisan('citizen-cases:send-sla-reminders')->assertSuccessful();
        Notification::assertSentToTimes($handler, ProgrammeAlert::class, 1);
    }

    /**
     * @param  list<County>  $counties
     */
    private function publishedReferenceRelease(array $counties, User $approver, array $organizations = [], array $sectors = []): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => collect($organizations)->map(fn (Organization $organization): array => ['id' => $organization->id])->all(),
            'sectors' => collect($sectors)->map(fn (Sector $sector): array => ['id' => $sector->id])->all(),
            'programmes' => [],
            'programme_county_coverages' => [],
        ];

        return ReferenceDataRelease::factory()->create([
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
