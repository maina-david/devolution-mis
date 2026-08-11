<?php

namespace Tests\Feature;

use App\Actions\CreateIgrResolution;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\DocumentLink;
use App\Models\IgrForum;
use App\Models\IgrForumMeeting;
use App\Models\IgrGapCategory;
use App\Models\IgrResolution;
use App\Models\IgrResolutionAssignment;
use App\Models\IgrResolutionDependency;
use App\Models\IgrResolutionGap;
use App\Models\Organization;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Support\CanonicalJson;
use Database\Seeders\IgrWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IgrResolutionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_registers_a_uniquely_identified_resolution_with_responsible_parties_and_workflow(): void
    {
        $county = County::factory()->create(['logo_path' => '/images/counties/mombasa.webp']);
        $administrator = User::factory()->devolutionAdmin()->create();
        $responsible = User::factory()->countyOfficial($county)->create();
        $release = $this->publishedReferenceRelease([$county], [], $administrator);
        $this->seed(IgrWorkflowSeeder::class);
        $forum = IgrForum::factory()->create(['created_by' => $administrator->id]);

        $this->actingAs($administrator)->post(route('igr-resolutions.store', $administrator->currentTeam->slug), [
            'igr_forum_id' => $forum->id, 'resolution_number' => 'IGR/2026/001', 'title' => 'Harmonize conditional grant reporting',
            'resolution_text' => 'Adopt and operate a single reconciliation calendar across national and county government institutions.',
            'resolved_on' => today()->subWeek()->toDateString(), 'due_on' => today()->addMonth()->toDateString(), 'priority' => 'high',
            'assignments' => [['user_id' => $responsible->id, 'county_id' => $county->id, 'responsibility_role' => 'lead', 'is_lead' => true]],
        ])->assertRedirect();

        $resolution = IgrResolution::query()->sole();
        $this->assertTrue(Str::isUuid($resolution->id));
        $this->assertSame('open', $resolution->status);
        $this->assertNotNull($resolution->workflow_instance_id);
        $this->assertSame($release->id, $resolution->reference_data_release_id);
        $this->assertTrue($resolution->assignments()->where('user_id', $responsible->id)->where('is_lead', true)->exists());
        $this->assertDatabaseHas('audit_events', ['subject_id' => $resolution->id, 'action' => 'igr.resolution.created']);
        $event = AuditEvent::query()->where('subject_id', $resolution->id)->where('action', 'igr.resolution.created')->sole();
        $this->assertSame($release->id, $event->metadata['reference_data_release_id']);
        $this->assertSame($release->checksum, $event->metadata['reference_data_release_checksum']);
        $this->actingAs($administrator)
            ->get(route('igr-resolutions.index', $administrator->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('resolutions.0.assignments.0.county.kind', 'county')
                ->where('resolutions.0.assignments.0.county.logoUrl', '/images/counties/mombasa.webp')
                ->where('resolutions.0.referenceRelease', "v{$release->version} · {$release->effective_from?->toDateString()}")
                ->where('resolutions.0.referenceChecksum', $release->checksum));
        foreach (['json', 'csv'] as $format) {
            $content = $this->actingAs($administrator)->get(route('workspace.export', [$administrator->currentTeam->slug, 'igr-resolutions', $format]))->assertOk()->streamedContent();
            $this->assertStringContainsString('Reference release', $content);
            $this->assertStringContainsString("v{$release->version}", $content);
            $this->assertStringContainsString($release->checksum, $content);
        }
        $this->actingAs($administrator)->get(route('workspace.export', [$administrator->currentTeam->slug, 'igr-resolutions', 'xlsx']))->assertOk()->assertDownload();
        $this->actingAs($administrator)->get(route('workspace.export', [$administrator->currentTeam->slug, 'igr-resolutions', 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_formal_meeting_provenance_is_validated_linked_and_exported(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $responsible = User::factory()->countyOfficial($county)->create();
        $this->publishedReferenceRelease([$county], [], $administrator);
        $this->seed(IgrWorkflowSeeder::class);
        $forum = IgrForum::factory()->create(['created_by' => $administrator->id]);

        $this->actingAs($administrator)->post(route('igr-resolutions.meetings.store', $administrator->currentTeam->slug), [
            'igr_forum_id' => $forum->id,
            'reference' => 'IGR/SUMMIT/2026/04',
            'title' => 'Fourth formal summit sitting',
            'held_on' => today()->subDays(2)->toDateString(),
            'venue' => 'Council Chamber, Nairobi',
            'chair_user_id' => $administrator->id,
            'quorum_confirmed' => true,
            'minutes_reference' => 'DMS/IGR/MIN/2026/04',
        ])->assertRedirect();

        $meeting = IgrForumMeeting::query()->sole();
        $this->assertTrue(Str::isUuid($meeting->id));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $meeting->id, 'action' => 'igr.forum.meeting_recorded']);

        $payload = [
            'igr_forum_id' => $forum->id,
            'igr_forum_meeting_id' => $meeting->id,
            'resolution_number' => 'IGR/2026/MEETING/001',
            'title' => 'Adopt shared implementation standard',
            'resolution_text' => 'Adopt the shared implementation standard approved by the formally constituted forum meeting.',
            'resolved_on' => today()->subDay()->toDateString(),
            'due_on' => today()->addMonth()->toDateString(),
            'priority' => 'high',
            'assignments' => [['user_id' => $responsible->id, 'county_id' => $county->id, 'responsibility_role' => 'lead', 'is_lead' => true]],
        ];
        $this->actingAs($administrator)->post(route('igr-resolutions.store', $administrator->currentTeam->slug), $payload)->assertRedirect();

        $resolution = IgrResolution::query()->sole();
        $this->assertTrue($resolution->meeting->is($meeting));
        $this->actingAs($administrator)->get(route('igr-resolutions.index', $administrator->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('resolutions.0.meeting.reference', 'IGR/SUMMIT/2026/04')
            ->where('resolutions.0.meeting.quorumConfirmed', true)
            ->where('resolutions.0.meeting.minutesReference', 'DMS/IGR/MIN/2026/04'));
        $export = $this->actingAs($administrator)->get(route('workspace.export', [$administrator->currentTeam->slug, 'igr-resolutions', 'json']))->assertOk()->streamedContent();
        $decodedExport = json_decode($export, true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('IGR/SUMMIT/2026/04', $decodedExport['rows'][0][4]);
        $this->assertStringContainsString('DMS/IGR/MIN/2026/04', $decodedExport['rows'][0][4]);

        $nonQuorumMeeting = IgrForumMeeting::factory()->for($forum, 'forum')->create(['quorum_confirmed' => false, 'created_by' => $administrator->id]);
        $this->actingAs($administrator)->post(route('igr-resolutions.store', $administrator->currentTeam->slug), [...$payload, 'igr_forum_meeting_id' => $nonQuorumMeeting->id, 'resolution_number' => 'IGR/2026/MEETING/002'])->assertSessionHasErrors('igr_forum_meeting_id');

        $otherForum = IgrForum::factory()->create(['created_by' => $administrator->id]);
        $otherMeeting = IgrForumMeeting::factory()->for($otherForum, 'forum')->create(['created_by' => $administrator->id]);
        $this->actingAs($administrator)->post(route('igr-resolutions.store', $administrator->currentTeam->slug), [...$payload, 'igr_forum_meeting_id' => $otherMeeting->id, 'resolution_number' => 'IGR/2026/MEETING/003'])->assertSessionHasErrors('igr_forum_meeting_id');
    }

    public function test_resolution_dependencies_reject_duplicates_self_links_and_cycles(): void
    {
        $administrator = User::factory()->devolutionAdmin()->create();
        $first = IgrResolution::factory()->create(['resolution_number' => 'IGR/DEP/A']);
        $second = IgrResolution::factory()->create(['resolution_number' => 'IGR/DEP/B']);
        $third = IgrResolution::factory()->create(['resolution_number' => 'IGR/DEP/C']);
        $dependency = ['dependency_type' => 'blocks', 'rationale' => 'This prerequisite must be completed before the dependent commitment can close.'];

        $this->actingAs($administrator)->post(route('igr-resolutions.dependencies.store', [$administrator->currentTeam->slug, $first]), [...$dependency, 'prerequisite_resolution_id' => $second->id])->assertRedirect();
        $this->actingAs($administrator)->post(route('igr-resolutions.dependencies.store', [$administrator->currentTeam->slug, $second]), [...$dependency, 'prerequisite_resolution_id' => $third->id])->assertRedirect();
        $this->assertCount(2, IgrResolutionDependency::all());
        $this->assertDatabaseHas('audit_events', ['subject_type' => IgrResolutionDependency::class, 'action' => 'igr.resolution.dependency_created']);

        $this->actingAs($administrator)->post(route('igr-resolutions.dependencies.store', [$administrator->currentTeam->slug, $third]), [...$dependency, 'prerequisite_resolution_id' => $first->id])->assertSessionHasErrors('prerequisite_resolution_id');
        $this->actingAs($administrator)->post(route('igr-resolutions.dependencies.store', [$administrator->currentTeam->slug, $first]), [...$dependency, 'prerequisite_resolution_id' => $first->id])->assertStatus(422);
        $this->actingAs($administrator)->post(route('igr-resolutions.dependencies.store', [$administrator->currentTeam->slug, $first]), [...$dependency, 'prerequisite_resolution_id' => $second->id])->assertStatus(422);
        $this->assertCount(2, IgrResolutionDependency::all());

        $this->actingAs($administrator)->get(route('igr-resolutions.index', $administrator->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('resolutions', fn ($resolutions) => collect($resolutions)->contains(fn (array $resolution): bool => $resolution['id'] === $first->id && $resolution['dependencies'][0]['resolutionId'] === $second->id)));
        $export = $this->actingAs($administrator)->get(route('workspace.export', [$administrator->currentTeam->slug, 'igr-resolutions', 'json']))->assertOk()->streamedContent();
        $decodedExport = json_decode($export, true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue(collect($decodedExport['rows'])->contains(fn (array $row): bool => $row[7] === 'IGR/DEP/B (open)'));
    }

    public function test_governed_gap_taxonomy_lifecycle_analytics_scope_and_exports(): void
    {
        [$county, $resolution, $responsible] = $this->resolutionFixture();
        $administrator = User::factory()->devolutionAdmin()->create();

        $this->actingAs($administrator)->post(route('igr-resolutions.gap-categories.store', $administrator->currentTeam->slug), [
            'code' => 'DATA-QUALITY',
            'name' => 'Data quality and interoperability',
            'description' => 'Constraints affecting completeness, consistency, reconciliation or exchange of implementation data.',
            'default_severity' => 'high',
        ])->assertRedirect();
        $category = IgrGapCategory::query()->sole();
        $this->assertTrue(Str::isUuid($category->id));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $category->id, 'action' => 'igr.gap_category.created']);

        $this->actingAs($responsible)->post(route('igr-resolutions.gaps.store', [$responsible->currentTeam->slug, $resolution]), [
            'igr_gap_category_id' => $category->id,
            'county_id' => $county->id,
            'owner_user_id' => $responsible->id,
            'title' => 'Legacy extracts fail reconciliation checks',
            'description' => 'Two legacy finance extracts contain inconsistent identifiers and cannot pass automated reconciliation.',
            'impact' => 'The inconsistency delays consolidated reporting and prevents dependable implementation status calculation.',
            'severity' => 'critical',
            'due_on' => today()->addWeek()->toDateString(),
        ])->assertRedirect();

        $gap = IgrResolutionGap::query()->sole();
        $this->assertTrue(Str::isUuid($gap->id));
        $this->assertSame('open', $gap->status);
        $this->assertSame($gap->title, $resolution->refresh()->implementation_gap);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $gap->id, 'action' => 'igr.resolution.gap_reported']);
        $this->actingAs($responsible)->get(route('igr-resolutions.index', $responsible->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('gapAnalytics.summary.total', 1)
            ->where('gapAnalytics.summary.critical', 1)
            ->where('resolutions.0.gaps.0.category', 'DATA-QUALITY · Data quality and interoperability')
            ->where('gapWorkspace.rows.0.cells.3.kind', 'county')
            ->where('gapWorkspace.rows.0.cells.3.id', $county->id));
        $export = $this->actingAs($responsible)->get(route('workspace.export', [$responsible->currentTeam->slug, 'igr-gaps', 'json']))->assertOk()->streamedContent();
        $decoded = json_decode($export, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('DATA-QUALITY · Data quality and interoperability', $decoded['rows'][0][2]);
        $this->assertSame($county->id, $decoded['rows'][0][3]['id']);
        $this->assertSame($county->name, $decoded['rows'][0][3]['name']);

        $outsider = User::factory()->countyAdmin(County::factory()->create())->create();
        $this->actingAs($outsider)->get(route('igr-resolutions.index', $outsider->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->where('gapAnalytics.summary.total', 0)->where('gapWorkspace.pagination.total', 0));
        $this->actingAs($outsider)->patch(route('igr-resolutions.gaps.transition', [$outsider->currentTeam->slug, $resolution, $gap]), ['transition' => 'start_mitigation', 'rationale' => 'This cross-county mutation must not pass the resolution scope boundary.'])->assertForbidden();

        $this->actingAs($responsible)->patch(route('igr-resolutions.gaps.transition', [$responsible->currentTeam->slug, $resolution, $gap]), ['transition' => 'start_mitigation', 'rationale' => 'Normalize legacy identifiers and rerun the controlled reconciliation process.'])->assertRedirect();
        $this->assertSame('mitigating', $gap->refresh()->status);
        $this->actingAs($responsible)->patch(route('igr-resolutions.gaps.transition', [$responsible->currentTeam->slug, $resolution, $gap]), ['transition' => 'resolve', 'rationale' => 'Identifiers were normalized and all extracts now pass the controlled reconciliation checks.'])->assertRedirect();
        $this->assertSame('resolved', $gap->refresh()->status);
        $this->actingAs($responsible)->patch(route('igr-resolutions.gaps.transition', [$responsible->currentTeam->slug, $resolution, $gap]), ['transition' => 'accept', 'rationale' => 'The reporter cannot independently accept their own gap resolution.'])->assertForbidden();

        $reviewer = User::factory()->topManagement()->create();
        $reviewer->assignedCounties()->attach($county);
        $this->actingAs($reviewer)->patch(route('igr-resolutions.gaps.transition', [$reviewer->currentTeam->slug, $resolution, $gap]), ['transition' => 'accept', 'rationale' => 'Reconciliation evidence and corrected extracts were independently reviewed and accepted.'])->assertRedirect();
        $this->assertSame('accepted', $gap->refresh()->status);
        $this->assertSame($reviewer->id, $gap->accepted_by);
        $this->assertNull($resolution->refresh()->implementation_gap);
    }

    public function test_responsible_party_reports_gaps_and_evidence_before_independent_closure(): void
    {
        Storage::fake('local');
        [$county, $resolution, $responsible] = $this->resolutionFixture();
        $reviewer = User::factory()->topManagement()->create();
        $reviewer->assignedCounties()->attach($county);

        $this->actingAs($responsible)->post(route('igr-resolutions.documents.store', [$responsible->currentTeam->slug, $resolution]), [
            'record_purpose' => 'resolution', 'title' => 'Signed adopted resolution', 'category' => 'Adopted resolution', 'source_type' => 'scanned',
            'document' => UploadedFile::fake()->create('adopted-resolution.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($responsible)->patch(route('igr-resolutions.transition', [$responsible->currentTeam->slug, $resolution]), ['transition' => 'start', 'comment' => 'Implementation responsibilities and delivery schedule confirmed.'])->assertRedirect();
        $this->actingAs($responsible)->post(route('igr-resolutions.updates.store', [$responsible->currentTeam->slug, $resolution]), ['progress_percentage' => 60, 'narrative' => 'The reporting template is deployed in pilot departments and reconciliation testing is under way.', 'implementation_gap' => 'Two legacy finance extracts require manual mapping.'])->assertRedirect();
        $this->assertSame(60, $resolution->refresh()->progress_percentage);
        $this->assertNotNull($resolution->implementation_gap);
        $this->actingAs($responsible)->post(route('igr-resolutions.updates.store', [$responsible->currentTeam->slug, $resolution]), ['progress_percentage' => 100, 'narrative' => 'All reporting and reconciliation steps have been completed and accepted by the responsible institutions.', 'evidence_reference' => 'IDMIS-DMS/IGR/2026/001 signed reconciliation and acceptance record.'])->assertRedirect();
        $this->actingAs($responsible)->patch(route('igr-resolutions.transition', [$responsible->currentTeam->slug, $resolution]), ['transition' => 'submit_closure', 'comment' => 'A text reference alone must not satisfy the repository evidence gate.'])->assertSessionHasErrors('transition');
        $this->actingAs($responsible)->post(route('igr-resolutions.documents.store', [$responsible->currentTeam->slug, $resolution]), [
            'record_purpose' => 'implementation_evidence', 'title' => 'Signed reconciliation and acceptance record', 'category' => 'Implementation evidence', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('acceptance-record.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $administrator = User::factory()->devolutionAdmin()->create();
        $prerequisite = IgrResolution::factory()->create(['resolution_number' => 'IGR/PREREQUISITE/001', 'status' => 'in_progress']);
        IgrResolutionAssignment::factory()->for($prerequisite, 'resolution')->create(['county_id' => $county->id]);
        $this->actingAs($administrator)->post(route('igr-resolutions.dependencies.store', [$administrator->currentTeam->slug, $resolution]), [
            'prerequisite_resolution_id' => $prerequisite->id,
            'dependency_type' => 'blocks',
            'rationale' => 'The prerequisite institutional approval must close before this dependent commitment can close.',
        ])->assertRedirect();
        $this->actingAs($responsible)->patch(route('igr-resolutions.transition', [$responsible->currentTeam->slug, $resolution]), ['transition' => 'submit_closure', 'comment' => 'Blocking prerequisite remains open and must prevent closure review.'])->assertStatus(409);
        $prerequisite->update(['status' => 'closed']);
        $gap = IgrResolutionGap::factory()->for($resolution, 'resolution')->create(['county_id' => $county->id, 'owner_user_id' => $responsible->id, 'reported_by' => $administrator->id]);
        $this->actingAs($responsible)->patch(route('igr-resolutions.transition', [$responsible->currentTeam->slug, $resolution]), ['transition' => 'submit_closure', 'comment' => 'An unresolved governed gap must prevent closure review.'])->assertStatus(409);
        $this->actingAs($responsible)->patch(route('igr-resolutions.gaps.transition', [$responsible->currentTeam->slug, $resolution, $gap]), ['transition' => 'resolve', 'rationale' => 'The implementation gap was remediated and its effect on delivery has been addressed.'])->assertRedirect();
        $this->actingAs($reviewer)->patch(route('igr-resolutions.gaps.transition', [$reviewer->currentTeam->slug, $resolution, $gap]), ['transition' => 'accept', 'rationale' => 'The gap resolution was independently reviewed against implementation evidence and accepted.'])->assertRedirect();
        $this->actingAs($responsible)->patch(route('igr-resolutions.transition', [$responsible->currentTeam->slug, $resolution]), ['transition' => 'submit_closure', 'comment' => 'Completed resolution submitted with the signed evidence record.'])->assertRedirect();
        $links = DocumentLink::query()->with('document')->where('subject_id', $resolution->id)->orderBy('purpose')->get();
        $this->assertSame(['igr-implementation-evidence', 'igr-resolution-record'], $links->pluck('purpose')->all());
        $links->each(function (DocumentLink $link): void {
            $this->assertSame('clean', $link->document->scan_status);
            Storage::disk('local')->assertExists($link->document->path);
        });
        $this->actingAs($responsible)->get(route('igr-resolutions.index', $responsible->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('resolutions', fn ($resolutions) => collect($resolutions)->contains(fn (array $item): bool => $item['id'] === $resolution->id && count($item['documents']) === 2))
            ->where('workspace.rows', fn ($rows) => collect($rows)->contains(fn (array $row): bool => $row['id'] === $resolution->id && count($row['documents']) === 2)));
        $this->actingAs($responsible)->get(route('evidence.preview', [$responsible->currentTeam->slug, $links->first()->document]))->assertOk();
        $outsideUser = User::factory()->countyAdmin(County::factory()->create())->create();
        $this->actingAs($outsideUser)->get(route('evidence.preview', [$outsideUser->currentTeam->slug, $links->first()->document]))->assertForbidden();
        $this->actingAs($responsible)->get(route('evidence.index', $responsible->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.pagination.total', 2));
        $this->actingAs($responsible)->patch(route('igr-resolutions.transition', [$responsible->currentTeam->slug, $resolution]), ['transition' => 'approve_closure', 'comment' => 'Attempted self-approval must fail separation of duties.'])->assertForbidden();
        $this->actingAs($reviewer)->patch(route('igr-resolutions.transition', [$reviewer->currentTeam->slug, $resolution]), ['transition' => 'approve_closure', 'comment' => 'Completion evidence independently reviewed and resolution closed.'])->assertRedirect();
        $this->assertSame('closed', $resolution->refresh()->status);
        $this->assertSame($reviewer->id, $resolution->closed_by);
        $this->actingAs($responsible)->post(route('igr-resolutions.documents.store', [$responsible->currentTeam->slug, $resolution]), [
            'record_purpose' => 'implementation_evidence', 'title' => 'Late evidence', 'category' => 'Implementation evidence', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
    }

    public function test_county_scope_protects_page_exports_and_direct_resolution_mutations(): void
    {
        $home = County::factory()->create(['name' => 'Visible County']);
        $other = County::factory()->create(['name' => 'Hidden County']);
        $user = User::factory()->countyAdmin($home)->create();
        $visible = IgrResolution::factory()->create(['resolution_number' => 'VISIBLE/001']);
        IgrResolutionAssignment::factory()->for($visible, 'resolution')->create(['user_id' => $user->id, 'county_id' => $home->id]);
        $hidden = IgrResolution::factory()->create(['resolution_number' => 'HIDDEN/001']);
        IgrResolutionAssignment::factory()->for($hidden, 'resolution')->create(['county_id' => $other->id]);

        $this->actingAs($user)->get(route('igr-resolutions.index', $user->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->component('igr-resolutions/index')->where('workspace.pagination.total', 1)->where('workspace.rows.0.id', $visible->id));
        $content = $this->actingAs($user)->get(route('workspace.export', [$user->currentTeam->slug, 'igr-resolutions', 'json']))->assertOk()->streamedContent();
        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('VISIBLE/001', $decoded['rows'][0][0]);
        $this->assertStringNotContainsString('HIDDEN/001', json_encode($decoded, JSON_THROW_ON_ERROR));
        $this->actingAs($user)->post(route('igr-resolutions.updates.store', [$user->currentTeam->slug, $hidden]), ['progress_percentage' => 20, 'narrative' => 'This update attempts to cross a protected county boundary.'])->assertForbidden();
    }

    public function test_resolution_creation_fails_closed_without_complete_county_and_organization_release_lineage(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $responsible = User::factory()->countyOfficial($county)->create();
        $organization = Organization::factory()->create();
        $forum = IgrForum::factory()->create(['created_by' => $administrator->id]);
        $this->seed(IgrWorkflowSeeder::class);
        $payload = $this->resolutionPayload($county, $responsible, $forum, $organization);

        $this->actingAs($administrator)->post(route('igr-resolutions.store', $administrator->currentTeam->slug), $payload)->assertStatus(409);
        $this->assertDatabaseCount('igr_resolutions', 0);

        $this->publishedReferenceRelease([$county], [], $administrator);
        $this->actingAs($administrator)->post(route('igr-resolutions.store', $administrator->currentTeam->slug), $payload)->assertSessionHasErrors('assignments');
        $this->assertDatabaseCount('igr_resolutions', 0);

        $release = $this->publishedReferenceRelease([$county], [$organization], $administrator);
        $this->actingAs($administrator)->post(route('igr-resolutions.store', $administrator->currentTeam->slug), $payload)->assertRedirect();
        $this->assertSame($release->id, IgrResolution::query()->sole()->reference_data_release_id);
    }

    public function test_resolution_action_rejects_an_assignment_outside_the_actor_county_scope(): void
    {
        $homeCounty = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $countyAdministrator = User::factory()->countyAdmin($homeCounty)->create();
        $responsible = User::factory()->countyOfficial($outsideCounty)->create();
        $approver = User::factory()->devolutionAdmin()->create();
        $forum = IgrForum::factory()->create(['created_by' => $approver->id]);
        $this->publishedReferenceRelease([$homeCounty, $outsideCounty], [], $approver);

        $this->expectException(HttpException::class);
        app(CreateIgrResolution::class)->handle($countyAdministrator, $this->resolutionPayload($outsideCounty, $responsible, $forum));
    }

    public function test_due_and_overdue_reminders_are_idempotent(): void
    {
        $responsible = User::factory()->create();
        $resolution = IgrResolution::factory()->create(['due_on' => today()->addDays(3)]);
        IgrResolutionAssignment::factory()->for($resolution, 'resolution')->create(['user_id' => $responsible->id]);
        Notification::fake();

        $this->artisan('igr:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($responsible, ProgrammeAlert::class, 1);
        $this->assertNotNull($resolution->refresh()->reminder_sent_at);
        $this->artisan('igr:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($responsible, ProgrammeAlert::class, 1);
    }

    /** @return array{County, IgrResolution, User} */
    private function resolutionFixture(): array
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $responsible = User::factory()->countyOfficial($county)->create();
        $this->publishedReferenceRelease([$county], [], $administrator);
        $this->seed(IgrWorkflowSeeder::class);
        $forum = IgrForum::factory()->create(['created_by' => $administrator->id]);
        $this->actingAs($administrator)->post(route('igr-resolutions.store', $administrator->currentTeam->slug), ['igr_forum_id' => $forum->id, 'resolution_number' => 'IGR/TEST/001', 'title' => 'Test implementation resolution', 'resolution_text' => 'Implement the agreed intergovernmental coordination action and submit evidence.', 'resolved_on' => today()->subWeek()->toDateString(), 'due_on' => today()->addMonth()->toDateString(), 'priority' => 'high', 'assignments' => [['user_id' => $responsible->id, 'county_id' => $county->id, 'responsibility_role' => 'lead', 'is_lead' => true]]])->assertRedirect();

        return [$county, IgrResolution::query()->sole(), $responsible];
    }

    /** @return array<string, mixed> */
    private function resolutionPayload(County $county, User $responsible, IgrForum $forum, ?Organization $organization = null): array
    {
        $assignments = [['user_id' => $responsible->id, 'county_id' => $county->id, 'responsibility_role' => 'lead', 'is_lead' => true]];
        if ($organization !== null) {
            $assignments[] = ['organization_id' => $organization->id, 'county_id' => $county->id, 'responsibility_role' => 'support', 'is_lead' => false];
        }

        return [
            'igr_forum_id' => $forum->id,
            'resolution_number' => 'IGR/REFERENCE/2026/001',
            'title' => 'Harmonize intergovernmental delivery controls',
            'resolution_text' => 'Adopt the governed delivery and accountability standard across assigned institutions.',
            'resolved_on' => today()->subWeek()->toDateString(),
            'due_on' => today()->addMonth()->toDateString(),
            'priority' => 'high',
            'assignments' => $assignments,
        ];
    }

    /**
     * @param  list<County>  $counties
     * @param  list<Organization>  $organizations
     */
    private function publishedReferenceRelease(array $counties, array $organizations, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => collect($organizations)->map(fn (Organization $organization): array => ['id' => $organization->id])->all(),
            'sectors' => [],
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
            'approval_reference' => 'SDD-MDM-IGR-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
