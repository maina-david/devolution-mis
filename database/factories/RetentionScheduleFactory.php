<?php

namespace Database\Factories;

use App\Models\RetentionSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetentionSchedule>
 */
class RetentionScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approved_by' => User::factory(),
            'code' => fake()->unique()->bothify('RET-###-??'),
            'record_class' => fake()->words(3, true),
            'trigger_event' => 'Closure of the associated programme cycle',
            'retention_months' => 84,
            'disposition_action' => 'review_then_destroy',
            'legal_authority' => 'Approved government records schedule reference pending',
            'legal_hold_rule' => 'Suspend disposition while any legal hold, audit or investigation is active.',
            'status' => 'approved',
            'effective_from' => now(),
            'approved_at' => now(),
            'next_review_at' => now()->addYear(),
        ];
    }
}
