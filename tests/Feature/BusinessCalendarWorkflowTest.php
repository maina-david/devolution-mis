<?php

namespace Tests\Feature;

use App\Actions\StartWorkflow;
use App\Actions\TransitionWorkflow;
use App\Enums\ProgrammePermission;
use App\Models\Assessment;
use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Services\WorkflowSlaMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessCalendarWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_independently_published_calendar_skips_gazetted_holiday_and_weekend_for_workflow_slas(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-12-24 16:00:00', 'Africa/Nairobi'));
        $author = User::factory()->devolutionAdmin()->create();
        $publisher = User::factory()->platformAdmin()->create();
        $operator = User::factory()->countyAdmin()->create();

        $this->actingAs($author)->post(route('business-calendars.store'), $this->calendarPayload())->assertRedirect();
        $calendar = BusinessCalendar::query()->sole();
        $this->assertTrue(Str::isUuid($calendar->id));
        $this->actingAs($author)->post(route('business-calendars.holidays.store', [$calendar]), ['holiday_date' => '2026-12-25', 'name' => 'Christmas Day', 'category' => 'public_holiday', 'source_reference' => 'Public Holidays Act and Kenya Gazette'])->assertRedirect();
        $this->actingAs($author)->patch(route('business-calendars.publish', [$calendar]))->assertForbidden();
        $this->actingAs($publisher)->patch(route('business-calendars.publish', [$calendar]))->assertRedirect();
        $this->assertSame('published', $calendar->refresh()->status);
        $this->assertSame(64, mb_strlen((string) $calendar->checksum));

        [$definition, $assessment] = $this->workflowFixture($calendar);
        $instance = app(StartWorkflow::class)->handle($definition, $assessment, $operator);
        $this->assertSame($calendar->id, $instance->business_calendar_id);
        $this->assertSame('2026-12-28T09:00:00+03:00', $instance->due_at?->setTimezone('Africa/Nairobi')->toIso8601String());

        $transitioned = app(TransitionWorkflow::class)->handle($instance, 'submit', $operator, ['ready' => true]);
        $this->assertSame('2026-12-28T16:00:00+03:00', $transitioned->due_at?->setTimezone('Africa/Nairobi')->toIso8601String());
        $this->travelTo(CarbonImmutable::parse('2026-12-28 15:59:59', 'Africa/Nairobi'));
        $this->assertSame(0, app(WorkflowSlaMonitor::class)->escalateOverdue());
        $this->travelTo(CarbonImmutable::parse('2026-12-28 16:00:01', 'Africa/Nairobi'));
        $this->assertSame(1, app(WorkflowSlaMonitor::class)->escalateOverdue());
        $this->assertDatabaseHas('audit_events', ['subject_id' => $calendar->id, 'action' => 'workflow.business-calendar.published']);
    }

    public function test_published_calendar_is_immutable_and_effective_versions_cannot_overlap(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $publisher = User::factory()->platformAdmin()->create();
        $calendar = BusinessCalendar::factory()->create(['created_by' => $author->id, 'effective_from' => '2026-01-01']);
        $this->actingAs($publisher)->patch(route('business-calendars.publish', [$calendar]))->assertRedirect();
        $this->actingAs($author)->post(route('business-calendars.holidays.store', [$calendar]), ['holiday_date' => '2026-10-20', 'name' => 'Mashujaa Day', 'category' => 'public_holiday', 'source_reference' => 'Public Holidays Act'])->assertStatus(409);
        $overlap = BusinessCalendar::factory()->create(['code' => $calendar->code, 'version' => 2, 'created_by' => $author->id, 'effective_from' => '2026-06-01']);
        $this->actingAs($publisher)->patch(route('business-calendars.publish', [$overlap]))->assertStatus(409);
        $this->assertSame('draft', $overlap->refresh()->status);
        try {
            BusinessCalendar::query()->whereKey($calendar)->update(['name' => 'Mutated calendar']);
            $this->fail('Published calendar mutation should be rejected by PostgreSQL.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Published business calendars are immutable', $exception->getMessage());
        }
    }

    public function test_workflow_registry_exposes_calendar_provenance_and_draft_holiday_soft_deletion(): void
    {
        $admin = User::factory()->devolutionAdmin()->create();
        $calendar = BusinessCalendar::factory()->create(['created_by' => $admin->id]);
        $holiday = BusinessCalendarHoliday::factory()->create(['business_calendar_id' => $calendar->id, 'created_by' => $admin->id]);
        $this->actingAs($admin)->get(route('workflows.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('workflows/index')->has('calendars', 1)->where('calendars.0.id', $calendar->id)->where('calendars.0.holidays.0.sourceReference', $holiday->source_reference));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($admin)->get(route('workspace.export', ['business-calendars', $format]))->assertOk()->assertDownload();
        }
        $this->actingAs($admin)->delete(route('business-calendars.holidays.destroy', [$calendar, $holiday]))->assertRedirect();
        $this->assertSoftDeleted($holiday);
    }

    /** @return array{WorkflowDefinition, Assessment} */
    private function workflowFixture(BusinessCalendar $calendar): array
    {
        $definition = WorkflowDefinition::factory()->create(['code' => 'BUSINESS-CALENDAR-SLA']);
        WorkflowVersion::factory()->published()->create(['workflow_definition_id' => $definition->id, 'configuration' => ['initial_state' => 'draft', 'states' => ['draft', 'submitted'], 'terminal_states' => [], 'business_calendar_id' => $calendar->id, 'state_slas' => ['draft' => 2, 'submitted' => 9], 'start_permission' => ProgrammePermission::SubmitAssessment->value, 'escalation_permission' => ProgrammePermission::ManageWorkflows->value, 'transitions' => [['name' => 'submit', 'from' => 'draft', 'to' => 'submitted', 'permission' => ProgrammePermission::SubmitAssessment->value]], 'rules' => []]]);

        return [$definition, Assessment::factory()->create()];
    }

    /** @return array<string, mixed> */
    private function calendarPayload(): array
    {
        return ['code' => 'KENYA-GOVERNMENT', 'name' => 'Kenya Government working calendar', 'timezone' => 'Africa/Nairobi', 'working_days' => [1, 2, 3, 4, 5], 'workday_starts_at' => '08:00', 'workday_ends_at' => '17:00', 'effective_from' => '2026-01-01', 'effective_to' => null];
    }
}
