<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Services\LegacyReferenceInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyReferenceInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_inventories_only_explicitly_unpinned_records_without_backfilling_them(): void
    {
        $county = County::factory()->create();
        $actor = User::factory()->create();
        $sector = Sector::factory()->create();
        $release = ReferenceDataRelease::factory()->create();
        Assessment::factory()->create(['county_id' => $county->id, 'cycle' => '2018/19 ACPA', 'reference_data_release_id' => null]);
        Assessment::factory()->create(['county_id' => $county->id, 'cycle' => '2019/20 ACPA', 'reference_data_release_id' => null]);
        Assessment::factory()->create(['county_id' => $county->id, 'cycle' => '2020/21 ACPA', 'reference_data_release_id' => $release->id]);
        DevolutionProject::factory()->create(['lead_county_id' => $county->id, 'sector_id' => $sector->id, 'created_by' => $actor->id, 'reference_data_release_id' => null]);

        $report = app(LegacyReferenceInventory::class)->report();

        $this->assertSame(3, $report['total']);
        $this->assertSame(2, $report['recordTypes']);
        $this->assertSame('Assessments', $report['records'][0]['type']);
        $this->assertSame(2, $report['records'][0]['count']);
        $this->assertSame(3, Assessment::query()->count());
        $this->assertSame(2, Assessment::query()->whereNull('reference_data_release_id')->count());
    }
}
