import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardCheck,
    Database,
    FileUp,
    MapPinned,
    Layers3,
    MoreHorizontal,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
import SearchableSelect from '@/components/searchable-select';
import StaticSearchableSelect from '@/components/static-searchable-select';
import TableEmptyState from '@/components/table-empty-state';
import { Badge } from '@/components/ui/badge';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import UniqueValueInput from '@/components/unique-value-input';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceDataTable from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { index as dataImportsIndex } from '@/routes/data-migrations';
import { store as storeOrganization } from '@/routes/reference-data/organizations';
import {
    destroy as destroyProgrammeCoverage,
    store as storeProgrammeCoverage,
} from '@/routes/reference-data/programme-coverages';
import { store as storeProgramme } from '@/routes/reference-data/programmes';
import {
    publish as publishRelease,
    store as storeRelease,
} from '@/routes/reference-data/releases';
import { store as storeSector } from '@/routes/reference-data/sectors';

type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Option = { id: string; name: string };
type RegistryCell = ReactNode | CountyIdentityValue;

type Props = {
    filters: Record<string, string | undefined>;
    organizations: Pagination<{
        id: string;
        code: string;
        name: string;
        type: string;
        county: CountyIdentityValue | null;
        email: string | null;
        status: string;
    }>;
    sectors: Pagination<{
        id: string;
        code: string;
        name: string;
        parent: { id: string; code: string; name: string } | null;
        description: string | null;
        isActive: boolean;
    }>;
    programmes: Pagination<{
        id: string;
        code: string;
        name: string;
        organization: string | null;
        sector: string | null;
        status: string;
        budgetAmount: string | null;
        currency: string;
    }>;
    programmeCoverages: {
        title: string;
        description: string;
        columns: string[];
        rows: WorkspaceRow[];
        pagination: WorkspacePagination;
    };
    options: {
        counties: CountyIdentityValue[];
        organizations: Option[];
        sectors: Option[];
        programmes: Option[];
    };
    releases: Array<{
        id: string;
        version: number;
        status: string;
        changeSummary: string;
        checksum: string;
        submittedBy: string;
        approvedBy: string | null;
        submittedAt: string;
        publishedAt: string | null;
        effectiveFrom: string | null;
        approvalReference: string | null;
        counts: Record<string, number>;
    }>;
    capabilities: { manage: boolean; approve: boolean };
};

