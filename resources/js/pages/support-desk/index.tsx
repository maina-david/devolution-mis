import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ClockAlert,
    ClipboardCheck,
    Download,
    Eye,
    FileUp,
    Headphones,
    MoreHorizontal,
    Plus,
    Settings2,
    ShieldCheck,
    UserRoundCheck,
} from 'lucide-react';
import { useState } from 'react';
import { storeSupportTicket } from '@/actions/App/Http/Controllers/LinkedDocumentController';
import {
    assign,
    publishPolicy,
    store,
    storePolicy,
    transition,
} from '@/actions/App/Http/Controllers/SupportDeskController';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import type { SearchableSelectOption } from '@/components/searchable-select';
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
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
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
import {
    DEFAULT_TIMEZONE,
    formatDateTime as formatCatalogDateTime,
    formatNumber,
} from '@/lib/reference-catalog';
import { download, preview } from '@/routes/evidence';
import { exportMethod as exportWorkspace } from '@/routes/workspace';

type Activity = {
    id: string;
    actor: string;
    type: string;
    fromStatus: string;
    toStatus: string;
    narrative: string;
    occurredAt: string;
    checksum: string;
};

type DocumentRecord = {
    id: string;
    title: string;
    originalName: string | null;
    mimeType: string | null;
    sizeBytes: number;
    checksum: string;
    scanStatus: string;
    ocrStatus: string;
    recordStatus: string;
};

type TicketDetail = {
    id: string;
    reference: string;
    subject: string;
    description: string;
    category: string;
    priority: string;
    channel: string;
    status: string;
    county: CountyIdentityValue | null;
    requester: { id: string; name: string; email: string };
    assignee: { id: string; name: string; email: string } | null;
    resolver: string | null;
    closer: string | null;
    resolutionSummary: string | null;
    requestedAt: string;
    firstResponseDueAt: string;
    firstRespondedAt: string | null;
    resolutionDueAt: string;
    resolvedAt: string | null;
    closedAt: string | null;
    referenceData: {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    };
    servicePolicy: {
        id: string;
        code: string;
        version: number;
        authorityStatus: string;
        approvalReference: string | null;
        checksum: string;
        calendar: {
            code: string;
            version: number;
            timezone: string;
            checksum: string;
        };
    } | null;
    activities: Activity[];
    documents: DocumentRecord[];
};

type ServicePolicy = {
    id: string;
    code: string;
    version: number;
    name: string;
    description: string;
    status: string;
    authorityStatus: string;
    approvalReference: string | null;
    effectiveFrom: string;
    effectiveTo: string | null;
    calendar: {
        id: string;
        name: string;
        code: string;
        version: number;
        timezone: string;
        checksum: string;
    };
    categories: Array<{ code: string; name: string }>;
    channels: string[];
    priorityTargets: Record<
        string,
        { first_response: number; resolution: number; reminder: number }
    >;
    creator: string;
    publisher: string | null;
    publishedAt: string | null;
    checksum: string | null;
    roster: Array<{
        id: string;
        user: string;
        county: CountyIdentityValue | null;
        tier: number;
        dutyRole: string;
        isPrimary: boolean;
        startsAt: string;
        endsAt: string | null;
    }>;
};

type Props = {
    workspace: {
        columns: string[];
        rows: WorkspaceRow[];
        pagination: WorkspacePagination;
    };
    details: Record<string, TicketDetail>;
    filters: Record<string, string | undefined>;
    summary: {
        total: number;
        active: number;
        unassigned: number;
        overdue: number;
    };
    options: {
        counties: CountyIdentityValue[];
        assignees: SearchableSelectOption[];
    };
    catalogue:
        | { available: false }
        | { available: true; version: number; checksum: string };
    effectiveServicePolicy: {
        id: string;
        code: string;
        version: number;
        authority_status: string;
        checksum: string;
    } | null;
    servicePolicies: ServicePolicy[];
    policyOptions: {
        calendars: SearchableSelectOption[];
        resolvers: SearchableSelectOption[];
    };
    capabilities: {
        submit: boolean;
        manage: boolean;
        resolve: boolean;
        national: boolean;
        userId: string;
        configurePolicy: boolean;
        publishPolicy: boolean;
    };
};

const statusOptions = [
    'open',
    'triaged',
    'in_progress',
    'awaiting_requester',
    'resolved',
    'closed',
].map((status) => ({ id: status, name: humanize(status) }));

