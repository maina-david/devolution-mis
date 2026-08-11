<?php

namespace Database\Factories;

use App\Models\PartnerCollaborationAction;
use App\Models\PartnerCollaborationActionUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCollaborationActionUpdate>
 */
class PartnerCollaborationActionUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_collaboration_action_id' => PartnerCollaborationAction::factory(), 'progress_percentage' => 50, 'narrative' => fake()->paragraph(), 'submitted_by' => User::factory(), 'submitted_at' => now(), 'evidence_checksum' => null, 'update_checksum' => fake()->sha256(),
        ];
    }
}
