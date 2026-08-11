<?php

namespace App\Models;

use Database\Factories\UserActivitySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $team_id
 * @property string|null $current_route
 * @property string|null $current_path
 * @property string|null $current_page_title
 * @property string|null $last_method
 * @property string|null $last_action
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $logged_in_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $logged_out_at
 * @property-read User $user
 * @property-read Team|null $team
 */
#[Fillable(['user_id', 'session_fingerprint', 'team_id', 'ip_address', 'user_agent', 'current_route', 'current_path', 'current_page_title', 'last_method', 'last_action', 'logged_in_at', 'last_seen_at', 'logged_out_at'])]
class UserActivitySession extends Model
{
    /** @use HasFactory<UserActivitySessionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    protected function casts(): array
    {
        return ['logged_in_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime', 'logged_out_at' => 'immutable_datetime'];
    }
}
