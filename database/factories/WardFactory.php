<?php

namespace Database\Factories;

use App\Models\SubCounty;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->streetName().' Ward';

        return [
            'sub_county_id' => SubCounty::factory(),
            'code' => fake()->unique()->numerify('WD-#####'),
            'name' => $name,
            'slug' => Str::slug($name),
            'source_authority' => 'Independent Electoral and Boundaries Commission',
            'source_reference' => 'Controlled administrative-unit fixture',
            'source_checksum_sha256' => hash('sha256', $name),
            'boundary_geojson' => null,
            'boundary_checksum_sha256' => null,
            'registered_voters_2022' => fake()->numberBetween(3000, 50000),
            'effective_from' => '2022-08-09',
            'effective_to' => null,
        ];
    }
}
