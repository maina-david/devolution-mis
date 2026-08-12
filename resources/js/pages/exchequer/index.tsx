import { Form, Head, usePage } from '@inertiajs/react';
import {
    Banknote,
    Clock,
    Download,
    Eye,
    MoreHorizontal,
    Plus,
    Send,
} from 'lucide-react';
import { useState } from 'react';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
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
import type { WorkspaceRow } from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import {
    DEFAULT_CURRENCY_CODE,
    DEFAULT_LOCALE,
    formatCurrency,
    formatNumber,
} from '@/lib/reference-catalog';
import { store as storeExchequer } from '@/routes/exchequer';
import { store as storeEvent } from '@/routes/exchequer/events';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string };
type Event = {
    id: string;
    type: string;
    source: string;
    sourceReference: string;
    occurredAt: string;
    receivedAt: string;
    elapsedStageMinutes: number;
    elapsedTotalMinutes: number;
    notes: string | null;
    checksum: string;
    recorder: string;
    exchange: null | { id: string; correlationId: string; checksum: string };
};
type ExchequerRequest = {
    id: string;
    reference: string;
    trancheReference: string;
    county: CountyIdentityValue;
    grant: string;
    amount: number;
    currency: string;
    stage: string;
    status: string;
    stageDueAt: string | null;
    overdue: boolean;
    creator: string;
    createdAt: string;
    creditedAt: string | null;
    events: Event[];
    referenceData: null | {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    };
};
type PageSet<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    requests: PageSet<ExchequerRequest>;
    summary: {
        total: number;
        completed: number;
        overdue: number;
        creditedAmount: number;
        averageTurnaroundHours: number | null;
    };
    filters: Record<string, string | undefined>;
    capabilities: { create: boolean; recordEvents: boolean };
    options: {
        counties: CountyIdentityValue[];
        grants: Array<Option & { countyId: string }>;
        exchanges: Option[];
    };
    catalogue: {
        available: boolean;
        version?: number;
        effectiveFrom?: string | null;
        checksum?: string;
    };
};

const stages = [
    'prepared',
    'submitted_to_treasury',
    'forwarded_to_ocob',
    'authorized_by_ocob',
    'issued_to_cbk',
    'credited',
    'returned',
    'exception',
];
const nextEvents: Record<string, string[]> = {
    prepared: ['submitted_to_treasury', 'exception'],
    submitted_to_treasury: ['treasury_forwarded_ocob', 'returned', 'exception'],
    forwarded_to_ocob: ['ocob_authorized', 'returned', 'exception'],
    authorized_by_ocob: ['treasury_issued_cbk', 'exception'],
    issued_to_cbk: ['cbk_credited', 'exception'],
};
const eventSources: Record<string, string[]> = {
    submitted_to_treasury: ['IDMIS', 'TREASURY'],
    treasury_forwarded_ocob: ['TREASURY'],
    ocob_authorized: ['OCOB'],
    treasury_issued_cbk: ['TREASURY'],
    cbk_credited: ['CBK'],
    returned: ['TREASURY', 'OCOB'],
    exception: ['IDMIS', 'TREASURY', 'OCOB', 'CBK'],
};

export default function ExchequerTracking({
    requests,
    summary,
    filters,
    capabilities,
    options,
    catalogue,
}: Props) {
    const { routeContext } = usePage().props;

    if (!routeContext) {
        return null;
    }

    const rows: WorkspaceRow[] = requests.data.map((request) => ({
        id: request.id,
        status: request.overdue ? 'overdue' : request.status,
        cells: [
            request.reference,
            request.trancheReference,
            request.county,
            request.grant,
            formatCurrency(request.amount, request.currency),
            humanize(request.stage),
            request.events.length,
            request.events.length
                ? `${(request.events.at(-1)!.elapsedTotalMinutes / 60).toFixed(1)} h`
                : 'Not started',
            request.overdue ? 'Overdue' : humanize(request.status),
        ],
    }));

    return (
        <>
            <Head title="Exchequer tracking" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] uppercase opacity-75">
                                KDSP II funds-flow assurance
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Exchequer turnaround tracker
                            </h1>
                            <p className="mt-3 max-w-2xl opacity-80">
                                Trace county fund requests through the National
                                Treasury, OCoB authorization and CBK credit
                                confirmation using immutable source events and
                                auditable elapsed-time evidence.
                            </p>
                        </div>
                        {capabilities.create && (
                            <RequestForm
                                options={options}
                                catalogue={catalogue}
                            />
                        )}
                    </div>
                </section>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Metric
                        title="Requests"
                        value={summary.total.toLocaleString()}
                        description="Authorized scope"
                    />
                    <Metric
                        title="Completed"
                        value={summary.completed.toLocaleString()}
                        description="CBK credit confirmed"
                    />
                    <Metric
                        title="Overdue"
                        value={summary.overdue.toLocaleString()}
                        description="Current stage SLA"
                    />
                    <Metric
                        title="Credited"
                        value={formatCurrency(summary.creditedAmount)}
                        description="Completed requests"
                    />
                    <Metric
                        title="Average turnaround"
                        value={
                            summary.averageTurnaroundHours === null
                                ? 'Pending'
                                : `${summary.averageTurnaroundHours} h`
                        }
                        description="Creation to CBK credit"
                    />
                </div>
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'county_id',
                            label: 'County',
                            options: options.counties,
                            value: filters.county_id,
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            options: [
                                'open',
                                'completed',
                                'returned',
                                'exception',
                            ].map(option),
                            value: filters.status,
                        },
                        {
                            key: 'stage',
                            label: 'Current stage',
                            options: stages.map(option),
                            value: filters.stage,
                        },
                    ]}
                />
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">Funds-flow register</h2>
                            <p className="text-sm text-muted-foreground">
                                {requests.total.toLocaleString()} county-scoped
                                requests with source-attributed stage telemetry
                            </p>
                        </div>
                        <ExportMenu filters={filters} />
                    </div>
                    {rows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Reference',
                                'Tranche',
                                'County',
                                'Grant',
                                'Amount',
                                'Stage',
                                'Events',
                                'Elapsed',
                                'Status',
                            ]}
                            rows={rows}
                            pagination={{
                                currentPage: requests.current_page,
                                lastPage: requests.last_page,
                                perPage: requests.per_page,
                                total: requests.total,
                            }}
                            renderActionControl={(row) => {
                                const request = requests.data.find(
                                    (item) => item.id === row.id,
                                );

                                return request ? (
                                    <RequestActions
                                        request={request}
                                        canRecord={capabilities.recordEvents}
                                        exchanges={options.exchanges}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No exchequer requests found"
                            description="Adjust the filters or create the first grant-linked request for Treasury submission."
                            className="min-h-72 border-0"
                        />
                    )}
                </section>
            </div>
        </>
    );
}

