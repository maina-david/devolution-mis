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
}