export default function SupportDesk({
    workspace,
    details,
    filters,
    summary,
    options,
    catalogue,
    effectiveServicePolicy,
    servicePolicies,
    policyOptions,
    capabilities,
}: Props) {
    const copy = usePage().props.localization.supportDesk;
    const [selectedTicketId, setSelectedTicketId] = useState<string | null>(
        null,
    );

    const selectedTicket = selectedTicketId
        ? details[selectedTicketId]
        : undefined;

    return (
        <>
            <Head title="Service desk" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
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
                        {capabilities.submit && (
                            <CreateTicketSheet
                                counties={options.counties}
                                national={capabilities.national}
                                intakeAvailable={
                                    catalogue.available &&
                                    effectiveServicePolicy !== null
                                }
                            />
                        )}
                    </div>
                </section>

                <ServicePolicyRegister
                    policies={servicePolicies}
                    options={policyOptions}
                    capabilities={capabilities}
                    effectivePolicyId={effectiveServicePolicy?.id ?? null}
                />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        title="All authorized tickets"
                        value={summary.total}
                        description="Within your county or national scope"
                        icon={Headphones}
                    />
                    <Metric
                        title="Active workload"
                        value={summary.active}
                        description="Not yet resolved or accepted"
                        icon={ClockAlert}
                    />
                    <Metric
                        title="Awaiting triage"
                        value={summary.unassigned}
                        description="Open requests without an owner"
                        icon={UserRoundCheck}
                    />
                    <Metric
                        title="SLA overdue"
                        value={summary.overdue}
                        description="Resolution target has passed"
                        icon={ShieldCheck}
                    />
                </section>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    cycles={[]}
                    selectFilters={[
                        ...(capabilities.national
                            ? [
                                  {
                                      key: 'county_id',
                                      label: 'County',
                                      options: options.counties,
                                      value: filters.county_id,
                                  },
                              ]
                            : []),
                        {
                            key: 'status',
                            label: 'Ticket status',
                            options: statusOptions,
                            value: filters.status,
                        },
                    ]}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.support_case_register}</CardTitle>
                        <CardDescription>
                            {copy.support_case_register_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        <WorkspaceDataTable
                            columns={workspace.columns}
                            rows={workspace.rows}
                            pagination={workspace.pagination}
                            bulkExport={{
                                workspace: 'support-desk',
                                filters,
                            }}
                            allowFilteredBulkSelection
                            renderActionControl={(row) => (
                                <TicketMenu
                                    detail={details[row.id]}
                                    onView={() => setSelectedTicketId(row.id)}
                                />
                            )}
                        />
                    </CardContent>
                </Card>
            </main>

            <TicketSheet
                ticket={selectedTicket}
                open={Boolean(selectedTicket)}
                onOpenChange={(open) => !open && setSelectedTicketId(null)}
                assignees={options.assignees}
                capabilities={capabilities}
            />
        </>
    );
}

const serviceCategories = [
    { code: 'access', name: 'Access and identity' },
    { code: 'incident', name: 'Service incident' },
    { code: 'service_request', name: 'Service request' },
    { code: 'data_quality', name: 'Data quality' },
    { code: 'integration', name: 'Integration' },
    { code: 'training', name: 'Training and adoption' },
    { code: 'document', name: 'Documents and OCR' },
    { code: 'other', name: 'Other' },
];

const priorityDefaults: Record<
    string,
    { firstResponse: number; resolution: number; reminder: number }
> = {
    critical: { firstResponse: 1, resolution: 4, reminder: 0.5 },
    high: { firstResponse: 4, resolution: 16, reminder: 2 },
    medium: { firstResponse: 8, resolution: 40, reminder: 4 },
    low: { firstResponse: 16, resolution: 80, reminder: 8 },
};

