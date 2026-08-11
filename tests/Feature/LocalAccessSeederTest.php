<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\CountySeeder;
use Database\Seeders\LocalAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocalAccessSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_access_seeder_creates_a_profile_for_every_programme_role(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->seed([CountySeeder::class, LocalAccessSeeder::class]);

        $users = User::query()->whereIn('email', [
            'county.official@idmis.test',
            'county.admin@idmis.test',
            'assessor@idmis.test',
            'partner@idmis.test',
            'management@idmis.test',
            'devolution.admin@idmis.test',
            'platform.admin@idmis.test',
        ])->get();

        $this->assertCount(count(UserRole::cases()), $users);
        $this->assertEqualsCanonicalizing(UserRole::cases(), $users->map->programmeRole()->all());
        $this->assertTrue($users->every(fn (User $user) => Hash::check('password', $user->password)));
    }

    public function test_seeded_profiles_have_the_expected_county_scopes(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->seed([CountySeeder::class, LocalAccessSeeder::class]);

        $countyOfficial = User::query()->where('email', 'county.official@idmis.test')->with('county')->firstOrFail();
        $countyAdmin = User::query()->where('email', 'county.admin@idmis.test')->with('county')->firstOrFail();
        $assessor = User::query()->where('email', 'assessor@idmis.test')->firstOrFail();
        $partner = User::query()->where('email', 'partner@idmis.test')->firstOrFail();
        $management = User::query()->where('email', 'management@idmis.test')->firstOrFail();

        $this->assertSame('Mombasa', $countyOfficial->county->name);
        $this->assertSame('Nairobi', $countyAdmin->county->name);
        $this->assertSame(10, $assessor->assignedCounties()->count());
        $this->assertSame(5, $partner->assignedCounties()->count());
        $this->assertSame(10, $management->assignedCounties()->count());
    }

    public function test_local_access_seeder_does_not_create_profiles_outside_local_environment(): void
    {
        $this->seed([CountySeeder::class, LocalAccessSeeder::class]);

        $this->assertSame(0, User::query()->where('email', 'like', '%@idmis.test')->count());
    }
}
