<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccessibilityContractTest extends TestCase
{
    public function test_public_and_authentication_journeys_have_keyboard_bypass_targets(): void
    {
        $authLayout = $this->source('resources/js/layouts/auth/auth-simple-layout.tsx');
        $this->assertStringContainsString('href="#main-content"', $authLayout);
        $this->assertStringContainsString('{copy.skipToMainContent}', $authLayout);
        $this->assertStringContainsString('id="main-content"', $authLayout);
        $this->assertStringContainsString('tabIndex={-1}', $authLayout);

        foreach (['welcome.tsx', 'help.tsx', 'faqs.tsx'] as $page) {
            $source = $this->source("resources/js/pages/{$page}");
            $this->assertStringContainsString('<main id="main-content" tabIndex={-1}>', $source, "{$page} must expose a focusable skip-link target.");
        }
    }

    public function test_public_header_remains_visible_without_obscuring_the_skip_link(): void
    {
        $publicHeader = $this->source('resources/js/components/public-site-header.tsx');
        $publicLayout = $this->source('resources/js/layouts/public-layout.tsx');

        $this->assertStringContainsString('sticky top-0', $publicHeader);
        $this->assertStringContainsString('isolate z-40', $publicHeader);
        $this->assertStringContainsString('fixed top-3 left-3 z-50', $publicLayout);
    }

    public function test_authenticated_workspaces_have_a_keyboard_bypass_target(): void
    {
        $appShell = $this->source('resources/js/components/app-shell.tsx');
        $this->assertStringContainsString('href="#main-content"', $appShell);
        $this->assertStringContainsString('{localization.copy.skipToMainContent}', $appShell);

        $appContent = $this->source('resources/js/components/app-content.tsx');
        $this->assertSame(2, substr_count($appContent, 'id="main-content"'));
        $this->assertSame(2, substr_count($appContent, 'tabIndex={-1}'));
    }

    public function test_sidebar_toggle_uses_the_idmis_mark_and_a_localized_accessible_name(): void
    {
        $sidebar = $this->source('resources/js/components/ui/sidebar.tsx');
        $this->assertStringContainsString('function IdmisSidebarToggleIcon', $sidebar);
        $this->assertStringNotContainsString('PanelLeftOpenIcon', $sidebar);
        $this->assertStringNotContainsString('PanelLeftCloseIcon', $sidebar);

        $header = $this->source('resources/js/components/app-sidebar-header.tsx');
        $this->assertStringContainsString('aria-label={localization.copy.toggleNavigation}', $header);
        $this->assertStringContainsString('title={localization.copy.toggleNavigation}', $header);

        foreach (['en', 'sw', 'fr'] as $locale) {
            $catalogue = $this->source("lang/{$locale}/idmis.php");
            $this->assertStringContainsString("'toggle_navigation' =>", $catalogue);
        }
    }

    public function test_citizen_journey_has_focusable_bypass_and_announced_async_feedback(): void
    {
        $shell = $this->source('resources/js/components/citizen-engagement-shell.tsx');
        $publicLayout = $this->source('resources/js/layouts/public-layout.tsx');
        $this->assertStringContainsString('href="#main-content"', $publicLayout);
        $this->assertStringContainsString('<main id="main-content" tabIndex={-1}>', $shell);

        $intake = $this->source('resources/js/pages/citizen-engagement/index.tsx');
        $this->assertStringContainsString('aria-invalid={Boolean(errors.consent_given)}', $intake);
        $this->assertStringContainsString("'consent-description consent-error'", $intake);
        $this->assertStringContainsString('id="consent-error"', $intake);

        $receipt = $this->source('resources/js/pages/citizen-engagement/receipt.tsx');
        $this->assertStringContainsString('role="status"', $receipt);
        $this->assertStringContainsString('aria-live="polite"', $receipt);
        $this->assertStringContainsString('copyText.receipt_copy_failed', $receipt);
    }

    public function test_global_theme_respects_reduced_motion_and_destructive_contrast(): void
    {
        $styles = $this->source('resources/css/app.css');
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $styles);
        $this->assertStringContainsString('--destructive-foreground: oklch(1 0 0);', $styles);
        $this->assertStringContainsString('animation-duration: 0.01ms !important;', $styles);
    }

    public function test_shared_form_errors_and_authentication_statuses_are_announced(): void
    {
        $inputError = $this->source('resources/js/components/input-error.tsx');
        $this->assertStringContainsString("role = 'alert'", $inputError);
        $this->assertStringContainsString('role={role}', $inputError);

        foreach (['login.tsx', 'forgot-password.tsx'] as $page) {
            $source = $this->source("resources/js/pages/auth/{$page}");
            $this->assertStringContainsString('role="status"', $source);
            $this->assertStringContainsString('aria-live="polite"', $source);
        }
    }

    public function test_critical_authentication_fields_expose_labels_and_error_relationships(): void
    {
        $login = $this->source('resources/js/pages/auth/login.tsx');
        $this->assertStringContainsString("errors.email ? 'email-error' : undefined", $login);
        $this->assertStringContainsString("? 'password-error'", $login);

        $forgotPassword = $this->source('resources/js/pages/auth/forgot-password.tsx');
        $this->assertStringContainsString('aria-invalid={Boolean(errors.email)}', $forgotPassword);
        $this->assertStringContainsString('id="email-error"', $forgotPassword);

        $twoFactor = $this->source('resources/js/pages/auth/two-factor-challenge.tsx');
        $this->assertStringContainsString('htmlFor="recovery_code"', $twoFactor);
        $this->assertStringContainsString('id="recovery_code"', $twoFactor);
        $this->assertStringContainsString("? 'authentication-code-error'", $twoFactor);

        $setup = $this->source('resources/js/components/two-factor-setup-modal.tsx');
        $this->assertStringContainsString('aria-label={copy.two_factor_qr_code}', $setup);
        $this->assertStringContainsString('aria-label={copy.manual_setup_key}', $setup);
        $this->assertStringContainsString('aria-label={copy.copy_setup_key}', $setup);
        $this->assertStringContainsString('aria-label={copy.authentication_code}', $setup);

        $recovery = $this->source('resources/js/components/two-factor-recovery-codes.tsx');
        $this->assertStringContainsString('aria-expanded={codesAreVisible}', $recovery);
        $this->assertStringContainsString('aria-controls="recovery-codes-section"', $recovery);
        $this->assertStringContainsString('role="status"', $recovery);

        $notifications = $this->source('resources/js/pages/notifications/index.tsx');
        $this->assertStringContainsString('aria-live="polite"', $notifications);
        $this->assertStringContainsString('aria-busy={processing}', $notifications);
        $this->assertStringContainsString('pageHref(', $notifications);

        $activity = $this->source('resources/js/pages/user-activity/index.tsx');
        $this->assertStringContainsString('aria-live="polite"', $activity);
        $this->assertStringContainsString('aria-hidden="true"', $activity);
        $this->assertStringContainsString('localization.current', $activity);

        $classroom = $this->source('resources/js/pages/learning/classrooms/show.tsx');
        $this->assertStringContainsString('aria-label={interpolate(copy.attendance_actions', $classroom);
        $this->assertStringContainsString('aria-invalid={Boolean(errors.notes)}', $classroom);
        $this->assertStringContainsString('role="alert"', $classroom);
        $this->assertStringNotContainsString('DEFAULT_LOCALE', $classroom);

        $communityAnalytics = $this->source('resources/js/pages/knowledge/community-analytics.tsx');
        $this->assertStringContainsString('accessibilityLayer', $communityAnalytics);
        $this->assertStringContainsString('aria-label={interpolate(copy.discussion_actions', $communityAnalytics);
        $this->assertStringContainsString('aria-hidden="true"', $communityAnalytics);

        $certificate = $this->source('resources/js/pages/learning/certificate-verification.tsx');
        $this->assertStringContainsString('aria-live="polite"', $certificate);
        $this->assertStringContainsString('aria-invalid={Boolean(errors.code)}', $certificate);
        $this->assertStringContainsString("'verification-code-error'", $certificate);
        $this->assertStringContainsString('aria-hidden="true"', $certificate);

        $accountDeletion = $this->source('resources/js/components/delete-user.tsx');
        $this->assertStringContainsString('onOpenAutoFocus={(event)', $accountDeletion);
        $this->assertStringContainsString('aria-invalid={Boolean(', $accountDeletion);
        $this->assertStringContainsString("'delete-password-error'", $accountDeletion);
        $this->assertStringContainsString('aria-busy={processing}', $accountDeletion);
        $this->assertStringContainsString('copy.confirm_account_deletion', $accountDeletion);

        foreach (['evidence-upload-form.tsx', 'criterion-evidence-upload-form.tsx'] as $component) {
            $upload = $this->source("resources/js/components/{$component}");
            $this->assertStringContainsString('usePage().props.localization.evidence', $upload);
            $this->assertStringContainsString('aria-describedby={', $upload);
            $this->assertStringContainsString('<InputError', $upload);
            $this->assertStringContainsString('aria-busy={processing}', $upload);
            $this->assertStringContainsString('copy.uploading', $upload);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        $this->assertIsString($source, "Unable to read {$path}.");

        return $source;
    }
}
