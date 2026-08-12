<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPrivacyNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_notice_is_versioned_localized_and_explicitly_pending_approval(): void
    {
        foreach (['en', 'sw', 'fr'] as $locale) {
            $this->withSession(['locale' => $locale])->get(route('privacy-notice.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('privacy-notice')
                ->where('localization.current', $locale)
                ->where('notice.version', config('privacy.public_notice.version'))
                ->where('notice.approvalStatus', 'draft_pending_dpo_legal_approval')
                ->where('notice.copy.page_title', trans('privacy-notice.page_title', locale: $locale))
                ->where('notice.copy.rights_title', trans('privacy-notice.rights_title', locale: $locale)));
        }
    }

    public function test_citizen_intake_exposes_and_accepts_only_the_current_notice_version(): void
    {
        $county = County::factory()->create();
        $this->publishedReferenceRelease([$county]);

        $this->get(route('citizen-engagement.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('privacyNoticeVersion', config('privacy.public_notice.version')));

        $payload = ['case_type' => 'feedback', 'category' => 'complaint', 'channel' => 'web', 'county_id' => $county->id, 'subject' => 'Privacy notice contract', 'description' => 'This submission verifies that consent is bound to the current configured notice.', 'is_anonymous' => true, 'preferred_contact' => 'none', 'consent_given' => true, 'privacy_notice_version' => 'superseded-version'];

        $this->post(route('citizen-engagement.store'), $payload)->assertSessionHasErrors('privacy_notice_version');
        $payload['privacy_notice_version'] = config('privacy.public_notice.version');
        $this->post(route('citizen-engagement.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('citizen_cases', ['privacy_notice_version' => config('privacy.public_notice.version'), 'consent_given' => true]);
    }

    public function test_privacy_catalogues_and_accessible_public_surface_remain_synchronized(): void
    {
        $english = require lang_path('en/privacy-notice.php');
        $swahili = require lang_path('sw/privacy-notice.php');
        $french = require lang_path('fr/privacy-notice.php');
        $this->assertSame(array_keys($english), array_keys($swahili));
        $this->assertSame(array_keys($english), array_keys($french));

        $page = file_get_contents(resource_path('js/pages/privacy-notice.tsx'));
        $this->assertIsString($page);
        $this->assertStringContainsString('<PublicLayout>', $page);
        $this->assertStringContainsString('<main id="main-content" tabIndex={-1}>', $page);
        $this->assertStringContainsString('draft_pending_dpo_legal_approval', $page);
        $this->assertStringContainsString('aria-labelledby={`${key}-heading`}', $page);

        $application = file_get_contents(resource_path('js/app.tsx'));
        $this->assertIsString($application);
        $this->assertStringContainsString("case name === 'privacy-notice':", $application);

        $intake = file_get_contents(resource_path('js/pages/citizen-engagement/index.tsx'));
        $this->assertIsString($intake);
        $this->assertStringContainsString('href={privacyNotice()}', $intake);
        $this->assertStringContainsString('value={privacyNoticeVersion}', $intake);

        $footer = file_get_contents(resource_path('js/components/public-site-footer.tsx'));
        $this->assertIsString($footer);
        $this->assertStringContainsString('href={privacyNotice()}', $footer);
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties): ReferenceDataRelease
    {
        $snapshot = ['counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id, 'code' => $county->code, 'name' => $county->name])->all(), 'organizations' => [], 'sectors' => [], 'programmes' => []];

        return ReferenceDataRelease::factory()->create(['version' => 1, 'status' => 'published', 'effective_from' => now()->subDay(), 'snapshot' => $snapshot, 'checksum' => app(CanonicalJson::class)->checksum($snapshot)]);
    }
}
