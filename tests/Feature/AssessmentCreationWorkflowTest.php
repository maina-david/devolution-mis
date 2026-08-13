<?php

namespace Tests\Feature;

use App\Actions\CreateAssessment;
use App\Enums\AssessmentStatus;
use App\Enums\ProgrammePermission;
use App\Models\Assessment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentScorecardVersion;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssessmentCreationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrator_can_initiate_a_governed_assessment_with_complete_lineage(): void
    {
        $county = County::factory()->create(['name' => 'Makueni']);
        $administrator = User::factory()->devolutionAdmin()->create(['name' => 'Assessment Administrator']);
        $cycle = AssessmentCycle::factory()->create(['code' => 'ACPA-2027-28', 'name' => 'FY 2027/28 ACPA', 'status' => 'planned']);
        $release = $this->publishedReferenceRelease([$county], $administrator);

        $this->actingAs($administrator)->get(route('assessments.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('capabilities.create', true)
                ->where('workspace.assessmentCreationOptions.counties.0.id', $county->id)
                ->where('workspace.assessmentCreationOptions.cycles.0.id', $cycle->id)
                ->where('workspace.assessmentCreationOptions.pairs.0.countyId', $county->id)
                ->where('workspace.assessmentCreationOptions.pairs.0.cycleId', $cycle->id));

        $response = $this->actingAs($administrator)->post(route('assessments.store'), [
            'county_id' => $county->id,
            'assessment_cycle_id' => $cycle->id,
        ]);

        $assessment = Assessment::query()->sole();
        $response->assertRedirect(route('assessments.show', [$assessment]));
        $this->assertSame($county->id, $assessment->county_id);
        $this->assertSame($cycle->id, $assessment->assessment_cycle_id);
        $this->assertSame($cycle->assessment_scorecard_version_id, $assessment->assessment_scorecard_version_id);
        $this->assertSame($release->id, $assessment->reference_data_release_id);
        $this->assertSame($administrator->id, $assessment->created_by);
        $this->assertSame($cycle->code, $assessment->cycle);
        $this->assertSame(AssessmentStatus::Draft, $assessment->status);

        $event = AuditEvent::query()->where('subject_id', $assessment->id)->where('action', 'assessment.created')->sole();
        $this->assertSame($cycle->id, $event->metadata['assessment_cycle_id']);
        $this->assertSame($cycle->assessment_scorecard_version_id, $event->metadata['assessment_scorecard_version_id']);
        $this->assertSame($release->id, $event->metadata['reference_data_release_id']);
        $this->assertSame($release->checksum, $event->metadata['reference_data_release_checksum']);

        $this->actingAs($administrator)->get(route('assessments.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('programme/workspace')
                ->where('capabilities.create', true)
                ->where('workspace.columns.2', 'Reference release')
                ->where('workspace.columns.3', 'Reference checksum')
                ->where('workspace.rows.0.cells.2', "v{$release->version} · {$release->effective_from?->toDateString()}")
                ->where('workspace.rows.0.cells.3', $release->checksum)
                ->where('workspace.rows.0.cells.4', $administrator->name)
                ->where('workspace.rows.0.meta.isLegacy', 'false')
                ->has('workspace.assessmentCreationOptions.pairs', 0));

        $this->actingAs($administrator)->get(route('assessments.show', [$assessment]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assessment.referenceRelease.version', $release->version)
                ->where('assessment.referenceRelease.checksum', $release->checksum)
                ->where('assessment.createdBy', $administrator->name));

        foreach (['csv', 'json'] as $format) {
            $content = $this->actingAs($administrator)
                ->get(route('workspace.export', ['assessments', $format]))
                ->assertOk()
                ->streamedContent();
            $this->assertStringContainsString($release->checksum, $content);
            $this->assertStringContainsString('Assessment Administrator', $content);
        }
        $this->actingAs($administrator)->get(route('workspace.export', ['assessments', 'xlsx']))->assertOk()->assertDownload();
        $this->actingAs($administrator)->get(route('workspace.export', ['assessments', 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_creation_fails_closed_without_a_complete_effective_reference_release(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $cycle = AssessmentCycle::factory()->create();
        $route = route('assessments.store');
        $payload = ['county_id' => $county->id, 'assessment_cycle_id' => $cycle->id];

        $this->actingAs($administrator)->post($route, $payload)->assertStatus(409);
        $this->assertSame(0, Assessment::query()->count());

        $this->publishedReferenceRelease([], $administrator);
        $this->actingAs($administrator)->from(route('assessments.index'))->post($route, $payload)
            ->assertSessionHasErrors('county_id');
        $this->assertSame(0, Assessment::query()->count());

        $this->publishedReferenceRelease([$county], $administrator);
        $this->actingAs($administrator)->post($route, $payload)->assertRedirect();
        $this->assertSame(1, Assessment::query()->count());
    }

    public function test_creation_rejects_unreleased_scorecards_duplicates_and_unauthorized_counties(): void
    {
        $homeCounty = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $countyAdministrator = User::factory()->countyAdmin($homeCounty)->create();
        $countyAdministrator->givePermissionTo(ProgrammePermission::ManageAssessmentConfiguration->value);
        $this->publishedReferenceRelease([$homeCounty, $outsideCounty], $administrator);

        $draftVersion = AssessmentScorecardVersion::factory()->create(['status' => 'draft', 'checksum' => str_repeat('a', 64)]);
        $invalidCycle = AssessmentCycle::factory()->create(['assessment_scorecard_version_id' => $draftVersion->id, 'status' => 'open']);
        $this->actingAs($administrator)->post(route('assessments.store'), [
            'county_id' => $homeCounty->id,
            'assessment_cycle_id' => $invalidCycle->id,
        ])->assertStatus(409);

        $cycle = AssessmentCycle::factory()->create(['status' => 'open']);
        try {
            app(CreateAssessment::class)->handle($countyAdministrator, $outsideCounty->id, $cycle->id);
            $this->fail('Out-of-scope assessment creation should fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_duplicate_and_unauthorized_http_creation_are_rejected(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $cycle = AssessmentCycle::factory()->create();
        $this->publishedReferenceRelease([$county], $administrator);
        $payload = ['county_id' => $county->id, 'assessment_cycle_id' => $cycle->id];

        $this->actingAs($official)->post(route('assessments.store'), $payload)->assertForbidden();
        $this->actingAs($administrator)->post(route('assessments.store'), $payload)->assertRedirect();
        $this->actingAs($administrator)->from(route('assessments.index'))->post(route('assessments.store'), $payload)
            ->assertSessionHasErrors('county_id');
        $this->assertSame(1, Assessment::query()->count());
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, User $approver): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
            'sectors' => [],
            'programmes' => [],
            'programme_county_coverages' => [],
        ];
        $version = ((int) ReferenceDataRelease::query()->max('version')) + 1;

        return ReferenceDataRelease::factory()->create([
            'version' => $version,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-ACPA-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
