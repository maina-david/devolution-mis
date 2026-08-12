import { Form, Head } from '@inertiajs/react';
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
            <Head title="Operational readiness" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Service assurance and recovery
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Operational readiness centre
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                Dependency probes, SLO measurements, checksummed
                                backups, isolated restore evidence, scheduled
                                controls, and independently validated release
                                and rollback history.
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
                        title="Readiness"
                        value={readiness.ready ? 'Ready' : 'Not ready'}
                        detail={`${Object.values(readiness.checks).filter((check) => check.status === 'pass').length}/${Object.keys(readiness.checks).length} dependencies passing`}
                        status={readiness.ready ? 'pass' : 'fail'}
                    />
                    <MetricCard
                        title="Availability target"
                        value={`${targets.availabilityPercent}%`}
                        detail="Provisional target pending service-owner approval"
                        status="info"
                    />
                    <MetricCard
                        title="Recovery point"
                        value={`${targets.rpoMinutes} min`}
                        detail={`Backup evidence must be newer than ${targets.backupMaxAgeMinutes} minutes`}
                        status="info"
                    />
                    <MetricCard
                        title="Recovery time"
                        value={`${targets.rtoMinutes} min`}
                        detail="Restore exercise target pending Konza validation"
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
                                    {check.latency_ms ?? '—'} ms
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
                            label: 'Backup status',
                            options: ['running', 'completed', 'failed'].map(
                                option,
                            ),
                            value: filters.status,
                        },
                    ]}
                />
                <section className="overflow-hidden rounded-xl border bg-card">
                    <div className="border-b px-5 py-4 sm:px-6">
                        <h2 className="font-bold">Operational alerts</h2>
                        <p className="text-sm text-muted-foreground">
                            {operationalAlerts.total.toLocaleString()} governed
                            threshold alerts with deduplicated recurrence,
                            acknowledgement and automatic recovery evidence.
                            Thresholds remain provisional until service-owner
                            approval.
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
                            title="No operational alerts"
                            description="Warning and failure measurements will open a deduplicated alert here; passing measurements automatically retain recovery evidence."
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
                            title="No backup evidence"
                            description="Request the first backup or wait for the scheduled daily control."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="grid gap-4 xl:grid-cols-2">
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <div className="border-b px-5 py-4 sm:px-6">
                            <h2 className="font-bold">Failed queue jobs</h2>
                            <p className="text-sm text-muted-foreground">
                                {failedJobs.total.toLocaleString()} retained
                                failures. Payload and exception contents remain
                                hidden; operators receive checksums and safe
                                classifications.
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
                                title="No failed queue jobs"
                                description="No database, notification, report, extraction or backup job is awaiting recovery."
                                className="min-h-64 border-0"
                            />
                        )}
                    </div>
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <div className="border-b px-5 py-4 sm:px-6">
                            <h2 className="font-bold">
                                Immutable recovery evidence
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Latest operator-attributed requeue outcomes;
                                successful jobs may leave the failed register,
                                but this evidence remains.
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
                                title="No recovery attempts"
                                description="Recovery evidence appears after an authorized operator retries a retained failed job."
                                className="min-h-64 border-0"
                            />
                        )}
                    </div>
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <div className="border-b px-5 py-4 sm:px-6">
                        <h2 className="font-bold">
                            Performance assurance evidence
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {performanceRuns.total.toLocaleString()} immutable,
                            checksum-bound HTTP concurrency runs. Thresholds are
                            environment snapshots and do not constitute Konza
                            production certification.
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
                            title="No performance evidence"
                            description="Run the bounded operations performance probe in an approved environment to establish the first baseline."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="grid gap-4 lg:grid-cols-2">
                    <div className="grid content-start gap-4">
                        <div>
                            <h2 className="font-bold">
                                Release and rollback evidence
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Deployments require independent validation
                                before they become approved rollback targets.
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
                                title="No release evidence"
                                description="Record a reproducible artifact deployment for independent validation."
                            />
                        )}
                    </div>
                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Activity className="size-4" /> Latest
                                    service measurements
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
                                                {measurement.service} ·{' '}
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
                                        Measurements will appear after the
                                        scheduled operational probe.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Gauge className="size-4" /> Scheduled
                                    controls
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
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${humanize(alert.metric)} alert`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        <Eye /> View alert evidence
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{humanize(alert.metric)}</SheetTitle>
                        <SheetDescription>
                            {humanize(alert.service)} ·{' '}
                            {humanize(alert.severity)}
                            {' · '}
                            {humanize(alert.status)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-5 px-4 pb-6">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <EvidenceField
                                label="Latest value"
                                value={`${alert.latestValue} ${alert.unit}`}
                            />
                            <EvidenceField
                                label="Provisional threshold"
                                value={
                                    alert.threshold
                                        ? `${alert.threshold} ${alert.unit}`
                                        : 'Not configured'
                                }
                            />
                            <EvidenceField
                                label="Occurrences"
                                value={alert.occurrenceCount.toLocaleString()}
                            />
                            <EvidenceField
                                label="Last detected"
                                value={formatDate(alert.lastDetectedAt)}
                            />
                            <EvidenceField
                                label="Acknowledged by"
                                value={alert.acknowledgedBy ?? 'Pending'}
                            />
                            <EvidenceField
                                label="Recovered"
                                value={formatDate(alert.recoveredAt)}
                            />
                        </div>
                        {alert.acknowledgementNote && (
                            <EvidenceField
                                label="Acknowledgement note"
                                value={alert.acknowledgementNote}
                            />
                        )}
                        <EvidenceField
                            label="Alert evidence checksum"
                            value={alert.evidenceChecksum}
                            mono
                        />
                        <div className="flex flex-col gap-3">
                            <div>
                                <h3 className="font-medium">
                                    Immutable timeline
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Showing the latest {alert.events.length} of{' '}
                                    {alert.eventCount.toLocaleString()} retained
                                    events.
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
                                                Accountable response note
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
                                                placeholder="Record the immediate response, owner and next control action."
                                            />
                                            <InputError message={errors.note} />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Acknowledge alert
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
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for performance run ${run.id}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        <Eye /> View evidence
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Performance run evidence</SheetTitle>
                        <SheetDescription>
                            {run.routePath} · {formatDate(run.startedAt)} ·{' '}
                            {run.environment}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 px-4 pb-6">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <EvidenceField
                                label="Outcome"
                                value={humanize(run.outcome)}
                            />
                            <EvidenceField label="Tool" value={run.tool} />
                            <EvidenceField
                                label="Requests / concurrency"
                                value={`${run.requestCount} / ${run.concurrency}`}
                            />
                            <EvidenceField
                                label="Successful / failed"
                                value={`${run.successfulRequests} / ${run.failedRequests}`}
                            />
                            <EvidenceField
                                label="Requests per second"
                                value={run.requestsPerSecond ?? 'Unavailable'}
                            />
                            <EvidenceField
                                label="Mean latency"
                                value={formatMetric(run.meanLatencyMs, 'ms')}
                            />
                            <EvidenceField
                                label="P50 / P95 / P99"
                                value={`${formatMetric(run.p50LatencyMs, 'ms')} / ${formatMetric(run.p95LatencyMs, 'ms')} / ${formatMetric(run.p99LatencyMs, 'ms')}`}
                            />
                            <EvidenceField
                                label="Wall-clock duration"
                                value={`${run.durationMs.toLocaleString()} ms`}
                            />
                            <EvidenceField
                                label="Initiated by"
                                value={run.initiatedBy}
                            />
                            <EvidenceField
                                label="Target"
                                value={run.targetUrl}
                            />
                        </div>
                        <div className="grid gap-2">
                            <p className="text-sm font-medium">
                                Threshold snapshot
                            </p>
                            <pre className="overflow-x-auto rounded-lg border bg-muted/40 p-3 text-xs">
                                {JSON.stringify(run.thresholdSnapshot, null, 2)}
                            </pre>
                        </div>
                        <EvidenceField
                            label="Output checksum"
                            value={run.outputChecksum}
                            mono
                        />
                        <EvidenceField
                            label="Evidence checksum"
                            value={run.evidenceChecksum}
                            mono
                        />
                        {run.errorCategory && (
                            <EvidenceField
                                label="Failure classification"
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
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for failed job ${job.uuid}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        <Eye /> View recovery evidence
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{job.jobName}</SheetTitle>
                        <SheetDescription>
                            {job.connection} · {job.queue} · failed{' '}
                            {formatDate(job.failedAt)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        <Detail label="Failed job UUID" value={job.uuid} />
                        <Detail
                            label="Safe failure category"
                            value={job.exceptionCategory}
                        />
                        <Detail
                            label="Payload checksum"
                            value={job.payloadChecksum}
                        />
                        <Detail
                            label="Exception checksum"
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
                                            Requeue the retained payload without
                                            exposing it. The original failure
                                            leaves the active register only
                                            after the queue accepts it, and an
                                            immutable attributed attempt is
                                            retained either way.
                                        </p>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <RotateCcw /> Retry failed job
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
    return (
        <FormSheet
            title="Request database backup"
            description="Queue a checksummed PostgreSQL custom-format backup on the configured private backup disk."
            triggerLabel="Request backup"
            icon={ArchiveRestore}
        >
            <Form action={requestBackup()} className="grid gap-4 pt-4">
                <p className="text-sm text-muted-foreground">
                    The queue worker will record artifact size, SHA-256
                    checksum, timestamps and any failure. Restore verification
                    is a separate controlled action.
                </p>
                <Button type="submit">
                    <ArchiveRestore /> Queue backup
                </Button>
            </Form>
        </FormSheet>
    );
}

function ReleaseForm() {
    return (
        <FormSheet
            title="Record deployment"
            description="Register the exact artifact and migration state for independent post-deployment validation."
            triggerLabel="Record release"
            icon={Plus}
            size="xl"
        >
            <Form action={storeRelease()} className="grid gap-5 pt-4">
                {({ processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field name="version" label="Release version" />
                            <Field
                                name="git_sha"
                                label="Git commit SHA (40 hex)"
                            />
                            <SearchableSelect
                                id="release-environment"
                                name="environment"
                                label="Environment"
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
                                label="Artifact SHA-256"
                            />
                            <Field
                                name="change_reference"
                                label="Approved change reference"
                            />
                            <Field
                                name="migration_batch"
                                label="Migration batch"
                                type="number"
                                optional
                            />
                        </div>
                        <DatePickerField
                            name="deployed_at"
                            label="Deployment date and time"
                            includeTime
                            required
                        />
                        <TextField
                            name="notes"
                            label="Deployment evidence and observations"
                            optional
                        />
                        <Button type="submit" disabled={processing}>
                            Record deployment
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
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="ghost"
                size="icon"
                onClick={() => setOpen(true)}
                aria-label={`Open backup ${backup.reference}`}
            >
                <MoreHorizontal />
            </Button>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{backup.reference}</SheetTitle>
                        <SheetDescription>
                            {backup.database} · {humanize(backup.status)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        <Detail
                            label="Artifact checksum"
                            value={backup.sha256 ?? 'Unavailable'}
                        />
                        <Detail
                            label="Artifact size"
                            value={formatBytes(backup.sizeBytes)}
                        />
                        <Detail
                            label="Restore verification"
                            value={
                                backup.restoreVerifiedAt
                                    ? `${formatDate(backup.restoreVerifiedAt)} · ${backup.verifiedTableCount} tables · ${backup.restoreDurationMs} ms`
                                    : 'Not yet verified'
                            }
                        />
                        {backup.errorDetail && (
                            <Detail
                                label="Failure"
                                value={backup.errorDetail}
                            />
                        )}
                        {canManage && backup.status === 'completed' && (
                            <Form
                                action={verifyBackup({ backup: backup.id })}
                                className="grid gap-4 rounded-lg border p-4"
                            >
                                <p className="text-sm">
                                    Queue an isolated restore into a generated
                                    temporary database. The verifier counts
                                    restored tables and drops only that
                                    validated temporary target.
                                </p>
                                <Button type="submit">
                                    <ShieldCheck /> Verify isolated restore
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
                            {release.version} · {humanize(release.environment)}
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
                                aria-label={`Actions for release ${release.version}`}
                            >
                                <MoreHorizontal />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onSelect={() => setSurface('details')}
                            >
                                <Eye /> View evidence
                            </DropdownMenuItem>
                            {canManage && release.status === 'deployed' && (
                                <DropdownMenuItem
                                    onSelect={() => setSurface('validate')}
                                >
                                    <ShieldCheck /> Independently validate
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
                                        <RotateCcw /> Record rollback
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
                            {release.environment} · {release.changeReference}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <Detail
                                    label="Artifact checksum"
                                    value={release.artifactChecksum}
                                />
                                <Detail
                                    label="Deployed by"
                                    value={`${release.deployer ?? 'Unknown'} · ${formatDate(release.deployedAt)}`}
                                />
                                <Detail
                                    label="Validated by"
                                    value={
                                        release.validator
                                            ? `${release.validator} · ${formatDate(release.validatedAt)}`
                                            : 'Pending independent validation'
                                    }
                                />
                                <Detail
                                    label="Notes"
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
                                    label="Post-deployment validation evidence"
                                />
                                <Button type="submit">
                                    <ShieldCheck /> Validate release
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
                                    label="Validated rollback target"
                                    options={rollbackTargets.map((target) => ({
                                        id: target.version,
                                        name: `${target.version} · ${target.changeReference}`,
                                    }))}
                                />
                                <TextField
                                    name="reason"
                                    label="Rollback trigger and evidence"
                                />
                                <Button type="submit" variant="destructive">
                                    <RotateCcw /> Record rollback decision
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
    return (
        <div className="flex items-center justify-between border-b px-5 py-4 sm:px-6">
            <div>
                <h2 className="font-bold">Backup and restore evidence</h2>
                <p className="text-sm text-muted-foreground">
                    {total.toLocaleString()} recovery artifacts
                </p>
            </div>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <Download /> Export
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
