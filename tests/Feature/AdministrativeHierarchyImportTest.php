<?php

namespace Tests\Feature;

use App\Actions\ApplyHistoricalDataMigration;
use App\Actions\ReviewHistoricalDataMigration;
use App\Actions\StageReferenceDataImport;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministrativeHierarchyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_governed_sub_county_and_ward_imports_create_a_checksummed_hierarchy(): void
    {
        Storage::fake('local');
        $county = County::factory()->create(['code' => 1]);
        $submitter = User::factory()->platformAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();
        $sourceChecksum = hash('sha256', 'official-administrative-units-register');
        $subCounty = $this->apply(
            $submitter,
            $reviewer,
            $applier,
            'sub_counties',
            [['001', 'SC-001-01', 'Mvita', '2022-08-09', '', $sourceChecksum, '', '']],
        );
        $this->assertSame($county->id, SubCounty::query()->sole()->county_id);

        $this->apply(
            User::factory()->platformAdmin()->create(),
            User::factory()->platformAdmin()->create(),
            User::factory()->platformAdmin()->create(),
            'wards',
            [['SC-001-01', 'WD-001-01-01', 'Mji wa Kale/Makadara', '2022-08-09', '', $sourceChecksum, '', '']],
        );

        $ward = Ward::query()->with('subCounty.county')->sole();
        $this->assertSame('Mvita', $ward->subCounty->name);
        $this->assertSame($county->id, $ward->subCounty->county->id);
        $this->assertSame($sourceChecksum, $ward->source_checksum_sha256);
        $release = ReferenceDataRelease::query()->latest('version')->firstOrFail();
        $this->assertSame($ward->id, data_get($release->snapshot, 'wards.0.id'));
        $this->assertSame($subCounty->id, data_get($release->snapshot, 'sub_counties.0.id'));
    }

    public function test_unknown_parent_and_unverifiable_source_are_rejected_during_staging(): void
    {
        Storage::fake('local');
        $actor = User::factory()->platformAdmin()->create();
        $batch = app(StageReferenceDataImport::class)->handle(
            $actor,
            $this->csv('wards', [['UNKNOWN', 'WD-999', 'Unknown ward', '2022-08-09', '', 'bad-checksum', '{}', '']]),
            'wards',
            'Unreconciled ward register',
            'UNVERIFIED-WARDS',
        );

        $this->assertSame('validation_failed', $batch->status);
        $this->assertEqualsCanonicalizing(['unknown_sub_county_code', 'invalid_source_checksum'], $batch->rows->sole()->validation_errors);
    }

    /** @param list<list<string>> $rows */
    private function apply(User $submitter, User $reviewer, User $applier, string $datasetType, array $rows): SubCounty|Ward
    {
        $batch = app(StageReferenceDataImport::class)->handle($submitter, $this->csv($datasetType, $rows), $datasetType, 'Independent Electoral and Boundaries Commission', 'IEBC-ADMINISTRATIVE-UNITS-2022');
        $batch = app(ReviewHistoricalDataMigration::class)->handle($batch, $reviewer, 'approve', 'Parent keys, source checksum and effective dates independently reconciled.');
        app(ApplyHistoricalDataMigration::class)->handle($batch, $applier);

        return $datasetType === 'sub_counties' ? SubCounty::query()->latest()->firstOrFail() : Ward::query()->latest()->firstOrFail();
    }

    /** @param list<list<string>> $rows */
    private function csv(string $datasetType, array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'r+');
        $this->assertIsResource($stream);
        fputcsv($stream, StageReferenceDataImport::HEADERS[$datasetType]);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent("{$datasetType}.csv", $content);
    }
}
