<?php

namespace App\Models;

use App\Enums\SupportedLocale;
use Database\Factories\UserLocalePreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'locale'])]
class UserLocalePreference extends Model
{
    /** @use HasFactory<UserLocalePreferenceFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['locale' => SupportedLocale::class];
    }
}
