<?php

namespace Database\Factories;

use App\Models\AccessReviewCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessReviewCampaign>
 */
class AccessReviewCampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'launched_by' => User::factory(),
            'reviewer_id' => User::factory(),
            'reference' => fake()->unique()->bothify('ACR-2026-Q#-###'),
            'name' => 'Quarterly privileged access certification',
            'scope' => 'Review all privileged national, county and independent-verification access.',
            'role_scope' => ['county-admin', 'assessor', 'top-management', 'devolution-admin', 'platform-admin'],
            'status' => 'open',
            'period_from' => now()->subQuarter()->startOfQuarter(),
            'period_to' => now()->subQuarter()->endOfQuarter(),
            'due_at' => now()->addDays(21),
            'launched_at' => now(),
        ];
    }
}
