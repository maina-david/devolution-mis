<?php

namespace Tests\Feature;

use App\Actions\CreateProgrammeCountyCoverage;
use App\Models\County;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProgrammeCountyCoverage;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use Database\Seeders\ProgrammeCountyCoverageSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProgrammeCountyCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_manager_creates_filters_and_exports_governed_county_coverage(): void
    {
        $manager = User::factory()->devolutionAdmin()->create();
        $county = County::factory()->create(['name' => 'Mombasa', 'code' => 1, 'logo_path' => '/images/counties/mombasa.webp']);
        $otherCounty = County::factory()->create(['name' => 'Kwale', 'code' => 2]);
        $lead = Organization::factory()->create(['name' => 'Mombasa County Government']);
        $programme = Programme::factory()->create([
            'code' => 'KDSP-II',
            'name' => 'Second Kenya Devolution Support Program',
            'starts_on' => '2024-07-01',
            'ends_on' => '2028-06-30',
            'currency' => 'KES',
        ]);

        $this->actingAs($manager)->post(route('reference-data.programme-coverages.store', $manager->currentTeam->slug), $this->payload($programme, $county, $lead))->assertRedirect();

        $coverage = ProgrammeCountyCoverage::query()->sole();
        $this->assertTrue(Str::isUuid($coverage->id));
        $this->assertSame($manager->id, $coverage->created_by);
        $this->assertSame('25000000.00', $coverage->funding_allocation);
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $coverage->id,
            'county_id' => $county->id,
            'action' => 'reference.programme-coverage.created',
        ]);

        $this->actingAs($manager)->get(route('reference-data.index', [
            'current_team' => $manager->currentTeam->slug,
            'county_id' => $county->id,
            'status' => 'active',
            'from' => '2025-01-01',
            'to' => '2026-12-31',
        ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('reference-data/index')
            ->where('programmeCoverages.pagination.total', 1)
            ->where('programmeCoverages.rows.0.id', $coverage->id)
            ->where('programmeCoverages.rows.0.cells.0', 'KDSP-II · Second Kenya Devolution Support Program')
            ->where('programmeCoverages.rows.0.cells.1.kind', 'county')
            ->where('programmeCoverages.rows.0.cells.1.name', 'Mombasa')
            ->where('programmeCoverages.rows.0.cells.1.logoUrl', '/images/counties/mombasa.webp')
            ->where('programmeCoverages.rows.0.cells.3', 'Mombasa County Government')
            ->where('filters.county_id', $county->id)
            ->where('filters.status', 'active'));

        $this->actingAs($manager)->get(route('reference-data.index', [
            'current_team' => $manager->currentTeam->slug,
            'county_id' => $otherCounty->id,
        ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('programmeCoverages.pagination.total', 0));

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $response = $this->actingAs($manager)->get(route('workspace.export', [
                'current_team' => $manager->currentTeam->slug,
                'workspace' => 'programme-coverage',
                'format' => $format,
                'county_id' => $county->id,
            ]));
            $response->assertOk();
            $this->assertStringContainsString($format === 'pdf' ? 'application/pdf' : ($format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : $format), (string) $response->headers->get('content-type'));
        }
    }

    public function test_coverage_validation_rejects_out_of_programme_and_overlapping_periods(): void
    {
        $manager = User::factory()->platformAdmin()->create();
        $programme = Programme::factory()->create(['starts_on' => '2025-07-01', 'ends_on' => '2028-06-30']);
        $county = County::factory()->create();
        $lead = Organization::factory()->create();

        $this->actingAs($manager)->post(route('reference-data.programme-coverages.store', $manager->currentTeam->slug), $this->payload($programme, $county, $lead, [
            'starts_on' => '2025-01-01',
            'ends_on' => '2028-12-31',
        ]))->assertSessionHasErrors(['starts_on', 'ends_on']);

        $this->actingAs($manager)->post(route('reference-data.programme-coverages.store', $manager->currentTeam->slug), $this->payload($programme, $county, $lead))->assertRedirect();
        $this->actingAs($manager)->post(route('reference-data.programme-coverages.store', $manager->currentTeam->slug), $this->payload($programme, $county, $lead, [
            'starts_on' => '2027-01-01',
            'ends_on' => '2028-06-30',
        ]))->assertStatus(409);

        $otherCounty = County::factory()->create();
        $this->actingAs($manager)->post(route('reference-data.programme-coverages.store', $manager->currentTeam->slug), $this->payload($programme, $otherCounty, $lead))->assertRedirect();
        $this->assertSame(2, ProgrammeCountyCoverage::query()->count());
    }

    public function test_postgresql_constraint_rejects_overlaps_outside_the_application_action(): void
    {
        $coverage = ProgrammeCountyCoverage::factory()->create([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $this->expectException(QueryException::class);
        ProgrammeCountyCoverage::factory()->create([
            'programme_id' => $coverage->programme_id,
            'county_id' => $coverage->county_id,
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-05-31',
        ]);
    }

    public function test_action_boundary_independently_enforces_programme_dates(): void
    {
        $manager = User::factory()->platformAdmin()->create();
        $programme = Programme::factory()->create(['starts_on' => '2025-01-01', 'ends_on' => '2027-12-31']);
        $county = County::factory()->create();

        try {
            app(CreateProgrammeCountyCoverage::class)->handle($manager, [
                'programme_id' => $programme->id,
                'county_id' => $county->id,
                'implementation_lead_id' => null,
                'starts_on' => '2024-12-31',
                'ends_on' => '2028-01-01',
                'status' => 'planned',
                'funding_allocation' => null,
                'currency' => 'KES',
                'source_reference' => 'SDD/KDSP/INVALID-DATES',
                'notes' => null,
            ]);
            $this->fail('The action must reject coverage outside the programme period.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('programme_county_coverages', 0);
    }

    public function test_coverage_is_permission_protected_archivable_and_included_in_immutable_release_snapshot(): void
    {
        $manager = User::factory()->platformAdmin()->create();
        $official = User::factory()->countyOfficial()->create();
        $programme = Programme::factory()->create(['starts_on' => '2024-07-01', 'ends_on' => '2028-06-30']);
        $county = County::factory()->create();
        $lead = Organization::factory()->create();
        $payload = $this->payload($programme, $county, $lead);

        $this->actingAs($official)->post(route('reference-data.programme-coverages.store', $official->currentTeam->slug), $payload)->assertForbidden();
        $this->actingAs($manager)->post(route('reference-data.programme-coverages.store', $manager->currentTeam->slug), $payload)->assertRedirect();
        $coverage = ProgrammeCountyCoverage::query()->sole();

        $this->actingAs($manager)->post(route('reference-data.releases.store', $manager->currentTeam->slug), [
            'change_summary' => 'Add governed county implementation coverage to the canonical programme catalogue.',
        ])->assertRedirect();
        $release = ReferenceDataRelease::query()->sole();
        $this->assertCount(1, $release->snapshot['programme_county_coverages']);
        $this->assertSame($coverage->id, $release->snapshot['programme_county_coverages'][0]['id']);

        $this->actingAs($manager)->delete(route('reference-data.programmes.destroy', [$manager->currentTeam->slug, $programme]))->assertStatus(409);
        $this->actingAs($manager)->delete(route('reference-data.programme-coverages.destroy', [$manager->currentTeam->slug, $coverage]))->assertRedirect();
        $this->assertSoftDeleted($coverage);
        $this->assertCount(1, $release->fresh()->snapshot['programme_county_coverages']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $coverage->id, 'action' => 'reference.programme-coverage.archived']);

        $this->actingAs($official)->get(route('workspace.export', [
            'current_team' => $official->currentTeam->slug,
            'workspace' => 'programme-coverage',
            'format' => 'json',
        ]))->assertForbidden();
    }

    public function test_targeted_kdsp_coverage_baseline_is_idempotent_and_does_not_invent_allocations_or_leads(): void
    {
        User::factory()->devolutionAdmin()->create(['email' => 'devolution.admin@idmis.test']);
        County::factory()->count(47)->sequence(fn ($sequence): array => ['code' => $sequence->index + 1])->create();
        Programme::factory()->create([
            'code' => 'KDSP-II',
            'name' => 'Second Kenya Devolution Support Program',
            'starts_on' => '2024-07-01',
            'ends_on' => '2028-06-30',
            'currency' => 'KES',
        ]);

        $this->seed(ProgrammeCountyCoverageSeeder::class);
        $this->seed(ProgrammeCountyCoverageSeeder::class);

        $this->assertSame(47, ProgrammeCountyCoverage::query()->count());
        $this->assertSame(47, ProgrammeCountyCoverage::query()->whereNull('funding_allocation')->whereNull('implementation_lead_id')->count());
        $this->assertSame(47, ProgrammeCountyCoverage::query()->where('source_reference', 'like', 'IDMIS-TOR-KDSP-II-COUNTY-%')->count());
    }

    public function test_reference_data_page_uses_shared_governed_workspace_controls_for_coverage(): void
    {
        $source = file_get_contents(resource_path('js/pages/reference-data/index.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString('<DateRangeFilter', $source);
        $this->assertStringContainsString('<WorkspaceDataTable', $source);
        $this->assertStringContainsString("workspace: 'programme-coverage'", $source);
        $this->assertStringContainsString('storeProgrammeCoverage.form', $source);
        $this->assertStringContainsString('destroyProgrammeCoverage.form', $source);
        $this->assertStringContainsString('<DatePickerField', $source);
        $this->assertStringContainsString('<SearchableSelect', $source);
        $this->assertStringContainsString('<Sheet open={open}', $source);
        $this->assertStringNotContainsString('type="date"', $source);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(Programme $programme, County $county, Organization $lead, array $overrides = []): array
    {
        return [
            'programme_id' => $programme->id,
            'county_id' => $county->id,
            'implementation_lead_id' => $lead->id,
            'starts_on' => '2025-07-01',
            'ends_on' => '2028-06-30',
            'status' => 'active',
            'funding_allocation' => 25000000,
            'currency' => 'KES',
            'source_reference' => 'SDD/KDSP-II/COVERAGE/001',
            'notes' => 'County implementation coverage approved for the programme period.',
            ...$overrides,
        ];
    }
}
