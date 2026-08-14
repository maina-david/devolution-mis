import { Form, Head, usePage } from '@inertiajs/react';
import {
    Activity,
    ArchiveRestore,
    Download,
    Eye,
    Gauge,
    MoreHorizontal,
    Plus,
    RotateCcw,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { interpolate } from '@/hooks/use-localization';
import { DEFAULT_LOCALE } from '@/lib/reference-catalog';
import { acknowledge as acknowledgeAlert } from '@/routes/operations/alerts';
import { store as requestBackup } from '@/routes/operations/backups';
import { verify as verifyBackup } from '@/routes/operations/backups';
import { retry as retryFailedJob } from '@/routes/operations/failed-jobs';
import {
    rollback,
    store as storeRelease,
    validate,
} from '@/routes/operations/releases';
import { exportMethod } from '@/routes/workspace';

type Check = { status: string; latency_ms: number | null; detail: string };
type Backup = {
    id: string;
    reference: string;
    database: string;
    format: string;
    sha256: string | null;
    sizeBytes: number | null;
    status: string;
    startedAt: string;
    completedAt: string | null;
    restoreVerifiedAt: string | null;
    restoreDurationMs: number | null;
    verifiedTableCount: number | null;
    initiator: string | null;
    verifier: string | null;
    errorDetail: string | null;
};
type Release = {
    id: string;
    version: string;
    gitSha: string;
    environment: string;
    artifactChecksum: string;
    changeReference: string;
    migrationBatch: number | null;
    status: string;
    deployedAt: string;
    validatedAt: string | null;
    rolledBackAt: string | null;
    rollbackToVersion: string | null;
    deployer: string | null;
    validator: string | null;
    rollbackActor: string | null;
    notes: string | null;
};
type Measurement = {
    id: string;
    service: string;
    metric: string;
    value: string;
    unit: string;
    target: string | null;
    status: string;
    observedAt: string;
};
type OperationalAlertEvent = {
    id: string;
    type: string;
    status: string;
    narrative: string;
    occurredAt: string;
    actor: string | null;
    evidenceChecksum: string;
};
type OperationalAlert = {
    id: string;
    service: string;
    metric: string;
    severity: string;
    status: string;
    latestValue: string;
    threshold: string | null;
    unit: string;
    occurrenceCount: number;
    eventCount: number;
    firstDetectedAt: string;
    lastDetectedAt: string;
    acknowledgedAt: string | null;
    acknowledgedBy: string | null;
    acknowledgementNote: string | null;
    recoveredAt: string | null;
    evidenceChecksum: string;
    events: OperationalAlertEvent[];
};
type ScheduleItem = {
    command: string;
    expression: string;
    description: string | null;
};
type FailedJob = {
    uuid: string;
    connection: string;
    queue: string;
    jobName: string;
    payloadChecksum: string;
    exceptionCategory: string;
    exceptionChecksum: string;
    failedAt: string;
};
type QueueRecovery = {
    id: string;
    failedJobUuid: string;
    connection: string;
    queue: string;
    jobName: string;
    payloadChecksum: string;
    exceptionChecksum: string;
    outcome: string;
    errorCategory: string | null;
    errorDetail: string | null;
    failedAt: string;
    attemptedAt: string;
    initiatedBy: string;
    evidenceChecksum: string;
};
type PerformanceRun = {
    id: string;
    environment: string;
    tool: string;
    targetUrl: string;
    routePath: string;
    requestCount: number;
    concurrency: number;
    successfulRequests: number;
    failedRequests: number;
    requestsPerSecond: string | null;
    meanLatencyMs: string | null;
    p50LatencyMs: string | null;
    p95LatencyMs: string | null;
    p99LatencyMs: string | null;
    durationMs: number;
    thresholdSnapshot: Record<string, number>;
    outcome: string;
    errorCategory: string | null;
    errorDetail: string | null;
    initiatedBy: string;
    startedAt: string;
    completedAt: string;
    outputChecksum: string;
    evidenceChecksum: string;
};
type PageSet<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    readiness: {
        ready: boolean;
        checked_at: string;
        checks: Record<string, Check>;
    };
    backups: PageSet<Backup>;
    failedJobs: PageSet<FailedJob>;
    queueRecoveries: QueueRecovery[];
    performanceRuns: PageSet<PerformanceRun>;
    operationalAlerts: PageSet<OperationalAlert>;
    releases: Release[];
    measurements: Measurement[];
    schedule: ScheduleItem[];
    targets: {
        rpoMinutes: number;
        rtoMinutes: number;
        availabilityPercent: number;
        backupMaxAgeMinutes: number;
    };
    filters: Record<string, string | undefined>;
    capabilities: { manage: boolean };
};

