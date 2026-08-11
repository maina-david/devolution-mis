<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function __construct(private Request $request, private CanonicalJson $canonicalJson) {}

    /** @param array<string, mixed> $metadata */
    public function record(?User $actor, Model $subject, string $action, string $description, ?string $countyId = null, array $metadata = []): AuditEvent
    {
        return DB::transaction(function () use ($actor, $subject, $action, $description, $countyId, $metadata): AuditEvent {
            DB::select('SELECT pg_advisory_xact_lock(?)', [730947]);

            $occurredAt = now()->startOfSecond();
            $previousHash = AuditEvent::query()->latest('occurred_at')->latest('id')->value('event_hash');
            $hashPayload = [
                'actor_id' => $actor?->id,
                'county_id' => $countyId,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => (string) $subject->getKey(),
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata,
                'ip_address' => $this->request->ip(),
                'occurred_at' => $occurredAt->toISOString(),
                'previous_hash' => $previousHash,
                'hash_version' => 2,
            ];

            return AuditEvent::create([
                ...$hashPayload,
                'occurred_at' => $occurredAt,
                'event_hash' => $this->canonicalJson->checksum($hashPayload),
            ]);
        });
    }
}
