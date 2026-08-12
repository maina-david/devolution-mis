<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\RolloutWave;
use App\Models\TrainingAssessment;
use App\Models\TrainingCohort;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChangeReadinessWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_is_separated_from_independent_competency_and_readiness_approval(): void
    {
        $county = County::factory()->create();
        $author = User::factory()->devolutionAdmin()->create();
        $trainer = User::factory()->platformAdmin()->create();
        $approver = User::factory()->topManagement()->create();
        $participantUser = User::factory()->countyOfficial($county)->create();
        $release = $this->publishedReferenceRelease([$county], $author);

        $this->actingAs($author)->post(route('change-readiness.waves.store'), $this->wavePayload([$county->id], 1))->assertRedirect();
        $wave = RolloutWave::query()->sole();
        $this->assertTrue(Str::isUuid($wave->id));
        $this->assertSame('planning', $wave->status);
        $this->assertSame($release->id, $wave->reference_data_release_id);
        $this->assertSame([$county->id], $wave->counties()->pluck('counties.id')->all());

        $this->actingAs($author)->post(route('change-readiness.cohorts.store'), $this->cohortPayload($wave, $county, $trainer))->assertRedirect();
        $cohort = TrainingCohort::query()->sole();
        $this->assertSame($release->id, $cohort->reference_data_release_id);
        $this->actingAs($author)->post(route('change-readiness.participants.store'), ['training_cohort_id' => $cohort->id, 'user_id' => $participantUser->id, 'county_id' => $county->id, 'participant_reference' => 'TRN-2026-0001', 'role_title' => 'County M&E officer'])->assertRedirect();
        $participant = TrainingParticipant::query()->sole();
        $this->assertNull($participant->completed_at);

        $this->actingAs($approver)->patch(route('change-readiness.waves.approve', [$wave]), ['readiness_notes' => 'Attempt before competency completion evidence has been recorded.'])->assertSessionHasErrors('status');
        $this->actingAs($trainer)->post(route('change-readiness.assessments.store', [$participant]), ['assessment_type' => 'post_training', 'score' => 82, 'attended_hours' => 7, 'feedback' => 'Participant demonstrated the required operational workflow and county reporting competency.', 'evidence_references' => ['ATTENDANCE-001', 'PRACTICAL-001']])->assertRedirect();
        $this->assertSame('competent', $participant->refresh()->competency_status);
        $this->assertNotNull($participant->completed_at);
        $this->assertDatabaseCount('training_assessments', 1);
        $this->assertSame('competent', TrainingAssessment::query()->sole()->outcome);

        $this->actingAs($author)->patch(route('change-readiness.waves.approve', [$wave]), ['readiness_notes' => 'Author attempts to approve their own readiness plan after completion evidence exists.'])->assertForbidden();
        $this->actingAs($approver)->patch(route('change-readiness.waves.approve', [$wave]), ['readiness_notes' => 'Training, competency, help-desk rehearsal and approved material evidence were independently reviewed.'])->assertRedirect();
        $this->assertSame('approved', $wave->refresh()->status);
        $this->assertSame($approver->id, $wave->approved_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $wave->id, 'action' => 'change-readiness.wave.approved']);
    }

    public function test_cohort_capacity_and_county_boundaries_are_enforced(): void
    {
        $county = County::factory()->create();
        $other = County::factory()->create();
        $admin = User::factory()->devolutionAdmin()->create();
        $trainer = User::factory()->platformAdmin()->create();
        $this->publishedReferenceRelease([$county, $other], $admin);
        $this->actingAs($admin)->post(route('change-readiness.waves.store'), $this->wavePayload([$county->id], 1))->assertRedirect();
        $wave = RolloutWave::query()->sole();
        $invalid = $this->cohortPayload($wave, $other, $trainer);
        $this->actingAs($admin)->post(route('change-readiness.cohorts.store'), $invalid)->assertSessionHasErrors('county_id');
        $this->actingAs($admin)->post(route('change-readiness.cohorts.store'), $this->cohortPayload($wave, $county, $trainer))->assertRedirect();
        $cohort = TrainingCohort::query()->sole();
        $this->actingAs($admin)->post(route('change-readiness.participants.store'), ['training_cohort_id' => $cohort->id, 'county_id' => $other->id, 'participant_reference' => 'WRONG-COUNTY', 'role_title' => 'Officer'])->assertSessionHasErrors('county_id');
        $this->actingAs($admin)->post(route('change-readiness.participants.store'), ['training_cohort_id' => $cohort->id, 'county_id' => $county->id, 'participant_reference' => 'SEAT-001', 'role_title' => 'Officer'])->assertRedirect();
        $this->actingAs($admin)->post(route('change-readiness.participants.store'), ['training_cohort_id' => $cohort->id, 'county_id' => $county->id, 'participant_reference' => 'SEAT-002', 'role_title' => 'Officer'])->assertSessionHasErrors('training_cohort_id');

        $countyViewer = User::factory()->countyOfficial($county)->create();
        $otherViewer = User::factory()->countyOfficial($other)->create();
        $this->actingAs($countyViewer)->get(route('change-readiness.index'))->assertOk()->assertInertia(fn ($page) => $page->where('cohorts.total', 1));
        $this->actingAs($otherViewer)->get(route('change-readiness.index'))->assertOk()->assertInertia(fn ($page) => $page->where('cohorts.total', 0)->where('waves', []));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($countyViewer)->get(route('workspace.export', ['change-readiness', $format]))->assertOk()->assertDownload();
        }
    }

    public function test_rollout_planning_fails_closed_without_complete_catalogue_lineage(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->devolutionAdmin()->create();
        $payload = $this->wavePayload([$county->id], 10);

        $this->actingAs($admin)->post(route('change-readiness.waves.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('rollout_waves', 0);

        $snapshot = ['counties' => [], 'organizations' => [], 'sectors' => [], 'programmes' => []];
        ReferenceDataRelease::factory()->create(['approved_by' => $admin->id, 'status' => 'published', 'snapshot' => $snapshot, 'checksum' => app(CanonicalJson::class)->checksum($snapshot), 'effective_from' => now()->subMinute(), 'published_at' => now()]);
        $this->actingAs($admin)->post(route('change-readiness.waves.store'), $payload)->assertSessionHasErrors('county_ids');
        $this->assertDatabaseCount('rollout_waves', 0);
    }

    /** @param list<string> $countyIds */
    private function wavePayload(array $countyIds, int $participants): array
    {
        return ['code' => 'WAVE-PILOT-2026', 'name' => 'Representative county pilot', 'objective' => 'Validate operational readiness, learning transfer and support escalation before national rollout.', 'starts_on' => now()->addMonth()->toDateString(), 'ends_on' => now()->addMonths(2)->toDateString(), 'planned_participants' => $participants, 'county_ids' => $countyIds, 'entry_criteria' => ['County sponsor named', 'Connectivity check passed'], 'support_channels' => ['help desk', 'knowledge base'], 'help_desk_rehearsed' => true, 'training_materials_approved' => true];
    }

    /** @return array<string, mixed> */
    private function cohortPayload(RolloutWave $wave, County $county, User $trainer): array
    {
        return ['rollout_wave_id' => $wave->id, 'county_id' => $county->id, 'facilitator_id' => $trainer->id, 'code' => 'COHORT-'.$county->code, 'name' => $county->name.' county operator cohort', 'audience_role' => 'county-official', 'delivery_mode' => 'blended', 'language' => 'en', 'venue' => 'County training room', 'seat_capacity' => 1, 'minimum_attendance_hours' => 6, 'passing_score' => 70, 'starts_at' => now()->addMonth()->toIso8601String(), 'ends_at' => now()->addMonth()->addDay()->toIso8601String()];
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, User $approver): ReferenceDataRelease
    {
        $snapshot = ['counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id, 'code' => $county->code, 'name' => $county->name])->values()->all(), 'organizations' => [], 'sectors' => [], 'programmes' => []];

        return ReferenceDataRelease::factory()->create(['approved_by' => $approver->id, 'status' => 'published', 'snapshot' => $snapshot, 'checksum' => app(CanonicalJson::class)->checksum($snapshot), 'effective_from' => now()->subMinute(), 'published_at' => now()]);
    }
}
