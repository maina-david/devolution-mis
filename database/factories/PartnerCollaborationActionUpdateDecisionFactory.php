<?php

namespace Database\Factories;

use App\Models\PartnerCollaborationActionUpdate;
use App\Models\PartnerCollaborationActionUpdateDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCollaborationActionUpdateDecision>
 */
class PartnerCollaborationActionUpdateDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_collaboration_action_update_id' => PartnerCollaborationActionUpdate::factory(), 'decision' => 'verified', 'verification_note' => fake()->sentence(10), 'verified_by' => User::factory(), 'verified_at' => now(), 'decision_checksum' => fake()->sha256(),
        ];
    }
}
