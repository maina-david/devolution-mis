<?php

namespace Tests\Feature;

use App\Models\AccessDelegation;
use App\Models\Assessment;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\ReferenceDataRelease;
use App\Models\ReferenceLineageDisposition;
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

    public function test_applied_retain_and_deprecate_decisions_leave_the_unresolved_inventory_while_rejected_records_return_to_it(): void
    {
        $county = County::factory()->create();
        $assessments = Assessment::factory()
            ->count(5)
            ->sequence(
                ['cycle' => '2018/19 ACPA'],
                ['cycle' => '2019/20 ACPA'],
                ['cycle' => '2020/21 ACPA'],
                ['cycle' => '2021/22 ACPA'],
                ['cycle' => '2022/23 ACPA'],
            )
            ->create(['county_id' => $county->id, 'reference_data_release_id' => null]);

        [$unassigned, $pending, $retained, $deprecated, $rejected] = $assessments->all();

        ReferenceLineageDisposition::factory()->create(['record_type' => 'assessment', 'record_id' => $pending->id, 'status' => 'approved']);
        ReferenceLineageDisposition::factory()->create(['record_type' => 'assessment', 'record_id' => $retained->id, 'decision' => 'retain_legacy', 'status' => 'applied']);
        ReferenceLineageDisposition::factory()->create(['record_type' => 'assessment', 'record_id' => $deprecated->id, 'decision' => 'deprecate', 'status' => 'applied']);
        ReferenceLineageDisposition::factory()->create(['record_type' => 'assessment', 'record_id' => $rejected->id, 'status' => 'rejected']);

        $inventory = app(LegacyReferenceInventory::class);
        $report = $inventory->report();
        $assessments = collect($report['records'])->firstWhere('key', 'assessment');

        $this->assertIsArray($assessments);
        $this->assertSame(3, $report['total']);
        $this->assertSame(3, $assessments['count']);
        $this->assertSame(2, $assessments['available']);
        $this->assertSame(1, $assessments['pending']);
        $this->assertSame(2, $assessments['applied']);
        $this->assertEqualsCanonicalizing([$unassigned->id, $rejected->id], collect($inventory->candidates('assessment'))->pluck('id')->all());
    }

    public function test_it_includes_unpinned_access_delegations_in_the_controlled_inventory(): void
    {
        $delegation = AccessDelegation::factory()->create(['reference_data_release_id' => null]);

        $inventory = app(LegacyReferenceInventory::class);
        $record = collect($inventory->report()['records'])->firstWhere('key', 'access_delegation');

        $this->assertIsArray($record);
        $this->assertSame(trans('migration.lineage_types.access_delegation'), $record['type']);
        $this->assertSame(1, $record['count']);
        $this->assertSame($delegation->id, $inventory->candidates('access_delegation')[0]['id']);
        $this->assertEquals($delegation->county_scope_snapshot, $inventory->candidates('access_delegation')[0]['snapshot']['county_scope_snapshot']);
    }
}
