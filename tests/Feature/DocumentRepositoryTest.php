<?php

namespace Tests\Feature;

use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DocumentFolder;
use App\Models\DocumentLegalHold;
use App\Models\User;
use Database\Seeders\DocumentRepositorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_lists_only_folders_and_documents_in_the_users_authorized_scope(): void
    {
        $ownCounty = County::factory()->create();
        $otherCounty = County::factory()->create();
        $official = User::factory()->countyOfficial($ownCounty)->create();
        $creator = User::factory()->devolutionAdmin()->create();
        $ownFolder = DocumentFolder::factory()->create(['county_id' => $ownCounty->id, 'created_by' => $creator->id, 'name' => 'County plans']);
        DocumentFolder::factory()->create(['county_id' => $otherCounty->id, 'created_by' => $creator->id, 'name' => 'Restricted county records']);
        DocumentFolder::factory()->national()->create(['created_by' => $creator->id, 'name' => 'National policy']);
        $visible = AssessmentDocument::factory()->create(['assessment_id' => null, 'county_id' => $ownCounty->id, 'folder_id' => $ownFolder->id, 'title' => 'Makueni annual development plan']);
        AssessmentDocument::factory()->create(['assessment_id' => null, 'county_id' => $otherCounty->id, 'title' => 'Restricted report']);

        $this->actingAs($official)->get(route('evidence.index', ['folder_id' => $ownFolder->id, 'search' => 'annual development']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.repository.currentFolderId', $ownFolder->id)
                ->has('workspace.repository.folders', 1)
                ->where('workspace.repository.folders.0.name', 'County plans')
                ->has('workspace.rows', 1)
                ->where('workspace.rows.0.id', $visible->id)
                ->where('workspace.rows.0.meta.folderName', 'County plans'));
    }

    public function test_records_manager_can_create_rename_and_delete_an_empty_folder_but_not_duplicate_or_cyclic_folders(): void
    {
        $county = County::factory()->create();
        $manager = User::factory()->devolutionAdmin()->create();

        $this->actingAs($manager)->post(route('evidence.repository.folders.store'), [
            'county_id' => $county->id,
            'name' => 'County legislation',
        ])->assertRedirect();

        $folder = DocumentFolder::query()->where('name', 'County legislation')->firstOrFail();
        $this->actingAs($manager)->post(route('evidence.repository.folders.store'), [
            'county_id' => $county->id,
            'name' => 'county LEGISLATION',
        ])->assertStatus(422);

        $child = DocumentFolder::factory()->within($folder)->create(['created_by' => $manager->id, 'name' => 'Acts']);
        $this->actingAs($manager)->patch(route('evidence.repository.folders.update', $folder), [
            'parent_id' => $child->id,
            'name' => 'County laws',
        ])->assertStatus(422);

        $this->actingAs($manager)->delete(route('evidence.repository.folders.destroy', $folder))->assertStatus(409);
        $this->actingAs($manager)->delete(route('evidence.repository.folders.destroy', $child))->assertRedirect(route('evidence.index'));
        $this->assertSoftDeleted('document_folders', ['id' => $child->id]);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $folder->id, 'action' => 'document.folder.created']);
    }

    public function test_county_user_can_upload_scanned_or_digital_files_only_to_their_county_repository(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('filesystems.default', 'local');
        config()->set('repository.security.malware_scanner', 'signature');
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $creator = User::factory()->devolutionAdmin()->create();
        $folder = DocumentFolder::factory()->create(['county_id' => $county->id, 'created_by' => $creator->id, 'name' => 'Public participation']);
        $restrictedFolder = DocumentFolder::factory()->create(['county_id' => $otherCounty->id, 'created_by' => $creator->id]);

        $this->actingAs($official)->post(route('evidence.repository.documents.store'), [
            'folder_id' => $folder->id,
            'title' => 'Signed public participation register',
            'category' => 'Public participation',
            'source_type' => 'scanned',
            'description' => 'Certified attendance register for the county planning forum.',
            'document_date' => now()->toDateString(),
            'tags' => 'planning, participation, signed',
            'document' => UploadedFile::fake()->createWithContent('signed-register.txt', 'Certified county participation register'),
        ])->assertRedirect();

        $document = AssessmentDocument::query()->where('title', 'Signed public participation register')->firstOrFail();
        $this->assertSame($folder->id, $document->folder_id);
        $this->assertSame('clean', $document->scan_status);
        $this->assertCount(1, $document->versions);
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($official)->post(route('evidence.repository.documents.store'), [
            'folder_id' => $restrictedFolder->id,
            'title' => 'Unauthorized record',
            'category' => 'Other',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->createWithContent('record.txt', 'restricted'),
        ])->assertForbidden();
    }

    public function test_bulk_move_enforces_scope_and_active_legal_holds(): void
    {
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $manager = User::factory()->devolutionAdmin()->create();
        $source = DocumentFolder::factory()->create(['county_id' => $county->id, 'created_by' => $manager->id]);
        $destination = DocumentFolder::factory()->create(['county_id' => $county->id, 'created_by' => $manager->id]);
        $otherDestination = DocumentFolder::factory()->create(['county_id' => $otherCounty->id, 'created_by' => $manager->id]);
        $movable = AssessmentDocument::factory()->create(['assessment_id' => null, 'county_id' => $county->id, 'folder_id' => $source->id]);

        $this->actingAs($manager)->patch(route('evidence.repository.documents.move'), [
            'ids' => [$movable->id],
            'folder_id' => $destination->id,
        ])->assertRedirect();
        $this->assertSame($destination->id, $movable->refresh()->folder_id);

        $this->actingAs($manager)->patch(route('evidence.repository.documents.move'), [
            'ids' => [$movable->id],
            'folder_id' => $otherDestination->id,
        ])->assertStatus(422);

        DocumentLegalHold::factory()->create(['assessment_document_id' => $movable->id, 'placed_by' => $manager->id]);
        $this->actingAs($manager)->patch(route('evidence.repository.documents.move'), [
            'ids' => [$movable->id],
            'folder_id' => $source->id,
        ])->assertStatus(409);
        $this->assertSame($destination->id, $movable->refresh()->folder_id);
    }

    public function test_folder_management_requires_records_management_permission(): void
    {
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();

        $this->actingAs($official)->post(route('evidence.repository.folders.store'), [
            'county_id' => $county->id,
            'name' => 'Bypassed folder',
        ])->assertForbidden();

        $this->assertDatabaseMissing('document_folders', ['name' => 'Bypassed folder']);
    }

    public function test_repository_seeder_provisions_a_realistic_hierarchy_and_files_existing_records(): void
    {
        $county = County::factory()->create(['name' => 'Repository Test County']);
        User::factory()->devolutionAdmin()->create();
        $document = AssessmentDocument::factory()->create([
            'assessment_id' => null,
            'county_id' => $county->id,
            'folder_id' => null,
            'title' => 'County Integrated Development Plan 2023-2027',
            'category' => 'CIDP',
        ]);

        $this->seed(DocumentRepositorySeeder::class);

        $root = DocumentFolder::query()->where('county_id', $county->id)->whereNull('parent_id')->where('name', 'Plans and budgets')->firstOrFail();
        $current = DocumentFolder::query()->where('parent_id', $root->id)->where('name', 'Current cycle')->firstOrFail();
        $this->assertSame($current->id, $document->refresh()->folder_id);
        $this->assertSame(4, DocumentFolder::query()->whereNull('county_id')->count());
    }
}
