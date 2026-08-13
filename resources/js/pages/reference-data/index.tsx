import type { Method } from '@inertiajs/core';
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    Archive,
    ClipboardCheck,
    Database,
    FileUp,
    Map,
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
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
import { show as dataImportTemplate } from '@/routes/data-migrations/templates';
import {
    bulkArchive as bulkArchiveCounties,
    destroy as destroyCounty,
    store as storeCounty,
    update as updateCounty,
} from '@/routes/reference-data/counties';
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
import { exportMethod as exportWorkspace } from '@/routes/workspace';

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
    counties: Pagination<{
        id: string;
        identity: CountyIdentityValue;
        region: string | null;
        mapX: number;
        mapY: number;
        references: number;
    }>;
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
    subCounties: Pagination<{
        id: string;
        code: string;
        name: string;
        classification: string;
        county: CountyIdentityValue;
        wardCount: number;
        effectiveFrom: string;
        sourceAuthority: string;
        checksum: string;
    }>;
    wards: Pagination<{
        id: string;
        code: string;
        name: string;
        subCounty: { id: string; code: string; name: string };
        county: CountyIdentityValue;
        registeredVoters2022: number | null;
        effectiveFrom: string;
        sourceAuthority: string;
        checksum: string;
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
    counties,
    filters,
    organizations,
    sectors,
    programmes,
    programmeCoverages,
    options,
    releases,
    capabilities,
    subCounties,
    wards,
}: Props) {
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
                                <Link href={dataImportsIndex()}>
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
                                    {...storeOrganization.form()}
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
                                                serverError={errors.code}
                                                required
                                            />
                                            <UniqueValueInput
                                                id="organization-name"
                                                name="name"
                                                label="Name"
                                                resource="organizations"
                                                field="name"
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
                                    {...storeSector.form()}
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
                                                serverError={errors.code}
                                                required
                                            />
                                            <UniqueValueInput
                                                id="sector-name"
                                                name="name"
                                                label="Name"
                                                resource="sectors"
                                                field="name"
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
                                    {...storeProgramme.form()}
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
                                                serverError={errors.code}
                                                required
                                            />
                                            <UniqueValueInput
                                                id="programme-name"
                                                name="name"
                                                label="Name"
                                                resource="programmes"
                                                field="name"
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
                    canManage={capabilities.manage}
                />

                <CountyRegister
                    counties={counties}
                    canManage={capabilities.manage}
                />

                <AdministrativeHierarchyRegister
                    subCounties={subCounties}
                    wards={wards}
                    canManage={capabilities.manage}
                />

                <ReleaseRegister
                    releases={releases}
                    capabilities={capabilities}
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

function AdministrativeHierarchyRegister({
    subCounties,
    wards,
    canManage,
}: {
    subCounties: Props['subCounties'];
    wards: Props['wards'];
    canManage: boolean;
}) {
    const { localization } = usePage().props;
    const copy = localization.referenceData;

    return (
        <section
            className="grid gap-6"
            aria-labelledby="administrative-hierarchy-title"
        >
            <Card>
                <CardHeader className="flex-row items-start justify-between gap-4">
                    <div>
                        <CardTitle
                            id="administrative-hierarchy-title"
                            className="flex items-center gap-2"
                        >
                            <MapPinned aria-hidden="true" />{' '}
                            {copy.administrative_hierarchy}
                        </CardTitle>
                        <CardDescription>
                            {copy.administrative_hierarchy_description}
                        </CardDescription>
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            {['sub_counties', 'wards'].map((datasetType) => (
                                <Button
                                    key={datasetType}
                                    asChild
                                    variant="outline"
                                >
                                    <Link
                                        href={dataImportsIndex.url({
                                            query: { type: datasetType },
                                        })}
                                    >
                                        <FileUp data-icon="inline-start" />
                                        {datasetType === 'wards'
                                            ? copy.bulk_upload_wards
                                            : copy.bulk_upload_sub_counties}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    )}
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            {copy.counties_covered}
                        </p>
                        <p className="mt-1 text-2xl font-bold">
                            {copy.complete_county_coverage}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            {copy.parent_units}
                        </p>
                        <p className="mt-1 text-2xl font-bold">
                            {subCounties.total.toLocaleString(
                                localization.current,
                            )}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            {copy.county_assembly_wards}
                        </p>
                        <p className="mt-1 text-2xl font-bold">
                            {wards.total.toLocaleString(localization.current)}
                        </p>
                    </div>
                </CardContent>
            </Card>
            <RegistryTable
                title={copy.parent_units}
                headers={[
                    copy.code,
                    copy.unit,
                    copy.county,
                    copy.classification,
                    copy.wards,
                    copy.effective_from,
                    copy.source,
                    copy.checksum,
                ]}
                pagination={subCounties}
                rows={subCounties.data.map((item) => [
                    item.code,
                    item.name,
                    item.county,
                    copy[`value_${item.classification}`] ??
                        item.classification.replaceAll('_', ' '),
                    item.wardCount.toLocaleString(localization.current),
                    new Date(
                        `${item.effectiveFrom}T00:00:00`,
                    ).toLocaleDateString(localization.current),
                    item.sourceAuthority,
                    item.checksum,
                ])}
            />
            <RegistryTable
                title={copy.county_assembly_wards}
                headers={[
                    copy.iebc_code,
                    copy.ward,
                    copy.parent_unit,
                    copy.county,
                    copy.registered_voters_2022,
                    copy.effective_from,
                    copy.source,
                    copy.checksum,
                ]}
                pagination={wards}
                rows={wards.data.map((item) => [
                    item.code,
                    item.name,
                    `${item.subCounty.code} · ${item.subCounty.name}`,
                    item.county,
                    item.registeredVoters2022?.toLocaleString(
                        localization.current,
                    ) ?? copy.not_available,
                    new Date(
                        `${item.effectiveFrom}T00:00:00`,
                    ).toLocaleDateString(localization.current),
                    item.sourceAuthority,
                    item.checksum,
                ])}
            />
        </section>
    );
}

function CountyRegister({
    counties,
    canManage,
}: {
    counties: Props['counties'];
    canManage: boolean;
}) {
    const [selectedIds, setSelectedIds] = useState<string[]>([]);
    const [editing, setEditing] = useState<
        Props['counties']['data'][number] | null
    >(null);
    const [archiving, setArchiving] = useState<
        Props['counties']['data'][number] | null
    >(null);
    const [bulkOpen, setBulkOpen] = useState(false);
    const allSelected =
        counties.data.length > 0 &&
        counties.data.every((county) => selectedIds.includes(county.id));
    const toggle = (id: string) =>
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );

    return (
        <Card className="overflow-hidden">
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle>Counties</CardTitle>
                    <CardDescription>
                        Govern the official 47-county registry, identity
                        provenance and map placement.
                    </CardDescription>
                </div>
                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button type="button" variant="outline">
                                    <Database data-icon="inline-start" /> Export
                                    counties
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>
                                    Download format
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {['csv', 'xlsx', 'json', 'pdf'].map(
                                    (format) => (
                                        <DropdownMenuItem key={format} asChild>
                                            <a
                                                href={exportWorkspace.url({
                                                    workspace: 'counties',
                                                    format,
                                                })}
                                            >
                                                {format.toUpperCase()}
                                            </a>
                                        </DropdownMenuItem>
                                    ),
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Button asChild variant="outline">
                            <Link
                                href={dataImportTemplate.url({
                                    datasetType: 'counties',
                                })}
                            >
                                <FileUp data-icon="inline-start" /> Download
                                import template
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={dataImportsIndex.url({
                                    query: { type: 'counties' },
                                })}
                            >
                                <FileUp data-icon="inline-start" /> Bulk upload
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={selectedIds.length === 0}
                            onClick={() => setBulkOpen(true)}
                        >
                            <Archive data-icon="inline-start" /> Archive
                            selected
                        </Button>
                        <FormSheet
                            title="Create county"
                            description="Add a county reference. Official identity assets require separate provenance verification."
                            triggerLabel="Create county"
                            icon={Map}
                        >
                            <CountyForm
                                form={storeCounty.form()}
                                submitLabel="Create county"
                            />
                        </FormSheet>
                    </div>
                )}
            </CardHeader>
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {canManage && (
                                <TableHead className="w-12">
                                    <Checkbox
                                        checked={allSelected}
                                        onCheckedChange={(checked) =>
                                            setSelectedIds(
                                                checked
                                                    ? counties.data.map(
                                                          (county) => county.id,
                                                      )
                                                    : [],
                                            )
                                        }
                                        aria-label="Select all counties on this page"
                                    />
                                </TableHead>
                            )}
                            <TableHead>#</TableHead>
                            <TableHead>County</TableHead>
                            <TableHead>Region</TableHead>
                            <TableHead>Map position</TableHead>
                            <TableHead>References</TableHead>
                            {canManage && (
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {counties.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={canManage ? 7 : 5}>
                                    <TableEmptyState />
                                </TableCell>
                            </TableRow>
                        ) : (
                            counties.data.map((county, index) => (
                                <TableRow key={county.id}>
                                    {canManage && (
                                        <TableCell>
                                            <Checkbox
                                                checked={selectedIds.includes(
                                                    county.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggle(county.id)
                                                }
                                                aria-label={`Select ${county.identity.name}`}
                                            />
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        {(counties.current_page - 1) * 15 +
                                            index +
                                            1}
                                    </TableCell>
                                    <TableCell>
                                        <CountyIdentity
                                            county={county.identity}
                                            compact
                                        />
                                    </TableCell>
                                    <TableCell>
                                        {county.region ?? 'Not specified'}
                                    </TableCell>
                                    <TableCell>
                                        {county.mapX.toFixed(2)},{' '}
                                        {county.mapY.toFixed(2)}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                county.references > 0
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {county.references}
                                        </Badge>
                                    </TableCell>
                                    {canManage && (
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Actions for ${county.identity.name}`}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuGroup>
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                setEditing(
                                                                    county,
                                                                )
                                                            }
                                                        >
                                                            Edit county
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            disabled={
                                                                county.references >
                                                                0
                                                            }
                                                            onSelect={() =>
                                                                setArchiving(
                                                                    county,
                                                                )
                                                            }
                                                        >
                                                            Archive county
                                                        </DropdownMenuItem>
                                                    </DropdownMenuGroup>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
                <PaginationControls pagination={counties} />
            </CardContent>
            <Sheet
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>Edit county</SheetTitle>
                        <SheetDescription>
                            Update governed county metadata. Published snapshots
                            retain their prior values.
                        </SheetDescription>
                    </SheetHeader>
                    {editing && (
                        <div className="px-4 pb-8">
                            <CountyForm
                                form={updateCounty.form({ county: editing.id })}
                                county={editing}
                                submitLabel="Save county"
                                onSuccess={() => setEditing(null)}
                            />
                        </div>
                    )}
                </SheetContent>
            </Sheet>
            <Sheet
                open={archiving !== null}
                onOpenChange={(open) => !open && setArchiving(null)}
            >
                <SheetContent>
                    <SheetHeader>
                        <SheetTitle>Archive county</SheetTitle>
                        <SheetDescription>
                            Only an unreferenced county can be archived.
                        </SheetDescription>
                    </SheetHeader>
                    {archiving && (
                        <div className="px-4">
                            <Form
                                {...destroyCounty.form({
                                    county: archiving.id,
                                })}
                                onSuccess={() => setArchiving(null)}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        <Archive data-icon="inline-start" />{' '}
                                        Archive {archiving.identity.name}
                                    </Button>
                                )}
                            </Form>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
            <Sheet open={bulkOpen} onOpenChange={setBulkOpen}>
                <SheetContent>
                    <SheetHeader>
                        <SheetTitle>Archive selected counties</SheetTitle>
                        <SheetDescription>
                            This atomic action fails if any selected county is
                            referenced.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4">
                        <Form
                            {...bulkArchiveCounties.form()}
                            onSuccess={() => {
                                setBulkOpen(false);
                                setSelectedIds([]);
                            }}
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="ids"
                                        value={selectedIds.join(',')}
                                    />
                                    {selectedIds.map((id) => (
                                        <input
                                            key={id}
                                            type="hidden"
                                            name="ids[]"
                                            value={id}
                                        />
                                    ))}
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        <Archive data-icon="inline-start" />{' '}
                                        Archive {selectedIds.length} counties
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </SheetContent>
            </Sheet>
        </Card>
    );
}

function CountyForm({
    form,
    county,
    submitLabel,
    onSuccess,
}: {
    form: { action: string; method: Method };
    county?: Props['counties']['data'][number];
    submitLabel: string;
    onSuccess?: () => void;
}) {
    return (
        <Form
            {...form}
            resetOnSuccess={!county}
            onSuccess={onSuccess}
            className="flex flex-col gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <UniqueValueInput
                        id="county-code"
                        name="code"
                        label="County code"
                        resource="counties"
                        field="code"
                        defaultValue={
                            county ? String(county.identity.code) : ''
                        }
                        excludeId={county?.id}
                        serverError={errors.code}
                        required
                    />
                    <UniqueValueInput
                        id="county-name"
                        name="name"
                        label="County name"
                        resource="counties"
                        field="name"
                        defaultValue={county?.identity.name ?? ''}
                        excludeId={county?.id}
                        serverError={errors.name}
                        required
                    />
                    <Field label="Region" name="county-region">
                        <Input
                            id="county-region"
                            name="region"
                            defaultValue={county?.region ?? ''}
                            aria-invalid={Boolean(errors.region)}
                        />
                    </Field>
                    <Field label="Official website" name="county-website">
                        <Input
                            id="county-website"
                            name="official_website_url"
                            type="url"
                            defaultValue={
                                county?.identity.officialWebsiteUrl ?? ''
                            }
                            aria-invalid={Boolean(errors.official_website_url)}
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Map X" name="county-map-x">
                            <Input
                                id="county-map-x"
                                name="map_x"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                defaultValue={county?.mapX ?? ''}
                                required
                                aria-invalid={Boolean(errors.map_x)}
                            />
                        </Field>
                        <Field label="Map Y" name="county-map-y">
                            <Input
                                id="county-map-y"
                                name="map_y"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                defaultValue={county?.mapY ?? ''}
                                required
                                aria-invalid={Boolean(errors.map_y)}
                            />
                        </Field>
                    </div>
                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}

function ProgrammeCoverageRegister({
    workspace,
    options,
    filters,
    canManage,
}: {
    workspace: Props['programmeCoverages'];
    options: Props['options'];
    filters: Props['filters'];
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
                            {...storeProgrammeCoverage.form()}
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
                            workspace: 'programme-coverage',
                            filters,
                        }}
                        renderActionControl={(row) => (
                            <ProgrammeCoverageAction row={row} />
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

function ProgrammeCoverageAction({ row }: { row: WorkspaceRow }) {
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
}: {
    releases: ReferenceDataRelease[];
    capabilities: Props['capabilities'];
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
                            {...storeRelease.form()}
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
                            {...publishRelease.form({ release: selected.id })}
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
