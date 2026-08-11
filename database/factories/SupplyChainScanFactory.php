<?php

namespace Database\Factories;

use App\Models\SupplyChainScan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplyChainScan>
 */
class SupplyChainScanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'environment' => 'testing',
            'source_revision' => str_repeat('a', 40),
            'source_state' => 'clean',
            'composer_lock_checksum' => hash('sha256', 'composer-lock'),
            'javascript_lock_checksum' => hash('sha256', 'package-lock'),
            'javascript_lockfile' => 'package-lock.json',
            'composer_component_count' => 80,
            'javascript_component_count' => 250,
            'composer_advisory_count' => 0,
            'npm_info_count' => 0,
            'npm_low_count' => 0,
            'npm_moderate_count' => 0,
            'npm_high_count' => 0,
            'npm_critical_count' => 0,
            'finding_codes' => [],
            'tool_versions' => ['composer' => '2.8.0', 'npm' => '11.17.0'],
            'sbom_format' => 'CycloneDX',
            'sbom_spec_version' => '1.5',
            'disk' => 'local',
            'path' => 'security/sbom/test.cdx.json',
            'mime_type' => 'application/vnd.cyclonedx+json',
            'size_bytes' => 1024,
            'artifact_checksum' => hash('sha256', 'sbom'),
            'outcome' => 'pass',
            'initiated_by_name' => 'system:supply-chain-scan',
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
            'evidence_checksum' => hash('sha256', Str::uuid()->toString()),
        ];
    }
}
