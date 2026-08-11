<?php

namespace App\Actions;

use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEvent;
use App\Models\User;
use Illuminate\Support\Str;

class RecordSecurityIncidentEvent
{
    public function handle(SecurityIncident $incident, ?User $actor, string $transition, string $fromStatus, string $toStatus, string $narrative, ?string $evidenceReference = null): SecurityIncidentEvent
    {
        $id = (string) Str::uuid7();
        $occurredAt = now();
        $evidence = ['id' => $id, 'security_incident_id' => $incident->id, 'actor_id' => $actor?->id, 'actor_name' => $actor === null ? 'system:security-incident-monitor' : $actor->name, 'transition' => $transition, 'from_status' => $fromStatus, 'to_status' => $toStatus, 'narrative' => $narrative, 'evidence_reference' => $evidenceReference, 'occurred_at' => $occurredAt->toIso8601String()];

        return SecurityIncidentEvent::create([...$evidence, 'occurred_at' => $occurredAt, 'evidence_checksum' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
    }
}
