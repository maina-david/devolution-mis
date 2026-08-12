<?php

namespace App\Models;

use Database\Factories\UserPageViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property string $id
 * @property string $user_id
 * @property string|null $user_activity_session_id
 * @property string $route_name
 * @property string $path
 * @property string $page_title
 * @property string|null $ip_address
 * @property Carbon $viewed_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'user_activity_session_id', 'route_name', 'path', 'page_title', 'ip_address', 'user_agent', 'viewed_at'])]
class UserPageView extends Model
{
    /** @use HasFactory<UserPageViewFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<UserActivitySession, $this> */
    public function activitySession(): BelongsTo
    {
        return $this->belongsTo(UserActivitySession::class, 'user_activity_session_id');
    }

    protected function casts(): array
    {
        return ['viewed_at' => 'immutable_datetime'];
    }
}
