<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SupportTicketActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $support_ticket_id
 * @property string|null $actor_id
 * @property string $actor_name
 * @property string $activity_type
 * @property string $from_status
 * @property string $to_status
 * @property string $narrative
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $occurred_at
 * @property string $evidence_checksum
 */
#[Fillable(['support_ticket_id', 'actor_id', 'actor_name', 'activity_type', 'from_status', 'to_status', 'narrative', 'metadata', 'occurred_at', 'evidence_checksum'])]
class SupportTicketActivity extends Model
{
    /** @use HasFactory<SupportTicketActivityFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['narrative' => 'encrypted', 'metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
