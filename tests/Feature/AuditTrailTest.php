<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\ProgrammePermission;
use App\Models\Assessment;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_mutations_create_attributed_audit_events(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);
        Notification::fake();

        $this->actingAs($admin)->patch(route('assessments.submit', [$assessment]))->assertRedirect();

        $event = AuditEvent::query()->sole();
        $this->assertSame('assessment.submitted', $event->action);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame($county->id, $event->county_id);
        $this->assertSame($assessment->id, $event->subject_id);
        $this->assertNotNull($event->occurred_at);
        $this->assertNull($event->previous_hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $event->event_hash);
    }

    public function test_audit_events_are_hash_chained_and_database_immutable(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $first = Assessment::factory()->create(['county_id' => $county->id, 'cycle' => '2024/25 ACPA', 'status' => AssessmentStatus::EvidenceCollection]);
        $second = Assessment::factory()->create(['county_id' => $county->id, 'cycle' => '2025/26 ACPA', 'status' => AssessmentStatus::EvidenceCollection]);
        Notification::fake();

        $this->actingAs($admin)->patch(route('assessments.submit', [$first]))->assertRedirect();
        $this->actingAs($admin)->patch(route('assessments.submit', [$second]))->assertRedirect();

        [$firstEvent, $secondEvent] = AuditEvent::query()->orderBy('occurred_at')->orderBy('id')->get()->all();
        $this->assertSame($firstEvent->event_hash, $secondEvent->previous_hash);

        $this->expectException(QueryException::class);
        $secondEvent->update(['description' => 'Tampered']);
    }

    public function test_only_devolution_and_platform_administrators_can_view_or_export_the_audit_trail(): void
    {
        $assessor = User::factory()->assessor()->create();
        $topManagement = User::factory()->topManagement()->create();
        $devolutionAdmin = User::factory()->devolutionAdmin()->create();
        $platformAdmin = User::factory()->platformAdmin()->create();
        $assessor->givePermissionTo(ProgrammePermission::ViewAuditTrail->value);

        foreach ([$assessor, $topManagement] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)->get(route('audit.index'))->assertForbidden();
            $this->actingAs($unauthorizedUser)->get(route('workspace.export', ['workspace' => 'audit', 'format' => 'csv']))->assertForbidden();
        }

        foreach ([$devolutionAdmin, $platformAdmin] as $authorizedUser) {
            $this->actingAs($authorizedUser)->get(route('audit.index'))->assertOk();
            $this->actingAs($authorizedUser)->get(route('workspace.export', ['workspace' => 'audit', 'format' => 'csv']))->assertDownload();
        }
    }
}
