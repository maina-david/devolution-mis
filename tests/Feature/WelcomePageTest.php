<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_welcome_page_is_available_to_guests(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('auth.user', null));
    }

    public function test_public_shell_and_welcome_page_use_localized_government_service_content(): void
    {
        $this->withSession(['locale' => 'sw'])
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.current', 'sw')
                ->where('localization.welcome.hero_title', 'Mfumo mmoja unaosimamiwa kwa utekelezaji wa ugatuzi.')
                ->where('localization.copy.republic', 'Jamhuri ya Kenya')
                ->where('localization.copy.signIn', 'Ingia'));

        $english = require lang_path('en/welcome.php');
        $swahili = require lang_path('sw/welcome.php');
        $french = require lang_path('fr/welcome.php');

        $this->assertSame(array_keys($english), array_keys($swahili));
        $this->assertSame(array_keys($english), array_keys($french));
    }

    public function test_public_shell_uses_semantic_theme_tokens_and_real_service_routes(): void
    {
        foreach ([
            'resources/js/pages/welcome.tsx',
            'resources/js/pages/help.tsx',
            'resources/js/pages/faqs.tsx',
            'resources/js/pages/learning/certificate-verification.tsx',
            'resources/js/components/public-site-header.tsx',
            'resources/js/components/public-site-footer.tsx',
            'resources/js/layouts/public-layout.tsx',
            'resources/js/layouts/auth/auth-simple-layout.tsx',
        ] as $path) {
            $source = file_get_contents(base_path($path));
            $this->assertIsString($source);
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}/', $source);
        }

        $welcome = file_get_contents(resource_path('js/pages/welcome.tsx'));
        $this->assertIsString($welcome);
        $this->assertStringContainsString('citizenEngagement()', $welcome);
        $this->assertStringContainsString('verifyCertificate()', $welcome);
        $this->assertStringNotContainsString('CoordinationMap', $welcome);
    }
}
