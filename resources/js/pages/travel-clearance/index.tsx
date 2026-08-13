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

const clearanceStatuses = [
    'draft',
    'manager_review',
    'finance_review',
    'approved',
    'rejected',
    'cancelled',
];

function useTravelCopy(): Record<string, string> {
    return usePage().props.localization.travelClearance;
}

export default function TravelClearance({
    requests,
    filters,
    capabilities,
    options,
    analytics,
}: Props) {
    const { auth, localization } = usePage().props;
    const copy = useTravelCopy();
    const locale = localization.current;
    const statusOptions = clearanceStatuses.map((status) => ({
        id: status,
        name: translateValue(copy, status),
    }));

    const rows: WorkspaceRow[] = requests.data.map((request) => ({
        id: request.id,
        status: request.status,
        cells: [
            request.reference,
            request.requester,
            request.countyIdentity ?? copy.national,
            request.destination,
            `${formatDate(request.departureDate, locale)} – ${formatDate(request.returnDate, locale)}`,
            formatMoney(
                Number(request.estimatedCost),
                request.currency,
                locale,
            ),
            request.referenceRelease,
            translateValue(copy, request.status),
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
                        {capabilities.submit && (
                            <TravelRequestForm options={options} />
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
                            label: copy.clearance_status,
                            options: statusOptions,
                            value: filters.status,
                        },
                        {
                            key: 'county_id',
                            label: copy.county,
                            options: options.counties,
                            value: filters.county_id,
                        },
                        {
                            key: 'sector_id',
                            label: copy.sector,
                            options: options.sectors,
                            value: filters.sector_id,
                        },
                    ]}
                />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        label={copy.requests}
                        value={analytics.summary.total.toLocaleString()}
                    />
                    <MetricCard
                        label={copy.approved}
                        value={analytics.summary.approved.toLocaleString()}
                    />
                    <MetricCard
                        label={copy.rejected}
                        value={analytics.summary.rejected.toLocaleString()}
                    />
                    <MetricCard
                        label={copy.average_decision_time}
                        value={
                            analytics.summary.averageTurnaroundHours === null
                                ? '—'
                                : `${analytics.summary.averageTurnaroundHours} ${copy.hours}`
                        }
                    />
                </section>

                <section className="grid gap-4 xl:grid-cols-3">
                    <AnalyticsTable
                        title={copy.cost_by_currency}
                        description={copy.cost_by_currency_description}
                        columns={[
                            copy.currency,
                            copy.requests,
                            copy.total_estimate,
                            copy.average,
                        ]}
                        rows={analytics.costs.map((item) => ({
                            id: item.id,
                            cells: [
                                item.currency,
                                item.requests,
                                formatMoney(
                                    item.totalCost,
                                    item.currency,
                                    locale,
                                ),
                                formatMoney(
                                    item.averageCost,
                                    item.currency,
                                    locale,
                                ),
                            ],
                        }))}
                    />
                    <AnalyticsTable
                        title={copy.frequent_destinations}
                        description={copy.frequent_destinations_description}
                        columns={[
                            copy.destination,
                            copy.requests,
                            copy.estimated_cost,
                        ]}
                        rows={analytics.destinations.map((item) => ({
                            id: item.id,
                            cells: [
                                item.destination,
                                item.requests,
                                formatMoney(
                                    item.totalCost,
                                    item.currency,
                                    locale,
                                ),
                            ],
                        }))}
                    />
                    <AnalyticsTable
                        title={copy.decision_pipeline}
                        description={copy.decision_pipeline_description}
                        columns={[copy.status, copy.requests]}
                        rows={analytics.statuses.map((item) => ({
                            id: item.id,
                            status: item.status,
                            cells: [
                                translateValue(copy, item.status),
                                item.requests,
                            ],
                        }))}
                    />
                </section>

                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex items-center justify-between gap-4 border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">
                                {copy.clearance_register}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {requests.total.toLocaleString()}{' '}
                                {copy.authorized_portfolio_count}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <DownloadIcon /> {copy.export}
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
                                copy.reference,
                                copy.requester,
                                copy.county,
                                copy.destination,
                                copy.travel_dates,
                                copy.estimate,
                                copy.reference_release,
                                copy.status,
                            ]}
                            rows={rows}
                            pagination={pagination}
                            bulkExport={{
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
                                        currentUserId={auth.user.id}
                                        capabilities={capabilities}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.empty_title}
                            description={copy.empty_description}
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
    const copy = useTravelCopy();

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
                        title={`${copy.no_data_prefix} ${title.toLowerCase()} ${copy.data}`}
                        description={copy.analytics_empty_description}
                        className="min-h-48 border-0"
                    />
                )}
            </CardContent>
        </Card>
    );
}