export default function Operations({
    readiness,
    backups,
    failedJobs,
    queueRecoveries,
    performanceRuns,
    operationalAlerts,
    releases,
    measurements,
    schedule,
    targets,
    filters,
    capabilities,
}: Props) {
    const copy = usePage().props.localization.operations.ui;
    const rows: WorkspaceRow[] = backups.data.map((backup) => ({
        id: backup.id,
        status: backup.status,
        cells: [
            backup.reference,
            backup.database,
            formatBytes(backup.sizeBytes),
            backup.sha256?.slice(0, 12) ?? '—',
            formatDate(backup.completedAt),
            backup.restoreVerifiedAt
                ? `${formatDate(backup.restoreVerifiedAt)} · ${backup.verifiedTableCount} tables`
                : 'Not verified',
            backup.restoreDurationMs ? `${backup.restoreDurationMs} ms` : '—',
            humanize(backup.status),
        ],
    }));
    const pagination: WorkspacePagination = {
        currentPage: backups.current_page,
        lastPage: backups.last_page,
        perPage: backups.per_page,
        total: backups.total,
    };
    const failedJobRows: WorkspaceRow[] = failedJobs.data.map((job) => ({
        id: job.uuid,
        status: 'failed',
        cells: [
            job.jobName,
            job.queue,
            job.connection,
            job.exceptionCategory,
            job.payloadChecksum.slice(0, 12),
            formatDate(job.failedAt),
        ],
    }));
    const failedJobPagination: WorkspacePagination = {
        currentPage: failedJobs.current_page,
        lastPage: failedJobs.last_page,
        perPage: failedJobs.per_page,
        total: failedJobs.total,
        pageName: 'failed_page',
    };
    const recoveryRows: WorkspaceRow[] = queueRecoveries.map((attempt) => ({
        id: attempt.id,
        status: attempt.outcome,
        cells: [
            attempt.jobName,
            attempt.queue,
            attempt.initiatedBy,
            formatDate(attempt.attemptedAt),
            attempt.evidenceChecksum.slice(0, 12),
            humanize(attempt.outcome),
        ],
    }));
    const recoveryPagination: WorkspacePagination = {
        currentPage: 1,
        lastPage: 1,
        perPage: 25,
        total: queueRecoveries.length,
    };
    const performanceRows: WorkspaceRow[] = performanceRuns.data.map((run) => ({
        id: run.id,
        status: run.outcome,
        cells: [
            run.routePath,
            `${run.requestCount} / ${run.concurrency}`,
            run.requestsPerSecond ?? '—',
            run.p95LatencyMs ? `${run.p95LatencyMs} ms` : '—',
            run.failedRequests.toLocaleString(),
            formatDate(run.startedAt),
            humanize(run.outcome),
        ],
    }));
    const performancePagination: WorkspacePagination = {
        currentPage: performanceRuns.current_page,
        lastPage: performanceRuns.last_page,
        perPage: performanceRuns.per_page,
        total: performanceRuns.total,
        pageName: 'performance_page',
    };
    const alertRows: WorkspaceRow[] = operationalAlerts.data.map((alert) => ({
        id: alert.id,
        status: alert.status,
        cells: [
            humanize(alert.service),
            humanize(alert.metric),
            humanize(alert.severity),
            `${alert.latestValue} ${alert.unit}`,
            alert.threshold ? `${alert.threshold} ${alert.unit}` : '—',
            alert.occurrenceCount.toLocaleString(),
            formatDate(alert.lastDetectedAt),
            humanize(alert.status),
        ],
    }));
    const alertPagination: WorkspacePagination = {
        currentPage: operationalAlerts.current_page,
        lastPage: operationalAlerts.last_page,
        perPage: operationalAlerts.per_page,
        total: operationalAlerts.total,
        pageName: 'alert_page',
    };

    return (
        <>
            <Head title={copy.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                {copy.eyebrow}
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {copy.title}
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                {copy.description}
                            </p>
                        </div>
                        {capabilities.manage && (
                            <div className="flex gap-2">
                                <BackupRequest />
                                <ReleaseForm />
                            </div>
                        )}
                    </div>
                </section>
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        title={copy.readiness}
                        value={readiness.ready ? copy.ready : copy.not_ready}
                        detail={interpolate(copy.dependencies_passing, {
                            passing: Object.values(readiness.checks).filter(
                                (check) => check.status === 'pass',
                            ).length,
                            total: Object.keys(readiness.checks).length,
                        })}
                        status={readiness.ready ? 'pass' : 'fail'}
                    />
                    <MetricCard
                        title={copy.availability_target}
                        value={`${targets.availabilityPercent}%`}
                        detail={copy.availability_target_detail}
                        status="info"
                    />
                    <MetricCard
                        title={copy.recovery_point}
                        value={`${targets.rpoMinutes} min`}
                        detail={interpolate(copy.backup_age_detail, {
                            minutes: targets.backupMaxAgeMinutes,
                        })}
                        status="info"
                    />
                    <MetricCard
                        title={copy.recovery_time}
                        value={`${targets.rtoMinutes} min`}
                        detail={copy.recovery_time_detail}
                        status="info"
                    />
                </section>
                <section className="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                    {Object.entries(readiness.checks).map(([name, check]) => (
                        <Card key={name}>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-base">
                                        {humanize(name)}
                                    </CardTitle>
                                    <Badge
                                        variant={
                                            check.status === 'pass'
                                                ? 'default'
                                                : 'destructive'
                                        }
                                    >
                                        {check.status}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-bold">
                                    {check.latency_ms ?? '—'} {copy.ms}
                                </p>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {check.detail}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </section>
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    selectFilters={[
                        {
                            key: 'status',
                            label: copy.backup_status,
                            options: ['running', 'completed', 'failed'].map(
                                option,
                            ),
                            value: filters.status,
                        },
                    ]}
                />
                <section className="overflow-hidden rounded-xl border bg-card">
                    <div className="border-b px-5 py-4 sm:px-6">
                        <h2 className="font-bold">{copy.operational_alerts}</h2>
                        <p className="text-sm text-muted-foreground">
                            {operationalAlerts.total.toLocaleString()}{' '}
                            {copy.operational_alerts_description}
                        </p>
                    </div>
                    {alertRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Service',
                                'Metric',
                                'Severity',
                                'Latest value',
                                'Threshold',
                                'Occurrences',
                                'Last detected',
                                'Status',
                            ]}
                            rows={alertRows}
                            pagination={alertPagination}
                            bulkExport={{
                                workspace: 'operational-alerts',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const alert = operationalAlerts.data.find(
                                    (entry) => entry.id === row.id,
                                );

                                return alert ? (
                                    <OperationalAlertAction
                                        alert={alert}
                                        canManage={capabilities.manage}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_operational_alerts}
                            description={copy.no_alerts_description}
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <RegisterHeader filters={filters} total={backups.total} />
                    {rows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Reference',
                                'Database',
                                'Size',
                                'Checksum',
                                'Completed',
                                'Restore evidence',
                                'Duration',
                                'Status',
                            ]}
                            rows={rows}
                            pagination={pagination}
                            bulkExport={{
                                workspace: 'operations',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const backup = backups.data.find(
                                    (entry) => entry.id === row.id,
                                );

                                return backup ? (
                                    <BackupAction
                                        backup={backup}
                                        canManage={capabilities.manage}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_backup_evidence}
                            description={copy.no_backups_description}
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="grid gap-4 xl:grid-cols-2">
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <div className="border-b px-5 py-4 sm:px-6">
                            <h2 className="font-bold">
                                {copy.failed_queue_jobs}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {failedJobs.total.toLocaleString()}{' '}
                                {copy.failed_queue_jobs_description}
                            </p>
                        </div>
                        {failedJobRows.length ? (
                            <WorkspaceDataTable
                                columns={[
                                    'Job',
                                    'Queue',
                                    'Connection',
                                    'Failure',
                                    'Payload checksum',
                                    'Failed',
                                ]}
                                rows={failedJobRows}
                                pagination={failedJobPagination}
                                renderActionControl={(row) => {
                                    const job = failedJobs.data.find(
                                        (entry) => entry.uuid === row.id,
                                    );

                                    return job ? (
                                        <FailedJobAction
                                            job={job}
                                            canManage={capabilities.manage}
                                        />
                                    ) : null;
                                }}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title={copy.no_failed_queue_jobs}
                                description={copy.no_failed_jobs_description}
                                className="min-h-64 border-0"
                            />
                        )}
                    </div>
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <div className="border-b px-5 py-4 sm:px-6">
                            <h2 className="font-bold">
                                {copy.immutable_recovery_evidence}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {copy.immutable_recovery_evidence_description}
                            </p>
                        </div>
                        {recoveryRows.length ? (
                            <WorkspaceDataTable
                                columns={[
                                    'Job',
                                    'Queue',
                                    'Operator',
                                    'Attempted',
                                    'Evidence checksum',
                                    'Outcome',
                                ]}
                                rows={recoveryRows}
                                pagination={recoveryPagination}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title={copy.no_recovery_attempts}
                                description={copy.no_job_recoveries_description}
                                className="min-h-64 border-0"
                            />
                        )}
                    </div>
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <div className="border-b px-5 py-4 sm:px-6">
                        <h2 className="font-bold">
                            {copy.performance_assurance_evidence}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {performanceRuns.total.toLocaleString()}{' '}
                            {copy.performance_assurance_evidence_description}
                        </p>
                    </div>
                    {performanceRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Route',
                                'Requests / concurrency',
                                'Requests/sec',
                                'P95 latency',
                                'Failures',
                                'Started',
                                'Outcome',
                            ]}
                            rows={performanceRows}
                            pagination={performancePagination}
                            renderActionControl={(row) => {
                                const run = performanceRuns.data.find(
                                    (entry) => entry.id === row.id,
                                );

                                return run ? (
                                    <PerformanceRunAction run={run} />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_performance_evidence}
                            description={copy.no_performance_runs_description}
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="grid gap-4 lg:grid-cols-2">
                    <div className="grid content-start gap-4">
                        <div>
                            <h2 className="font-bold">
                                {copy.release_rollback_evidence}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {copy.release_rollback_evidence_description}
                            </p>
                        </div>
                        {releases.map((release) => (
                            <ReleaseCard
                                key={release.id}
                                release={release}
                                releases={releases}
                                canManage={capabilities.manage}
                            />
                        ))}
                        {releases.length === 0 && (
                            <WorkspaceEmptyState
                                title={copy.no_release_evidence}
                                description={copy.no_releases_description}
                            />
                        )}
                    </div>
                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Activity className="size-4" />{' '}
                                    {copy.latest_service_measurements}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {measurements.map((measurement) => (
                                    <div
                                        key={measurement.id}
                                        className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {humanize(measurement.metric)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {measurement.service}{' '}
                                                {copy.separator}{' '}
                                                {formatDate(
                                                    measurement.observedAt,
                                                )}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                measurement.status === 'pass'
                                                    ? 'default'
                                                    : 'destructive'
                                            }
                                        >
                                            {measurement.value}{' '}
                                            {measurement.unit}
                                        </Badge>
                                    </div>
                                ))}
                                {measurements.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        {copy.measurements_empty}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Gauge className="size-4" />{' '}
                                    {copy.scheduled_controls}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {schedule.map((item) => (
                                    <div
                                        key={`${item.expression}-${item.command}`}
                                        className="rounded-lg border p-3"
                                    >
                                        <p className="text-sm font-medium">
                                            {item.description ?? item.command}
                                        </p>
                                        <p className="mt-1 font-mono text-xs text-muted-foreground">
                                            {item.expression}
                                        </p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </section>
            </div>
        </>
    );
}

function OperationalAlertAction({
    alert,
    canManage,
}: {
    alert: OperationalAlert;
    canManage: boolean;
}) {
    const copy = usePage().props.localization.operations.ui;
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={interpolate(copy.actions_for_alert, {
                            metric: humanize(alert.metric),
                        })}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        <Eye /> {copy.view_alert_evidence}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{humanize(alert.metric)}</SheetTitle>
                        <SheetDescription>
                            {humanize(alert.service)} {copy.separator}{' '}
                            {humanize(alert.severity)} {copy.separator}{' '}
                            {humanize(alert.status)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-5 px-4 pb-6">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <EvidenceField
                                label={copy.latest_value}
                                value={`${alert.latestValue} ${alert.unit}`}
                            />
                            <EvidenceField
                                label={copy.provisional_threshold}
                                value={
                                    alert.threshold
                                        ? `${alert.threshold} ${alert.unit}`
                                        : 'Not configured'
                                }
                            />
                            <EvidenceField
                                label={copy.occurrences}
                                value={alert.occurrenceCount.toLocaleString()}
                            />
                            <EvidenceField
                                label={copy.last_detected}
                                value={formatDate(alert.lastDetectedAt)}
                            />
                            <EvidenceField
                                label={copy.acknowledged_by}
                                value={alert.acknowledgedBy ?? 'Pending'}
                            />
                            <EvidenceField
                                label={copy.recovered}
                                value={formatDate(alert.recoveredAt)}
                            />
                        </div>
                        {alert.acknowledgementNote && (
                            <EvidenceField
                                label={copy.acknowledgement_note}
                                value={alert.acknowledgementNote}
                            />
                        )}
                        <EvidenceField
                            label={copy.alert_evidence_checksum}
                            value={alert.evidenceChecksum}
                            mono
                        />
                        <div className="flex flex-col gap-3">
                            <div>
                                <h3 className="font-medium">
                                    {copy.immutable_timeline}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    {copy.showing_latest} {alert.events.length}{' '}
                                    {copy.of}{' '}
                                    {alert.eventCount.toLocaleString()}{' '}
                                    {copy.retained_events}
                                </p>
                            </div>
                            {alert.events.map((event) => (
                                <div
                                    key={event.id}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <Badge variant="outline">
                                            {humanize(event.type)}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDate(event.occurredAt)}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm">
                                        {event.narrative}
                                    </p>
                                    <p className="mt-2 font-mono text-xs text-muted-foreground">
                                        {event.evidenceChecksum}
                                    </p>
                                </div>
                            ))}
                        </div>
                        {canManage && alert.status === 'open' && (
                            <Form
                                {...acknowledgeAlert.form({
                                    operationalAlert: alert.id,
                                })}
                                resetOnSuccess
                                onSuccess={() => setOpen(false)}
                                className="flex flex-col gap-4 rounded-lg border p-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="flex flex-col gap-2">
                                            <Label
                                                htmlFor={`alert-note-${alert.id}`}
                                            >
                                                {copy.accountable_response_note}
                                            </Label>
                                            <Textarea
                                                id={`alert-note-${alert.id}`}
                                                name="note"
                                                required
                                                minLength={20}
                                                maxLength={2000}
                                                aria-invalid={Boolean(
                                                    errors.note,
                                                )}
                                                placeholder={
                                                    copy.record_the_immediate_response_owner_and_next_control_action
                                                }
                                            />
                                            <InputError message={errors.note} />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {copy.acknowledge_alert}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function PerformanceRunAction({ run }: { run: PerformanceRun }) {
    const copy = usePage().props.localization.operations.ui;
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={interpolate(
                            copy.actions_for_performance_run,
                            { id: run.id },
                        )}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        <Eye /> {copy.view_evidence}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{copy.performance_run_evidence}</SheetTitle>
                        <SheetDescription>
                            {run.routePath} {copy.separator}{' '}
                            {formatDate(run.startedAt)} {copy.separator}{' '}
                            {run.environment}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 px-4 pb-6">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <EvidenceField
                                label={copy.outcome}
                                value={humanize(run.outcome)}
                            />
                            <EvidenceField label={copy.tool} value={run.tool} />
                            <EvidenceField
                                label={copy.requests_concurrency}
                                value={`${run.requestCount} / ${run.concurrency}`}
                            />
                            <EvidenceField
                                label={copy.successful_failed}
                                value={`${run.successfulRequests} / ${run.failedRequests}`}
                            />
                            <EvidenceField
                                label={copy.requests_per_second}
                                value={run.requestsPerSecond ?? 'Unavailable'}
                            />
                            <EvidenceField
                                label={copy.mean_latency}
                                value={formatMetric(run.meanLatencyMs, 'ms')}
                            />
                            <EvidenceField
                                label={copy.p50_p95_p99}
                                value={`${formatMetric(run.p50LatencyMs, 'ms')} / ${formatMetric(run.p95LatencyMs, 'ms')} / ${formatMetric(run.p99LatencyMs, 'ms')}`}
                            />
                            <EvidenceField
                                label={copy.wall_clock_duration}
                                value={`${run.durationMs.toLocaleString()} ms`}
                            />
                            <EvidenceField
                                label={copy.initiated_by}
                                value={run.initiatedBy}
                            />
                            <EvidenceField
                                label={copy.target}
                                value={run.targetUrl}
                            />
                        </div>
                        <div className="grid gap-2">
                            <p className="text-sm font-medium">
                                {copy.threshold_snapshot}
                            </p>
                            <pre className="overflow-x-auto rounded-lg border bg-muted/40 p-3 text-xs">
                                {JSON.stringify(run.thresholdSnapshot, null, 2)}
                            </pre>
                        </div>
                        <EvidenceField
                            label={copy.output_checksum}
                            value={run.outputChecksum}
                            mono
                        />
                        <EvidenceField
                            label={copy.evidence_checksum}
                            value={run.evidenceChecksum}
                            mono
                        />
                        {run.errorCategory && (
                            <EvidenceField
                                label={copy.failure_classification}
                                value={`${run.errorCategory}${run.errorDetail ? ` · ${run.errorDetail}` : ''}`}
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function EvidenceField({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: string;
    mono?: boolean;
}) {
    return (
        <div className="grid gap-1">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <p className={mono ? 'font-mono text-xs break-all' : 'text-sm'}>
                {value}
            </p>
        </div>
    );
}

function formatMetric(value: string | null, unit: string): string {
    return value ? `${value} ${unit}` : 'Unavailable';
}

function FailedJobAction({
    job,
    canManage,
}: {
    job: FailedJob;
    canManage: boolean;
}) {
    const copy = usePage().props.localization.operations.ui;
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={interpolate(copy.actions_for_failed_job, {
                            id: job.uuid,
                        })}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        <Eye /> {copy.view_recovery_evidence}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{job.jobName}</SheetTitle>
                        <SheetDescription>
                            {job.connection} {copy.separator} {job.queue}{' '}
                            {copy.separator} {copy.failed}{' '}
                            {formatDate(job.failedAt)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        <Detail label={copy.failed_job_uuid} value={job.uuid} />
                        <Detail
                            label={copy.safe_failure_category}
                            value={job.exceptionCategory}
                        />
                        <Detail
                            label={copy.payload_checksum}
                            value={job.payloadChecksum}
                        />
                        <Detail
                            label={copy.exception_checksum}
                            value={job.exceptionChecksum}
                        />
                        {canManage && (
                            <Form
                                {...retryFailedJob.form({
                                    failedJobUuid: job.uuid,
                                })}
                                className="grid gap-4 rounded-lg border p-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <p className="text-sm text-muted-foreground">
                                            {copy.requeue_description}
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <RotateCcw />{' '}
                                            {copy.retry_failed_job}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function BackupRequest() {
    const copy = usePage().props.localization.operations.ui;

    return (
        <FormSheet
            title={copy.request_database_backup}
            description={copy.request_backup_description}
            triggerLabel={copy.request_backup}
            icon={ArchiveRestore}
        >
            <Form action={requestBackup()} className="grid gap-4 pt-4">
                <p className="text-sm text-muted-foreground">
                    {copy.backup_request_description}
                </p>
                <Button type="submit">
                    <ArchiveRestore /> {copy.queue_backup}
                </Button>
            </Form>
        </FormSheet>
    );
}

function ReleaseForm() {
    const copy = usePage().props.localization.operations.ui;

    return (
        <FormSheet
            title={copy.record_deployment}
            description={copy.record_release_description}
            triggerLabel={copy.record_release}
            icon={Plus}
            size="xl"
        >
            <Form action={storeRelease()} className="grid gap-5 pt-4">
                {({ processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                name="version"
                                label={copy.release_version}
                            />
                            <Field
                                name="git_sha"
                                label={copy.git_commit_sha_40_hex}
                            />
                            <SearchableSelect
                                id="release-environment"
                                name="environment"
                                label={copy.environment}
                                options={[
                                    'test',
                                    'staging',
                                    'pilot',
                                    'production',
                                ].map(option)}
                                defaultValue="pilot"
                            />
                            <Field
                                name="artifact_checksum"
                                label={copy.artifact_sha256}
                            />
                            <Field
                                name="change_reference"
                                label={copy.approved_change_reference}
                            />
                            <Field
                                name="migration_batch"
                                label={copy.migration_batch}
                                type="number"
                                optional
                            />
                        </div>
                        <DatePickerField
                            name="deployed_at"
                            label={copy.deployment_date_and_time}
                            includeTime
                            required
                        />
                        <TextField
                            name="notes"
                            label={copy.deployment_evidence_and_observations}
                            optional
                        />
                        <Button type="submit" disabled={processing}>
                            {copy.record_deployment}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function BackupAction({
    backup,
    canManage,
}: {
    backup: Backup;
    canManage: boolean;
}) {
    const copy = usePage().props.localization.operations.ui;
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="ghost"
                size="icon"
                onClick={() => setOpen(true)}
                aria-label={interpolate(copy.open_backup, {
                    reference: backup.reference,
                })}
            >
                <MoreHorizontal />
            </Button>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{backup.reference}</SheetTitle>
                        <SheetDescription>
                            {backup.database} {copy.separator}{' '}
                            {humanize(backup.status)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        <Detail
                            label={copy.artifact_checksum}
                            value={backup.sha256 ?? 'Unavailable'}
                        />
                        <Detail
                            label={copy.artifact_size}
                            value={formatBytes(backup.sizeBytes)}
                        />
                        <Detail
                            label={copy.restore_verification}
                            value={
                                backup.restoreVerifiedAt
                                    ? `${formatDate(backup.restoreVerifiedAt)} · ${backup.verifiedTableCount} tables · ${backup.restoreDurationMs} ms`
                                    : 'Not yet verified'
                            }
                        />
                        {backup.errorDetail && (
                            <Detail
                                label={copy.failure}
                                value={backup.errorDetail}
                            />
                        )}
                        {canManage && backup.status === 'completed' && (
                            <Form
                                action={verifyBackup({ backup: backup.id })}
                                className="grid gap-4 rounded-lg border p-4"
                            >
                                <p className="text-sm">
                                    {copy.restore_description}
                                </p>
                                <Button type="submit">
                                    <ShieldCheck />{' '}
                                    {copy.verify_isolated_restore}
                                </Button>
                            </Form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ReleaseCard({
    release,
    releases,
    canManage,
}: {
    release: Release;
    releases: Release[];
    canManage: boolean;
}) {
    const copy = usePage().props.localization.operations.ui;
    const [surface, setSurface] = useState<string | null>(null);
    const rollbackTargets = releases.filter(
        (candidate) =>
            candidate.environment === release.environment &&
            candidate.status === 'validated' &&
            candidate.id !== release.id,
    );

    return (
        <>
            <Card>
                <CardHeader className="flex-row items-start justify-between">
                    <div>
                        <CardTitle className="text-base">
                            {release.version} {copy.separator}{' '}
                            {humanize(release.environment)}
                        </CardTitle>
                        <p className="mt-1 font-mono text-xs text-muted-foreground">
                            {release.gitSha.slice(0, 12)}
                        </p>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={interpolate(
                                    copy.actions_for_release,
                                    { version: release.version },
                                )}
                            >
                                <MoreHorizontal />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onSelect={() => setSurface('details')}
                            >
                                <Eye /> {copy.view_evidence}
                            </DropdownMenuItem>
                            {canManage && release.status === 'deployed' && (
                                <DropdownMenuItem
                                    onSelect={() => setSurface('validate')}
                                >
                                    <ShieldCheck />{' '}
                                    {copy.independently_validate}
                                </DropdownMenuItem>
                            )}
                            {canManage &&
                                ['deployed', 'validated'].includes(
                                    release.status,
                                ) &&
                                rollbackTargets.length > 0 && (
                                    <DropdownMenuItem
                                        onSelect={() => setSurface('rollback')}
                                    >
                                        <RotateCcw /> {copy.record_rollback}
                                    </DropdownMenuItem>
                                )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap gap-2">
                        <Badge>{humanize(release.status)}</Badge>
                        <Badge variant="outline">
                            {release.changeReference}
                        </Badge>
                        <Badge variant="outline">
                            {formatDate(release.deployedAt)}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'details'
                                ? `Release ${release.version}`
                                : humanize(surface ?? '')}
                        </SheetTitle>
                        <SheetDescription>
                            {release.environment} {copy.separator}{' '}
                            {release.changeReference}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <Detail
                                    label={copy.artifact_checksum}
                                    value={release.artifactChecksum}
                                />
                                <Detail
                                    label={copy.deployed_by}
                                    value={`${release.deployer ?? 'Unknown'} · ${formatDate(release.deployedAt)}`}
                                />
                                <Detail
                                    label={copy.validated_by}
                                    value={
                                        release.validator
                                            ? `${release.validator} · ${formatDate(release.validatedAt)}`
                                            : 'Pending independent validation'
                                    }
                                />
                                <Detail
                                    label={copy.notes}
                                    value={release.notes ?? '—'}
                                />
                            </>
                        ) : surface === 'validate' ? (
                            <Form
                                action={validate({ release: release.id })}
                                className="grid gap-4"
                            >
                                <TextField
                                    name="evidence"
                                    label={
                                        copy.post_deployment_validation_evidence
                                    }
                                />
                                <Button type="submit">
                                    <ShieldCheck /> {copy.validate_release}
                                </Button>
                            </Form>
                        ) : surface === 'rollback' ? (
                            <Form
                                action={rollback({ release: release.id })}
                                className="grid gap-4"
                            >
                                <SearchableSelect
                                    id={`rollback-target-${release.id}`}
                                    name="rollback_to_version"
                                    label={copy.validated_rollback_target}
                                    options={rollbackTargets.map((target) => ({
                                        id: target.version,
                                        name: `${target.version} · ${target.changeReference}`,
                                    }))}
                                />
                                <TextField
                                    name="reason"
                                    label={copy.rollback_trigger_and_evidence}
                                />
                                <Button type="submit" variant="destructive">
                                    <RotateCcw />{' '}
                                    {copy.record_rollback_decision}
                                </Button>
                            </Form>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function RegisterHeader({
    filters,
    total,
}: {
    filters: Props['filters'];
    total: number;
}) {
    const copy = usePage().props.localization.operations.ui;

    return (
        <div className="flex items-center justify-between border-b px-5 py-4 sm:px-6">
            <div>
                <h2 className="font-bold">{copy.backup_restore_evidence}</h2>
                <p className="text-sm text-muted-foreground">
                    {total.toLocaleString()} {copy.recovery_artifacts}
                </p>
            </div>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <Download /> {copy.export}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a
                                href={exportMethod.url(
                                    { workspace: 'operations', format },
                                    { query: filters },
                                )}
                            >
                                {format.toUpperCase()}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
function MetricCard({
    title,
    value,
    detail,
    status,
}: {
    title: string;
    value: string;
    detail: string;
    status: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex items-center justify-between">
                    <p className="text-2xl font-bold">{value}</p>
                    <Badge
                        variant={status === 'fail' ? 'destructive' : 'outline'}
                    >
                        {status}
                    </Badge>
                </div>
                <p className="mt-2 text-xs text-muted-foreground">{detail}</p>
            </CardContent>
        </Card>
    );
}
function Field({
    name,
    label,
    type = 'text',
    optional = false,
}: {
    name: string;
    label: string;
    type?: 'text' | 'number';
    optional?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} name={name} type={type} required={!optional} />
        </div>
    );
}
function TextField({
    name,
    label,
    optional = false,
}: {
    name: string;
    label: string;
    optional?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Textarea id={name} name={name} rows={4} required={!optional} />
        </div>
    );
}
function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 text-sm break-words">{value}</p>
        </div>
    );
}
function option(id: string) {
    return { id, name: humanize(id) };
}
function humanize(value: string) {
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replaceAll('.', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}
function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString(DEFAULT_LOCALE) : '—';
}
function formatBytes(value: number | null) {
    if (value === null) {
        return '—';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1_048_576) {
        return `${(value / 1024).toFixed(1)} KB`;
    }

    return `${(value / 1_048_576).toFixed(1)} MB`;
}
