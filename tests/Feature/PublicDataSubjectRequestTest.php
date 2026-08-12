<?php

namespace Tests\Feature;

use App\Models\DataSubjectRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicDataSubjectRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_can_open_localized_data_rights_service_without_authenticated_layout(): void
    {
        foreach (['en', 'sw', 'fr'] as $locale) {
            $this->withSession(['locale' => $locale])
                ->get(route('data-rights.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('data-rights/index')
                    ->where('auth.user', null)
                    ->where('localization.current', $locale)
                    ->where('localization.dataRights.page_title', trans('data-rights.page_title', locale: $locale))
                    ->where('noticeVersion', config('privacy.public_notice.version'))
                    ->where('targetDays', config('privacy.data_subject_request_target_days')));
        }

        $english = require lang_path('en/data-rights.php');
        $swahili = require lang_path('sw/data-rights.php');
        $french = require lang_path('fr/data-rights.php');
        $this->assertSame(array_keys($english), array_keys($swahili));
        $this->assertSame(array_keys($english), array_keys($french));

        $application = file_get_contents(resource_path('js/app.tsx'));
        $this->assertIsString($application);
        $this->assertStringContainsString("case name.startsWith('data-rights/'):", $application);
    }

    public function test_guest_submission_enters_governed_encrypted_workflow_with_notice_lineage_and_receipt(): void
    {
        Carbon::setTestNow('2026-08-12 09:00:00 Africa/Nairobi');
        $payload = $this->validPayload();

        $response = $this->post(route('data-rights.store'), $payload);

        $response->assertRedirect(route('data-rights.receipt'));
        $privacyRequest = DataSubjectRequest::query()->sole();
        $this->assertTrue(Str::isUuid($privacyRequest->id));
        $this->assertStringStartsWith('DSR-2026-', $privacyRequest->reference);
        $this->assertNull($privacyRequest->assigned_to);
        $this->assertSame('received', $privacyRequest->status);
        $this->assertSame('pending', $privacyRequest->identity_status);
        $this->assertSame('public_web', $privacyRequest->metadata['intake_channel']);
        $this->assertSame('en', $privacyRequest->metadata['locale']);
        $this->assertSame(config('privacy.public_notice.version'), $privacyRequest->metadata['privacy_notice_version']);
        $this->assertTrue($privacyRequest->metadata['consent_given']);
        $this->assertSame(
            $privacyRequest->received_at->addDays((int) config('privacy.data_subject_request_target_days'))->toIso8601String(),
            $privacyRequest->due_at->toIso8601String(),
        );

        $raw = DataSubjectRequest::query()->toBase()->sole();
        $this->assertStringNotContainsString($payload['requester_name'], (string) $raw->requester_name);
        $this->assertStringNotContainsString($payload['requester_contact'], (string) $raw->requester_contact);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => null,
            'subject_id' => $privacyRequest->id,
            'action' => 'privacy.data-subject-request.received',
        ]);

        $this->get(route('data-rights.receipt'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('data-rights/receipt')
                ->where('receipt.reference', $privacyRequest->reference)
                ->where('receipt.receivedAt', $privacyRequest->received_at->toIso8601String())
                ->where('receipt.dueAt', $privacyRequest->due_at->toIso8601String())
                ->missing('receipt.requesterName')
                ->missing('receipt.requesterContact')
                ->missing('receipt.scope'));
    }

    public function test_public_intake_rejects_stale_notice_invalid_contact_honeypot_and_missing_consent(): void
    {
        $stale = [...$this->validPayload(), 'privacy_notice_version' => 'superseded'];
        $this->post(route('data-rights.store'), $stale)->assertSessionHasErrors('privacy_notice_version');

        $invalidEmail = [...$this->validPayload(), 'requester_contact' => 'not-an-email'];
        $this->post(route('data-rights.store'), $invalidEmail)->assertSessionHasErrors('requester_contact');

        $honeypot = [...$this->validPayload(), 'website' => 'automated-submission'];
        $this->post(route('data-rights.store'), $honeypot)->assertSessionHasErrors('website');

        $withoutConsent = $this->validPayload();
        unset($withoutConsent['consent_given']);
        $this->post(route('data-rights.store'), $withoutConsent)->assertSessionHasErrors('consent_given');

        $this->assertDatabaseCount('data_subject_requests', 0);
    }

    public function test_receipt_cannot_be_opened_without_the_one_time_session_payload(): void
    {
        $this->get(route('data-rights.receipt'))->assertRedirect(route('data-rights.index'));

        $page = $this->source('resources/js/pages/data-rights/index.tsx');
        $receipt = $this->source('resources/js/pages/data-rights/receipt.tsx');
        $footer = $this->source('resources/js/components/public-site-footer.tsx');
        $notice = $this->source('resources/js/pages/privacy-notice.tsx');
        $this->assertStringContainsString('<PublicLayout>', $page);
        $this->assertStringContainsString('<main id="main-content" tabIndex={-1}>', $page);
        $this->assertStringContainsString('<Sheet>', $page);
        $this->assertStringContainsString('aria-invalid={Boolean(errors.scope)}', $page);
        $this->assertStringContainsString('<PublicLayout>', $receipt);
        $this->assertStringContainsString('href={dataRights()}', $footer);
        $this->assertStringContainsString('href={dataRights()}', $notice);
    }

    public function test_public_intake_is_rate_limited_by_source_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.66']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('data-rights.store'), [
                ...$this->validPayload(),
                'scope' => "Personal information request for controlled rate-limit verification attempt {$attempt}.",
            ])->assertRedirect(route('data-rights.receipt'));
        }

        $this->post(route('data-rights.store'), $this->validPayload())->assertStatus(429);
        $this->assertDatabaseCount('data_subject_requests', 5);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'request_type' => 'access',
            'requester_name' => 'Protected Citizen',
            'requester_contact' => 'citizen@example.test',
            'contact_channel' => 'email',
            'scope' => 'All personal information connected to citizen feedback reference CFM-2026-001.',
            'consent_given' => true,
            'privacy_notice_version' => config('privacy.public_notice.version'),
            'website' => '',
        ];
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        $this->assertIsString($source);

        return $source;
    }
}
