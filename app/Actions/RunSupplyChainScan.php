<?php

namespace App\Actions;

use App\Models\SupplyChainScan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RunSupplyChainScan
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(?User $initiator = null): SupplyChainScan
    {
        $id = (string) Str::uuid7();
        $startedAt = now();
        $timeout = (int) config('security-governance.scan_timeout_seconds', 120);
        $composerAuditResult = Process::timeout($timeout)->run(['composer', 'audit', '--format=json', '--no-interaction']);
        $npmAuditResult = Process::timeout($timeout)->run(['npm', 'audit', '--json', '--package-lock-only']);
        $gitRevisionResult = Process::timeout(15)->run(['git', 'rev-parse', 'HEAD']);
        $gitStatusResult = Process::timeout(15)->run(['git', 'status', '--porcelain']);
        $composerVersionResult = Process::timeout(15)->run(['composer', '--version', '--no-ansi']);
        $npmVersionResult = Process::timeout(15)->run(['npm', '--version']);

        $composerLock = $this->readJson(base_path('composer.lock'));
        $javascriptLock = $this->readJson(base_path('package-lock.json'));
        $composerAudit = $this->decode($composerAuditResult->output());
        $npmAudit = $this->decode($npmAuditResult->output());
        $findings = [];
        if ($composerLock === null) {
            $findings[] = 'composer_lock_missing_or_invalid';
        }
        if ($javascriptLock === null) {
            $findings[] = 'javascript_lock_missing_or_invalid';
        }
        if ($composerAudit === null) {
            $findings[] = 'composer_audit_failed';
        }
        if ($npmAudit === null) {
            $findings[] = 'npm_audit_failed';
        }
        foreach ((array) config('security-governance.additional_javascript_lockfiles', []) as $additionalLockfile) {
            if (is_string($additionalLockfile) && is_file(base_path($additionalLockfile))) {
                $findings[] = 'multiple_javascript_lockfiles';
                break;
            }
        }

        $sourceRevision = $gitRevisionResult->successful() && preg_match('/^[a-f0-9]{40}$/', trim($gitRevisionResult->output())) === 1 ? trim($gitRevisionResult->output()) : null;
        $sourceState = $sourceRevision === null ? 'unversioned' : (trim($gitStatusResult->output()) === '' ? 'clean' : 'dirty');
        if ($sourceState !== 'clean') {
            $findings[] = 'source_'.$sourceState;
        }

        $composerComponents = $this->composerComponents($composerLock);
        $javascriptComponents = $this->javascriptComponents($javascriptLock);
        $composerAdvisories = $this->composerAdvisoryCount($composerAudit);
        $npmCounts = $this->npmVulnerabilityCounts($npmAudit);
        if ($composerAdvisories > 0) {
            $findings[] = 'composer_advisories_present';
        }
        foreach (['critical', 'high', 'moderate', 'low', 'info'] as $severity) {
            if ($npmCounts[$severity] > 0) {
                $findings[] = "npm_{$severity}_vulnerabilities_present";
            }
        }
        $findings = array_values(array_unique($findings));

        $outcome = in_array('composer_audit_failed', $findings, true)
            || in_array('npm_audit_failed', $findings, true)
            || in_array('composer_lock_missing_or_invalid', $findings, true)
            || in_array('javascript_lock_missing_or_invalid', $findings, true)
            || $composerAdvisories > 0
            || $npmCounts['critical'] > 0
            || $npmCounts['high'] > 0 ? 'fail' : ($findings === [] ? 'pass' : 'warn');
        $completedAt = now();
        $composerChecksum = $this->fileChecksum(base_path('composer.lock'), 'missing-composer-lock');
        $javascriptChecksum = $this->fileChecksum(base_path('package-lock.json'), 'missing-package-lock');
        $toolVersions = [
            'php' => PHP_VERSION,
            'composer' => $composerVersionResult->successful() ? trim($composerVersionResult->output()) : 'unavailable',
            'npm' => $npmVersionResult->successful() ? trim($npmVersionResult->output()) : 'unavailable',
        ];
        $sbom = [
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.5',
            'serialNumber' => 'urn:uuid:'.$id,
            'version' => 1,
            'metadata' => [
                'timestamp' => $completedAt->toIso8601String(),
                'component' => ['type' => 'application', 'bom-ref' => 'pkg:generic/idmis@'.($sourceRevision ?? 'unversioned'), 'name' => 'Integrated Devolution Management Information System', 'version' => $sourceRevision ?? 'unversioned'],
                'properties' => [
                    ['name' => 'idmis:composer-lock-sha256', 'value' => $composerChecksum],
                    ['name' => 'idmis:javascript-lock-sha256', 'value' => $javascriptChecksum],
                    ['name' => 'idmis:source-state', 'value' => $sourceState],
                ],
            ],
            'components' => [...$composerComponents, ...$javascriptComponents],
        ];
        $artifact = json_encode($sbom, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $disk = (string) config('security-governance.sbom_disk', 'local');
        $path = trim((string) config('security-governance.sbom_path', 'security/sbom'), '/').'/'.$id.'.cdx.json';
        $stored = Storage::disk($disk)->put($path, $artifact);
        if (! $stored) {
            $outcome = 'fail';
            $findings[] = 'sbom_storage_failed';
            $path = null;
        }

        $evidence = [
            'id' => $id,
            'environment' => app()->environment(),
            'source_revision' => $sourceRevision,
            'source_state' => $sourceState,
            'composer_lock_checksum' => $composerChecksum,
            'javascript_lock_checksum' => $javascriptChecksum,
            'javascript_lockfile' => 'package-lock.json',
            'composer_component_count' => count($composerComponents),
            'javascript_component_count' => count($javascriptComponents),
            'composer_advisory_count' => $composerAdvisories,
            'npm_info_count' => $npmCounts['info'],
            'npm_low_count' => $npmCounts['low'],
            'npm_moderate_count' => $npmCounts['moderate'],
            'npm_high_count' => $npmCounts['high'],
            'npm_critical_count' => $npmCounts['critical'],
            'finding_codes' => array_values(array_unique($findings)),
            'tool_versions' => $toolVersions,
            'sbom_format' => 'CycloneDX',
            'sbom_spec_version' => '1.5',
            'disk' => $disk,
            'path' => $path,
            'mime_type' => 'application/vnd.cyclonedx+json',
            'size_bytes' => $stored ? strlen($artifact) : null,
            'artifact_checksum' => $stored ? hash('sha256', $artifact) : null,
            'outcome' => $outcome,
            'failure_category' => $outcome === 'fail' ? 'supply_chain_assurance_failed' : null,
            'initiated_by' => $initiator?->id,
            'initiated_by_name' => $initiator !== null ? $initiator->name : 'system:supply-chain-scan',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ];
        $scan = SupplyChainScan::create([...$evidence, 'evidence_checksum' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
        if ($initiator !== null) {
            $this->auditLogger->record($initiator, $scan, 'security.supply-chain.scanned', "Supply-chain scan {$scan->id} completed with {$scan->outcome} outcome.");
        }

        return $scan;
    }

    /** @return array<string, mixed>|null */
    private function readJson(string $path): ?array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        return is_string($contents) ? $this->decode($contents) : null;
    }

    private function fileChecksum(string $path, string $missingValue): string
    {
        if (! is_file($path)) {
            return hash('sha256', $missingValue);
        }

        $checksum = hash_file('sha256', $path);

        return is_string($checksum) ? $checksum : hash('sha256', $missingValue);
    }

    /** @return array<string, mixed>|null */
    private function decode(string $json): ?array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed>|null $lock
     * @return list<array<string, mixed>>
     */
    private function composerComponents(?array $lock): array
    {
        $packages = [
            ...(is_array($lock['packages'] ?? null) ? $lock['packages'] : []),
            ...(is_array($lock['packages-dev'] ?? null) ? $lock['packages-dev'] : []),
        ];

        return array_values(collect($packages)->filter(fn ($package): bool => is_array($package) && is_string($package['name'] ?? null) && is_string($package['version'] ?? null))->map(fn (array $package): array => ['type' => 'library', 'bom-ref' => 'pkg:composer/'.$package['name'].'@'.$package['version'], 'name' => $package['name'], 'version' => $package['version'], 'purl' => 'pkg:composer/'.$package['name'].'@'.rawurlencode($package['version'])])->all());
    }

    /** @param array<string, mixed>|null $lock
     * @return list<array<string, mixed>>
     */
    private function javascriptComponents(?array $lock): array
    {
        $packages = is_array($lock['packages'] ?? null) ? $lock['packages'] : [];
        $components = [];
        foreach ($packages as $path => $package) {
            if ($path === '' || ! is_array($package) || ! is_string($package['version'] ?? null)) {
                continue;
            }
            $name = is_string($package['name'] ?? null) ? $package['name'] : preg_replace('#^node_modules/#', '', (string) $path);
            if (! is_string($name) || $name === '') {
                continue;
            }
            $components[] = ['type' => 'library', 'bom-ref' => 'pkg:npm/'.rawurlencode($name).'@'.$package['version'], 'name' => $name, 'version' => $package['version'], 'purl' => 'pkg:npm/'.rawurlencode($name).'@'.rawurlencode($package['version'])];
        }

        return $components;
    }

    /** @param array<string, mixed>|null $audit */
    private function composerAdvisoryCount(?array $audit): int
    {
        $advisories = $audit['advisories'] ?? [];
        if (! is_array($advisories)) {
            return 0;
        }

        return array_is_list($advisories) ? count($advisories) : array_sum(array_map(fn ($items): int => is_array($items) ? count($items) : 0, $advisories));
    }

    /** @param array<string, mixed>|null $audit
     * @return array{info: int, low: int, moderate: int, high: int, critical: int}
     */
    private function npmVulnerabilityCounts(?array $audit): array
    {
        $counts = $audit['metadata']['vulnerabilities'] ?? [];

        return [
            'info' => is_array($counts) && is_numeric($counts['info'] ?? null) ? (int) $counts['info'] : 0,
            'low' => is_array($counts) && is_numeric($counts['low'] ?? null) ? (int) $counts['low'] : 0,
            'moderate' => is_array($counts) && is_numeric($counts['moderate'] ?? null) ? (int) $counts['moderate'] : 0,
            'high' => is_array($counts) && is_numeric($counts['high'] ?? null) ? (int) $counts['high'] : 0,
            'critical' => is_array($counts) && is_numeric($counts['critical'] ?? null) ? (int) $counts['critical'] : 0,
        ];
    }
}
