<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_and_reuse_the_session_locale(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from('/settings/profile')->patch(route('locale.update'), [
            'locale' => 'sw',
        ]);

        $response->assertRedirect('/settings/profile')
            ->assertSessionHas('locale', 'sw')
            ->assertSessionHas('status', 'Lugha yako chaguomsingi imesasishwa.');

        $this->assertDatabaseHas('user_locale_preferences', [
            'user_id' => $user->id,
            'locale' => 'sw',
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('lang="sw"', false)
            ->assertInertia(fn ($page) => $page
                ->where('localization.current', 'sw')
                ->where('localization.copy.chooseLanguage', 'Chagua lugha')
                ->has('localization.supported', 3));

        $this->flushSession();
        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn ($page) => $page->where('localization.current', 'sw'));
    }

    public function test_locale_change_requires_authentication_and_a_supported_locale(): void
    {
        $this->patch(route('locale.update'), ['locale' => 'sw'])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->patch(route('locale.update'), ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }

    public function test_header_locale_selector_has_screen_reader_and_wayfinder_contracts(): void
    {
        $source = file_get_contents(resource_path('js/components/app-sidebar-header.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString("import { update as updateLocale } from '@/routes/locale';", $source);
        $this->assertStringContainsString('aria-label={`${localization.copy.chooseLanguage}', $source);
        $this->assertStringContainsString('role="status"', $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringContainsString('lang={locale.code}', $source);
        $this->assertStringContainsString('aria-hidden="true">{locale.flag}', $source);
    }
}
