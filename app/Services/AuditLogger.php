<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\UserActivitySession;
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
        $metadata = $this->requestMetadata($metadata);

        $event = DB::transaction(function () use ($actor, $subject, $action, $description, $countyId, $metadata): AuditEvent {
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

        $activitySessionId = $metadata['activity_session_id'] ?? null;
        if ($actor !== null && is_string($activitySessionId)) {
            UserActivitySession::query()->whereKey($activitySessionId)->where('user_id', $actor->id)->update(['last_action' => $action, 'last_seen_at' => now(), 'current_route' => $metadata['route_name'] ?? null, 'last_method' => $metadata['request_method'] ?? null]);
        }

        return $event;
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function requestMetadata(array $metadata): array
    {
        if (! $this->request->hasSession()) {
            return $metadata;
        }

        return [...$metadata, 'activity_session_id' => $this->request->session()->get('activity_session_id'), 'route_name' => $this->request->route()?->getName(), 'request_method' => $this->request->method()];
    }
}
