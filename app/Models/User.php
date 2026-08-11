<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use App\Enums\UserRole;
use App\Services\DelegatedAccessResolver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $county_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $access_revoked_at
 * @property string|null $access_revoked_by
 * @property string|null $access_revocation_reason
 * @property string|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 */
#[Fillable(['name', 'email', 'password', 'county_id', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasTeams, HasUuids, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable {
        HasTeams::teams insteadof HasRoles;
        HasRoles::teams as permissionTeams;
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsToMany<County, $this> */
    public function assignedCounties(): BelongsToMany
    {
        return $this->belongsToMany(County::class)->withTimestamps();
    }

    /** @return BelongsToMany<DswgWorkingGroup, $this> */
    public function dswgWorkingGroups(): BelongsToMany
    {
        return $this->belongsToMany(DswgWorkingGroup::class, 'dswg_working_group_user')->withPivot(['membership_role', 'status'])->withTimestamps();
    }

    /** @return MorphMany<DatabaseNotification, $this> */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    /** @return HasMany<UserActivitySession, $this> */
    public function activitySessions(): HasMany
    {
        return $this->hasMany(UserActivitySession::class);
    }

    public function canAccessCounty(County $county): bool
    {
        if ($this->programmeRole()->hasNationalScope()) {
            return true;
        }

        if (in_array($this->programmeRole(), [UserRole::CountyOfficial, UserRole::CountyAdmin])) {
            return $this->county_id === $county->id || app(DelegatedAccessResolver::class)->allowsCounty($this, $county->id);
        }

        return $this->assignedCounties()->whereKey($county)->exists() || app(DelegatedAccessResolver::class)->allowsCounty($this, $county->id);
    }

    public function programmeRole(): UserRole
    {
        return UserRole::from($this->getRoleNames()->firstOrFail());
    }

    /** @return list<string> */
    public function programmePermissionValues(): array
    {
        return array_values($this->getAllPermissions()
            ->map(fn (Permission $permission): string => $permission->name)
            ->merge(app(DelegatedAccessResolver::class)->permissionValues($this))
            ->unique()
            ->sort()
            ->values()
            ->all());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'access_revoked_at' => 'datetime',
        ];
    }
}
