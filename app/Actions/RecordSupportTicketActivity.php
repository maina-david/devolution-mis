<?php

namespace App\Actions;

use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Support\Str;

class RecordSupportTicketActivity
{
    public function __construct(private CanonicalJson $canonicalJson) {}

    /** @param array<string, mixed> $metadata */
    public function handle(SupportTicket $ticket, ?User $actor, string $activityType, string $fromStatus, string $toStatus, string $narrative, array $metadata = []): SupportTicketActivity
    {
        $id = (string) Str::uuid7();
        $occurredAt = now()->startOfSecond();
        $evidence = [
            'id' => $id,
            'support_ticket_id' => $ticket->id,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'system:support-sla-monitor',
            'activity_type' => $activityType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'narrative' => $narrative,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt->toIso8601String(),
        ];

        return SupportTicketActivity::create([
            ...$evidence,
            'occurred_at' => $occurredAt,
            'evidence_checksum' => $this->canonicalJson->checksum($evidence),
        ]);
    }
}
