<?php

namespace App\Actions;

use App\Models\AuditAssuranceRun;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RunAuditAssurance
{
    public function __construct(private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    public function handle(?User $initiator = null): AuditAssuranceRun
    {
        $startedAt = now();
        $runId = Str::uuid7()->toString();
        $verification = DB::transaction(function (): array {
            DB::select('SELECT pg_advisory_xact_lock(?)', [730947]);

            return $this->verifyEvents();
        });
        $findingCodes = $verification['finding_codes'];
        $activeKeyReference = config('audit.active_signing_key');
        $signingKeys = config('audit.signing_keys', []);
        $signingKey = is_string($activeKeyReference) && is_array($signingKeys) ? ($signingKeys[$activeKeyReference] ?? null) : null;
        if (! is_string($activeKeyReference) || $activeKeyReference === '' || ! is_string($signingKey) || $signingKey === '') {
            $activeKeyReference = null;
            $signingKey = null;
            $findingCodes[] = 'signing_key_unavailable';
        }
        $findingCodes = array_values(array_unique($findingCodes));
        $outcome = $verification['mismatch_count'] > 0 ? 'fail' : ($findingCodes === [] ? 'pass' : 'warn');
        $completedAt = now();
        $manifest = [
            'schema' => 'idmis.audit-assurance.v1',
            'run_id' => $runId,
            'environment' => app()->environment(),
            'outcome' => $outcome,
            'covered_through_event_id' => $verification['last_event_id'],
            'covered_through_event_hash' => $verification['last_event_hash'],
            'event_count' => $verification['event_count'],
            'verified_event_count' => $verification['verified_event_count'],
            'legacy_event_count' => $verification['legacy_event_count'],
            'mismatch_count' => $verification['mismatch_count'],
            'chain_root_checksum' => $verification['chain_root_checksum'],
            'finding_codes' => $findingCodes,
            'mismatches' => $verification['mismatches'],
            'started_at' => $startedAt->toISOString(),
            'completed_at' => $completedAt->toISOString(),
        ];
        $artifact = $this->canonicalJson->encode($manifest);
        $artifactChecksum = hash('sha256', $artifact);
        $signature = $signingKey === null ? null : hash_hmac('sha256', $artifactChecksum, $signingKey);
        $disk = (string) config('audit.assurance_disk', 'local');
        $path = trim((string) config('audit.assurance_path', 'audit/assurance'), '/')."/{$runId}.json";
        $stored = Storage::disk($disk)->put($path, $artifact);
        if (! $stored) {
            $path = null;
            $findingCodes[] = 'artifact_storage_failed';
            $findingCodes = array_values(array_unique($findingCodes));
            $outcome = 'fail';
        }
        $evidence = [
            ...$manifest,
            'outcome' => $outcome,
            'finding_codes' => $findingCodes,
            'artifact_checksum' => $artifactChecksum,
            'signature_algorithm' => $signature === null ? null : 'hmac-sha256',
            'signing_key_reference' => $activeKeyReference,
            'signature' => $signature,
            'initiated_by' => $initiator?->id,
            'initiated_by_name' => $initiator instanceof User ? $initiator->name : 'system:audit-assurance',
        ];
        $run = AuditAssuranceRun::create([
            'id' => $runId,
            'environment' => app()->environment(),
            'outcome' => $outcome,
            'event_count' => $verification['event_count'],
            'verified_event_count' => $verification['verified_event_count'],
            'legacy_event_count' => $verification['legacy_event_count'],
            'mismatch_count' => $verification['mismatch_count'],
            'first_event_id' => $verification['first_event_id'],
            'last_event_id' => $verification['last_event_id'],
            'first_event_hash' => $verification['first_event_hash'],
            'last_event_hash' => $verification['last_event_hash'],
            'chain_root_checksum' => $verification['chain_root_checksum'],
            'finding_codes' => $findingCodes,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => 'application/json',
            'size_bytes' => $stored ? strlen($artifact) : null,
            'artifact_checksum' => $artifactChecksum,
            'signature_algorithm' => $signature === null ? null : 'hmac-sha256',
            'signing_key_reference' => $activeKeyReference,
            'signature' => $signature,
            'initiated_by' => $initiator?->id,
            'initiated_by_name' => $initiator instanceof User ? $initiator->name : 'system:audit-assurance',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'evidence_checksum' => $this->canonicalJson->checksum($evidence),
        ]);
        $this->auditLogger->record($initiator, $run, 'audit.assurance.completed', "Audit assurance run completed with outcome {$outcome}.", metadata: ['covered_through_event_id' => $run->last_event_id, 'event_count' => $run->event_count, 'mismatch_count' => $run->mismatch_count, 'evidence_checksum' => $run->evidence_checksum]);

        return $run;
    }

    /** @return array{event_count: int, verified_event_count: int, legacy_event_count: int, mismatch_count: int, first_event_id: string|null, last_event_id: string|null, first_event_hash: string|null, last_event_hash: string|null, chain_root_checksum: string, finding_codes: list<string>, mismatches: list<array{event_id: string, codes: list<string>}>} */
    private function verifyEvents(): array
    {
        $eventCount = 0;
        $verifiedEventCount = 0;
        $legacyEventCount = 0;
        $mismatchCount = 0;
        $firstEventId = null;
        $firstEventHash = null;
        $lastEventId = null;
        $lastEventHash = null;
        $expectedPreviousHash = null;
        $chainRoot = hash('sha256', '');
        $findingCodes = [];
        $mismatches = [];

        foreach (AuditEvent::query()->orderBy('occurred_at')->orderBy('id')->cursor() as $event) {
            $eventCount++;
            $firstEventId ??= $event->id;
            $firstEventHash ??= $event->event_hash;
            $codes = [];
            if (! hash_equals((string) ($expectedPreviousHash ?? ''), (string) ($event->previous_hash ?? ''))) {
                $codes[] = 'predecessor_mismatch';
            }
            if (! is_string($event->event_hash) || preg_match('/\A[0-9a-f]{64}\z/', $event->event_hash) !== 1) {
                $codes[] = 'missing_or_malformed_event_hash';
            }
            if ($event->hash_version === 2) {
                $expectedHash = $this->canonicalJson->checksum($this->eventHashPayload($event));
                if (! is_string($event->event_hash) || ! hash_equals($expectedHash, $event->event_hash)) {
                    $codes[] = 'event_hash_mismatch';
                } else {
                    $verifiedEventCount++;
                }
            } else {
                $legacyEventCount++;
                $findingCodes[] = 'legacy_hash_version';
            }
            if ($codes !== []) {
                $mismatchCount++;
                $findingCodes = [...$findingCodes, ...$codes];
                if (count($mismatches) < 100) {
                    $mismatches[] = ['event_id' => $event->id, 'codes' => $codes];
                }
            }
            $eventSnapshotChecksum = $this->canonicalJson->checksum(['id' => $event->id, ...$this->eventHashPayload($event), 'event_hash' => $event->event_hash]);
            $chainRoot = hash('sha256', $chainRoot.$eventSnapshotChecksum);
            $expectedPreviousHash = $event->event_hash;
            $lastEventId = $event->id;
            $lastEventHash = $event->event_hash;
        }

        if ($eventCount === 0) {
            $findingCodes[] = 'no_audit_events';
        }

        return ['event_count' => $eventCount, 'verified_event_count' => $verifiedEventCount, 'legacy_event_count' => $legacyEventCount, 'mismatch_count' => $mismatchCount, 'first_event_id' => $firstEventId, 'last_event_id' => $lastEventId, 'first_event_hash' => $firstEventHash, 'last_event_hash' => $lastEventHash, 'chain_root_checksum' => $chainRoot, 'finding_codes' => array_values(array_unique($findingCodes)), 'mismatches' => $mismatches];
    }

    /** @return array<string, mixed> */
    private function eventHashPayload(AuditEvent $event): array
    {
        if ($event->occurred_at === null) {
            throw new RuntimeException("Audit event {$event->id} has no occurrence timestamp.");
        }

        return ['actor_id' => $event->actor_id, 'county_id' => $event->county_id, 'subject_type' => $event->subject_type, 'subject_id' => $event->subject_id, 'action' => $event->action, 'description' => $event->description, 'metadata' => $event->metadata ?? [], 'ip_address' => $event->ip_address, 'occurred_at' => $event->occurred_at->toISOString(), 'previous_hash' => $event->previous_hash, 'hash_version' => $event->hash_version];
    }
}
