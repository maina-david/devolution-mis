<?php

namespace Tests\Feature;

use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportDeskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_request_follows_governed_assignment_resolution_and_acceptance_workflow(): void
    {
        Notification::fake();
        Storage::fake(config('filesystems.default'));
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $resolver = User::factory()->countyAdmin($county)->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $outsider = User::factory()->countyOfficial($otherCounty)->create();
        $release = $this->publishedReferenceRelease([$county, $otherCounty], $manager);

        $payload = [
            'county_id' => $county->id,
            'category' => 'data_quality',
            'priority' => 'high',
            'channel' => 'web',
            'subject' => 'County indicator import rejects approved workbook',
            'description' => 'The approved quarterly indicator workbook passes local validation but the governed import reports an unexplained row-level validation failure.',
        ];
        $this->actingAs($requester)
            ->post(route('support-desk.store', $requester->currentTeam->slug), $payload)
            ->assertRedirect();

        $ticket = SupportTicket::query()->sole();
        $this->assertTrue(Str::isUuid($ticket->id));
        $this->assertSame($release->id, $ticket->reference_data_release_id);
        $this->assertSame('open', $ticket->status);
        $this->assertSame(4, (int) $ticket->requested_at->diffInHours($ticket->first_response_due_at));
        $this->assertSame(16, (int) $ticket->requested_at->diffInHours($ticket->resolution_due_at));
        $this->assertStringNotContainsString('approved quarterly indicator workbook', (string) SupportTicket::query()->toBase()->where('id', $ticket->id)->value('description'));
        $this->assertDatabaseHas('support_ticket_activities', [
            'support_ticket_id' => $ticket->id,
            'activity_type' => 'created',
        ]);

        $this->actingAs($outsider)
            ->get(route('support-desk.index', $outsider->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('workspace.pagination.total', 0));
        $this->actingAs($manager)
            ->patch(route('support-desk.assign', [$manager->currentTeam->slug, $ticket]), [
                'assigned_to' => $resolver->id,
                'narrative' => 'Assigned to the county support resolver with the data-quality investigation brief.',
            ])
            ->assertRedirect();
        $this->assertSame('triaged', $ticket->refresh()->status);
        $this->assertSame($resolver->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->first_responded_at);

        $this->actingAs($resolver)
            ->patch(route('support-desk.transition', [$resolver->currentTeam->slug, $ticket]), $this->transition('start'))
            ->assertRedirect();
        $this->actingAs($resolver)
            ->post(route('support-desk.documents.store', [$resolver->currentTeam->slug, $ticket]), [
                'record_purpose' => 'investigation',
                'title' => 'Indicator import validation trace',
                'category' => 'Service desk investigation',
                'source_type' => 'scanned',
                'document' => UploadedFile::fake()->create('validation-trace.pdf', 48, 'application/pdf'),
            ])
            ->assertRedirect();
        $document = AssessmentDocument::query()->sole();
        $this->assertSame('clean', $document->scan_status);
        $this->assertDatabaseHas('support_ticket_activities', [
            'support_ticket_id' => $ticket->id,
            'activity_type' => 'document_uploaded',
        ]);
        $this->actingAs($requester)
            ->get(route('evidence.preview', [$requester->currentTeam->slug, $document]))
            ->assertOk();
        $this->actingAs($outsider)
            ->get(route('evidence.preview', [$outsider->currentTeam->slug, $document]))
            ->assertForbidden();

        $this->actingAs($resolver)
            ->patch(route('support-desk.transition', [$resolver->currentTeam->slug, $ticket]), [
                ...$this->transition('resolve'),
                'resolution_summary' => 'The workbook schema profile was refreshed against the published indicator catalogue and the affected rows were successfully revalidated.',
            ])
            ->assertRedirect();
        $this->actingAs($resolver)
            ->patch(route('support-desk.transition', [$resolver->currentTeam->slug, $ticket]), $this->transition('close'))
            ->assertForbidden();
        $this->actingAs($requester)
            ->patch(route('support-desk.transition', [$requester->currentTeam->slug, $ticket]), $this->transition('close'))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertSame($resolver->id, $ticket->resolved_by);
        $this->assertSame($requester->id, $ticket->closed_by);
        $this->assertSame(6, $ticket->activities()->count());
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $ticket->id,
            'action' => 'support.ticket.close',
        ]);
        Notification::assertSentTo($resolver, ProgrammeAlert::class);

        $this->actingAs($manager)
            ->get(route('support-desk.index', $manager->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('workspace.pagination.total', 1)
                ->where("details.{$ticket->id}.documents.0.id", $document->id)
                ->where("details.{$ticket->id}.status", 'closed'));

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($manager)
                ->get(route('workspace.export', [$manager->currentTeam->slug, 'support-desk', $format]))
                ->assertDownload();
        }

        $activity = SupportTicketActivity::query()->latest('occurred_at')->firstOrFail();
        $this->expectException(QueryException::class);
        $activity->update(['to_status' => 'tampered']);
    }

    public function test_county_scope_and_separation_of_duties_are_enforced(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $samePersonManager = User::factory()->countyAdmin($county)->create();
        $outsideResolver = User::factory()->countyAdmin($otherCounty)->create();
        $nationalManager = User::factory()->devolutionAdmin()->create();
        $this->publishedReferenceRelease([$county, $otherCounty], $nationalManager);

        $this->actingAs($requester)
            ->post(route('support-desk.store', $requester->currentTeam->slug), [
                'county_id' => $otherCounty->id,
                'category' => 'access',
                'priority' => 'medium',
                'channel' => 'web',
                'subject' => 'Unauthorized county support scope check',
                'description' => 'This request intentionally targets a county outside the requester scope and must be rejected before persistence.',
            ])
            ->assertForbidden();
        $this->assertSame(0, SupportTicket::query()->count());

        $ticket = SupportTicket::factory()->create([
            'reference_data_release_id' => ReferenceDataRelease::query()->sole()->id,
            'requester_id' => $requester->id,
            'county_id' => $county->id,
        ]);
        $this->actingAs($samePersonManager)
            ->patch(route('support-desk.assign', [$samePersonManager->currentTeam->slug, $ticket]), [
                'assigned_to' => $requester->id,
                'narrative' => 'Attempt to assign the requester as their own resolver.',
            ])
            ->assertStatus(422);
        $this->actingAs($nationalManager)
            ->patch(route('support-desk.assign', [$nationalManager->currentTeam->slug, $ticket]), [
                'assigned_to' => $outsideResolver->id,
                'narrative' => 'Attempt to assign a resolver outside the governed county scope.',
            ])
            ->assertForbidden();
    }

    public function test_sla_monitor_is_idempotent_and_records_system_escalation(): void
    {
        Notification::fake();
        $county = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $resolver = User::factory()->countyAdmin($county)->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $release = $this->publishedReferenceRelease([$county], $manager);
        $ticket = SupportTicket::factory()->assigned($resolver)->create([
            'reference_data_release_id' => $release->id,
            'requester_id' => $requester->id,
            'county_id' => $county->id,
            'status' => 'in_progress',
            'requested_at' => now()->subDays(2),
            'first_response_due_at' => now()->subDay(),
            'first_responded_at' => now()->subDay(),
            'resolution_due_at' => now()->subHour(),
        ]);

        $this->assertSame(0, Artisan::call('support-desk:monitor-slas'));
        $this->assertNotNull($ticket->refresh()->escalated_at);
        $this->assertDatabaseHas('support_ticket_activities', [
            'support_ticket_id' => $ticket->id,
            'activity_type' => 'sla_escalated',
            'actor_name' => 'system:support-sla-monitor',
        ]);
        Notification::assertSentTo($resolver, ProgrammeAlert::class);
        Notification::assertSentTo($requester, ProgrammeAlert::class);
        Notification::assertSentTo($manager, ProgrammeAlert::class);

        $this->assertSame(0, Artisan::call('support-desk:monitor-slas'));
        $this->assertSame(1, $ticket->activities()->where('activity_type', 'sla_escalated')->count());
        Notification::assertSentToTimes($resolver, ProgrammeAlert::class, 1);
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => [
                'id' => $county->id,
                'code' => $county->code,
                'name' => $county->name,
            ])->values()->all(),
            'organizations' => [],
            'sectors' => [],
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

    /** @return array<string, string> */
    private function transition(string $transition): array
    {
        return [
            'transition' => $transition,
            'narrative' => ucfirst(str_replace('_', ' ', $transition)).' was completed with accountable ownership and retained support evidence.',
        ];
    }
}
