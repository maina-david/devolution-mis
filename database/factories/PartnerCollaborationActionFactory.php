<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\PartnerCollaborationAction;
use App\Models\PartnerCollaborationPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCollaborationAction>
 */
class PartnerCollaborationActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_collaboration_plan_id' => PartnerCollaborationPlan::factory(), 'county_id' => County::factory(), 'code' => fake()->unique()->bothify('ACT-###'), 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'accountable_user_id' => User::factory(), 'due_on' => today()->addMonth(), 'progress_percentage' => 0, 'status' => 'open', 'created_by' => User::factory(),
        ];
    }
}
