<?php

namespace Database\Factories;

use App\Models\AuditAssuranceRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditAssuranceRun>
 */
class AuditAssuranceRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $artifactChecksum = hash('sha256', Str::uuid()->toString());

        return ['environment' => 'testing', 'outcome' => 'pass', 'event_count' => 10, 'verified_event_count' => 10, 'legacy_event_count' => 0, 'mismatch_count' => 0, 'first_event_id' => Str::uuid()->toString(), 'last_event_id' => Str::uuid()->toString(), 'first_event_hash' => hash('sha256', 'first'), 'last_event_hash' => hash('sha256', 'last'), 'chain_root_checksum' => hash('sha256', 'root'), 'finding_codes' => [], 'disk' => 'local', 'path' => 'audit/assurance/test.json', 'mime_type' => 'application/json', 'size_bytes' => 1024, 'artifact_checksum' => $artifactChecksum, 'signature_algorithm' => 'hmac-sha256', 'signing_key_reference' => 'test-key', 'signature' => hash_hmac('sha256', $artifactChecksum, 'test-secret'), 'initiated_by_name' => 'system:audit-assurance', 'started_at' => now()->subSecond(), 'completed_at' => now(), 'evidence_checksum' => hash('sha256', Str::uuid()->toString())];
    }
}
