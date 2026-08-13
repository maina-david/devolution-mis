import { Form, Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    DatabaseBackup,
    Download,
    Eye,
    FileSpreadsheet,
    FileUp,
    MoreHorizontal,
    PlayCircle,
    Scale,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
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
import { apply, download, index, review, store } from '@/routes/data-migrations';
import { download as downloadExceptions } from '@/routes/data-migrations/exceptions';
import {
    apply as applyLineageDisposition,
    review as reviewLineageDisposition,
    store as storeLineageDisposition,
} from '@/routes/data-migrations/lineage-dispositions';
import { store as storeReferenceData } from '@/routes/data-migrations/reference-data';
import { show as showTemplate } from '@/routes/data-migrations/templates';

type Batch = {
    id: string;
    reference: string;
    datasetType: string;
    sourceName: string;
    sourceReference: string;
    periodFrom: string;
    periodTo: string;
    originalName: string;
    fileChecksum: string;
    status: string;
    totalRows: number;
    validRows: number;
    invalidRows: number;
    errorCounts: Record<string, number>;
    referenceDataRelease: {
        id: string;
        version: number;
        status: string;
        checksum: string;
    } | null;
    submittedBy: string;
    reviewedBy: string | null;
    reviewNotes: string | null;
    appliedBy: string | null;
    createdAt: string;
    reviewedAt: string | null;
    appliedAt: string | null;
};

type PageSet<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type LineageDisposition = {
    id: string;
    reference: string;
    recordType: string;
    recordId: string;
    recordSnapshot: Record<string, unknown>;
    recordChecksum: string;
    decision: string;
    release: { id: string; version: number; checksum: string } | null;
    successorRecordType: string | null;
    successorRecordId: string | null;
    businessReason: string;
    sourceReference: string;
    status: string;
    proposedBy: string;
    reviewedBy: string | null;
    reviewNotes: string | null;
    appliedBy: string | null;
    decisionChecksum: string;
    createdAt: string;
    reviewedAt: string | null;
    appliedAt: string | null;
};

type Props = {
    batches: PageSet<Batch>;
    filters: Record<string, string | undefined>;
    capabilities: { stage: boolean; review: boolean; apply: boolean };
    legacyType: string;
    legacyCandidates: Array<{
        id: string;
        label: string;
        snapshot: Record<string, unknown>;
    }>;
    referenceReleases: Array<{
        id: string;
        version: number;
        checksum: string;
        effectiveFrom: string | null;
    }>;
    lineageDispositions: PageSet<LineageDisposition>;
    lineageTranslations: Record<string, string>;
    legacyInventory: {
        total: number;
        recordTypes: number;
        records: Array<{
            key: string;
            type: string;
            model: string;
            count: number;
            pending: number;
            applied: number;
            oldestAt: string | null;
            latestAt: string | null;
        }>;
    };
};

const datasetOptions = [
    { id: 'acpa_scores', name: 'ACPA scores' },
    { id: 'acpa_reconstruction', name: 'Legacy ACPA reconstruction' },
    { id: 'performance_metrics', name: 'Performance metrics' },
    { id: 'evaluation_baselines', name: 'Evaluation baselines' },
];

const referenceDatasetOptions = [
    { id: 'counties', name: 'Counties' },
    { id: 'organizations', name: 'Organizations' },
    { id: 'sectors', name: 'Sectors' },
    { id: 'programmes', name: 'Programmes' },
    {
        id: 'programme_county_coverages',
        name: 'Programme county coverages',
    },
    { id: 'users', name: 'Users and role assignments' },
    { id: 'sub_counties', name: 'Sub-counties' },
    { id: 'wards', name: 'Wards' },
];

const statusOptions = [
    { id: 'validated', name: 'Validated' },
    { id: 'validation_failed', name: 'Validation failed' },
    { id: 'approved', name: 'Approved' },
    { id: 'rejected', name: 'Rejected' },
    { id: 'applied', name: 'Applied' },
];

export default function HistoricalDataMigrations({
    batches,
    filters,
    capabilities,
    legacyInventory,
    legacyType,
    legacyCandidates,
    referenceReleases,
    lineageDispositions,
    lineageTranslations: t,
}: Props) {
    const [selected, setSelected] = useState<Batch | null>(null);
    const [action, setAction] = useState<'details' | 'review' | 'apply'>(
        'details',
    );
    const [selectedDisposition, setSelectedDisposition] =
        useState<LineageDisposition | null>(null);
    const [dispositionAction, setDispositionAction] = useState<
        'details' | 'review' | 'apply'
    >('details');

    const rows: WorkspaceRow[] = batches.data.map((batch) => ({
        id: batch.id,
        status: batch.status,
        cells: [
            batch.reference,
            humanize(batch.datasetType),
            batch.sourceName,
            `${formatDate(batch.periodFrom)} – ${formatDate(batch.periodTo)}`,
            batch.totalRows,
            batch.invalidRows,
            humanize(batch.status),
            batch.submittedBy,
            formatDateTime(batch.createdAt),
        ],
    }));
    const pagination: WorkspacePagination = {
        currentPage: batches.current_page,
        lastPage: batches.last_page,
        perPage: batches.per_page,
        total: batches.total,
    };
    const open = (batch: Batch, nextAction: typeof action) => {
        setSelected(batch);
        setAction(nextAction);
    };

    return (
        <>
            <Head title="Bulk data imports" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] uppercase opacity-75">
                                Controlled provenance and reconciliation
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Bulk data imports
                            </h1>
                            <p className="mt-3 max-w-2xl opacity-80">
                                Validate, approve and atomically apply governed
                                operational registers and historical result
                                datasets from checksum-retained CSV or XLSX
                                sources.
                            </p>
                        </div>
                        {capabilities.stage && (
                            <div className="flex flex-wrap gap-2">
                                <ReferenceImportForm />
                                <MigrationForm />
                            </div>
                        )}
                    </div>
                </section>

                <Alert>
                    <DatabaseBackup />
                    <AlertTitle>Three-person control</AlertTitle>
                    <AlertDescription>
                        Upload, independent review and final application must be
                        performed by different authorized users. Applied records
                        and their checksum-bound provenance are immutable.
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                            <div>
                                <CardTitle>{t.inventory_title}</CardTitle>
                                <CardDescription>
                                    {t.inventory_description}
                                </CardDescription>
                            </div>
                            {capabilities.stage && legacyInventory.total > 0 && (
                                <LineageDispositionForm
                                    recordType={legacyType}
                                    candidates={legacyCandidates}
                                    releases={referenceReleases}
                                    t={t}
                                />
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {legacyInventory.total === 0 ? (
                            <WorkspaceEmptyState
                                title="No unpinned records detected"
                                description="All inventoried product records carry governed reference-release lineage."
                            />
                        ) : (
                            <div className="grid gap-4 lg:grid-cols-[12rem_1fr]">
                                <dl className="rounded-lg border p-4">
                                    <dt className="text-sm text-muted-foreground">
                                        Records requiring disposition
                                    </dt>
                                    <dd className="mt-1 text-3xl font-semibold">
                                        {legacyInventory.total}
                                    </dd>
                                    <dt className="mt-4 text-sm text-muted-foreground">
                                        Record types
                                    </dt>
                                    <dd className="font-semibold">
                                        {legacyInventory.recordTypes}
                                    </dd>
                                </dl>
                                <div className="space-y-3">
                                    <SearchableSelect
                                        id="legacy-record-type"
                                        name="legacy_type"
                                        label={t.record_type}
                                        value={legacyType}
                                        options={legacyInventory.records.map(
                                            (record) => ({
                                                id: record.key,
                                                name: `${record.type} (${record.count})`,
                                            }),
                                        )}
                                        onValueChange={(value) =>
                                            router.get(
                                                index.url(),
                                                {
                                                    ...filters,
                                                    legacy_type: value,
                                                },
                                                {
                                                    preserveScroll: true,
                                                    preserveState: true,
                                                },
                                            )
                                        }
                                    />
                                <ul className="divide-y rounded-lg border">
                                    {legacyInventory.records.map((record) => (
                                        <li
                                            key={record.model}
                                            className="flex items-center justify-between gap-4 p-3 text-sm"
                                        >
                                            <span>{record.type}</span>
                                            <span className="flex gap-3 text-xs">
                                                <strong>{record.count} {t.open}</strong>
                                                <span className="text-muted-foreground">{record.pending} {t.pending}</span>
                                                <span className="text-muted-foreground">{record.applied} {t.applied}</span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t.register_title}</CardTitle>
                        <CardDescription>
                            {t.register_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {lineageDispositions.total === 0 ? (
                            <WorkspaceEmptyState
                                title={t.empty_title}
                                description={t.empty_description}
                            />
                        ) : (
                            <WorkspaceDataTable
                                columns={[
                                    t.reference,
                                    t.record_type_value,
                                    t.decision,
                                    t.catalogue,
                                    t.status,
                                    t.proposed_by,
                                    t.created_at,
                                ]}
                                rows={lineageDispositions.data.map(
                                    (disposition) => ({
                                        id: disposition.id,
                                        status: disposition.status,
                                        cells: [
                                            disposition.reference,
                                            humanize(disposition.recordType),
                                            humanize(disposition.decision),
                                            disposition.release
                                                ? `v${disposition.release.version}`
                                                : t.not_pinned,
                                            humanize(disposition.status),
                                            disposition.proposedBy,
                                            formatDateTime(
                                                disposition.createdAt,
                                            ),
                                        ],
                                    }),
                                )}
                                pagination={{
                                    currentPage:
                                        lineageDispositions.current_page,
                                    lastPage: lineageDispositions.last_page,
                                    perPage: lineageDispositions.per_page,
                                    total: lineageDispositions.total,
                                    pageName: 'disposition_page',
                                    perPageName: 'disposition_per_page',
                                }}
                                renderActionControl={(row) => {
                                    const disposition =
                                        lineageDispositions.data.find(
                                            (item) => item.id === row.id,
                                        );

                                    if (!disposition) {
                                        return null;
                                    }

                                    const openDisposition = (
                                        nextAction:
                                            | 'details'
                                            | 'review'
                                            | 'apply',
                                    ) => {
                                        setSelectedDisposition(disposition);
                                        setDispositionAction(nextAction);
                                    };

                                    return (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={translate(t.actions_for, { reference: disposition.reference })}
                                                >
                                                    <MoreHorizontal />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        openDisposition(
                                                            'details',
                                                        )
                                                    }
                                                >
                                                    <Eye /> {t.view_details}
                                                </DropdownMenuItem>
                                                {capabilities.review &&
                                                    disposition.status ===
                                                        'proposed' && (
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                openDisposition(
                                                                    'review',
                                                                )
                                                            }
                                                        >
                                                            <CheckCircle2 />
                                                            {t.review_decision}
                                                        </DropdownMenuItem>
                                                    )}
                                                {capabilities.apply &&
                                                    disposition.status ===
                                                        'approved' && (
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                openDisposition(
                                                                    'apply',
                                                                )
                                                            }
                                                        >
                                                            <PlayCircle /> Apply
                                                            {t.apply_disposition}
                                                        </DropdownMenuItem>
                                                    )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    );
                                }}
                            />
                        )}
                    </CardContent>
                </Card>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    searchPlaceholder="Search migration reference or source"
                    selectFilters={[
                        {
                            key: 'type',
                            label: 'Dataset type',
                            options: [
                                ...datasetOptions,
                                ...referenceDatasetOptions,
                            ],
                            value: filters.type,
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            options: statusOptions,
                            value: filters.status,
                        },
                    ]}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Migration batches</CardTitle>
                        <CardDescription>
                            Private source files, validation outcomes and
                            maker-checker application history.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {batches.total === 0 ? (
                            <WorkspaceEmptyState
                                title="No migration batches found"
                                description="Adjust the filters or stage an authorized historical CSV source."
                            />
                        ) : (
                            <WorkspaceDataTable
                                columns={[
                                    'Reference',
                                    'Dataset',
                                    'Source',
                                    'Period',
                                    'Rows',
                                    'Exceptions',
                                    'Status',
                                    'Submitted by',
                                    'Staged at',
                                ]}
                                rows={rows}
                                pagination={pagination}
                                renderActionControl={(row) => {
                                    const batch = batches.data.find(
                                        (candidate) => candidate.id === row.id,
                                    );

                                    if (!batch) {
                                        return null;
                                    }

                                    return (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Actions for ${batch.reference}`}
                                                >
                                                    <MoreHorizontal />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuGroup>
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            open(
                                                                batch,
                                                                'details',
                                                            )
                                                        }
                                                    >
                                                        <Eye /> View details
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <a
                                                            href={download.url({
                                                                dataMigrationBatch:
                                                                    batch.id,
                                                            })}
                                                        >
                                                            <Download />{' '}
                                                            Download source
                                                        </a>
                                                    </DropdownMenuItem>
                                                    {batch.invalidRows > 0 && (
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <a
                                                                href={downloadExceptions.url(
                                                                    {
                                                                        dataMigrationBatch:
                                                                            batch.id,
                                                                    },
                                                                )}
                                                            >
                                                                <Download />
                                                                Download row
                                                                exceptions
                                                            </a>
                                                        </DropdownMenuItem>
                                                    )}
                                                    {capabilities.review &&
                                                        [
                                                            'validated',
                                                            'validation_failed',
                                                        ].includes(
                                                            batch.status,
                                                        ) && (
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    open(
                                                                        batch,
                                                                        'review',
                                                                    )
                                                                }
                                                            >
                                                                <CheckCircle2 />
                                                                Record review
                                                            </DropdownMenuItem>
                                                        )}
                                                    {capabilities.apply &&
                                                        batch.status ===
                                                            'approved' && (
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    open(
                                                                        batch,
                                                                        'apply',
                                                                    )
                                                                }
                                                            >
                                                                <PlayCircle />{' '}
                                                                Apply records
                                                            </DropdownMenuItem>
                                                        )}
                                                </DropdownMenuGroup>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    );
                                }}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>

            <BatchSheet
                batch={selected}
                action={action}
                onOpenChange={(isOpen) => !isOpen && setSelected(null)}
            />
            <LineageDispositionSheet
                disposition={selectedDisposition}
                action={dispositionAction}
                onOpenChange={(isOpen) =>
                    !isOpen && setSelectedDisposition(null)
                }
                t={t}
            />
        </>
    );
}

function LineageDispositionForm({
    recordType,
    candidates,
    releases,
    t,
}: {
    recordType: string;
    candidates: Props['legacyCandidates'];
    releases: Props['referenceReleases'];
    t: Props['lineageTranslations'];
}) {
    const [decision, setDecision] = useState('retain_legacy');
    const [successorId, setSuccessorId] = useState('');

    if (candidates.length === 0) {
        return null;
    }

    return (
        <FormSheet
            title={t.proposal_title}
            description={t.proposal_description}
            triggerLabel={t.reconcile}
            icon={Scale}
            size="lg"
        >
            <Form {...storeLineageDisposition.form()} resetOnSuccess>
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <input
                            type="hidden"
                            name="record_type"
                            value={recordType}
                        />
                        <SearchableSelect
                            id="lineage-record"
                            name="record_id"
                            label={t.record}
                            options={candidates.map((candidate) => ({
                                id: candidate.id,
                                name: candidate.label,
                            }))}
                            error={errors.record_id}
                        />
                        <SearchableSelect
                            id="lineage-decision"
                            name="decision"
                            label={t.disposition_decision}
                            value={decision}
                            onValueChange={setDecision}
                            options={[
                                {
                                    id: 'retain_legacy',
                                    name: t.retain_legacy,
                                },
                                {
                                    id: 'pin_release',
                                    name: t.pin_release,
                                },
                                {
                                    id: 'deprecate',
                                    name: t.deprecate,
                                },
                            ]}
                            error={errors.decision}
                        />
                        {decision === 'pin_release' && (
                            <SearchableSelect
                                id="lineage-release"
                                name="reference_data_release_id"
                                label={t.published_release}
                                options={releases.map((release) => ({
                                    id: release.id,
                                    name: translate(t.version, {
                                        version: String(release.version),
                                    }),
                                }))}
                                error={errors.reference_data_release_id}
                            />
                        )}
                        <SearchableSelect
                            id="lineage-successor"
                            name="successor_record_id"
                            label={t.successor}
                            optional
                            value={successorId}
                            onValueChange={setSuccessorId}
                            options={candidates.map((candidate) => ({
                                id: candidate.id,
                                name: candidate.label,
                            }))}
                            error={errors.successor_record_id}
                        />
                        {successorId !== '' && (
                            <input
                                type="hidden"
                                name="successor_record_type"
                                value={recordType}
                            />
                        )}
                        <TextField
                            id="lineage-source-reference"
                            name="source_reference"
                            label={t.source_reference}
                            error={errors.source_reference}
                        />
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="lineage-business-reason">
                                {t.rationale}
                            </Label>
                            <Textarea
                                id="lineage-business-reason"
                                name="business_reason"
                                rows={5}
                                aria-invalid={Boolean(errors.business_reason)}
                                required
                            />
                            {errors.business_reason && (
                                <ErrorText>{errors.business_reason}</ErrorText>
                            )}
                        </div>
                        <Button type="submit" disabled={processing}>
                            <Scale data-icon="inline-start" />
                            {processing
                                ? t.recording
                                : t.submit_review}
                        </Button>
                    </div>
                )}
            </Form>
        </FormSheet>
    );
}

function LineageDispositionSheet({
    disposition,
    action,
    onOpenChange,
    t,
}: {
    disposition: LineageDisposition | null;
    action: 'details' | 'review' | 'apply';
    onOpenChange: (open: boolean) => void;
    t: Props['lineageTranslations'];
}) {
    return (
        <Sheet open={Boolean(disposition)} onOpenChange={onOpenChange}>
            <SheetContent className="overflow-y-auto sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>
                        {action === 'review'
                            ? t.review_title
                            : action === 'apply'
                              ? t.apply_title
                              : t.details_title}
                    </SheetTitle>
                    <SheetDescription>
                        {translate(t.checksum_bound, {
                            reference: disposition?.reference ?? '',
                        })}
                    </SheetDescription>
                </SheetHeader>
                {disposition && (
                    <div className="flex flex-col gap-5 px-4 pb-8">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Detail
                                label={t.record_type_value}
                                value={humanize(disposition.recordType)}
                            />
                            <Detail
                                label={t.decision}
                                value={humanize(disposition.decision)}
                            />
                            <Detail
                                label={t.status}
                                value={humanize(disposition.status)}
                            />
                            <Detail
                                label={t.catalogue}
                                value={
                                    disposition.release
                                        ? translate(t.version, {
                                              version: String(
                                                  disposition.release.version,
                                              ),
                                          })
                                        : t.explicitly_unpinned
                                }
                            />
                            <Detail
                                label={t.proposed_by}
                                value={disposition.proposedBy}
                            />
                            <Detail
                                label={t.reviewed_by}
                                value={disposition.reviewedBy ?? t.pending}
                            />
                            <Detail
                                label={t.applied_by}
                                value={disposition.appliedBy ?? t.pending}
                            />
                            <Detail
                                label={t.source_reference}
                                value={disposition.sourceReference}
                            />
                        </div>
                        <Detail
                            label={t.business_rationale}
                            value={disposition.businessReason}
                        />
                        {disposition.successorRecordId && (
                            <Detail
                                label={t.controlled_successor}
                                value={`${humanize(disposition.successorRecordType ?? '')} · ${disposition.successorRecordId}`}
                            />
                        )}
                        <div>
                            <p className="text-xs font-medium text-muted-foreground">
                                {t.source_checksum}
                            </p>
                            <p className="mt-1 font-mono text-xs break-all">
                                {disposition.recordChecksum}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-medium text-muted-foreground">
                                {t.decision_checksum}
                            </p>
                            <p className="mt-1 font-mono text-xs break-all">
                                {disposition.decisionChecksum}
                            </p>
                        </div>
                        {action === 'review' && (
                            <Form
                                {...reviewLineageDisposition.form(
                                    disposition.id,
                                )}
                            >
                                {({ errors, processing }) => (
                                    <div className="flex flex-col gap-4 rounded-lg border p-4">
                                        <SearchableSelect
                                            id="lineage-review-decision"
                                            name="decision"
                                            label={t.review_decision}
                                            options={[
                                                {
                                                    id: 'approve',
                                                    name: t.approve,
                                                },
                                                {
                                                    id: 'reject',
                                                    name: t.reject,
                                                },
                                            ]}
                                            error={errors.decision}
                                        />
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="lineage-review-notes">
                                                {t.independent_notes}
                                            </Label>
                                            <Textarea
                                                id="lineage-review-notes"
                                                name="notes"
                                                rows={5}
                                                aria-invalid={Boolean(
                                                    errors.notes,
                                                )}
                                                required
                                            />
                                            {errors.notes && (
                                                <ErrorText>
                                                    {errors.notes}
                                                </ErrorText>
                                            )}
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t.record_independent_decision}
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        )}
                        {action === 'apply' && (
                            <Form
                                {...applyLineageDisposition.form(
                                    disposition.id,
                                )}
                            >
                                {({ processing }) => (
                                    <Alert>
                                        <PlayCircle />
                                        <AlertTitle>
                                            {t.final_application}
                                        </AlertTitle>
                                        <AlertDescription>
                                            {t.final_application_description}
                                        </AlertDescription>
                                        <Button
                                            className="mt-4"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t.apply_disposition}
                                        </Button>
                                    </Alert>
                                )}
                            </Form>
                        )}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

function MigrationForm() {
    const [datasetType, setDatasetType] = useState('acpa_scores');
    const requiredColumns =
        datasetType === 'acpa_reconstruction'
            ? 'county_code, assessment_reference, period, record_type, record_reference, criterion_code, title, numeric_value, maximum_value, status, assignment_role, person_identifier, person_name, description, decision, file_name, mime_type, file_checksum, source_reference'
            : 'county_code, period, metric_code, metric_name, numeric_value, narrative_value, unit, source_reference';

    return (
        <FormSheet
            title="Stage historical source"
            description="Upload an authorized CSV or XLSX source for row-level validation and county reconciliation. No record is applied at this stage."
            triggerLabel="Upload historical data"
            icon={FileUp}
            size="lg"
        >
            <Form {...store.form()} resetOnSuccess>
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="migration-file">
                                CSV or XLSX source
                            </Label>
                            <Input
                                id="migration-file"
                                name="file"
                                type="file"
                                accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                aria-invalid={Boolean(errors.file)}
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                Required columns: {requiredColumns}. Maximum
                                5,000 rows and 20 MB. Legacy ACPA files use one
                                assessment header followed by criterion,
                                evidence, finding, assessor and appeal records.
                            </p>
                            {errors.file && (
                                <ErrorText>{errors.file}</ErrorText>
                            )}
                        </div>
                        <StaticSearchableSelect
                            id="migration-dataset-type"
                            name="dataset_type"
                            label="Dataset type"
                            values={datasetOptions.map((option) => option.id)}
                            value={datasetType}
                            onValueChange={setDatasetType}
                            error={errors.dataset_type}
                        />
                        <TemplateDownloadMenu datasetType={datasetType} />
                        <TextField
                            id="migration-source-name"
                            name="source_name"
                            label="Source name"
                            error={errors.source_name}
                        />
                        <TextField
                            id="migration-source-reference"
                            name="source_reference"
                            label="Source reference"
                            error={errors.source_reference}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="period_from"
                                label="Period from"
                                error={errors.period_from}
                            />
                            <DatePickerField
                                name="period_to"
                                label="Period to"
                                error={errors.period_to}
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            <FileUp data-icon="inline-start" />
                            {processing
                                ? 'Validating source…'
                                : 'Stage and validate'}
                        </Button>
                    </div>
                )}
            </Form>
        </FormSheet>
    );
}

function ReferenceImportForm() {
    const [datasetType, setDatasetType] = useState('organizations');

    return (
        <FormSheet
            title="Upload reference data"
            description="Upload a create-only CSV or XLSX template. The file is dry-run validated and cannot change the registry until independent approval and final application."
            triggerLabel="Upload reference data"
            icon={FileUp}
            size="lg"
        >
            <Form {...storeReferenceData.form()} resetOnSuccess>
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <StaticSearchableSelect
                            id="reference-import-dataset-type"
                            name="dataset_type"
                            label="Registry"
                            values={referenceDatasetOptions.map(
                                (option) => option.id,
                            )}
                            value={datasetType}
                            onValueChange={setDatasetType}
                            error={errors.dataset_type}
                        />
                        <TemplateDownloadMenu datasetType={datasetType} />
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="reference-import-file">
                                Completed CSV or XLSX template
                            </Label>
                            <Input
                                id="reference-import-file"
                                name="file"
                                type="file"
                                accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                aria-invalid={Boolean(errors.file)}
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                Maximum 5,000 rows and 20 MB. Existing or
                                duplicate identifiers and overlapping coverage
                                periods are rejected; imports never overwrite
                                current records.
                            </p>
                            {errors.file && (
                                <ErrorText>{errors.file}</ErrorText>
                            )}
                        </div>
                        <TextField
                            id="reference-import-source-name"
                            name="source_name"
                            label="Authoritative source name"
                            error={errors.source_name}
                        />
                        <TextField
                            id="reference-import-source-reference"
                            name="source_reference"
                            label="Approval or source reference"
                            error={errors.source_reference}
                        />
                        <Button type="submit" disabled={processing}>
                            <FileUp data-icon="inline-start" />
                            {processing
                                ? 'Validating file…'
                                : 'Upload and dry-run validate'}
                        </Button>
                    </div>
                )}
            </Form>
        </FormSheet>
    );
}

function TemplateDownloadMenu({ datasetType }: { datasetType: string }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline">
                    <Download data-icon="inline-start" />
                    Download {humanize(datasetType)} template
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuItem asChild>
                    <a href={showTemplate.url({ datasetType })}>
                        <Download /> CSV template
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={showTemplate.url(
                            { datasetType },
                            { query: { format: 'xlsx' } },
                        )}
                    >
                        <FileSpreadsheet /> XLSX template
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function BatchSheet({
    batch,
    action,
    onOpenChange,
}: {
    batch: Batch | null;
    action: 'details' | 'review' | 'apply';
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={Boolean(batch)} onOpenChange={onOpenChange}>
            <SheetContent className="overflow-y-auto sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>
                        {action === 'review'
                            ? 'Review migration batch'
                            : action === 'apply'
                              ? 'Apply immutable records'
                              : 'Migration batch details'}
                    </SheetTitle>
                    <SheetDescription>
                        {batch?.reference} · {batch?.sourceName}
                    </SheetDescription>
                </SheetHeader>
                {batch && (
                    <div className="flex flex-col gap-5 px-4 pb-8">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Detail
                                label="Status"
                                value={humanize(batch.status)}
                            />
                            <Detail
                                label="Dataset"
                                value={humanize(batch.datasetType)}
                            />
                            <Detail
                                label="Valid rows"
                                value={String(batch.validRows)}
                            />
                            <Detail
                                label="Exceptions"
                                value={String(batch.invalidRows)}
                            />
                            <Detail
                                label="Submitted by"
                                value={batch.submittedBy}
                            />
                            <Detail
                                label="Reviewed by"
                                value={batch.reviewedBy ?? 'Pending'}
                            />
                        </div>
                        <div>
                            <p className="text-xs font-medium text-muted-foreground">
                                SHA-256 source checksum
                            </p>
                            <p className="mt-1 font-mono text-xs break-all">
                                {batch.fileChecksum}
                            </p>
                        </div>
                        {batch.referenceDataRelease && (
                            <div className="rounded-lg border bg-muted/30 p-4">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Detail
                                        label="Catalogue release candidate"
                                        value={`v${batch.referenceDataRelease.version}`}
                                    />
                                    <Detail
                                        label="Release status"
                                        value={humanize(
                                            batch.referenceDataRelease.status,
                                        )}
                                    />
                                </div>
                                <p className="mt-4 text-xs font-medium text-muted-foreground">
                                    Release SHA-256 checksum
                                </p>
                                <p className="mt-1 font-mono text-xs break-all">
                                    {batch.referenceDataRelease.checksum}
                                </p>
                                <p className="mt-3 text-xs text-muted-foreground">
                                    This candidate requires independent
                                    publication before it becomes effective.
                                </p>
                            </div>
                        )}
                        {Object.keys(batch.errorCounts).length > 0 && (
                            <Alert variant="destructive">
                                <XCircle />
                                <AlertTitle>Validation exceptions</AlertTitle>
                                <AlertDescription>
                                    {Object.entries(batch.errorCounts)
                                        .map(
                                            ([error, count]) =>
                                                `${humanize(error)}: ${count}`,
                                        )
                                        .join(' · ')}
                                </AlertDescription>
                            </Alert>
                        )}
                        {batch.invalidRows > 0 && (
                            <Button variant="outline" asChild>
                                <a
                                    href={downloadExceptions.url({
                                        dataMigrationBatch: batch.id,
                                    })}
                                >
                                    <Download data-icon="inline-start" />
                                    Download row-level exception report
                                </a>
                            </Button>
                        )}
                        {action === 'review' && (
                            <Form
                                {...review.form({
                                    dataMigrationBatch: batch.id,
                                })}
                            >
                                {({ errors, processing }) => (
                                    <div className="flex flex-col gap-4">
                                        <StaticSearchableSelect
                                            id="migration-review-decision"
                                            name="decision"
                                            label="Decision"
                                            values={['approve', 'reject']}
                                            error={errors.decision}
                                        />
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="migration-review-notes">
                                                Independent review notes
                                            </Label>
                                            <Textarea
                                                id="migration-review-notes"
                                                name="notes"
                                                minLength={10}
                                                required
                                                aria-invalid={Boolean(
                                                    errors.notes,
                                                )}
                                            />
                                            {errors.notes && (
                                                <ErrorText>
                                                    {errors.notes}
                                                </ErrorText>
                                            )}
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Record review decision
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        )}
                        {action === 'apply' && (
                            <Form
                                {...apply.form({
                                    dataMigrationBatch: batch.id,
                                })}
                            >
                                {({ processing }) => (
                                    <div className="flex flex-col gap-4">
                                        <input
                                            type="hidden"
                                            name="confirmation"
                                            value="1"
                                        />
                                        <Alert>
                                            <PlayCircle />
                                            <AlertTitle>
                                                Final controlled application
                                            </AlertTitle>
                                            <AlertDescription>
                                                This atomically creates the
                                                validated records. The operator
                                                must be independent of both
                                                submitter and reviewer.
                                            </AlertDescription>
                                        </Alert>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Apply {batch.validRows} records
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        )}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

function TextField({
    id,
    name,
    label,
    error,
}: {
    id: string;
    name: string;
    label: string;
    error?: string;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} name={name} aria-invalid={Boolean(error)} required />
            {error && <ErrorText>{error}</ErrorText>}
        </div>
    );
}

function ErrorText({ children }: { children: string }) {
    return <p className="text-xs text-destructive">{children}</p>;
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium">{value}</p>
        </div>
    );
}

function humanize(value: string): string {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}

function translate(
    template: string,
    replacements: Record<string, string>,
): string {
    return Object.entries(replacements).reduce(
        (translated, [key, value]) => translated.replace(`:${key}`, value),
        template,
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(DEFAULT_LOCALE, {
        dateStyle: 'medium',
    }).format(new Date(`${value}T00:00:00`));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(DEFAULT_LOCALE, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
