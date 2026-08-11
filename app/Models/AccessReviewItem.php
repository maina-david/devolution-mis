<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AccessReviewItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $access_review_campaign_id
 * @property string|null $user_id
 * @property string|null $reviewed_by
 * @property string|null $reinstated_by
 * @property string $role_name
 * @property list<string> $permission_snapshot
 * @property string|null $home_county_id
 * @property list<array{kind: string, id: string, code: int, name: string, logoUrl: string|null, logoSourceAuthority: string|null, logoVerifiedAt: string|null}> $assigned_county_snapshot
 * @property bool $mfa_enabled
 * @property bool $passkey_enabled
 * @property CarbonImmutable|null $last_authenticated_at
 * @property string $decision
 * @property string|null $rationale
 * @property string|null $remediation_action
 * @property CarbonImmutable|null $remediation_due_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $revoked_at
 * @property int $sessions_revoked
 * @property CarbonImmutable|null $reinstated_at
 * @property string|null $reinstatement_rationale
 * @property AccessReviewCampaign $campaign
 * @property User|null $user
 * @property User|null $reviewer
 * @property User|null $reinstater
 * @property County|null $homeCounty
 */
#[Fillable(['access_review_campaign_id', 'user_id', 'reviewed_by', 'reinstated_by', 'role_name', 'permission_snapshot', 'home_county_id', 'assigned_county_snapshot', 'mfa_enabled', 'passkey_enabled', 'last_authenticated_at', 'decision', 'rationale', 'remediation_action', 'remediation_due_at', 'reviewed_at', 'revoked_at', 'sessions_revoked', 'reinstated_at', 'reinstatement_rationale'])]
class AccessReviewItem extends Model
{
    /** @use HasFactory<AccessReviewItemFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['mfa_enabled' => false, 'passkey_enabled' => false, 'decision' => 'pending', 'sessions_revoked' => 0];

    protected function casts(): array
    {
        return ['permission_snapshot' => 'array', 'assigned_county_snapshot' => 'array', 'mfa_enabled' => 'boolean', 'passkey_enabled' => 'boolean', 'last_authenticated_at' => 'immutable_datetime', 'remediation_due_at' => 'date', 'reviewed_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'sessions_revoked' => 'integer', 'reinstated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AccessReviewCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AccessReviewCampaign::class, 'access_review_campaign_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reinstater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reinstated_by');
    }

    /** @return BelongsTo<County, $this> */
    public function homeCounty(): BelongsTo
    {
        return $this->belongsTo(County::class, 'home_county_id');
    }
}
