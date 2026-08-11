<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AccessReviewCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $launched_by
 * @property string|null $reviewer_id
 * @property string $reference
 * @property string $name
 * @property string $scope
 * @property list<string> $role_scope
 * @property string $status
 * @property CarbonImmutable $period_from
 * @property CarbonImmutable $period_to
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable $launched_at
 * @property CarbonImmutable|null $completed_at
 * @property int $item_count
 * @property int $retained_count
 * @property int $revoked_count
 * @property int $remediation_count
 * @property string|null $evidence_checksum
 * @property User|null $launcher
 * @property User|null $reviewer
 */
#[Fillable(['launched_by', 'reviewer_id', 'reference', 'name', 'scope', 'role_scope', 'status', 'period_from', 'period_to', 'due_at', 'launched_at', 'completed_at', 'item_count', 'retained_count', 'revoked_count', 'remediation_count', 'evidence_checksum'])]
class AccessReviewCampaign extends Model
{
    /** @use HasFactory<AccessReviewCampaignFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'open', 'item_count' => 0, 'retained_count' => 0, 'revoked_count' => 0, 'remediation_count' => 0];

    protected function casts(): array
    {
        return ['role_scope' => 'array', 'period_from' => 'date', 'period_to' => 'date', 'due_at' => 'immutable_datetime', 'launched_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'item_count' => 'integer', 'retained_count' => 'integer', 'revoked_count' => 'integer', 'remediation_count' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function launcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'launched_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return HasMany<AccessReviewItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AccessReviewItem::class);
    }
}
