<?php

namespace Tests\Feature;

use App\Models\LearningCertificate;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearningCertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_verify_an_exact_issued_certificate_code(): void
    {
        $course = LearningCourse::factory()->create([
            'code' => 'IDMIS-GOV-101',
            'title' => 'Foundations of Devolution Governance',
        ]);
        $learner = User::factory()->create(['name' => 'Amina Wanjiku']);
        $enrollment = LearningEnrollment::factory()->create([
            'learning_course_id' => $course->id,
            'user_id' => $learner->id,
            'status' => 'completed',
        ]);
        $certificate = LearningCertificate::factory()->create([
            'learning_enrollment_id' => $enrollment->id,
            'verification_code' => 'A1B2C3D4E5F6G7H8J9K0M1N2',
            'certificate_number' => 'IDMIS-LRN-2026-000001',
            'final_score' => 88.5,
            'issued_at' => '2026-08-01 09:00:00+03',
            'expires_at' => null,
        ]);

        $this->get(route('learning.certificates.verify', ['code' => $certificate->verification_code]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('learning/certificate-verification')
                ->where('searched', true)
                ->where('query', $certificate->verification_code)
                ->where('certificate.number', 'IDMIS-LRN-2026-000001')
                ->where('certificate.learner', 'Amina Wanjiku')
                ->where('certificate.courseCode', 'IDMIS-GOV-101')
                ->where('certificate.courseTitle', 'Foundations of Devolution Governance')
                ->where('certificate.finalScore', '88.50')
                ->where('certificate.status', 'valid')
                ->where('certificate.checksum', $certificate->content_checksum)
            );
    }

    public function test_verification_fails_closed_and_reports_expiry_without_authentication(): void
    {
        $expired = LearningCertificate::factory()->create([
            'verification_code' => 'Z9Y8X7W6V5U4T3S2R1Q0P9N8',
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('learning.certificates.verify'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('searched', false)
                ->where('certificate', null)
            );

        $this->get(route('learning.certificates.verify', ['code' => '111111111111111111111111']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('searched', true)
                ->where('certificate', null)
            );

        $this->get(route('learning.certificates.verify', ['code' => $expired->verification_code]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('certificate.status', 'expired')
                ->where('certificate.expiresAt', $expired->expires_at->toDateString())
            );

        $this->get(route('learning.certificates.verify', ['code' => 'short']))
            ->assertSessionHasErrors('code');
    }

    public function test_public_certificate_page_is_not_wrapped_in_authenticated_realtime_layout(): void
    {
        $application = file_get_contents(resource_path('js/app.tsx'));
        $realtimeSync = file_get_contents(resource_path('js/components/notification-realtime-sync.tsx'));

        $this->assertIsString($application);
        $this->assertIsString($realtimeSync);
        $this->assertStringContainsString("case name === 'learning/certificate-verification':", $application);
        $this->assertStringContainsString('const userId = page.props.auth.user?.id;', $realtimeSync);
        $this->assertStringContainsString('<AuthenticatedNotificationRealtimeSync userId={userId} />', $realtimeSync);
        $this->assertStringContainsString('`App.Models.User.${userId}`', $realtimeSync);
    }

    public function test_public_certificate_verification_uses_the_active_locale(): void
    {
        $this->withSession(['locale' => 'fr'])
            ->get(route('learning.certificates.verify'))
            ->assertOk()
            ->assertSee('lang="fr"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.learning.verify_certificate_heading', 'Vérifier un certificat de formation')
                ->where('localization.learning.certificate_not_verified', 'Certificat non vérifié'));
    }
}
