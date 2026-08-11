<?php

namespace Tests\Feature;

use App\Actions\ApplyHistoricalDataMigration;
use App\Actions\ReviewHistoricalDataMigration;
use App\Actions\StageReferenceDataImport;
use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;
use ZipArchive;

class BulkReferenceDataImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_csv_is_dry_run_validated_and_stored_with_encrypted_rows(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();

        $batch = app(StageReferenceDataImport::class)->handle(
            $submitter,
            $this->csv('organizations', [
                ['NT', 'National Treasury', 'national', '', 'registry@treasury.go.ke', 'active'],
                ['CG001', 'County Government of Mombasa', 'county', '001', 'info@mombasa.go.ke', 'active'],
            ]),
            'organizations',
            'Approved institutional registry',
            'SDD-REGISTRY-2026-01',
        );

        $this->assertSame('validated', $batch->status);
        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(0, $batch->invalid_rows);
        $this->assertSame('CG001', $batch->rows->last()->source_payload['code']);
        $storedPayload = DB::table('data_migration_rows')->where('data_migration_batch_id', $batch->id)->value('source_payload');
        $this->assertIsString($storedPayload);
        $this->assertStringNotContainsString('National Treasury', $storedPayload);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $batch->id, 'action' => 'data_import.staged']);
    }

    public function test_invalid_and_duplicate_reference_rows_are_retained_as_exceptions(): void
    {
        Storage::fake('local');
        Organization::factory()->create(['code' => 'EXISTING']);
        $submitter = User::factory()->platformAdmin()->create();

        $batch = app(StageReferenceDataImport::class)->handle(
            $submitter,
            $this->csv('organizations', [
                ['EXISTING', 'Duplicate organization', 'unknown', '999', 'not-an-email', 'wrong'],
                ['DUP', 'First duplicate', 'partner', '', 'one@example.test', 'active'],
                ['DUP', 'Second duplicate', 'partner', '', 'two@example.test', 'active'],
            ]),
            'organizations',
            'Registry validation exercise',
            'SDD-REGISTRY-EXCEPTIONS',
        );

        $this->assertSame('validation_failed', $batch->status);
        $this->assertSame(3, $batch->invalid_rows);
        $this->assertEqualsCanonicalizing(
            ['invalid_organization_type', 'unknown_county_code', 'invalid_email', 'invalid_status', 'code_already_exists'],
            $batch->rows->first()->validation_errors,
        );
        $this->assertContains('duplicate_code_in_file', $batch->rows->last()->validation_errors);
    }

    public function test_authorized_reviewer_can_download_formula_safe_row_level_exception_evidence(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();

        $batch = app(StageReferenceDataImport::class)->handle(
            $submitter,
            $this->csv('organizations', [
                ['INVALID', '=HYPERLINK("https://example.test")', 'unknown', '999', 'not-an-email', 'wrong'],
            ]),
            'organizations',
            'Registry validation exercise',
            'SDD-REGISTRY-EXCEPTIONS',
        );

        $response = $this->actingAs($submitter)->get(route('data-migrations.exceptions.download', [
            $submitter->currentTeam->slug,
            $batch,
        ]));

        $response->assertOk()->assertDownload("{$batch->reference}-row-exceptions.csv");
        $content = $response->streamedContent();
        $this->assertStringContainsString('batch_reference,dataset_type,source_file_checksum,row_number,code,name,type,county_code,email,status,validation_errors,source_row_checksum', $content);
        $this->assertStringContainsString($batch->file_checksum, $content);
        $this->assertStringContainsString('invalid_organization_type|unknown_county_code|invalid_email|invalid_status', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString("\n=HYPERLINK", $content);
    }

    public function test_exception_report_requires_authorization_and_at_least_one_invalid_row(): void
    {
        Storage::fake('local');
        $submitter = User::factory()->platformAdmin()->create();
        $countyUser = User::factory()->countyOfficial()->create();
        $batch = app(StageReferenceDataImport::class)->handle(
            $submitter,
            $this->csv('organizations', [
                ['VALID', 'Valid organization', 'national', '', 'valid@example.test', 'active'],
            ]),
            'organizations',
            'Approved institutional registry',
            'SDD-REGISTRY-2026-01',
        );

        $route = route('data-migrations.exceptions.download', [$submitter->currentTeam->slug, $batch]);
        $this->actingAs($countyUser)->get($route)->assertForbidden();
        $this->actingAs($submitter)->get($route)->assertNotFound();
    }

    public function test_three_person_control_atomically_applies_each_supported_registry(): void
    {
        Storage::fake('local');
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();

        $organizationBatch = $this->approvedBatch($submitter, $reviewer, 'organizations', [
            ['SDD', 'State Department for Devolution', 'national', '', 'info@devolution.go.ke', 'active'],
        ]);
        app(ApplyHistoricalDataMigration::class)->handle($organizationBatch, $applier);

        $sectorBatch = $this->approvedBatch($submitter, $reviewer, 'sectors', [
            ['DEV-GOV', 'Devolution Governance', '', 'Intergovernmental governance and coordination', 'true'],
        ]);
        app(ApplyHistoricalDataMigration::class)->handle($sectorBatch, $applier);

        $programmeBatch = $this->approvedBatch($submitter, $reviewer, 'programmes', [
            ['KDSP-II', 'Second Kenya Devolution Support Programme', 'Institutional strengthening programme', 'SDD', 'DEV-GOV', '2024-01-01', '2028-12-31', 'active', '1000000', 'KES'],
        ]);
        app(ApplyHistoricalDataMigration::class)->handle($programmeBatch, $applier);

        $this->assertDatabaseHas('organizations', ['code' => 'SDD', 'name' => 'State Department for Devolution']);
        $this->assertDatabaseHas('sectors', ['code' => 'DEV-GOV', 'name' => 'Devolution Governance']);
        $programme = Programme::query()->where('code', 'KDSP-II')->sole();
        $this->assertSame(Organization::query()->where('code', 'SDD')->value('id'), $programme->lead_organization_id);
        $this->assertSame(Sector::query()->where('code', 'DEV-GOV')->value('id'), $programme->sector_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $programmeBatch->id, 'action' => 'data_import.applied']);
    }

    public function test_authorized_user_can_download_exact_csv_templates(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->get(route('data-migrations.templates.show', [$administrator->currentTeam->slug, 'programmes']))
            ->assertOk()
            ->assertDownload('programmes-bulk-import-template.csv');

        $this->actingAs($administrator)
            ->get(route('data-migrations.templates.show', [$administrator->currentTeam->slug, 'unsupported']))
            ->assertNotFound();
    }

    public function test_authorized_user_can_download_and_stage_an_exact_xlsx_template(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->get(route('data-migrations.templates.show', [
                $administrator->currentTeam->slug,
                'organizations',
                'format' => 'xlsx',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('organizations-bulk-import-template.xlsx');

        $this->actingAs($administrator)->post(route('data-migrations.reference-data.store', $administrator->currentTeam->slug), [
            'file' => $this->xlsx('organizations', [
                ['COG', 'Council of Governors', 'national', '', 'info@cog.go.ke', 'active'],
            ]),
            'dataset_type' => 'organizations',
            'source_name' => 'Approved institutional registry',
            'source_reference' => 'SDD-REGISTRY-XLSX-2026-01',
        ])->assertRedirect();
        $batch = DataMigrationBatch::query()->sole();

        $this->assertSame('validated', $batch->status);
        $this->assertSame('COG', $batch->rows->sole()->source_payload['code']);
        $this->assertStringEndsWith('.xlsx', $batch->path);
        Storage::disk('local')->assertExists($batch->path);
    }

    public function test_xlsx_import_rejects_formula_cells_and_multiple_worksheets(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->platformAdmin()->create();

        try {
            app(StageReferenceDataImport::class)->handle(
                $administrator,
                $this->xlsx('organizations', [
                    ['FORMULA', '=1+1', 'national', '', 'formula@example.test', 'active'],
                ]),
                'organizations',
                'Formula-bearing registry',
                'SDD-REGISTRY-FORMULA',
            );
            $this->fail('Formula-bearing spreadsheets must fail closed.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('formulas are not allowed', $exception->errors()['file'][0]);
        }

        $this->expectException(ValidationException::class);
        app(StageReferenceDataImport::class)->handle(
            $administrator,
            $this->xlsx('organizations', [
                ['MULTI', 'Multiple sheet registry', 'national', '', 'multi@example.test', 'active'],
            ], true),
            'organizations',
            'Ambiguous multi-sheet registry',
            'SDD-REGISTRY-MULTI-SHEET',
        );
    }

    public function test_xlsx_import_rejects_external_workbook_links(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->platformAdmin()->create();

        $this->expectException(ValidationException::class);
        app(StageReferenceDataImport::class)->handle(
            $administrator,
            $this->xlsx('organizations', [
                ['LINKED', 'Externally linked registry', 'national', '', 'linked@example.test', 'active'],
            ], externalLink: true),
            'organizations',
            'Externally linked registry',
            'SDD-REGISTRY-EXTERNAL-LINK',
        );
    }

    public function test_user_import_validates_roles_county_scopes_and_creates_invited_accounts_without_uploaded_passwords(): void
    {
        Storage::fake('local');
        Notification::fake();
        $counties = County::factory()->count(3)->sequence(
            ['code' => 1],
            ['code' => 2],
            ['code' => 3],
        )->create();
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();

        $batch = $this->approvedBatch($submitter, $reviewer, 'users', [
            ['Amina Hassan', 'amina.hassan@mombasa.go.ke', 'county-official', '001', ''],
            ['Independent Verification Agent', 'assessor@idmis.go.ke', 'assessor', '', '001|002|003'],
        ]);
        app(ApplyHistoricalDataMigration::class)->handle($batch, $applier);

        $official = User::query()->where('email', 'amina.hassan@mombasa.go.ke')->firstOrFail();
        $assessor = User::query()->where('email', 'assessor@idmis.go.ke')->firstOrFail();
        $this->assertSame('county-official', $official->programmeRole()->value);
        $this->assertSame($counties[0]->id, $official->county_id);
        $this->assertSame('assessor', $assessor->programmeRole()->value);
        $this->assertEqualsCanonicalizing($counties->pluck('id')->all(), $assessor->assignedCounties()->pluck('counties.id')->all());
        $this->assertNotSame('', $official->password);
        $this->assertNotNull($official->currentTeam);
        $this->assertSame('applied', $batch->fresh()->status);
    }

    public function test_user_import_rejects_existing_email_invalid_roles_and_invalid_scope_shapes(): void
    {
        Storage::fake('local');
        County::factory()->create(['code' => 1]);
        User::factory()->create(['email' => 'existing@idmis.go.ke']);
        $submitter = User::factory()->platformAdmin()->create();

        $batch = app(StageReferenceDataImport::class)->handle(
            $submitter,
            $this->csv('users', [
                ['Existing User', 'existing@idmis.go.ke', 'county-official', '', ''],
                ['Invalid Role', 'invalid-role@idmis.go.ke', 'super-admin', '', ''],
                ['Invalid Portfolio', 'portfolio@idmis.go.ke', 'assessor', '001', '999'],
            ]),
            'users',
            'Approved user access roster',
            'IAM-ROSTER-2026-01',
        );

        $this->assertSame('validation_failed', $batch->status);
        $this->assertSame(3, $batch->invalid_rows);
        $this->assertContains('email_already_exists', $batch->rows[0]->validation_errors);
        $this->assertContains('valid_home_county_required', $batch->rows[0]->validation_errors);
        $this->assertContains('invalid_role', $batch->rows[1]->validation_errors);
        $this->assertContains('home_county_not_allowed_for_role', $batch->rows[2]->validation_errors);
        $this->assertContains('valid_assigned_counties_required', $batch->rows[2]->validation_errors);
    }

    /** @param list<list<string>> $rows */
    private function approvedBatch(User $submitter, User $reviewer, string $datasetType, array $rows): DataMigrationBatch
    {
        $batch = app(StageReferenceDataImport::class)->handle(
            $submitter,
            $this->csv($datasetType, $rows),
            $datasetType,
            $datasetType.' authoritative registry',
            'SDD-'.str($datasetType)->upper().'-2026',
        );

        return app(ReviewHistoricalDataMigration::class)->handle($batch, $reviewer, 'approve', 'All rows and authority references were independently reconciled.');
    }

    /** @param list<list<string>> $rows */
    private function csv(string $datasetType, array $rows): UploadedFile
    {
        $lines = [implode(',', StageReferenceDataImport::HEADERS[$datasetType])];
        foreach ($rows as $row) {
            $stream = fopen('php://temp', 'r+');
            $this->assertIsResource($stream);
            fputcsv($stream, $row);
            rewind($stream);
            $lines[] = rtrim((string) stream_get_contents($stream));
            fclose($stream);
        }

        return UploadedFile::fake()->createWithContent("{$datasetType}.csv", implode("\n", $lines));
    }

    /** @param list<list<string>> $rows */
    private function xlsx(string $datasetType, array $rows, bool $secondSheet = false, bool $externalLink = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'idmis-import-test-');
        $this->assertIsString($path);
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(StageReferenceDataImport::HEADERS[$datasetType]));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        if ($secondSheet) {
            $writer->addNewSheetAndMakeItCurrent();
            $writer->addRow(Row::fromValues(['unexpected']));
        }
        $writer->close();
        if ($externalLink) {
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($path) === true);
            $this->assertTrue($archive->addFromString('xl/externalLinks/externalLink1.xml', '<externalLink/>'));
            $archive->close();
        }

        return new UploadedFile(
            $path,
            "{$datasetType}.xlsx",
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
