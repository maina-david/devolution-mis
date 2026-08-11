<?php

namespace App\Models;

use Database\Factories\PartnerOperationalAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $partner_profile_id
 * @property string|null $county_id
 * @property string $alert_type
 * @property string $severity
 * @property string $fingerprint
 * @property string $summary
 * @property Carbon|null $due_on
 * @property string $status
 * @property Carbon $detected_at
 * @property Carbon|null $notified_at
 * @property string|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $resolution
 * @property-read PartnerProfile $partner
 * @property-read County|null $county
 * @property-read User|null $resolver
 */
#[Fillable(['partner_profile_id', 'county_id', 'subject_type', 'subject_id', 'alert_type', 'severity', 'fingerprint', 'summary', 'due_on', 'status', 'detected_at', 'notified_at', 'resolved_by', 'resolved_at', 'resolution'])]
class PartnerOperationalAlert extends Model
{
    /** @use HasFactory<PartnerOperationalAlertFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['due_on' => 'immutable_date', 'detected_at' => 'immutable_datetime', 'notified_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PartnerProfile, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
