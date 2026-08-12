<?php

namespace Tests\Feature;

use App\Models\AssessmentCriterion;
use App\Models\AssessmentCycle;
use App\Models\AssessmentScorecard;
use App\Models\AssessmentScorecardVersion;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentConfigurationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function versionPayload(int $devolvedFunctions = 14): array
    {
        return [
            'change_notes' => 'Approved ACPA configuration baseline.',
            'calculation_method' => 'mcda',
            'mcda_configuration' => ['normalization' => 'percentage', 'aggregation' => 'weighted_sum', 'missing_data' => 'incomplete'],
            'performance_thresholds' => [
                ['label' => 'Meets standard', 'minimum' => 70, 'maximum' => 100, 'color' => 'green'],
                ['label' => 'Needs improvement', 'minimum' => 0, 'maximum' => 69.9999, 'color' => 'amber'],
            ],
            'functions' => collect(range(1, $devolvedFunctions))->map(fn (int $number): array => [
                'code' => 'F'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'name' => "Devolved function {$number}",
                'description' => 'Function capacity and service delivery.',
                'function_type' => 'devolved',
                'weight' => $number === 14 ? 7.1423 : 7.1429,
                'sequence' => $number,
                'thematic_areas' => [[
                    'code' => "F{$number}-T1",
                    'name' => 'Institutional capacity',
                    'description' => 'Enabler thematic area.',
                    'weight' => 100,
                    'sequence' => 1,
                    'standards' => [[
                        'code' => "F{$number}-S1",
                        'name' => 'Sector standard',
                        'description' => 'Approved standard.',
                        'norm_reference' => 'Applicable approved sector norm.',
                        'weight' => 100,
                        'sequence' => 1,
                        'criteria' => [[
                            'code' => "F{$number}-C1",
                            'name' => 'Compliance and results criterion',
                            'description' => 'Evidence-based criterion.',
                            'weight' => 100,
                            'maximum_score' => 100,
                            'scoring_method' => 'scale',
                            'formula' => ['type' => 'linear'],
                            'thresholds' => [['label' => 'Compliant', 'minimum' => 70]],
                            'is_mandatory' => true,
                            'sequence' => 1,
                            'evidence_requirements' => [[
                                'code' => "F{$number}-E1",
                                'name' => 'Primary evidence',
                                'description' => 'Approved primary evidence.',
                                'minimum_documents' => 1,
                                'allowed_categories' => ['policy', 'report'],
                                'accepted_mime_types' => ['application/pdf', 'image/jpeg'],
                                'requires_verification' => true,
                                'is_mandatory' => true,
                            ]],
                        ]],
                    ]],
                ]],
            ])->all(),
        ];
    }

    public function test_administrator_can_publish_fourteen_function_scorecard_and_create_cycle(): void
    {
        $admin = User::factory()->devolutionAdmin()->create();
        $this->actingAs($admin)->post(route('assessment-configuration.scorecards.store'), [
            'code' => 'DPA',
            'name' => 'Devolution Performance Assessment',
            'description' => 'National and county performance scorecard.',
            'status' => 'active',
        ])->assertRedirect();
        $scorecard = AssessmentScorecard::query()->sole();

        $this->actingAs($admin)->post(route('assessment-configuration.scorecards.versions.store', [$scorecard]), $this->versionPayload())->assertRedirect();
        $version = AssessmentScorecardVersion::query()->sole();
        $this->assertSame(14, $version->functions()->count());
        $this->assertSame(14, AssessmentCriterion::query()->count());

        $this->actingAs($admin)->patch(route('assessment-configuration.scorecards.versions.publish', [$scorecard, $version]))->assertRedirect();
        $version->refresh();
        $this->assertSame('published', $version->status);
        $this->assertSame(64, Str::length((string) $version->checksum));
        $this->assertSame($admin->id, $version->published_by);

        $this->actingAs($admin)->post(route('assessment-configuration.cycles.store'), [
            'code' => 'ACPA-2026-27',
            'name' => 'ACPA 2026/27',
            'assessment_scorecard_version_id' => $version->id,
            'period_start' => '2026-07-01',
            'period_end' => '2027-06-30',
            'submission_opens_at' => '2026-08-01 08:00:00',
            'submission_closes_at' => '2026-10-31 17:00:00',
            'status' => 'planned',
        ])->assertRedirect();

        $cycle = AssessmentCycle::query()->sole();
        $this->assertTrue(Str::isUuid($cycle->id));
        $this->assertSame($version->id, $cycle->assessment_scorecard_version_id);
        $this->assertSame(4, AuditEvent::query()->count());

        $this->actingAs($admin)->get(route('assessment-configuration.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assessment-configuration/index')
                ->where('scorecards.data.0.versions.0.functionCount', 14)
                ->where('cycles.data.0.code', 'ACPA-2026-27'));
    }

    public function test_scorecard_requires_exactly_fourteen_devolved_functions(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $scorecard = AssessmentScorecard::factory()->create();

        $this->actingAs($admin)->post(route('assessment-configuration.scorecards.versions.store', [$scorecard]), $this->versionPayload(13))
            ->assertSessionHasErrors('functions');
        $this->assertDatabaseCount('assessment_scorecard_versions', 0);
    }

    public function test_published_scorecard_version_is_database_immutable(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL immutability triggers are database specific.');
        }

        $admin = User::factory()->devolutionAdmin()->create();
        $scorecard = AssessmentScorecard::factory()->create();
        $this->actingAs($admin)->post(route('assessment-configuration.scorecards.versions.store', [$scorecard]), $this->versionPayload())->assertRedirect();
        $version = AssessmentScorecardVersion::query()->sole();
        $this->actingAs($admin)->patch(route('assessment-configuration.scorecards.versions.publish', [$scorecard, $version]))->assertRedirect();

        $this->expectException(QueryException::class);
        $version->update(['calculation_method' => 'weighted_sum']);
    }

    public function test_published_scorecard_component_update_is_database_immutable(): void
    {
        [$version] = $this->publishScorecardVersion();

        $this->expectException(QueryException::class);
        AssessmentCriterion::query()->firstOrFail()->update(['name' => 'Tampered']);
    }

    public function test_component_cannot_be_inserted_beneath_published_scorecard(): void
    {
        [$version] = $this->publishScorecardVersion();

        $function = $version->functions()->firstOrFail();
        $this->expectException(QueryException::class);
        $function->thematicAreas()->create([
            'code' => 'TAMPERED',
            'name' => 'Tampered',
            'weight' => 100,
            'sequence' => 99,
        ]);
    }

    /** @return array{AssessmentScorecardVersion, User} */
    private function publishScorecardVersion(): array
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL immutability triggers are database specific.');
        }

        $admin = User::factory()->devolutionAdmin()->create();
        $scorecard = AssessmentScorecard::factory()->create();
        $this->actingAs($admin)->post(route('assessment-configuration.scorecards.versions.store', [$scorecard]), $this->versionPayload())->assertRedirect();
        $version = AssessmentScorecardVersion::query()->sole();
        $this->actingAs($admin)->patch(route('assessment-configuration.scorecards.versions.publish', [$scorecard, $version]))->assertRedirect();

        return [$version->refresh(), $admin];
    }

    public function test_county_user_cannot_manage_assessment_configuration(): void
    {
        $official = User::factory()->countyOfficial()->create();

        $this->actingAs($official)->get(route('assessment-configuration.index'))->assertForbidden();
        $this->actingAs($official)->post(route('assessment-configuration.scorecards.store'), [])->assertForbidden();
    }
}
