<?php

namespace Tests\Feature;

use App\Actions\ApplyHistoricalDataMigration;
use App\Actions\ReviewHistoricalDataMigration;
use App\Actions\StageReferenceDataImport;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CountyReferenceDataWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_can_create_update_and_view_counties(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)->post(route('reference-data.counties.store'), [
            'code' => 48, 'name' => 'Test County', 'region' => 'Test Region',
            'official_website_url' => 'https://county.example.test', 'map_x' => 40.25, 'map_y' => 52.75,
        ])->assertRedirect();

        $county = County::query()->where('code', 48)->sole();
        $this->assertSame('test-county', $county->slug);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $county->id, 'action' => 'reference.county.created']);

        $this->actingAs($administrator)->patch(route('reference-data.counties.update', $county), [
            'code' => 48, 'name' => 'Updated Test County', 'region' => 'Updated Region',
            'official_website_url' => 'https://updated.example.test', 'map_x' => 41, 'map_y' => 53,
        ])->assertRedirect();

        $this->actingAs($administrator)->get(route('reference-data.index'))
            ->assertInertia(fn (Assert $page) => $page->component('reference-data/index')
                ->where('counties.data.0.identity.name', 'Updated Test County')
                ->where('counties.data.0.region', 'Updated Region'));
    }

    public function test_county_crud_is_permission_gated_and_unique(): void
    {
        $county = County::factory()->create(['code' => 48, 'name' => 'Existing County']);
        $official = User::factory()->countyOfficial()->create();
        $administrator = User::factory()->platformAdmin()->create();
        $payload = ['code' => 48, 'name' => 'Existing County', 'map_x' => 10, 'map_y' => 20];

        $this->actingAs($official)->post(route('reference-data.counties.store'), $payload)->assertForbidden();
        $this->actingAs($administrator)->post(route('reference-data.counties.store'), $payload)->assertSessionHasErrors(['code', 'name']);
        $this->assertModelExists($county);
    }

    public function test_bulk_archive_is_atomic_and_protects_constitutional_counties(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $constitutional = County::factory()->create(['code' => 1, 'name' => 'Mombasa']);
        $first = County::factory()->create(['code' => 48]);
        $second = County::factory()->create(['code' => 49]);

        $this->actingAs($administrator)->post(route('reference-data.counties.bulk-archive'), ['ids' => [$first->id, $constitutional->id]])->assertConflict();
        $this->assertNull($first->fresh()->deleted_at);

        $this->actingAs($administrator)->post(route('reference-data.counties.bulk-archive'), ['ids' => [$first->id, $second->id]])->assertRedirect();
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    }

    public function test_county_bulk_import_uses_review_and_apply_controls(): void
    {
        Storage::fake('local');
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();
        $file = UploadedFile::fake()->createWithContent('counties.csv', "code,name,region,official_website_url,map_x,map_y\n48,Test County,Test Region,https://county.example.test,44.5,51.5\n");

        $batch = app(StageReferenceDataImport::class)->handle($submitter, $file, 'counties', 'Approved county registry', 'SDD-COUNTIES-2026');
        app(ReviewHistoricalDataMigration::class)->handle($batch, $reviewer, 'approve', 'Validated against the approved source.');
        app(ApplyHistoricalDataMigration::class)->handle($batch->refresh(), $applier);

        $this->assertDatabaseHas('counties', ['code' => 48, 'name' => 'Test County']);
        $this->assertSame('submitted', $batch->refresh()->validation_report['reference_data_release']['status']);
        $this->actingAs($applier)->get(route('data-migrations.templates.show', ['counties']))->assertDownload('counties-bulk-import-template.csv');
    }
}
