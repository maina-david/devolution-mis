<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Jobs\ExtractDocumentText;
use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DocumentExtraction;
use App\Models\DocumentExtractionAttempt;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentTextExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_text_document_is_checksum_indexed_and_searchable_only_inside_county_scope(): void
    {
        Storage::fake('local');
        Notification::fake();
        $home = County::factory()->create();
        $other = County::factory()->create();
        $official = User::factory()->countyOfficial($home)->create();
        $otherOfficial = User::factory()->countyOfficial($other)->create();
        $homeAssessment = Assessment::factory()->create(['county_id' => $home->id, 'status' => AssessmentStatus::EvidenceCollection]);
        $otherAssessment = Assessment::factory()->create(['county_id' => $other->id, 'status' => AssessmentStatus::EvidenceCollection]);

        $this->uploadTextEvidence($official, $homeAssessment, 'Home evidence', 'The verified allocation includes the unique KWALE-SEARCH-441 marker.');
        $this->uploadTextEvidence($otherOfficial, $otherAssessment, 'Other evidence', 'The hidden record also contains KWALE-SEARCH-441.');

        $document = AssessmentDocument::query()->where('county_id', $home->id)->sole();
        $extraction = DocumentExtraction::query()->whereHas('version', fn ($query) => $query->where('assessment_document_id', $document->id))->sole();
        $this->assertSame('completed', $document->ocr_status);
        $this->assertSame('native-text', $extraction->engine);
        $this->assertSame(hash('sha256', (string) $extraction->extracted_text), $extraction->text_checksum_sha256);
        $this->assertSame(mb_strlen((string) $extraction->extracted_text), $extraction->character_count);
        $attempt = DocumentExtractionAttempt::query()->where('document_extraction_id', $extraction->id)->sole();
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame('upload', $attempt->trigger_source);
        $this->assertSame($official->id, $attempt->initiated_by);
        $this->assertSame($official->name, $attempt->initiated_by_name);
        $this->assertSame('completed', $attempt->status);
        $this->assertSame($extraction->text_checksum_sha256, $attempt->text_checksum_sha256);
        $this->assertNotNull($attempt->duration_ms);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $document->id, 'action' => 'document.text_extraction_completed']);

        $this->actingAs($official)->get(route('evidence.index', [
            'current_team' => $official->currentTeam->slug,
            'search' => 'KWALE-SEARCH-441',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('workspace.rows', 1)
            ->where('workspace.rows.0.id', $document->id)
            ->where('workspace.rows.0.meta.ocrStatus', 'completed')
            ->where('workspace.rows.0.meta.extractionEngine', 'native-text')
            ->where('workspace.rows.0.meta.extractionAttempts.0.triggerSource', 'upload')
            ->where('workspace.rows.0.meta.extractionAttempts.0.initiatedBy', $official->name)
            ->where('workspace.pagination.total', 1));
    }

    public function test_scanned_image_stays_pending_on_an_explicit_ocr_dependency_instead_of_becoming_falsely_searchable(): void
    {
        Storage::fake('local');
        Notification::fake();
        config()->set('repository.extraction.tesseract_binary', 'missing-idmis-tesseract');
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
        $extraction = DocumentExtraction::query()->sole();
        $this->assertSame('waiting_dependency', $document->ocr_status);
        $this->assertSame('waiting_dependency', $extraction->status);
        $this->assertSame('tesseract_unavailable', $extraction->error_code);
        $this->assertSame('tesseract_unavailable', DocumentExtractionAttempt::query()->sole()->error_code);
        $this->assertNull($extraction->extracted_text);
        $this->actingAs($official)->get(route('evidence.preview', [$official->currentTeam->slug, $document]))->assertOk();
    }

    public function test_image_only_pdf_is_rasterized_ocr_indexed_and_records_page_provenance(): void
    {
        Storage::fake('local');
        Notification::fake();
        $binaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'idmis-ocr-test-'.str()->uuid();
        mkdir($binaryDirectory, 0700, true);
        $pdftotext = $binaryDirectory.DIRECTORY_SEPARATOR.'pdftotext';
        $pdftoppm = $binaryDirectory.DIRECTORY_SEPARATOR.'pdftoppm';
        $tesseract = $binaryDirectory.DIRECTORY_SEPARATOR.'tesseract';
        file_put_contents($pdftotext, "#!/bin/sh\nexit 0\n");
        file_put_contents($pdftoppm, "#!/bin/sh\nfor last; do true; done\ntouch \"\${last}-1.png\"\n");
        file_put_contents($tesseract, "#!/bin/sh\nprintf 'Scanned county allocation marker OCR-PDF-778'\n");
        chmod($pdftotext, 0700);
        chmod($pdftoppm, 0700);
        chmod($tesseract, 0700);

        try {
            config()->set('repository.extraction.pdftotext_binary', $pdftotext);
            config()->set('repository.extraction.pdftoppm_binary', $pdftoppm);
            config()->set('repository.extraction.tesseract_binary', $tesseract);
            $county = County::factory()->create();
            $official = User::factory()->countyOfficial($county)->create();
            $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);

            $this->actingAs($official)->post(route('evidence.store', [$official->currentTeam->slug, $assessment]), [
                'title' => 'Image-only scanned PDF',
                'category' => 'Public participation',
                'source_type' => 'scanned',
                'document' => UploadedFile::fake()->createWithContent('scanned-register.pdf', "%PDF-1.4\n%%EOF"),
            ])->assertRedirect();

            $document = AssessmentDocument::query()->sole();
            $extraction = DocumentExtraction::query()->sole();
            $this->assertSame('completed', $document->ocr_status, (string) $extraction->error_code);
            $this->assertSame('poppler-pdftoppm+tesseract', $extraction->engine);
            $this->assertSame(1, $extraction->page_count);
            $this->assertSame(200, $extraction->metadata['dpi']);
            $this->assertSame('poppler-pdftoppm+tesseract', DocumentExtractionAttempt::query()->sole()->engine);
            $this->actingAs($official)->get(route('evidence.index', ['current_team' => $official->currentTeam->slug, 'search' => 'OCR-PDF-778']))
                ->assertOk()->assertInertia(fn (Assert $page) => $page->has('workspace.rows', 1));
        } finally {
            foreach ([$pdftotext, $pdftoppm, $tesseract] as $binary) {
                @unlink($binary);
            }
            @rmdir($binaryDirectory);
        }
    }

    public function test_manual_retry_appends_attributed_attempt_evidence_without_replacing_prior_history(): void
    {
        Storage::fake('local');
        Notification::fake();
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $recordsManager = User::factory()->devolutionAdmin()->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);
        $this->uploadTextEvidence($official, $assessment, 'Retry evidence', 'Governed text extraction retry.');
        $document = AssessmentDocument::query()->sole();

        $this->actingAs($official)->post(route('evidence.extract', [$official->currentTeam->slug, $document]))->assertForbidden();
        $this->actingAs($recordsManager)->post(route('evidence.extract', [$recordsManager->currentTeam->slug, $document]))->assertRedirect();

        $attempts = DocumentExtractionAttempt::query()->orderBy('attempt_number')->get();
        $this->assertCount(2, $attempts);
        $this->assertSame(['upload', 'manual_retry'], $attempts->pluck('trigger_source')->all());
        $this->assertSame([$official->id, $recordsManager->id], $attempts->pluck('initiated_by')->all());
        $this->assertSame([1, 2], $attempts->pluck('attempt_number')->all());
        $this->assertSame(2, DocumentExtraction::query()->sole()->attempt_count);
        $this->assertSame('completed', $document->fresh()?->ocr_status);
    }

    public function test_completed_extraction_attempt_evidence_is_database_immutable(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL immutability trigger is database specific.');
        }

        Storage::fake('local');
        Notification::fake();
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);
        $this->uploadTextEvidence($official, $assessment, 'Immutable extraction evidence', 'Immutable attempt result.');
        $attempt = DocumentExtractionAttempt::query()->sole();

        try {
            DocumentExtractionAttempt::query()->whereKey($attempt->id)->update(['error_code' => 'tampered']);
            $this->fail('Completed extraction attempt mutation should be rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Completed document extraction attempts are immutable', $exception->getMessage());
        }

        $this->expectException(QueryException::class);
        DocumentExtractionAttempt::query()->whereKey($attempt->id)->delete();
    }

    public function test_replacement_indexes_only_the_current_version_and_retains_prior_extraction_evidence(): void
    {
        Storage::fake('local');
        Notification::fake();
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);
        $this->uploadTextEvidence($official, $assessment, 'Versioned evidence', 'Legacy marker OLD-CONTENT-771.');
        $document = AssessmentDocument::query()->sole();
        $oldVersionId = (string) $document->current_version_id;

        $this->actingAs($official)->post(route('evidence.versions.store', [$official->currentTeam->slug, $document]), [
            'document' => UploadedFile::fake()->createWithContent('replacement.txt', 'Corrected marker NEW-CONTENT-992.'),
            'change_summary' => 'Corrected the approved reference.',
        ])->assertRedirect();

        $document->refresh();
        $this->assertNotSame($oldVersionId, $document->current_version_id);
        $this->assertSame('completed', $document->ocr_status);
        $this->assertSame(2, DocumentExtraction::query()->count());
        $this->assertSame(2, DocumentVersion::query()->count());

        $this->actingAs($official)->get(route('evidence.index', ['current_team' => $official->currentTeam->slug, 'search' => 'OLD-CONTENT-771']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('workspace.rows', 0));
        $this->actingAs($official)->get(route('evidence.index', ['current_team' => $official->currentTeam->slug, 'search' => 'NEW-CONTENT-992']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('workspace.rows', 1));
    }

    public function test_recovery_command_queues_retryable_current_versions_and_skips_completed_versions(): void
    {
        Queue::fake();
        $pending = AssessmentDocument::factory()->create(['ocr_status' => 'pending']);
        $pendingVersion = DocumentVersion::factory()->create(['assessment_document_id' => $pending->id]);
        $pending->update(['current_version_id' => $pendingVersion->id]);
        $completed = AssessmentDocument::factory()->create(['ocr_status' => 'completed']);
        $completedVersion = DocumentVersion::factory()->create(['assessment_document_id' => $completed->id]);
        $completed->update(['current_version_id' => $completedVersion->id]);

        $this->artisan('documents:recover-extractions')->assertSuccessful();

        Queue::assertPushed(ExtractDocumentText::class, 1);
        Queue::assertPushed(fn (ExtractDocumentText $job): bool => $job->documentVersionId === $pendingVersion->id);
    }

    public function test_storage_tampering_is_blocked_from_extraction_preview_and_download(): void
    {
        Storage::fake('local');
        Notification::fake();
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);
        $this->uploadTextEvidence($official, $assessment, 'Integrity evidence', 'Approved original content.');
        $document = AssessmentDocument::query()->sole();
        Storage::put($document->path, 'Unauthorized storage-layer replacement.');

        ExtractDocumentText::dispatchSync((string) $document->current_version_id, true);

        $this->assertSame('failed', $document->fresh()?->ocr_status);
        $this->assertSame('integrity_mismatch', $document->currentVersion?->extraction?->fresh()?->error_code);
        $this->actingAs($official)->get(route('evidence.preview', [$official->currentTeam->slug, $document]))->assertStatus(409);
        $this->actingAs($official)->get(route('evidence.download', [$official->currentTeam->slug, $document]))->assertStatus(409);
    }

    private function uploadTextEvidence(User $official, Assessment $assessment, string $title, string $contents): void
    {
        $this->actingAs($official)->post(route('evidence.store', [$official->currentTeam->slug, $assessment]), [
            'title' => $title,
            'category' => 'Other',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->createWithContent(str($title)->slug()->append('.txt')->toString(), $contents),
        ])->assertRedirect();
    }
}
