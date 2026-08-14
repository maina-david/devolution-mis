import { Form, Head, Link, router, usePage } from '@inertiajs/react';
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
import {
    apply,
    download,
    index,
    review,
    store,
} from '@/routes/data-migrations';
import { download as downloadExceptions } from '@/routes/data-migrations/exceptions';
import {
    apply as applyLineageDisposition,
    review as reviewLineageDisposition,
    store as storeLineageDisposition,
} from '@/routes/data-migrations/lineage-dispositions';
import { store as storeReferenceData } from '@/routes/data-migrations/reference-data';
import { show as showTemplate } from '@/routes/data-migrations/templates';
import { exportMethod as workspaceExport } from '@/routes/workspace';

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

type LegacyAcpaComponent = {
    id: string;
    type: string;
    reference: string;
    criterionCode: string | null;
    title: string | null;
    value: string | null;
    maximumValue: string | null;
    status: string | null;
    assignmentRole: string | null;
    personName: string | null;
    description: string | null;
    decision: string | null;
    fileName: string | null;
    mimeType: string | null;
    fileChecksum: string | null;
    sourceReference: string;
    recordChecksum: string;
};

type LegacyAcpaRow = WorkspaceRow & {
    meta: { countyId: string; components: LegacyAcpaComponent[] };
};

type WorkspaceDataset = {
    title: string;
    description: string;
    columns: string[];
    rows: LegacyAcpaRow[];
    pagination: WorkspacePagination;
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
            available: number;
            pending: number;
            applied: number;
            oldestAt: string | null;
            latestAt: string | null;
        }>;
    };
    legacyAcpa: WorkspaceDataset;
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

