<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\SubCounty;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdministrativeHierarchySeeder extends Seeder
{
    private const EFFECTIVE_FROM = '2022-08-09';

    private const SOURCE_AUTHORITY = 'Independent Electoral and Boundaries Commission via CitizenGuide.KE';

    private const SOURCE_REFERENCE = 'IEBC 2022 ward register; CitizenGuide.KE refresh 2026-07-18; Gatundu North parent corrected against Kenya Gazette 2017-06-27 and Kiambu ADP 2025/26';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = file_get_contents(database_path('data/kenya-wards-iebc.json'));
        if ($json === false) {
            throw new RuntimeException('The controlled Kenya ward hierarchy snapshot could not be read.');
        }

        /** @var list<array{ward_code:string,name:string,constituency_name:string,county_name:string,registered_voters_2022:int}> $wards */
        $wards = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSnapshotCoverage($wards);

        $counties = County::query()->get()->keyBy(fn (County $county): string => $this->normalizeCountyName($county->name));

        DB::transaction(function () use ($wards, $counties): void {
            collect($wards)
                ->groupBy('county_name')
                ->sortKeys()
                ->each(function ($countyWards, string $sourceCountyName) use ($counties): void {
                    $county = $counties->get($this->normalizeCountyName($sourceCountyName));
                    if (! $county instanceof County) {
                        throw new RuntimeException("No canonical county matches {$sourceCountyName}.");
                    }

                    $countyWards->groupBy('constituency_name')->sortKeys()->values()->each(
                        function ($constituencyWards, int $index) use ($county): void {
                            /** @var array{constituency_name:string} $firstWard */
                            $firstWard = $constituencyWards->first();
                            $name = Str::title(Str::lower($firstWard['constituency_name']));
                            $code = sprintf('CST-%03d-%03d', $county->code, $index + 1);
                            $sourceChecksum = hash('sha256', (string) json_encode($constituencyWards->values()->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                            $subCounty = SubCounty::withTrashed()->firstOrNew([
                                'county_id' => $county->id,
                                'code' => $code,
                            ]);
                            $subCounty->fill([
                                'name' => $name,
                                'slug' => Str::slug($name),
                                'classification' => 'constituency',
                                'source_authority' => self::SOURCE_AUTHORITY,
                                'source_reference' => self::SOURCE_REFERENCE,
                                'source_checksum_sha256' => $sourceChecksum,
                                'boundary_geojson' => null,
                                'boundary_checksum_sha256' => null,
                                'effective_from' => self::EFFECTIVE_FROM,
                                'effective_to' => null,
                            ]);
                            $subCounty->deleted_at = null;
                            $subCounty->save();

                            $constituencyWards->sortBy('ward_code')->each(function (array $sourceWard) use ($subCounty): void {
                                $name = Str::title(Str::lower($sourceWard['name']));
                                $ward = Ward::withTrashed()->firstOrNew([
                                    'sub_county_id' => $subCounty->id,
                                    'code' => str_pad($sourceWard['ward_code'], 4, '0', STR_PAD_LEFT),
                                ]);
                                $ward->fill([
                                    'name' => $name,
                                    'slug' => Str::slug($name),
                                    'source_authority' => self::SOURCE_AUTHORITY,
                                    'source_reference' => self::SOURCE_REFERENCE,
                                    'source_checksum_sha256' => hash('sha256', (string) json_encode($sourceWard, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                                    'boundary_geojson' => null,
                                    'boundary_checksum_sha256' => null,
                                    'registered_voters_2022' => $sourceWard['registered_voters_2022'],
                                    'effective_from' => self::EFFECTIVE_FROM,
                                    'effective_to' => null,
                                ]);
                                $ward->deleted_at = null;
                                $ward->save();
                            });
                        },
                    );
                });
        });
    }

    /** @param list<array{ward_code:string,name:string,constituency_name:string,county_name:string,registered_voters_2022:int}> $wards */
    private function assertSnapshotCoverage(array $wards): void
    {
        $records = collect($wards);
        $countyCount = $records->pluck('county_name')->unique()->count();
        $constituencyCount = $records->map(fn (array $ward): string => $ward['county_name'].'|'.$ward['constituency_name'])->unique()->count();

        if ($records->count() !== 1450 || $countyCount !== 47 || $constituencyCount !== 290) {
            throw new RuntimeException("The controlled hierarchy snapshot is incomplete: {$records->count()} wards, {$constituencyCount} constituencies, {$countyCount} counties.");
        }
    }

    private function normalizeCountyName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replace(' county', '')
            ->ascii()
            ->replaceMatches('/[^a-z0-9]/', '')
            ->toString();
    }
}
