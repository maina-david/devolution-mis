<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppearanceDefaultTest extends TestCase
{
    public function test_light_appearance_is_used_when_no_preference_cookie_exists(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertViewHas('appearance', 'light');
    }

    public function test_explicit_dark_appearance_cookie_is_respected(): void
    {
        $response = $this
            ->withUnencryptedCookie('appearance', 'dark')
            ->get('/');

        $response->assertOk()
            ->assertViewHas('appearance', 'dark');
    }
}