function ServicePolicyRegister({
    policies,
    options,
    capabilities,
    effectivePolicyId,
}: {
    policies: ServicePolicy[];
    options: Props['policyOptions'];
    capabilities: Props['capabilities'];
    effectivePolicyId: string | null;
}) {
    const copy = usePage().props.localization.supportDesk;

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle>{copy.governed_service_catalogue}</CardTitle>
                    <CardDescription>
                        {copy.governed_service_catalogue_description}
                    </CardDescription>
                </div>
                {capabilities.configurePolicy && (
                    <div className="flex flex-wrap gap-2">
                        <PolicyExportMenu />
                        <CreateServicePolicySheet options={options} />
                    </div>
                )}
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {policies.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {copy.no_service_policy}
                    </p>
                )}
                {policies.map((policy) => (
                    <div key={policy.id} className="rounded-xl border p-4">
                        <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="font-semibold">
                                        {policy.name}
                                    </h3>
                                    <Badge variant="outline">
                                        {policy.code} {copy.separator}{' '}
                                        {copy.version_prefix}
                                        {policy.version}
                                    </Badge>
                                    <Badge
                                        variant={
                                            policy.status === 'published'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {humanize(policy.status)}
                                    </Badge>
                                    <Badge variant="outline">
                                        {humanize(policy.authorityStatus)}
                                    </Badge>
                                    {policy.id === effectivePolicyId && (
                                        <Badge>{copy.effective_now}</Badge>
                                    )}
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {policy.description}
                                </p>
                            </div>
                            {policy.status === 'draft' &&
                                capabilities.publishPolicy && (
                                    <PublishServicePolicySheet
                                        policy={policy}
                                    />
                                )}
                        </div>
                        <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                            <KeyValue
                                label="Business calendar"
                                value={`${policy.calendar.code} v${policy.calendar.version} · ${policy.calendar.timezone}`}
                            />
                            <KeyValue
                                label="Effective period"
                                value={`${formatDateTime(policy.effectiveFrom)} – ${policy.effectiveTo ? formatDateTime(policy.effectiveTo) : 'Open ended'}`}
                            />
                            <KeyValue
                                label="Roster"
                                value={`${policy.roster.length} governed member${policy.roster.length === 1 ? '' : 's'}`}
                            />
                            <KeyValue
                                label="Publication lineage"
                                value={
                                    policy.checksum
                                        ? `${policy.publisher ?? 'Unknown'} · ${policy.checksum.slice(0, 12)}…`
                                        : `Drafted by ${policy.creator}`
                                }
                            />
                        </div>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {Object.entries(policy.priorityTargets).map(
                                ([priority, target]) => (
                                    <div
                                        key={priority}
                                        className="rounded-lg border bg-muted/30 p-3 text-sm"
                                    >
                                        <p className="font-medium">
                                            {humanize(priority)}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {copy.respond}{' '}
                                            {target.first_response}
                                            {copy.hour_suffix}{' '}
                                            {copy.separator} {copy.resolve}{' '}
                                            {target.resolution}
                                            {copy.hour_suffix}{' '}
                                            {copy.separator} {copy.remind}{' '}
                                            {target.reminder}
                                            {copy.hour_suffix}{' '}
                                            {copy.before_due}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function PolicyExportMenu() {
    const copy = usePage().props.localization.supportDesk;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    <Download aria-hidden="true" />
                    {copy.export_policies}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>
                    {copy.export_governed_register}
                </DropdownMenuLabel>
                <DropdownMenuGroup>
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <Link
                                href={
                                    exportWorkspace({
                                        workspace: 'service-desk-policies',
                                        format,
                                    }).url
                                }
                            >
                                {format.toUpperCase()}
                            </Link>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function CreateServicePolicySheet({
    options,
}: {
    options: Props['policyOptions'];
}) {
    const copy = usePage().props.localization.supportDesk;
    const [effectiveFrom, setEffectiveFrom] = useState('');

    return (
        <FormSheet
            title="Draft service-desk policy"
            description="Create a new immutable candidate version. An independent publication decision is required before runtime use."
            triggerLabel="Configure policy"
            icon={Settings2}
            size="xl"
            triggerDisabled={
                options.calendars.length === 0 || options.resolvers.length < 2
            }
            triggerTitle={
                options.calendars.length === 0 || options.resolvers.length < 2
                    ? 'A published calendar and at least two authorized resolvers are required.'
                    : undefined
            }
        >
            <Form {...storePolicy.form()} resetOnSuccess>
                {({ errors, processing }) => (
                    <FieldGroup>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <PolicyInput
                                id="policy-code"
                                name="code"
                                label="Policy code"
                                defaultValue="IDMIS-SUPPORT"
                                error={errors.code}
                            />
                            <PolicyInput
                                id="policy-name"
                                name="name"
                                label="Policy name"
                                defaultValue="IDMIS operational support policy"
                                error={errors.name}
                            />
                        </div>
                        <Field data-invalid={Boolean(errors.description)}>
                            <FieldLabel htmlFor="policy-description">
                                {copy.scope_and_service_commitment}
                            </FieldLabel>
                            <Textarea
                                id="policy-description"
                                name="description"
                                required
                                rows={4}
                                defaultValue="Support for authorized IDMIS users across access, data, integration, records, training and operational incidents."
                                aria-invalid={Boolean(errors.description)}
                            />
                            <FieldError>{errors.description}</FieldError>
                        </Field>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <SearchableSelect
                                id="policy-calendar"
                                name="business_calendar_id"
                                label="Published business calendar"
                                options={options.calendars}
                                error={errors.business_calendar_id}
                            />
                            <DatePickerField
                                name="effective_from"
                                label="Effective from"
                                required
                                includeTime
                                error={errors.effective_from}
                                value={effectiveFrom}
                                onValueChange={setEffectiveFrom}
                            />
                        </div>
                        <DatePickerField
                            name="effective_to"
                            label="Effective to (required for a finite calendar)"
                            includeTime
                            min={effectiveFrom.slice(0, 10)}
                            error={errors.effective_to}
                        />
                        {serviceCategories.map((category, index) => (
                            <span key={category.code}>
                                <input
                                    type="hidden"
                                    name={`categories[${index}][code]`}
                                    value={category.code}
                                />
                                <input
                                    type="hidden"
                                    name={`categories[${index}][name]`}
                                    value={category.name}
                                />
                            </span>
                        ))}
                        {['web', 'email', 'phone', 'walk_in', 'training'].map(
                            (channel, index) => (
                                <input
                                    key={channel}
                                    type="hidden"
                                    name={`channels[${index}]`}
                                    value={channel}
                                />
                            ),
                        )}
                        <section aria-labelledby="priority-targets-heading">
                            <h3
                                id="priority-targets-heading"
                                className="font-semibold"
                            >
                                {copy.business_hour_service_targets}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {copy.business_hour_service_targets_description}
                            </p>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                {Object.entries(priorityDefaults).map(
                                    ([priority, defaults]) => (
                                        <div
                                            key={priority}
                                            className="rounded-xl border p-4"
                                        >
                                            <p className="font-medium">
                                                {humanize(priority)}
                                            </p>
                                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                                <PolicyNumberInput
                                                    name={`priority_targets[${priority}][first_response]`}
                                                    label="Response hours"
                                                    defaultValue={
                                                        defaults.firstResponse
                                                    }
                                                />
                                                <PolicyNumberInput
                                                    name={`priority_targets[${priority}][resolution]`}
                                                    label="Resolution hours"
                                                    defaultValue={
                                                        defaults.resolution
                                                    }
                                                />
                                                <PolicyNumberInput
                                                    name={`priority_targets[${priority}][reminder]`}
                                                    label="Reminder lead"
                                                    defaultValue={
                                                        defaults.reminder
                                                    }
                                                />
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </section>
                        {Object.keys(priorityDefaults).flatMap(
                            (priority, priorityIndex) =>
                                ['first_response', 'resolution'].flatMap(
                                    (stage, stageIndex) => {
                                        const index =
                                            priorityIndex * 2 + stageIndex;

                                        return [
                                            <input
                                                key={`${index}-priority`}
                                                type="hidden"
                                                name={`escalation_rules[${index}][priority]`}
                                                value={priority}
                                            />,
                                            <input
                                                key={`${index}-stage`}
                                                type="hidden"
                                                name={`escalation_rules[${index}][stage]`}
                                                value={stage}
                                            />,
                                            <input
                                                key={`${index}-tier`}
                                                type="hidden"
                                                name={`escalation_rules[${index}][tier]`}
                                                value={
                                                    priority === 'critical'
                                                        ? 3
                                                        : stage === 'resolution'
                                                          ? 3
                                                          : 2
                                                }
                                            />,
                                        ];
                                    },
                                ),
                        )}
                        <section aria-labelledby="roster-heading">
                            <h3 id="roster-heading" className="font-semibold">
                                {copy.national_duty_roster}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {copy.national_duty_roster_description}
                            </p>
                            <div className="mt-4 grid gap-5 sm:grid-cols-2">
                                <SearchableSelect
                                    id="policy-tier-one"
                                    name="roster[0][user_id]"
                                    label="Tier 1 primary responder"
                                    options={options.resolvers}
                                    error={errors['roster.0.user_id']}
                                />
                                <SearchableSelect
                                    id="policy-tier-three"
                                    name="roster[1][user_id]"
                                    label="Tier 3 escalation manager"
                                    options={options.resolvers}
                                    error={errors['roster.1.user_id']}
                                />
                            </div>
                            {[1, 3].map((tier, index) => (
                                <span key={tier}>
                                    <input
                                        type="hidden"
                                        name={`roster[${index}][county_id]`}
                                        value=""
                                    />
                                    <input
                                        type="hidden"
                                        name={`roster[${index}][tier]`}
                                        value={tier}
                                    />
                                    <input
                                        type="hidden"
                                        name={`roster[${index}][duty_role]`}
                                        value={
                                            tier === 1 ? 'responder' : 'manager'
                                        }
                                    />
                                    <input
                                        type="hidden"
                                        name={`roster[${index}][is_primary]`}
                                        value="1"
                                    />
                                    <input
                                        type="hidden"
                                        name={`roster[${index}][starts_at]`}
                                        value={effectiveFrom}
                                    />
                                    <input
                                        type="hidden"
                                        name={`roster[${index}][ends_at]`}
                                        value=""
                                    />
                                </span>
                            ))}
                        </section>
                        <Button
                            type="submit"
                            disabled={processing || !effectiveFrom}
                        >
                            {processing ? 'Saving…' : 'Create policy draft'}
                        </Button>
                    </FieldGroup>
                )}
            </Form>
        </FormSheet>
    );
}

function PublishServicePolicySheet({ policy }: { policy: ServicePolicy }) {
    const [authorityStatus, setAuthorityStatus] = useState('provisional');

    return (
        <FormSheet
            title={`Publish ${policy.code} v${policy.version}`}
            description="Verify the calendar, targets, roster and escalation matrix. Publication is immutable and requires an actor independent of the author."
            triggerLabel="Review publication"
            icon={ClipboardCheck}
        >
            <Form {...publishPolicy.form({ serviceDeskPolicy: policy.id })}>
                {({ errors, processing }) => (
                    <FieldGroup>
                        <SearchableSelect
                            id={`policy-authority-${policy.id}`}
                            name="authority_status"
                            label="Authority status"
                            options={[
                                {
                                    id: 'provisional',
                                    name: 'Provisional engineering policy',
                                },
                                {
                                    id: 'approved',
                                    name: 'Accountable owner approved',
                                },
                            ]}
                            value={authorityStatus}
                            onValueChange={setAuthorityStatus}
                            error={errors.authority_status}
                        />
                        {authorityStatus === 'approved' && (
                            <PolicyInput
                                id={`policy-approval-${policy.id}`}
                                name="approval_reference"
                                label="Approval reference"
                                error={errors.approval_reference}
                            />
                        )}
                        {authorityStatus === 'provisional' && (
                            <input
                                type="hidden"
                                name="approval_reference"
                                value=""
                            />
                        )}
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Publishing…'
                                : 'Publish immutable policy'}
                        </Button>
                    </FieldGroup>
                )}
            </Form>
        </FormSheet>
    );
}

function PolicyInput({
    id,
    name,
    label,
    defaultValue,
    error,
}: {
    id: string;
    name: string;
    label: string;
    defaultValue?: string;
    error?: string;
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Input
                id={id}
                name={name}
                required
                defaultValue={defaultValue}
                aria-invalid={Boolean(error)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function PolicyNumberInput({
    name,
    label,
    defaultValue,
}: {
    name: string;
    label: string;
    defaultValue: number;
}) {
    const id = name.replaceAll(/[^a-zA-Z0-9_-]/g, '-');

    return (
        <Field>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Input
                id={id}
                name={name}
                type="number"
                min="0.25"
                max="1000"
                step="0.25"
                required
                defaultValue={defaultValue}
            />
        </Field>
    );
}

function CreateTicketSheet({
    counties,
    national,
    intakeAvailable,
}: {
    counties: CountyIdentityValue[];
    national: boolean;
    intakeAvailable: boolean;
}) {
    const copy = usePage().props.localization.supportDesk;

    return (
        <FormSheet
            title="Submit support request"
            description="Create a governed service request. Personally sensitive narrative is encrypted at rest."
            triggerLabel="New support ticket"
            icon={Plus}
            triggerDisabled={!intakeAvailable}
            triggerTitle={
                intakeAvailable
                    ? undefined
                    : 'A checksum-valid reference catalogue and effective service-desk policy are required.'
            }
        >
            <Form {...store.form()} resetOnSuccess>
                {({ errors, processing }) => (
                    <FieldGroup>
                        {national && (
                            <SearchableSelect
                                id="support-county"
                                name="county_id"
                                label="County"
                                options={counties}
                                optional
                                error={errors.county_id}
                            />
                        )}
                        {!national && counties.length === 1 && (
                            <input
                                type="hidden"
                                name="county_id"
                                value={counties[0].id}
                            />
                        )}
                        <div className="grid gap-5 sm:grid-cols-2">
                            <SearchableSelect
                                id="support-category"
                                name="category"
                                label="Category"
                                options={[
                                    {
                                        id: 'access',
                                        name: 'Access and identity',
                                    },
                                    {
                                        id: 'incident',
                                        name: 'Service incident',
                                    },
                                    {
                                        id: 'service_request',
                                        name: 'Service request',
                                    },
                                    {
                                        id: 'data_quality',
                                        name: 'Data quality',
                                    },
                                    { id: 'integration', name: 'Integration' },
                                    {
                                        id: 'training',
                                        name: 'Training and adoption',
                                    },
                                    {
                                        id: 'document',
                                        name: 'Documents and OCR',
                                    },
                                    { id: 'other', name: 'Other' },
                                ]}
                                error={errors.category}
                            />
                            <SearchableSelect
                                id="support-priority"
                                name="priority"
                                label="Priority"
                                options={[
                                    { id: 'low', name: 'Low' },
                                    { id: 'medium', name: 'Medium' },
                                    { id: 'high', name: 'High' },
                                    { id: 'critical', name: 'Critical' },
                                ]}
                                defaultValue="medium"
                                error={errors.priority}
                            />
                        </div>
                        <input type="hidden" name="channel" value="web" />
                        <Field data-invalid={Boolean(errors.subject)}>
                            <FieldLabel htmlFor="support-subject">
                                {copy.subject}
                            </FieldLabel>
                            <Input
                                id="support-subject"
                                name="subject"
                                required
                                maxLength={255}
                                aria-invalid={Boolean(errors.subject)}
                                aria-describedby={
                                    errors.subject
                                        ? 'support-subject-error'
                                        : undefined
                                }
                            />
                            <FieldError id="support-subject-error">
                                {errors.subject}
                            </FieldError>
                        </Field>
                        <Field data-invalid={Boolean(errors.description)}>
                            <FieldLabel htmlFor="support-description">
                                {copy.description_label}
                            </FieldLabel>
                            <Textarea
                                id="support-description"
                                name="description"
                                required
                                rows={7}
                                maxLength={10000}
                                aria-invalid={Boolean(errors.description)}
                                aria-describedby={
                                    errors.description
                                        ? 'support-description-error'
                                        : undefined
                                }
                            />
                            <FieldError id="support-description-error">
                                {errors.description}
                            </FieldError>
                        </Field>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Submitting…' : 'Submit request'}
                        </Button>
                    </FieldGroup>
                )}
            </Form>
        </FormSheet>
    );
}

function TicketMenu({
    detail,
    onView,
}: {
    detail: TicketDetail | undefined;
    onView: () => void;
}) {
    const copy = usePage().props.localization.supportDesk;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={copy.ticket_actions}
                >
                    <MoreHorizontal aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    <DropdownMenuLabel>{copy.ticket_actions}</DropdownMenuLabel>
                    <DropdownMenuItem onSelect={onView} disabled={!detail}>
                        <Eye aria-hidden="true" />
                        {copy.open_complete_record}
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function TicketSheet({
    ticket,
    open,
    onOpenChange,
    assignees,
    capabilities,
}: {
    ticket: TicketDetail | undefined;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    assignees: SearchableSelectOption[];
    capabilities: Props['capabilities'];
}) {
    const copy = usePage().props.localization.supportDesk;

    if (!ticket) {
        return null;
    }

    const isRequester = ticket.requester.id === capabilities.userId;
    const isAssignee = ticket.assignee?.id === capabilities.userId;
    const canUpload =
        ticket.status !== 'closed' &&
        (isRequester || isAssignee || capabilities.manage);
    const transitionOptions = availableTransitions(
        ticket,
        isRequester,
        isAssignee && capabilities.resolve,
    );

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-3xl">
                <SheetHeader>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{ticket.reference}</Badge>
                        <Badge variant="secondary">
                            {humanize(ticket.status)}
                        </Badge>
                        <Badge variant="outline">
                            {humanize(ticket.priority)} {copy.priority}
                        </Badge>
                    </div>
                    <SheetTitle>{ticket.subject}</SheetTitle>
                    <SheetDescription>
                        {copy.requested}{' '}
                        {formatDateTime(ticket.requestedAt)} {copy.by}{' '}
                        {ticket.requester.name}
                        {copy.full_stop}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex flex-col gap-6 px-4 pb-8">
                    {ticket.county && <CountyIdentity county={ticket.county} />}
                    <Card>
                        <CardHeader>
                            <CardTitle>{copy.request_narrative}</CardTitle>
                            <CardDescription>
                                {humanize(ticket.category)} {copy.separator}{' '}
                                {humanize(ticket.channel)} {copy.channel}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm leading-6 whitespace-pre-wrap">
                                {ticket.description}
                            </p>
                        </CardContent>
                    </Card>

                    <SlaCard ticket={ticket} />

                    {capabilities.manage &&
                        ['open', 'triaged'].includes(ticket.status) && (
                            <ActionCard
                                title="Triage and assignment"
                                description="Assign an authorized resolver who is separate from the requester."
                            >
                                <Form
                                    {...assign.form({
                                        supportTicket: ticket.id,
                                    })}
                                >
                                    {({ errors, processing }) => (
                                        <FieldGroup>
                                            <SearchableSelect
                                                id={`support-assignee-${ticket.id}`}
                                                name="assigned_to"
                                                label="Support resolver"
                                                options={assignees.filter(
                                                    (option) =>
                                                        option.id !==
                                                        ticket.requester.id,
                                                )}
                                                error={errors.assigned_to}
                                            />
                                            <NarrativeField
                                                id={`assignment-${ticket.id}`}
                                                error={errors.narrative}
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {copy.assign_resolver}
                                            </Button>
                                        </FieldGroup>
                                    )}
                                </Form>
                            </ActionCard>
                        )}

                    {transitionOptions.length > 0 && (
                        <ActionCard
                            title="Workflow action"
                            description="All status changes are scope checked and appended to the immutable activity ledger."
                        >
                            <TransitionForm
                                ticket={ticket}
                                options={transitionOptions}
                            />
                        </ActionCard>
                    )}

                    {canUpload && (
                        <ActionCard
                            title="Upload support record"
                            description="Scanned and born-digital records are privately stored, malware scanned, checksummed and sent for OCR when supported."
                        >
                            <DocumentUploadForm ticket={ticket} />
                        </ActionCard>
                    )}

                    <section aria-labelledby="support-documents-heading">
                        <h2
                            id="support-documents-heading"
                            className="text-lg font-semibold"
                        >
                            {copy.governed_documents}
                        </h2>
                        <div className="mt-3 flex flex-col gap-3">
                            {ticket.documents.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-5 text-sm text-muted-foreground">
                                    {copy.no_support_records}
                                </p>
                            ) : (
                                ticket.documents.map((document) => (
                                    <div
                                        key={document.id}
                                        className="flex flex-col justify-between gap-3 rounded-lg border p-4 sm:flex-row sm:items-center"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {document.title}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {document.originalName ??
                                                    'Stored record'}{' '}
                                                {copy.separator}{' '}
                                                {formatBytes(
                                                    document.sizeBytes,
                                                )}
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                <Badge variant="outline">
                                                    {copy.scan_label}{' '}
                                                    {humanize(
                                                        document.scanStatus,
                                                    )}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {copy.ocr_label}{' '}
                                                    {humanize(
                                                        document.ocrStatus,
                                                    )}
                                                </Badge>
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={preview.url({
                                                        document: document.id,
                                                    })}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Eye aria-hidden="true" />
                                                    {copy.preview}
                                                </a>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={download.url({
                                                        document: document.id,
                                                    })}
                                                >
                                                    <Download aria-hidden="true" />
                                                    {copy.download}
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <Separator />
                    <section aria-labelledby="support-history-heading">
                        <h2
                            id="support-history-heading"
                            className="text-lg font-semibold"
                        >
                            {copy.immutable_activity_history}
                        </h2>
                        <ol className="mt-4 flex flex-col gap-4">
                            {[...ticket.activities]
                                .reverse()
                                .map((activity) => (
                                    <li
                                        key={activity.id}
                                        className="border-l-2 border-primary/30 pl-4"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">
                                                {humanize(activity.type)}
                                            </p>
                                            <Badge variant="outline">
                                                {humanize(activity.toStatus)}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm">
                                            {activity.narrative}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {activity.actor} {copy.separator}{' '}
                                            {formatDateTime(
                                                activity.occurredAt,
                                            )}{' '}
                                            {copy.separator}{' '}
                                            {copy.checksum}{' '}
                                            {activity.checksum.slice(0, 12)}
                                            {copy.ellipsis}
                                        </p>
                                    </li>
                                ))}
                        </ol>
                    </section>

                    <p className="text-xs text-muted-foreground">
                        {copy.reference_catalogue_version_prefix}
                        {ticket.referenceData.version} {copy.separator}{' '}
                        {copy.checksum}{' '}
                        {ticket.referenceData.checksum.slice(0, 16)}
                        {copy.ellipsis}
                    </p>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function SlaCard({ ticket }: { ticket: TicketDetail }) {
    const copy = usePage().props.localization.supportDesk;
    const [observedAt] = useState(() => Date.now());
    const requested = new Date(ticket.requestedAt).getTime();
    const due = new Date(ticket.resolutionDueAt).getTime();
    const end = ticket.resolvedAt
        ? new Date(ticket.resolvedAt).getTime()
        : observedAt;
    const progress = Math.max(
        0,
        Math.min(100, ((end - requested) / (due - requested)) * 100),
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>{copy.service_level_targets}</CardTitle>
                <CardDescription>
                    {ticket.servicePolicy
                        ? `${humanize(ticket.servicePolicy.authorityStatus)} ${ticket.servicePolicy.code} v${ticket.servicePolicy.version} · ${ticket.servicePolicy.calendar.code} v${ticket.servicePolicy.calendar.version}`
                        : 'Legacy config-derived targets without governed policy lineage.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <Progress
                    value={progress}
                    aria-label="Resolution SLA elapsed"
                />
                <div className="grid gap-3 text-sm sm:grid-cols-2">
                    <KeyValue
                        label="First response due"
                        value={formatDateTime(ticket.firstResponseDueAt)}
                    />
                    <KeyValue
                        label="First response"
                        value={
                            ticket.firstRespondedAt
                                ? formatDateTime(ticket.firstRespondedAt)
                                : 'Pending'
                        }
                    />
                    <KeyValue
                        label="Resolution due"
                        value={formatDateTime(ticket.resolutionDueAt)}
                    />
                    <KeyValue
                        label="Resolution"
                        value={
                            ticket.resolvedAt
                                ? formatDateTime(ticket.resolvedAt)
                                : 'Pending'
                        }
                    />
                </div>
                {ticket.servicePolicy && (
                    <p className="text-xs text-muted-foreground">
                        {copy.policy_checksum}{' '}
                        {ticket.servicePolicy.checksum.slice(0, 16)}
                        {copy.ellipsis} {copy.separator}{' '}
                        {copy.calendar_checksum}{' '}
                        {ticket.servicePolicy.calendar.checksum.slice(0, 16)}
                        {copy.ellipsis}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function TransitionForm({
    ticket,
    options,
}: {
    ticket: TicketDetail;
    options: SearchableSelectOption[];
}) {
    const copy = usePage().props.localization.supportDesk;
    const [selectedTransition, setSelectedTransition] = useState(
        options[0]?.id ?? '',
    );

    return (
        <Form {...transition.form({ supportTicket: ticket.id })}>
            {({ errors, processing }) => (
                <FieldGroup>
                    <SearchableSelect
                        id={`transition-${ticket.id}`}
                        name="transition"
                        label="Transition"
                        options={options}
                        value={selectedTransition}
                        onValueChange={setSelectedTransition}
                        error={errors.transition}
                    />
                    <NarrativeField
                        id={`transition-narrative-${ticket.id}`}
                        error={errors.narrative}
                    />
                    {selectedTransition === 'resolve' && (
                        <Field
                            data-invalid={Boolean(errors.resolution_summary)}
                        >
                            <FieldLabel
                                htmlFor={`resolution-summary-${ticket.id}`}
                            >
                                {copy.resolution_summary}
                            </FieldLabel>
                            <Textarea
                                id={`resolution-summary-${ticket.id}`}
                                name="resolution_summary"
                                required
                                rows={5}
                                aria-invalid={Boolean(
                                    errors.resolution_summary,
                                )}
                            />
                            <FieldError>{errors.resolution_summary}</FieldError>
                        </Field>
                    )}
                    <Button type="submit" disabled={processing}>
                        {copy.record_workflow_action}
                    </Button>
                </FieldGroup>
            )}
        </Form>
    );
}

function DocumentUploadForm({ ticket }: { ticket: TicketDetail }) {
    const copy = usePage().props.localization.supportDesk;

    return (
        <Form
            {...storeSupportTicket.form({ supportTicket: ticket.id })}
            resetOnSuccess
        >
            {({ errors, processing, progress }) => (
                <FieldGroup>
                    <Field data-invalid={Boolean(errors.title)}>
                        <FieldLabel htmlFor={`document-title-${ticket.id}`}>
                            {copy.record_title}
                        </FieldLabel>
                        <Input
                            id={`document-title-${ticket.id}`}
                            name="title"
                            required
                            aria-invalid={Boolean(errors.title)}
                        />
                        <FieldError>{errors.title}</FieldError>
                    </Field>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <SearchableSelect
                            id={`document-purpose-${ticket.id}`}
                            name="record_purpose"
                            label="Record purpose"
                            options={[
                                { id: 'request', name: 'Request evidence' },
                                {
                                    id: 'investigation',
                                    name: 'Investigation evidence',
                                },
                                {
                                    id: 'resolution',
                                    name: 'Resolution evidence',
                                },
                            ]}
                            error={errors.record_purpose}
                        />
                        <SearchableSelect
                            id={`document-source-${ticket.id}`}
                            name="source_type"
                            label="Document source"
                            options={[
                                { id: 'scanned', name: 'Scanned original' },
                                { id: 'digital', name: 'Born digital' },
                            ]}
                            error={errors.source_type}
                        />
                    </div>
                    <Field data-invalid={Boolean(errors.category)}>
                        <FieldLabel htmlFor={`document-category-${ticket.id}`}>
                            {copy.records_category}
                        </FieldLabel>
                        <Input
                            id={`document-category-${ticket.id}`}
                            name="category"
                            required
                            defaultValue="service-desk-record"
                            aria-invalid={Boolean(errors.category)}
                        />
                        <FieldError>{errors.category}</FieldError>
                    </Field>
                    <Field data-invalid={Boolean(errors.document)}>
                        <FieldLabel htmlFor={`document-file-${ticket.id}`}>
                            {copy.file}
                        </FieldLabel>
                        <Input
                            id={`document-file-${ticket.id}`}
                            name="document"
                            type="file"
                            required
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            aria-invalid={Boolean(errors.document)}
                        />
                        <FieldError>{errors.document}</FieldError>
                    </Field>
                    {progress && (
                        <Progress
                            value={progress.percentage}
                            aria-label="Document upload progress"
                        />
                    )}
                    <Button type="submit" disabled={processing}>
                        <FileUp aria-hidden="true" />
                        {copy.upload_governed_record}
                    </Button>
                </FieldGroup>
            )}
        </Form>
    );
}

function NarrativeField({ id, error }: { id: string; error?: string }) {
    const copy = usePage().props.localization.supportDesk;

    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>{copy.action_narrative}</FieldLabel>
            <Textarea
                id={id}
                name="narrative"
                required
                rows={4}
                maxLength={5000}
                aria-invalid={Boolean(error)}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function ActionCard({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function Metric({
    title,
    value,
    description,
    icon: Icon,
}: {
    title: string;
    value: number;
    description: string;
    icon: typeof Headphones;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardDescription>{title}</CardDescription>
                    <CardTitle className="mt-2 text-3xl">
                        {value.toLocaleString()}
                    </CardTitle>
                </div>
                <Icon className="text-muted-foreground" aria-hidden="true" />
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground">
                {description}
            </CardContent>
        </Card>
    );
}

function KeyValue({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg bg-muted/50 p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium">{value}</p>
        </div>
    );
}

function availableTransitions(
    ticket: TicketDetail,
    requester: boolean,
    resolver: boolean,
): SearchableSelectOption[] {
    const available: Record<string, SearchableSelectOption> = {
        start: { id: 'start', name: 'Start investigation' },
        request_information: {
            id: 'request_information',
            name: 'Request more information',
        },
        provide_information: {
            id: 'provide_information',
            name: 'Provide requested information',
        },
        resolve: { id: 'resolve', name: 'Submit resolution' },
        close: { id: 'close', name: 'Accept and close' },
        reopen: { id: 'reopen', name: 'Reject resolution and reopen' },
    };
    const keys = resolver
        ? ({
              triaged: ['start'],
              in_progress: ['request_information', 'resolve'],
          }[ticket.status] ?? [])
        : requester
          ? ({
                awaiting_requester: ['provide_information'],
                resolved: ['close', 'reopen'],
            }[ticket.status] ?? [])
          : [];

    return keys.map((key) => available[key]);
}

function humanize(value: string): string {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}

function formatDateTime(value: string): string {
    return formatCatalogDateTime(value, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: DEFAULT_TIMEZONE,
    });
}

function formatBytes(value: number): string {
    return formatNumber(
        value >= 1_000_000 ? value / 1_000_000 : value / 1_000,
        {
            style: 'unit',
            unit: value >= 1_000_000 ? 'megabyte' : 'kilobyte',
            unitDisplay: 'short',
            maximumFractionDigits: 1,
        },
    );
}