function PaginationControls({
    pagination,
}: {
    pagination: Pagination<unknown>;
}) {
    return (
        <div className="flex items-center justify-between gap-3 border-t px-5 py-3 text-sm">
            <span className="text-muted-foreground">
                Page {pagination.current_page} of {pagination.last_page} ·{' '}
                {pagination.total.toLocaleString()} records
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!pagination.prev_page_url}
                    onClick={() =>
                        pagination.prev_page_url &&
                        router.visit(pagination.prev_page_url, {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!pagination.next_page_url}
                    onClick={() =>
                        pagination.next_page_url &&
                        router.visit(pagination.next_page_url, {
                            preserveScroll: true,
                            preserveState: true,
                        })
                    }
                >
                    Next
                </Button>
            </div>
        </div>
    );
}

function Field({
    label,
    name,
    children,
}: {
    label: string;
    name: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={name}>{label}</Label>
            {children}
        </div>
    );
}

export default function ReferenceDataIndex({
    filters,
    organizations,
    sectors,
    programmes,
    programmeCoverages,
    options,
    releases,
    capabilities,
}: Props) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam!.slug;

    return (
        <>
            <Head title="Reference data" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Shared platform control plane
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Reference data registry
                            </h1>
                            <p className="mt-3 max-w-3xl text-sm leading-6 text-[#c7d6dd] sm:text-base">
                                Govern the canonical organizations, sectors, and
                                programmes reused across all fourteen IDMIS
                                modules.
                            </p>
                        </div>
                        {capabilities.manage && (
                            <Button variant="secondary" asChild>
                                <Link href={dataImportsIndex(teamSlug)}>
                                    <FileUp data-icon="inline-start" />
                                    Bulk upload
                                </Link>
                            </Button>
                        )}
                    </div>
                </section>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    searchPlaceholder="Search reference data or programme coverage"
                    selectFilters={[
                        {
                            key: 'county_id',
                            label: 'County',
                            options: options.counties,
                            value: filters.county_id,
                        },
                        {
                            key: 'sector_id',
                            label: 'Sector',
                            options: options.sectors,
                            value: filters.sector_id,
                        },
                        {
                            key: 'status',
                            label: 'Coverage status',
                            options: [
                                'planned',
                                'active',
                                'paused',
                                'closed',
                            ].map((value) => ({
                                id: value,
                                name: value.replaceAll('_', ' '),
                            })),
                            value: filters.status,
                        },
                    ]}
                />

                <div className="grid gap-6 xl:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 aria-hidden="true" /> Organizations
                            </CardTitle>
                            <CardDescription>
                                National, county, partner and civil-society
                                bodies.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FormSheet
                                title="Create organization"
                                description="Add an organization to the canonical registry. Publish a release before downstream exchange."
                                triggerLabel="Create organization"
                                icon={Building2}
                            >
                                <Form
                                    {...storeOrganization.form(teamSlug)}
                                    resetOnSuccess
                                    className="flex flex-col gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <UniqueValueInput
                                                id="organization-code"
                                                name="code"
                                                label="Code"
                                                resource="organizations"
                                                field="code"
                                                teamSlug={teamSlug}
                                                serverError={errors.code}
                                                required
                                            />
                                            <UniqueValueInput
                                                id="organization-name"
                                                name="name"
                                                label="Name"
                                                resource="organizations"
                                                field="name"
                                                teamSlug={teamSlug}
                                                serverError={errors.name}
                                                required
                                            />
                                            <StaticSearchableSelect
                                                id="organization-type"
                                                label="Type"
                                                name="type"
                                                values={[
                                                    'national',
                                                    'county',
                                                    'development_partner',
                                                    'civil_society',
                                                    'other',
                                                ]}
                                                defaultValue="national"
                                                error={errors.type}
                                            />
                                            <SearchableSelect
                                                id="organization-county"
                                                label="County"
                                                name="county_id"
                                                optional
                                                options={options.counties.map(
                                                    (county) => ({
                                                        id: county.id,
                                                        name: county.name,
                                                        logoUrl: county.logoUrl,
                                                    }),
                                                )}
                                                error={errors.county_id}
                                            />
                                            <input
                                                type="hidden"
                                                name="status"
                                                value="active"
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Create organization
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </FormSheet>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Layers3 aria-hidden="true" /> Sectors
                            </CardTitle>
                            <CardDescription>
                                Shared thematic and reporting classifications.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FormSheet
                                title="Create sector"
                                description="Add a governed thematic and reporting classification."
                                triggerLabel="Create sector"
                                icon={Layers3}
                            >
                                <Form
                                    {...storeSector.form(teamSlug)}
                                    resetOnSuccess
                                    className="flex flex-col gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <UniqueValueInput
                                                id="sector-code"
                                                name="code"
                                                label="Code"
                                                resource="sectors"
                                                field="code"
                                                teamSlug={teamSlug}
                                                serverError={errors.code}
                                                required
                                            />
                                            <UniqueValueInput
                                                id="sector-name"
                                                name="name"
                                                label="Name"
                                                resource="sectors"
                                                field="name"
                                                teamSlug={teamSlug}
                                                serverError={errors.name}
                                                required
                                            />
                                            <Field
                                                label="Description"
                                                name="sector-description"
                                            >
                                                <Input
                                                    id="sector-description"
                                                    name="description"
                                                    aria-invalid={
                                                        !!errors.description
                                                    }
                                                />
                                            </Field>
                                            <SearchableSelect
                                                id="sector-parent"
                                                name="parent_sector_id"
                                                label="Parent sector"
                                                options={options.sectors}
                                                optional
                                                error={errors.parent_sector_id}
                                            />
                                            <input
                                                type="hidden"
                                                name="is_active"
                                                value="1"
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Create sector
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </FormSheet>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database aria-hidden="true" /> Programmes
                            </CardTitle>
                            <CardDescription>
                                Authoritative programme portfolio records.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FormSheet
                                title="Create programme"
                                description="Add an authoritative programme portfolio record."
                                triggerLabel="Create programme"
                                icon={Database}
                            >
                                <Form
                                    {...storeProgramme.form(teamSlug)}
                                    resetOnSuccess
                                    className="flex flex-col gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <UniqueValueInput
                                                id="programme-code"
                                                name="code"
                                                label="Code"
                                                resource="programmes"
                                                field="code"
                                                teamSlug={teamSlug}
                                                serverError={errors.code}
                                                required
                                            />
                                            <UniqueValueInput
                                                id="programme-name"
                                                name="name"
                                                label="Name"
                                                resource="programmes"
                                                field="name"
                                                teamSlug={teamSlug}
                                                serverError={errors.name}
                                                required
                                            />
                                            <SearchableSelect
                                                id="programme-organization"
                                                label="Lead organization"
                                                name="lead_organization_id"
                                                optional
                                                options={options.organizations}
                                                error={
                                                    errors.lead_organization_id
                                                }
                                            />
                                            <SearchableSelect
                                                id="programme-sector"
                                                label="Sector"
                                                name="sector_id"
                                                optional
                                                options={options.sectors}
                                                error={errors.sector_id}
                                            />
                                            <input
                                                type="hidden"
                                                name="status"
                                                value="planned"
                                            />
                                            <ReferenceCatalogSelect
                                                id="programme-currency"
                                                name="currency"
                                                label="Currency"
                                                catalog="currency"
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Create programme
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </FormSheet>
                        </CardContent>
                    </Card>
                </div>

                <ProgrammeCoverageRegister
                    workspace={programmeCoverages}
                    options={options}
                    filters={filters}
                    teamSlug={teamSlug}
                    canManage={capabilities.manage}
                />

                <ReleaseRegister
                    releases={releases}
                    capabilities={capabilities}
                    teamSlug={teamSlug}
                />

                <RegistryTable
                    title="Organizations"
                    headers={['Code', 'Name', 'Type', 'County', 'Status']}
                    pagination={organizations}
                    rows={organizations.data.map((item) => [
                        item.code,
                        item.name,
                        item.type.replaceAll('_', ' '),
                        item.county ?? 'National / portfolio',
                        item.status,
                    ])}
                />
                <RegistryTable
                    title="Sectors"
                    headers={[
                        'Code',
                        'Name',
                        'Parent sector',
                        'Description',
                        'State',
                    ]}
                    pagination={sectors}
                    rows={sectors.data.map((item) => [
                        item.code,
                        item.name,
                        item.parent
                            ? `${item.parent.code} · ${item.parent.name}`
                            : 'Top level',
                        item.description ?? '—',
                        item.isActive ? 'Active' : 'Inactive',
                    ])}
                />
                <RegistryTable
                    title="Programmes"
                    headers={[
                        'Code',
                        'Programme',
                        'Lead organization',
                        'Sector',
                        'Status',
                        'Budget',
                    ]}
                    pagination={programmes}
                    rows={programmes.data.map((item) => [
                        item.code,
                        item.name,
                        item.organization ?? 'Unassigned',
                        item.sector ?? 'Unassigned',
                        item.status.replaceAll('_', ' '),
                        item.budgetAmount
                            ? `${item.currency} ${Number(item.budgetAmount).toLocaleString()}`
                            : 'Not set',
                    ])}
                />
            </div>
        </>
    );
}

