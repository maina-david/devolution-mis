<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandedErrorAndMailTemplateTest extends TestCase
{
    public function test_not_found_responses_use_the_branded_localized_inertia_error_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 404)
                ->where('title', 'Page not found')
                ->where('goBackLabel', 'Go back')
            );

        $this->assertSame('Ukurasa haujapatikana', trans('idmis.errors.404.title', locale: 'sw'));
        $this->assertSame('Page introuvable', trans('idmis.errors.404.title', locale: 'fr'));
    }

    public function test_markdown_mail_shell_uses_official_devolution_and_kenyan_identity(): void
    {
        $message = (new MailMessage)
            ->subject('IDMIS account security')
            ->line('Use the secure link below to continue.')
            ->action('Continue securely', 'https://idmis.test/secure');

        $html = $message->render()->toHtml();

        $this->assertStringContainsString('/images/branding/devolution-emblem.png', $html);
        $this->assertStringContainsString('/images/branding/kenya-flag.svg', $html);
        $this->assertStringContainsString('State Department for Devolution', $html);
        $this->assertStringContainsString('Official system communication', $html);
        $this->assertStringNotContainsString('Laravel Logo', $html);
    }

    public function test_server_rendered_error_fallback_uses_the_same_official_identity(): void
    {
        $html = view('errors.503')->render();

        $this->assertStringContainsString('devolution-emblem.png', $html);
        $this->assertStringContainsString('kenya-flag.svg', $html);
        $this->assertStringContainsString('Service temporarily unavailable', $html);
        $this->assertStringContainsString('State Department for Devolution', $html);
        $this->assertStringNotContainsString('gray-400', $html);
    }

    public function test_password_reset_notifications_inherit_the_branded_mail_shell(): void
    {
        $mail = (new ResetPassword('secure-token'))->toMail(new User(['email' => 'recipient@idmis.test']));
        $html = $mail->render()->toHtml();

        $this->assertStringContainsString('IDMIS', $html);
        $this->assertStringContainsString('devolution-emblem.png', $html);
        $this->assertStringContainsString('kenya-flag.svg', $html);
    }
}
