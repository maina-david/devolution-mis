<?php

namespace Tests\Feature;

use App\Actions\ApplyHistoricalDataMigration;
use App\Actions\ReviewHistoricalDataMigration;
use App\Actions\StageHistoricalDataMigration;
use App\Models\County;
use App\Models\LegacyAcpaAssessment;
use App\Models\LegacyAcpaComponent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LegacyAcpaReconstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_legacy_acpa_file_is_reconstructed_into_an_immutable_provenance_register(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1, 'name' => 'Mombasa']);
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();
        $rows = $this->completeRows();

        $batch = app(StageHistoricalDataMigration::class)->handle(
            $submitter,
            $this->csv($rows),
            'acpa_reconstruction',
            'Independent ACPA 2018 verification archive',
            'ACPA-2018-SIGNED-REGISTER',
            '2018-01-01',
            '2018-12-31',
        );

        $this->assertSame('validated', $batch->status);
        $this->assertSame(6, $batch->valid_rows);
        $batch = app(ReviewHistoricalDataMigration::class)->handle($batch, $reviewer, 'approve', 'Signed register and evidence manifest independently reconciled.');
        $batch = app(ApplyHistoricalDataMigration::class)->handle($batch, $applier);

        $assessment = LegacyAcpaAssessment::query()->with('components')->sole();
        $this->assertSame('applied', $batch->status);
        $this->assertSame('ACPA-001-2018', $assessment->assessment_reference);
        $this->assertSame('67.5000', $assessment->overall_score);
        $this->assertCount(5, $assessment->components);
        $this->assertEqualsCanonicalizing(
            ['appeal', 'assessor_assignment', 'criterion_result', 'evidence_manifest', 'finding'],
            $assessment->components->pluck('record_type')->all(),
        );
        $assignment = $assessment->components->firstWhere('record_type', 'assessor_assignment');
        $this->assertInstanceOf(LegacyAcpaComponent::class, $assignment);
        $this->assertSame('IVA-2018-0042', $assignment->person_identifier);
        $rawAssignment = DB::table('legacy_acpa_components')->where('id', $assignment->id)->first();
        $this->assertIsString($rawAssignment?->person_identifier);
        $this->assertStringNotContainsString('IVA-2018-0042', $rawAssignment->person_identifier);
        $this->assertStringNotContainsString('Independent verification assessor', (string) $rawAssignment->source_payload);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $batch->id, 'action' => 'data_migration.legacy_acpa_applied']);

        $this->actingAs($applier)->get(route('data-migrations.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('legacyAcpa.pagination.total', 1)
            ->where('legacyAcpa.rows.0.cells.0.name', 'Mombasa')
            ->where('legacyAcpa.rows.0.cells.1', 'ACPA-001-2018 · 2018 Annual County Performance Assessment')
            ->where('legacyAcpa.rows.0.meta.components.0.type', 'appeal')
            ->has('legacyAcpa.rows.0.meta.components', 5));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($applier)->get(route('workspace.export', ['workspace' => 'legacy-acpa', 'format' => $format]))
                ->assertOk()
                ->assertDownload();
        }

        $this->expectException(QueryException::class);
        $assessment->update(['status' => 'revised']);
    }

    public function test_missing_assessment_header_and_invalid_evidence_checksum_are_retained_as_exceptions(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();
        $row = $this->completeRows()[2];
        $row[17] = 'not-a-sha256-checksum';

        $batch = app(StageHistoricalDataMigration::class)->handle($submitter, $this->csv([array_values($row)]), 'acpa_reconstruction', 'Unreconciled evidence list', 'UNVERIFIED-EVIDENCE', '2018-01-01', '2018-12-31');

        $this->assertSame('validation_failed', $batch->status);
        $this->assertEqualsCanonicalizing(['incomplete_evidence_manifest', 'missing_assessment_header'], $batch->rows->sole()->validation_errors);
    }

    public function test_applied_legacy_record_replay_and_conflict_are_detected(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();
        $rows = $this->completeRows();
        $original = app(StageHistoricalDataMigration::class)->handle(User::factory()->platformAdmin()->create(), $this->csv($rows), 'acpa_reconstruction', 'Signed ACPA archive', 'ACPA-ARCHIVE-2018', '2018-01-01', '2018-12-31');
        $original = app(ReviewHistoricalDataMigration::class)->handle($original, $reviewer, 'approve', 'Archive independently reconciled.');
        app(ApplyHistoricalDataMigration::class)->handle($original, $applier);

        $replay = app(StageHistoricalDataMigration::class)->handle(User::factory()->platformAdmin()->create(), $this->csv($rows), 'acpa_reconstruction', 'Signed ACPA archive replay', 'ACPA-ARCHIVE-REPLAY', '2018-01-01', '2018-12-31');
        $conflictingRows = $rows;
        $conflictingRows[1][7] = '91.0';
        $conflict = app(StageHistoricalDataMigration::class)->handle(User::factory()->platformAdmin()->create(), $this->csv(array_map(array_values(...), $conflictingRows)), 'acpa_reconstruction', 'Conflicting ACPA archive', 'ACPA-ARCHIVE-CONFLICT', '2018-01-01', '2018-12-31');

        $this->assertSame('validation_failed', $replay->status);
        $this->assertContains('duplicate_applied_record', $replay->rows->first()->validation_errors);
        $this->assertSame('validation_failed', $conflict->status);
        $this->assertContains('conflicting_applied_record', $conflict->rows->firstWhere('row_number', 3)->validation_errors);
    }

    public function test_reconstruction_rejects_broken_cross_record_integrity_and_component_only_appends(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $rows = $this->completeRows();
        $rows[1][7] = '120';
        $rows[2][5] = 'UNKNOWN-CRITERION';
        $rows[4][10] = 'supporting_assessor';
        $rows[5][14] = '';

        $invalid = app(StageHistoricalDataMigration::class)->handle(User::factory()->platformAdmin()->create(), $this->csv(array_map(array_values(...), $rows)), 'acpa_reconstruction', 'Broken ACPA archive', 'ACPA-BROKEN', '2018-01-01', '2018-12-31');

        $this->assertSame('validation_failed', $invalid->status);
        $this->assertContains('criterion_score_out_of_range', $invalid->rows->firstWhere('row_number', 3)->validation_errors);
        $this->assertContains('unknown_criterion_reference', $invalid->rows->firstWhere('row_number', 4)->validation_errors);
        $this->assertContains('missing_lead_assessor', $invalid->rows->firstWhere('row_number', 2)->validation_errors);
        $this->assertContains('missing_appeal_decision', $invalid->rows->firstWhere('row_number', 7)->validation_errors);

        $validRows = $this->completeRows();
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();
        $batch = app(StageHistoricalDataMigration::class)->handle($submitter, $this->csv($validRows), 'acpa_reconstruction', 'Complete ACPA archive', 'ACPA-COMPLETE', '2018-01-01', '2018-12-31');
        $batch = app(ReviewHistoricalDataMigration::class)->handle($batch, $reviewer, 'approve', 'Complete archive reconciled.');
        app(ApplyHistoricalDataMigration::class)->handle($batch, $applier);

        $append = app(StageHistoricalDataMigration::class)->handle(User::factory()->platformAdmin()->create(), $this->csv([$validRows[3]]), 'acpa_reconstruction', 'Late finding append', 'ACPA-LATE-APPEND', '2018-01-01', '2018-12-31');
        $this->assertContains('missing_assessment_header', $append->rows->sole()->validation_errors);
    }

    public function test_three_person_control_and_exact_template_are_enforced_for_reconstruction(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();
        $batch = app(StageHistoricalDataMigration::class)->handle($submitter, $this->csv($this->completeRows()), 'acpa_reconstruction', 'Independent ACPA archive', 'ACPA-2018', '2018-01-01', '2018-12-31');

        try {
            app(ReviewHistoricalDataMigration::class)->handle($batch, $submitter, 'approve', 'Self approval attempted.');
            $this->fail('A submitter must not approve the reconstruction batch.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $response = $this->actingAs(User::factory()->platformAdmin()->create())->get(route('data-migrations.templates.show', ['datasetType' => 'acpa_reconstruction']));
        $response->assertOk();
        $this->assertSame(implode(',', StageHistoricalDataMigration::LEGACY_ACPA_HEADERS)."\n", $response->streamedContent());
    }

    /** @return list<list<string>> */
    private function completeRows(): array
    {
        $checksum = hash('sha256', 'signed-acpa-evidence-document');

        return [
            ['001', 'ACPA-001-2018', '2018', 'assessment', 'ACPA-001-2018', '', '2018 Annual County Performance Assessment', '67.5', '100', 'final', '', '', '', 'Signed final assessment register.', '', '', '', '', 'ACPA-2018-FINAL'],
            ['001', 'ACPA-001-2018', '2018', 'criterion_result', 'PFM-01', 'PFM-01', 'Approved budget and financial statements', '67.5', '100', 'met', '', '', '', 'Criterion verified against signed county records.', '', '', '', '', 'ACPA-2018-SCORECARD'],
            ['001', 'ACPA-001-2018', '2018', 'evidence_manifest', 'EVID-PFM-01', 'PFM-01', 'Audited financial statements', '', '', 'verified', '', '', '', 'Checksum manifest for the retained signed evidence.', '', 'county-001-audited-statements.pdf', 'application/pdf', $checksum, 'ACPA-2018-EVIDENCE-MANIFEST'],
            ['001', 'ACPA-001-2018', '2018', 'finding', 'FIND-PFM-01', 'PFM-01', 'Procurement plan publication delay', '', '', 'closed', '', '', '', 'The procurement plan was published after the prescribed date.', '', '', '', '', 'ACPA-2018-FINDINGS'],
            ['001', 'ACPA-001-2018', '2018', 'assessor_assignment', 'ASSIGN-0042', '', 'Independent verification assessor', '', '', 'completed', 'lead_assessor', 'IVA-2018-0042', 'Grace Wanjiku', 'Independent verification assessor assigned to County 001.', '', '', '', '', 'ACPA-2018-ASSIGNMENTS'],
            ['001', 'ACPA-001-2018', '2018', 'appeal', 'APPEAL-PFM-01', 'PFM-01', 'County appeal on PFM criterion', '', '', 'determined', '', '', '', 'County requested reconsideration of the criterion score.', 'upheld_original_score', '', '', '', 'ACPA-2018-APPEALS'],
        ];
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'r+');
        $this->assertIsResource($stream);
        fputcsv($stream, StageHistoricalDataMigration::LEGACY_ACPA_HEADERS);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('legacy-acpa-reconstruction.csv', $content);
    }
}
