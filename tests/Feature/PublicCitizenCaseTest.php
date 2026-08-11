<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CitizenCase;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCitizenCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_submits_private_trackable_feedback_with_scanned_evidence_and_consent(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $release = $this->publishedReferenceRelease([$county]);
        $response = $this->post(route('citizen-engagement.store'), [
            'case_type' => 'feedback', 'category' => 'complaint', 'channel' => 'web', 'county_id' => $county->id,
            'subject' => 'Delayed water project response', 'description' => 'The published water project contact channel has not responded to repeated service enquiries.',
            'is_anonymous' => false, 'citizen_name' => 'Amina Wanjiku', 'citizen_email' => 'amina@example.test', 'preferred_contact' => 'email',
            'accessibility_needs' => 'Please provide a large-print response.', 'consent_given' => true, 'privacy_notice_version' => '2026-08',
            'attachment' => UploadedFile::fake()->image('scanned-letter.jpg'), 'source_type' => 'scanned',
        ])->assertRedirect(route('citizen-engagement.receipt'));

        $case = CitizenCase::query()->sole();
        $receipt = $response->getSession()->get('case_receipt');
        $this->assertTrue(Str::isUuid($case->id));
        $this->assertSame('received', $case->status);
        $this->assertSame($release->id, $case->intake_reference_data_release_id);
        $this->assertSame('Amina Wanjiku', $case->citizen_name);
        $this->assertNotSame('Amina Wanjiku', $case->getRawOriginal('citizen_name'));
        $this->assertSame(hash('sha256', $receipt['trackingToken']), $case->tracking_token_hash);
        $this->assertDatabaseHas('citizen_case_attachments', ['citizen_case_id' => $case->id, 'source_type' => 'scanned', 'scan_status' => 'clean', 'ocr_status' => 'pending']);
        Storage::disk('local')->assertExists($case->attachments()->sole()->path);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $case->id, 'action' => 'citizen_case.received', 'actor_id' => null]);
        $this->assertSame($release->checksum, AuditEvent::query()->where('subject_id', $case->id)->where('action', 'citizen_case.received')->sole()->metadata['reference_data_checksum']);
    }

    public function test_private_tracking_code_reveals_only_public_case_history(): void
    {
        $token = Str::random(48);
        $county = County::factory()->create(['logo_path' => '/images/counties/mombasa.webp', 'logo_source_authority' => 'The National Treasury – Bajeti Yetu', 'logo_verified_at' => '2026-08-10']);
        $case = CitizenCase::factory()->create(['county_id' => $county->id, 'tracking_token_hash' => hash('sha256', $token), 'status' => 'in_progress']);
        $case->messages()->create(['direction' => 'outbound', 'visibility' => 'public', 'channel' => 'web', 'body' => 'The county team has started reviewing the service records.', 'delivery_status' => 'recorded', 'posted_at' => now()]);
        $case->messages()->create(['direction' => 'internal', 'visibility' => 'internal', 'channel' => 'web', 'body' => 'Protected investigation note that must never be public.', 'delivery_status' => 'recorded', 'posted_at' => now()]);

        $this->post(route('citizen-engagement.track'), ['reference' => $case->reference, 'tracking_token' => Str::random(48)])->assertSessionHasErrors('reference');
        $this->post(route('citizen-engagement.track'), ['reference' => $case->reference, 'tracking_token' => $token])->assertRedirect(route('citizen-engagement.tracking'));
        $this->get(route('citizen-engagement.tracking'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('citizen-engagement/tracking')->where('case.reference', $case->reference)->where('case.county.kind', 'county')->where('case.county.logoUrl', '/images/counties/mombasa.webp')->where('case.county.logoSourceAuthority', 'The National Treasury – Bajeti Yetu')->has('case.messages', 1)->where('case.messages.0.body', 'The county team has started reviewing the service records.'));
    }

    public function test_public_dashboard_contains_aggregates_without_personal_case_data(): void
    {
        $county = County::factory()->create(['code' => 1, 'logo_path' => '/images/counties/mombasa.webp']);
        $release = $this->publishedReferenceRelease([$county]);
        CitizenCase::factory()->create(['county_id' => $county->id, 'citizen_name' => 'Protected Citizen', 'status' => 'resolved', 'satisfaction_rating' => 4]);
        CitizenCase::factory()->create(['county_id' => $county->id, 'status' => 'received']);
        $this->get(route('citizen-engagement.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('citizen-engagement/index')->where('catalogue.available', true)->where('catalogue.version', $release->version)->where('counties.0.kind', 'county')->where('counties.0.logoUrl', '/images/counties/mombasa.webp')->where('dashboard.total', 2)->where('dashboard.resolved', 1)->where('dashboard.pending', 1)->missing('cases'));
    }

    public function test_public_dashboard_and_tracking_remain_available_when_intake_catalogue_is_unavailable(): void
    {
        $county = County::factory()->create();
        CitizenCase::factory()->create(['county_id' => $county->id]);

        $this->get(route('citizen-engagement.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('citizen-engagement/index')
            ->where('catalogue.available', false)
            ->has('counties', 0)
            ->has('sectors', 0)
            ->where('dashboard.total', 1));

        $this->post(route('citizen-engagement.store'), [
            'case_type' => 'feedback', 'category' => 'complaint', 'channel' => 'web', 'county_id' => $county->id,
            'subject' => 'Unavailable governed intake', 'description' => 'This submission must fail closed without an effective governed catalogue.',
            'is_anonymous' => true, 'preferred_contact' => 'none', 'consent_given' => true, 'privacy_notice_version' => '2026-08',
        ])->assertConflict();
        $this->assertSame(1, CitizenCase::query()->count());
    }

    public function test_public_intake_rejects_records_outside_the_effective_snapshot_and_a_tampered_release(): void
    {
        $governedCounty = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $release = $this->publishedReferenceRelease([$governedCounty]);
        $payload = [
            'case_type' => 'feedback', 'category' => 'complaint', 'channel' => 'web',
            'subject' => 'Governed catalogue validation', 'description' => 'The selected county must exist in the checksum-verified effective catalogue snapshot.',
            'is_anonymous' => true, 'preferred_contact' => 'none', 'consent_given' => true, 'privacy_notice_version' => '2026-08',
        ];

        $this->from(route('citizen-engagement.index'))->post(route('citizen-engagement.store'), [
            ...$payload,
            'county_id' => $outsideCounty->id,
        ])->assertSessionHasErrors('county_id');
        $this->assertSame(0, CitizenCase::query()->count());

        ReferenceDataRelease::factory()->create([
            'status' => 'published',
            'snapshot' => $release->snapshot,
            'checksum' => str_repeat('0', 64),
            'effective_from' => now(),
            'published_at' => now(),
        ]);
        $this->get(route('citizen-engagement.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('catalogue.available', false)->has('counties', 0));
        $this->post(route('citizen-engagement.store'), [
            ...$payload,
            'county_id' => $governedCounty->id,
        ])->assertConflict();
        $this->assertSame(0, CitizenCase::query()->count());
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
            'sectors' => [],
            'programmes' => [],
            'programme_county_coverages' => [],
        ];

        return ReferenceDataRelease::factory()->create([
            'submitted_by' => User::factory()->devolutionAdmin(),
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
