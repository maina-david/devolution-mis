<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSupportPagesTest extends TestCase
{
    public function test_faq_page_is_publicly_available(): void
    {
        $this->get(route('faqs'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('faqs'));
    }

    public function test_help_page_is_publicly_available(): void
    {
        $this->get(route('help'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('help'));
    }

    public function test_public_support_content_covers_current_governed_capabilities_in_every_locale(): void
    {
        foreach (['en', 'sw', 'fr'] as $locale) {
            $help = require lang_path("{$locale}/help.php");
            $support = require lang_path("{$locale}/support.php");

            $this->assertCount(4, $help['common_tasks']);
            $this->assertNotEmpty($help['support_request_description']);
            $this->assertGreaterThanOrEqual(16, count($support['questions']));
        }
    }
}
