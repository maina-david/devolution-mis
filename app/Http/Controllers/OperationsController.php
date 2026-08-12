<?php

namespace App\Http\Controllers;

use App\Actions\AcknowledgeOperationalAlert;
use App\Actions\RetryFailedQueueJob;
use App\Actions\RollbackRelease;
use App\Actions\ValidateRelease;
use App\Enums\ProgrammePermission;
use App\Http\Requests\AcknowledgeOperationalAlertRequest;
use App\Http\Requests\RetryFailedQueueJobRequest;
use App\Http\Requests\RollbackReleaseRequest;
use App\Http\Requests\StoreReleaseRecordRequest;
use App\Http\Requests\ValidateReleaseRequest;
use App\Http\Requests\WorkspaceIndexRequest;
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
use App\Services\AuditLogger;
use App\Services\OperationalReadinessCheck;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(WorkspaceIndexRequest $request, OperationalReadinessCheck $readiness): Response
    {
        Gate::authorize(ProgrammePermission::ViewOperations->value);
        $backups = OperationalBackup::query()->with(['initiator:id,name', 'restoreVerifier:id,name'])->when($request->filled('from'), fn (Builder $query) => $query->whereDate('started_at', '>=', $request->date('from')))->when($request->filled('to'), fn (Builder $query) => $query->whereDate('started_at', '<=', $request->date('to')))->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))->latest('started_at')->paginate($request->integer('per_page', 10))->withQueryString();
        $releases = ReleaseRecord::query()->with(['deployer:id,name', 'validator:id,name', 'rollbackActor:id,name'])->latest('deployed_at')->get();
        $measurements = ServiceLevelMeasurement::query()->where('observed_at', '>=', now()->subDay())->latest('observed_at')->get()->groupBy('metric')->map->first()->filter(fn ($measurement): bool => $measurement instanceof ServiceLevelMeasurement)->values();
        $failedJobs = DB::table(config('queue.failed.table', 'failed_jobs'))->when($request->filled('from'), fn ($query) => $query->whereDate('failed_at', '>=', $request->date('from')))->when($request->filled('to'), fn ($query) => $query->whereDate('failed_at', '<=', $request->date('to')))->when($request->filled('search'), function ($query) use ($request): void {
            $search = $request->string('search')->trim()->toString();
            $query->where(fn ($searchQuery) => $searchQuery->where('uuid', 'ilike', "%{$search}%")->orWhere('queue', 'ilike', "%{$search}%")->orWhere('payload', 'ilike', "%{$search}%"));
        })->latest('failed_at')->paginate($request->integer('per_page', 10), ['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'], 'failed_page')->withQueryString();
        $queueRecoveries = QueueRecoveryAttempt::query()->with('initiator:id,name')->latest('attempted_at')->limit(25)->get();
        $performanceRuns = PerformanceTestRun::query()
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('started_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('started_at', '<=', $request->date('to')))
            ->latest('started_at')
            ->paginate($request->integer('per_page', 10), ['*'], 'performance_page')
            ->withQueryString();
        $operationalAlerts = OperationalAlert::query()
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('first_detected_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('first_detected_at', '<=', $request->date('to')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(fn (Builder $searchQuery) => $searchQuery->where('service', 'ilike', "%{$search}%")->orWhere('metric', 'ilike', "%{$search}%"));
            })
            ->withCount('events')
            ->with(['acknowledger:id,name', 'events' => fn ($query) => $query->with('actor:id,name')->latest('occurred_at')->limit(100)])
            ->latest('last_detected_at')
            ->paginate($request->integer('per_page', 10), ['*'], 'alert_page')
            ->withQueryString();

        return Inertia::render('operations/index', ['readiness' => $readiness->run(), 'backups' => $backups->through(fn (OperationalBackup $backup): array => ['id' => $backup->id, 'reference' => $backup->reference, 'database' => $backup->database_name, 'format' => $backup->format, 'sha256' => $backup->sha256, 'sizeBytes' => $backup->size_bytes, 'status' => $backup->status, 'startedAt' => $backup->started_at->toIso8601String(), 'completedAt' => $backup->completed_at?->toIso8601String(), 'restoreVerifiedAt' => $backup->restore_verified_at?->toIso8601String(), 'restoreDurationMs' => $backup->restore_duration_ms, 'verifiedTableCount' => $backup->verified_table_count, 'initiator' => $backup->initiator?->name, 'verifier' => $backup->restoreVerifier?->name, 'errorDetail' => $backup->error_detail]), 'failedJobs' => $failedJobs->through(function (object $job): array {
            $row = (array) $job;
            $payload = (string) ($row['payload'] ?? '');
            $exception = (string) ($row['exception'] ?? '');

            return ['uuid' => (string) ($row['uuid'] ?? ''), 'connection' => (string) ($row['connection'] ?? ''), 'queue' => (string) ($row['queue'] ?? ''), 'jobName' => $this->jobName($payload), 'payloadChecksum' => hash('sha256', $payload), 'exceptionCategory' => Str::limit(Str::before($exception, ':'), 160, ''), 'exceptionChecksum' => hash('sha256', $exception), 'failedAt' => (string) ($row['failed_at'] ?? '')];
        }), 'queueRecoveries' => $queueRecoveries->map(fn (QueueRecoveryAttempt $attempt): array => ['id' => $attempt->id, 'failedJobUuid' => $attempt->failed_job_uuid, 'connection' => $attempt->connection, 'queue' => $attempt->queue, 'jobName' => $attempt->job_name, 'payloadChecksum' => $attempt->payload_checksum, 'exceptionChecksum' => $attempt->exception_checksum, 'outcome' => $attempt->outcome, 'errorCategory' => $attempt->error_category, 'errorDetail' => $attempt->error_detail, 'failedAt' => $attempt->failed_at->toIso8601String(), 'attemptedAt' => $attempt->attempted_at->toIso8601String(), 'initiatedBy' => $attempt->initiated_by_name, 'evidenceChecksum' => $attempt->evidence_checksum])->values(), 'performanceRuns' => $performanceRuns->through(fn (PerformanceTestRun $run): array => ['id' => $run->id, 'environment' => $run->environment, 'tool' => $run->tool, 'targetUrl' => $run->target_url, 'routePath' => $run->route_path, 'requestCount' => $run->request_count, 'concurrency' => $run->concurrency, 'successfulRequests' => $run->successful_requests, 'failedRequests' => $run->failed_requests, 'requestsPerSecond' => $run->requests_per_second, 'meanLatencyMs' => $run->mean_latency_ms, 'p50LatencyMs' => $run->p50_latency_ms, 'p95LatencyMs' => $run->p95_latency_ms, 'p99LatencyMs' => $run->p99_latency_ms, 'durationMs' => $run->duration_ms, 'thresholdSnapshot' => $run->threshold_snapshot, 'outcome' => $run->outcome, 'errorCategory' => $run->error_category, 'errorDetail' => $run->error_detail, 'initiatedBy' => $run->initiated_by_name, 'startedAt' => $run->started_at->toIso8601String(), 'completedAt' => $run->completed_at->toIso8601String(), 'outputChecksum' => $run->output_checksum, 'evidenceChecksum' => $run->evidence_checksum]), 'operationalAlerts' => $operationalAlerts->through(fn (OperationalAlert $alert): array => ['id' => $alert->id, 'service' => $alert->service, 'metric' => $alert->metric, 'severity' => $alert->severity, 'status' => $alert->status, 'latestValue' => $alert->latest_value, 'threshold' => $alert->threshold, 'unit' => $alert->unit, 'occurrenceCount' => $alert->occurrence_count, 'eventCount' => $alert->events_count, 'firstDetectedAt' => $alert->first_detected_at->toIso8601String(), 'lastDetectedAt' => $alert->last_detected_at->toIso8601String(), 'acknowledgedAt' => $alert->acknowledged_at?->toIso8601String(), 'acknowledgedBy' => $alert->acknowledger?->name, 'acknowledgementNote' => $alert->acknowledgement_note, 'recoveredAt' => $alert->recovered_at?->toIso8601String(), 'evidenceChecksum' => $alert->evidence_checksum, 'events' => $alert->events->map(fn (OperationalAlertEvent $event): array => ['id' => $event->id, 'type' => $event->event_type, 'status' => $event->status, 'narrative' => $event->narrative, 'occurredAt' => $event->occurred_at->toIso8601String(), 'actor' => $event->actor?->name, 'evidenceChecksum' => $event->evidence_checksum])->values()->all()]), 'releases' => $releases->map(fn (ReleaseRecord $release): array => ['id' => $release->id, 'version' => $release->version, 'gitSha' => $release->git_sha, 'environment' => $release->environment, 'artifactChecksum' => $release->artifact_checksum, 'changeReference' => $release->change_reference, 'migrationBatch' => $release->migration_batch, 'status' => $release->status, 'deployedAt' => $release->deployed_at->toIso8601String(), 'validatedAt' => $release->validated_at?->toIso8601String(), 'rolledBackAt' => $release->rolled_back_at?->toIso8601String(), 'rollbackToVersion' => $release->rollback_to_version, 'deployer' => $release->deployer?->name, 'validator' => $release->validator?->name, 'rollbackActor' => $release->rollbackActor?->name, 'notes' => $release->notes])->values(), 'measurements' => $measurements->map(fn (ServiceLevelMeasurement $measurement): array => ['id' => $measurement->id, 'service' => $measurement->service, 'metric' => $measurement->metric, 'value' => $measurement->value, 'unit' => $measurement->unit, 'target' => $measurement->target, 'status' => $measurement->status, 'observedAt' => $measurement->observed_at->toIso8601String()])->values(), 'schedule' => collect(Schedule::events())->map(fn ($event): array => ['command' => $event->command ?? $event->description, 'expression' => $event->expression, 'description' => $event->description])->values(), 'targets' => ['rpoMinutes' => config('operations.rpo_minutes'), 'rtoMinutes' => config('operations.rto_minutes'), 'availabilityPercent' => config('operations.availability_target_percent'), 'backupMaxAgeMinutes' => config('operations.backup_max_age_minutes')], 'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'per_page']), 'capabilities' => ['manage' => $request->user()?->can(ProgrammePermission::ManageOperations->value) === true]]);
    }

    public function storeRelease(StoreReleaseRecordRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $release = ReleaseRecord::create([...$request->validated(), 'deployed_by' => $user->id, 'status' => 'deployed']);
        $this->auditLogger->record($user, $release, 'operations.release.recorded', "Release {$release->version} recorded for {$release->environment}.");

        return back()->with('success', 'Deployment record created for independent validation.');
    }

    public function validateRelease(ValidateReleaseRequest $request, ReleaseRecord $release, ValidateRelease $action): RedirectResponse
    {
        $action->handle($release, $this->user($request), $request->validated('evidence'));

        return back()->with('success', 'Release independently validated.');
    }

    public function rollbackRelease(RollbackReleaseRequest $request, ReleaseRecord $release, RollbackRelease $action): RedirectResponse
    {
        $rollbackToVersion = $request->validated('rollback_to_version');
        $reason = $request->validated('reason');
        abort_unless(is_string($rollbackToVersion) && is_string($reason), 422);
        $action->handle($release, $this->user($request), ['rollback_to_version' => $rollbackToVersion, 'reason' => $reason]);

        return back()->with('success', 'Rollback decision recorded. Execute the approved deployment runbook and attach platform evidence.');
    }

    public function createBackup(Request $request): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageOperations->value);
        CreateOperationalBackupJob::dispatch($this->user($request)->id);

        return back()->with('success', 'Database backup queued.');
    }

    public function verifyBackup(Request $request, OperationalBackup $backup): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageOperations->value);
        VerifyOperationalBackupJob::dispatch($backup->id, $this->user($request)->id, true);

        return back()->with('success', 'Isolated restore verification queued.');
    }

    public function retryFailedJob(RetryFailedQueueJobRequest $request, string $failedJobUuid, RetryFailedQueueJob $action): RedirectResponse
    {
        $attempt = $action->handle($failedJobUuid, $this->user($request));

        return back()->with($attempt->outcome === 'requeued' ? 'success' : 'error', $attempt->outcome === 'requeued' ? 'Failed job requeued with immutable recovery evidence.' : 'Queue provider rejected the recovery request; the failed job remains available.');
    }

    public function acknowledgeAlert(AcknowledgeOperationalAlertRequest $request, OperationalAlert $operationalAlert, AcknowledgeOperationalAlert $action): RedirectResponse
    {
        $note = $request->validated('note');
        abort_unless(is_string($note), 422);
        $action->handle($operationalAlert, $this->user($request), $note);

        return back()->with('success', 'Operational alert acknowledged with immutable response evidence.');
    }

    private function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        $name = is_array($decoded) ? ($decoded['displayName'] ?? $decoded['job'] ?? null) : null;

        return is_string($name) ? Str::limit($name, 255, '') : 'Unknown queued job';
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
