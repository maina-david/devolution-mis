<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PartnerCollaborationAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $alert_type
 * @property string $severity
 * @property string $status
 * @property string $summary
 * @property string|null $resolution
 * @property CarbonImmutable|null $detected_at
 * @property-read PartnerProfile $primaryPartner
 * @property-read PartnerProfile $relatedPartner
 */
#[Fillable(['primary_partner_id', 'related_partner_id', 'alert_type', 'severity', 'scope_fingerprint', 'scope', 'summary', 'status', 'detected_at', 'resolved_by', 'resolved_at', 'resolution'])]
class PartnerCollaborationAlert extends Model
{
    /** @use HasFactory<PartnerCollaborationAlertFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['scope' => 'array', 'detected_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<PartnerProfile, $this> */
    public function primaryPartner(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'primary_partner_id');
    }

    /** @return BelongsTo<PartnerProfile, $this> */
    public function relatedPartner(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'related_partner_id');
    }
}
