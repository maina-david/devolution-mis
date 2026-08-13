<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Services\DelegatedAccessResolver;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string|null $profile_photo_disk
 * @property string|null $profile_photo_path
 * @property string|null $profile_photo_mime_type
 * @property int|null $profile_photo_size_bytes
 * @property string|null $profile_photo_checksum
 * @property Carbon|null $profile_photo_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'county_id', 'profile_photo_disk', 'profile_photo_path', 'profile_photo_mime_type', 'profile_photo_size_bytes', 'profile_photo_checksum', 'profile_photo_updated_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'profile_photo_disk', 'profile_photo_path', 'profile_photo_mime_type', 'profile_photo_size_bytes', 'profile_photo_checksum'])]
class User extends Authenticatable implements HasLocalePreference, OAuthenticatable, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

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

    /** @return HasOne<UserLocalePreference, $this> */
    public function localePreference(): HasOne
    {
        return $this->hasOne(UserLocalePreference::class);
    }

    public function preferredLocale(): string
    {
        $preference = $this->relationLoaded('localePreference')
            ? $this->localePreference?->locale
            : $this->localePreference()->value('locale');

        return $preference instanceof \BackedEnum
            ? (string) $preference->value
            : (is_string($preference) ? $preference : (string) config('app.locale'));
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
            'profile_photo_size_bytes' => 'integer',
            'profile_photo_updated_at' => 'datetime',
        ];
    }
}
