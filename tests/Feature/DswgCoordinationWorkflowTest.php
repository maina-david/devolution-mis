<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\DocumentLink;
use App\Models\DswgAction;
use App\Models\DswgMeeting;
use App\Models\DswgMeetingSeries;
use App\Models\DswgWorkingGroup;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Support\CanonicalJson;
use Database\Seeders\DswgMeetingSeriesSeeder;
use Database\Seeders\DswgWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DswgCoordinationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_schedules_meeting_records_quorum_and_requires_independent_minutes_approval(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $sector = Sector::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $approver = User::factory()->devolutionAdmin()->create();
        $member = User::factory()->countyOfficial($county)->create();
        $release = $this->publishedReferenceRelease([$county], [$sector], [], $administrator);
        $this->seed(DswgWorkflowSeeder::class);

        $this->actingAs($administrator)->post(route('dswg.groups.store', $administrator->currentTeam->slug), [
            'code' => 'DSWG-WASH', 'name' => 'Water and Sanitation DSWG', 'mandate' => 'Coordinate county and national water-sector delivery.', 'scope' => 'sector',
            'secretariat_user_id' => $administrator->id, 'meeting_frequency' => 'Quarterly', 'county_ids' => [$county->id], 'sector_ids' => [$sector->id], 'member_ids' => [$member->id],
        ])->assertRedirect();
        $group = DswgWorkingGroup::query()->sole();
        $this->assertTrue(Str::isUuid($group->id));
        $this->assertTrue($group->members()->whereKey($member)->exists());
        $this->assertSame($release->id, $group->reference_data_release_id);
        $creationEvent = AuditEvent::query()->where('subject_id', $group->id)->where('action', 'dswg.group.created')->sole();
        $this->assertSame($release->id, $creationEvent->metadata['reference_data_release_id']);
        $this->assertSame($release->checksum, $creationEvent->metadata['reference_data_release_checksum']);

        $this->actingAs($administrator)->post(route('dswg.meetings.store', $administrator->currentTeam->slug), [
            'dswg_working_group_id' => $group->id, 'reference' => 'DSWG-WASH-2026-Q3', 'title' => 'Quarterly delivery review',
            'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDay()->addHours(2)->toIso8601String(), 'meeting_mode' => 'hybrid',
            'venue' => 'SDD boardroom', 'virtual_link' => 'https://meet.example.org/wash', 'agenda' => 'Review performance, financing bottlenecks, decisions and accountable actions.',
            'quorum_required' => 2, 'invitee_ids' => [$administrator->id, $member->id],
        ])->assertRedirect();
        $meeting = DswgMeeting::query()->sole();
        $this->assertSame('scheduled', $meeting->status);
        $this->assertNotNull($meeting->workflow_instance_id);

        $this->actingAs($administrator)->post(route('dswg.meetings.documents.store', [$administrator->currentTeam->slug, $meeting]), [
            'record_purpose' => 'agenda', 'title' => 'Approved quarterly meeting agenda', 'category' => 'Agenda', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('approved-agenda.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $this->actingAs($member)->patch(route('dswg.meetings.invitation.respond', [$member->currentTeam->slug, $meeting]), ['invitation_status' => 'accepted'])->assertRedirect();
        $this->actingAs($administrator)->patch(route('dswg.meetings.outcomes.record', [$administrator->currentTeam->slug, $meeting]), [
            'minutes' => 'The members confirmed quorum, reviewed the delivery portfolio, adopted the financing decision and assigned accountable follow-up actions.',
            'present_user_ids' => [$administrator->id, $member->id],
        ])->assertRedirect();
        $this->assertSame('minutes_pending', $meeting->refresh()->status);
        $this->assertSame('present', $meeting->invitees()->whereKey($member)->firstOrFail()->pivot->attendance_status);

        $this->actingAs($approver)->patch(route('dswg.meetings.minutes.approve', [$approver->currentTeam->slug, $meeting]), ['approval_comment' => 'Approval must wait for the signed repository record.'])->assertStatus(409);

        $this->actingAs($administrator)->post(route('dswg.meetings.documents.store', [$administrator->currentTeam->slug, $meeting]), [
            'record_purpose' => 'minutes', 'title' => 'Signed quarterly meeting minutes', 'category' => 'Minutes', 'source_type' => 'scanned',
            'document' => UploadedFile::fake()->image('signed-minutes.jpg'),
        ])->assertRedirect();
        $links = DocumentLink::query()->with('document.currentVersion')->orderBy('created_at')->get();
        $this->assertSame(['dswg-agenda-record', 'dswg-minutes-record'], $links->pluck('purpose')->all());
        $this->assertTrue($links->every(fn (DocumentLink $link): bool => $link->subject_id === $meeting->id && $link->document->currentVersion !== null));
        $links->each(fn (DocumentLink $link) => Storage::disk('local')->assertExists($link->document->path));
        $minutesDocument = DocumentLink::query()->with('document')->where('purpose', 'dswg-minutes-record')->sole()->document;
        $this->actingAs($member)->get(route('evidence.preview', [$member->currentTeam->slug, $minutesDocument]))->assertOk();
        $outsideUser = User::factory()->countyAdmin(County::factory()->create())->create();
        $this->actingAs($outsideUser)->get(route('evidence.preview', [$outsideUser->currentTeam->slug, $minutesDocument]))->assertForbidden();
        $this->actingAs($member)->get(route('dswg.index', $member->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('meetings.0.documents', 2));
        $this->actingAs($member)->get(route('evidence.index', $member->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.pagination.total', 2));

        $this->actingAs($administrator)->patch(route('dswg.meetings.minutes.approve', [$administrator->currentTeam->slug, $meeting]), ['approval_comment' => 'Minutes accurately reflect the adopted decisions.'])->assertForbidden();
        $this->actingAs($approver)->patch(route('dswg.meetings.minutes.approve', [$approver->currentTeam->slug, $meeting]), ['approval_comment' => 'Minutes independently reviewed and approved.'])->assertRedirect();
        $this->assertSame('closed', $meeting->refresh()->status);
        $this->assertSame($approver->id, $meeting->minutes_approved_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $meeting->id, 'action' => 'dswg.minutes.approved']);
        $this->actingAs($administrator)->post(route('dswg.meetings.documents.store', [$administrator->currentTeam->slug, $meeting]), [
            'record_purpose' => 'supporting', 'title' => 'Late meeting record', 'category' => 'Supporting', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
    }

    public function test_working_group_creation_fails_closed_and_exposes_reference_lineage_in_ui_and_exports(): void
    {
        $county = County::factory()->create();
        $sector = Sector::factory()->create();
        $leadOrganization = Organization::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $payload = $this->workingGroupPayload($county, $sector, $administrator, $leadOrganization);

        $this->actingAs($administrator)->post(route('dswg.groups.store', $administrator->currentTeam->slug), $payload)
            ->assertStatus(409);
        $this->assertDatabaseCount('dswg_working_groups', 0);

        $this->publishedReferenceRelease([$county], [$sector], [], $administrator);
        $this->actingAs($administrator)->post(route('dswg.groups.store', $administrator->currentTeam->slug), $payload)
            ->assertSessionHasErrors('lead_organization_id');
        $this->assertDatabaseCount('dswg_working_groups', 0);

        $release = $this->publishedReferenceRelease([$county], [$sector], [$leadOrganization], $administrator);
        $this->actingAs($administrator)->post(route('dswg.groups.store', $administrator->currentTeam->slug), $payload)
            ->assertRedirect();
        $group = DswgWorkingGroup::query()->sole();
        $meeting = DswgMeeting::factory()->for($group, 'workingGroup')->create(['organized_by' => $administrator->id]);
        $action = DswgAction::factory()->for($meeting, 'meeting')->create(['county_id' => $county->id, 'accountable_user_id' => $administrator->id]);

        $this->actingAs($administrator)->get(route('dswg.index', $administrator->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.columns.2', 'Group reference release')
                ->where('workspace.columns.4', 'Action reference release')
                ->where('workspace.rows.0.id', $action->id)
                ->where('workspace.rows.0.cells.2', "v{$release->version} · {$release->effective_from?->toDateString()}")
                ->where('workspace.rows.0.cells.3', $release->checksum));

        foreach (['json', 'csv'] as $format) {
            $export = $this->actingAs($administrator)->get(route('workspace.export', [$administrator->currentTeam->slug, 'dswg', $format]))
                ->assertOk()
                ->streamedContent();
            $this->assertStringContainsString('Group reference release', $export);
            $this->assertStringContainsString("v{$release->version}", $export);
            $this->assertStringContainsString($release->checksum, $export);
        }
        $this->actingAs($administrator)->get(route('workspace.export', [$administrator->currentTeam->slug, 'dswg', 'xlsx']))
            ->assertOk()
            ->assertDownload();
        $this->actingAs($administrator)->get(route('workspace.export', [$administrator->currentTeam->slug, 'dswg', 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_county_dswg_manager_cannot_establish_a_group_for_an_outside_county(): void
    {
        $home = County::factory()->create();
        $outside = County::factory()->create();
        $sector = Sector::factory()->create();
        $countyAdministrator = User::factory()->countyAdmin($home)->create();
        $countyAdministrator->givePermissionTo(Permission::findOrCreate(ProgrammePermission::ManageDswg->value, 'web'));
        $this->publishedReferenceRelease([$home, $outside], [$sector], [], $countyAdministrator);

        $this->actingAs($countyAdministrator)->post(
            route('dswg.groups.store', $countyAdministrator->currentTeam->slug),
            $this->workingGroupPayload($outside, $sector, $countyAdministrator),
        )->assertForbidden();

        $this->assertDatabaseCount('dswg_working_groups', 0);
    }

    public function test_accountable_action_uses_published_workflow_and_independent_completion_verification(): void
    {
        Storage::fake('local');
        [$county, $group, $meeting, $administrator] = $this->meetingFixture();
        $accountable = User::factory()->countyOfficial($county)->create();
        $accountableOrganization = Organization::factory()->create(['name' => 'County Planning Directorate']);
        $release = $this->publishedReferenceRelease([$county], [], [$accountableOrganization], $administrator);
        $group->members()->attach($accountable, ['membership_role' => 'member', 'status' => 'active']);

        $this->actingAs($administrator)->post(route('dswg.actions.store', [$administrator->currentTeam->slug, $meeting]), [
            'code' => 'DSWG-ACT-001', 'title' => 'Reconcile county project pipeline', 'description' => 'Reconcile the county pipeline and submit an evidence-backed exception report.',
            'accountable_user_id' => $accountable->id, 'accountable_organization_id' => $accountableOrganization->id, 'county_id' => $county->id, 'due_on' => today()->addWeeks(2)->toDateString(), 'priority' => 'high',
        ])->assertRedirect();
        $action = DswgAction::query()->sole();
        $this->assertTrue(Str::isUuid($action->id));
        $this->assertSame('open', $action->status);
        $this->assertNotNull($action->workflow_instance_id);
        $this->assertSame($release->id, $action->reference_data_release_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $action->id, 'action' => 'dswg.action.created']);
        $creationEvent = AuditEvent::query()->where('subject_id', $action->id)->where('action', 'dswg.action.created')->sole();
        $this->assertSame($release->version, $creationEvent->metadata['reference_data_release_version']);
        $this->assertSame($release->checksum, $creationEvent->metadata['reference_data_release_checksum']);

        $this->actingAs($accountable)->patch(route('dswg.actions.transition', [$accountable->currentTeam->slug, $action]), [
            'transition' => 'start', 'progress_percentage' => 10, 'progress_note' => 'Source records collected.', 'comment' => 'Action implementation has started.',
        ])->assertRedirect();
        $this->actingAs($accountable)->patch(route('dswg.actions.transition', [$accountable->currentTeam->slug, $action]), [
            'transition' => 'submit_completion', 'progress_percentage' => 100, 'completion_evidence' => 'Narrative reference without a repository object.', 'comment' => 'Attempt completion before uploading evidence.',
        ])->assertSessionHasErrors('transition');
        $this->actingAs($accountable)->post(route('dswg.actions.documents.store', [$accountable->currentTeam->slug, $action]), [
            'title' => 'Signed county pipeline reconciliation', 'category' => 'Action evidence', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('reconciliation.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($accountable)->patch(route('dswg.actions.transition', [$accountable->currentTeam->slug, $action]), [
            'transition' => 'submit_completion', 'progress_percentage' => 100, 'completion_evidence' => 'Reconciled project register reference DSWG/EVIDENCE/2026/001 with signed exception schedule.', 'comment' => 'Completed work submitted for independent verification.',
        ])->assertRedirect();
        $this->assertSame('completion_review', $action->refresh()->status);
        $link = DocumentLink::query()->with('document')->where('subject_id', $action->id)->sole();
        $this->assertSame('dswg-action-evidence', $link->purpose);
        Storage::disk('local')->assertExists($link->document->path);
        $this->actingAs($accountable)->get(route('dswg.index', $accountable->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('workspace.rows.0.documents', 1)
            ->where('workspace.rows.0.cells.4', "v{$release->version} · {$release->effective_from?->toDateString()}")
            ->where('workspace.rows.0.cells.5', $release->checksum)
            ->where('workspace.rows.0.cells.8', 'County Planning Directorate'));
        $export = $this->actingAs($administrator)->get(route('workspace.export', [$administrator->currentTeam->slug, 'dswg', 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString($release->checksum, $export);
        $this->assertStringContainsString('County Planning Directorate', $export);

        $verifier = User::factory()->topManagement()->create();
        $verifier->assignedCounties()->attach($county);
        $this->actingAs($verifier)->patch(route('dswg.actions.transition', [$verifier->currentTeam->slug, $action]), [
            'transition' => 'verify', 'comment' => 'Evidence and county exception schedule independently reconciled.',
        ])->assertRedirect();
        $this->assertSame('completed', $action->refresh()->status);
        $this->assertSame($verifier->id, $action->verified_by);
        $this->assertSame(100, $action->progress_percentage);
        $this->actingAs($accountable)->post(route('dswg.actions.documents.store', [$accountable->currentTeam->slug, $action]), [
            'title' => 'Late action evidence', 'category' => 'Action evidence', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('late-action.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
    }

    public function test_county_scope_applies_to_dswg_page_direct_mutations_and_exports(): void
    {
        $home = County::factory()->create(['name' => 'Visible County']);
        $other = County::factory()->create(['name' => 'Hidden County']);
        $countyUser = User::factory()->countyAdmin($home)->create();
        $visibleGroup = DswgWorkingGroup::factory()->create();
        $visibleGroup->counties()->attach($home);
        $visibleMeeting = DswgMeeting::factory()->for($visibleGroup, 'workingGroup')->create();
        $visibleAction = DswgAction::factory()->for($visibleMeeting, 'meeting')->create(['county_id' => $home->id]);
        $hiddenGroup = DswgWorkingGroup::factory()->create();
        $hiddenGroup->counties()->attach($other);
        $hiddenMeeting = DswgMeeting::factory()->for($hiddenGroup, 'workingGroup')->create();
        $hiddenAction = DswgAction::factory()->for($hiddenMeeting, 'meeting')->create(['county_id' => $other->id]);

        $this->actingAs($countyUser)->get(route('dswg.index', $countyUser->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dswg/index')->where('workspace.pagination.total', 1)->where('workspace.rows.0.id', $visibleAction->id));
        $export = $this->actingAs($countyUser)->get(route('workspace.export', [$countyUser->currentTeam->slug, 'dswg', 'json']))->assertOk();
        $content = $export->streamedContent();
        $this->assertStringContainsString($visibleAction->code, $content);
        $this->assertStringNotContainsString($hiddenAction->code, $content);

        $this->actingAs($countyUser)->post(route('dswg.actions.store', [$countyUser->currentTeam->slug, $hiddenMeeting]), [
            'code' => 'FORBIDDEN-ACTION', 'title' => 'Forbidden action', 'description' => 'Must not cross the county boundary.', 'accountable_user_id' => $countyUser->id,
            'county_id' => $other->id, 'due_on' => today()->addWeek()->toDateString(), 'priority' => 'medium',
        ])->assertForbidden();
    }

    public function test_accountable_action_rejects_corrupt_and_incomplete_reference_catalogues(): void
    {
        [$county, $group, $meeting, $administrator] = $this->meetingFixture();
        $accountable = User::factory()->countyOfficial($county)->create();
        $organization = Organization::factory()->create();
        $group->members()->attach($accountable, ['membership_role' => 'member', 'status' => 'active']);
        $snapshot = ['counties' => [['id' => $county->id]], 'organizations' => [['id' => $organization->id]], 'sectors' => [], 'programmes' => [], 'programme_county_coverages' => []];
        ReferenceDataRelease::factory()->create(['version' => 2, 'approved_by' => $administrator->id, 'status' => 'published', 'snapshot' => $snapshot, 'checksum' => str_repeat('a', 64), 'effective_from' => now()->subSeconds(30), 'published_at' => now()]);
        $payload = ['code' => 'DSWG-ACT-CATALOGUE', 'title' => 'Validate action catalogue', 'description' => 'Validate the accountable action against the effective governed catalogue.', 'accountable_user_id' => $accountable->id, 'accountable_organization_id' => $organization->id, 'county_id' => $county->id, 'due_on' => today()->addWeek()->toDateString(), 'priority' => 'high'];

        $this->actingAs($administrator)->post(route('dswg.actions.store', [$administrator->currentTeam->slug, $meeting]), $payload)->assertStatus(409);

        $incompleteSnapshot = ['counties' => [['id' => $county->id]], 'organizations' => [], 'sectors' => [], 'programmes' => [], 'programme_county_coverages' => []];
        ReferenceDataRelease::factory()->create(['version' => 3, 'approved_by' => $administrator->id, 'status' => 'published', 'snapshot' => $incompleteSnapshot, 'checksum' => app(CanonicalJson::class)->checksum($incompleteSnapshot), 'effective_from' => now(), 'published_at' => now()]);
        $this->actingAs($administrator)->post(route('dswg.actions.store', [$administrator->currentTeam->slug, $meeting]), $payload)->assertSessionHasErrors('accountable_organization_id');
        $this->assertDatabaseCount('dswg_actions', 0);
    }

    public function test_reminder_command_notifies_once_for_upcoming_meetings_and_due_actions(): void
    {
        $invitee = User::factory()->countyOfficial(County::factory()->create())->create();
        $meeting = DswgMeeting::factory()->create(['starts_at' => now()->addHours(12), 'ends_at' => now()->addHours(14)]);
        $meeting->invitees()->attach($invitee, ['invitation_status' => 'accepted', 'attendance_status' => 'not_recorded', 'meeting_role' => 'participant', 'invited_at' => now()]);
        $action = DswgAction::factory()->create(['accountable_user_id' => $invitee->id, 'due_on' => today()->addDays(2)]);
        Notification::fake();

        $this->artisan('dswg:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($invitee, ProgrammeAlert::class, 2);
        $this->assertNotNull($meeting->refresh()->reminder_sent_at);
        $this->assertNotNull($action->refresh()->reminder_sent_at);

        $this->artisan('dswg:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($invitee, ProgrammeAlert::class, 2);
    }

    public function test_recurring_series_generates_a_rolling_idempotent_schedule_with_workflows_invitations_and_audit(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $member = User::factory()->countyOfficial($county)->create();
        $this->seed(DswgWorkflowSeeder::class);
        $group = DswgWorkingGroup::factory()->create(['secretariat_user_id' => $administrator->id, 'created_by' => $administrator->id]);
        $group->counties()->attach($county);
        $group->members()->attach([
            $administrator->id => ['membership_role' => 'secretariat', 'status' => 'active'],
            $member->id => ['membership_role' => 'member', 'status' => 'active'],
        ]);
        Notification::fake();

        $this->actingAs($administrator)->post(route('dswg.meeting-series.store', $administrator->currentTeam->slug), [
            'dswg_working_group_id' => $group->id,
            'reference_prefix' => 'DSWG-WASH-REC',
            'title' => 'Weekly county delivery coordination',
            'frequency' => 'weekly',
            'interval' => 1,
            'first_starts_at' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d\TH:i'),
            'ends_on' => today()->addDays(40)->toDateString(),
            'duration_minutes' => 90,
            'timezone' => 'Africa/Nairobi',
            'meeting_mode' => 'hybrid',
            'venue' => 'County coordination room',
            'virtual_link' => 'https://meet.example.org/weekly-delivery',
            'agenda' => 'Review delivery exceptions, decisions and accountable actions.',
            'quorum_required' => 2,
            'generation_horizon_days' => 14,
            'invitee_ids' => [$administrator->id, $member->id],
        ])->assertRedirect();

        $series = DswgMeetingSeries::query()->sole();
        $this->assertTrue(Str::isUuid($series->id));
        $this->assertSame(2, $series->meetings()->count());
        $this->assertSame(10, $series->meetings()->oldest('starts_at')->firstOrFail()->starts_at->setTimezone('Africa/Nairobi')->hour);
        $this->assertSame([1, 2], $series->meetings()->orderBy('occurrence_sequence')->pluck('occurrence_sequence')->all());
        $this->assertSame(2, $series->meetings()->whereNotNull('workflow_instance_id')->count());
        $this->assertTrue($series->meetings->every(fn (DswgMeeting $meeting): bool => $meeting->invitees()->count() === 2));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $series->id, 'action' => 'dswg.meeting_series.created']);
        $this->assertSame(2, AuditEvent::query()->where('action', 'dswg.meeting.generated')->count());
        Notification::assertSentToTimes($member, ProgrammeAlert::class, 2);

        $this->artisan('dswg:generate-recurring-meetings')->assertSuccessful();
        $this->assertSame(2, $series->meetings()->count());

        $this->travel(8)->days();
        $this->artisan('dswg:generate-recurring-meetings')->assertSuccessful();
        $this->assertSame(3, $series->meetings()->count());
        $this->assertSame(4, $series->refresh()->next_sequence);
        $this->actingAs($administrator)->get(route('dswg.index', $administrator->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('series.0.referencePrefix', 'DSWG-WASH-REC')
            ->where('series.0.generatedMeetings', 3));
    }

    public function test_recurring_series_rejects_out_of_scope_groups_nonmembers_and_impossible_quorum(): void
    {
        $home = County::factory()->create();
        $other = County::factory()->create();
        $countyAdministrator = User::factory()->countyAdmin($home)->create();
        $countyAdministrator->givePermissionTo(Permission::findOrCreate(ProgrammePermission::ManageDswg->value, 'web'));
        $outsider = User::factory()->countyOfficial($other)->create();
        $hiddenGroup = DswgWorkingGroup::factory()->create();
        $hiddenGroup->counties()->attach($other);
        $visibleGroup = DswgWorkingGroup::factory()->create();
        $visibleGroup->counties()->attach($home);
        $visibleGroup->members()->attach($countyAdministrator, ['membership_role' => 'secretariat', 'status' => 'active']);
        $payload = [
            'reference_prefix' => 'DSWG-SCOPE-REC', 'title' => 'Scoped recurring review', 'frequency' => 'monthly', 'interval' => 1,
            'first_starts_at' => now()->addWeek()->toIso8601String(), 'ends_on' => today()->addMonths(4)->toDateString(), 'duration_minutes' => 60,
            'timezone' => 'Africa/Nairobi',
            'meeting_mode' => 'virtual', 'virtual_link' => 'https://meet.example.org/scoped', 'agenda' => 'Review scoped delivery.',
            'quorum_required' => 1, 'generation_horizon_days' => 60, 'invitee_ids' => [$countyAdministrator->id],
        ];

        $this->actingAs($countyAdministrator)->post(route('dswg.meeting-series.store', $countyAdministrator->currentTeam->slug), [
            ...$payload, 'dswg_working_group_id' => $hiddenGroup->id,
        ])->assertNotFound();
        $this->actingAs($countyAdministrator)->post(route('dswg.meeting-series.store', $countyAdministrator->currentTeam->slug), [
            ...$payload, 'dswg_working_group_id' => $visibleGroup->id, 'invitee_ids' => [$outsider->id],
        ])->assertStatus(422);
        $this->actingAs($countyAdministrator)->post(route('dswg.meeting-series.store', $countyAdministrator->currentTeam->slug), [
            ...$payload, 'dswg_working_group_id' => $visibleGroup->id, 'quorum_required' => 2,
        ])->assertStatus(422);
        $this->actingAs($countyAdministrator)->post(route('dswg.meeting-series.store', $countyAdministrator->currentTeam->slug), [
            ...$payload, 'dswg_working_group_id' => $visibleGroup->id, 'timezone' => 'Mars/Olympus',
        ])->assertSessionHasErrors('timezone');
        $this->assertSame(0, DswgMeetingSeries::query()->count());
    }

    public function test_recurring_series_baseline_seeder_is_realistic_and_idempotent(): void
    {
        $county = County::factory()->create();
        $secretariat = User::factory()->devolutionAdmin()->create();
        $member = User::factory()->countyOfficial($county)->create();
        $this->seed(DswgWorkflowSeeder::class);
        $group = DswgWorkingGroup::factory()->create([
            'code' => 'DSWG-WASH-01',
            'name' => 'Water, sanitation and climate resilience working group',
            'secretariat_user_id' => $secretariat->id,
            'created_by' => $secretariat->id,
        ]);
        $group->counties()->attach($county);
        $group->members()->attach([
            $secretariat->id => ['membership_role' => 'secretariat', 'status' => 'active'],
            $member->id => ['membership_role' => 'member', 'status' => 'active'],
        ]);
        Notification::fake();

        $this->seed(DswgMeetingSeriesSeeder::class);
        $series = DswgMeetingSeries::query()->sole();
        $meetingCount = $series->meetings()->count();
        $this->assertSame('DSWG-WASH-DELIVERY', $series->reference_prefix);
        $this->assertSame('quarterly', $series->frequency);
        $this->assertGreaterThanOrEqual(1, $meetingCount);
        $this->assertSame(10, $series->meetings()->oldest('starts_at')->firstOrFail()->starts_at->setTimezone('Africa/Nairobi')->hour);

        $this->seed(DswgMeetingSeriesSeeder::class);
        $this->assertSame(1, DswgMeetingSeries::query()->count());
        $this->assertSame($meetingCount, $series->meetings()->count());
    }

    /** @return array<string, mixed> */
    private function workingGroupPayload(County $county, Sector $sector, User $secretariat, ?Organization $leadOrganization = null): array
    {
        return [
            'code' => 'DSWG-REFERENCE-LINEAGE',
            'name' => 'Reference-governed sector working group',
            'mandate' => 'Coordinate accountable county and national delivery against the approved sector mandate.',
            'scope' => 'sector',
            'lead_organization_id' => $leadOrganization?->id,
            'secretariat_user_id' => $secretariat->id,
            'meeting_frequency' => 'Quarterly',
            'county_ids' => [$county->id],
            'sector_ids' => [$sector->id],
            'member_ids' => [$secretariat->id],
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
            'approval_reference' => 'SDD-MDM-DSWG-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }

    /** @return array{County, DswgWorkingGroup, DswgMeeting, User} */
    private function meetingFixture(): array
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $this->publishedReferenceRelease([$county], [], [], $administrator);
        $this->seed(DswgWorkflowSeeder::class);
        $group = DswgWorkingGroup::factory()->create(['secretariat_user_id' => $administrator->id, 'created_by' => $administrator->id]);
        $group->counties()->attach($county);
        $group->members()->attach($administrator, ['membership_role' => 'secretariat', 'status' => 'active']);
        $meeting = DswgMeeting::factory()->for($group, 'workingGroup')->create(['organized_by' => $administrator->id, 'status' => 'minutes_pending']);

        return [$county, $group, $meeting, $administrator];
    }
}
