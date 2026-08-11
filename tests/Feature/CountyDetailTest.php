<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\CountyGrant;
use App\Models\User;
use Database\Seeders\AssessmentScorecardSeeder;
use Database\Seeders\CountySeeder;
use Database\Seeders\DemoProgrammeSeeder;
use Database\Seeders\LocalAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CountyDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_filtered_county_record_with_all_related_modules(): void
    {
        $county = County::factory()->create(['name' => 'Nairobi']);
        $otherCounty = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'cycle' => 'Visible cycle', 'created_at' => '2026-02-01']);
        AssessmentDocument::factory()->create(['county_id' => $county->id, 'assessment_id' => $assessment->id, 'title' => 'Visible evidence', 'created_at' => '2026-03-01']);
        CountyGrant::factory()->create(['county_id' => $county->id, 'programme' => 'Visible grant', 'created_at' => '2026-04-01']);

        $this->actingAs($admin)->get(route('counties.show', [$admin->currentTeam->slug, $county, 'from' => '2026-01-01', 'to' => '2026-12-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('counties/show')
                ->where('county.name', 'Nairobi')
                ->where('summary.assessments', 1)
                ->where('summary.documents', 1)
                ->has('assessments.rows', 1)
                ->has('documents.rows', 1)
                ->has('grants.rows', 1)
                ->where('filters.from', '2026-01-01')
            );

        $this->actingAs($admin)->get(route('counties.show', [$admin->currentTeam->slug, $otherCounty]))->assertForbidden();
    }

    public function test_demo_documents_are_backed_by_previewable_stored_files(): void
    {
        Storage::fake('local');
        $this->app->detectEnvironment(fn () => 'local');
        $this->seed([CountySeeder::class, LocalAccessSeeder::class, AssessmentScorecardSeeder::class, DemoProgrammeSeeder::class]);
        $document = AssessmentDocument::query()->firstOrFail();
        $admin = User::factory()->countyAdmin($document->county)->create();

        Storage::assertExists($document->path);
        $this->actingAs($admin)
            ->get(route('evidence.preview', [$admin->currentTeam->slug, $document]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }
}
