<?php

namespace Database\Factories;

use App\Models\PartnerCollaborationPlan;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCollaborationPlan>
 */
class PartnerCollaborationPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_profile_id' => PartnerProfile::factory(), 'reference' => fake()->unique()->bothify('COLLAB-####-???'), 'title' => fake()->sentence(4), 'objective' => fake()->paragraph(), 'starts_on' => today(), 'ends_on' => today()->addMonths(6), 'status' => 'draft', 'created_by' => User::factory(),
        ];
    }
}
