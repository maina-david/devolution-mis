<?php

namespace Tests\Feature;

use App\Actions\RunSupplyChainScan;
use App\Models\SupplyChainScan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class SupplyChainAssuranceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_generates_lock_derived_cyclonedx_and_immutable_evidence(): void
    {
        Storage::fake('local');
        config()->set('security-governance.additional_javascript_lockfiles', []);
        $this->fakeCleanAudits();
        $actor = User::factory()->devolutionAdmin()->create();

        $scan = app(RunSupplyChainScan::class)->handle($actor);

        $this->assertTrue(Str::isUuid($scan->id));
        $this->assertSame('pass', $scan->outcome);
        $this->assertSame(0, $scan->composer_advisory_count);
        $this->assertSame(0, $scan->npm_high_count);
        $this->assertGreaterThan(0, $scan->composer_component_count);
        $this->assertGreaterThan(0, $scan->javascript_component_count);
        $this->assertNotNull($scan->path);
        Storage::disk('local')->assertExists((string) $scan->path);
        $artifact = Storage::disk('local')->get((string) $scan->path);
        $sbom = json_decode($artifact, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('CycloneDX', $sbom['bomFormat']);
        $this->assertSame('1.5', $sbom['specVersion']);
        $this->assertSame('urn:uuid:'.$scan->id, $sbom['serialNumber']);
        $this->assertCount($scan->composer_component_count + $scan->javascript_component_count, $sbom['components']);
        $this->assertSame(hash('sha256', $artifact), $scan->artifact_checksum);
        $this->assertSame(64, strlen($scan->evidence_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $scan->id, 'action' => 'security.supply-chain.scanned']);

        $this->expectException(QueryException::class);
        $scan->update(['outcome' => 'warn']);
    }

    public function test_authorized_viewer_can_review_and_download_only_checksum_valid_artifacts(): void
    {
        Storage::fake('local');
        $artifact = json_encode(['bomFormat' => 'CycloneDX', 'specVersion' => '1.5'], JSON_THROW_ON_ERROR);
        $path = 'security/sbom/assurance.cdx.json';
        Storage::disk('local')->put($path, $artifact);
        $scan = SupplyChainScan::factory()->create(['path' => $path, 'size_bytes' => strlen($artifact), 'artifact_checksum' => hash('sha256', $artifact)]);
        $viewer = User::factory()->devolutionAdmin()->create();

        $this->actingAs($viewer)
            ->get(route('security-governance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('security-governance/index')
                ->where('supplyChainScans.data.0.id', $scan->id)
                ->where('supplyChainScans.data.0.downloadable', true)
                ->where('supplyChainScans.data.0.artifactChecksum', hash('sha256', $artifact)));

        $this->actingAs($viewer)
            ->get(route('security-governance.supply-chain-scans.download', [$scan]))
            ->assertOk()
            ->assertDownload('idmis-'.$scan->id.'.cdx.json');
        $this->assertDatabaseHas('audit_events', ['subject_id' => $scan->id, 'action' => 'security.supply-chain.artifact-downloaded']);

        Storage::disk('local')->put($path, '{"tampered":true}');
        $this->actingAs($viewer)
            ->get(route('security-governance.supply-chain-scans.download', [$scan]))
            ->assertStatus(409);

        $countyUser = User::factory()->countyAdmin()->create();
        $this->actingAs($countyUser)
            ->get(route('security-governance.supply-chain-scans.download', [$scan]))
            ->assertForbidden();
    }

    public function test_scan_retains_dependency_failure_and_command_returns_failure(): void
    {
        Storage::fake('local');
        config()->set('security-governance.additional_javascript_lockfiles', []);
        $this->fakeCleanAudits(['info' => 0, 'low' => 0, 'moderate' => 0, 'high' => 2, 'critical' => 1, 'total' => 3]);

        $this->artisan('security:supply-chain-scan')
            ->expectsOutputToContain('fail')
            ->assertFailed();

        $scan = SupplyChainScan::query()->sole();
        $this->assertSame('fail', $scan->outcome);
        $this->assertSame(2, $scan->npm_high_count);
        $this->assertSame(1, $scan->npm_critical_count);
        $this->assertContains('npm_high_vulnerabilities_present', $scan->finding_codes);
        $this->assertContains('npm_critical_vulnerabilities_present', $scan->finding_codes);
        Storage::disk('local')->assertExists((string) $scan->path);
    }

    /** @param array{info: int, low: int, moderate: int, high: int, critical: int, total: int}|null $npmCounts */
    private function fakeCleanAudits(?array $npmCounts = null): void
    {
        Process::preventStrayProcesses();
        Process::fake(function ($process) use ($npmCounts) {
            $command = $process->command;
            if (! is_array($command)) {
                throw new RuntimeException('Unexpected string process command in supply-chain assurance test.');
            }

            return match (array_slice($command, 0, 2)) {
                ['composer', 'audit'] => Process::result(output: json_encode(['advisories' => [], 'abandoned' => []], JSON_THROW_ON_ERROR)),
                ['npm', 'audit'] => Process::result(output: json_encode(['metadata' => ['vulnerabilities' => $npmCounts ?? ['info' => 0, 'low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 0, 'total' => 0]]], JSON_THROW_ON_ERROR)),
                ['git', 'rev-parse'] => Process::result(output: str_repeat('a', 40)."\n"),
                ['git', 'status'] => Process::result(output: ''),
                ['composer', '--version'] => Process::result(output: 'Composer version 2.8.0'),
                ['npm', '--version'] => Process::result(output: '11.17.0'),
                default => throw new RuntimeException('Unexpected process command: '.implode(' ', $command)),
            };
        });
    }
}
