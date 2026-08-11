<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\CountyGrant;
use App\Models\ExchequerEvent;
use App\Models\ExchequerRequest;
use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use App\Models\IntegrationSystem;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExchequerTrackingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_linked_request_moves_through_independently_attested_immutable_timeline(): void
    {
        Carbon::setTestNow('2026-08-10 06:00:00');
        $county = County::factory()->create();
        $grant = CountyGrant::factory()->create(['county_id' => $county->id, 'allocated_amount' => 100_000_000, 'disbursed_amount' => 0, 'status' => 'planned']);
        $creator = User::factory()->devolutionAdmin()->create();
        $integrator = User::factory()->platformAdmin()->create();
        $ocobSystem = IntegrationSystem::factory()->create(['code' => 'OCOB-SBX']);
        $ocobContract = IntegrationContract::factory()->create(['integration_system_id' => $ocobSystem->id]);
        $ocobExchange = IntegrationExchange::factory()->create(['integration_contract_id' => $ocobContract->id, 'county_id' => $county->id, 'status' => 'succeeded']);
        $release = $this->publishedReferenceRelease([$county], $creator);
        $this->actingAs($creator)->post(route('exchequer.store', $creator->currentTeam->slug), $this->requestPayload($grant))->assertRedirect();
        $request = ExchequerRequest::query()->sole();
        $this->assertTrue(Str::isUuid($request->id));
        $this->assertSame($release->id, $request->reference_data_release_id);
        $this->actingAs($creator)->post(route('exchequer.events.store', [$creator->currentTeam->slug, $request]), $this->eventPayload('submitted_to_treasury', 'IDMIS', 'IDMIS-SUBMIT-001'))->assertForbidden();

        Carbon::setTestNow(now()->addHours(2));
        $this->actingAs($integrator)->post(route('exchequer.events.store', [$integrator->currentTeam->slug, $request]), $this->eventPayload('ocob_authorized', 'OCOB', 'OCOB-SKIP-001'))->assertSessionHasErrors('event_type');
        $this->actingAs($integrator)->post(route('exchequer.events.store', [$integrator->currentTeam->slug, $request]), $this->eventPayload('submitted_to_treasury', 'CBK', 'CBK-WRONG-001'))->assertSessionHasErrors('source_system');
        $this->record($integrator, $request, 'submitted_to_treasury', 'IDMIS', 'IDMIS-SUBMIT-001');
        $this->assertSame('submitted_to_treasury', $request->refresh()->current_stage);
        Carbon::setTestNow(now()->addHours(8));
        $this->record($integrator, $request, 'treasury_forwarded_ocob', 'TREASURY', 'IFMIS-FWD-001');
        $this->assertSame('forwarded_to_ocob', $request->refresh()->current_stage);
        Carbon::setTestNow(now()->addHours(12));
        $this->actingAs($integrator)->post(route('exchequer.events.store', [$integrator->currentTeam->slug, $request]), [...$this->eventPayload('ocob_authorized', 'OCOB', 'OCOB-AUTH-001'), 'integration_exchange_id' => $ocobExchange->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('authorized_by_ocob', $request->refresh()->current_stage);
        Carbon::setTestNow(now()->addHours(3));
        $this->record($integrator, $request, 'treasury_issued_cbk', 'TREASURY', 'IFMIS-ISSUE-001');
        $this->assertSame('issued_to_cbk', $request->refresh()->current_stage);
        Carbon::setTestNow(now()->addHours(4));
        $this->record($integrator, $request, 'cbk_credited', 'CBK', 'CBK-CREDIT-001');
        $this->assertSame('credited', $request->refresh()->current_stage);

        $this->assertSame('completed', $request->refresh()->status);
        $this->assertSame('credited', $request->current_stage);
        $this->assertSame('25000000.00', $grant->refresh()->disbursed_amount);
        $this->assertSame('processing', $grant->status);
        $this->assertDatabaseCount('exchequer_events', 5);
        $event = ExchequerEvent::query()->where('event_type', 'cbk_credited')->sole();
        $this->assertTrue(Str::isUuid($event->id));
        $this->assertSame(64, mb_strlen($event->evidence_checksum));
        $this->assertSame(1740, $event->elapsed_total_minutes);
        $this->assertDatabaseHas('exchequer_events', ['event_type' => 'ocob_authorized', 'integration_exchange_id' => $ocobExchange->id]);
        $this->expectException(QueryException::class);
        $event->update(['notes' => 'Tampered']);
    }

    public function test_county_scope_filters_analytics_and_four_exports_do_not_leak_other_counties(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $viewer = User::factory()->developmentPartner()->create();
        $viewer->assignedCounties()->attach($county);
        $creator = User::factory()->devolutionAdmin()->create();
        $grant = CountyGrant::factory()->create(['county_id' => $county->id]);
        $otherGrant = CountyGrant::factory()->create(['county_id' => $otherCounty->id]);
        $visible = ExchequerRequest::factory()->create(['county_grant_id' => $grant->id, 'county_id' => $county->id, 'created_by' => $creator->id, 'tranche_reference' => 'VISIBLE']);
        ExchequerRequest::factory()->create(['county_grant_id' => $otherGrant->id, 'county_id' => $otherCounty->id, 'created_by' => $creator->id, 'tranche_reference' => 'HIDDEN']);

        $this->actingAs($viewer)->get(route('exchequer.index', [$viewer->currentTeam->slug, 'search' => 'VISIBLE']))->assertOk()->assertInertia(fn ($page) => $page->where('requests.total', 1)->where('requests.data.0.id', $visible->id)->where('requests.data.0.referenceData', null)->where('summary.total', 1));
        $this->actingAs($viewer)->get(route('exchequer.index', [$viewer->currentTeam->slug, 'county_id' => $otherCounty->id]))->assertForbidden();
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($viewer)->get(route('workspace.export', [$viewer->currentTeam->slug, 'exchequer', $format, 'search' => 'VISIBLE']))->assertOk()->assertDownload();
        }
        $this->actingAs($viewer)->get(route('workspace.export', [$viewer->currentTeam->slug, 'exchequer', 'json', 'county_id' => $otherCounty->id]))->assertForbidden();
    }

    public function test_allocation_and_source_reference_controls_reject_overcommit_and_replay(): void
    {
        $county = County::factory()->create();
        $grant = CountyGrant::factory()->create(['county_id' => $county->id, 'allocated_amount' => 30_000_000]);
        $creator = User::factory()->devolutionAdmin()->create();
        $integrator = User::factory()->platformAdmin()->create();
        $this->publishedReferenceRelease([$county], $creator);
        $this->actingAs($creator)->post(route('exchequer.store', $creator->currentTeam->slug), $this->requestPayload($grant))->assertRedirect();
        $this->actingAs($creator)->post(route('exchequer.store', $creator->currentTeam->slug), [...$this->requestPayload($grant), 'tranche_reference' => 'OVERCOMMIT', 'amount' => 10_000_000])->assertUnprocessable();
        $request = ExchequerRequest::query()->sole();
        $this->record($integrator, $request, 'submitted_to_treasury', 'IDMIS', 'SOURCE-REPLAY-001');
        $secondGrant = CountyGrant::factory()->create(['county_id' => $county->id, 'programme' => 'Other programme']);
        $second = ExchequerRequest::factory()->create(['county_grant_id' => $secondGrant->id, 'county_id' => $county->id, 'created_by' => $creator->id]);
        $this->actingAs($integrator)->post(route('exchequer.events.store', [$integrator->currentTeam->slug, $second]), $this->eventPayload('submitted_to_treasury', 'IDMIS', 'SOURCE-REPLAY-001'))->assertSessionHasErrors('source_event_reference');
    }

    public function test_request_creation_fails_closed_without_a_valid_governed_county_catalogue(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $grant = CountyGrant::factory()->create(['county_id' => $county->id]);
        $creator = User::factory()->devolutionAdmin()->create();

        $this->actingAs($creator)->post(route('exchequer.store', $creator->currentTeam->slug), $this->requestPayload($grant))->assertConflict();
        $this->assertDatabaseCount('exchequer_requests', 0);

        $this->publishedReferenceRelease([$otherCounty], $creator);
        $this->actingAs($creator)->post(route('exchequer.store', $creator->currentTeam->slug), $this->requestPayload($grant))->assertSessionHasErrors('county_id');
        $this->assertDatabaseCount('exchequer_requests', 0);
    }

    private function record(User $actor, ExchequerRequest $request, string $event, string $source, string $reference): void
    {
        $this->actingAs($actor)->post(route('exchequer.events.store', [$actor->currentTeam->slug, $request]), $this->eventPayload($event, $source, $reference))->assertRedirect()->assertSessionHasNoErrors();
    }

    /** @return array<string, mixed> */
    private function requestPayload(CountyGrant $grant): array
    {
        return ['county_grant_id' => $grant->id, 'tranche_reference' => 'KDSP-TRANCHE-001', 'amount' => 25_000_000, 'currency' => 'KES'];
    }

    /** @return array<string, mixed> */
    private function eventPayload(string $event, string $source, string $reference): array
    {
        return ['event_type' => $event, 'source_system' => $source, 'source_event_reference' => $reference, 'occurred_at' => now()->toIso8601String(), 'notes' => 'Source-attributed controlled lifecycle evidence.'];
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
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
            'approval_reference' => 'SDD-MDM-EXCHEQUER-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
