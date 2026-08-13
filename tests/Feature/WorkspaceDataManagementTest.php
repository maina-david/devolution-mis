<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_records_are_date_filtered_searched_and_server_paginated(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        Assessment::factory()->count(12)->sequence(fn ($sequence) => [
            'county_id' => $county->id,
            'cycle' => "Cycle {$sequence->index}",
            'created_at' => $sequence->index === 0 ? '2025-01-10' : '2026-08-01',
        ])->create();

        $this->actingAs($admin)->withSession(['locale' => 'fr'])->get(route('assessments.index', ['from' => '2026-01-01',
            'to' => '2026-12-31',
            'search' => 'Cycle',
            'per_page' => 10,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('workspace.rows', 10)
            ->where('workspace.pagination.total', 11)
            ->where('workspace.pagination.lastPage', 2)
            ->where('filters.from', '2026-01-01')
            ->where('localization.current', 'fr')
            ->where('localization.programmeWorkspace.authorized_records', 'Dossiers autorisés')
            ->where('localization.programmeWorkspace.no_matching_records', 'Aucun dossier correspondant')
        );
    }

    public function test_authorized_user_can_export_filtered_workspace_in_all_supported_formats(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        Assessment::factory()->create(['county_id' => $county->id, 'cycle' => 'Export cycle']);

        foreach (['csv', 'json', 'xlsx', 'pdf'] as $format) {
            $this->actingAs($admin)
                ->get(route('workspace.export', ['assessments', $format]))
                ->assertOk()
                ->assertDownload();
        }
    }

    public function test_authorized_user_can_export_an_explicit_selection_without_leaking_unavailable_records(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $selected = Assessment::factory()->create(['county_id' => $county->id, 'cycle' => 'Selected export cycle']);
        Assessment::factory()->create(['county_id' => $county->id, 'cycle' => 'Unselected export cycle']);
        $outsideScope = Assessment::factory()->create(['county_id' => $otherCounty->id, 'cycle' => 'Outside export cycle']);

        $content = $this->actingAs($admin)->get(route('workspace.export', ['assessments',
            'json',
            'ids' => [$selected->id],
        ]))->assertOk()->streamedContent();

        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $payload['rows']);
        $this->assertSame('Selected export cycle', $payload['rows'][0][1]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'workspace.exported',
            'subject_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('workspace.export', ['assessments',
            'json',
            'ids' => [$selected->id, $outsideScope->id],
        ]))->assertUnprocessable();
    }

    public function test_selected_workspace_export_requires_bounded_distinct_uuid_ids(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);

        $this->actingAs($admin)->get(route('workspace.export', ['assessments',
            'json',
            'ids' => [$assessment->id, $assessment->id],
        ]))->assertSessionHasErrors('ids.1');

        $this->actingAs($admin)->get(route('workspace.export', ['assessments',
            'json',
            'ids' => array_fill(0, 101, fake()->uuid()),
        ]))->assertSessionHasErrors('ids');
    }

    public function test_assessment_workspace_and_exports_are_filtered_by_assessment_cycle(): void
    {
        $county = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $selectedCycle = AssessmentCycle::factory()->create(['name' => 'Selected ACPA cycle']);
        $otherCycle = AssessmentCycle::factory()->create(['name' => 'Other ACPA cycle']);
        Assessment::factory()->create([
            'county_id' => $county->id,
            'assessment_cycle_id' => $selectedCycle->id,
            'cycle' => $selectedCycle->code,
        ]);
        Assessment::factory()->create([
            'county_id' => $county->id,
            'assessment_cycle_id' => $otherCycle->id,
            'cycle' => $otherCycle->code,
        ]);

        $query = ['cycle_id' => $selectedCycle->id,
        ];

        $this->actingAs($admin)->get(route('assessments.index', $query))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('workspace.rows', 1)
                ->where('workspace.rows.0.cells.1', $selectedCycle->code)
                ->where('filters.cycle_id', $selectedCycle->id)
                ->has('cycles', 2));

        $this->actingAs($admin)
            ->get(route('workspace.export', ['assessments', 'csv']).'?cycle_id='.$selectedCycle->id)
            ->assertOk()
            ->assertDownload();
    }

    public function test_document_preview_metadata_and_archive_are_county_scoped(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $otherAssessment = Assessment::factory()->create(['county_id' => $otherCounty->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id, 'path' => 'evidence/visible.pdf']);
        $hidden = AssessmentDocument::factory()->create(['assessment_id' => $otherAssessment->id, 'county_id' => $otherCounty->id, 'path' => 'evidence/hidden.pdf']);
        Storage::put($document->path, '%PDF evidence');
        Storage::put($hidden->path, '%PDF hidden');
        $document->update(['content_checksum' => hash('sha256', '%PDF evidence')]);
        $hidden->update(['content_checksum' => hash('sha256', '%PDF hidden')]);

        $this->actingAs($admin)->get(route('evidence.preview', [$document]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get(route('evidence.preview', [$hidden]))->assertForbidden();

        $this->actingAs($admin)->patch(route('evidence.update', [$document]), [
            'title' => 'Updated CIDP evidence',
            'category' => 'CIDP',
            'description' => 'Approved planning evidence',
            'document_date' => '2026-07-01',
            'retention_until' => '2033-07-01',
            'tags' => 'planning, approved',
        ])->assertRedirect();
        $this->assertSame(['planning', 'approved'], $document->fresh()?->tags);

        $this->actingAs($admin)->delete(route('evidence.destroy', [$document]))->assertRedirect();
        $this->assertSoftDeleted($document);
        Storage::assertExists('evidence/visible.pdf');
    }
}
