<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\SubCounty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubCounty>
 */
class SubCountyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city().' Sub-county';

        return [
            'county_id' => County::factory(),
            'code' => fake()->unique()->numerify('SC-####'),
            'name' => $name,
            'slug' => Str::slug($name),
            'source_authority' => 'Independent Electoral and Boundaries Commission',
            'source_reference' => 'Controlled administrative-unit fixture',
            'source_checksum_sha256' => hash('sha256', $name),
            'boundary_geojson' => null,
            'boundary_checksum_sha256' => null,
            'effective_from' => '2022-08-09',
            'effective_to' => null,
        ];
    }
}
