<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SecurityIncidentEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $security_incident_id
 * @property string|null $actor_id
 * @property string $actor_name
 * @property string $transition
 * @property string $from_status
 * @property string $to_status
 * @property string $narrative
 * @property string|null $evidence_reference
 * @property CarbonImmutable $occurred_at
 * @property string $evidence_checksum
 */
#[Fillable(['security_incident_id', 'actor_id', 'actor_name', 'transition', 'from_status', 'to_status', 'narrative', 'evidence_reference', 'occurred_at', 'evidence_checksum'])]
class SecurityIncidentEvent extends Model
{
    /** @use HasFactory<SecurityIncidentEventFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['narrative' => 'encrypted', 'occurred_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SecurityIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'security_incident_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
