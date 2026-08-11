<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PartnerCollaborationPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $partner_profile_id
 * @property string $reference
 * @property string $title
 * @property string $objective
 * @property string $status
 * @property string $created_by
 * @property string|null $submitted_by
 * @property string|null $approved_by
 * @property string|null $decision_note
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $approved_at
 * @property-read PartnerProfile $partner
 * @property-read User $creator
 * @property-read Collection<int, PartnerCollaborationAction> $actions
 */
#[Fillable(['partner_profile_id', 'reference', 'title', 'objective', 'starts_on', 'ends_on', 'status', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'decision_note'])]
class PartnerCollaborationPlan extends Model
{
    /** @use HasFactory<PartnerCollaborationPlanFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PartnerProfile, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PartnerCollaborationAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(PartnerCollaborationAction::class);
    }
}
