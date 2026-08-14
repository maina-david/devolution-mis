<?php

namespace Tests\Feature;

use App\Actions\CreateDevolutionProject;
use App\Actions\CreateProjectResource;
use App\Actions\IngestVerifiedProjectResults;
use App\Actions\VerifyIndicatorObservation;
use App\Actions\VerifyProjectProgress;
use App\Enums\ProgrammePermission;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\DocumentLink;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorObservation;
use App\Models\Programme;
use App\Models\ProjectResource;
use App\Models\ProjectResourceAllocation;
use App\Models\ProjectScheduleBaseline;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowVersion;
use App\Services\ProjectDependencyGraph;
use App\Services\ProjectScheduleAnalyzer;
use App\Support\CanonicalJson;
use Database\Seeders\ProjectWorkflowSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProjectManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_failures_and_catalogues_follow_the_active_locale(): void
    {
        $english = require lang_path('en/projects.php');
        $kiswahili = require lang_path('sw/projects.php');
        $french = require lang_path('fr/projects.php');
        $this->assertSame(array_keys(Arr::dot($english)), array_keys(Arr::dot($kiswahili)));
        $this->assertSame(array_keys(Arr::dot($english)), array_keys(Arr::dot($french)));

        $county = County::factory()->create();
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id, 'status' => 'closed', 'lifecycle_stage' => 'closed']);
        $actor = User::factory()->devolutionAdmin()->create();
        app()->setLocale('sw');

        try {
            app(CreateProjectResource::class)->handle($project, $actor, []);
            $this->fail('A closed project must not accept a resource plan.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame(__('projects.errors.resource_planning_locked'), $exception->getMessage());
        }
    }

    public function test_project_creation_starts_published_lifecycle_and_links_counties(): void
    {
        $this->projectWorkflow();
        $lead = County::factory()->create();
        $participating = County::factory()->create();
        $admin = User::factory()->devolutionAdmin()->create();
        $sector = Sector::factory()->create();
        $release = $this->publishedReferenceRelease([$lead, $participating], $sector, $admin);

        $project = app(CreateDevolutionProject::class)->handle($admin, $this->projectPayload($lead, $participating, $sector));

        $this->assertTrue(Str::isUuid($project->id));
        $this->assertSame('initiation', $project->lifecycle_stage);
        $this->assertSame(collect([$lead->id, $participating->id])->sort()->values()->all(), $project->counties()->pluck('counties.id')->sort()->values()->all());
        $this->assertNotNull($project->workflow_instance_id);
        $this->assertSame($release->id, $project->reference_data_release_id);
        $this->assertDatabaseHas('workflow_instances', ['id' => $project->workflow_instance_id, 'subject_id' => $project->id, 'current_state' => 'initiation']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $project->id, 'action' => 'project.created']);
        $event = AuditEvent::query()->where('subject_id', $project->id)->where('action', 'project.created')->sole();
        $this->assertSame($release->id, $event->metadata['reference_data_release_id']);
        $this->assertSame($release->checksum, $event->metadata['reference_data_release_checksum']);

        $this->actingAs($admin)->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.data.0.referenceRelease.version', $release->version)
                ->where('projects.data.0.referenceRelease.checksum', $release->checksum));
        $this->actingAs($admin)->get(route('projects.show', [$project]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('referenceRelease.version', $release->version)
                ->where('referenceRelease.checksum', $release->checksum));
        $export = $this->actingAs($admin)->get(route('workspace.export', ['projects', 'json']))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('Reference release', $export);
        $this->assertStringContainsString("v{$release->version}", $export);
        $this->assertStringContainsString($release->checksum, $export);
    }

    public function test_project_creation_fails_closed_without_a_current_complete_reference_release(): void
    {
        $this->projectWorkflow();
        $lead = County::factory()->create();
        $participating = County::factory()->create();
        $admin = User::factory()->devolutionAdmin()->create();
        $sector = Sector::factory()->create();
        $payload = $this->projectPayload($lead, $participating, $sector);

        try {
            app(CreateDevolutionProject::class)->handle($admin, $payload);
            $this->fail('Project creation must require an effective reference-data release.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->publishedReferenceRelease([$lead, $participating], $sector, $admin, now()->addDay());

        try {
            app(CreateDevolutionProject::class)->handle($admin, $payload);
            $this->fail('A future reference-data release must not govern current initiation.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $incomplete = $this->publishedReferenceRelease([$lead], $sector, $admin);

        try {
            app(CreateDevolutionProject::class)->handle($admin, $payload);
            $this->fail('Every selected reference must exist in the effective release snapshot.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('county_ids', $exception->errors());
        }

        $complete = $this->publishedReferenceRelease([$lead, $participating], $sector, $admin);
        $project = app(CreateDevolutionProject::class)->handle($admin, $payload);

        $this->assertNotSame($incomplete->id, $project->reference_data_release_id);
        $this->assertSame($complete->id, $project->reference_data_release_id);
        $this->assertDatabaseCount('devolution_projects', 1);
    }

    public function test_county_admin_cannot_create_a_project_covering_another_county(): void
    {
        $this->projectWorkflow();
        $home = County::factory()->create();
        $other = County::factory()->create();
        $admin = User::factory()->countyAdmin($home)->create();

        $this->expectException(HttpException::class);
        app(CreateDevolutionProject::class)->handle($admin, $this->projectPayload($home, $other, Sector::factory()->create()));
    }

    public function test_project_page_is_county_scoped_and_exposes_delivery_registers(): void
    {
        $home = County::factory()->create();
        $other = County::factory()->create();
        $admin = User::factory()->countyAdmin($home)->create();
        $visible = DevolutionProject::factory()->create(['lead_county_id' => $home->id]);
        $visible->counties()->attach($home, ['is_lead' => true]);
        $hidden = DevolutionProject::factory()->create(['lead_county_id' => $other->id]);
        $hidden->counties()->attach($other, ['is_lead' => true]);

        $this->actingAs($admin)->get(route('projects.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')->where('projects.total', 1)->where('projects.data.0.id', $visible->id));
        $this->actingAs($admin)->get(route('projects.show', [$visible]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('projects/show')->has('project.milestones')->has('project.budget_lines')->has('project.risks')->has('project.procurements')->has('project.progress_updates')->where('referenceRelease', null));
        $this->actingAs($admin)->get(route('projects.show', [$hidden]))->assertForbidden();
        $export = $this->actingAs($admin)->get(route('workspace.export', ['projects', 'json']))->assertOk();
        $exportedContent = $export->streamedContent();
        $this->assertStringContainsString($visible->code, $exportedContent);
        $this->assertStringNotContainsString($hidden->code, $exportedContent);
    }

    public function test_progress_update_retains_provenance_and_must_be_independently_verified_later(): void
    {
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $project->counties()->attach($county, ['is_lead' => true]);

        $this->actingAs($official)->post(route('projects.progress-updates.store', [$project]), [
            'reporting_date' => today()->toDateString(), 'physical_progress' => 35, 'financial_progress' => 28,
            'narrative' => 'Foundation works completed.', 'provenance' => ['source_system' => 'county-investment-dashboard', 'captured_at' => now()->toIso8601String()],
        ])->assertRedirect();

        $update = $project->progressUpdates()->sole();
        $this->assertSame('submitted', $update->verification_status);
        $this->assertSame('county-investment-dashboard', $update->provenance['source_system']);
        $this->assertSame('0.00', $project->refresh()->physical_progress);

        try {
            app(VerifyProjectProgress::class)->handle($update, $official, 'verified', 'Self review');
            $this->fail('A submitter must not verify their own update.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($county);
        $this->actingAs($assessor)->patch(route('projects.progress-updates.verify', [$project, $update]), [
            'status' => 'verified', 'rationale' => 'Site certificate and expenditure report reconciled.',
        ])->assertRedirect();
        $this->assertSame('verified', $update->refresh()->verification_status);
        $this->assertSame('35.00', $project->refresh()->physical_progress);
    }

    public function test_verified_project_results_enter_separate_me_quality_queue_idempotently(): void
    {
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $projectVerifier = User::factory()->assessor()->create();
        $meVerifier = User::factory()->assessor()->create();
        $projectVerifier->assignedCounties()->attach($county);
        $meVerifier->assignedCounties()->attach($county);
        $programme = Programme::factory()->create();
        $indicator = IndicatorDefinition::factory()->create(['code' => 'M07-PROJECT-01', 'value_type' => 'percentage']);
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id, 'programme_id' => $programme->id]);
        $project->counties()->attach($county, ['is_lead' => true]);
        $project->indicators()->attach($indicator, ['is_primary' => true]);

        $this->actingAs($official)->post(route('projects.progress-updates.store', [$project]), [
            'reporting_date' => today()->toDateString(), 'physical_progress' => 42, 'financial_progress' => 38,
            'narrative' => 'Verified outputs recorded for the reporting quarter.',
            'provenance' => ['source_system' => 'county-investment-dashboard', 'captured_at' => now()->toIso8601String()],
            'indicator_results' => [[
                'indicator_definition_id' => $indicator->id,
                'county_id' => $county->id,
                'period_start' => today()->startOfQuarter()->toDateString(),
                'period_end' => today()->toDateString(),
                'dimension_key' => 'sex:female',
                'disaggregation' => ['sex' => 'female'],
                'numeric_value' => 64.25,
            ]],
        ])->assertRedirect();

        $update = $project->progressUpdates()->with('indicatorResults')->sole();
        $sourceResult = $update->indicatorResults->sole();
        $this->assertTrue(Str::isUuid($sourceResult->id));
        $this->assertSame(['sex' => 'female'], $sourceResult->disaggregation);

        $this->actingAs($projectVerifier)->patch(route('projects.progress-updates.verify', [$project, $update]), [
            'status' => 'verified', 'rationale' => 'Project source register and delivery certificate reconciled.',
        ])->assertRedirect();

        $observation = IndicatorObservation::query()->sole();
        $this->assertSame($sourceResult->id, $observation->source_project_indicator_result_id);
        $this->assertSame('submitted', $observation->verification_status);
        $this->assertSame('unassessed', $observation->quality_status);
        $this->assertSame($official->id, $observation->submitted_by);
        $this->assertSame($project->id, $observation->provenance['project_id']);
        $this->assertSame($projectVerifier->id, $observation->provenance['project_verification_actor_id']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $observation->id, 'action' => 'indicator.observation.ingested_from_project']);

        app(IngestVerifiedProjectResults::class)->handle($update->refresh(), $projectVerifier);
        $this->assertDatabaseCount('indicator_observations', 1);
        $this->actingAs($meVerifier)->get(route('monitoring-evaluation.index', ['county_id' => $county->id,
            'status' => 'submitted',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('results.summary.total', 1)
            ->where('results.summary.submitted', 1)
            ->where('results.summary.projectSourced', 1)
            ->where('results.projectContributions.0.project.id', $project->id)
            ->where('results.projectContributions.0.county.logoUrl', $county->logo_path)
            ->where('results.projectContributions.0.dimension', 'female'));

        try {
            app(VerifyIndicatorObservation::class)->handle($projectVerifier, $observation, $this->indicatorVerificationPayload());
            $this->fail('The project verifier unexpectedly completed the M&E verification.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verification_status', $exception->errors());
        }

        $verified = app(VerifyIndicatorObservation::class)->handle($meVerifier, $observation, $this->indicatorVerificationPayload());
        $this->assertSame('verified', $verified->verification_status);
        $this->assertSame($meVerifier->id, $verified->verified_by);
        $this->actingAs($meVerifier)->get(route('monitoring-evaluation.index', ['county_id' => $county->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('results.summary.verified', 1)
            ->where('results.indicators.0.code', 'M07-PROJECT-01')
            ->where('results.indicators.0.average', 64.25)
            ->where('results.disaggregations.0.dimension', 'sex:female'));
        $export = $this->actingAs($meVerifier)->get(route('workspace.export', ['monitoring-evaluation',
            'json',
            'county_id' => $county->id,
            'status' => 'verified',
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString($project->code, $export);
        $this->assertStringContainsString('sex: female', $export);

        try {
            DB::transaction(fn () => DB::table('project_indicator_results')->where('id', $sourceResult->id)->update(['numeric_value' => 99]));
            $this->fail('A verified project indicator source result unexpectedly changed.');
        } catch (QueryException) {
            $this->assertSame('64.250000', $sourceResult->refresh()->numeric_value);
        }
    }

    public function test_project_result_rejects_unlinked_indicator_and_out_of_scope_county(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id, 'programme_id' => Programme::factory()]);
        $project->counties()->attach($county, ['is_lead' => true]);
        $unlinkedIndicator = IndicatorDefinition::factory()->create();
        $payload = [
            'reporting_date' => today()->toDateString(), 'physical_progress' => 10, 'financial_progress' => 8,
            'narrative' => 'Quarterly progress.', 'provenance' => ['source_system' => 'county-mis', 'captured_at' => now()->toIso8601String()],
            'indicator_results' => [[
                'indicator_definition_id' => $unlinkedIndicator->id, 'county_id' => $county->id,
                'period_start' => today()->startOfQuarter()->toDateString(), 'period_end' => today()->toDateString(),
                'dimension_key' => 'total', 'numeric_value' => 12,
            ]],
        ];

        $this->actingAs($official)->post(route('projects.progress-updates.store', [$project]), $payload)
            ->assertSessionHasErrors('indicator_results.0.indicator_definition_id');
        $project->indicators()->attach($unlinkedIndicator);
        $payload['indicator_results'][0]['county_id'] = $otherCounty->id;
        $this->actingAs($official)->post(route('projects.progress-updates.store', [$project]), $payload)
            ->assertSessionHasErrors('indicator_results.0.county_id');
        $this->assertDatabaseCount('project_progress_updates', 0);
        $this->assertDatabaseCount('project_indicator_results', 0);
    }

    public function test_project_closure_requires_clean_repository_report_and_independent_approval(): void
    {
        Storage::fake('local');
        $leadCounty = County::factory()->create();
        $participatingCounty = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $reportingOfficer = User::factory()->countyOfficial($leadCounty)->create();
        $verifier = User::factory()->assessor()->create();
        $verifier->assignedCounties()->attach($leadCounty);
        $this->seed(ProjectWorkflowSeeder::class);
        $sector = Sector::factory()->create();
        $this->publishedReferenceRelease([$leadCounty, $participatingCounty], $sector, $administrator);
        $project = app(CreateDevolutionProject::class)->handle($administrator, $this->projectPayload($leadCounty, $participatingCounty, $sector));

        $this->actingAs($administrator)->patch(route('projects.transition', [$project]), [
            'transition' => 'plan', 'comment' => 'The approved project plan is ready for detailed delivery controls.',
        ])->assertRedirect();
        $this->actingAs($administrator)->patch(route('projects.transition', [$project]), [
            'transition' => 'start_execution', 'comment' => 'Planning controls are complete and execution is authorized.',
        ])->assertRedirect();
        $this->actingAs($reportingOfficer)->post(route('projects.progress-updates.store', [$project]), [
            'reporting_date' => today()->toDateString(), 'physical_progress' => 100, 'financial_progress' => 98,
            'narrative' => 'All approved outputs are complete and pending independent closure verification.',
            'provenance' => ['source_system' => 'project-completion-certificate', 'captured_at' => now()->toIso8601String()],
        ])->assertRedirect();
        $progressUpdate = $project->progressUpdates()->sole();
        $this->actingAs($verifier)->patch(route('projects.progress-updates.verify', [$project, $progressUpdate]), [
            'status' => 'verified', 'rationale' => 'Physical completion was independently reconciled to certified outputs.',
        ])->assertRedirect();

        $this->actingAs($administrator)->patch(route('projects.transition', [$project]), [
            'transition' => 'submit_closure', 'comment' => 'A narrative alone must not satisfy the retained closure-report gate.',
        ])->assertSessionHasErrors('transition');
        $this->actingAs($administrator)->post(route('projects.documents.store', [$project]), [
            'record_purpose' => 'closure_report', 'title' => 'Signed final project closure report', 'category' => 'Project closure', 'source_type' => 'scanned',
            'document' => UploadedFile::fake()->create('signed-project-closure-report.pdf', 30, 'application/pdf'),
        ])->assertRedirect();
        $link = DocumentLink::query()->with('document')->where('subject_id', $project->id)->sole();
        $this->assertSame('project-closure-report', $link->purpose);
        $this->assertSame('clean', $link->document->scan_status);
        Storage::disk('local')->assertExists($link->document->path);

        $this->actingAs($administrator)->patch(route('projects.transition', [$project]), [
            'transition' => 'submit_closure', 'comment' => 'Verified completion and the signed closure report are submitted for approval.',
        ])->assertRedirect();
        $this->assertSame('closure_review', $project->refresh()->lifecycle_stage);
        $this->actingAs($administrator)->patch(route('projects.transition', [$project]), [
            'transition' => 'approve_closure', 'comment' => 'The closure submitter must not approve the same project closure.',
        ])->assertForbidden();
        $this->actingAs($verifier)->patch(route('projects.transition', [$project]), [
            'transition' => 'approve_closure', 'comment' => 'Completion, expenditure, retained report and outcome records are independently verified.',
        ])->assertRedirect();
        $this->assertSame('closed', $project->refresh()->lifecycle_stage);
        $this->assertSame('closed', $project->status);
        $this->actingAs($administrator)->post(route('projects.documents.store', [$project]), [
            'record_purpose' => 'closure_report', 'title' => 'Late replacement report', 'category' => 'Project closure', 'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('late-report.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
    }

    public function test_project_control_register_amendments_are_attributed_scoped_and_terminally_locked(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id, 'status' => 'active', 'lifecycle_stage' => 'execution']);
        $project->counties()->attach($county, ['is_lead' => true]);
        $routeArguments = [$project];

        $this->actingAs($administrator)->post(route('projects.milestones.store', $routeArguments), [
            'code' => 'MS-01', 'title' => 'Initial works', 'planned_start_date' => '2026-08-01', 'planned_end_date' => '2026-12-31', 'weight' => 40,
        ])->assertRedirect();
        $this->actingAs($administrator)->post(route('projects.budget-lines.store', $routeArguments), [
            'code' => 'BL-01', 'category' => 'Works', 'description' => 'Initial civil works', 'approved_amount' => 1000000, 'committed_amount' => 200000, 'actual_amount' => 100000, 'currency' => 'KES', 'financial_year' => '2026/27',
        ])->assertRedirect();
        $this->actingAs($administrator)->post(route('projects.risks.store', $routeArguments), [
            'code' => 'RSK-01', 'category' => 'Delivery', 'description' => 'Seasonal access disruption', 'probability' => 4, 'impact' => 4, 'mitigation' => 'Sequence works before peak rainfall.',
        ])->assertRedirect();
        $this->actingAs($administrator)->post(route('projects.procurements.store', $routeArguments), [
            'reference' => 'TENDER-2026-001', 'title' => 'Civil works package', 'method' => 'open_tender', 'estimated_value' => 900000, 'currency' => 'KES', 'planned_notice_date' => '2026-08-15',
        ])->assertRedirect();

        $milestone = $project->milestones()->sole();
        $budgetLine = $project->budgetLines()->sole();
        $risk = $project->risks()->sole();
        $procurement = $project->procurements()->sole();
        $reason = 'Updated after the independently reviewed monthly project-control meeting.';
        $this->actingAs($administrator)->patch(route('projects.milestones.update', [...$routeArguments, $milestone]), [
            'title' => 'Initial works completed', 'description' => 'Certified initial works package.', 'planned_start_date' => '2026-08-01', 'planned_end_date' => '2026-12-31', 'actual_start_date' => '2026-08-05', 'actual_end_date' => '2026-12-20', 'weight' => 40, 'progress' => 100, 'status' => 'completed', 'amendment_reason' => $reason,
        ])->assertRedirect();
        $this->actingAs($administrator)->patch(route('projects.budget-lines.update', [...$routeArguments, $budgetLine]), [
            'category' => 'Works', 'description' => 'Certified civil works', 'approved_amount' => 1000000, 'committed_amount' => 850000, 'actual_amount' => 800000, 'funding_source' => 'KDSP II', 'amendment_reason' => $reason,
        ])->assertRedirect();
        $this->actingAs($administrator)->patch(route('projects.risks.update', [...$routeArguments, $risk]), [
            'category' => 'Delivery', 'description' => 'Seasonal access disruption', 'probability' => 4, 'impact' => 4, 'residual_probability' => 1, 'residual_impact' => 2, 'mitigation' => 'Works resequenced before peak rainfall.', 'status' => 'mitigated', 'review_due_date' => '2027-01-15', 'amendment_reason' => $reason,
        ])->assertRedirect();
        $this->actingAs($administrator)->patch(route('projects.procurements.update', [...$routeArguments, $procurement]), [
            'title' => 'Civil works package', 'method' => 'open_tender', 'status' => 'awarded', 'estimated_value' => 900000, 'contract_value' => 875000, 'planned_notice_date' => '2026-08-15', 'award_date' => '2026-10-01', 'supplier_name' => 'County Works Consortium', 'contract_reference' => 'CONTRACT-2026-001', 'amendment_reason' => $reason,
        ])->assertRedirect();

        $this->assertSame('completed', $milestone->refresh()->status);
        $this->assertSame('800000.00', $project->refresh()->actual_expenditure);
        $this->assertSame('mitigated', $risk->refresh()->status);
        $this->assertSame('awarded', $procurement->refresh()->status);
        foreach (['project.milestone_amended', 'project.budget_line_amended', 'project.risk_amended', 'project.procurement_amended'] as $action) {
            $event = AuditEvent::query()->where('action', $action)->firstOrFail();
            $this->assertSame($reason, $event->metadata['reason']);
            $this->assertNotEmpty($event->metadata['before']);
            $this->assertNotEmpty($event->metadata['after']);
        }

        $otherProject = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $otherProject->counties()->attach($county, ['is_lead' => true]);
        $this->actingAs($administrator)->patch(route('projects.milestones.update', [$otherProject, $milestone]), [
            'title' => 'Cross-project amendment', 'planned_start_date' => '2026-08-01', 'planned_end_date' => '2026-12-31', 'weight' => 40, 'progress' => 100, 'status' => 'completed', 'amendment_reason' => $reason,
        ])->assertNotFound();
        $project->update(['status' => 'closed']);
        $this->actingAs($administrator)->patch(route('projects.risks.update', [...$routeArguments, $risk]), [
            'category' => 'Delivery', 'description' => 'Late amendment', 'probability' => 1, 'impact' => 1, 'mitigation' => 'No late changes.', 'status' => 'closed', 'amendment_reason' => $reason,
        ])->assertStatus(409);
    }

    public function test_milestone_dependencies_are_project_scoped_and_cannot_form_cycles(): void
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id, 'status' => 'active', 'lifecycle_stage' => 'execution']);
        $project->counties()->attach($county, ['is_lead' => true]);
        $routeArguments = [$project];

        foreach ([
            ['code' => 'MS-01', 'title' => 'Design approval', 'dependencies' => []],
            ['code' => 'MS-02', 'title' => 'Works delivery', 'dependencies' => fn (): array => [$project->milestones()->where('code', 'MS-01')->sole()->id]],
            ['code' => 'MS-03', 'title' => 'Completion certification', 'dependencies' => fn (): array => [$project->milestones()->where('code', 'MS-02')->sole()->id]],
        ] as $index => $milestone) {
            $dependencies = is_callable($milestone['dependencies']) ? $milestone['dependencies']() : $milestone['dependencies'];
            $this->actingAs($administrator)->post(route('projects.milestones.store', $routeArguments), [
                'code' => $milestone['code'],
                'title' => $milestone['title'],
                'planned_start_date' => sprintf('2026-%02d-01', $index + 8),
                'planned_end_date' => sprintf('2026-%02d-28', $index + 8),
                'weight' => 30,
                'dependencies' => $dependencies,
            ])->assertRedirect();
        }

        $first = $project->milestones()->where('code', 'MS-01')->sole();
        $second = $project->milestones()->where('code', 'MS-02')->sole();
        $third = $project->milestones()->where('code', 'MS-03')->sole();
        $this->assertSame([$first->id], $second->dependencies);
        $this->assertSame([$second->id], $third->dependencies);

        $amendment = [
            'title' => $first->title,
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-28',
            'weight' => 30,
            'progress' => 0,
            'status' => 'not_started',
            'dependencies' => [$third->id],
            'amendment_reason' => 'Testing circular dependency protection.',
        ];
        $this->actingAs($administrator)->patch(route('projects.milestones.update', [...$routeArguments, $first]), $amendment)
            ->assertSessionHasErrors('dependencies');
        $this->assertSame([], $first->refresh()->dependencies);

        $otherProject = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $outsideMilestone = $otherProject->milestones()->create([
            'code' => 'OUT-01', 'title' => 'Outside milestone', 'planned_start_date' => '2026-08-01', 'planned_end_date' => '2026-08-28', 'weight' => 10,
        ]);
        $amendment['dependencies'] = [$outsideMilestone->id];
        $this->actingAs($administrator)->patch(route('projects.milestones.update', [...$routeArguments, $first]), $amendment)
            ->assertSessionHasErrors('dependencies');
        $this->assertSame([], $first->refresh()->dependencies);
    }

    public function test_authorized_project_participants_upload_scanned_and_digital_records_with_private_repository_access(): void
    {
        Storage::fake('local');
        $leadCounty = County::factory()->create();
        $participatingCounty = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $uploader = User::factory()->countyOfficial($participatingCounty)->create();
        $outsider = User::factory()->countyOfficial($outsideCounty)->create();
        $viewerWithoutUploadPermission = User::factory()->assessor()->create();
        $viewerWithoutUploadPermission->assignedCounties()->attach($participatingCounty);
        $project = DevolutionProject::factory()->create(['lead_county_id' => $leadCounty->id, 'status' => 'active']);
        $project->counties()->attach([
            $leadCounty->id => ['is_lead' => true],
            $participatingCounty->id => ['is_lead' => false],
        ]);

        $this->actingAs($uploader)->post(route('projects.documents.store', [$project]), [
            'title' => 'Signed site inspection certificate',
            'category' => 'Implementation evidence',
            'source_type' => 'scanned',
            'document' => UploadedFile::fake()->image('site-certificate.jpg'),
        ])->assertRedirect();
        $this->actingAs($uploader)->post(route('projects.documents.store', [$project]), [
            'title' => 'Approved project work plan',
            'category' => 'Planning',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('work-plan.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $links = DocumentLink::query()->with('document.currentVersion')->orderBy('created_at')->get();
        $this->assertCount(2, $links);
        $this->assertTrue($links->every(fn (DocumentLink $link): bool => Str::isUuid($link->id) && $link->subject_id === $project->id && $link->document->assessment_id === null && $link->document->currentVersion !== null));
        $this->assertSame(['scanned', 'digital'], $links->pluck('document.source_type')->all());
        $links->each(fn (DocumentLink $link) => Storage::disk('local')->assertExists($link->document->path));
        $this->assertDatabaseCount('document_versions', 2);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $links->firstOrFail()->document->id, 'action' => 'document.linked_uploaded']);

        $scannedDocument = $links->firstOrFail()->document;
        $this->actingAs($uploader)->get(route('evidence.preview', [$scannedDocument]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($uploader)->get(route('projects.show', [$project]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('documents', 2)
            ->where('documents', fn ($documents): bool => collect($documents)->pluck('title')->sort()->values()->all() === ['Approved project work plan', 'Signed site inspection certificate'])
            ->where('capabilities.uploadDocuments', true));
        $this->actingAs($uploader)->get(route('evidence.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspace.pagination.total', 2));
        $this->actingAs($outsider)->get(route('evidence.preview', [$scannedDocument]))->assertForbidden();
        $this->actingAs($viewerWithoutUploadPermission)->get(route('evidence.preview', [$scannedDocument]))->assertOk();
        $this->actingAs($viewerWithoutUploadPermission)->post(route('projects.documents.store', [$project]), [
            'title' => 'Unauthorized upload',
            'category' => 'Planning',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('unauthorized.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $project->update(['status' => 'closed']);
        $this->actingAs($uploader)->post(route('projects.documents.store', [$project]), [
            'title' => 'Late closure document',
            'category' => 'Closure',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])->assertStatus(409);
        $this->assertDatabaseCount('document_links', 2);
    }

    public function test_schedule_baseline_requires_independent_approval_and_drives_critical_path_forecast_variance(): void
    {
        $this->travelTo('2026-01-10 12:00:00');
        $county = County::factory()->create();
        $requester = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->assessor()->create();
        $reviewer->assignedCounties()->attach($county);
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id, 'status' => 'active', 'lifecycle_stage' => 'planning']);
        $project->counties()->attach($county, ['is_lead' => true]);
        $design = $project->milestones()->create(['code' => 'MS-01', 'title' => 'Design approval', 'planned_start_date' => '2026-01-01', 'planned_end_date' => '2026-01-05', 'weight' => 40, 'dependencies' => []]);
        $works = $project->milestones()->create(['code' => 'MS-02', 'title' => 'Works delivery', 'planned_start_date' => '2026-01-06', 'planned_end_date' => '2026-01-10', 'weight' => 30, 'dependencies' => [$design->id]]);
        $mobilization = $project->milestones()->create(['code' => 'MS-03', 'title' => 'Independent mobilization', 'planned_start_date' => '2026-01-01', 'planned_end_date' => '2026-01-03', 'weight' => 30, 'dependencies' => []]);
        $reason = 'The planning review confirmed the complete dependency-linked delivery schedule.';

        $this->actingAs($requester)->post(route('projects.schedule-baselines.store', [$project]), ['baseline_reason' => $reason])->assertRedirect();
        $baseline = ProjectScheduleBaseline::query()->sole();
        $this->assertTrue(Str::isUuid($baseline->id));
        $this->assertSame('pending', $baseline->status);
        $this->assertSame(1, $baseline->version);
        $this->assertSame(['MS-01', 'MS-02'], $baseline->critical_path_analysis['critical_path_codes']);
        $this->assertSame(7, collect($baseline->critical_path_analysis['milestones'])->firstWhere('id', $mobilization->id)['total_float_days']);
        $this->actingAs($requester)->patch(route('projects.schedule-baselines.decide', [$project, $baseline]), [
            'decision' => 'approve', 'decision_rationale' => 'The requester must not independently approve the same baseline.',
        ])->assertSessionHasErrors('decision');
        $this->actingAs($reviewer)->patch(route('projects.schedule-baselines.decide', [$project, $baseline]), [
            'decision' => 'approve', 'decision_rationale' => 'Dependencies, milestone dates, weights and delivery authority were independently verified.',
        ])->assertRedirect();
        $this->assertSame('approved', $baseline->refresh()->status);
        $this->assertSame($reviewer->id, $baseline->decided_by);
        $this->assertNotNull($baseline->decision_checksum);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $baseline->id, 'action' => 'project.schedule_baseline_approved']);

        $works->update(['planned_end_date' => '2026-01-12', 'actual_start_date' => '2026-01-06', 'progress' => 50, 'status' => 'in_progress']);
        $this->actingAs($reviewer)->get(route('projects.show', [$project]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('scheduleBaselines.0.version', 1)
            ->where('scheduleBaselines.0.status', 'approved')
            ->where('scheduleAnalysis.baseline_finish', '2026-01-10')
            ->where('scheduleAnalysis.current_finish', '2026-01-12')
            ->where('scheduleAnalysis.forecast_finish', '2026-01-15')
            ->where('scheduleAnalysis.planned_variance_days', 2)
            ->where('scheduleAnalysis.forecast_variance_days', 5)
            ->where('scheduleAnalysis.critical_path_codes', ['MS-01', 'MS-02']));
        $export = $this->actingAs($reviewer)->get(route('workspace.export', ['projects', 'json']))->assertOk()->streamedContent();
        $this->assertStringContainsString('MS-01', $export);
        $this->assertStringContainsString('2026-01-15', $export);
        $this->assertStringContainsString('v1', $export);

        try {
            DB::transaction(fn () => ProjectScheduleBaseline::query()->whereKey($baseline)->update(['baseline_reason' => 'Tampered approved baseline']));
            $this->fail('An approved schedule baseline unexpectedly changed.');
        } catch (QueryException) {
            $this->assertSame($reason, $baseline->refresh()->baseline_reason);
        }
    }

    public function test_schedule_baseline_rejects_incomplete_stale_and_cross_project_decisions(): void
    {
        $county = County::factory()->create();
        $requester = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->assessor()->create();
        $reviewer->assignedCounties()->attach($county);
        $project = DevolutionProject::factory()->create(['lead_county_id' => $county->id, 'status' => 'active', 'lifecycle_stage' => 'planning']);
        $project->counties()->attach($county, ['is_lead' => true]);
        $milestone = $project->milestones()->create(['code' => 'MS-01', 'title' => 'Incomplete schedule', 'planned_start_date' => '2026-02-01', 'planned_end_date' => '2026-02-10', 'weight' => 80, 'dependencies' => []]);
        $payload = ['baseline_reason' => 'The complete schedule is being submitted for independent baseline review.'];

        $this->actingAs($requester)->post(route('projects.schedule-baselines.store', [$project]), $payload)->assertStatus(422);
        $milestone->update(['weight' => 100]);
        $this->actingAs($requester)->post(route('projects.schedule-baselines.store', [$project]), $payload)->assertRedirect();
        $baseline = ProjectScheduleBaseline::query()->sole();
        $milestone->update(['planned_end_date' => '2026-02-12']);
        $decision = ['decision' => 'approve', 'decision_rationale' => 'The schedule was independently reviewed against current milestone controls.'];
        $this->actingAs($reviewer)->patch(route('projects.schedule-baselines.decide', [$project, $baseline]), $decision)->assertSessionHasErrors('decision');
        $this->assertSame('pending', $baseline->refresh()->status);

        $otherProject = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $otherProject->counties()->attach($county, ['is_lead' => true]);
        $this->actingAs($reviewer)->patch(route('projects.schedule-baselines.decide', [$otherProject, $baseline]), $decision)->assertNotFound();
    }

    public function test_schedule_dependency_failures_are_localized_in_french(): void
    {
        app()->setLocale('fr');
        $analyzer = app(ProjectScheduleAnalyzer::class);
        $dependencyGraph = app(ProjectDependencyGraph::class);

        try {
            $analyzer->analyze(collect());
            $this->fail('An empty schedule unexpectedly passed baseline analysis.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Au moins un jalon est requis avant de pouvoir capturer un référentiel de calendrier.',
                $exception->errors()['baseline_reason'][0],
            );
        }

        $project = DevolutionProject::factory()->create();
        $first = $project->milestones()->create([
            'code' => 'JAL-01',
            'title' => 'Premier jalon',
            'planned_start_date' => '2026-01-01',
            'planned_end_date' => '2026-01-05',
            'weight' => 50,
            'dependencies' => [],
        ]);
        $second = $project->milestones()->create([
            'code' => 'JAL-02',
            'title' => 'Deuxième jalon',
            'planned_start_date' => '2026-01-06',
            'planned_end_date' => '2026-01-10',
            'weight' => 50,
            'dependencies' => [$first->id],
        ]);

        try {
            $dependencyGraph->validate($project, $first, [$first->id]);
            $this->fail('A self-referencing milestone unexpectedly passed dependency validation.');
        } catch (ValidationException $exception) {
            $this->assertSame('Un jalon ne peut pas dépendre de lui-même.', $exception->errors()['dependencies'][0]);
        }

        try {
            $dependencyGraph->validate($project, $first, [$second->id]);
            $this->fail('A circular milestone dependency unexpectedly passed validation.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Les dépendances sélectionnées créeraient une chaîne circulaire de jalons.',
                $exception->errors()['dependencies'][0],
            );
        }

        $outsideProject = DevolutionProject::factory()->create();
        $outside = $outsideProject->milestones()->create([
            'code' => 'EXT-01',
            'title' => 'Jalon externe',
            'planned_start_date' => '2026-01-01',
            'planned_end_date' => '2026-01-02',
            'weight' => 100,
            'dependencies' => [],
        ]);

        try {
            $dependencyGraph->validate($project, $first, [$outside->id]);
            $this->fail('A cross-project milestone dependency unexpectedly passed validation.');
        } catch (ValidationException $exception) {
            $this->assertSame('Chaque dépendance doit appartenir à ce projet.', $exception->errors()['dependencies'][0]);
        }

        $first->update(['dependencies' => [$second->id]]);
        try {
            $analyzer->analyze($project->milestones()->get());
            $this->fail('A cyclic schedule unexpectedly passed critical-path analysis.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Le graphe de dépendances des jalons contient un cycle.',
                $exception->errors()['baseline_reason'][0],
            );
        }
    }

    public function test_resource_capacity_is_project_bound_costed_and_enforced_for_each_overlapping_day(): void
    {
        $this->travelTo('2026-01-01 09:00:00');
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $project = DevolutionProject::factory()->create([
            'lead_county_id' => $county->id,
            'status' => 'active',
            'lifecycle_stage' => 'planning',
            'currency' => 'USD',
        ]);
        $project->counties()->attach($county, ['is_lead' => true]);
        $milestone = $project->milestones()->create([
            'code' => 'MS-CAP', 'title' => 'Capacity-controlled delivery', 'planned_start_date' => '2026-01-05', 'planned_end_date' => '2026-01-20', 'weight' => 100, 'dependencies' => [],
        ]);

        $this->actingAs($administrator)->post(route('projects.resources.store', [$project]), [
            'code' => 'ENG-01', 'name' => 'Resident engineer', 'resource_type' => 'human', 'capacity_unit' => 'hours', 'capacity_per_day' => 8, 'cost_rate' => 125, 'available_from' => '2026-01-01', 'available_to' => '2026-01-31',
        ])->assertRedirect();
        $resource = ProjectResource::query()->sole();
        $this->assertTrue(Str::isUuid($resource->id));
        $this->assertSame('USD', $resource->currency);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $resource->id, 'action' => 'project.resource_created']);

        $allocation = ['project_resource_id' => $resource->id, 'project_milestone_id' => $milestone->id, 'starts_on' => '2026-01-05', 'ends_on' => '2026-01-09', 'planned_units_per_day' => 6, 'notes' => 'Primary supervision allocation.'];
        $this->actingAs($administrator)->post(route('projects.resource-allocations.store', [$project]), $allocation)->assertRedirect();
        $created = ProjectResourceAllocation::query()->sole();
        $this->assertSame('30.0000', $created->planned_units);
        $this->assertSame('3750.00', $created->planned_cost);
        $this->assertSame(64, strlen($created->allocation_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $created->id, 'action' => 'project.resource_allocated']);
        $this->actingAs($administrator)->get(route('projects.show', [$project]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('resourcePlan.0.id', $resource->id)
            ->where('resourcePlan.0.currency', 'USD')
            ->where('resourcePlan.0.plannedCost', 3750)
            ->where('resourcePlan.0.allocations.0.checksum', $created->allocation_checksum));

        $this->actingAs($administrator)->post(route('projects.resource-allocations.store', [$project]), [
            ...$allocation, 'starts_on' => '2026-01-08', 'ends_on' => '2026-01-12', 'planned_units_per_day' => 3,
        ])->assertSessionHasErrors('planned_units_per_day');
        $this->assertDatabaseCount('project_resource_allocations', 1);

        $otherProject = DevolutionProject::factory()->create(['lead_county_id' => $county->id]);
        $otherProject->counties()->attach($county, ['is_lead' => true]);
        $this->actingAs($administrator)->post(route('projects.resource-allocations.store', [$otherProject]), $allocation)->assertNotFound();
    }

    public function test_approved_weighted_baseline_drives_reproducible_earned_value_forecast(): void
    {
        $this->travelTo('2026-01-05 12:00:00');
        $county = County::factory()->create();
        $viewer = User::factory()->devolutionAdmin()->create();
        $project = DevolutionProject::factory()->create([
            'lead_county_id' => $county->id,
            'status' => 'active',
            'approved_budget' => 100000,
            'actual_expenditure' => 30000,
            'physical_progress' => 40,
        ]);
        $project->counties()->attach($county, ['is_lead' => true]);
        $first = $project->milestones()->create(['code' => 'MS-01', 'title' => 'First work package', 'planned_start_date' => '2026-01-01', 'planned_end_date' => '2026-01-05', 'weight' => 50, 'dependencies' => []]);
        $second = $project->milestones()->create(['code' => 'MS-02', 'title' => 'Second work package', 'planned_start_date' => '2026-01-06', 'planned_end_date' => '2026-01-10', 'weight' => 50, 'dependencies' => [$first->id]]);
        $project->scheduleBaselines()->create([
            'version' => 1,
            'status' => 'approved',
            'schedule_snapshot' => [
                ['id' => $first->id, 'code' => 'MS-01', 'title' => 'First work package', 'planned_start_date' => '2026-01-01', 'planned_end_date' => '2026-01-05', 'weight' => 50, 'dependencies' => []],
                ['id' => $second->id, 'code' => 'MS-02', 'title' => 'Second work package', 'planned_start_date' => '2026-01-06', 'planned_end_date' => '2026-01-10', 'weight' => 50, 'dependencies' => [$first->id]],
            ],
            'critical_path_analysis' => ['project_finish' => '2026-01-10'],
            'snapshot_checksum' => str_repeat('a', 64),
            'baseline_reason' => 'Approved complete schedule for earned-value controls.',
            'requested_by' => $viewer->id,
            'decided_by' => User::factory()->assessor()->create()->id,
            'decision_rationale' => 'Independently reviewed and approved schedule.',
            'decision_checksum' => str_repeat('b', 64),
            'decided_at' => now(),
        ]);

        $this->actingAs($viewer)->get(route('projects.show', [$project]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('earnedValueAnalysis.available', true)
            ->where('earnedValueAnalysis.method', 'CPI-only earned value forecast using approved weighted schedule baseline')
            ->where('earnedValueAnalysis.planned_completion_percent', 50)
            ->where('earnedValueAnalysis.planned_value', 50000)
            ->where('earnedValueAnalysis.earned_value', 40000)
            ->where('earnedValueAnalysis.actual_cost', 30000)
            ->where('earnedValueAnalysis.cost_performance_index', 1.3333)
            ->where('earnedValueAnalysis.schedule_performance_index', 0.8)
            ->where('earnedValueAnalysis.estimate_at_completion', 75000)
            ->where('earnedValueAnalysis.variance_at_completion', 25000));
        $export = $this->actingAs($viewer)->get(route('workspace.export', ['projects', 'json']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Resource plan cost', $export);
        $this->assertStringContainsString('75000', $export);
        $this->assertStringContainsString('1.3333', $export);
    }

    /** @return array<string, mixed> */
    private function projectPayload(County $lead, County $participating, Sector $sector): array
    {
        return ['code' => fake()->unique()->bothify('PIM-####'), 'title' => 'County water resilience programme', 'description' => 'Integrated investment delivery project.', 'sector_id' => $sector->id, 'lead_county_id' => $lead->id, 'county_ids' => [$lead->id, $participating->id], 'planned_start_date' => '2026-09-01', 'planned_end_date' => '2028-06-30', 'approved_budget' => 250000000, 'currency' => 'KES', 'indicator_ids' => [], 'climate_risk_screening' => ['rating' => 'moderate']];
    }

    /** @return array<string, mixed> */
    private function indicatorVerificationPayload(): array
    {
        return ['verification_status' => 'verified', 'quality_status' => 'accepted', 'quality_issues' => [], 'rationale' => 'Source record and calculation independently reconciled.'];
    }

    private function projectWorkflow(): WorkflowDefinition
    {
        $definition = WorkflowDefinition::factory()->create(['code' => 'PROJECT-LIFECYCLE', 'module' => 'project-management']);
        WorkflowVersion::factory()->published()->create(['workflow_definition_id' => $definition->id, 'configuration' => [
            'initial_state' => 'initiation', 'states' => ['initiation', 'planning', 'execution', 'closure_review', 'closed'], 'terminal_states' => ['closed'], 'start_permission' => ProgrammePermission::ManageProjects->value,
            'transitions' => [
                ['name' => 'plan', 'from' => 'initiation', 'to' => 'planning', 'permission' => ProgrammePermission::ManageProjects->value],
                ['name' => 'start_execution', 'from' => 'planning', 'to' => 'execution', 'permission' => ProgrammePermission::ManageProjects->value],
                ['name' => 'submit_closure', 'from' => 'execution', 'to' => 'closure_review', 'permission' => ProgrammePermission::ManageProjects->value],
                ['name' => 'approve_closure', 'from' => 'closure_review', 'to' => 'closed', 'permission' => ProgrammePermission::VerifyProjectUpdates->value, 'separation_from' => ['submit_closure'], 'terminal' => true],
            ], 'rules' => [],
        ]]);

        return $definition;
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, Sector $sector, User $approver, mixed $effectiveFrom = null): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
            'sectors' => [['id' => $sector->id]],
            'programmes' => [],
            'programme_county_coverages' => [],
        ];

        return ReferenceDataRelease::factory()->create([
            'version' => ((int) ReferenceDataRelease::query()->max('version')) + 1,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-PROJECT-'.str_pad((string) (((int) ReferenceDataRelease::query()->max('version')) + 1), 3, '0', STR_PAD_LEFT),
            'effective_from' => $effectiveFrom ?? now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
