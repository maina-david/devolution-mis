import { Form, Head, usePage } from '@inertiajs/react';
import {
    Download,
    Eye,
    MessageSquareReply,
    ShieldAlert,
    UsersRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import {
    message,
    transition,
    triage,
} from '@/actions/App/Http/Controllers/CitizenCaseController';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DateRangeFilter from '@/components/date-range-filter';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { CitizenCaseBulkTriageActions } from '@/components/workspace-bulk-actions';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import { index } from '@/routes/citizen-cases';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string };
type Case = {
    id: string;
    reference: string;
    type: string;
    category: string;
    channel: string;
    county: CountyIdentityValue;
    sector: string | null;
    subject: string;
    description: string;
    citizenName: string | null;
    preferredContact: string;
    accessibilityNeeds: string | null;
    priority: string;
    status: string;
    sensitive: boolean;
    assignee: string | null;
    assignedTo: string | null;
    intakeReferenceData: ReferenceDataLineage | null;
    triageReferenceData: ReferenceDataLineage | null;
    firstResponseDueAt: string;
    resolutionDueAt: string;
    resolutionSummary: string | null;
    messages: Array<{
        id: string;
        direction: string;
        visibility: string;
        body: string;
        postedAt: string;
    }>;
    attachments: Array<{
        id: string;
        title: string;
        originalName: string;
        sourceType: string;
        scanStatus: string;
        ocrStatus: string;
    }>;
};
type ReferenceDataLineage = {
    version: number;
    effectiveFrom: string | null;
    checksum: string;
};
type SatisfactionAnalytics = {
    responses: number | null;
    responseRate: number | null;
    resolutionTimeCorrelation: {
        samples: number | null;
        coefficient: number | null;
    };
};
type Props = {
    workspace: {
        title: string;
        description: string;
        columns: string[];
        rows: WorkspaceRow[];
        pagination: WorkspacePagination;
    };
    filters: { from?: string; to?: string; search?: string };
    capabilities: { manage: boolean; respond: boolean; resolve: boolean };
    summary: {
        total: number;
        open: number;
        overdue: number;
        grievances: number;
        satisfaction: string | null;
    };
    analytics: { satisfaction: SatisfactionAnalytics };
    cases: Case[];
    options: { users: Option[]; organizations: Option[]; sectors: Option[] };
};

export default function CitizenCasesIndex({
    workspace,
    filters,
    capabilities,
    summary,
    analytics,
    cases,
    options,
}: Props) {
    const lookup = new Map(cases.map((item) => [item.id, item]));
    const copy = usePage().props.localization.citizen;

    return (
        <>
            <Head title="Citizen cases" />
            <div className="flex max-w-full min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section>
                    <p className="text-sm font-semibold tracking-[0.14em] text-primary uppercase">
                        Citizen accountability operations
                    </p>
                    <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-bold">
                                Feedback and grievance casework
                            </h1>
                            <p className="mt-2 max-w-3xl text-muted-foreground">
                                Triage, route, respond, escalate and
                                independently resolve privacy-controlled citizen
                                cases within SLA.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {['csv', 'xlsx', 'pdf', 'json'].map((format) => (
                                <Button
                                    key={format}
                                    asChild
                                    size="sm"
                                    variant="outline"
                                >
                                    <a
                                        href={exportMethod.url(
                                            {
                                                workspace: 'citizen-cases',
                                                format,
                                            },
                                            { query: filters },
                                        )}
                                    >
                                        <Download aria-hidden="true" />
                                        {format.toUpperCase()}
                                    </a>
                                </Button>
                            ))}
                        </div>
                    </div>
                </section>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Metric label="Cases" value={summary.total} />
                    <Metric label="Open" value={summary.open} />
                    <Metric label="Overdue" value={summary.overdue} />
                    <Metric label="Grievances" value={summary.grievances} />
                    <Metric
                        label="Satisfaction"
                        value={
                            summary.satisfaction
                                ? `${summary.satisfaction} / 5`
                                : '—'
                        }
                    />
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>{copy.satisfaction_insights}</CardTitle>
                        <CardDescription>
                            {copy.satisfaction_insights_description}
                        </CardDescription>
                    </CardHeader>
                    <div className="grid gap-4 px-6 pb-6 md:grid-cols-3">
                        <Metric
                            label={copy.rating_responses}
                            value={analytics.satisfaction.responses ?? '—'}
                        />
                        <Metric
                            label={copy.rating_response_rate}
                            value={
                                analytics.satisfaction.responseRate === null
                                    ? '—'
                                    : `${analytics.satisfaction.responseRate}%`
                            }
                        />
                        <Metric
                            label={copy.resolution_rating_correlation}
                            value={
                                analytics.satisfaction
                                    .resolutionTimeCorrelation.coefficient ??
                                '—'
                            }
                        />
                    </div>
                </Card>
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                />
                <Card className="min-w-0 overflow-hidden">
                    <CardHeader>
                        <CardTitle>{workspace.title}</CardTitle>
                        <CardDescription>
                            {workspace.description}
                        </CardDescription>
                    </CardHeader>
                    <WorkspaceDataTable
                        columns={workspace.columns}
                        rows={workspace.rows}
                        pagination={workspace.pagination}
                        bulkExport={{ workspace: 'citizen-cases', filters }}
                        allowFilteredBulkSelection={capabilities.manage}
                        renderBulkActions={(
                            selectedRows,
                            clearSelection,
                            selection,
                        ) =>
                            capabilities.manage &&
                            selectedRows.every(
                                (row) => row.status === 'received',
                            ) ? (
                                <CitizenCaseBulkTriageActions
                                    rows={selectedRows}
                                    users={options.users}
                                    organizations={options.organizations}
                                    sectors={options.sectors}
                                    filters={filters}
                                    selection={selection}
                                    clearSelection={clearSelection}
                                />
                            ) : null
                        }
                        renderActions={(row) => {
                            const item = lookup.get(row.id);

                            return item ? (
                                <CaseSheet
                                    citizenCase={item}
                                    capabilities={capabilities}
                                    options={options}
                                />
                            ) : null;
                        }}
                    />
                </Card>
            </div>
        </>
    );
}

