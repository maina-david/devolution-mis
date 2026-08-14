<?php

namespace Tests\Feature;

use App\Actions\CreateServiceDeskPolicy;
use App\Actions\PublishServiceDeskPolicy;
use App\Models\AssessmentDocument;
use App\Models\BusinessCalendar;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\BusinessTimeCalculator;
use App\Services\EffectiveServiceDeskPolicyResolver;
use App\Services\SupportTicketAccess;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SupportDeskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sla_monitor_rejects_invalid_or_unbounded_limits(): void
    {
        app()->setLocale('en');
        $this->assertSame(2, Artisan::call('support-desk:monitor-slas', ['--limit' => 0]));
        $this->assertStringContainsString(__('support-desk.console.invalid_limit'), Artisan::output());
        $this->assertSame(2, Artisan::call('support-desk:monitor-slas', ['--limit' => 5001]));
        $this->assertSame(2, Artisan::call('support-desk:monitor-slas', ['--limit' => 'unbounded']));

        app()->setLocale('fr');
        $this->assertSame(2, Artisan::call('support-desk:monitor-slas', ['--limit' => 0]));
        $this->assertStringContainsString(__('support-desk.console.invalid_limit'), Artisan::output());
    }

    public function test_service_policy_failures_and_catalogues_follow_the_active_locale(): void
    {
        app()->setLocale('sw');

        try {
            app(EffectiveServiceDeskPolicyResolver::class)->resolve(now());
            $this->fail('Ticket intake without an effective governed service policy must fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertSame(__('support-desk.policy.errors.no_effective_policy'), $exception->getMessage());
        }

        $english = require lang_path('en/support-desk.php');
        $kiswahili = require lang_path('sw/support-desk.php');
        $french = require lang_path('fr/support-desk.php');

        $this->assertSame(array_keys(Arr::dot($english)), array_keys(Arr::dot($kiswahili)));
        $this->assertSame(array_keys(Arr::dot($english)), array_keys(Arr::dot($french)));
    }

    public function test_ticket_scope_failures_follow_the_active_locale(): void
    {
        $county = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        app()->setLocale('sw');

        try {
            app(SupportTicketAccess::class)->assertCounty($requester, null);
            $this->fail('A county requester must not be able to create a national support ticket.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(__('support-desk.ticket.errors.national_scope_required'), $exception->getMessage());
        }
    }

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
        $publisher = User::factory()->platformAdmin()->create();
        $this->publishedServicePolicy($manager, $publisher);

        $payload = [
            'county_id' => $county->id,
            'category' => 'data_quality',
            'priority' => 'high',
            'channel' => 'web',
            'subject' => 'County indicator import rejects approved workbook',
            'description' => 'The approved quarterly indicator workbook passes local validation but the governed import reports an unexplained row-level validation failure.',
        ];
        $this->actingAs($requester)
            ->post(route('support-desk.store'), $payload)
            ->assertRedirect();

        $ticket = SupportTicket::query()->sole();
        $this->assertTrue(Str::isUuid($ticket->id));
        $this->assertSame($release->id, $ticket->reference_data_release_id);
        $this->assertSame('open', $ticket->status);
        $this->assertNotNull($ticket->service_desk_policy_id);
        $this->assertSame(
            app(BusinessTimeCalculator::class)->addHours($ticket->serviceDeskPolicy->businessCalendar, $ticket->requested_at, 4)->toIso8601String(),
            $ticket->first_response_due_at->toIso8601String(),
        );
        $this->assertSame(
            app(BusinessTimeCalculator::class)->addHours($ticket->serviceDeskPolicy->businessCalendar, $ticket->requested_at, 16)->toIso8601String(),
            $ticket->resolution_due_at->toIso8601String(),
        );
        $this->assertStringNotContainsString('approved quarterly indicator workbook', (string) SupportTicket::query()->toBase()->where('id', $ticket->id)->value('description'));
        $this->assertDatabaseHas('support_ticket_activities', [
            'support_ticket_id' => $ticket->id,
            'activity_type' => 'created',
        ]);

        $this->actingAs($outsider)
            ->get(route('support-desk.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('workspace.pagination.total', 0));
        $this->actingAs($manager)
            ->patch(route('support-desk.assign', [$ticket]), [
                'assigned_to' => $resolver->id,
                'narrative' => 'Assigned to the county support resolver with the data-quality investigation brief.',
            ])
            ->assertRedirect();
        $this->assertSame('triaged', $ticket->refresh()->status);
        $this->assertSame($resolver->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->first_responded_at);

        $this->actingAs($resolver)
            ->patch(route('support-desk.transition', [$ticket]), $this->transition('start'))
            ->assertRedirect();
        $this->actingAs($resolver)
            ->post(route('support-desk.documents.store', [$ticket]), [
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
            ->get(route('evidence.preview', [$document]))
            ->assertOk();
        $this->actingAs($outsider)
            ->get(route('evidence.preview', [$document]))
            ->assertForbidden();

        $this->actingAs($resolver)
            ->patch(route('support-desk.transition', [$ticket]), [
                ...$this->transition('resolve'),
                'resolution_summary' => 'The workbook schema profile was refreshed against the published indicator catalogue and the affected rows were successfully revalidated.',
            ])
            ->assertRedirect();
        $this->actingAs($resolver)
            ->patch(route('support-desk.transition', [$ticket]), $this->transition('close'))
            ->assertForbidden();
        $this->actingAs($requester)
            ->patch(route('support-desk.transition', [$ticket]), $this->transition('close'))
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
        Notification::assertSentTo(
            $resolver,
            ProgrammeAlert::class,
            fn (ProgrammeAlert $notification): bool => $notification->titleTranslationKey === 'support-desk.ticket.notifications.assigned_title'
                && $notification->messageTranslationKey === 'support-desk.ticket.notifications.reference_subject',
        );

        $this->actingAs($manager)
            ->get(route('support-desk.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('workspace.pagination.total', 1)
                ->where("details.{$ticket->id}.documents.0.id", $document->id)
                ->where("details.{$ticket->id}.status", 'closed'));

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($manager)
                ->get(route('workspace.export', ['support-desk', $format]))
                ->assertDownload();
        }

        $activity = SupportTicketActivity::query()->latest('occurred_at')->firstOrFail();
        $this->expectException(QueryException::class);
        $activity->update(['to_status' => 'tampered']);
    }

    private function publishedServicePolicy(User $author, User $publisher): void
    {
        $calendar = BusinessCalendar::factory()->published()->create([
            'effective_from' => now()->subYear()->toDateString(),
        ]);
        $policy = app(CreateServiceDeskPolicy::class)->handle($author, [
            'code' => 'IDMIS-SUPPORT',
            'name' => 'IDMIS support policy',
            'description' => 'Governed support policy fixture with business-hour targets and an independently published national roster.',
            'business_calendar_id' => $calendar->id,
            'categories' => [['code' => 'data_quality', 'name' => 'Data quality']],
            'channels' => ['web'],
            'priority_targets' => [
                'critical' => ['first_response' => 1, 'resolution' => 4, 'reminder' => 0.5],
                'high' => ['first_response' => 4, 'resolution' => 16, 'reminder' => 2],
                'medium' => ['first_response' => 8, 'resolution' => 40, 'reminder' => 4],
                'low' => ['first_response' => 16, 'resolution' => 80, 'reminder' => 8],
            ],
            'escalation_rules' => [
                ['priority' => 'high', 'stage' => 'first_response', 'tier' => 1],
                ['priority' => 'high', 'stage' => 'resolution', 'tier' => 3],
            ],
            'effective_from' => now()->subMinute(),
            'effective_to' => null,
            'roster' => [
                ['user_id' => $author->id, 'county_id' => null, 'tier' => 1, 'duty_role' => 'responder', 'is_primary' => true, 'starts_at' => now()->subMinute(), 'ends_at' => null],
                ['user_id' => $publisher->id, 'county_id' => null, 'tier' => 3, 'duty_role' => 'manager', 'is_primary' => true, 'starts_at' => now()->subMinute(), 'ends_at' => null],
            ],
        ]);
        app(PublishServiceDeskPolicy::class)->handle($policy, $publisher, ['authority_status' => 'provisional', 'approval_reference' => null]);
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
            ->post(route('support-desk.store'), [
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
            ->patch(route('support-desk.assign', [$ticket]), [
                'assigned_to' => $requester->id,
                'narrative' => 'Attempt to assign the requester as their own resolver.',
            ])
            ->assertStatus(422);
        $this->actingAs($nationalManager)
            ->patch(route('support-desk.assign', [$ticket]), [
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
