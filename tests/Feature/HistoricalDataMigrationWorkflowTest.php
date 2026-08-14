<?php

namespace Tests\Feature;

use App\Actions\ApplyHistoricalDataMigration;
use App\Actions\ReviewHistoricalDataMigration;
use App\Actions\StageHistoricalDataMigration;
use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\HistoricalMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HistoricalDataMigrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_is_privately_staged_with_county_reconciliation_checksums_and_encrypted_source_rows(): void
    {
        Storage::fake('local');
        $county = County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();

        $batch = app(StageHistoricalDataMigration::class)->handle(
            $submitter,
            $this->csv([
                ['001', '2018', 'ACPA-OVERALL', 'Annual county performance assessment score', '67.5', '', 'percent', 'ACPA-2018-FINAL'],
                ['001', '2019-12-31', 'PFM-KRA', 'Public financial management KRA score', '72.25', '', 'percent', 'ACPA-2019-FINAL'],
            ]),
            'acpa_scores',
            'Independent ACPA verification register',
            'ACPA-HISTORICAL-2018-2019',
            '2018-01-01',
            '2019-12-31',
        );

        $this->assertSame('validated', $batch->status);
        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(0, $batch->invalid_rows);
        $this->assertSame($county->id, $batch->rows->first()->county_id);
        Storage::disk('local')->assertExists($batch->path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($batch->path)), $batch->file_checksum);
        $storedPayload = DB::table('data_migration_rows')->where('data_migration_batch_id', $batch->id)->value('source_payload');
        $this->assertIsString($storedPayload);
        $this->assertStringNotContainsString('ACPA-OVERALL', $storedPayload);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $batch->id, 'action' => 'data_migration.staged']);
    }

    public function test_invalid_county_period_and_value_are_retained_as_exceptions_and_cannot_be_approved(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $batch = app(StageHistoricalDataMigration::class)->handle(
            $submitter,
            $this->csv([['999', '2017', '', 'Historical score', 'not-a-number', '', 'percent', 'UNVERIFIED']]),
            'acpa_scores',
            'Unreconciled source',
            'SOURCE-UNVERIFIED',
            '2018-01-01',
            '2025-12-31',
        );

        $this->assertSame('validation_failed', $batch->status);
        $this->assertSame(1, $batch->invalid_rows);
        $this->assertEqualsCanonicalizing(
            ['unknown_county_code', 'period_outside_batch_range', 'missing_metric_code', 'invalid_numeric_value'],
            $batch->rows->sole()->validation_errors,
        );

        $this->expectException(HttpException::class);
        app(ReviewHistoricalDataMigration::class)->handle($batch, $reviewer, 'approve', 'Approval attempted before exception resolution.');
    }

    public function test_xlsx_historical_source_preserves_typed_dates_and_is_privately_retained(): void
    {
        Storage::fake('local');
        $county = County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();

        $this->withSession(['locale' => 'fr'])->actingAs($submitter)->post(route('data-migrations.store'), [
            'file' => $this->xlsx([
                [1, new \DateTimeImmutable('2019-12-31'), 'PFM-KRA', 'Public financial management KRA score', 72.25, '', 'percent', 'ACPA-2019-FINAL'],
            ]),
            'dataset_type' => 'acpa_scores',
            'source_name' => 'Independent ACPA verification workbook',
            'source_reference' => 'ACPA-HISTORICAL-XLSX-2019',
            'period_from' => '2019-01-01',
            'period_to' => '2019-12-31',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'La source historique a été préparée et rapprochée. Examinez chaque exception signalée avant approbation.');
        $batch = DataMigrationBatch::query()->sole();

        $this->assertSame('validated', $batch->status);
        $this->assertSame($county->id, $batch->rows->sole()->county_id);
        $this->assertSame('2019-12-31', $batch->rows->sole()->period->toDateString());
        $this->assertSame('72.2500', $batch->rows->sole()->numeric_value);
        $this->assertStringEndsWith('.xlsx', $batch->path);
        Storage::disk('local')->assertExists($batch->path);
    }

    public function test_three_person_approval_applies_immutable_historical_metrics_with_provenance(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();
        $batch = app(StageHistoricalDataMigration::class)->handle(
            $submitter,
            $this->csv([['001', '2018', 'ACPA-OVERALL', 'Annual county performance assessment score', '67.5', '', 'percent', 'ACPA-2018-FINAL']]),
            'acpa_scores',
            'Independent ACPA verification register',
            'ACPA-HISTORICAL-2018',
            '2018-01-01',
            '2018-12-31',
        );

        try {
            app(ReviewHistoricalDataMigration::class)->handle($batch, $submitter, 'approve', 'Self approval is prohibited.');
            $this->fail('The submitter must not approve their own migration batch.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $batch = app(ReviewHistoricalDataMigration::class)->handle($batch, $reviewer, 'approve', 'County code and source totals independently reconciled.');
        try {
            app(ApplyHistoricalDataMigration::class)->handle($batch, $reviewer);
            $this->fail('The reviewer must not apply the same migration batch.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $batch = app(ApplyHistoricalDataMigration::class)->handle($batch, $applier);
        $metric = HistoricalMetric::query()->sole();

        $this->assertSame('applied', $batch->status);
        $this->assertSame('ACPA-2018-FINAL', $metric->source_reference);
        $this->assertSame(64, mb_strlen($metric->record_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $batch->id, 'action' => 'data_migration.applied']);
        $this->assertDatabaseHas('historical_metrics', ['id' => $metric->id, 'numeric_value' => '67.5000']);
    }

    public function test_authorized_users_can_filter_the_migration_register_while_county_users_are_denied(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $countyUser = User::factory()->countyOfficial()->create();
        DataMigrationBatch::factory()->create([
            'dataset_type' => 'acpa_scores',
            'status' => 'validated',
            'submitted_by' => $administrator->id,
            'source_name' => 'Independent ACPA verification register',
        ]);
        DataMigrationBatch::factory()->create([
            'dataset_type' => 'performance_metrics',
            'status' => 'rejected',
            'submitted_by' => $administrator->id,
        ]);

        $this->actingAs($administrator)->get(route('data-migrations.index', ['type' => 'acpa_scores',
            'status' => 'validated',
            'search' => 'ACPA',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('data-migrations/index')
            ->has('batches.data', 1)
            ->where('batches.data.0.datasetType', 'acpa_scores')
            ->where('capabilities.stage', true));

        $this->actingAs($countyUser)
            ->get(route('data-migrations.index'))
            ->assertForbidden();
    }

    public function test_http_workflow_enforces_permissions_and_three_distinct_actors(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();

        $this->actingAs($submitter)->post(route('data-migrations.store'), [
            'file' => $this->csv([['001', '2018', 'ACPA-OVERALL', 'Annual county performance assessment score', '67.5', '', 'percent', 'ACPA-2018-FINAL']]),
            'dataset_type' => 'acpa_scores',
            'source_name' => 'Independent ACPA verification register',
            'source_reference' => 'ACPA-HISTORICAL-2018',
            'period_from' => '2018-01-01',
            'period_to' => '2018-12-31',
        ])->assertRedirect();
        $batch = DataMigrationBatch::query()->sole();

        $this->actingAs($submitter)->patch(route('data-migrations.review', [$batch]), [
            'decision' => 'approve',
            'notes' => 'The submitter cannot independently approve this batch.',
        ])->assertForbidden();

        $this->actingAs($reviewer)->patch(route('data-migrations.review', [$batch]), [
            'decision' => 'approve',
            'notes' => 'County codes and source totals independently reconciled.',
        ])->assertRedirect();

        $this->actingAs($reviewer)->post(route('data-migrations.apply', [$batch]), [
            'confirmation' => true,
        ])->assertForbidden();

        $this->actingAs($applier)->post(route('data-migrations.apply', [$batch]), [
            'confirmation' => true,
        ])->assertRedirect();

        $this->assertSame('applied', $batch->fresh()->status);
        $this->assertDatabaseCount('historical_metrics', 1);
    }

    public function test_direct_migration_actions_enforce_permissions_before_parsing_payloads_or_loading_state(): void
    {
        Storage::fake('local');
        $submitter = User::factory()->platformAdmin()->create();
        $unauthorizedActor = User::factory()->countyOfficial()->create();
        $batch = DataMigrationBatch::factory()->create([
            'submitted_by' => $submitter->id,
            'status' => 'validated',
            'invalid_rows' => 0,
        ]);
        app()->setLocale('fr');

        $attempts = [
            'Vous n’êtes pas autorisé à préparer les migrations historiques.' => fn () => app(StageHistoricalDataMigration::class)->handle(
                $unauthorizedActor,
                UploadedFile::fake()->createWithContent('hostile.exe', 'not tabular data'),
                'hostile-dataset',
                '',
                '',
                'not-a-date',
                'not-a-date',
            ),
            'Vous n’êtes pas autorisé à examiner les migrations historiques.' => fn () => app(ReviewHistoricalDataMigration::class)->handle($batch, $unauthorizedActor, 'hostile-decision', ''),
            'Vous n’êtes pas autorisé à appliquer les migrations historiques.' => fn () => app(ApplyHistoricalDataMigration::class)->handle($batch, $unauthorizedActor),
        ];

        foreach ($attempts as $expectedMessage => $attempt) {
            try {
                $attempt();
                $this->fail('An unauthorized direct migration action must be denied.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
                $this->assertSame($expectedMessage, $exception->getMessage());
            }
        }

        $this->assertSame('validated', $batch->fresh()->status);
        $this->assertDatabaseCount('data_migration_rows', 0);
        $this->assertDatabaseCount('historical_metrics', 0);
        app()->setLocale('en');
    }

    public function test_review_audit_uses_the_reviewers_active_locale(): void
    {
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $batch = DataMigrationBatch::factory()->create([
            'submitted_by' => $submitter->id,
            'status' => 'validated',
            'invalid_rows' => 0,
        ]);
        app()->setLocale('sw');

        app(ReviewHistoricalDataMigration::class)->handle($batch, $reviewer, 'reject', 'Chanzo hakina idhini inayohitajika.');

        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $batch->id,
            'action' => 'data_migration.rejected',
            'description' => 'Uhamishaji wa data ya kihistoria umekataliwa.',
        ]);
        app()->setLocale('en');
    }

    public function test_source_download_rechecks_the_retained_checksum(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->platformAdmin()->create();
        $path = 'data-migrations/retained.csv';
        Storage::disk('local')->put($path, 'verified source');
        $batch = DataMigrationBatch::factory()->create([
            'submitted_by' => $administrator->id,
            'path' => $path,
            'original_name' => 'retained.csv',
            'mime_type' => 'text/csv',
            'file_checksum' => hash('sha256', 'verified source'),
        ]);

        $this->actingAs($administrator)
            ->get(route('data-migrations.download', [$batch]))
            ->assertOk()
            ->assertDownload('retained.csv');

        Storage::disk('local')->put($path, 'tampered source');
        $this->actingAs($administrator)
            ->get(route('data-migrations.download', [$batch]))
            ->assertStatus(409);
    }

    public function test_staging_rejects_duplicate_natural_keys_and_applied_replays_or_conflicts(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();
        $duplicateBatch = app(StageHistoricalDataMigration::class)->handle(
            $submitter,
            $this->csv([
                ['001', '2018', 'ACPA-OVERALL', 'Annual county performance assessment score', '67.5', '', 'percent', 'ACPA-2018-FINAL'],
                ['001', '2018', 'acpa-overall', 'Annual county performance assessment score', '67.5', '', 'percent', 'ACPA-2018-FINAL'],
            ]),
            'acpa_scores',
            'Duplicate source rows',
            'ACPA-DUPLICATE-2018',
            '2018-01-01',
            '2018-12-31',
        );

        $this->assertSame('validation_failed', $duplicateBatch->status);
        $this->assertSame(2, $duplicateBatch->invalid_rows);
        $this->assertTrue($duplicateBatch->rows->every(fn ($row): bool => in_array('duplicate_natural_key_in_batch', $row->validation_errors, true)));

        $applied = app(StageHistoricalDataMigration::class)->handle(
            $submitter,
            $this->csv([['001', '2018', 'ACPA-OVERALL', 'Annual county performance assessment score', '67.5', '', 'percent', 'ACPA-2018-FINAL']]),
            'acpa_scores',
            'Independent ACPA verification register',
            'ACPA-HISTORICAL-2018',
            '2018-01-01',
            '2018-12-31',
        );
        $applied = app(ReviewHistoricalDataMigration::class)->handle($applied, $reviewer, 'approve', 'The source and county totals were independently reconciled.');
        app(ApplyHistoricalDataMigration::class)->handle($applied, $applier);

        $exactReplay = app(StageHistoricalDataMigration::class)->handle(
            User::factory()->platformAdmin()->create(),
            $this->csv([['001', '2018', 'ACPA-OVERALL', 'Annual county performance assessment score', '67.5', '', 'percent', 'ACPA-2018-FINAL']]),
            'acpa_scores',
            'Independent ACPA verification register',
            'ACPA-REPLAY-2018',
            '2018-01-01',
            '2018-12-31',
        );
        $conflict = app(StageHistoricalDataMigration::class)->handle(
            User::factory()->platformAdmin()->create(),
            $this->csv([['001', '2018', 'ACPA-OVERALL', 'Annual county performance assessment score', '91.0', '', 'percent', 'REVISED-WITHOUT-AUTHORITY']]),
            'acpa_scores',
            'Unapproved conflicting source',
            'ACPA-CONFLICT-2018',
            '2018-01-01',
            '2018-12-31',
        );

        $this->assertSame(['duplicate_applied_record'], $exactReplay->rows->sole()->validation_errors);
        $this->assertSame(['conflicting_applied_record'], $conflict->rows->sole()->validation_errors);
        $this->assertSame('validation_failed', $exactReplay->status);
        $this->assertSame('validation_failed', $conflict->status);
    }

    public function test_application_rechecks_for_a_conflict_created_after_staging(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $firstSubmitter = User::factory()->platformAdmin()->create();
        $secondSubmitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $firstApplier = User::factory()->platformAdmin()->create();
        $secondApplier = User::factory()->platformAdmin()->create();
        $first = app(StageHistoricalDataMigration::class)->handle($firstSubmitter, $this->csv([['001', '2018', 'ACPA-OVERALL', 'Annual county performance assessment score', '67.5', '', 'percent', 'SOURCE-A']]), 'acpa_scores', 'First reconciled source', 'BATCH-A', '2018-01-01', '2018-12-31');
        $second = app(StageHistoricalDataMigration::class)->handle($secondSubmitter, $this->csv([['001', '2018', 'ACPA-OVERALL', 'Annual county performance assessment score', '70.0', '', 'percent', 'SOURCE-B']]), 'acpa_scores', 'Concurrent conflicting source', 'BATCH-B', '2018-01-01', '2018-12-31');
        $first = app(ReviewHistoricalDataMigration::class)->handle($first, $reviewer, 'approve', 'First source independently reconciled before application.');
        $second = app(ReviewHistoricalDataMigration::class)->handle($second, $reviewer, 'approve', 'Second source independently reconciled before application.');
        app(ApplyHistoricalDataMigration::class)->handle($first, $firstApplier);

        try {
            app(ApplyHistoricalDataMigration::class)->handle($second, $secondApplier);
            $this->fail('A natural-key conflict introduced after staging must block application.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame('approved', $second->fresh()->status);
        $this->assertDatabaseCount('historical_metrics', 1);
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): UploadedFile
    {
        $lines = ['county_code,period,metric_code,metric_name,numeric_value,narrative_value,unit,source_reference'];
        foreach ($rows as $row) {
            $stream = fopen('php://temp', 'r+');
            $this->assertIsResource($stream);
            fputcsv($stream, $row);
            rewind($stream);
            $lines[] = rtrim((string) stream_get_contents($stream));
            fclose($stream);
        }

        return UploadedFile::fake()->createWithContent('historical-metrics.csv', implode("\n", $lines));
    }

    /** @param list<list<mixed>> $rows */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'idmis-historical-test-');
        $this->assertIsString($path);
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(StageHistoricalDataMigration::REQUIRED_HEADERS));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValuesWithStyles($row, [
                1 => new Style(format: 'yyyy-mm-dd'),
            ]));
        }
        $writer->close();

        return new UploadedFile(
            $path,
            'historical-metrics.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
