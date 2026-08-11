<?php

namespace Database\Factories;

use App\Models\AccessReviewCampaign;
use App\Models\AccessReviewItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessReviewItem>
 */
class AccessReviewItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'access_review_campaign_id' => AccessReviewCampaign::factory(),
            'user_id' => User::factory()->withTwoFactor(),
            'role_name' => 'platform-admin',
            'permission_snapshot' => ['dashboard:view', 'user-access:manage'],
            'assigned_county_snapshot' => [],
            'mfa_enabled' => true,
            'passkey_enabled' => false,
            'decision' => 'pending',
        ];
    }
}
