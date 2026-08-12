<?php

namespace App\Actions;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionUserWorkspace
{
    public function handle(User $user, string $name): Team
    {
        return DB::transaction(function () use ($user, $name): Team {
            abort_if($user->teams()->exists(), 409, 'The account already has a provisioned workspace.');

            $team = Team::create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(substr($user->id, 0, 8)),
                'is_personal' => true,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            $user->switchTeam($team);

            return $team;
        });
    }
}