function useMigrationCopy(): Record<string, string> {
    return usePage().props.localization.migration;
}

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
    legacyAcpa,
}: Props) {
    const copy = useMigrationCopy();
    const [selected, setSelected] = useState<Batch | null>(null);
    const [action, setAction] = useState<'details' | 'review' | 'apply'>(
        'details',
    );
    const [selectedDisposition, setSelectedDisposition] =
        useState<LineageDisposition | null>(null);
    const [dispositionAction, setDispositionAction] = useState<
        'details' | 'review' | 'apply'
    >('details');
    const [selectedLegacyAcpa, setSelectedLegacyAcpa] =
        useState<LegacyAcpaRow | null>(null);

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
    const legacyRows = legacyAcpa.rows.map((row) => ({
        ...row,
        cells: row.cells.slice(0, 10),
    }));
    const legacyExportQuery = {
        from: filters.from,
        to: filters.to,
        search: filters.search,
        county_id: filters.county_id,
        status: filters.legacy_status,
    };
    const open = (batch: Batch, nextAction: typeof action) => {
        setSelected(batch);
        setAction(nextAction);
    };

    return (
        <>
            <Head title={copy.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] uppercase opacity-75">
                                {copy.eyebrow}
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {copy.title}
                            </h1>
                            <p className="mt-3 max-w-2xl opacity-80">
                                {copy.description}
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
                    <AlertTitle>{copy.three_person_control}</AlertTitle>
                    <AlertDescription>
                        {copy.three_person_control_description}
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
                            {capabilities.stage &&
                                legacyCandidates.length > 0 && (
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
                                title={t.inventory_empty_title}
                                description={t.inventory_empty_description}
                            />
                        ) : (
                            <div className="grid gap-4 lg:grid-cols-[12rem_1fr]">
                                <dl className="rounded-lg border p-4">
                                    <dt className="text-sm text-muted-foreground">
                                        {copy.records_requiring_disposition}
                                    </dt>
                                    <dd className="mt-1 text-3xl font-semibold">
                                        {legacyInventory.total}
                                    </dd>
                                    <dt className="mt-4 text-sm text-muted-foreground">
                                        {copy.record_types}
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
                                        {legacyInventory.records.map(
                                            (record) => (
                                                <li
                                                    key={record.model}
                                                    className="flex items-center justify-between gap-4 p-3 text-sm"
                                                >
                                                    <span>{record.type}</span>
                                                    <span className="flex gap-3 text-xs">
                                                        <strong>
                                                            {record.count}{' '}
                                                            {t.open}
                                                        </strong>
                                                        <span className="text-muted-foreground">
                                                            {record.pending}{' '}
                                                            {t.pending}
                                                        </span>
                                                        <span className="text-muted-foreground">
                                                            {record.applied}{' '}
                                                            {t.applied}
                                                        </span>
                                                    </span>
                                                </li>
                                            ),
                                        )}
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
                                            'details' | 'review' | 'apply',
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
                                                    aria-label={translate(
                                                        t.actions_for,
                                                        {
                                                            reference:
                                                                disposition.reference,
                                                        },
                                                    )}
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
                                                            <PlayCircle />{' '}
                                                            {copy.apply}{' '}
                                                            {
                                                                t.apply_disposition
                                                            }
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
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle>{legacyAcpa.title}</CardTitle>
                            <CardDescription>
                                {legacyAcpa.description}
                            </CardDescription>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <Download /> {t.legacy_export}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {['csv', 'xlsx', 'json', 'pdf'].map(
                                    (format) => (
                                        <DropdownMenuItem key={format} asChild>
                                            <Link
                                                href={workspaceExport(
                                                    {
                                                        workspace:
                                                            'legacy-acpa',
                                                        format,
                                                    },
                                                    {
                                                        query: legacyExportQuery,
                                                    },
                                                )}
                                            >
                                                <Download />
                                                {format.toUpperCase()}
                                            </Link>
                                        </DropdownMenuItem>
                                    ),
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </CardHeader>
                    <CardContent>
                        {legacyAcpa.pagination.total === 0 ? (
                            <WorkspaceEmptyState
                                title={t.legacy_empty_title}
                                description={t.legacy_empty_description}
                            />
                        ) : (
                            <WorkspaceDataTable
                                columns={legacyAcpa.columns.slice(0, 10)}
                                rows={legacyRows}
                                pagination={{
                                    ...legacyAcpa.pagination,
                                    pageName: 'legacy_page',
                                    perPageName: 'legacy_per_page',
                                }}
                                renderActionControl={(row) => {
                                    const assessment = legacyAcpa.rows.find(
                                        (candidate) => candidate.id === row.id,
                                    );

                                    return assessment ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={translate(
                                                        t.legacy_actions,
                                                        {
                                                            assessment: String(
                                                                assessment
                                                                    .cells[1],
                                                            ),
                                                        },
                                                    )}
                                                >
                                                    <MoreHorizontal />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        setSelectedLegacyAcpa(
                                                            assessment,
                                                        )
                                                    }
                                                >
                                                    <Eye /> {t.legacy_view}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : null;
                                }}
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.migration_batches}</CardTitle>
                        <CardDescription>
                            {copy.migration_batches_description}
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
                                                        <Eye />{' '}
                                                        {copy.view_details}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <a
                                                            href={download.url({
                                                                dataMigrationBatch:
                                                                    batch.id,
                                                            })}
                                                        >
                                                            <Download />{' '}
                                                            {
                                                                copy.download_source
                                                            }
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
                                                                {
                                                                    copy.download_exceptions
                                                                }
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
                                                                {
                                                                    copy.record_review
                                                                }
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
                                                                {
                                                                    copy.apply_records
                                                                }
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
            <LegacyAcpaSheet
                assessment={selectedLegacyAcpa}
                onOpenChange={(isOpen) =>
                    !isOpen && setSelectedLegacyAcpa(null)
                }
                t={t}
            />
        </>
    );
}

function LegacyAcpaSheet({
    assessment,
    onOpenChange,
    t,
}: {
    assessment: LegacyAcpaRow | null;
    onOpenChange: (open: boolean) => void;
    t: Props['lineageTranslations'];
}) {
    return (
        <Sheet open={Boolean(assessment)} onOpenChange={onOpenChange}>
            <SheetContent className="overflow-y-auto sm:max-w-3xl">
                <SheetHeader>
                    <SheetTitle>{t.legacy_sheet_title}</SheetTitle>
                    <SheetDescription>
                        {t.legacy_sheet_description}
                    </SheetDescription>
                </SheetHeader>
                {assessment && (
                    <div className="mt-6 grid gap-5">
                        <div className="grid gap-3 rounded-xl border p-4 text-sm sm:grid-cols-2">
                            <Detail
                                label={t.assessment}
                                value={assessment.cells[1]}
                            />
                            <Detail
                                label={t.period}
                                value={assessment.cells[2]}
                            />
                            <Detail
                                label={t.status}
                                value={assessment.cells[3]}
                            />
                            <Detail
                                label={t.overall_score}
                                value={assessment.cells[4]}
                            />
                            <Detail
                                label={t.source_reference}
                                value={assessment.cells[10]}
                            />
                            <Detail
                                label={t.imported_by}
                                value={assessment.cells[13]}
                            />
                            <Detail
                                label={t.source_checksum}
                                value={assessment.cells[11]}
                                mono
                            />
                            <Detail
                                label={t.record_checksum}
                                value={assessment.cells[12]}
                                mono
                            />
                        </div>
                        <div>
                            <h3 className="font-semibold">{t.components}</h3>
                            <p className="text-sm text-muted-foreground">
                                {translate(t.component_count, {
                                    count: assessment.meta.components.length.toLocaleString(
                                        DEFAULT_LOCALE,
                                    ),
                                })}
                            </p>
                        </div>
                        <div className="grid gap-3">
                            {assessment.meta.components.map((component) => (
                                <Card key={component.id}>
                                    <CardHeader>
                                        <CardTitle className="flex flex-wrap items-center justify-between gap-2 text-base">
                                            <span>
                                                {component.reference}
                                                {' · '}
                                                {component.title ??
                                                    humanize(component.type)}
                                            </span>
                                            <span className="rounded-full border px-2 py-1 text-xs font-medium">
                                                {humanize(component.type)}
                                            </span>
                                        </CardTitle>
                                        <CardDescription>
                                            {component.criterionCode ??
                                                t.assessment_level}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                                        <Detail
                                            label={t.status}
                                            value={component.status}
                                        />
                                        <Detail
                                            label={t.score}
                                            value={
                                                component.value === null
                                                    ? null
                                                    : `${component.value}${component.maximumValue ? ` / ${component.maximumValue}` : ''}`
                                            }
                                        />
                                        <Detail
                                            label={t.assessor_role}
                                            value={component.assignmentRole}
                                        />
                                        <Detail
                                            label={t.assessor}
                                            value={component.personName}
                                        />
                                        <Detail
                                            label={t.decision}
                                            value={component.decision}
                                        />
                                        <Detail
                                            label={t.evidence_file}
                                            value={
                                                component.fileName
                                                    ? `${component.fileName} · ${component.mimeType ?? t.unknown_type}`
                                                    : null
                                            }
                                        />
                                        <Detail
                                            label={t.description}
                                            value={component.description}
                                        />
                                        <Detail
                                            label={t.source_reference}
                                            value={component.sourceReference}
                                        />
                                        <Detail
                                            label={t.file_checksum}
                                            value={component.fileChecksum}
                                            mono
                                        />
                                        <Detail
                                            label={t.record_checksum}
                                            value={component.recordChecksum}
                                            mono
                                        />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
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
                            {processing ? t.recording : t.submit_review}
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
    const copy = useMigrationCopy();
    const [datasetType, setDatasetType] = useState('acpa_scores');
    const requiredColumns =
        datasetType === 'acpa_reconstruction'
            ? 'county_code, assessment_reference, period, record_type, record_reference, criterion_code, title, numeric_value, maximum_value, status, assignment_role, person_identifier, person_name, description, decision, file_name, mime_type, file_checksum, source_reference'
            : 'county_code, period, metric_code, metric_name, numeric_value, narrative_value, unit, source_reference';

    return (
        <FormSheet
            title={copy.stage_historical_source}
            description={copy.stage_historical_source_description}
            triggerLabel={copy.upload_historical_data}
            icon={FileUp}
            size="lg"
        >
            <Form {...store.form()} resetOnSuccess>
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="migration-file">
                                {copy.csv_xlsx_source}
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
                                {copy.required_columns_label} {requiredColumns}
                                {'. '}
                                {copy.required_columns_suffix}
                            </p>
                            {errors.file && (
                                <ErrorText>{errors.file}</ErrorText>
                            )}
                        </div>
                        <StaticSearchableSelect
                            id="migration-dataset-type"
                            name="dataset_type"
                            label={copy.dataset_type}
                            values={datasetOptions.map((option) => option.id)}
                            value={datasetType}
                            onValueChange={setDatasetType}
                            error={errors.dataset_type}
                        />
                        <TemplateDownloadMenu datasetType={datasetType} />
                        <TextField
                            id="migration-source-name"
                            name="source_name"
                            label={copy.source_name}
                            error={errors.source_name}
                        />
                        <TextField
                            id="migration-source-reference"
                            name="source_reference"
                            label={copy.source_reference}
                            error={errors.source_reference}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="period_from"
                                label={copy.period_from}
                                error={errors.period_from}
                            />
                            <DatePickerField
                                name="period_to"
                                label={copy.period_to}
                                error={errors.period_to}
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            <FileUp data-icon="inline-start" />
                            {processing
                                ? copy.validating_source
                                : copy.stage_and_validate}
                        </Button>
                    </div>
                )}
            </Form>
        </FormSheet>
    );
}

function ReferenceImportForm() {
    const copy = useMigrationCopy();
    const [datasetType, setDatasetType] = useState('organizations');

    return (
        <FormSheet
            title={copy.upload_reference_data}
            description={copy.upload_reference_data_description}
            triggerLabel={copy.upload_reference_data}
            icon={FileUp}
            size="lg"
        >
            <Form {...storeReferenceData.form()} resetOnSuccess>
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <StaticSearchableSelect
                            id="reference-import-dataset-type"
                            name="dataset_type"
                            label={copy.registry}
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
                                {copy.completed_template}
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
                                {copy.reference_import_limits}
                            </p>
                            {errors.file && (
                                <ErrorText>{errors.file}</ErrorText>
                            )}
                        </div>
                        <TextField
                            id="reference-import-source-name"
                            name="source_name"
                            label={copy.authoritative_source_name}
                            error={errors.source_name}
                        />
                        <TextField
                            id="reference-import-source-reference"
                            name="source_reference"
                            label={copy.approval_or_source_reference}
                            error={errors.source_reference}
                        />
                        <Button type="submit" disabled={processing}>
                            <FileUp data-icon="inline-start" />
                            {processing
                                ? copy.validating_file
                                : copy.upload_and_validate}
                        </Button>
                    </div>
                )}
            </Form>
        </FormSheet>
    );
}

function TemplateDownloadMenu({ datasetType }: { datasetType: string }) {
    const copy = useMigrationCopy();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline">
                    <Download data-icon="inline-start" />
                    {copy.download} {humanize(datasetType)} {copy.template}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuItem asChild>
                    <a href={showTemplate.url({ datasetType })}>
                        <Download /> {copy.csv_template}
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={showTemplate.url(
                            { datasetType },
                            { query: { format: 'xlsx' } },
                        )}
                    >
                        <FileSpreadsheet /> {copy.xlsx_template}
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
    const copy = useMigrationCopy();

    return (
        <Sheet open={Boolean(batch)} onOpenChange={onOpenChange}>
            <SheetContent className="overflow-y-auto sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>
                        {action === 'review'
                            ? copy.review_migration_batch
                            : action === 'apply'
                              ? copy.apply_immutable_records
                              : copy.migration_batch_details}
                    </SheetTitle>
                    <SheetDescription>
                        {batch?.reference} {copy.separator} {batch?.sourceName}
                    </SheetDescription>
                </SheetHeader>
                {batch && (
                    <div className="flex flex-col gap-5 px-4 pb-8">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Detail
                                label={copy.status}
                                value={humanize(batch.status)}
                            />
                            <Detail
                                label={copy.dataset}
                                value={humanize(batch.datasetType)}
                            />
                            <Detail
                                label={copy.valid_rows}
                                value={String(batch.validRows)}
                            />
                            <Detail
                                label={copy.exceptions}
                                value={String(batch.invalidRows)}
                            />
                            <Detail
                                label={copy.submitted_by}
                                value={batch.submittedBy}
                            />
                            <Detail
                                label={copy.reviewed_by}
                                value={batch.reviewedBy ?? copy.pending}
                            />
                        </div>
                        <div>
                            <p className="text-xs font-medium text-muted-foreground">
                                {copy.batch_source_checksum}
                            </p>
                            <p className="mt-1 font-mono text-xs break-all">
                                {batch.fileChecksum}
                            </p>
                        </div>
                        {batch.referenceDataRelease && (
                            <div className="rounded-lg border bg-muted/30 p-4">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Detail
                                        label={copy.catalogue_release_candidate}
                                        value={`v${batch.referenceDataRelease.version}`}
                                    />
                                    <Detail
                                        label={copy.release_status}
                                        value={humanize(
                                            batch.referenceDataRelease.status,
                                        )}
                                    />
                                </div>
                                <p className="mt-4 text-xs font-medium text-muted-foreground">
                                    {copy.release_checksum}
                                </p>
                                <p className="mt-1 font-mono text-xs break-all">
                                    {batch.referenceDataRelease.checksum}
                                </p>
                                <p className="mt-3 text-xs text-muted-foreground">
                                    {copy.release_candidate_notice}
                                </p>
                            </div>
                        )}
                        {Object.keys(batch.errorCounts).length > 0 && (
                            <Alert variant="destructive">
                                <XCircle />
                                <AlertTitle>
                                    {copy.validation_exceptions}
                                </AlertTitle>
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
                                    {copy.download_exception_report}
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
                                            label={copy.decision}
                                            values={['approve', 'reject']}
                                            error={errors.decision}
                                        />
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="migration-review-notes">
                                                {copy.independent_notes}
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
                                            {copy.record_review_decision}
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
                                                {copy.final_application}
                                            </AlertTitle>
                                            <AlertDescription>
                                                {
                                                    copy.batch_final_application_description
                                                }
                                            </AlertDescription>
                                        </Alert>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {copy.apply} {batch.validRows}{' '}
                                            {copy.records}
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

function Detail({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: unknown;
    mono?: boolean;
}) {
    const text =
        value === null || value === undefined || value === ''
            ? '—'
            : String(value);

    return (
        <div className="min-w-0 rounded-lg border p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={`mt-1 font-medium break-words ${mono ? 'font-mono text-xs' : 'text-sm'}`}
            >
                {text}
            </p>
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