CitizenCasesIndex.layout = () => ({
    breadcrumbs: [
        {
            title: 'Citizen cases',
            href: index(),
        },
    ],
});

function Metric({ label, value }: { label: string; value: number | string }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

function CaseSheet({
    citizenCase,
    capabilities,
    options,
}: {
    citizenCase: Case;
    capabilities: Props['capabilities'];
    options: Props['options'];
}) {
    const canHandle =
        capabilities.manage || capabilities.resolve || capabilities.respond;

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    aria-label={`Open ${citizenCase.reference}`}
                >
                    <Eye aria-hidden="true" />
                    Open
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>
                        {citizenCase.reference} · {citizenCase.subject}
                    </SheetTitle>
                    <SheetDescription>
                        {citizenCase.type} · {citizenCase.category} ·{' '}
                        {citizenCase.county.name} County
                    </SheetDescription>
                </SheetHeader>
                <div className="flex flex-col gap-5 px-4 pb-8">
                    <CountyIdentity county={citizenCase.county} />
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="outline">
                            {citizenCase.status.replaceAll('_', ' ')}
                        </Badge>
                        <Badge variant="secondary">
                            {citizenCase.priority}
                        </Badge>
                        <Badge variant="secondary">{citizenCase.channel}</Badge>
                        {citizenCase.sensitive && (
                            <Badge variant="destructive">Sensitive</Badge>
                        )}
                    </div>
                    <dl className="grid gap-4 text-sm sm:grid-cols-2">
                        <Info
                            label="Citizen"
                            value={citizenCase.citizenName ?? 'Withheld'}
                        />
                        <Info
                            label="Assignee"
                            value={citizenCase.assignee ?? 'Unassigned'}
                        />
                        <Info
                            label="First response due"
                            value={new Date(
                                citizenCase.firstResponseDueAt,
                            ).toLocaleString()}
                        />
                        <Info
                            label="Resolution due"
                            value={new Date(
                                citizenCase.resolutionDueAt,
                            ).toLocaleString()}
                        />
                        <Info
                            label="Intake catalogue"
                            value={formatReferenceDataLineage(
                                citizenCase.intakeReferenceData,
                                'Legacy unpinned',
                            )}
                        />
                        <Info
                            label="Triage catalogue"
                            value={formatReferenceDataLineage(
                                citizenCase.triageReferenceData,
                                'Not yet triaged / legacy',
                            )}
                        />
                    </dl>
                    <div>
                        <h3 className="font-medium">Submission</h3>
                        <p className="mt-2 text-sm whitespace-pre-wrap text-muted-foreground">
                            {citizenCase.description}
                        </p>
                    </div>
                    {citizenCase.accessibilityNeeds && (
                        <div className="rounded-lg bg-muted p-3 text-sm">
                            <strong>Accessibility support:</strong>{' '}
                            {citizenCase.accessibilityNeeds}
                        </div>
                    )}
                    {capabilities.manage &&
                        citizenCase.status === 'received' && (
                            <TriageSheetForm
                                citizenCase={citizenCase}
                                options={options}
                            />
                        )}
                    {canHandle &&
                        citizenCase.status !== 'received' &&
                        !['resolved', 'closed'].includes(
                            citizenCase.status,
                        ) && (
                            <MessageSheetForm
                                citizenCase={citizenCase}
                                capabilities={capabilities}
                            />
                        )}
                    {canHandle && (
                        <WorkflowActions
                            citizenCase={citizenCase}
                            capabilities={capabilities}
                        />
                    )}
                    <div>
                        <h3 className="font-medium">Case history</h3>
                        <ol className="mt-3 flex flex-col gap-3">
                            {citizenCase.messages.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="rounded-lg border p-3 text-sm"
                                >
                                    <div className="flex justify-between gap-3 text-xs text-muted-foreground">
                                        <span>
                                            {entry.visibility} ·{' '}
                                            {entry.direction}
                                        </span>
                                        <time dateTime={entry.postedAt}>
                                            {new Date(
                                                entry.postedAt,
                                            ).toLocaleString()}
                                        </time>
                                    </div>
                                    <p className="mt-2">{entry.body}</p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function formatReferenceDataLineage(
    lineage: ReferenceDataLineage | null,
    fallback: string,
) {
    return lineage
        ? 'v' + lineage.version + ' · ' + lineage.checksum
        : fallback;
}

function TriageSheetForm({
    citizenCase,
    options,
}: {
    citizenCase: Case;
    options: Props['options'];
}) {
    return (
        <ActionSheet
            trigger="Triage and assign"
            title="Triage citizen case"
            icon={<UsersRound aria-hidden="true" />}
        >
            <Form
                action={triage({ case: citizenCase.id })}
                className="flex flex-col gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <SelectField
                            id="assigned_to"
                            name="assigned_to"
                            label="Responsible officer"
                            options={options.users}
                            error={errors.assigned_to}
                        />
                        <SelectField
                            id="assigned_organization_id"
                            name="assigned_organization_id"
                            label="Responsible organization"
                            options={options.organizations}
                            optional
                            error={errors.assigned_organization_id}
                        />
                        <SelectField
                            id="sector_id"
                            name="sector_id"
                            label="Sector"
                            options={options.sectors}
                            optional
                            error={errors.sector_id}
                        />
                        <SelectField
                            id="priority"
                            name="priority"
                            label="Priority"
                            options={[
                                { id: 'low', name: 'Low' },
                                { id: 'medium', name: 'Medium' },
                                { id: 'high', name: 'High' },
                                { id: 'critical', name: 'Critical' },
                            ]}
                            error={errors.priority}
                        />
                        <SelectField
                            id="is_sensitive"
                            name="is_sensitive"
                            label="Sensitivity"
                            options={[
                                { id: '0', name: 'Standard access' },
                                { id: '1', name: 'Restricted case' },
                            ]}
                            error={errors.is_sensitive}
                        />
                        <TextField
                            id="triage_note"
                            name="triage_note"
                            label="Triage rationale"
                            error={errors.triage_note}
                        />
                        <Button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing}
                        >
                            Assign case
                        </Button>
                    </>
                )}
            </Form>
        </ActionSheet>
    );
}

