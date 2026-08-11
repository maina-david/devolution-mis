<?php

namespace Database\Factories;

use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCalendarHoliday>
 */
class BusinessCalendarHolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_calendar_id' => BusinessCalendar::factory(),
            'holiday_date' => now()->addMonth()->toDateString(),
            'name' => fake()->words(3, true),
            'category' => 'public_holiday',
            'source_reference' => 'Kenya Gazette '.fake()->unique()->numerify('####'),
            'created_by' => User::factory(),
        ];
    }
}
