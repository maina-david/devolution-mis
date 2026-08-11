<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Programme;
use App\Models\ProgrammeCountyCoverage;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProgrammeCountyCoverageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $programme = Programme::query()->where('code', 'KDSP-II')->first();
        $administrator = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $counties = County::query()->orderBy('code')->get();

        if (! $programme || ! $administrator || $counties->count() !== 47) {
            throw new RuntimeException('KDSP II, the Devolution administrator and all 47 governed counties must exist before seeding programme coverage.');
        }

        foreach ($counties as $county) {
            ProgrammeCountyCoverage::query()->updateOrCreate([
                'programme_id' => $programme->id,
                'county_id' => $county->id,
                'starts_on' => '2024-07-01',
            ], [
                'implementation_lead_id' => null,
                'created_by' => $administrator->id,
                'ends_on' => '2028-06-30',
                'status' => 'active',
                'funding_allocation' => null,
                'currency' => $programme->currency,
                'source_reference' => "IDMIS-TOR-KDSP-II-COUNTY-{$county->code}",
                'notes' => 'ToR-backed KDSP II county coverage baseline; county-specific allocation and accountable implementation lead remain unasserted until approved source data is supplied.',
            ]);
        }
    }
}