function MessageSheetForm({
    citizenCase,
    capabilities,
}: {
    citizenCase: Case;
    capabilities: Props['capabilities'];
}) {
    return (
        <ActionSheet
            trigger="Add response or note"
            title="Record case communication"
            icon={<MessageSquareReply aria-hidden="true" />}
        >
            <Form
                action={message({ case: citizenCase.id })}
                className="flex flex-col gap-4"
            >
                {({ errors, processing, progress }) => (
                    <>
                        <TextField
                            id="body"
                            name="body"
                            label="Message"
                            error={errors.body}
                        />
                        <SelectField
                            id="visibility"
                            name="visibility"
                            label="Visibility"
                            options={
                                capabilities.manage
                                    ? [
                                          {
                                              id: 'public',
                                              name: 'Public response',
                                          },
                                          {
                                              id: 'internal',
                                              name: 'Internal note',
                                          },
                                      ]
                                    : [
                                          {
                                              id: 'public',
                                              name: 'Public response',
                                          },
                                      ]
                            }
                            error={errors.visibility}
                        />
                        <SelectField
                            id="source_type"
                            name="source_type"
                            label="Attachment source"
                            options={[
                                { id: 'born_digital', name: 'Born-digital' },
                                { id: 'scanned', name: 'Scanned paper' },
                            ]}
                            optional
                            error={errors.source_type}
                        />
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="case-attachment">
                                Attachment (optional)
                            </Label>
                            <Input
                                id="case-attachment"
                                name="attachment"
                                type="file"
                                aria-invalid={Boolean(errors.attachment)}
                            />
                        </div>
                        {progress && (
                            <progress
                                value={progress.percentage}
                                max="100"
                                className="w-full"
                                aria-label="Upload progress"
                            />
                        )}
                        <Button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing}
                        >
                            Record communication
                        </Button>
                    </>
                )}
            </Form>
        </ActionSheet>
    );
}