function ProgrammeCoverageRegister({
    workspace,
    options,
    filters,
    teamSlug,
    canManage,
}: {
    workspace: Props['programmeCoverages'];
    options: Props['options'];
    filters: Props['filters'];
    teamSlug: string;
    canManage: boolean;
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle>Programme county coverage</CardTitle>
                    <CardDescription>
                        Effective-dated programme reach, implementation
                        authority, funding and source provenance.
                    </CardDescription>
                </div>
                {canManage && (
                    <FormSheet
                        title="Add programme county coverage"
                        description="Link one programme to a county for a non-overlapping effective period with accountable source evidence."
                        triggerLabel="Add coverage"
                        icon={MapPinned}
                    >
                        <Form
                            {...storeProgrammeCoverage.form(teamSlug)}
                            resetOnSuccess
                            className="flex flex-col gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <SearchableSelect
                                        id="coverage-programme"
                                        name="programme_id"
                                        label="Programme"
                                        options={options.programmes}
                                        error={errors.programme_id}
                                    />
                                    <SearchableSelect
                                        id="coverage-county"
                                        name="county_id"
                                        label="County"
                                        options={options.counties}
                                        error={errors.county_id}
                                    />
                                    <SearchableSelect
                                        id="coverage-lead"
                                        name="implementation_lead_id"
                                        label="Implementation lead"
                                        options={options.organizations}
                                        optional
                                        error={errors.implementation_lead_id}
                                    />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <DatePickerField
                                            name="starts_on"
                                            label="Starts on"
                                            required
                                            error={errors.starts_on}
                                        />
                                        <DatePickerField
                                            name="ends_on"
                                            label="Ends on"
                                            error={errors.ends_on}
                                        />
                                    </div>
                                    <StaticSearchableSelect
                                        id="coverage-status"
                                        name="status"
                                        label="Status"
                                        values={[
                                            'planned',
                                            'active',
                                            'paused',
                                            'closed',
                                        ]}
                                        defaultValue="planned"
                                        error={errors.status}
                                    />
                                    <Field
                                        label="Funding allocation"
                                        name="coverage-allocation"
                                    >
                                        <Input
                                            id="coverage-allocation"
                                            name="funding_allocation"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            aria-invalid={Boolean(
                                                errors.funding_allocation,
                                            )}
                                        />
                                    </Field>
                                    <ReferenceCatalogSelect
                                        id="coverage-currency"
                                        name="currency"
                                        label="Currency"
                                        catalog="currency"
                                    />
                                    <Field
                                        label="Source reference"
                                        name="coverage-source-reference"
                                    >
                                        <Input
                                            id="coverage-source-reference"
                                            name="source_reference"
                                            required
                                            aria-invalid={Boolean(
                                                errors.source_reference,
                                            )}
                                        />
                                    </Field>
                                    <Field label="Notes" name="coverage-notes">
                                        <Textarea
                                            id="coverage-notes"
                                            name="notes"
                                            aria-invalid={Boolean(errors.notes)}
                                        />
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        Save county coverage
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            </CardHeader>
            <CardContent className="p-0">
                {workspace.rows.length > 0 ? (
                    <WorkspaceDataTable
                        columns={workspace.columns}
                        rows={workspace.rows}
                        pagination={workspace.pagination}
                        bulkExport={{
                            teamSlug,
                            workspace: 'programme-coverage',
                            filters,
                        }}
                        renderActionControl={(row) => (
                            <ProgrammeCoverageAction
                                row={row}
                                teamSlug={teamSlug}
                            />
                        )}
                    />
                ) : (
                    <WorkspaceEmptyState
                        title="No programme county coverage"
                        description="Add the first effective-dated county assignment or adjust the active filters."
                        className="min-h-64 border-0"
                    />
                )}
            </CardContent>
        </Card>
    );
}

function ProgrammeCoverageAction({
    row,
    teamSlug,
}: {
    row: WorkspaceRow;
    teamSlug: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${String(row.cells[0])}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setOpen(true)}>
                            <Trash2 /> Review archive
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>Archive programme coverage</SheetTitle>
                        <SheetDescription>
                            Archive this effective assignment. Published
                            catalogue snapshots retain their immutable copy.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-4 px-4 pb-8">
                        <p className="text-sm text-muted-foreground">
                            {String(row.cells[0])} · {String(row.cells[4])} ·{' '}
                            {String(row.cells[6])}
                        </p>
                        <Form
                            {...destroyProgrammeCoverage.form({
                                current_team: teamSlug,
                                programmeCountyCoverage: row.id,
                            })}
                            onSuccess={() => setOpen(false)}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    <Trash2 /> Archive coverage
                                </Button>
                            )}
                        </Form>
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

type ReferenceDataRelease = Props['releases'][number];

function ReleaseRegister({
    releases,
    capabilities,
    teamSlug,
}: {
    releases: ReferenceDataRelease[];
    capabilities: Props['capabilities'];
    teamSlug: string;
}) {
    const [selected, setSelected] = useState<ReferenceDataRelease | null>(null);

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle>Canonical releases</CardTitle>
                    <CardDescription>
                        Immutable, checksummed catalogue snapshots for
                        controlled module and integration consumption.
                    </CardDescription>
                </div>
                {capabilities.manage && (
                    <FormSheet
                        title="Submit catalogue release"
                        description="Capture the complete current county, organization, sector and programme catalogue for independent publication."
                        triggerLabel="Create release"
                        icon={ClipboardCheck}
                    >
                        <Form
                            {...storeRelease.form(teamSlug)}
                            resetOnSuccess
                            className="flex flex-col gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Field
                                        label="Change summary"
                                        name="release-change-summary"
                                    >
                                        <Input
                                            id="release-change-summary"
                                            name="change_summary"
                                            required
                                            minLength={10}
                                            aria-invalid={Boolean(
                                                errors.change_summary,
                                            )}
                                            aria-describedby={
                                                errors.change_summary
                                                    ? 'release-change-summary-error'
                                                    : undefined
                                            }
                                        />
                                    </Field>
                                    {errors.change_summary && (
                                        <p
                                            id="release-change-summary-error"
                                            role="alert"
                                            className="text-xs text-destructive"
                                        >
                                            {errors.change_summary}
                                        </p>
                                    )}
                                    <Button type="submit" disabled={processing}>
                                        Submit snapshot
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            </CardHeader>
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Version</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Contents</TableHead>
                            <TableHead>Submitted by</TableHead>
                            <TableHead>Effective</TableHead>
                            <TableHead>Checksum</TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {releases.length ? (
                            releases.map((release) => (
                                <TableRow key={release.id}>
                                    <TableCell className="font-medium">
                                        v{release.version}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                release.status === 'published'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {release.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {Object.entries(release.counts)
                                            .map(
                                                ([name, count]) =>
                                                    `${count} ${name}`,
                                            )
                                            .join(' · ')}
                                    </TableCell>
                                    <TableCell>{release.submittedBy}</TableCell>
                                    <TableCell>
                                        {release.effectiveFrom ?? 'Pending'}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {release.checksum.slice(0, 12)}…
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {capabilities.approve &&
                                            release.status === 'submitted' && (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Actions for reference-data release version ${release.version}`}
                                                        >
                                                            <MoreHorizontal />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuGroup>
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    setSelected(
                                                                        release,
                                                                    )
                                                                }
                                                            >
                                                                <ClipboardCheck />
                                                                Review and
                                                                publish
                                                            </DropdownMenuItem>
                                                        </DropdownMenuGroup>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            )}
                                    </TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={7}>
                                    <TableEmptyState
                                        title="No catalogue releases"
                                        description="No governed reference-data releases have been submitted."
                                    />
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </CardContent>
            <Sheet
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>
                            Publish reference-data release v{selected?.version}
                        </SheetTitle>
                        <SheetDescription>
                            Independently validate the snapshot checksum and
                            record its approval authority and effective date.
                        </SheetDescription>
                    </SheetHeader>
                    {selected && (
                        <Form
                            {...publishRelease.form({
                                current_team: teamSlug,
                                release: selected.id,
                            })}
                            onSuccess={() => setSelected(null)}
                            className="flex flex-col gap-4 px-4 pb-8"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        Submitted by {selected.submittedBy} ·{' '}
                                        checksum {selected.checksum}
                                    </p>
                                    <Field
                                        label="Approval reference"
                                        name="release-approval-reference"
                                    >
                                        <Input
                                            id="release-approval-reference"
                                            name="approval_reference"
                                            required
                                            aria-invalid={Boolean(
                                                errors.approval_reference,
                                            )}
                                        />
                                    </Field>
                                    <DatePickerField
                                        name="effective_from"
                                        label="Effective from"
                                        required
                                        error={errors.effective_from}
                                    />
                                    <Button type="submit" disabled={processing}>
                                        Publish immutable release
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </SheetContent>
            </Sheet>
        </Card>
    );
}

function RegistryTable({
    title,
    headers,
    rows,
    pagination,
}: {
    title: string;
    headers: string[];
    rows: RegistryCell[][];
    pagination: Pagination<unknown>;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <div>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>
                        Governed canonical records and lifecycle state.
                    </CardDescription>
                </div>
                <Badge variant="secondary">
                    {pagination.total.toLocaleString()} records
                </Badge>
            </CardHeader>
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {headers.map((header) => (
                                <TableHead key={header}>{header}</TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.length > 0 ? (
                            rows.map((row, rowIndex) => (
                                <TableRow key={rowIndex}>
                                    {row.map((cell, cellIndex) => (
                                        <TableCell
                                            key={`${rowIndex}-${cellIndex}`}
                                            className="capitalize"
                                        >
                                            {isCountyIdentity(cell) ? (
                                                <CountyIdentity
                                                    county={cell}
                                                    compact
                                                />
                                            ) : (
                                                cell
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={headers.length}>
                                    <TableEmptyState />
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
                <PaginationControls pagination={pagination} />
            </CardContent>
        </Card>
    );
}

function isCountyIdentity(value: RegistryCell): value is CountyIdentityValue {
    return (
        typeof value === 'object' &&
        value !== null &&
        'kind' in value &&
        value.kind === 'county'
    );
}
