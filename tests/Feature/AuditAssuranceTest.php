<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\AuditAssuranceRun;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditAssuranceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('audit.assurance_disk', 'local');
        config()->set('audit.active_signing_key', 'audit-key-2026-01');
        config()->set('audit.signing_keys', ['audit-key-2026-01' => 'testing-audit-assurance-secret']);
    }

    public function test_national_manager_generates_signed_reproducible_immutable_assurance_evidence(): void
    {
        $manager = User::factory()->platformAdmin()->create();
        app(AuditLogger::class)->record($manager, $manager, 'test.audit-event.recorded', 'Reproducible audit event.', metadata: ['purpose' => 'assurance-test']);

        $this->actingAs($manager)->post(route('audit-assurance.store'))->assertRedirect()->assertSessionHas('success');

        $run = AuditAssuranceRun::query()->sole();
        $this->assertTrue(Str::isUuid($run->id));
        $this->assertSame('pass', $run->outcome);
        $this->assertSame(1, $run->event_count);
        $this->assertSame(1, $run->verified_event_count);
        $this->assertSame(0, $run->legacy_event_count);
        $this->assertSame(0, $run->mismatch_count);
        $this->assertSame('hmac-sha256', $run->signature_algorithm);
        $this->assertSame('audit-key-2026-01', $run->signing_key_reference);
        $this->assertSame(hash_hmac('sha256', (string) $run->artifact_checksum, 'testing-audit-assurance-secret'), $run->signature);
        Storage::disk('local')->assertExists((string) $run->path);
        $manifest = json_decode(Storage::disk('local')->get((string) $run->path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('idmis.audit-assurance.v1', $manifest['schema']);
        $this->assertSame($run->last_event_id, $manifest['covered_through_event_id']);
        $this->assertSame($run->chain_root_checksum, $manifest['chain_root_checksum']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $run->id, 'action' => 'audit.assurance.completed', 'hash_version' => 2]);

        $this->expectException(QueryException::class);
        $run->update(['outcome' => 'fail']);
    }

    public function test_legacy_and_invalid_hashes_are_reported_without_false_assurance(): void
    {
        $manager = User::factory()->platformAdmin()->create();
        config()->set('audit.active_signing_key', null);
        config()->set('audit.signing_keys', []);
        $legacy = $this->insertAuditEvent(null, hash('sha256', 'legacy'));
        $this->actingAs($manager)->post(route('audit-assurance.store'))->assertRedirect();
        $warning = AuditAssuranceRun::query()->sole();
        $this->assertSame('warn', $warning->outcome);
        $this->assertSame(1, $warning->legacy_event_count);
        $this->assertContains('legacy_hash_version', $warning->finding_codes);
        $this->assertContains('signing_key_unavailable', $warning->finding_codes);
        $this->assertNull($warning->signature);
        $this->assertSame($legacy->id, $warning->last_event_id);

        $previousHash = AuditEvent::query()->latest('occurred_at')->latest('id')->value('event_hash');
        $invalid = $this->insertAuditEvent(2, str_repeat('f', 64), $previousHash);
        $this->actingAs($manager)->post(route('audit-assurance.store'))->assertRedirect();
        $failed = AuditAssuranceRun::query()->latest('started_at')->firstOrFail();
        $this->assertSame('fail', $failed->outcome);
        $this->assertGreaterThanOrEqual(1, $failed->mismatch_count);
        $this->assertContains('event_hash_mismatch', $failed->finding_codes);
        $manifest = json_decode(Storage::disk('local')->get((string) $failed->path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($invalid->id, collect($manifest['mismatches'])->firstWhere('event_id', $invalid->id)['event_id']);

        $this->insertAuditEvent(null, null, $invalid->event_hash);
        $this->actingAs($manager)->post(route('audit-assurance.store'))->assertRedirect();
        $missingHash = AuditAssuranceRun::query()->latest('started_at')->firstOrFail();
        $this->assertSame('fail', $missingHash->outcome);
        $this->assertContains('missing_or_malformed_event_hash', $missingHash->finding_codes);
    }

    public function test_scope_download_integrity_exports_and_schedule_are_enforced(): void
    {
        $manager = User::factory()->platformAdmin()->create();
        $devolutionAdmin = User::factory()->devolutionAdmin()->create();
        $assessor = User::factory()->assessor()->create();
        $countyUser = User::factory()->countyOfficial(County::factory()->create())->create();
        app(AuditLogger::class)->record($manager, $manager, 'test.audit-event.recorded', 'Download assurance event.');
        $this->actingAs($manager)->post(route('audit-assurance.store'))->assertRedirect();
        $run = AuditAssuranceRun::query()->sole();

        $this->actingAs($devolutionAdmin)->get(route('audit-assurance.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('workspaceType', 'audit-assurance')
            ->where('workspace.rows.0.id', $run->id)
            ->where('workspace.rows.0.meta.artifactChecksum', $run->artifact_checksum)
            ->where('capabilities.run', true));
        $this->actingAs($assessor)->get(route('audit-assurance.index'))->assertForbidden();
        $this->actingAs($countyUser)->get(route('audit-assurance.index'))->assertForbidden();
        $this->actingAs($assessor)->get(route('audit-assurance.download', [$run]))->assertForbidden();
        $this->actingAs($manager)->get(route('audit-assurance.download', [$run]))->assertOk()->assertDownload("idmis-audit-assurance-{$run->id}.json");

        config()->set('audit.signing_keys', []);
        $this->actingAs($manager)->get(route('audit-assurance.download', [$run]))->assertStatus(409);
        config()->set('audit.signing_keys', ['audit-key-2026-01' => 'testing-audit-assurance-secret']);

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($manager)->get(route('workspace.export', ['audit-assurance', $format]))->assertOk()->assertDownload();
        }

        Storage::disk('local')->put((string) $run->path, 'tampered assurance evidence');
        $this->actingAs($manager)->get(route('audit-assurance.download', [$run]))->assertStatus(409);
        $events = collect(Schedule::events());
        $this->assertTrue($events->contains(fn ($event): bool => $event->description === 'Verify and retain checksum-bound audit-chain assurance evidence'));
        $this->assertSame(0, $this->artisan('audit:assure --user='.$manager->id)->run());
        $this->assertSame(2, $this->artisan('audit:assure --user='.Str::uuid())->run());
        $this->assertTrue($manager->can(ProgrammePermission::ManageSecurityGovernance->value));
    }

    private function insertAuditEvent(?int $hashVersion, ?string $eventHash, ?string $previousHash = null): AuditEvent
    {
        return AuditEvent::create(['actor_id' => null, 'county_id' => null, 'subject_type' => User::class, 'subject_id' => Str::uuid()->toString(), 'action' => 'test.inserted-event', 'description' => 'Controlled invalid or legacy audit fixture.', 'metadata' => [], 'ip_address' => '127.0.0.1', 'occurred_at' => now(), 'previous_hash' => $previousHash, 'event_hash' => $eventHash, 'hash_version' => $hashVersion]);
    }
}
