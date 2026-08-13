<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DocumentLegalHold;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\DocumentSecurityScanner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentRecordsGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_clamav_scan_contract_normalizes_clean_and_infected_results(): void
    {
        config()->set('repository.security.malware_scanner', 'clamav');
        Process::preventStrayProcesses();
        Process::fake(function ($process) {
            $command = $process->command;
            if (! is_array($command)) {
                throw new \RuntimeException('Unexpected string malware scanner command.');
            }

            $path = (string) end($command);

            return str_contains((string) file_get_contents($path), 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')
                ? Process::result(output: "{$path}: Win.Test.EICAR_HDB-1 FOUND\n", exitCode: 1)
                : Process::result(output: '', exitCode: 0);
        });

        $scanner = app(DocumentSecurityScanner::class);
        $clean = $scanner->inspect(UploadedFile::fake()->createWithContent('clean.txt', 'approved county record'));
        $infected = $scanner->inspect(UploadedFile::fake()->createWithContent('eicar.txt', str_repeat('x', 9000).'EICAR-STANDARD-ANTIVIRUS-TEST-FILE'));

        $this->assertSame('clean', $clean['status']);
        $this->assertSame('clamav-clamscan', $clean['details']['engine']);
        $this->assertSame('infected', $infected['status']);
        $this->assertSame('Win.Test.EICAR_HDB-1', $infected['details']['signature']);
        $this->assertArrayNotHasKey('path', $infected['details']);
        Process::assertRanTimes(fn ($process): bool => is_array($process->command) && $process->command[0] === 'clamscan', times: 2);
    }

    public function test_development_signature_gate_scans_the_complete_file(): void
    {
        config()->set('repository.security.malware_scanner', 'signature');

        $inspection = app(DocumentSecurityScanner::class)->inspect(
            UploadedFile::fake()->createWithContent('late-signature.txt', str_repeat('x', 70_000).'EICAR-STANDARD-ANTIVIRUS-TEST-FILE'),
        );

        $this->assertSame('infected', $inspection['status']);
        $this->assertSame('idmis-development-signature-gate', $inspection['details']['engine']);
    }

    public function test_clamav_failure_rejects_an_unscanned_upload_without_persisting_it(): void
    {
        Storage::fake('local');
        Notification::fake();
        config()->set('repository.security.malware_scanner', 'clamav');
        Process::preventStrayProcesses();
        Process::fake(['*' => Process::result(errorOutput: 'scanner unavailable', exitCode: 2)]);
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);

        $this->actingAs($official)->post(route('evidence.store', [$assessment]), [
            'title' => 'Unscanned evidence',
            'category' => 'Other',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->createWithContent('evidence.txt', 'unscanned'),
        ])->assertServerError();

        $this->assertDatabaseCount('assessment_documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('assessment-evidence'));
    }

    public function test_upload_creates_checksum_bound_immutable_version_and_ocr_work_item(): void
    {
        Storage::fake('local');
        Notification::fake();
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);

        $this->actingAs($official)->post(route('evidence.store', [$assessment]), [
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

        $this->actingAs($official)->post(route('evidence.store', [$assessment]), [
            'title' => 'Unsafe attachment',
            'category' => 'Other',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->createWithContent('unsafe.txt', $eicar),
        ])->assertRedirect();

        $document = AssessmentDocument::query()->sole();
        $this->assertSame('infected', $document->scan_status);
        $this->actingAs($official)->get(route('evidence.preview', [$document]))->assertStatus(423);
        $this->actingAs($official)->get(route('evidence.download', [$document]))->assertStatus(423);
        $this->actingAs($assessor)->patch(route('evidence.verify', [$document]), ['status' => 'verified'])->assertStatus(409);
    }

    public function test_replacement_creates_a_new_version_and_resets_evidence_verification(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id, 'verification_status' => 'verified']);
        DocumentVersion::factory()->create(['assessment_document_id' => $document->id, 'uploaded_by' => $official->id]);

        $this->actingAs($official)->post(route('evidence.versions.store', [$document]), [
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

        $this->actingAs($recordsManager)->post(route('evidence.legal-holds.store', [$document]), [
            'reference' => 'HOLD-CASE-001',
            'reason' => 'Pending investigation and records preservation order.',
            'authority' => 'Office of the Auditor-General',
        ])->assertRedirect();
        $this->assertTrue($document->hasActiveLegalHold());

        $this->actingAs($official)->post(route('evidence.versions.store', [$document]), [
            'document' => UploadedFile::fake()->createWithContent('replacement.txt', 'replacement version'),
            'change_summary' => 'Attempted replacement',
        ])->assertStatus(409);
        $this->actingAs($official)->patch(route('evidence.update', [$document]), [
            'title' => $document->title,
            'category' => $document->category,
            'retention_until' => '2031-01-01',
        ])->assertStatus(409);
        $this->actingAs($official)->delete(route('evidence.destroy', [$document]))->assertStatus(409);
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

        $this->actingAs($admin)->get(route('evidence.versions.preview', [$document, $version]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get(route('evidence.versions.download', [$document, $version]))
            ->assertOk()
            ->assertDownload('evidence.pdf');
        $this->actingAs($admin)->get(route('evidence.versions.download', [$document, $otherVersion]))->assertNotFound();
        $this->actingAs($admin)->get(route('evidence.versions.download', [$otherDocument, $otherVersion]))->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['subject_id' => $version->id, 'action' => 'evidence.version_previewed']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $version->id, 'action' => 'evidence.version_downloaded']);

        Storage::put($version->path, '%PDF tampered retained version');
        $this->actingAs($admin)->get(route('evidence.versions.download', [$document, $version]))->assertStatus(409);
    }

    public function test_retained_media_versions_support_authorized_range_preview(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $otherCounty = County::factory()->create();
        $admin = User::factory()->countyAdmin($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $otherAssessment = Assessment::factory()->create(['county_id' => $otherCounty->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id]);
        $hiddenDocument = AssessmentDocument::factory()->create(['assessment_id' => $otherAssessment->id, 'county_id' => $otherCounty->id]);
        $content = str_repeat('media-range-', 128);

        Storage::put('assessment-evidence/briefing.mp4', $content);
        Storage::put('assessment-evidence/hidden.mp4', $content);
        $version = DocumentVersion::factory()->create([
            'assessment_document_id' => $document->id,
            'storage_disk' => 'local',
            'path' => 'assessment-evidence/briefing.mp4',
            'original_name' => 'county-briefing.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => strlen($content),
            'content_checksum' => hash('sha256', $content),
            'scan_status' => 'clean',
            'uploaded_by' => $admin->id,
        ]);
        $hiddenVersion = DocumentVersion::factory()->create([
            'assessment_document_id' => $hiddenDocument->id,
            'storage_disk' => 'local',
            'path' => 'assessment-evidence/hidden.mp4',
            'original_name' => 'hidden-briefing.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => strlen($content),
            'content_checksum' => hash('sha256', $content),
            'scan_status' => 'clean',
        ]);

        $this->actingAs($admin)
            ->withHeader('Range', 'bytes=0-9')
            ->get(route('evidence.versions.preview', [$document, $version]))
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 0-9/'.strlen($content))
            ->assertHeader('Content-Type', 'video/mp4');
        $this->withoutHeader('Range');

        $this->actingAs($admin)
            ->get(route('evidence.versions.preview', [$hiddenDocument, $hiddenVersion]))
            ->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['subject_id' => $version->id, 'action' => 'evidence.version_previewed']);
    }

    public function test_shared_document_action_uses_native_audio_and_video_previews(): void
    {
        $source = file_get_contents(base_path('resources/js/components/evidence-row-action.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString("const isVideo = mimeType.startsWith('video/')", $source);
        $this->assertStringContainsString("const isAudio = mimeType.startsWith('audio/')", $source);
        $this->assertStringContainsString('<video', $source);
        $this->assertStringContainsString('<audio', $source);
        $this->assertStringContainsString('preload="metadata"', $source);
        $this->assertStringContainsString('controlsList="nodownload"', $source);
        $this->assertStringContainsString('usePage().props.localization', $source);
        $this->assertStringContainsString('copy.manage_document', $source);
        $this->assertStringContainsString('toLocaleString(locale)', $source);
        $this->assertStringNotContainsString('DEFAULT_LOCALE', $source);
        $this->assertStringNotContainsString('>Manage document<', $source);

        $controller = file_get_contents(app_path('Http/Controllers/EvidenceController.php'));
        $this->assertIsString($controller);
        $this->assertStringContainsString("__('evidence.outcomes.uploaded')", $controller);
        $this->assertStringContainsString("__('evidence.errors.preview_unavailable')", $controller);
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

        $this->actingAs($recordsManager)->get(route('evidence.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('workspace.rows', 1)
                ->where('workspace.rows.0.meta.versions.0.id', $version->id)
                ->where('workspace.rows.0.meta.versions.0.isCurrent', true)
                ->where('workspace.rows.0.meta.legalHolds.0.id', $hold->id)
                ->where('workspace.rows.0.meta.legalHolds.0.releasedAt', null)
                ->where('workspace.rows.0.meta.legalHolds.0.canRelease', true));

        $this->actingAs($holdPlacer)->patch(route('evidence.legal-holds.release', [$document, $hold]), [
            'release_reason' => 'Attempted self-release.',
        ])->assertStatus(409);

        $this->actingAs($recordsManager)->patch(route('evidence.legal-holds.release', [$document, $hold]), [
            'release_reason' => 'The preservation authority confirmed the investigation is closed and approved release.',
        ])->assertRedirect();

        $hold->refresh();
        $this->assertSame($recordsManager->id, $hold->released_by);
        $this->assertNotNull($hold->released_at);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $hold->id, 'action' => 'document.legal_hold_released']);
    }
}
