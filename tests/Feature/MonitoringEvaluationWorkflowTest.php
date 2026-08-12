<?php

namespace Tests\Feature;

use App\Actions\CreateProgrammeEvaluation;
use App\Actions\RecordIndicatorObservation;
use App\Actions\SupersedeIndicatorDefinition;
use App\Actions\VerifyIndicatorObservation;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\DocumentLink;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorObservation;
use App\Models\Programme;
use App\Models\ProgrammeEvaluation;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Services\MonitoringEvaluationResults;
use App\Services\ProgrammeWorkspaceData;
use App\Support\CanonicalJson;
use App\Support\WorkspaceFilters;
use Database\Seeders\ProgrammeEvaluationWorkflowSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MonitoringEvaluationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_user_records_an_approved_indicator_observation_with_provenance(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyAdmin($county)->create();
        $indicator = IndicatorDefinition::factory()->create();
        $programme = Programme::factory()->create();

        $observation = app(RecordIndicatorObservation::class)->handle($user, $this->observationPayload($indicator, $county, $programme));

        $this->assertTrue(Str::isUuid($observation->id));
        $this->assertSame('county-mis', $observation->provenance['source_system']);
        $this->assertSame('submitted', $observation->verification_status);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $observation->id, 'action' => 'indicator.observation.submitted']);

        $observation->delete();
        $this->assertSoftDeleted($observation);
    }

    public function test_county_user_cannot_submit_data_for_another_county(): void
    {
        $homeCounty = County::factory()->create();
        $otherCounty = County::factory()->create();
        $user = User::factory()->countyAdmin($homeCounty)->create();

        $this->expectException(HttpException::class);
        app(RecordIndicatorObservation::class)->handle(
            $user,
            $this->observationPayload(IndicatorDefinition::factory()->create(), $otherCounty, Programme::factory()->create()),
        );
    }

    public function test_observation_requires_an_approved_indicator(): void
    {
        $county = County::factory()->create();
        $user = User::factory()->countyAdmin($county)->create();

        $this->expectException(ValidationException::class);
        app(RecordIndicatorObservation::class)->handle(
            $user,
            $this->observationPayload(IndicatorDefinition::factory()->draft()->create(), $county, Programme::factory()->create()),
        );
    }

    public function test_independent_verifier_can_verify_but_submitter_cannot_self_verify(): void
    {
        $county = County::factory()->create();
        $submitter = User::factory()->countyAdmin($county)->create();
        $verifier = User::factory()->assessor()->create();
        $verifier->assignedCounties()->attach($county);
        $observation = app(RecordIndicatorObservation::class)->handle(
            $submitter,
            $this->observationPayload(IndicatorDefinition::factory()->create(), $county, Programme::factory()->create()),
        );

        try {
            app(VerifyIndicatorObservation::class)->handle($submitter, $observation, $this->verificationPayload());
            $this->fail('The submitter unexpectedly verified their own observation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verification_status', $exception->errors());
        }

        $verified = app(VerifyIndicatorObservation::class)->handle($verifier, $observation, $this->verificationPayload());
        $this->assertSame('verified', $verified->verification_status);
        $this->assertSame($verifier->id, $verified->verified_by);

        $this->expectException(ValidationException::class);
        app(VerifyIndicatorObservation::class)->handle($verifier, $verified, $this->verificationPayload());
    }

    public function test_indicator_definition_requires_independent_approval(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $approver = User::factory()->devolutionAdmin()->create();
        $indicator = IndicatorDefinition::factory()->draft()->create(['created_by' => $author->id]);

        $this->actingAs($author)
            ->patch(route('monitoring-evaluation.indicators.approve', [$indicator]))
            ->assertSessionHasErrors('indicator');

        $this->actingAs($approver)
            ->patch(route('monitoring-evaluation.indicators.approve', [$indicator]))
            ->assertRedirect();

        $indicator->refresh();
        $this->assertSame('approved', $indicator->status);
        $this->assertSame($approver->id, $indicator->approved_by);
        $this->assertNotNull($indicator->approved_at);
    }

    public function test_approved_indicator_definition_is_database_immutable(): void
    {
        $indicator = IndicatorDefinition::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('indicator_definitions')->where('id', $indicator->id)->update(['name' => 'Mutated definition']);
    }

    public function test_current_indicator_can_be_superseded_without_mutating_historical_definition_or_observations(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $release = $this->publishedReferenceRelease([], [], $author);
        $indicator = IndicatorDefinition::factory()->create(['code' => 'M07-OUT-01', 'version' => 1]);
        $observation = IndicatorObservation::factory()->create(['indicator_definition_id' => $indicator->id]);

        $successor = app(SupersedeIndicatorDefinition::class)->handle($author, $indicator, $this->supersessionPayload());

        $this->assertTrue(Str::isUuid($successor->id));
        $this->assertSame($indicator->id, $successor->supersedes_id);
        $this->assertSame($indicator->code, $successor->code);
        $this->assertSame(2, $successor->version);
        $this->assertSame('draft', $successor->status);
        $this->assertSame($release->id, $successor->reference_data_release_id);
        $this->assertSame($indicator->id, $observation->refresh()->indicator_definition_id);
        $this->assertSame('approved', $indicator->refresh()->status);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $successor->id, 'action' => 'indicator.definition.supersession.drafted']);

        $this->expectException(ValidationException::class);
        app(SupersedeIndicatorDefinition::class)->handle($author, $indicator, $this->supersessionPayload());
    }

    public function test_independent_approval_promotes_successor_to_projects_and_closes_old_version_to_new_data(): void
    {
        $county = County::factory()->create();
        $author = User::factory()->devolutionAdmin()->create();
        $approver = User::factory()->devolutionAdmin()->create();
        $submitter = User::factory()->countyAdmin($county)->create();
        $programme = Programme::factory()->create();
        $this->publishedReferenceRelease([], [], $author);
        $indicator = IndicatorDefinition::factory()->create(['code' => 'M07-OUT-02', 'version' => 1]);
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $project->indicators()->attach($indicator, ['is_primary' => true]);
        $successor = app(SupersedeIndicatorDefinition::class)->handle($author, $indicator, $this->supersessionPayload());

        $this->actingAs($author)
            ->patch(route('monitoring-evaluation.indicators.approve', [$successor]))
            ->assertSessionHasErrors('indicator');
        $this->actingAs($approver)
            ->patch(route('monitoring-evaluation.indicators.approve', [$successor]))
            ->assertRedirect();

        $this->assertSame('approved', $successor->refresh()->status);
        $this->assertDatabaseHas('devolution_project_indicator', ['devolution_project_id' => $project->id, 'indicator_definition_id' => $successor->id, 'is_primary' => true]);
        $this->assertDatabaseHas('devolution_project_indicator', ['devolution_project_id' => $project->id, 'indicator_definition_id' => $indicator->id]);

        try {
            app(RecordIndicatorObservation::class)->handle($submitter, $this->observationPayload($indicator, $county, $programme));
            $this->fail('The superseded indicator unexpectedly accepted a new observation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('indicator_definition_id', $exception->errors());
        }

        $observation = app(RecordIndicatorObservation::class)->handle($submitter, $this->observationPayload($successor, $county, $programme));
        $this->assertSame($successor->id, $observation->indicator_definition_id);
    }

    public function test_indicator_creation_pins_complete_catalogue_and_fails_closed_without_it(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $sector = Sector::factory()->create();
        $programme = Programme::factory()->create(['sector_id' => $sector->id]);
        $payload = ['code' => 'M07-CATALOGUE-01', 'name' => 'Catalogue governed indicator', 'description' => 'A governed results-chain indicator definition.', 'sector_id' => $sector->id, 'programme_id' => $programme->id, 'results_level' => 'outcome', 'unit_of_measure' => 'percent', 'value_type' => 'percentage', 'direction' => 'increase', 'frequency' => 'quarterly', 'data_source' => 'Verified programme records', 'verification_method' => 'Independent source review'];

        $this->actingAs($author)->post(route('monitoring-evaluation.indicators.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('indicator_definitions', 0);

        $release = $this->publishedReferenceRelease([], [$programme], $author, [$sector]);
        $this->actingAs($author)->post(route('monitoring-evaluation.indicators.store'), $payload)->assertRedirect();
        $indicator = IndicatorDefinition::query()->sole();
        $this->assertSame($release->id, $indicator->reference_data_release_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $indicator->id, 'action' => 'indicator.definition.created']);
    }

    public function test_user_without_indicator_management_permission_cannot_create_successor(): void
    {
        $countyUser = User::factory()->countyAdmin(County::factory()->create())->create();
        $indicator = IndicatorDefinition::factory()->create();

        $this->actingAs($countyUser)
            ->post(route('monitoring-evaluation.indicators.supersede', [$indicator]), $this->supersessionPayload())
            ->assertForbidden();
    }

    public function test_monitoring_page_and_export_are_limited_to_authorized_county(): void
    {
        $homeCounty = County::factory()->create(['name' => 'Visible County', 'logo_path' => '/images/counties/mombasa.webp']);
        $otherCounty = County::factory()->create(['name' => 'Hidden County']);
        $user = User::factory()->countyAdmin($homeCounty)->create();
        $programme = Programme::factory()->create();
        $indicator = IndicatorDefinition::factory()->create();
        IndicatorObservation::factory()->create(['county_id' => $homeCounty->id, 'programme_id' => $programme->id, 'indicator_definition_id' => $indicator->id]);
        IndicatorObservation::factory()->create(['county_id' => $otherCounty->id, 'programme_id' => $programme->id, 'indicator_definition_id' => $indicator->id]);
        ProgrammeEvaluation::factory()->create(['county_id' => $homeCounty->id, 'title' => 'Visible county evaluation']);
        ProgrammeEvaluation::factory()->create(['county_id' => $otherCounty->id, 'title' => 'Hidden county evaluation']);
        ProgrammeEvaluation::factory()->create(['county_id' => null, 'title' => 'National evaluation']);
        $this->actingAs($user)->get(route('monitoring-evaluation.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('monitoring-evaluation/index')
                ->where('workspace.pagination.total', 1)
                ->where('workspace.rows.0.cells.3.kind', 'county')
                ->where('workspace.rows.0.cells.3.name', 'Visible County')
                ->where('options.counties.0.logoUrl', '/images/counties/mombasa.webp')
                ->has('options.evaluations', 1)
                ->where('options.evaluations.0.title', 'Visible county evaluation'));

        $this->actingAs($user)->get(route('workspace.export', ['workspace' => 'monitoring-evaluation', 'format' => 'json']))
            ->assertOk()
            ->assertDontSee('Hidden County');
        $dataset = app(ProgrammeWorkspaceData::class)->monitoringEvaluation($user, new WorkspaceFilters(null, null, '', 15));
        $this->assertSame('Legacy unpinned', $dataset['rows'][0]['cells'][1]);
        $this->actingAs($user)->get(route('monitoring-evaluation.index', ['county_id' => $otherCounty->id]))
            ->assertForbidden();
        $this->actingAs($user)->get(route('workspace.export', ['workspace' => 'monitoring-evaluation', 'format' => 'json', 'county_id' => $otherCounty->id]))
            ->assertForbidden();
    }

    public function test_results_pair_verified_actuals_with_scoped_verified_targets_and_build_trends(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $user = User::factory()->countyAdmin($county)->create();
        $programme = Programme::factory()->create();
        $indicator = IndicatorDefinition::factory()->create(['direction' => 'increase', 'unit_of_measure' => 'percent']);
        $common = ['indicator_definition_id' => $indicator->id, 'county_id' => $county->id, 'programme_id' => $programme->id, 'dimension_key' => 'total', 'verification_status' => 'verified', 'quality_status' => 'accepted'];
        IndicatorObservation::factory()->create([...$common, 'measure_type' => 'target', 'numeric_value' => 80, 'period_start' => '2026-01-01', 'period_end' => '2026-06-30']);
        IndicatorObservation::factory()->create([...$common, 'measure_type' => 'actual', 'numeric_value' => 70, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31']);
        IndicatorObservation::factory()->create([...$common, 'measure_type' => 'actual', 'numeric_value' => 100, 'period_start' => '2026-04-01', 'period_end' => '2026-06-30']);
        IndicatorObservation::factory()->create([...$common, 'measure_type' => 'target', 'numeric_value' => 120, 'verification_status' => 'submitted']);
        IndicatorObservation::factory()->create([...$common, 'county_id' => $otherCounty->id, 'measure_type' => 'actual', 'numeric_value' => 999]);
        $decreaseIndicator = IndicatorDefinition::factory()->create(['direction' => 'decrease', 'unit_of_measure' => 'days']);
        IndicatorObservation::factory()->create([...$common, 'indicator_definition_id' => $decreaseIndicator->id, 'measure_type' => 'target', 'numeric_value' => 50]);
        IndicatorObservation::factory()->create([...$common, 'indicator_definition_id' => $decreaseIndicator->id, 'measure_type' => 'actual', 'numeric_value' => 40]);
        $missingTargetIndicator = IndicatorDefinition::factory()->create(['direction' => 'increase']);
        IndicatorObservation::factory()->create([...$common, 'indicator_definition_id' => $missingTargetIndicator->id, 'measure_type' => 'actual', 'numeric_value' => 20]);

        $results = app(MonitoringEvaluationResults::class)->forUser($user, new WorkspaceFilters(null, null, '', 15));

        $this->assertSame(85.0, $results['indicators'][0]['average']);
        $this->assertSame(2, $results['performance']['summary']['withTarget']);
        $this->assertSame(2, $results['performance']['summary']['met']);
        $this->assertSame(0, $results['performance']['summary']['offTrack']);
        $this->assertSame(125.0, $results['performance']['summary']['averageAttainment']);
        $increaseRow = $this->performanceRow($results, $indicator->id);
        $decreaseRow = $this->performanceRow($results, $decreaseIndicator->id);
        $missingTargetRow = $this->performanceRow($results, $missingTargetIndicator->id);
        $this->assertSame(100.0, $increaseRow['actual']);
        $this->assertSame(80.0, $increaseRow['target']);
        $this->assertSame(20.0, $increaseRow['variance']);
        $this->assertSame('met', $increaseRow['status']);
        $this->assertSame(125.0, $decreaseRow['attainment']);
        $this->assertSame('met', $decreaseRow['status']);
        $this->assertSame('target_missing', $missingTargetRow['status']);
        $this->assertCount(2, $results['performance']['trends'][0]['points']);
        $this->assertSame($county->id, $results['performance']['rows'][0]['county']['id']);

        $export = $this->actingAs($user)->get(route('workspace.export', ['monitoring-performance', 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $exported = $export->streamedContent();
        $this->assertStringContainsString($indicator->code, $exported);
        $this->assertStringContainsString(',100,80,20,25,125,met', $exported);
        $this->assertStringNotContainsString('999', $exported);
        foreach (['xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($user)->get(route('workspace.export', ['monitoring-performance', $format]))->assertOk()->assertDownload();
        }
        $this->actingAs($user)->get(route('workspace.export', ['monitoring-performance', 'csv', 'county_id' => $otherCounty->id]))->assertForbidden();
    }

    public function test_programme_evaluation_requires_clean_repository_records_and_independent_approval(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->assessor()->create();
        $reviewer->assignedCounties()->attach($county);
        $programme = Programme::factory()->create();
        $release = $this->publishedReferenceRelease([$county], [$programme], $administrator);
        $this->seed(ProgrammeEvaluationWorkflowSeeder::class);

        $this->actingAs($administrator)->post(route('monitoring-evaluation.evaluations.store'), [
            'county_id' => $county->id, 'programme_id' => $programme->id, 'code' => 'EVAL-2026-01', 'title' => 'County service-delivery outcome evaluation', 'evaluation_type' => 'impact',
            'period_start' => '2025-01-01', 'period_end' => '2026-06-30',
            'terms_of_reference' => 'Assess attributable service-delivery outcomes using the approved mixed-method evaluation framework.',
            'methodology' => 'Quasi-experimental analysis supported by verified administrative records and beneficiary research.',
        ])->assertRedirect();

        $evaluation = ProgrammeEvaluation::query()->sole();
        $this->assertTrue(Str::isUuid($evaluation->id));
        $this->assertSame('planned', $evaluation->status);
        $this->assertNotNull($evaluation->workflow_instance_id);
        $this->assertSame($release->id, $evaluation->reference_data_release_id);
        $event = AuditEvent::query()->where('subject_id', $evaluation->id)->where('action', 'programme.evaluation.created')->sole();
        $this->assertSame($release->id, $event->metadata['reference_data_release_id']);
        $this->assertSame($release->checksum, $event->metadata['reference_data_release_checksum']);
        $this->actingAs($administrator)->patch(route('monitoring-evaluation.evaluations.transition', [$evaluation]), [
            'transition' => 'start', 'comment' => 'Attempt to start without an approved repository terms-of-reference record.',
        ])->assertSessionHasErrors('transition');

        $this->actingAs($administrator)->post(route('monitoring-evaluation.evaluations.documents.store', [$evaluation]), [
            'record_purpose' => 'terms_of_reference', 'title' => 'Signed evaluation terms of reference', 'category' => 'Terms of reference', 'source_type' => 'scanned',
            'document' => UploadedFile::fake()->create('evaluation-tor.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($administrator)->patch(route('monitoring-evaluation.evaluations.transition', [$evaluation]), [
            'transition' => 'start', 'comment' => 'Signed terms of reference validated and evaluation fieldwork authorized.',
        ])->assertRedirect();
        $this->assertSame('in_progress', $evaluation->refresh()->status);
        $this->actingAs($administrator)->patch(route('monitoring-evaluation.evaluations.transition', [$evaluation]), [
            'transition' => 'submit_review', 'comment' => 'Attempt review submission without a retained evaluation report.',
        ])->assertSessionHasErrors('transition');

        $this->actingAs($administrator)->post(route('monitoring-evaluation.evaluations.documents.store', [$evaluation]), [
            'record_purpose' => 'evaluation_report', 'title' => 'Final impact evaluation report', 'category' => 'Evaluation report', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('impact-evaluation.pdf', 30, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($administrator)->patch(route('monitoring-evaluation.evaluations.transition', [$evaluation]), [
            'transition' => 'submit_review', 'comment' => 'Final report submitted for independent methodological and evidence review.',
        ])->assertRedirect();
        $this->actingAs($administrator)->patch(route('monitoring-evaluation.evaluations.transition', [$evaluation]), [
            'transition' => 'approve', 'comment' => 'The submitting officer must not approve the same evaluation report.',
        ])->assertForbidden();
        $this->actingAs($reviewer)->patch(route('monitoring-evaluation.evaluations.transition', [$evaluation]), [
            'transition' => 'approve', 'comment' => 'Methodology, analysis, findings and repository evidence independently verified.',
        ])->assertRedirect();
        $this->assertSame('approved', $evaluation->refresh()->status);
        $this->assertSame($reviewer->id, $evaluation->approved_by);

        $links = DocumentLink::query()->with('document')->where('subject_id', $evaluation->id)->orderBy('purpose')->get();
        $this->assertSame(['programme-evaluation-report', 'programme-evaluation-tor'], $links->pluck('purpose')->all());
        $links->each(function (DocumentLink $link): void {
            $this->assertSame('clean', $link->document->scan_status);
            Storage::disk('local')->assertExists($link->document->path);
        });
        $this->actingAs($reviewer)->get(route('monitoring-evaluation.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('options.evaluations.0.documents', 2)
            ->where('options.evaluations.0.referenceRelease', "v{$release->version} · {$release->effective_from?->toDateString()}")
            ->where('options.evaluations.0.referenceChecksum', $release->checksum));
        foreach (['json', 'csv'] as $format) {
            $content = $this->actingAs($reviewer)->get(route('workspace.export', ['programme-evaluations', $format]))
                ->assertOk()
                ->streamedContent();
            $this->assertStringContainsString('Reference release', $content);
            $this->assertStringContainsString("v{$release->version}", $content);
            $this->assertStringContainsString($release->checksum, $content);
        }
        $this->actingAs($reviewer)->get(route('workspace.export', ['programme-evaluations', 'xlsx']))->assertOk()->assertDownload();
        $this->actingAs($reviewer)->get(route('workspace.export', ['programme-evaluations', 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($reviewer)->get(route('evidence.preview', [$links->first()->document]))->assertOk();
        $outside = User::factory()->countyAdmin(County::factory()->create())->create();
        $this->actingAs($outside)->get(route('evidence.preview', [$links->first()->document]))->assertForbidden();
        $this->actingAs($reviewer)->get(route('evidence.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('workspace.pagination.total', 2));
        $this->actingAs($administrator)->post(route('monitoring-evaluation.evaluations.documents.store', [$evaluation]), [
            'record_purpose' => 'supporting', 'title' => 'Late supporting record', 'category' => 'Supporting', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
    }

    public function test_programme_evaluation_creation_fails_closed_without_a_complete_effective_reference_release(): void
    {
        $county = County::factory()->create();
        $programme = Programme::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $this->seed(ProgrammeEvaluationWorkflowSeeder::class);
        $payload = $this->evaluationPayload($county, $programme);

        $this->actingAs($administrator)->post(route('monitoring-evaluation.evaluations.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('programme_evaluations', 0);

        $this->publishedReferenceRelease([$county], [], $administrator);
        $this->actingAs($administrator)->post(route('monitoring-evaluation.evaluations.store'), $payload)->assertSessionHasErrors('programme_id');
        $this->assertDatabaseCount('programme_evaluations', 0);

        $release = $this->publishedReferenceRelease([$county], [$programme], $administrator);
        $this->actingAs($administrator)->post(route('monitoring-evaluation.evaluations.store'), $payload)->assertRedirect();
        $this->assertSame($release->id, ProgrammeEvaluation::query()->sole()->reference_data_release_id);
    }

    public function test_programme_evaluation_action_rejects_an_outside_county_even_when_the_release_contains_it(): void
    {
        $homeCounty = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $programme = Programme::factory()->create();
        $countyAdministrator = User::factory()->countyAdmin($homeCounty)->create();
        $approver = User::factory()->devolutionAdmin()->create();
        $this->publishedReferenceRelease([$homeCounty, $outsideCounty], [$programme], $approver);

        $this->expectException(HttpException::class);
        app(CreateProgrammeEvaluation::class)->handle($countyAdministrator, $this->evaluationPayload($outsideCounty, $programme));
    }

    /** @return array<string, mixed> */
    private function observationPayload(IndicatorDefinition $indicator, County $county, Programme $programme): array
    {
        return [
            'indicator_definition_id' => $indicator->id,
            'county_id' => $county->id,
            'programme_id' => $programme->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'measure_type' => 'actual',
            'dimension_key' => 'total',
            'numeric_value' => 74.5,
            'source_reference' => 'County quarterly report Q1/2026',
            'provenance' => ['source_system' => 'county-mis', 'captured_at' => '2026-04-05T09:00:00+03:00'],
        ];
    }

    /** @return array<string, mixed> */
    private function evaluationPayload(County $county, Programme $programme): array
    {
        return [
            'county_id' => $county->id,
            'programme_id' => $programme->id,
            'code' => 'EVAL-2026-REGRESSION',
            'title' => 'County programme outcome evaluation',
            'evaluation_type' => 'impact',
            'period_start' => '2025-01-01',
            'period_end' => '2026-06-30',
            'terms_of_reference' => 'Assess outcomes against the approved programme theory of change and verified source records.',
            'methodology' => 'Independent mixed-method outcome evaluation.',
        ];
    }

    /**
     * @param  list<County>  $counties
     * @param  list<Programme>  $programmes
     */
    private function publishedReferenceRelease(array $counties, array $programmes, User $approver, array $sectors = []): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
            'sectors' => collect($sectors)->map(fn (Sector $sector): array => ['id' => $sector->id])->all(),
            'programmes' => collect($programmes)->map(fn (Programme $programme): array => ['id' => $programme->id])->all(),
            'programme_county_coverages' => [],
        ];
        $version = ((int) ReferenceDataRelease::query()->max('version')) + 1;

        return ReferenceDataRelease::factory()->create([
            'version' => $version,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-EVALUATION-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function supersessionPayload(): array
    {
        return [
            'name' => 'County service delivery coverage',
            'description' => 'Revised definition aligned to the approved national results framework.',
            'results_level' => 'outcome',
            'unit_of_measure' => 'percent',
            'value_type' => 'percentage',
            'direction' => 'increase',
            'frequency' => 'quarterly',
            'data_source' => 'Verified county quarterly reports',
            'verification_method' => 'Reconcile against signed source registers and repository evidence.',
            'effective_from' => now()->toDateString(),
            'change_summary' => 'Updated denominator and verification method following annual framework review.',
        ];
    }

    /** @return array<string, mixed> */
    private function verificationPayload(): array
    {
        return ['verification_status' => 'verified', 'quality_status' => 'accepted', 'quality_issues' => [], 'rationale' => 'Source record and calculation reconciled.'];
    }

    /** @param array<string, mixed> $results
     * @return array<string, mixed>
     */
    private function performanceRow(array $results, string $indicatorId): array
    {
        $performance = $results['performance'] ?? null;
        $rows = is_array($performance) ? ($performance['rows'] ?? null) : null;
        if (! is_array($rows)) {
            $this->fail('Target-performance rows were not returned.');
        }
        foreach ($rows as $row) {
            if (is_array($row) && data_get($row, 'indicator.id') === $indicatorId) {
                return $row;
            }
        }

        $this->fail("Target-performance row {$indicatorId} was not returned.");
    }
}
