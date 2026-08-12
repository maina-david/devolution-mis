<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\County;
use App\Models\User;
use App\Services\ProgrammeAuthorization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'county_id' => null,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function countyOfficial(?County $county = null): static
    {
        return $this->state(fn () => ['county_id' => $county ? $county->id : County::factory()])
            ->afterCreating(fn (User $user) => app(ProgrammeAuthorization::class)->assignRole($user, UserRole::CountyOfficial));
    }

    public function countyAdmin(?County $county = null): static
    {
        return $this->state(fn () => ['county_id' => $county ? $county->id : County::factory()])
            ->afterCreating(fn (User $user) => app(ProgrammeAuthorization::class)->assignRole($user, UserRole::CountyAdmin));
    }

    public function assessor(): static
    {
        return $this->state(fn () => ['county_id' => null])
            ->afterCreating(fn (User $user) => app(ProgrammeAuthorization::class)->assignRole($user, UserRole::Assessor));
    }

    public function developmentPartner(): static
    {
        return $this->state(fn () => ['county_id' => null])
            ->afterCreating(fn (User $user) => app(ProgrammeAuthorization::class)->assignRole($user, UserRole::DevelopmentPartner));
    }

    public function topManagement(): static
    {
        return $this->state(fn () => ['county_id' => null])
            ->afterCreating(fn (User $user) => app(ProgrammeAuthorization::class)->assignRole($user, UserRole::TopManagement));
    }

    public function devolutionAdmin(): static
    {
        return $this->state(fn () => ['county_id' => null])
            ->afterCreating(fn (User $user) => app(ProgrammeAuthorization::class)->assignRole($user, UserRole::DevolutionAdmin));
    }

    public function platformAdmin(): static
    {
        return $this->state(fn () => ['county_id' => null])
            ->afterCreating(fn (User $user) => app(ProgrammeAuthorization::class)->assignRole($user, UserRole::PlatformAdmin));
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->roles()->doesntExist()) {
                app(ProgrammeAuthorization::class)->assignRole($user, UserRole::CountyOfficial);
            }
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
