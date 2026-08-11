<?php

namespace Database\Seeders;

use App\Models\County;
use Illuminate\Database\Seeder;

class CountySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $identityJson = file_get_contents(database_path('data/county-identity.json'));
        if ($identityJson === false) {
            throw new \RuntimeException('The county identity registry could not be read.');
        }

        /** @var array<int, array<string, mixed>> $identities */
        $identities = json_decode($identityJson, true, flags: JSON_THROW_ON_ERROR);
        $counties = [
            [1, 'Mombasa', 'Coast', 67, 87], [2, 'Kwale', 'Coast', 59, 91], [3, 'Kilifi', 'Coast', 69, 78], [4, 'Tana River', 'Coast', 72, 61], [5, 'Lamu', 'Coast', 84, 59], [6, 'Taita-Taveta', 'Coast', 55, 80],
            [7, 'Garissa', 'North Eastern', 76, 46], [8, 'Wajir', 'North Eastern', 76, 27], [9, 'Mandera', 'North Eastern', 81, 10], [10, 'Marsabit', 'Eastern', 55, 16], [11, 'Isiolo', 'Eastern', 57, 35], [12, 'Meru', 'Eastern', 57, 43], [13, 'Tharaka-Nithi', 'Eastern', 57, 49], [14, 'Embu', 'Eastern', 56, 55], [15, 'Kitui', 'Eastern', 61, 65], [16, 'Machakos', 'Eastern', 52, 67], [17, 'Makueni', 'Eastern', 52, 75], [18, 'Nyandarua', 'Central', 43, 51], [19, 'Nyeri', 'Central', 48, 48], [20, 'Kirinyaga', 'Central', 52, 51], [21, "Murang'a", 'Central', 48, 56], [22, 'Kiambu', 'Central', 47, 62], [23, 'Turkana', 'Rift Valley', 31, 13], [24, 'West Pokot', 'Rift Valley', 30, 29], [25, 'Samburu', 'Rift Valley', 45, 31], [26, 'Trans Nzoia', 'Rift Valley', 27, 38], [27, 'Uasin Gishu', 'Rift Valley', 31, 45], [28, 'Elgeyo-Marakwet', 'Rift Valley', 35, 40], [29, 'Nandi', 'Rift Valley', 29, 51], [30, 'Baringo', 'Rift Valley', 40, 43], [31, 'Laikipia', 'Rift Valley', 45, 40], [32, 'Nakuru', 'Rift Valley', 40, 57], [33, 'Narok', 'Rift Valley', 34, 67], [34, 'Kajiado', 'Rift Valley', 45, 75], [35, 'Kericho', 'Rift Valley', 31, 58], [36, 'Bomet', 'Rift Valley', 31, 64], [37, 'Kakamega', 'Western', 20, 48], [38, 'Vihiga', 'Western', 22, 53], [39, 'Bungoma', 'Western', 20, 39], [40, 'Busia', 'Western', 13, 49], [41, 'Siaya', 'Nyanza', 16, 57], [42, 'Kisumu', 'Nyanza', 22, 59], [43, 'Homa Bay', 'Nyanza', 19, 68], [44, 'Migori', 'Nyanza', 23, 75], [45, 'Kisii', 'Nyanza', 28, 68], [46, 'Nyamira', 'Nyanza', 29, 63], [47, 'Nairobi', 'Nairobi', 49, 65],
        ];

        foreach ($counties as [$code, $name, $region, $mapX, $mapY]) {
            $identity = $identities[$code];
            County::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'slug' => str($name)->slug(),
                'region' => $region,
                'logo_path' => $identity['path'],
                'logo_source_url' => $identity['source_url'],
                'official_website_url' => $identity['official_website_url'],
                'logo_source_authority' => $identity['source_authority'],
                'logo_source_kind' => $identity['source_kind'],
                'logo_checksum_sha256' => $identity['checksum_sha256'],
                'logo_source_checksum_sha256' => $identity['source_checksum_sha256'],
                'logo_verified_at' => $identity['verified_at'],
                'map_x' => $mapX,
                'map_y' => $mapY,
            ]);
        }
    }
}