function WorkflowActions({
    citizenCase,
    capabilities,
}: {
    citizenCase: Case;
    capabilities: Props['capabilities'];
}) {
    const actions: Array<{ name: string; label: string; summary?: boolean }> =
        [];

    if (citizenCase.status === 'triaged' && capabilities.respond) {
        actions.push({ name: 'start', label: 'Start investigation' });
    }

    if (citizenCase.status === 'in_progress' && capabilities.respond) {
        actions.push(
            { name: 'escalate', label: 'Escalate' },
            {
                name: 'submit_resolution',
                label: 'Submit resolution',
                summary: true,
            },
        );
    }

    if (citizenCase.status === 'escalated' && capabilities.manage) {
        actions.push({ name: 'resume', label: 'Resume handling' });
    }

    if (citizenCase.status === 'resolution_review' && capabilities.resolve) {
        actions.push(
            { name: 'approve_resolution', label: 'Approve resolution' },
            { name: 'reject_resolution', label: 'Return for action' },
        );
    }

    if (citizenCase.status === 'resolved' && capabilities.manage) {
        actions.push({ name: 'close', label: 'Close case' });
    }

    if (!actions.length) {
        return null;
    }

    return (
        <ActionSheet
            trigger="Workflow action"
            title="Update governed case status"
            icon={<ShieldAlert aria-hidden="true" />}
        >
            <div className="flex flex-col gap-4">
                {actions.map((action) => (
                    <Form
                        key={action.name}
                        action={transition({ case: citizenCase.id })}
                        className="flex flex-col gap-3 rounded-lg border p-3"
                    >
                        {({ processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="transition"
                                    value={action.name}
                                />
                                {action.summary && (
                                    <TextField
                                        id={`summary-${action.name}`}
                                        name="resolution_summary"
                                        label="Resolution summary"
                                    />
                                )}
                                <Input
                                    name="comment"
                                    required
                                    placeholder={`${action.label} rationale`}
                                    aria-label={`${action.label} rationale`}
                                />
                                <Button
                                    type="submit"
                                    variant={
                                        action.name.includes('reject')
                                            ? 'outline'
                                            : 'default'
                                    }
                                    disabled={processing}
                                >
                                    {action.label}
                                </Button>
                            </>
                        )}
                    </Form>
                ))}
            </div>
        </ActionSheet>
    );
}

function ActionSheet({
    trigger,
    title,
    icon,
    children,
}: {
    trigger: string;
    title: string;
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline">
                    {icon}
                    {trigger}
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>
                        Changes are authorized, time-stamped and written to the
                        immutable audit trail.
                    </SheetDescription>
                </SheetHeader>
                <div className="px-4 pb-8">{children}</div>
            </SheetContent>
        </Sheet>
    );
}
function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}
function SelectField({
    id,
    name,
    label,
    options,
    optional = false,
    error,
}: {
    id: string;
    name: string;
    label: string;
    options: Option[];
    optional?: boolean;
    error?: string;
}) {
    return (
        <SearchableSelect
            id={id}
            name={name}
            label={label}
            options={options}
            optional={optional}
            error={error}
        />
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
            <Textarea
                id={id}
                name={name}
                required
                aria-invalid={Boolean(error)}
            />
            {error && (
                <p role="alert" className="text-xs text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
