import { Form, Head, usePage } from '@inertiajs/react';
import {
    Banknote,
    DownloadIcon,
    Eye,
    FileCheck2,
    FileText,
    MoreHorizontal,
    Plane,
    Plus,
    RouteIcon,
    ShieldCheck,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import GeographyCatalogFields from '@/components/geography-catalog-fields';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
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
import {
    download as downloadEvidence,
    preview as previewEvidence,
} from '@/routes/evidence';
import { store, transition } from '@/routes/travel-clearance';
import { store as storeTravelDocument } from '@/routes/travel-clearance/documents';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string };
type Itinerary = {
    id: string;
    origin: string;
    destination: string;
    departsAt: string;
    arrivesAt: string;
    transportMode: string;
    carrier: string | null;
    estimatedCost: string;
};
type Approval = {
    id: string;
    actor: string;
    stage: string;
    decision: string;
    rationale: string;
    decidedAt: string;
};
type TravelRequest = {
    id: string;
    reference: string;
    requester: string;
    requesterId: string;
    county: string | null;
    countyIdentity: CountyIdentityValue | null;
    organization: string | null;
    sector: string | null;
    travelType: string;
    purpose: string;
    justification: string;
    destination: string;
    departureDate: string;
    returnDate: string;
    estimatedCost: string;
    currency: string;
    fundingSource: string;
    costCentre: string | null;
    hrisReference: string | null;
    financeReference: string | null;
    integrationStatus: string;
    referenceRelease: string;
    referenceChecksum: string | null;
    status: string;
    priority: string;
    decisionDueAt: string | null;
    itineraries: Itinerary[];
    approvals: Approval[];
    documents: Array<{
        id: string;
        title: string;
        originalName: string | null;
        mimeType: string | null;
        sourceType: string;
        scanStatus: string;
    }>;
};
type Props = {
    requests: {
        data: TravelRequest[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: Record<string, string | undefined>;
    capabilities: {
        submit: boolean;
        approve: boolean;
        financeClear: boolean;
    };
    options: {
        counties: Option[];
        organizations: Option[];
        sectors: Option[];
    };
    analytics: {
        summary: {
            total: number;
            approved: number;
            rejected: number;
            averageTurnaroundHours: number | null;
        };
        costs: Array<{
            id: string;
            currency: string;
            requests: number;
            totalCost: number;
            averageCost: number;
        }>;
        destinations: Array<{
            id: string;
            destination: string;
            currency: string;
            requests: number;
            totalCost: number;
        }>;
        statuses: Array<{ id: string; status: string; requests: number }>;
    };
};

const statusOptions = [
    'draft',
    'manager_review',
    'finance_review',
    'approved',
    'rejected',
    'cancelled',
].map((status) => ({ id: status, name: humanize(status) }));

export default function TravelClearance({
    requests,
    filters,
    capabilities,
    options,
    analytics,
}: Props) {
    const { currentTeam, auth } = usePage().props;

    if (!currentTeam) {
        return null;
    }

    const rows: WorkspaceRow[] = requests.data.map((request) => ({
        id: request.id,
        status: request.status,
        cells: [
            request.reference,
            request.requester,
            request.countyIdentity ?? 'National',
            request.destination,
            `${formatDate(request.departureDate)} – ${formatDate(request.returnDate)}`,
            `${request.currency} ${Number(request.estimatedCost).toLocaleString()}`,
            request.referenceRelease,
            humanize(request.status),
        ],
    }));
    const pagination: WorkspacePagination = {
        currentPage: requests.current_page,
        lastPage: requests.last_page,
        perPage: requests.per_page,
        total: requests.total,
    };

    return (
        <>
            <Head title="Travel clearance" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Controlled official travel
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Travel clearance
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                Route official travel through management review,
                                independent finance commitment, SLA monitoring,
                                and an immutable decision trail.
                            </p>
                        </div>
                        {capabilities.submit && (
                            <TravelRequestForm
                                teamSlug={currentTeam.slug}
                                options={options}
                            />
                        )}
                    </div>
                </section>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'status',
                            label: 'Clearance status',
                            options: statusOptions,
                            value: filters.status,
                        },
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
                    ]}
                />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label="Requests"
                        value={analytics.summary.total.toLocaleString()}
                    />
                    <MetricCard
                        label="Approved"
                        value={analytics.summary.approved.toLocaleString()}
                    />
                    <MetricCard
                        label="Rejected"
                        value={analytics.summary.rejected.toLocaleString()}
                    />
                    <MetricCard
                        label="Average decision time"
                        value={
                            analytics.summary.averageTurnaroundHours === null
                                ? '—'
                                : `${analytics.summary.averageTurnaroundHours} hours`
                        }
                    />
                </section>

                <section className="grid gap-4 xl:grid-cols-3">
                    <AnalyticsTable
                        title="Cost by currency"
                        description="Estimated travel exposure is kept currency-specific to avoid invalid conversions."
                        columns={[
                            'Currency',
                            'Requests',
                            'Total estimate',
                            'Average',
                        ]}
                        rows={analytics.costs.map((item) => ({
                            id: item.id,
                            cells: [
                                item.currency,
                                item.requests,
                                formatMoney(item.totalCost, item.currency),
                                formatMoney(item.averageCost, item.currency),
                            ],
                        }))}
                    />
                    <AnalyticsTable
                        title="Frequent destinations"
                        description="Top destinations by request frequency within the authorized portfolio."
                        columns={['Destination', 'Requests', 'Estimated cost']}
                        rows={analytics.destinations.map((item) => ({
                            id: item.id,
                            cells: [
                                item.destination,
                                item.requests,
                                formatMoney(item.totalCost, item.currency),
                            ],
                        }))}
                    />
                    <AnalyticsTable
                        title="Decision pipeline"
                        description="Current request distribution across clearance states."
                        columns={['Status', 'Requests']}
                        rows={analytics.statuses.map((item) => ({
                            id: item.id,
                            status: item.status,
                            cells: [humanize(item.status), item.requests],
                        }))}
                    />
                </section>

                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex items-center justify-between gap-4 border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">Clearance register</h2>
                            <p className="text-sm text-muted-foreground">
                                {requests.total.toLocaleString()} requests in
                                your authorized portfolio
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <DownloadIcon /> Export
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {['csv', 'xlsx', 'json', 'pdf'].map(
                                        (format) => (
                                            <DropdownMenuItem
                                                key={format}
                                                asChild
                                            >
                                                <a
                                                    href={exportMethod.url(
                                                        {
                                                            current_team:
                                                                currentTeam.slug,
                                                            workspace:
                                                                'travel-clearance',
                                                            format,
                                                        },
                                                        { query: filters },
                                                    )}
                                                >
                                                    {format.toUpperCase()}
                                                </a>
                                            </DropdownMenuItem>
                                        ),
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Plane
                                className="size-5 text-[#147a55]"
                                aria-hidden="true"
                            />
                        </div>
                    </div>
                    {rows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Reference',
                                'Requester',
                                'County',
                                'Destination',
                                'Travel dates',
                                'Estimate',
                                'Reference release',
                                'Status',
                            ]}
                            rows={rows}
                            pagination={pagination}
                            bulkExport={{
                                teamSlug: currentTeam.slug,
                                workspace: 'travel-clearance',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const request = requests.data.find(
                                    (item) => item.id === row.id,
                                );

                                return request ? (
                                    <TravelRequestActions
                                        request={request}
                                        teamSlug={currentTeam.slug}
                                        currentUserId={auth.user.id}
                                        capabilities={capabilities}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No matching travel requests"
                            description="Adjust the dates, search, status, county, or sector filters. Authorized staff can create the first clearance request from this workspace."
                            className="min-h-72 border-0"
                        />
                    )}
                </section>
            </div>
        </>
    );
}

function MetricCard({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent className="text-3xl font-bold tabular-nums">
                {value}
            </CardContent>
        </Card>
    );
}

function AnalyticsTable({
    title,
    description,
    columns,
    rows,
}: {
    title: string;
    description: string;
    columns: string[];
    rows: WorkspaceRow[];
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <p className="text-sm text-muted-foreground">{description}</p>
            </CardHeader>
            <CardContent className="p-0">
                {rows.length ? (
                    <WorkspaceDataTable
                        columns={columns}
                        rows={rows}
                        pagination={{
                            currentPage: 1,
                            lastPage: 1,
                            perPage: Math.max(rows.length, 1),
                            total: rows.length,
                        }}
                    />
                ) : (
                    <WorkspaceEmptyState
                        title={`No ${title.toLowerCase()} data`}
                        description="Matching travel requests will populate this analysis."
                        className="min-h-48 border-0"
                    />
                )}
            </CardContent>
        </Card>
    );
}

function formatMoney(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
}

function TravelRequestForm({
    teamSlug,
    options,
}: {
    teamSlug: string;
    options: Props['options'];
}) {
    const [segments, setSegments] = useState([0]);

    return (
        <FormSheet
            title="New travel request"
            description="Capture purpose, funding, HRIS reference, and the complete itinerary before submission."
            triggerLabel="New travel request"
            icon={Plus}
            size="xl"
        >
            <Form action={store(teamSlug)} className="grid gap-6 pt-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <SearchableSelect
                                id="travel-county"
                                name="county_id"
                                label="County"
                                options={options.counties}
                                optional
                                error={errors.county_id}
                            />
                            <SearchableSelect
                                id="travel-organization"
                                name="organization_id"
                                label="Organization"
                                options={options.organizations}
                                optional
                                error={errors.organization_id}
                            />
                            <SearchableSelect
                                id="travel-sector"
                                name="sector_id"
                                label="Sector"
                                options={options.sectors}
                                optional
                                error={errors.sector_id}
                            />
                            <SearchableSelect
                                id="travel-type"
                                name="travel_type"
                                label="Travel type"
                                options={[
                                    { id: 'domestic', name: 'Domestic' },
                                    {
                                        id: 'international',
                                        name: 'International',
                                    },
                                ]}
                                defaultValue="domestic"
                                error={errors.travel_type}
                            />
                            <Field
                                name="purpose"
                                label="Purpose"
                                error={errors.purpose}
                            />
                            <GeographyCatalogFields
                                countryError={errors.destination_country}
                                subdivisionError={errors.destination_county}
                                cityError={errors.destination_city}
                            />
                            <DatePickerField
                                name="departure_date"
                                label="Departure date"
                                required
                                error={errors.departure_date}
                            />
                            <DatePickerField
                                name="return_date"
                                label="Return date"
                                required
                                error={errors.return_date}
                            />
                            <Field
                                name="estimated_cost"
                                label="Total estimated cost"
                                type="number"
                                error={errors.estimated_cost}
                            />
                            <ReferenceCatalogSelect
                                id="travel-currency"
                                name="currency"
                                label="Currency"
                                catalog="currency"
                                error={errors.currency}
                            />
                            <Field
                                name="funding_source"
                                label="Funding source"
                                error={errors.funding_source}
                            />
                            <Field
                                name="cost_centre"
                                label="Cost centre"
                                error={errors.cost_centre}
                            />
                            <Field
                                name="hris_employee_reference"
                                label="HRIS employee reference"
                                error={errors.hris_employee_reference}
                            />
                            <SearchableSelect
                                id="travel-priority"
                                name="priority"
                                label="Priority"
                                options={[
                                    { id: 'normal', name: 'Normal' },
                                    { id: 'urgent', name: 'Urgent' },
                                ]}
                                defaultValue="normal"
                                error={errors.priority}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="travel-justification">
                                Business justification
                            </Label>
                            <Textarea
                                id="travel-justification"
                                name="justification"
                                rows={4}
                                required
                                aria-invalid={Boolean(errors.justification)}
                            />
                            <ErrorText value={errors.justification} />
                        </div>
                        <div className="grid gap-4 rounded-xl border p-4">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <h3 className="font-semibold">Itinerary</h3>
                                    <p className="text-sm text-muted-foreground">
                                        All segments must fall within the
                                        request travel dates.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setSegments((items) => [
                                            ...items,
                                            Math.max(...items) + 1,
                                        ])
                                    }
                                >
                                    Add segment
                                </Button>
                            </div>
                            {segments.map((segment, position) => (
                                <Card key={segment}>
                                    <CardHeader className="flex-row items-center justify-between">
                                        <CardTitle className="text-base">
                                            Segment {position + 1}
                                        </CardTitle>
                                        {segments.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setSegments((items) =>
                                                        items.filter(
                                                            (item) =>
                                                                item !==
                                                                segment,
                                                        ),
                                                    )
                                                }
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </CardHeader>
                                    <CardContent className="grid gap-4 md:grid-cols-2">
                                        <Field
                                            name={`itineraries[${position}][origin]`}
                                            label="Origin"
                                            error={
                                                errors[
                                                    `itineraries.${position}.origin`
                                                ]
                                            }
                                        />
                                        <Field
                                            name={`itineraries[${position}][destination]`}
                                            label="Destination"
                                            error={
                                                errors[
                                                    `itineraries.${position}.destination`
                                                ]
                                            }
                                        />
                                        <DatePickerField
                                            name={`itineraries[${position}][departs_at]`}
                                            label="Departs at"
                                            includeTime
                                            required
                                            error={
                                                errors[
                                                    `itineraries.${position}.departs_at`
                                                ]
                                            }
                                        />
                                        <DatePickerField
                                            name={`itineraries[${position}][arrives_at]`}
                                            label="Arrives at"
                                            includeTime
                                            required
                                            error={
                                                errors[
                                                    `itineraries.${position}.arrives_at`
                                                ]
                                            }
                                        />
                                        <SearchableSelect
                                            id={`segment-mode-${segment}`}
                                            name={`itineraries[${position}][transport_mode]`}
                                            label="Transport mode"
                                            options={[
                                                'air',
                                                'road',
                                                'rail',
                                                'water',
                                                'other',
                                            ].map((value) => ({
                                                id: value,
                                                name: humanize(value),
                                            }))}
                                            defaultValue="road"
                                            error={
                                                errors[
                                                    `itineraries.${position}.transport_mode`
                                                ]
                                            }
                                        />
                                        <Field
                                            name={`itineraries[${position}][carrier]`}
                                            label="Carrier"
                                            error={
                                                errors[
                                                    `itineraries.${position}.carrier`
                                                ]
                                            }
                                        />
                                        <Field
                                            name={`itineraries[${position}][estimated_cost]`}
                                            label="Segment estimated cost"
                                            type="number"
                                            defaultValue="0"
                                            error={
                                                errors[
                                                    `itineraries.${position}.estimated_cost`
                                                ]
                                            }
                                        />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                        <Button type="submit" disabled={processing}>
                            Save draft request
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function TravelRequestActions({
    request,
    teamSlug,
    currentUserId,
    capabilities,
}: {
    request: TravelRequest;
    teamSlug: string;
    currentUserId: string;
    capabilities: Props['capabilities'];
}) {
    const [surface, setSurface] = useState<'details' | string | null>(null);
    const transitions: Array<{ id: string; label: string; visible: boolean }> =
        [
            {
                id: 'submit',
                label: 'Submit for manager review',
                visible:
                    capabilities.submit &&
                    request.requesterId === currentUserId &&
                    request.status === 'draft',
            },
            {
                id: 'cancel',
                label: 'Cancel request',
                visible:
                    capabilities.submit &&
                    request.requesterId === currentUserId &&
                    request.status === 'draft',
            },
            {
                id: 'manager_approve',
                label: 'Approve for finance review',
                visible:
                    capabilities.approve && request.status === 'manager_review',
            },
            {
                id: 'manager_reject',
                label: 'Reject at manager review',
                visible:
                    capabilities.approve && request.status === 'manager_review',
            },
            {
                id: 'finance_clear',
                label: 'Confirm finance clearance',
                visible:
                    capabilities.financeClear &&
                    request.status === 'finance_review',
            },
            {
                id: 'finance_reject',
                label: 'Reject finance clearance',
                visible:
                    capabilities.financeClear &&
                    request.status === 'finance_review',
            },
        ];

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${request.reference}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-64">
                    <DropdownMenuItem onSelect={() => setSurface('details')}>
                        <Eye /> View complete request
                    </DropdownMenuItem>
                    {transitions
                        .filter((item) => item.visible)
                        .map((item) => (
                            <DropdownMenuItem
                                key={item.id}
                                onSelect={() => setSurface(item.id)}
                            >
                                <ShieldCheck /> {item.label}
                            </DropdownMenuItem>
                        ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'details'
                                ? request.reference
                                : humanize(surface ?? '')}
                        </SheetTitle>
                        <SheetDescription>
                            {surface === 'details'
                                ? 'Complete travel, itinerary, integration, and decision record.'
                                : `Record a reasoned decision for ${request.reference}.`}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-8">
                        {surface === 'details' ? (
                            <TravelDetails
                                request={request}
                                teamSlug={teamSlug}
                                currentUserId={currentUserId}
                            />
                        ) : surface ? (
                            <TransitionForm
                                teamSlug={teamSlug}
                                request={request}
                                transitionName={surface}
                            />
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function TransitionForm({
    teamSlug,
    request,
    transitionName,
}: {
    teamSlug: string;
    request: TravelRequest;
    transitionName: string;
}) {
    const needsFinanceReference = transitionName === 'finance_clear';

    return (
        <Form
            action={transition({
                current_team: teamSlug,
                travelRequest: request.id,
            })}
            className="grid gap-4 pt-4"
        >
            {({ errors, processing }) => (
                <>
                    <input
                        type="hidden"
                        name="transition"
                        value={transitionName}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor={`rationale-${request.id}`}>
                            Decision rationale
                        </Label>
                        <Textarea
                            id={`rationale-${request.id}`}
                            name="rationale"
                            rows={5}
                            required
                            aria-invalid={Boolean(errors.rationale)}
                        />
                        <ErrorText value={errors.rationale} />
                    </div>
                    {(transitionName.includes('approve') ||
                        needsFinanceReference) && (
                        <Field
                            name="approved_cost"
                            label="Approved cost"
                            type="number"
                            defaultValue={request.estimatedCost}
                            error={errors.approved_cost}
                        />
                    )}
                    {needsFinanceReference && (
                        <Field
                            name="finance_commitment_reference"
                            label="Finance commitment reference"
                            error={errors.finance_commitment_reference}
                        />
                    )}
                    <Button type="submit" disabled={processing}>
                        {humanize(transitionName)}
                    </Button>
                </>
            )}
        </Form>
    );
}

function TravelDetails({
    request,
    teamSlug,
    currentUserId,
}: {
    request: TravelRequest;
    teamSlug: string;
    currentUserId: string;
}) {
    return (
        <div className="grid gap-6 pt-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-lg border p-3">
                    <p className="text-xs font-medium text-muted-foreground">
                        County
                    </p>
                    <div className="pt-2">
                        {request.countyIdentity ? (
                            <CountyIdentity county={request.countyIdentity} />
                        ) : (
                            <p className="text-sm">National</p>
                        )}
                    </div>
                </div>
                {[
                    ['Requester', request.requester],
                    ['Organization', request.organization ?? '—'],
                    ['Sector', request.sector ?? '—'],
                    ['Purpose', request.purpose],
                    ['Destination', request.destination],
                    [
                        'Travel dates',
                        `${formatDate(request.departureDate)} – ${formatDate(request.returnDate)}`,
                    ],
                    [
                        'Estimate',
                        `${request.currency} ${Number(request.estimatedCost).toLocaleString()}`,
                    ],
                    ['Funding source', request.fundingSource],
                    ['Cost centre', request.costCentre ?? '—'],
                    ['HRIS reference', request.hrisReference ?? 'Not linked'],
                    ['Reference release', request.referenceRelease],
                    [
                        'Reference checksum',
                        request.referenceChecksum ?? '—',
                    ],
                    [
                        'Finance reference',
                        request.financeReference ?? 'Pending',
                    ],
                ].map(([label, value]) => (
                    <div key={label} className="rounded-lg border p-3">
                        <p className="text-xs font-medium text-muted-foreground">
                            {label}
                        </p>
                        <p className="mt-1 text-sm font-medium">{value}</p>
                    </div>
                ))}
            </div>
            <div>
                <h3 className="font-semibold">Business justification</h3>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    {request.justification}
                </p>
            </div>
            <div className="grid gap-3">
                <div className="flex items-center gap-2">
                    <FileText className="size-4" aria-hidden="true" />
                    <h3 className="font-semibold">Supporting documents</h3>
                </div>
                {request.documents.length ? (
                    request.documents.map((document) => (
                        <div
                            key={document.id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                        >
                            <div>
                                <p className="font-medium">{document.title}</p>
                                <p className="text-sm text-muted-foreground">
                                    {humanize(document.sourceType)} ·{' '}
                                    {document.originalName ??
                                        document.mimeType ??
                                        'File'}{' '}
                                    · {humanize(document.scanStatus)}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button asChild variant="outline" size="sm">
                                    <a
                                        href={previewEvidence.url({
                                            current_team: teamSlug,
                                            document: document.id,
                                        })}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Preview
                                    </a>
                                </Button>
                                <Button asChild variant="outline" size="sm">
                                    <a
                                        href={downloadEvidence.url({
                                            current_team: teamSlug,
                                            document: document.id,
                                        })}
                                    >
                                        Download
                                    </a>
                                </Button>
                            </div>
                        </div>
                    ))
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No supporting documents uploaded.
                    </p>
                )}
                {request.status === 'draft' &&
                    request.requesterId === currentUserId && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Upload supporting record
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={storeTravelDocument({
                                        current_team: teamSlug,
                                        travelRequest: request.id,
                                    })}
                                    resetOnSuccess
                                    className="grid gap-4"
                                >
                                    {({ errors, processing, progress }) => (
                                        <>
                                            <Field
                                                name="title"
                                                label="Document title"
                                                error={errors.title}
                                            />
                                            <Field
                                                name="category"
                                                label="Record category"
                                                error={errors.category}
                                            />
                                            <SearchableSelect
                                                id={`source-type-${request.id}`}
                                                name="source_type"
                                                label="Source type"
                                                options={[
                                                    {
                                                        id: 'digital',
                                                        name: 'Born-digital file',
                                                    },
                                                    {
                                                        id: 'scanned',
                                                        name: 'Scanned copy',
                                                    },
                                                ]}
                                                defaultValue="digital"
                                                error={errors.source_type}
                                            />
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`travel-document-${request.id}`}
                                                >
                                                    Document
                                                </Label>
                                                <Input
                                                    id={`travel-document-${request.id}`}
                                                    name="document"
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                                    required
                                                    aria-invalid={Boolean(
                                                        errors.document,
                                                    )}
                                                    aria-describedby={
                                                        errors.document
                                                            ? `travel-document-error-${request.id}`
                                                            : undefined
                                                    }
                                                />
                                                {errors.document && (
                                                    <p
                                                        id={`travel-document-error-${request.id}`}
                                                        role="alert"
                                                        className="text-xs text-destructive"
                                                    >
                                                        {errors.document}
                                                    </p>
                                                )}
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <Upload aria-hidden="true" />
                                                {progress
                                                    ? `Uploading ${progress.percentage}%`
                                                    : 'Upload supporting record'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    )}
            </div>
            <div className="grid gap-3">
                <div className="flex items-center gap-2">
                    <RouteIcon className="size-4" />
                    <h3 className="font-semibold">Itinerary</h3>
                </div>
                {request.itineraries.map((item) => (
                    <div key={item.id} className="rounded-lg border p-4">
                        <p className="font-medium">
                            {item.origin} → {item.destination}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {new Date(item.departsAt).toLocaleString(
                                DEFAULT_LOCALE,
                            )}{' '}
                            –{' '}
                            {new Date(item.arrivesAt).toLocaleString(
                                DEFAULT_LOCALE,
                            )}{' '}
                            · {humanize(item.transportMode)} ·{' '}
                            {item.carrier ?? 'Carrier pending'}
                        </p>
                    </div>
                ))}
            </div>
            <div className="grid gap-3">
                <div className="flex items-center gap-2">
                    <FileCheck2 className="size-4" />
                    <h3 className="font-semibold">Decision history</h3>
                </div>
                {request.approvals.length ? (
                    request.approvals.map((approval) => (
                        <div
                            key={approval.id}
                            className="rounded-lg border p-4"
                        >
                            <div className="flex justify-between gap-3">
                                <p className="font-medium">
                                    {humanize(approval.stage)} ·{' '}
                                    {humanize(approval.decision)}
                                </p>
                                <Badge variant="outline">
                                    {approval.actor}
                                </Badge>
                            </div>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {approval.rationale}
                            </p>
                        </div>
                    ))
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No approval decisions recorded yet.
                    </p>
                )}
            </div>
            <div className="flex items-center gap-2 rounded-lg border bg-muted/30 p-4">
                <Banknote className="size-5" />
                <div>
                    <p className="text-sm font-medium">
                        Integration status:{' '}
                        {humanize(request.integrationStatus)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        HRIS and finance identifiers remain visible for
                        reconciliation and exception handling.
                    </p>
                </div>
            </div>
        </div>
    );
}

function Field({
    name,
    label,
    type = 'text',
    defaultValue,
    error,
}: {
    name: string;
    label: string;
    type?: 'text' | 'number';
    defaultValue?: string;
    error?: string;
}) {
    const id = name.replaceAll(/[^a-zA-Z0-9_-]/g, '-');

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={name}
                type={type}
                defaultValue={defaultValue}
                step={type === 'number' ? '0.01' : undefined}
                required={
                    ![
                        'destination_county',
                        'cost_centre',
                        'hris_employee_reference',
                    ].includes(name) && !name.includes('carrier')
                }
                aria-invalid={Boolean(error)}
            />
            <ErrorText value={error} />
        </div>
    );
}

function ErrorText({ value }: { value?: string }) {
    return value ? (
        <p role="alert" className="text-xs text-destructive">
            {value}
        </p>
    ) : null;
}
function humanize(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}
function formatDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString(DEFAULT_LOCALE, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
