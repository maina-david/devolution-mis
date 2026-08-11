import { Form, Head, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    DatabaseBackup,
    Download,
    Eye,
    FileSpreadsheet,
    FileUp,
    MoreHorizontal,
    PlayCircle,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
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
import { apply, download, review, store } from '@/routes/data-migrations';
import { download as downloadExceptions } from '@/routes/data-migrations/exceptions';
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

type Props = {
    batches: PageSet<Batch>;
    filters: Record<string, string | undefined>;
    capabilities: { stage: boolean; review: boolean; apply: boolean };
};

const datasetOptions = [
    { id: 'acpa_scores', name: 'ACPA scores' },
    { id: 'performance_metrics', name: 'Performance metrics' },
    { id: 'evaluation_baselines', name: 'Evaluation baselines' },
];

const referenceDatasetOptions = [
    { id: 'organizations', name: 'Organizations' },
    { id: 'sectors', name: 'Sectors' },
    { id: 'programmes', name: 'Programmes' },
    { id: 'users', name: 'Users and role assignments' },
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
}: Props) {
    const { currentTeam } = usePage().props;
    const [selected, setSelected] = useState<Batch | null>(null);
    const [action, setAction] = useState<'details' | 'review' | 'apply'>(
        'details',
    );

    if (!currentTeam) {
        return null;
    }

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
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
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
                                <ReferenceImportForm team={currentTeam.slug} />
                                <MigrationForm team={currentTeam.slug} />
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

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    searchPlaceholder="Search migration reference or source"
                    selectFilters={[
                        {
                            key: 'type',
                            label: 'Dataset type',
                            options: datasetOptions,
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
                                                                current_team:
                                                                    currentTeam.slug,
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
                                                                        current_team:
                                                                            currentTeam.slug,
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
            </main>

            <BatchSheet
                batch={selected}
                action={action}
                team={currentTeam.slug}
                onOpenChange={(isOpen) => !isOpen && setSelected(null)}
            />
        </>
    );
}

function MigrationForm({ team }: { team: string }) {
    const [datasetType, setDatasetType] = useState('acpa_scores');

    return (
        <FormSheet
            title="Stage historical source"
            description="Upload an authorized CSV or XLSX source for row-level validation and county reconciliation. No record is applied at this stage."
            triggerLabel="Upload historical data"
            icon={FileUp}
            size="lg"
        >
            <Form {...store.form(team)} resetOnSuccess>
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
                                Required columns: county_code, period,
                                metric_code, metric_name, numeric_value,
                                narrative_value, unit, source_reference. Maximum
                                5,000 rows and 20 MB.
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
                        <TemplateDownloadMenu
                            team={team}
                            datasetType={datasetType}
                        />
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

function ReferenceImportForm({ team }: { team: string }) {
    const [datasetType, setDatasetType] = useState('organizations');

    return (
        <FormSheet
            title="Upload reference data"
            description="Upload a create-only CSV or XLSX template. The file is dry-run validated and cannot change the registry until independent approval and final application."
            triggerLabel="Upload reference data"
            icon={FileUp}
            size="lg"
        >
            <Form {...storeReferenceData.form(team)} resetOnSuccess>
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
                        <TemplateDownloadMenu
                            team={team}
                            datasetType={datasetType}
                        />
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
                                duplicate codes are rejected; imports never
                                overwrite current records.
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

function TemplateDownloadMenu({
    team,
    datasetType,
}: {
    team: string;
    datasetType: string;
}) {
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
                    <a
                        href={showTemplate.url({
                            current_team: team,
                            datasetType,
                        })}
                    >
                        <Download /> CSV template
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={showTemplate.url(
                            { current_team: team, datasetType },
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
    team,
    onOpenChange,
}: {
    batch: Batch | null;
    action: 'details' | 'review' | 'apply';
    team: string;
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
                                        current_team: team,
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
                                    current_team: team,
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
                                    current_team: team,
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
