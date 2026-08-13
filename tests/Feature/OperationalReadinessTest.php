<?php

namespace Tests\Feature;

use App\Jobs\CreateOperationalBackupJob;
use App\Jobs\VerifyOperationalBackupJob;
use App\Models\OperationalAlert;
use App\Models\OperationalAlertEvent;
use App\Models\OperationalBackup;
use App\Models\PerformanceTestRun;
use App\Models\QueueRecoveryAttempt;
use App\Models\ReleaseRecord;
use App\Models\ServiceLevelMeasurement;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\OperationalReadinessCheck;
use App\Services\PostgreSqlBackupManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_readiness_probe_checks_dependencies_without_disclosing_details(): void
    {
        $this->getJson(route('health.ready'))->assertOk()->assertJsonPath('status', 'ready')->assertJsonCount(6, 'checks')->assertJsonFragment(['name' => 'search_indexes', 'status' => 'pass'])->assertJsonFragment(['name' => 'document_malware_scanner', 'status' => 'pass'])->assertJsonStructure(['status', 'checkedAt', 'checks' => [['name', 'status', 'latencyMs']]]);
        $this->assertSame([], Storage::disk('local')->allFiles('operations/readiness'));
    }

    public function test_readiness_detects_cache_outage_and_recovers_after_the_store_is_restored(): void
    {
        $cacheManager = app('cache');
        $originalDriver = $cacheManager->getDefaultDriver();
        config()->set('cache.stores.failure-injection', ['driver' => 'database', 'connection' => 'missing']);
        $cacheManager->setDefaultDriver('failure-injection');

        $failed = app(OperationalReadinessCheck::class)->run();
        $this->assertFalse($failed['ready']);
        $this->assertSame('fail', $failed['checks']['cache']['status']);

        $cacheManager->forgetDriver('failure-injection');
        $cacheManager->setDefaultDriver($originalDriver);
        $recovered = app(OperationalReadinessCheck::class)->run();
        $this->assertTrue($recovered['ready']);
        $this->assertSame('pass', $recovered['checks']['cache']['status']);
    }

    public function test_readiness_detects_missing_search_index_and_recovers_after_recreation(): void
    {
        DB::statement('DROP INDEX public.document_extractions_text_search_idx');

        $failed = app(OperationalReadinessCheck::class)->run();
        $this->assertFalse($failed['ready']);
        $this->assertSame('fail', $failed['checks']['search_indexes']['status']);
        $this->assertStringContainsString('document_extractions_text_search_idx', $failed['checks']['search_indexes']['detail']);

        DB::statement("CREATE INDEX document_extractions_text_search_idx ON public.document_extractions USING gin (to_tsvector('simple'::regconfig, COALESCE(extracted_text, ''::text)))");
        $recovered = app(OperationalReadinessCheck::class)->run();
        $this->assertTrue($recovered['ready']);
        $this->assertSame('pass', $recovered['checks']['search_indexes']['status']);
    }

    public function test_production_readiness_fails_closed_on_the_development_signature_gate(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('repository.security.malware_scanner', 'signature');

        $this->getJson(route('health.ready'))
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonFragment(['name' => 'document_malware_scanner', 'status' => 'fail'])
            ->assertJsonMissing(['detail' => 'Production document scanning requires the approved ClamAV scanner.']);
    }

    public function test_release_evidence_requires_independent_validation_and_supports_controlled_rollback(): void
    {
        $deployer = User::factory()->platformAdmin()->create();
        $validator = User::factory()->platformAdmin()->create();
        $versionOne = $this->releasePayload('2026.8.1', str_repeat('1', 40), str_repeat('a', 64));
        $this->actingAs($deployer)->post(route('operations.releases.store'), $versionOne)->assertRedirect();
        $releaseOne = ReleaseRecord::query()->sole();
        $this->assertTrue(Str::isUuid($releaseOne->id));
        $this->actingAs($deployer)->patch(route('operations.releases.validate', [$releaseOne]), ['evidence' => 'Attempted self-validation.'])->assertForbidden();
        $this->actingAs($validator)->patch(route('operations.releases.validate', [$releaseOne]), ['evidence' => 'Smoke, migration, authorization and rollback-readiness checks passed.'])->assertRedirect();
        $this->assertSame('validated', $releaseOne->refresh()->status);

        $this->actingAs($deployer)->post(route('operations.releases.store'), $this->releasePayload('2026.8.2', str_repeat('2', 40), str_repeat('b', 64)))->assertRedirect();
        $releaseTwo = ReleaseRecord::query()->where('version', '2026.8.2')->sole();
        $this->actingAs($validator)->patch(route('operations.releases.validate', [$releaseTwo]), ['evidence' => 'Independent post-deployment checks passed.'])->assertRedirect();
        $this->actingAs($validator)->patch(route('operations.releases.rollback', [$releaseTwo]), ['rollback_to_version' => 'missing-version', 'reason' => 'Invalid target test.'])->assertStatus(409);
        $this->actingAs($validator)->patch(route('operations.releases.rollback', [$releaseTwo]), ['rollback_to_version' => '2026.8.1', 'reason' => 'Controlled rollback rehearsal after simulated release-health regression.'])->assertRedirect();
        $this->assertSame('rolled_back', $releaseTwo->refresh()->status);
        $this->assertSame('2026.8.1', $releaseTwo->rollback_to_version);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $releaseTwo->id, 'action' => 'operations.release.rolled_back']);
    }

    public function test_operations_workspace_measurements_backup_jobs_and_exports_are_governed(): void
    {
        Queue::fake();
        Notification::fake();
        $operator = User::factory()->platformAdmin()->create();
        $viewer = User::factory()->topManagement()->create();
        $performanceRun = PerformanceTestRun::factory()->create();
        $this->assertSame(0, Artisan::call('operations:measure'));
        $this->assertSame(10, ServiceLevelMeasurement::query()->count());
        $alert = OperationalAlert::query()->sole();
        $this->assertSame('database', $alert->service);
        $this->assertSame('backup_age', $alert->metric);
        $this->assertSame('critical', $alert->severity);
        $this->assertSame('open', $alert->status);
        $this->assertSame(1, $alert->occurrence_count);
        $this->assertSame(64, mb_strlen($alert->evidence_checksum));
        $this->assertDatabaseHas('operational_alert_events', ['operational_alert_id' => $alert->id, 'event_type' => 'opened']);
        Notification::assertSentToTimes($operator, ProgrammeAlert::class, 1);

        $this->actingAs($viewer)->get(route('operations.index'))->assertOk()->assertInertia(fn ($page) => $page->where('readiness.ready', true)->where('capabilities.manage', false)->has('measurements', 10)->where('operationalAlerts.total', 1)->where('operationalAlerts.data.0.id', $alert->id)->where('operationalAlerts.data.0.status', 'open')->where('operationalAlerts.data.0.eventCount', 1)->has('operationalAlerts.data.0.events', 1)->where('performanceRuns.total', 1)->where('performanceRuns.data.0.id', $performanceRun->id)->where('performanceRuns.data.0.p95LatencyMs', '450.000')->where('performanceRuns.data.0.evidenceChecksum', $performanceRun->evidence_checksum));
        $acknowledgement = ['note' => 'Database backup freshness breach assigned to the operations lead for immediate remediation.'];
        $this->actingAs($viewer)->patch(route('operations.alerts.acknowledge', [$alert]), $acknowledgement)->assertForbidden();
        $this->actingAs($operator)->patch(route('operations.alerts.acknowledge', [$alert]), $acknowledgement)->assertRedirect();
        $this->assertSame('acknowledged', $alert->refresh()->status);
        $this->assertSame($operator->id, $alert->acknowledged_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $alert->id, 'action' => 'operations.alert.acknowledged']);

        $this->assertSame(0, Artisan::call('operations:measure'));
        $this->assertSame('acknowledged', $alert->refresh()->status);
        $this->assertSame(2, $alert->occurrence_count);
        $this->assertDatabaseCount('operational_alerts', 1);
        $this->assertDatabaseHas('operational_alert_events', ['operational_alert_id' => $alert->id, 'event_type' => 'repeated']);
        Notification::assertSentToTimes($operator, ProgrammeAlert::class, 1);
        $this->actingAs($viewer)->post(route('operations.backups.store'))->assertForbidden();
        $this->actingAs($operator)->post(route('operations.backups.store'))->assertRedirect();
        Queue::assertPushed(CreateOperationalBackupJob::class, fn ($job) => $job->userId === $operator->id);

        $backup = OperationalBackup::create(['initiated_by' => $operator->id, 'reference' => 'BKP-TEST-001', 'disk' => 'local', 'path' => 'operations/backups/test.dump', 'database_name' => 'devolution_mis_test', 'format' => 'postgres_custom', 'sha256' => str_repeat('c', 64), 'size_bytes' => 1024, 'status' => 'completed', 'started_at' => now()->subMinute(), 'completed_at' => now()]);
        $this->assertSame(0, Artisan::call('operations:measure'));
        $this->assertSame('recovered', $alert->refresh()->status);
        $this->assertNotNull($alert->recovered_at);
        $this->assertDatabaseHas('operational_alert_events', ['operational_alert_id' => $alert->id, 'event_type' => 'recovered']);
        Notification::assertSentToTimes($operator, ProgrammeAlert::class, 2);
        $this->actingAs($operator)->post(route('operations.backups.verify', [$backup]))->assertRedirect();
        Queue::assertPushed(VerifyOperationalBackupJob::class, fn ($job) => $job->backupId === $backup->id && $job->restoreProbe);
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($viewer)->get(route('workspace.export', ['operations', $format]))->assertOk()->assertDownload();
            $this->actingAs($viewer)->get(route('workspace.export', ['operational-alerts', $format]))->assertOk()->assertDownload();
        }
    }

    public function test_backup_verification_failures_follow_the_active_locale(): void
    {
        $backup = OperationalBackup::create([
            'reference' => 'BKP-LOCALE-001',
            'disk' => 'local',
            'path' => 'operations/backups/locale.dump',
            'database_name' => 'devolution_mis_test',
            'format' => 'postgres_custom',
            'status' => 'running',
            'started_at' => now(),
        ]);
        app()->setLocale('fr');

        try {
            app(PostgreSqlBackupManager::class)->verify($backup);
            $this->fail('An incomplete backup must not enter verification.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame(__('operations.backup.errors.completed_required'), $exception->getMessage());
        }

        $english = require lang_path('en/operations.php');
        $kiswahili = require lang_path('sw/operations.php');
        $french = require lang_path('fr/operations.php');

        $this->assertSame(array_keys($english['backup']['errors']), array_keys($kiswahili['backup']['errors']));
        $this->assertSame(array_keys($english['backup']['errors']), array_keys($french['backup']['errors']));
    }

    public function test_operations_outcomes_follow_the_user_locale_and_catalogs_remain_in_parity(): void
    {
        Queue::fake();
        $operator = User::factory()->platformAdmin()->create();
        $operator->localePreference()->updateOrCreate([], ['locale' => 'fr']);

        $this->actingAs($operator)
            ->withSession(['locale' => 'fr'])
            ->post(route('operations.backups.store'))
            ->assertRedirect()
            ->assertSessionHas('success', 'La sauvegarde de la base de données a été mise en file.');

        $englishKeys = array_keys(Arr::dot(require lang_path('en/operations.php')));
        sort($englishKeys);
        foreach (['sw', 'fr'] as $locale) {
            $localizedKeys = array_keys(Arr::dot(require lang_path("{$locale}/operations.php")));
            sort($localizedKeys);
            $this->assertSame($englishKeys, $localizedKeys, "Operations catalog keys differ for {$locale}.");
        }
    }

    public function test_operational_alert_event_history_and_alert_deletion_are_database_immutable(): void
    {
        $alert = OperationalAlert::factory()->create();
        $event = OperationalAlertEvent::factory()->create(['operational_alert_id' => $alert->id, 'measurement_id' => $alert->latest_measurement_id]);

        try {
            $event->update(['narrative' => 'Attempted rewrite.']);
            $this->fail('Operational alert events must reject updates.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(QueryException::class);
        $alert->delete();
    }

    public function test_failed_queue_jobs_are_minimized_requeued_and_retain_immutable_recovery_evidence(): void
    {
        $operator = User::factory()->platformAdmin()->create();
        $viewer = User::factory()->topManagement()->create();
        $failedJobUuid = (string) Str::uuid();
        $payload = json_encode(['uuid' => $failedJobUuid, 'displayName' => 'App\\Jobs\\GenerateScheduledReport', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'attempts' => 3, 'data' => ['commandName' => 'App\\Jobs\\GenerateScheduledReport', 'command' => 'protected-serialized-command']], JSON_THROW_ON_ERROR);
        DB::table('failed_jobs')->insert(['uuid' => $failedJobUuid, 'connection' => 'database', 'queue' => 'reports', 'payload' => $payload, 'exception' => 'RuntimeException: Simulated protected report failure at /private/path.php:10', 'failed_at' => now()->subMinutes(10)]);

        $this->actingAs($viewer)->post(route('operations.failed-jobs.retry', [$failedJobUuid]))->assertForbidden();
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $failedJobUuid]);
        $this->actingAs($operator)->get(route('operations.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('failedJobs.total', 1)
            ->where('failedJobs.data.0.jobName', 'App\\Jobs\\GenerateScheduledReport')
            ->where('failedJobs.data.0.exceptionCategory', 'RuntimeException')
            ->missing('failedJobs.data.0.payload')
            ->missing('failedJobs.data.0.exception'));

        $this->actingAs($operator)->post(route('operations.failed-jobs.retry', [$failedJobUuid]))->assertRedirect();
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $failedJobUuid]);
        $this->assertDatabaseHas('jobs', ['queue' => 'reports', 'attempts' => 0]);
        $attempt = QueueRecoveryAttempt::query()->sole();
        $this->assertSame('requeued', $attempt->outcome);
        $this->assertSame($operator->id, $attempt->initiated_by);
        $this->assertSame(64, strlen($attempt->payload_checksum));
        $this->assertSame(64, strlen($attempt->exception_checksum));
        $this->assertSame(64, strlen($attempt->evidence_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $attempt->id, 'action' => 'operations.queue.recovery_attempted']);

        $this->expectException(QueryException::class);
        $attempt->update(['outcome' => 'retry_failed']);
    }

    public function test_queue_recovery_fails_closed_for_missing_or_non_transactional_provider_jobs(): void
    {
        $operator = User::factory()->platformAdmin()->create();
        $failedJobUuid = (string) Str::uuid();
        $payload = json_encode(['uuid' => $failedJobUuid, 'displayName' => 'App\\Jobs\\ExternalProviderJob', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []], JSON_THROW_ON_ERROR);
        DB::table('failed_jobs')->insert(['uuid' => $failedJobUuid, 'connection' => 'sync', 'queue' => 'external', 'payload' => $payload, 'exception' => 'RuntimeException: External provider failure', 'failed_at' => now()->subMinute()]);

        $this->actingAs($operator)->post(route('operations.failed-jobs.retry', [(string) Str::uuid()]))->assertNotFound();
        $this->actingAs($operator)->post(route('operations.failed-jobs.retry', [$failedJobUuid]))->assertStatus(409);
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $failedJobUuid]);
        $this->assertDatabaseCount('queue_recovery_attempts', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    /** @return array<string, mixed> */
    private function releasePayload(string $version, string $gitSha, string $artifactChecksum): array
    {
        return ['version' => $version, 'git_sha' => $gitSha, 'environment' => 'pilot', 'artifact_checksum' => $artifactChecksum, 'change_reference' => 'CHG-IDMIS-2026-001', 'migration_batch' => 1, 'deployed_at' => now()->subMinute()->toIso8601String(), 'notes' => 'Reproducible pilot artifact deployment.'];
    }
}