function formatMoney(value: number, currency: string, locale: string): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

function TravelRequestForm({ options }: { options: Props['options'] }) {
    const copy = useTravelCopy();
    const [segments, setSegments] = useState([0]);

    return (
        <FormSheet
            title={copy.new_request}
            description={copy.new_request_description}
            triggerLabel={copy.new_request}
            icon={Plus}
            size="xl"
        >
            <Form action={store()} className="grid gap-6 pt-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <SearchableSelect
                                id="travel-county"
                                name="county_id"
                                label={copy.county}
                                options={options.counties}
                                optional
                                error={errors.county_id}
                            />
                            <SearchableSelect
                                id="travel-organization"
                                name="organization_id"
                                label={copy.organization}
                                options={options.organizations}
                                optional
                                error={errors.organization_id}
                            />
                            <SearchableSelect
                                id="travel-sector"
                                name="sector_id"
                                label={copy.sector}
                                options={options.sectors}
                                optional
                                error={errors.sector_id}
                            />
                            <SearchableSelect
                                id="travel-type"
                                name="travel_type"
                                label={copy.travel_type}
                                options={[
                                    { id: 'domestic', name: copy.domestic },
                                    {
                                        id: 'international',
                                        name: copy.international,
                                    },
                                ]}
                                defaultValue="domestic"
                                error={errors.travel_type}
                            />
                            <Field
                                name="purpose"
                                label={copy.purpose}
                                error={errors.purpose}
                            />
                            <GeographyCatalogFields
                                countryError={errors.destination_country}
                                subdivisionError={errors.destination_county}
                                cityError={errors.destination_city}
                            />
                            <DatePickerField
                                name="departure_date"
                                label={copy.departure_date}
                                required
                                error={errors.departure_date}
                            />
                            <DatePickerField
                                name="return_date"
                                label={copy.return_date}
                                required
                                error={errors.return_date}
                            />
                            <Field
                                name="estimated_cost"
                                label={copy.total_estimated_cost}
                                type="number"
                                error={errors.estimated_cost}
                            />
                            <ReferenceCatalogSelect
                                id="travel-currency"
                                name="currency"
                                label={copy.currency}
                                catalog="currency"
                                error={errors.currency}
                            />
                            <Field
                                name="funding_source"
                                label={copy.funding_source}
                                error={errors.funding_source}
                            />
                            <Field
                                name="cost_centre"
                                label={copy.cost_centre}
                                error={errors.cost_centre}
                            />
                            <Field
                                name="hris_employee_reference"
                                label={copy.hris_employee_reference}
                                error={errors.hris_employee_reference}
                            />
                            <SearchableSelect
                                id="travel-priority"
                                name="priority"
                                label={copy.priority}
                                options={[
                                    { id: 'normal', name: copy.normal },
                                    { id: 'urgent', name: copy.urgent },
                                ]}
                                defaultValue="normal"
                                error={errors.priority}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="travel-justification">
                                {copy.business_justification}
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
                                    <h3 className="font-semibold">
                                        {copy.itinerary}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {copy.itinerary_description}
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
                                    {copy.add_segment}
                                </Button>
                            </div>
                            {segments.map((segment, position) => (
                                <Card key={segment}>
                                    <CardHeader className="flex-row items-center justify-between">
                                        <CardTitle className="text-base">
                                            {copy.segment} {position + 1}
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
                                                {copy.remove}
                                            </Button>
                                        )}
                                    </CardHeader>
                                    <CardContent className="grid gap-4 md:grid-cols-2">
                                        <Field
                                            name={`itineraries[${position}][origin]`}
                                            label={copy.origin}
                                            error={
                                                errors[
                                                    `itineraries.${position}.origin`
                                                ]
                                            }
                                        />
                                        <Field
                                            name={`itineraries[${position}][destination]`}
                                            label={copy.destination}
                                            error={
                                                errors[
                                                    `itineraries.${position}.destination`
                                                ]
                                            }
                                        />
                                        <DatePickerField
                                            name={`itineraries[${position}][departs_at]`}
                                            label={copy.departs_at}
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
                                            label={copy.arrives_at}
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
                                            label={copy.transport_mode}
                                            options={[
                                                'air',
                                                'road',
                                                'rail',
                                                'water',
                                                'other',
                                            ].map((value) => ({
                                                id: value,
                                                name: translateValue(
                                                    copy,
                                                    value,
                                                ),
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
                                            label={copy.carrier}
                                            error={
                                                errors[
                                                    `itineraries.${position}.carrier`
                                                ]
                                            }
                                        />
                                        <Field
                                            name={`itineraries[${position}][estimated_cost]`}
                                            label={copy.segment_estimated_cost}
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
                            {copy.save_draft_request}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function TravelRequestActions({
    request,
    currentUserId,
    capabilities,
}: {
    request: TravelRequest;
    currentUserId: string;
    capabilities: Props['capabilities'];
}) {
    const copy = useTravelCopy();
    const [surface, setSurface] = useState<'details' | string | null>(null);
    const transitions: Array<{ id: string; label: string; visible: boolean }> =
        [
            {
                id: 'submit',
                label: copy.submit_manager_review,
                visible:
                    capabilities.submit &&
                    request.requesterId === currentUserId &&
                    request.status === 'draft',
            },
            {
                id: 'cancel',
                label: copy.cancel_request,
                visible:
                    capabilities.submit &&
                    request.requesterId === currentUserId &&
                    request.status === 'draft',
            },
            {
                id: 'manager_approve',
                label: copy.approve_finance_review,
                visible:
                    capabilities.approve && request.status === 'manager_review',
            },
            {
                id: 'manager_reject',
                label: copy.reject_manager_review,
                visible:
                    capabilities.approve && request.status === 'manager_review',
            },
            {
                id: 'finance_clear',
                label: copy.confirm_finance_clearance,
                visible:
                    capabilities.financeClear &&
                    request.status === 'finance_review',
            },
            {
                id: 'finance_reject',
                label: copy.reject_finance_clearance,
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
                        aria-label={`${copy.actions_for} ${request.reference}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-64">
                    <DropdownMenuItem onSelect={() => setSurface('details')}>
                        <Eye /> {copy.view_complete_request}
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
                                : translateValue(copy, surface ?? '')}
                        </SheetTitle>
                        <SheetDescription>
                            {surface === 'details'
                                ? copy.complete_record_description
                                : `${copy.record_decision_for} ${request.reference}.`}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-8">
                        {surface === 'details' ? (
                            <TravelDetails
                                request={request}
                                currentUserId={currentUserId}
                            />
                        ) : surface ? (
                            <TransitionForm
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
    request,
    transitionName,
}: {
    request: TravelRequest;
    transitionName: string;
}) {
    const copy = useTravelCopy();
    const needsFinanceReference = transitionName === 'finance_clear';

    return (
        <Form
            action={transition({ travelRequest: request.id })}
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
                            {copy.decision_rationale}
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
                            label={copy.approved_cost}
                            type="number"
                            defaultValue={request.estimatedCost}
                            error={errors.approved_cost}
                        />
                    )}
                    {needsFinanceReference && (
                        <Field
                            name="finance_commitment_reference"
                            label={copy.finance_commitment_reference}
                            error={errors.finance_commitment_reference}
                        />
                    )}
                    <Button type="submit" disabled={processing}>
                        {translateValue(copy, transitionName)}
                    </Button>
                </>
            )}
        </Form>
    );
}

function TravelDetails({
    request,
    currentUserId,
}: {
    request: TravelRequest;
    currentUserId: string;
}) {
    const copy = useTravelCopy();
    const locale = usePage().props.localization.current;

    return (
        <div className="grid gap-6 pt-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-lg border p-3">
                    <p className="text-xs font-medium text-muted-foreground">
                        {copy.county}
                    </p>
                    <div className="pt-2">
                        {request.countyIdentity ? (
                            <CountyIdentity county={request.countyIdentity} />
                        ) : (
                            <p className="text-sm">{copy.national}</p>
                        )}
                    </div>
                </div>
                {[
                    [copy.requester, request.requester],
                    [copy.organization, request.organization ?? '—'],
                    [copy.sector, request.sector ?? '—'],
                    [copy.purpose, request.purpose],
                    [copy.destination, request.destination],
                    [
                        copy.travel_dates,
                        `${formatDate(request.departureDate, locale)} – ${formatDate(request.returnDate, locale)}`,
                    ],
                    [
                        copy.estimate,
                        formatMoney(
                            Number(request.estimatedCost),
                            request.currency,
                            locale,
                        ),
                    ],
                    [copy.funding_source, request.fundingSource],
                    [copy.cost_centre, request.costCentre ?? '—'],
                    [
                        copy.hris_reference,
                        request.hrisReference ?? copy.not_linked,
                    ],
                    [copy.reference_release, request.referenceRelease],
                    [copy.reference_checksum, request.referenceChecksum ?? '—'],
                    [
                        copy.finance_reference,
                        request.financeReference ?? copy.pending,
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
                <h3 className="font-semibold">{copy.business_justification}</h3>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    {request.justification}
                </p>
            </div>
            <div className="grid gap-3">
                <div className="flex items-center gap-2">
                    <FileText className="size-4" aria-hidden="true" />
                    <h3 className="font-semibold">
                        {copy.supporting_documents}
                    </h3>
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
                                    {translateValue(copy, document.sourceType)}{' '}
                                    {copy.separator}{' '}
                                    {document.originalName ??
                                        document.mimeType ??
                                        copy.file}{' '}
                                    {copy.separator}{' '}
                                    {translateValue(copy, document.scanStatus)}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button asChild variant="outline" size="sm">
                                    <a
                                        href={previewEvidence.url({
                                            document: document.id,
                                        })}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {copy.preview}
                                    </a>
                                </Button>
                                <Button asChild variant="outline" size="sm">
                                    <a
                                        href={downloadEvidence.url({
                                            document: document.id,
                                        })}
                                    >
                                        {copy.download}
                                    </a>
                                </Button>
                            </div>
                        </div>
                    ))
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {copy.no_supporting_documents}
                    </p>
                )}
                {request.status === 'draft' &&
                    request.requesterId === currentUserId && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {copy.upload_supporting_record}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={storeTravelDocument({
                                        travelRequest: request.id,
                                    })}
                                    resetOnSuccess
                                    className="grid gap-4"
                                >
                                    {({ errors, processing, progress }) => (
                                        <>
                                            <Field
                                                name="title"
                                                label={copy.document_title}
                                                error={errors.title}
                                            />
                                            <Field
                                                name="category"
                                                label={copy.record_category}
                                                error={errors.category}
                                            />
                                            <SearchableSelect
                                                id={`source-type-${request.id}`}
                                                name="source_type"
                                                label={copy.source_type}
                                                options={[
                                                    {
                                                        id: 'digital',
                                                        name: copy.born_digital,
                                                    },
                                                    {
                                                        id: 'scanned',
                                                        name: copy.scanned_copy,
                                                    },
                                                ]}
                                                defaultValue="digital"
                                                error={errors.source_type}
                                            />
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`travel-document-${request.id}`}
                                                >
                                                    {copy.document}
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
                                                    ? `${copy.uploading} ${progress.percentage}%`
                                                    : copy.upload_supporting_record}
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
                    <h3 className="font-semibold">{copy.itinerary}</h3>
                </div>
                {request.itineraries.map((item) => (
                    <div key={item.id} className="rounded-lg border p-4">
                        <p className="font-medium">
                            {item.origin} {copy.route_arrow} {item.destination}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {new Date(item.departsAt).toLocaleString(locale)}{' '}
                            {copy.range_separator}{' '}
                            {new Date(item.arrivesAt).toLocaleString(locale)}{' '}
                            {copy.separator}{' '}
                            {translateValue(copy, item.transportMode)}{' '}
                            {copy.separator}{' '}
                            {item.carrier ?? copy.carrier_pending}
                        </p>
                    </div>
                ))}
            </div>
            <div className="grid gap-3">
                <div className="flex items-center gap-2">
                    <FileCheck2 className="size-4" />
                    <h3 className="font-semibold">{copy.decision_history}</h3>
                </div>
                {request.approvals.length ? (
                    request.approvals.map((approval) => (
                        <div
                            key={approval.id}
                            className="rounded-lg border p-4"
                        >
                            <div className="flex justify-between gap-3">
                                <p className="font-medium">
                                    {translateValue(copy, approval.stage)}{' '}
                                    {copy.separator}{' '}
                                    {translateValue(copy, approval.decision)}
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
                        {copy.no_decisions}
                    </p>
                )}
            </div>
            <div className="flex items-center gap-2 rounded-lg border bg-muted/30 p-4">
                <Banknote className="size-5" />
                <div>
                    <p className="text-sm font-medium">
                        {copy.integration_status}
                        {': '}
                        {translateValue(copy, request.integrationStatus)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {copy.integration_description}
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
function translateValue(copy: Record<string, string>, value: string): string {
    return copy[`value_${value}`] ?? humanize(value);
}
function formatDate(value: string, locale: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString(locale, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
