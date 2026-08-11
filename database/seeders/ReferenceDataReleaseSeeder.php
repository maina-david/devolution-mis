<?php

namespace Database\Seeders;

use App\Actions\CreateReferenceDataRelease;
use App\Actions\PublishReferenceDataRelease;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class ReferenceDataReleaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publishedReleaseCount = ReferenceDataRelease::query()->count();

        if (! app()->isLocal() || $publishedReleaseCount >= 2) {
            return;
        }

        $submitter = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $approver = User::query()->where('email', 'platform.admin@idmis.test')->first();

        if (! $submitter || ! $approver) {
            throw new RuntimeException('Local access profiles must be seeded before the foundation reference-data release.');
        }

        Sector::query()->firstOrCreate(
            ['code' => 'WASH'],
            [
                'name' => 'Water, sanitation and irrigation',
                'description' => 'Water security and county service delivery.',
                'is_active' => true,
            ],
        );

        $isFoundationRelease = $publishedReleaseCount === 0;
        $release = app(CreateReferenceDataRelease::class)->handle($submitter, $isFoundationRelease
            ? 'Foundation publication of county and sector reference data required by governed IDMIS project workflows.'
            : 'Comprehensive publication of county, organization, sector, programme and county-coverage reference data.');

        app(PublishReferenceDataRelease::class)->handle($release, $approver, [
            'approval_reference' => $isFoundationRelease ? 'REFDATA-CCB-2026-FOUNDATION' : 'REFDATA-CCB-2026-001',
            'effective_from' => $isFoundationRelease ? '2026-07-31' : '2026-08-01',
        ]);
    }
}
