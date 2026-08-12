<?php

namespace Tests\Feature;

use App\Models\BusinessCalendar;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\ServiceDeskPolicy;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\CanonicalJson;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceDeskPolicyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_independently_published_policy_pins_business_hour_targets_roster_and_lineage_to_new_tickets(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-14 16:00:00', 'Africa/Nairobi'));
        $county = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $author = User::factory()->devolutionAdmin()->create();
        $publisher = User::factory()->platformAdmin()->create();
        $calendar = BusinessCalendar::factory()->published()->create([
            'code' => 'KENYA-SUPPORT-2026',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);
        $this->publishedReferenceRelease($county, $publisher);

        $this->actingAs($author)
            ->post(route('support-desk.policies.store'), $this->policyPayload($calendar, $author, $publisher))
            ->assertRedirect();

        $policy = ServiceDeskPolicy::query()->sole();
        $this->assertTrue(Str::isUuid($policy->id));
        $this->assertSame('draft', $policy->status);
        $this->assertSame(2, $policy->rosterMembers()->count());
        $this->actingAs($author)
            ->patch(route('support-desk.policies.publish', [$policy]), [
                'authority_status' => 'approved',
                'approval_reference' => 'SDD-ICT-SERVICE-COMMITTEE-2026-08',
            ])
            ->assertForbidden();
        $this->actingAs($publisher)
            ->patch(route('support-desk.policies.publish', [$policy]), [
                'authority_status' => 'approved',
                'approval_reference' => 'SDD-ICT-SERVICE-COMMITTEE-2026-08',
            ])
            ->assertRedirect();

        $policy->refresh();
        $this->assertSame('published', $policy->status);
        $this->assertSame('approved', $policy->authority_status);
        $this->assertSame(64, mb_strlen((string) $policy->checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $policy->id, 'action' => 'support.policy.published']);

        $this->actingAs($requester)
            ->post(route('support-desk.store'), [
                'county_id' => $county->id,
                'category' => 'incident',
                'priority' => 'high',
                'channel' => 'web',
                'subject' => 'Assessment evidence workspace is unavailable',
                'description' => 'The authorized county assessment evidence workspace is returning an unavailable response and requires governed operational investigation.',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->sole();
        $this->assertSame($policy->id, $ticket->service_desk_policy_id);
        $this->assertSame($policy->checksum, $ticket->service_desk_policy_checksum);
        $this->assertSame('2026-08-17T11:00:00+03:00', $ticket->first_response_due_at->setTimezone('Africa/Nairobi')->toIso8601String());
        $this->assertSame('2026-08-18T14:00:00+03:00', $ticket->resolution_due_at->setTimezone('Africa/Nairobi')->toIso8601String());
        $this->actingAs($author)
            ->get(route('support-desk.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('effectiveServicePolicy.id', $policy->id)
                ->where("details.{$ticket->id}.servicePolicy.code", 'IDMIS-SUPPORT')
                ->where("details.{$ticket->id}.servicePolicy.calendar.code", 'KENYA-SUPPORT-2026')
                ->where('servicePolicies.0.checksum', $policy->checksum));

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($author)
                ->get(route('workspace.export', ['service-desk-policies', $format]))
                ->assertDownload();
        }
        $this->actingAs($author)
            ->getJson(route('search.global', ['q' => 'IDMIS-SUPPORT']))
            ->assertOk()
            ->assertJsonFragment(['category' => 'Service policies', 'id' => $policy->id]);

        $this->expectException(QueryException::class);
        ServiceDeskPolicy::query()->whereKey($policy)->update(['name' => 'Tampered policy']);
    }

    public function test_configuration_scope_and_fail_closed_policy_controls_are_enforced(): void
    {
        $county = County::factory()->create();
        $requester = User::factory()->countyOfficial($county)->create();
        $author = User::factory()->devolutionAdmin()->create();
        $publisher = User::factory()->platformAdmin()->create();
        $calendar = BusinessCalendar::factory()->published()->create();
        $this->publishedReferenceRelease($county, $publisher);

        $this->actingAs($requester)
            ->post(route('support-desk.policies.store'), $this->policyPayload($calendar, $author, $publisher))
            ->assertForbidden();
        $this->actingAs($requester)
            ->post(route('support-desk.store'), [
                'county_id' => $county->id,
                'category' => 'incident',
                'priority' => 'critical',
                'channel' => 'web',
                'subject' => 'Fail closed without effective policy',
                'description' => 'Ticket creation must fail closed when no effective checksum-bound service policy and responder roster are available.',
            ])
            ->assertStatus(503);
        $this->assertSame(0, SupportTicket::query()->count());

        $invalidPayload = $this->policyPayload($calendar, $author, $publisher);
        $invalidPayload['roster'][1]['tier'] = 2;
        $this->actingAs($author)
            ->post(route('support-desk.policies.store'), $invalidPayload)
            ->assertRedirect();
        $policy = ServiceDeskPolicy::query()->sole();
        $this->actingAs($publisher)
            ->patch(route('support-desk.policies.publish', [$policy]), [
                'authority_status' => 'provisional',
                'approval_reference' => null,
            ])
            ->assertStatus(422);
        $this->assertSame('draft', $policy->refresh()->status);
    }

    /** @return array<string, mixed> */
    private function policyPayload(BusinessCalendar $calendar, User $tierOne, User $tierThree): array
    {
        return [
            'code' => 'IDMIS-SUPPORT',
            'name' => 'IDMIS operational support policy',
            'description' => 'Governed support policy covering the service catalogue, business-hour targets, escalation matrix and accountable duty roster.',
            'business_calendar_id' => $calendar->id,
            'categories' => [['code' => 'incident', 'name' => 'Service incident']],
            'channels' => ['web'],
            'priority_targets' => [
                'critical' => ['first_response' => 1, 'resolution' => 4, 'reminder' => 0.5],
                'high' => ['first_response' => 4, 'resolution' => 16, 'reminder' => 2],
                'medium' => ['first_response' => 8, 'resolution' => 40, 'reminder' => 4],
                'low' => ['first_response' => 16, 'resolution' => 80, 'reminder' => 8],
            ],
            'escalation_rules' => [
                ['priority' => 'critical', 'stage' => 'first_response', 'tier' => 3],
                ['priority' => 'critical', 'stage' => 'resolution', 'tier' => 3],
                ['priority' => 'high', 'stage' => 'first_response', 'tier' => 2],
                ['priority' => 'high', 'stage' => 'resolution', 'tier' => 3],
            ],
            'effective_from' => now()->subMinute()->toIso8601String(),
            'effective_to' => null,
            'roster' => [
                ['user_id' => $tierOne->id, 'county_id' => null, 'tier' => 1, 'duty_role' => 'responder', 'is_primary' => true, 'starts_at' => now()->subMinute()->toIso8601String(), 'ends_at' => null],
                ['user_id' => $tierThree->id, 'county_id' => null, 'tier' => 3, 'duty_role' => 'manager', 'is_primary' => true, 'starts_at' => now()->subMinute()->toIso8601String(), 'ends_at' => null],
            ],
        ];
    }

    private function publishedReferenceRelease(County $county, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => [['id' => $county->id, 'code' => $county->code, 'name' => $county->name]],
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
            'effective_from' => now()->subHour(),
            'published_at' => now()->subHour(),
        ]);
    }
}
