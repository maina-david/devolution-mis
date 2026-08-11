<?php

namespace Database\Factories;

use App\Models\BusinessCalendar;
use App\Models\ServiceDeskPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceDeskPolicy>
 */
class ServiceDeskPolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'IDMIS-SUPPORT',
            'version' => 1,
            'name' => 'IDMIS operational support policy',
            'description' => 'Governed service-desk policy for test support operations.',
            'business_calendar_id' => BusinessCalendar::factory()->published(),
            'authority_status' => 'provisional',
            'approval_reference' => null,
            'categories' => [['code' => 'incident', 'name' => 'Service incident']],
            'channels' => ['web'],
            'priority_targets' => [
                'critical' => ['first_response' => 1, 'resolution' => 4, 'reminder' => 0.5],
                'high' => ['first_response' => 4, 'resolution' => 16, 'reminder' => 2],
                'medium' => ['first_response' => 8, 'resolution' => 40, 'reminder' => 4],
                'low' => ['first_response' => 16, 'resolution' => 80, 'reminder' => 8],
            ],
            'escalation_rules' => [['priority' => 'critical', 'stage' => 'resolution', 'tier' => 3]],
            'effective_from' => now()->startOfDay(),
            'effective_to' => null,
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
