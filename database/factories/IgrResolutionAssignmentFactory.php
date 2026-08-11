<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\IgrResolution;
use App\Models\IgrResolutionAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IgrResolutionAssignment>
 */
class IgrResolutionAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['igr_resolution_id' => IgrResolution::factory(), 'user_id' => User::factory(), 'county_id' => County::factory(), 'responsibility_role' => 'lead', 'is_lead' => true, 'status' => 'active'];
    }
}
