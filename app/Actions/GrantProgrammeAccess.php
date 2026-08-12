<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\ProgrammeAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class GrantProgrammeAccess
{
    public function __construct(
        private ProgrammeAuthorization $authorization,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array{name: string, email: string, role: string, county_id?: string|null, assigned_county_ids?: list<string>} $data */
    public function handle(array $data, User $actor, bool $sendSetup = true): User
    {
        $user = DB::transaction(function () use ($data): User {
            $role = UserRole::from($data['role']);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::password(40)),
                'email_verified_at' => now(),
                'county_id' => in_array($role, [UserRole::CountyOfficial, UserRole::CountyAdmin]) ? ($data['county_id'] ?? null) : null,
            ]);
            $this->authorization->assignRole($user, $role);
            $user->assignedCounties()->sync($role->hasAssignedCountyScope() ? ($data['assigned_county_ids'] ?? []) : []);

            return $user;
        });

        if ($sendSetup) {
            $this->sendAccessSetup($user);
        }
        $this->auditLogger->record($actor, $user, 'access.granted', "Programme access granted to {$user->email}.", $user->county_id, ['role' => $data['role'], 'assigned_county_ids' => $data['assigned_county_ids'] ?? []]);

        return $user;
    }

    public function sendAccessSetup(User $user): void
    {
        $user->notify(new ProgrammeAlert('IDMIS access granted', 'An administrator has created your IDMIS profile. Use the password-reset message to establish your password.', 'access'));
        Password::sendResetLink(['email' => $user->email]);
    }
}
