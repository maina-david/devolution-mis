<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
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

        $this->actingAs($admin)->patch(route('assessments.submit', [$admin->currentTeam->slug, $assessment]))->assertRedirect();

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

        $this->actingAs($admin)->patch(route('assessments.submit', [$admin->currentTeam->slug, $first]))->assertRedirect();
        $this->actingAs($admin)->patch(route('assessments.submit', [$admin->currentTeam->slug, $second]))->assertRedirect();

        [$firstEvent, $secondEvent] = AuditEvent::query()->orderBy('occurred_at')->orderBy('id')->get()->all();
        $this->assertSame($firstEvent->event_hash, $secondEvent->previous_hash);

        $this->expectException(QueryException::class);
        $secondEvent->update(['description' => 'Tampered']);
    }

    public function test_audit_view_is_filtered_to_assigned_counties(): void
    {
        $assigned = County::factory()->create();
        $hidden = County::factory()->create();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($assigned);
        AuditEvent::factory()->create(['county_id' => $assigned->id, 'action' => 'visible.event']);
        AuditEvent::factory()->create(['county_id' => $hidden->id, 'action' => 'hidden.event']);

        $this->actingAs($assessor)->get(route('audit.index', $assessor->currentTeam->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('workspace.rows', 1)
            ->where('workspace.rows.0.cells.0', 'visible.event')
        );
    }
}
