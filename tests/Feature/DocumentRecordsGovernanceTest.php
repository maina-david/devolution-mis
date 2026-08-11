<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DocumentLegalHold;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentRecordsGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_creates_checksum_bound_immutable_version_and_ocr_work_item(): void
    {
        Storage::fake('local');
        Notification::fake();
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);

        $this->actingAs($official)->post(route('evidence.store', [$official->currentTeam->slug, $assessment]), [
            'title' => 'Scanned participation register',
            'category' => 'Public participation',
            'source_type' => 'scanned',
            'document' => UploadedFile::fake()->image('register.jpg'),
        ])->assertRedirect();

        $document = AssessmentDocument::query()->sole();
        $version = DocumentVersion::query()->sole();
        $this->assertSame(hash('sha256', Storage::get($document->path)), $document->content_checksum);
        $this->assertSame($document->content_checksum, $version->content_checksum);
        $this->assertSame($version->id, $document->current_version_id);
        $this->assertSame('clean', $document->scan_status);
        $this->assertSame('waiting_dependency', $document->ocr_status);
        $this->assertSame('tesseract_unavailable', $version->extraction?->error_code);

        $this->expectException(QueryException::class);
        DB::table('document_versions')->where('id', $version->id)->update(['change_summary' => 'Tampered']);
    }

    public function test_infected_upload_is_quarantined_from_preview_download_and_verification(): void
    {
        Storage::fake('local');
        Notification::fake();
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($county);
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);
        $eicar = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

        $this->actingAs($official)->post(route('evidence.store', [$official->currentTeam->slug, $assessment]), [
            'title' => 'Unsafe attachment',
            'category' => 'Other',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->createWithContent('unsafe.txt', $eicar),
        ])->assertRedirect();

        $document = AssessmentDocument::query()->sole();
        $this->assertSame('infected', $document->scan_status);
        $this->actingAs($official)->get(route('evidence.preview', [$official->currentTeam->slug, $document]))->assertStatus(423);
        $this->actingAs($official)->get(route('evidence.download', [$official->currentTeam->slug, $document]))->assertStatus(423);
        $this->actingAs($assessor)->patch(route('evidence.verify', [$assessor->currentTeam->slug, $document]), ['status' => 'verified'])->assertStatus(409);
    }

    public function test_replacement_creates_a_new_version_and_resets_evidence_verification(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id, 'verification_status' => 'verified']);
        DocumentVersion::factory()->create(['assessment_document_id' => $document->id, 'uploaded_by' => $official->id]);

        $this->actingAs($official)->post(route('evidence.versions.store', [$official->currentTeam->slug, $document]), [
            'document' => UploadedFile::fake()->createWithContent('replacement.txt', 'replacement version'),
            'change_summary' => 'Corrected the signed date.',
        ])->assertRedirect();

        $document->refresh();
        $this->assertSame(2, $document->version);
        $this->assertSame('pending', $document->verification_status);
        $this->assertSame(2, $document->versions()->count());
        $this->assertSame('Corrected the signed date.', $document->currentVersion?->change_summary);
    }

    public function test_legal_hold_blocks_replacement_retention_change_and_archive(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $recordsManager = User::factory()->devolutionAdmin()->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id, 'retention_until' => '2030-01-01']);

        $this->actingAs($recordsManager)->post(route('evidence.legal-holds.store', [$recordsManager->currentTeam->slug, $document]), [
            'reference' => 'HOLD-CASE-001',
            'reason' => 'Pending investigation and records preservation order.',
            'authority' => 'Office of the Auditor-General',
        ])->assertRedirect();
        $this->assertTrue($document->hasActiveLegalHold());

        $this->actingAs($official)->post(route('evidence.versions.store', [$official->currentTeam->slug, $document]), [
            'document' => UploadedFile::fake()->createWithContent('replacement.txt', 'replacement version'),
            'change_summary' => 'Attempted replacement',
        ])->assertStatus(409);
        $this->actingAs($official)->patch(route('evidence.update', [$official->currentTeam->slug, $document]), [
            'title' => $document->title,
            'category' => $document->category,
            'retention_until' => '2031-01-01',
        ])->assertStatus(409);
        $this->actingAs($official)->delete(route('evidence.destroy', [$official->currentTeam->slug, $document]))->assertStatus(409);
        $this->assertDatabaseHas('assessment_documents', ['id' => $document->id, 'deleted_at' => null]);
        $this->assertSame(1, DocumentLegalHold::query()->count());
    }

    public function test_retained_versions_are_county_scoped_checksum_verified_and_audited(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $otherAssessment = Assessment::factory()->create(['county_id' => $otherCounty->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id]);
        $otherDocument = AssessmentDocument::factory()->create(['assessment_id' => $otherAssessment->id, 'county_id' => $otherCounty->id]);

        Storage::put('assessment-evidence/v1.pdf', '%PDF retained version one');
        Storage::put('assessment-evidence/other.pdf', '%PDF other county');
        $version = DocumentVersion::factory()->create([
            'assessment_document_id' => $document->id,
            'path' => 'assessment-evidence/v1.pdf',
            'content_checksum' => hash('sha256', '%PDF retained version one'),
            'uploaded_by' => $admin->id,
        ]);
        $otherVersion = DocumentVersion::factory()->create([
            'assessment_document_id' => $otherDocument->id,
            'path' => 'assessment-evidence/other.pdf',
            'content_checksum' => hash('sha256', '%PDF other county'),
        ]);

        $this->actingAs($admin)->get(route('evidence.versions.preview', [$admin->currentTeam->slug, $document, $version]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get(route('evidence.versions.download', [$admin->currentTeam->slug, $document, $version]))
            ->assertOk()
            ->assertDownload('evidence.pdf');
        $this->actingAs($admin)->get(route('evidence.versions.download', [$admin->currentTeam->slug, $document, $otherVersion]))->assertNotFound();
        $this->actingAs($admin)->get(route('evidence.versions.download', [$admin->currentTeam->slug, $otherDocument, $otherVersion]))->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['subject_id' => $version->id, 'action' => 'evidence.version_previewed']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $version->id, 'action' => 'evidence.version_downloaded']);

        Storage::put($version->path, '%PDF tampered retained version');
        $this->actingAs($admin)->get(route('evidence.versions.download', [$admin->currentTeam->slug, $document, $version]))->assertStatus(409);
    }

    public function test_workspace_exposes_complete_version_and_legal_hold_history_with_governed_release(): void
    {
        $county = County::factory()->create();
        $recordsManager = User::factory()->devolutionAdmin()->create();
        $holdPlacer = User::factory()->platformAdmin()->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id]);
        $version = DocumentVersion::factory()->create(['assessment_document_id' => $document->id, 'uploaded_by' => $recordsManager->id]);
        $document->update(['current_version_id' => $version->id, 'version' => 1]);
        $hold = DocumentLegalHold::factory()->create(['assessment_document_id' => $document->id, 'placed_by' => $holdPlacer->id]);

        $this->actingAs($recordsManager)->get(route('evidence.index', $recordsManager->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('workspace.rows', 1)
                ->where('workspace.rows.0.meta.versions.0.id', $version->id)
                ->where('workspace.rows.0.meta.versions.0.isCurrent', true)
                ->where('workspace.rows.0.meta.legalHolds.0.id', $hold->id)
                ->where('workspace.rows.0.meta.legalHolds.0.releasedAt', null)
                ->where('workspace.rows.0.meta.legalHolds.0.canRelease', true));

        $this->actingAs($holdPlacer)->patch(route('evidence.legal-holds.release', [$holdPlacer->currentTeam->slug, $document, $hold]), [
            'release_reason' => 'Attempted self-release.',
        ])->assertStatus(409);

        $this->actingAs($recordsManager)->patch(route('evidence.legal-holds.release', [$recordsManager->currentTeam->slug, $document, $hold]), [
            'release_reason' => 'The preservation authority confirmed the investigation is closed and approved release.',
        ])->assertRedirect();

        $hold->refresh();
        $this->assertSame($recordsManager->id, $hold->released_by);
        $this->assertNotNull($hold->released_at);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $hold->id, 'action' => 'document.legal_hold_released']);
    }
}
