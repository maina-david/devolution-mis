<?php

namespace Database\Factories;

use App\Models\DataAsset;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataAsset>
 */
class DataAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data_owner_id' => User::factory(),
            'steward_id' => User::factory(),
            'code' => fake()->unique()->bothify('DA-###-??'),
            'name' => fake()->words(4, true),
            'description' => fake()->paragraph(),
            'module' => 'Devolution Performance Assessment',
            'authoritative_source' => 'State Department for Devolution',
            'classification' => 'confidential',
            'contains_personal_data' => true,
            'contains_sensitive_personal_data' => false,
            'personal_data_categories' => ['name', 'official_email'],
            'data_subject_categories' => ['county_officials'],
            'storage_locations' => ['postgresql', 'private_object_storage'],
            'residency_country' => ReferenceCatalogue::defaultCountryCode(),
            'quality_standard' => 'Complete, valid, timely and traceable to authoritative source',
            'status' => 'active',
            'reviewed_at' => now(),
        ];
    }
}
