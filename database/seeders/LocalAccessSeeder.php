<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\County;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\ProgrammeAuthorization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalAccessSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(ProgrammeAuthorization $authorization): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $profiles = [
            ['name' => 'Amina Hassan', 'email' => 'county.official@idmis.test', 'role' => UserRole::CountyOfficial, 'home' => 'Mombasa', 'counties' => []],
            ['name' => 'Peter Mwangi', 'email' => 'county.admin@idmis.test', 'role' => UserRole::CountyAdmin, 'home' => 'Nairobi', 'counties' => []],
            ['name' => 'Grace Achieng', 'email' => 'assessor@idmis.test', 'role' => UserRole::Assessor, 'home' => null, 'counties' => ['Mombasa', 'Kwale', 'Kilifi', 'Tana River', 'Lamu', 'Taita-Taveta', 'Garissa', 'Wajir', 'Mandera', 'Marsabit']],
            ['name' => 'Brian Karanja', 'email' => 'assessor.central@idmis.test', 'role' => UserRole::Assessor, 'home' => null, 'counties' => ['Isiolo', 'Meru', 'Tharaka-Nithi', 'Embu', 'Kitui', 'Machakos', 'Makueni', 'Nyandarua', 'Nyeri', 'Kirinyaga']],
            ['name' => 'Mercy Wekesa', 'email' => 'assessor.rift@idmis.test', 'role' => UserRole::Assessor, 'home' => null, 'counties' => ["Murang'a", 'Kiambu', 'Turkana', 'West Pokot', 'Samburu', 'Trans Nzoia', 'Uasin Gishu', 'Elgeyo-Marakwet', 'Nandi', 'Baringo']],
            ['name' => 'Asha Noor', 'email' => 'assessor.western@idmis.test', 'role' => UserRole::Assessor, 'home' => null, 'counties' => ['Laikipia', 'Nakuru', 'Narok', 'Kajiado', 'Kericho', 'Bomet', 'Kakamega', 'Vihiga', 'Bungoma', 'Busia']],
            ['name' => 'Joseph Otieno', 'email' => 'assessor.nyanza@idmis.test', 'role' => UserRole::Assessor, 'home' => null, 'counties' => ['Siaya', 'Kisumu', 'Homa Bay', 'Migori', 'Kisii', 'Nyamira', 'Nairobi']],
            ['name' => 'Daniel Kiptoo', 'email' => 'partner@idmis.test', 'role' => UserRole::DevelopmentPartner, 'home' => null, 'counties' => ['Mombasa', 'Kwale', 'Kilifi', 'Lamu', 'Taita-Taveta']],
            ['name' => 'Dr. Faith Wanjiku', 'email' => 'management@idmis.test', 'role' => UserRole::TopManagement, 'home' => null, 'counties' => ['Nairobi', 'Kiambu', 'Nakuru', 'Mombasa', 'Kisumu', 'Uasin Gishu', 'Kakamega', 'Garissa', 'Turkana', 'Machakos']],
            ['name' => 'Samuel Mutua', 'email' => 'devolution.admin@idmis.test', 'role' => UserRole::DevolutionAdmin, 'home' => null, 'counties' => []],
            ['name' => 'IDMIS Platform Administrator', 'email' => 'platform.admin@idmis.test', 'role' => UserRole::PlatformAdmin, 'home' => null, 'counties' => []],
        ];

        foreach ($profiles as $profile) {
            $homeCountyId = $profile['home']
                ? County::query()->where('name', $profile['home'])->valueOrFail('id')
                : null;

            $user = User::withTrashed()->where('email', $profile['email'])->first();

            if ($user) {
                $user->restore();
                $user->update(['name' => $profile['name'], 'county_id' => $homeCountyId, 'password' => Hash::make('password'), 'email_verified_at' => now()]);
            } else {
                $user = User::factory()->create(['name' => $profile['name'], 'email' => $profile['email'], 'county_id' => $homeCountyId]);
            }

            $authorization->assignRole($user, $profile['role']);

            $countyIds = County::query()->whereIn('name', $profile['counties'])->pluck('id');
            $user->assignedCounties()->sync($countyIds);

            if ($user->notifications()->doesntExist()) {
                $user->notifyNow(new ProgrammeAlert(
                    title: 'Your IDMIS workspace is ready',
                    message: "Access has been provisioned for the {$profile['role']->label()} profile. Your dashboard and programme workspaces reflect the authorized county scope.",
                    category: 'access',
                ));
            }
        }
    }
}
