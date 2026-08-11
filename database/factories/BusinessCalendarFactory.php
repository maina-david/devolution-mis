<?php

namespace Database\Factories;

use App\Models\BusinessCalendar;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCalendar>
 */
class BusinessCalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'KENYA-GOVERNMENT',
            'version' => 1,
            'name' => ReferenceCatalogue::defaultCountryName().' Government working calendar',
            'timezone' => ReferenceCatalogue::defaultTimezone(),
            'working_days' => [1, 2, 3, 4, 5],
            'workday_starts_at' => '08:00',
            'workday_ends_at' => '17:00',
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published', 'published_by' => User::factory(), 'published_at' => now(), 'checksum' => hash('sha256', fake()->uuid())]);
    }
}
