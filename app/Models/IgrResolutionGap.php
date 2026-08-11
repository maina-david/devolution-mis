<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IgrResolutionGapFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property CarbonImmutable $due_on */
#[Fillable(['igr_resolution_id', 'igr_gap_category_id', 'county_id', 'owner_user_id', 'title', 'description', 'impact', 'severity', 'status', 'due_on', 'mitigation_plan', 'resolution_note', 'reported_by', 'resolved_by', 'accepted_by', 'mitigation_started_at', 'resolved_at', 'accepted_at'])]
class IgrResolutionGap extends Model
{
    /** @use HasFactory<IgrResolutionGapFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['due_on' => 'immutable_date', 'mitigation_started_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<IgrResolution, $this> */
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(IgrResolution::class, 'igr_resolution_id');
    }

    /** @return BelongsTo<IgrGapCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(IgrGapCategory::class, 'igr_gap_category_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
