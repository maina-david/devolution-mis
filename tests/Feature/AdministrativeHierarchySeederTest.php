<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\AdministrativeHierarchySeeder;
use Database\Seeders\CountySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdministrativeHierarchySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_controlled_snapshot_seeds_the_complete_iebc_hierarchy_with_lineage(): void
    {
        $this->seed([CountySeeder::class, AdministrativeHierarchySeeder::class]);

        $this->assertDatabaseCount('counties', 47);
        $this->assertDatabaseCount('sub_counties', 290);
        $this->assertDatabaseCount('wards', 1450);
        $this->assertSame(47, SubCounty::query()->distinct()->count('county_id'));
        $this->assertSame(290, Ward::query()->distinct()->count('sub_county_id'));

        $mombasa = County::query()->where('code', 1)->firstOrFail();
        $this->assertSame(6, $mombasa->subCounties()->count());
        $this->assertSame(30, Ward::query()->whereHas('subCounty', fn ($query) => $query->where('county_id', $mombasa->id))->count());

        $mjiWaKale = Ward::query()->where('name', 'Mji Wa Kale/Makadara')->with('subCounty.county')->firstOrFail();
        $this->assertSame('Mvita', $mjiWaKale->subCounty->name);
        $this->assertSame('constituency', $mjiWaKale->subCounty->classification);
        $this->assertSame('Mombasa', $mjiWaKale->subCounty->county->name);
        $this->assertGreaterThan(0, $mjiWaKale->registered_voters_2022);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $mjiWaKale->source_checksum_sha256);
        $this->assertStringContainsString('Independent Electoral and Boundaries Commission', $mjiWaKale->source_authority);

        $administrator = User::factory()->platformAdmin()->create();
        $this->actingAs($administrator)
            ->get(route('reference-data.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reference-data/index')
                ->where('subCounties.total', 290)
                ->where('wards.total', 1450)
                ->where('subCounties.data.0.county.code', 1)
                ->where('wards.data.0.county.code', 1)
                ->has('localization.referenceData.administrative_hierarchy'));
    }

    public function test_hierarchy_seeding_is_idempotent_and_restores_governed_records(): void
    {
        $this->seed([CountySeeder::class, AdministrativeHierarchySeeder::class]);
        $ward = Ward::query()->firstOrFail();
        $wardId = $ward->id;
        $ward->delete();

        $this->seed(AdministrativeHierarchySeeder::class);

        $this->assertDatabaseCount('sub_counties', 290);
        $this->assertSame(1450, Ward::withTrashed()->count());
        $this->assertSame($wardId, Ward::query()->whereKey($wardId)->firstOrFail()->id);
    }
}