function Metric({
    title,
    value,
    description,
}: {
    title: string;
    value: string;
    description: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-2xl">{value}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-xs text-muted-foreground">{description}</p>
            </CardContent>
        </Card>
    );
}

function RequestForm({
    options,
    catalogue,
}: {
    options: Props['options'];
    catalogue: Props['catalogue'];
}) {
    return (
        <FormSheet
            title="Create exchequer request"
            description="Bind a controlled tranche to an authorized county grant before external lifecycle events are recorded."
            triggerLabel="New request"
            icon={Plus}
            triggerDisabled={
                !catalogue.available || options.grants.length === 0
            }
            triggerTitle={
                catalogue.available
                    ? options.grants.length === 0
                        ? 'No governed county grants are available.'
                        : undefined
                    : 'Publish an effective reference-data catalogue before creating requests.'
            }
        >
            <Form action={storeExchequer()} className="grid gap-4 pt-4">
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id="exchequer-grant"
                            name="county_grant_id"
                            label="County grant"
                            options={options.grants}
                        />
                        <Field
                            name="tranche_reference"
                            label="Tranche reference"
                            error={errors.tranche_reference}
                        />
                        <Field
                            name="amount"
                            label="Request amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            error={errors.amount}
                        />
                        <input
                            type="hidden"
                            name="currency"
                            value={DEFAULT_CURRENCY_CODE}
                        />
                        <Button type="submit" disabled={processing}>
                            <Banknote data-icon="inline-start" /> Create request
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function RequestActions({
    request,
    canRecord,
    exchanges,
}: {
    request: ExchequerRequest;
    canRecord: boolean;
    exchanges: Option[];
}) {
    const [surface, setSurface] = useState<'details' | 'event' | null>(null);
    const available = nextEvents[request.stage] ?? [];

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
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setSurface('details')}>
                        <Eye /> Open timeline
                    </DropdownMenuItem>
                    {canRecord && available.length > 0 && (
                        <DropdownMenuItem onSelect={() => setSurface('event')}>
                            <Send /> Record source event
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-3xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'event'
                                ? 'Record exchequer event'
                                : request.reference}
                        </SheetTitle>
                        <SheetDescription>
                            {request.county.name} · {request.grant} ·{' '}
                            {humanize(request.stage)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <div className="flex flex-wrap gap-2">
                                    <Badge>{humanize(request.status)}</Badge>
                                    <Badge variant="outline">
                                        {request.currency}{' '}
                                        {formatNumber(request.amount)}
                                    </Badge>
                                    {request.overdue && (
                                        <Badge variant="destructive">
                                            Stage overdue
                                        </Badge>
                                    )}
                                </div>
                                <Card>
                                    <CardHeader>
                                        <CardDescription>
                                            Authoritative catalogue lineage
                                        </CardDescription>
                                        <CardTitle className="text-base">
                                            {request.referenceData
                                                ? `Reference data release v${request.referenceData.version}`
                                                : 'Legacy · unpinned'}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2 text-sm">
                                        {request.referenceData ? (
                                            <>
                                                <p className="text-muted-foreground">
                                                    Effective{' '}
                                                    {request.referenceData
                                                        .effectiveFrom ??
                                                        'date unavailable'}
                                                </p>
                                                <p className="font-mono text-xs break-all text-muted-foreground">
                                                    {
                                                        request.referenceData
                                                            .checksum
                                                    }
                                                </p>
                                            </>
                                        ) : (
                                            <p className="text-muted-foreground">
                                                Created before authoritative
                                                catalogue pinning was enforced.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                                {request.events.length ? (
                                    request.events.map((event, index) => (
                                        <Card key={event.id}>
                                            <CardHeader>
                                                <CardDescription>
                                                    Stage {index + 1} ·{' '}
                                                    {event.source}
                                                </CardDescription>
                                                <CardTitle className="text-base">
                                                    {humanize(event.type)}
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent className="grid gap-2 text-sm">
                                                <p>
                                                    {new Date(
                                                        event.occurredAt,
                                                    ).toLocaleString(
                                                        DEFAULT_LOCALE,
                                                    )}{' '}
                                                    · {event.recorder}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {event.sourceReference} ·{' '}
                                                    {(
                                                        event.elapsedStageMinutes /
                                                        60
                                                    ).toFixed(1)}{' '}
                                                    stage hours ·{' '}
                                                    {(
                                                        event.elapsedTotalMinutes /
                                                        60
                                                    ).toFixed(1)}{' '}
                                                    total hours
                                                </p>
                                                {event.notes && (
                                                    <p>{event.notes}</p>
                                                )}
                                                <p className="font-mono text-xs break-all text-muted-foreground">
                                                    {event.checksum}
                                                </p>
                                                {event.exchange && (
                                                    <p className="font-mono text-xs text-muted-foreground">
                                                        Exchange{' '}
                                                        {
                                                            event.exchange
                                                                .correlationId
                                                        }
                                                    </p>
                                                )}
                                            </CardContent>
                                        </Card>
                                    ))
                                ) : (
                                    <WorkspaceEmptyState
                                        title="Awaiting first source event"
                                        description="The request is prepared but has not yet been submitted to the National Treasury."
                                    />
                                )}
                            </>
                        ) : surface === 'event' ? (
                            <EventForm
                                request={request}
                                events={available}
                                exchanges={exchanges}
                                onSuccess={() => setSurface(null)}
                            />
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function EventForm({
    request,
    events,
    exchanges,
    onSuccess,
}: {
    request: ExchequerRequest;
    events: string[];
    exchanges: Option[];
    onSuccess: () => void;
}) {
    const [eventType, setEventType] = useState(events[0] ?? '');

    return (
        <Form
            action={storeEvent({ exchequerRequest: request.id })}
            className="grid gap-4"
            onSuccess={onSuccess}
        >
            {({ errors, processing }) => (
                <>
                    <SearchableSelect
                        id={`exchequer-event-${request.id}`}
                        name="event_type"
                        label="Event"
                        options={events.map(option)}
                        value={eventType}
                        onValueChange={setEventType}
                    />
                    <SearchableSelect
                        id={`exchequer-source-${request.id}`}
                        name="source_system"
                        label="Attesting source"
                        options={(eventSources[eventType] ?? []).map(option)}
                    />
                    <Field
                        name="source_event_reference"
                        label="Source event reference"
                        error={errors.source_event_reference}
                    />
                    <DatePickerField
                        name="occurred_at"
                        label="Occurred at"
                        includeTime
                        required
                        error={errors.occurred_at}
                    />
                    <SearchableSelect
                        id={`exchequer-exchange-${request.id}`}
                        name="integration_exchange_id"
                        label="Linked successful exchange"
                        options={exchanges}
                        optional
                    />
                    <TextField
                        name="notes"
                        label="Exception or processing notes"
                        optional
                        error={errors.notes}
                    />
                    <Button type="submit" disabled={processing}>
                        <Clock data-icon="inline-start" /> Record immutable
                        event
                    </Button>
                </>
            )}
        </Form>
    );
}

function ExportMenu({ filters }: { filters: Props['filters'] }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    <Download data-icon="inline-start" /> Export
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                    <DropdownMenuItem key={format} asChild>
                        <a
                            href={exportMethod.url(
                                { workspace: 'exchequer', format },
                                { query: filters },
                            )}
                        >
                            {format.toUpperCase()}
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
function Field({
    name,
    label,
    type = 'text',
    min,
    step,
    error,
}: {
    name: string;
    label: string;
    type?: string;
    min?: string;
    step?: string;
    error?: string;
}) {
    const errorId = `${name}-error`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                min={min}
                step={step}
                required
                aria-invalid={Boolean(error)}
                aria-describedby={error ? errorId : undefined}
            />
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
function TextField({
    name,
    label,
    optional = false,
    error,
}: {
    name: string;
    label: string;
    optional?: boolean;
    error?: string;
}) {
    const errorId = `${name}-error`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Textarea
                id={name}
                name={name}
                required={!optional}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? errorId : undefined}
            />
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
function option(id: string) {
    return { id, name: humanize(id) };
}
function humanize(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}
