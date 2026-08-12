import { Form, Head } from '@inertiajs/react';
import {
    Download,
    Landmark,
    ListChecks,
    Plus,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, XAxis } from 'recharts';
import {
    storeDependency,
    storeForum,
    storeGap,
    storeGapCategory,
    storeMeeting,
    storeResolution,
    storeUpdate,
    transition,
    transitionGap,
} from '@/actions/App/Http/Controllers/IgrResolutionController';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import {
    Field,
    Select,
    textareaClass,
} from '@/components/dswg-coordination-forms';
import type { DswgOption as Option } from '@/components/dswg-coordination-forms';
import FormSheet from '@/components/form-sheet';
import IgrDocumentControls from '@/components/igr-document-controls';
import StaticSearchableSelect from '@/components/static-searchable-select';
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
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspaceDocument,
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { exportMethod } from '@/routes/workspace';

type Assignment = {
    userId: string | null;
    countyId: string | null;
    user: string | null;
    organization: string | null;
    county: CountyIdentityValue | null;
    role: string;
    lead: boolean;
};
type ResolutionGap = {
    id: string;
    category: string;
    title: string;
    description: string;
    impact: string;
    severity: string;
    status: string;
    dueOn: string;
    overdue: boolean;
    county: CountyIdentityValue | null;
    owner: string;
    mitigationPlan: string | null;
    resolutionNote: string | null;
    resolver: string | null;
    accepter: string | null;
};
type Update = {
    id: string;
    progress: number;
    narrative: string;
    gap: string | null;
    evidence: string | null;
    reportedAt: string;
};
type Meeting = {
    id: string;
    reference: string;
    title: string;
    heldOn: string;
    venue: string;
    chair: string | null;
    quorumConfirmed: boolean;
    minutesReference: string;
};
type Dependency = {
    id: string;
    type: string;
    rationale?: string;
    resolutionId: string;
    number: string;
    title: string;
    status: string;
};
type Resolution = {
    id: string;
    number: string;
    title: string;
    text: string;
    forum: string;
    resolvedOn: string;
    dueOn: string;
    priority: string;
    status: string;
    progress: number;
    gap: string | null;
    closureEvidence: string | null;
    referenceRelease: string;
    referenceChecksum: string | null;
    meeting: Meeting | null;
    dependencies: Dependency[];
    dependents: Dependency[];
    gaps: ResolutionGap[];
    assignments: Assignment[];
    updates: Update[];
    documents: WorkspaceDocument[];
};
type Props = {
    workspace: {
        title: string;
        description: string;
        columns: string[];
        rows: WorkspaceRow[];
        pagination: WorkspacePagination;
    };
    gapWorkspace: {
        title: string;
        description: string;
        columns: string[];
        rows: WorkspaceRow[];
        pagination: WorkspacePagination;
    };
    filters: {
        from?: string;
        to?: string;
        search?: string;
        status?: string;
        countyId?: string;
        severity?: string;
        gapCategoryId?: string;
    };
    gapAnalytics: {
        summary: {
            total: number;
            open: number;
            mitigating: number;
            awaitingAcceptance: number;
            overdue: number;
            critical: number;
            affectedResolutions: number;
            activeAffectedResolutions: number;
            averageResolutionDays: number | null;
        };
        categories: Array<{ name: string; total: number }>;
        severities: Array<{ name: string; total: number }>;
        aging: Array<{ name: string; total: number }>;
        trend: Array<{
            period: string;
            label: string;
            reported: number;
            accepted: number;
        }>;
        counties: Array<{
            county: CountyIdentityValue | null;
            total: number;
            active: number;
            overdue: number;
        }>;
    };
    capabilities: { manage: boolean; update: boolean; close: boolean };
    resolutions: Resolution[];
    options: {
        forums: Option[];
        counties: Option[];
        organizations: Option[];
        users: Option[];
        meetings: Option[];
        resolutions: Option[];
        gapCategories: Option[];
    };
};

const gapTrendConfig = {
    reported: { label: 'Reported', color: 'var(--chart-3)' },
    accepted: { label: 'Accepted', color: 'var(--chart-1)' },
} satisfies ChartConfig;

export default function IgrResolutionsIndex({
    workspace,
    gapWorkspace,
    filters,
    gapAnalytics,
    capabilities,
    resolutions,
    options,
}: Props) {
    return (
        <>
            <Head title="IGR resolutions" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        Intergovernmental accountability
                    </p>
                    <h1 className="mt-3 text-3xl font-bold">
                        IGR resolutions tracking
                    </h1>
                    <p className="mt-3 max-w-3xl text-[#c7d6dd]">
                        Turn summit, council and committee resolutions into
                        uniquely identified, deadline-bound commitments with
                        named parties, implementation evidence, gap reporting,
                        reminders and independent closure.
                    </p>
                </section>
                {capabilities.manage && <GovernanceForms options={options} />}
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'status',
                            label: 'Gap status',
                            value: filters.status,
                            options: [
                                'open',
                                'mitigating',
                                'resolved',
                                'accepted',
                            ].map((value) => ({
                                id: value,
                                name: value.replaceAll('_', ' '),
                            })),
                        },
                        {
                            key: 'county_id',
                            label: 'Affected county',
                            value: filters.countyId,
                            options: options.counties,
                        },
                        {
                            key: 'severity',
                            label: 'Gap severity',
                            value: filters.severity,
                            options: ['low', 'medium', 'high', 'critical'].map(
                                (value) => ({ id: value, name: value }),
                            ),
                        },
                        {
                            key: 'gap_category_id',
                            label: 'Gap category',
                            value: filters.gapCategoryId,
                            options: options.gapCategories,
                        },
                    ]}
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Implementation-gap risk profile</CardTitle>
                        <CardDescription>
                            Filter-aware exposure across the resolutions and
                            counties this role is authorized to see.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid overflow-hidden rounded-lg border sm:grid-cols-2 lg:grid-cols-4">
                            {[
                                ['Total gaps', gapAnalytics.summary.total],
                                [
                                    'Active resolutions affected',
                                    gapAnalytics.summary
                                        .activeAffectedResolutions,
                                ],
                                ['Overdue', gapAnalytics.summary.overdue],
                                [
                                    'Critical active',
                                    gapAnalytics.summary.critical,
                                ],
                                ['Open', gapAnalytics.summary.open],
                                ['Mitigating', gapAnalytics.summary.mitigating],
                                [
                                    'Awaiting acceptance',
                                    gapAnalytics.summary.awaitingAcceptance,
                                ],
                                [
                                    'Average resolution time',
                                    gapAnalytics.summary
                                        .averageResolutionDays === null
                                        ? 'Not available'
                                        : `${gapAnalytics.summary.averageResolutionDays} days`,
                                ],
                            ].map(([label, value]) => (
                                <div
                                    key={String(label)}
                                    className="border-b p-4 last:border-b-0 sm:border-r sm:nth-[2n]:border-r-0 lg:nth-[2n]:border-r lg:nth-[4n]:border-r-0 lg:nth-[n+5]:border-b-0"
                                >
                                    <dt className="text-xs font-medium text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="mt-1 text-xl font-semibold">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>
                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Gap lifecycle trend</CardTitle>
                            <CardDescription>
                                New gaps versus independently accepted
                                resolutions over the latest six calendar months.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer
                                config={gapTrendConfig}
                                className="h-72 w-full"
                            >
                                <BarChart
                                    data={gapAnalytics.trend}
                                    accessibilityLayer
                                >
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="label"
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                    />
                                    <ChartTooltip
                                        cursor={false}
                                        content={<ChartTooltipContent />}
                                    />
                                    <Bar
                                        dataKey="reported"
                                        fill="var(--color-reported)"
                                        radius={4}
                                    />
                                    <Bar
                                        dataKey="accepted"
                                        fill="var(--color-accepted)"
                                        radius={4}
                                    />
                                </BarChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Active-gap aging</CardTitle>
                            <CardDescription>
                                Time since reporting for gaps that still need
                                independent acceptance.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {gapAnalytics.aging.map((band) => {
                                const activeTotal = Math.max(
                                    1,
                                    gapAnalytics.aging.reduce(
                                        (total, item) => total + item.total,
                                        0,
                                    ),
                                );

                                return (
                                    <div
                                        key={band.name}
                                        className="flex flex-col gap-2"
                                    >
                                        <div className="flex items-center justify-between gap-4 text-sm">
                                            <span>{band.name}</span>
                                            <span className="font-medium tabular-nums">
                                                {band.total.toLocaleString()}
                                            </span>
                                        </div>
                                        <Progress
                                            value={
                                                (band.total / activeTotal) * 100
                                            }
                                            aria-label={`${band.name}: ${band.total} active gaps`}
                                        />
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                </section>
                <Card>
                    <CardHeader>
                        <CardTitle>Risk concentration</CardTitle>
                        <CardDescription>
                            Ranked categories, severities and affected counties
                            for targeted intergovernmental intervention.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6 lg:grid-cols-3">
                        <AnalyticsRanking
                            title="Categories"
                            rows={gapAnalytics.categories}
                        />
                        <AnalyticsRanking
                            title="Severities"
                            rows={gapAnalytics.severities}
                        />
                        <div className="flex flex-col gap-3">
                            <h3 className="text-sm font-medium">
                                County bottlenecks
                            </h3>
                            {gapAnalytics.counties.length ? (
                                gapAnalytics.counties.map((row) => (
                                    <div
                                        key={row.county?.id ?? 'national'}
                                        className="flex items-center justify-between gap-3 border-b pb-3 last:border-b-0 last:pb-0"
                                    >
                                        {row.county ? (
                                            <CountyIdentity
                                                county={row.county}
                                                compact
                                            />
                                        ) : (
                                            <span>National / multi-county</span>
                                        )}
                                        <div className="text-right text-xs text-muted-foreground">
                                            <p className="font-medium text-foreground">
                                                {row.active} active
                                            </p>
                                            <p>{row.overdue} overdue</p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No county-specific gaps match the selected
                                    filters.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <div className="flex gap-3">
                            <Landmark
                                className="text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <CardTitle>
                                    Resolution implementation workspace
                                </CardTitle>
                                <CardDescription>
                                    Current commitments and their recent
                                    implementation history.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {resolutions.length === 0 ? (
                            <WorkspaceEmptyState
                                title="No resolutions available"
                                description="Record an authorized intergovernmental forum and its first resolution to begin implementation tracking."
                                className="min-h-56 border"
                            />
                        ) : (
                            resolutions.map((resolution) => (
                                <ResolutionCard
                                    key={resolution.id}
                                    resolution={resolution}
                                    capabilities={capabilities}
                                    resolutionOptions={options.resolutions}
                                    gapCategories={options.gapCategories}
                                    countyOptions={options.counties}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle>{gapWorkspace.title}</CardTitle>
                            <CardDescription>
                                {gapWorkspace.description}
                            </CardDescription>
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
                                            { workspace: 'igr-gaps', format },
                                            {
                                                query: {
                                                    from: filters.from,
                                                    to: filters.to,
                                                    search: filters.search,
                                                    status: filters.status,
                                                    county_id: filters.countyId,
                                                    severity: filters.severity,
                                                    gap_category_id:
                                                        filters.gapCategoryId,
                                                },
                                            },
                                        )}
                                    >
                                        <Download aria-hidden="true" />
                                        {format.toUpperCase()}
                                    </a>
                                </Button>
                            ))}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {gapWorkspace.rows.length ? (
                            <WorkspaceDataTable
                                columns={gapWorkspace.columns}
                                rows={gapWorkspace.rows}
                                pagination={gapWorkspace.pagination}
                                bulkExport={{ workspace: 'igr-gaps', filters }}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title="No matching implementation gaps"
                                description="No governed gaps match the selected reporting filters."
                                className="min-h-56 border-0"
                            />
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="flex gap-3">
                            <ListChecks
                                className="text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <CardTitle>{workspace.title}</CardTitle>
                                <CardDescription>
                                    {workspace.description}
                                </CardDescription>
                            </div>
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
                                                workspace: 'igr-resolutions',
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
                    </CardHeader>
                    <CardContent className="p-0">
                        {workspace.rows.length ? (
                            <WorkspaceDataTable
                                columns={workspace.columns}
                                rows={workspace.rows}
                                pagination={workspace.pagination}
                                bulkExport={{
                                    workspace: 'igr-resolutions',
                                    filters,
                                }}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title="No matching resolutions"
                                description="Adjust the search or reporting dates, or record a resolution from an authorized forum."
                                className="min-h-72 border-0"
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function AnalyticsRanking({
    title,
    rows,
}: {
    title: string;
    rows: Array<{ name: string; total: number }>;
}) {
    const maximum = Math.max(1, ...rows.map((row) => row.total));

    return (
        <div className="flex flex-col gap-3">
            <h3 className="text-sm font-medium">{title}</h3>
            {rows.length ? (
                rows.map((row) => (
                    <div key={row.name} className="flex flex-col gap-2">
                        <div className="flex items-center justify-between gap-4 text-sm">
                            <span className="truncate">{row.name}</span>
                            <span className="font-medium tabular-nums">
                                {row.total.toLocaleString()}
                            </span>
                        </div>
                        <Progress
                            value={(row.total / maximum) * 100}
                            aria-label={`${row.name}: ${row.total} gaps`}
                        />
                    </div>
                ))
            ) : (
                <p className="text-sm text-muted-foreground">
                    No data matches the selected filters.
                </p>
            )}
        </div>
    );
}

function GovernanceForms({ options }: { options: Props['options'] }) {
    const [partyCount, setPartyCount] = useState(1);
    const [quorumConfirmed, setQuorumConfirmed] = useState(false);

    return (
        <div className="flex flex-wrap gap-3">
            <FormSheet
                title="Establish IGR forum"
                triggerLabel="Establish forum"
                description="Create the authoritative source forum."
            >
                <Form
                    action={storeForum({})}
                    className="flex flex-col gap-3"
                    resetOnSuccess
                >
                    {({ processing }) => (
                        <>
                            <Field label="Forum code">
                                <Input
                                    name="code"
                                    required
                                    placeholder="IGR-CG-SUMMIT"
                                />
                            </Field>
                            <Field label="Forum name">
                                <Input name="name" required />
                            </Field>
                            <Field label="Forum type">
                                <StaticSearchableSelect
                                    id="igr-forum-type"
                                    name="forum_type"
                                    values={[
                                        'summit',
                                        'council',
                                        'committee',
                                        'technical',
                                    ]}
                                />
                            </Field>
                            <Field label="Mandate">
                                <textarea
                                    name="mandate"
                                    required
                                    className={textareaClass}
                                />
                            </Field>
                            <Field label="Secretariat">
                                <Select
                                    name="secretariat_user_id"
                                    options={options.users}
                                    optional
                                />
                            </Field>
                            <Button type="submit" disabled={processing}>
                                Create forum
                            </Button>
                        </>
                    )}
                </Form>
            </FormSheet>
            <FormSheet
                title="Record formal IGR meeting"
                triggerLabel="Record meeting"
                description="Capture quorum and minutes provenance before linking adopted resolutions."
            >
                <Form
                    action={storeMeeting({})}
                    className="flex flex-col gap-3"
                    resetOnSuccess
                    onSuccess={() => setQuorumConfirmed(false)}
                >
                    {({ processing }) => (
                        <>
                            <Field label="Forum">
                                <Select
                                    name="igr_forum_id"
                                    options={options.forums}
                                />
                            </Field>
                            <Field label="Meeting reference">
                                <Input
                                    name="reference"
                                    required
                                    placeholder="IGR/SUMMIT/2026/04"
                                />
                            </Field>
                            <Field label="Meeting title">
                                <Input name="title" required />
                            </Field>
                            <DatePickerField
                                name="held_on"
                                label="Meeting date"
                                required
                            />
                            <Field label="Venue">
                                <Input name="venue" required />
                            </Field>
                            <Field label="Chair">
                                <Select
                                    name="chair_user_id"
                                    options={options.users}
                                    optional
                                />
                            </Field>
                            <Field label="Minutes reference">
                                <Input
                                    name="minutes_reference"
                                    required
                                    placeholder="DMS/IGR/MIN/2026/04"
                                />
                            </Field>
                            <input
                                type="hidden"
                                name="quorum_confirmed"
                                value={quorumConfirmed ? '1' : '0'}
                            />
                            <div className="flex items-start gap-3 rounded-lg border p-3">
                                <Checkbox
                                    id="igr-quorum-confirmed"
                                    checked={quorumConfirmed}
                                    onCheckedChange={(checked) =>
                                        setQuorumConfirmed(checked === true)
                                    }
                                />
                                <label
                                    htmlFor="igr-quorum-confirmed"
                                    className="text-sm leading-5"
                                >
                                    Confirm that the formal meeting achieved
                                    quorum under the forum mandate.
                                </label>
                            </div>
                            <Button type="submit" disabled={processing}>
                                Record meeting
                            </Button>
                        </>
                    )}
                </Form>
            </FormSheet>
            <FormSheet
                title="Create implementation-gap category"
                triggerLabel="Create gap category"
                description="Configure a reusable classification and its default risk severity."
            >
                <Form
                    action={storeGapCategory({})}
                    className="flex flex-col gap-3"
                    resetOnSuccess
                >
                    {({ processing }) => (
                        <>
                            <Field label="Category code">
                                <Input
                                    name="code"
                                    required
                                    placeholder="DATA-QUALITY"
                                />
                            </Field>
                            <Field label="Category name">
                                <Input name="name" required />
                            </Field>
                            <Field label="Description">
                                <textarea
                                    name="description"
                                    required
                                    minLength={20}
                                    className={textareaClass}
                                />
                            </Field>
                            <Field label="Default severity">
                                <StaticSearchableSelect
                                    id="igr-gap-default-severity"
                                    name="default_severity"
                                    values={[
                                        'medium',
                                        'high',
                                        'critical',
                                        'low',
                                    ]}
                                />
                            </Field>
                            <Button type="submit" disabled={processing}>
                                Create category
                            </Button>
                        </>
                    )}
                </Form>
            </FormSheet>
            <FormSheet
                title="Register adopted resolution"
                triggerLabel="Register resolution"
                size="xl"
                description="Assign exactly one lead and any supporting responsible parties."
            >
                <Form
                    action={storeResolution({})}
                    className="flex flex-col gap-3"
                    resetOnSuccess
                >
                    {({ processing }) => (
                        <>
                            <Field label="Source forum">
                                <Select
                                    name="igr_forum_id"
                                    options={options.forums}
                                />
                            </Field>
                            <Field label="Formal meeting (optional for historical records)">
                                <Select
                                    name="igr_forum_meeting_id"
                                    options={options.meetings}
                                    optional
                                />
                            </Field>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Resolution number">
                                    <Input
                                        name="resolution_number"
                                        required
                                        placeholder="IGR/2026/001"
                                    />
                                </Field>
                                <Field label="Title">
                                    <Input name="title" required />
                                </Field>
                                <DatePickerField
                                    name="resolved_on"
                                    label="Resolution date"
                                    required
                                />
                                <DatePickerField
                                    name="due_on"
                                    label="Implementation deadline"
                                    required
                                />
                            </div>
                            <Field label="Resolution text">
                                <textarea
                                    name="resolution_text"
                                    required
                                    className={textareaClass}
                                />
                            </Field>
                            <Field label="Priority">
                                <StaticSearchableSelect
                                    id="igr-priority"
                                    name="priority"
                                    values={[
                                        'medium',
                                        'high',
                                        'critical',
                                        'low',
                                    ]}
                                />
                            </Field>
                            <div className="flex flex-col gap-3 rounded-lg border p-3">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="font-medium">
                                        Responsible parties
                                    </p>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setPartyCount((count) =>
                                                Math.min(10, count + 1),
                                            )
                                        }
                                    >
                                        <Plus aria-hidden="true" />
                                        Add party
                                    </Button>
                                </div>
                                {Array.from(
                                    { length: partyCount },
                                    (_, index) => (
                                        <div
                                            key={index}
                                            className="grid gap-2 rounded-md bg-muted/40 p-3 sm:grid-cols-2"
                                        >
                                            <input
                                                type="hidden"
                                                name={`assignments[${index}][is_lead]`}
                                                value={index === 0 ? '1' : '0'}
                                            />
                                            <Field
                                                label={
                                                    index === 0
                                                        ? 'Lead responsible user'
                                                        : 'Responsible user'
                                                }
                                            >
                                                <Select
                                                    name={`assignments[${index}][user_id]`}
                                                    options={options.users}
                                                />
                                            </Field>
                                            <Field label="County">
                                                <Select
                                                    name={`assignments[${index}][county_id]`}
                                                    options={options.counties}
                                                    optional
                                                />
                                            </Field>
                                            <Field label="Organization">
                                                <Select
                                                    name={`assignments[${index}][organization_id]`}
                                                    options={
                                                        options.organizations
                                                    }
                                                    optional
                                                />
                                            </Field>
                                            <Field label="Role">
                                                <StaticSearchableSelect
                                                    id={`igr-role-${index}`}
                                                    name={`assignments[${index}][responsibility_role]`}
                                                    values={
                                                        index === 0
                                                            ? [
                                                                  'lead',
                                                                  'support',
                                                                  'oversight',
                                                              ]
                                                            : [
                                                                  'implementer',
                                                                  'support',
                                                                  'oversight',
                                                              ]
                                                    }
                                                />
                                            </Field>
                                        </div>
                                    ),
                                )}
                            </div>
                            <Button type="submit" disabled={processing}>
                                Register and notify parties
                            </Button>
                        </>
                    )}
                </Form>
            </FormSheet>
        </div>
    );
}

function ResolutionCard({
    resolution,
    capabilities,
    resolutionOptions,
    gapCategories,
    countyOptions,
}: {
    resolution: Resolution;
    capabilities: Props['capabilities'];
    resolutionOptions: Option[];
    gapCategories: Option[];
    countyOptions: Option[];
}) {
    const overdue =
        resolution.status !== 'closed' &&
        new Date(resolution.dueOn) < new Date();
    const hasCleanImplementationEvidence = resolution.documents.some(
        (document) =>
            document.purpose === 'igr-implementation-evidence' &&
            document.scanStatus === 'clean',
    );
    const hasUnacceptedGaps = resolution.gaps.some(
        (gap) => gap.status !== 'accepted',
    );
    const responsibleUsers = resolution.assignments
        .filter(
            (assignment): assignment is Assignment & { userId: string } =>
                assignment.userId !== null,
        )
        .map((assignment) => ({
            id: assignment.userId,
            name: assignment.user ?? 'Responsible user',
        }));
    const assignedCountyIds = new Set(
        resolution.assignments
            .map((assignment) => assignment.countyId)
            .filter((countyId): countyId is string => countyId !== null),
    );

    return (
        <article className="flex flex-col gap-4 rounded-xl border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="outline">
                            {resolution.status.replaceAll('_', ' ')}
                        </Badge>
                        <Badge variant="secondary">{resolution.priority}</Badge>
                        {overdue && (
                            <Badge variant="destructive">Overdue</Badge>
                        )}
                    </div>
                    <h3 className="mt-2 font-bold">
                        {resolution.number} · {resolution.title}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {resolution.forum} · resolved{' '}
                        {new Date(resolution.resolvedOn).toLocaleDateString()} ·
                        due {new Date(resolution.dueOn).toLocaleDateString()}
                    </p>
                </div>
                <div className="min-w-44">
                    <div className="mb-1 flex justify-between text-xs">
                        <span>Implementation</span>
                        <span>{resolution.progress}%</span>
                    </div>
                    <Progress value={resolution.progress} />
                </div>
            </div>
            <p className="text-sm">{resolution.text}</p>
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <Badge variant="secondary">{resolution.referenceRelease}</Badge>
                {resolution.referenceChecksum && (
                    <span
                        className="max-w-64 truncate font-mono text-muted-foreground"
                        title={resolution.referenceChecksum}
                    >
                        {resolution.referenceChecksum}
                    </span>
                )}
            </div>
            {resolution.meeting ? (
                <div className="grid gap-2 rounded-lg border bg-muted/30 p-3 text-sm sm:grid-cols-2">
                    <div>
                        <p className="font-medium">Formal meeting provenance</p>
                        <p className="text-muted-foreground">
                            {resolution.meeting.reference} ·{' '}
                            {resolution.meeting.title}
                        </p>
                    </div>
                    <div>
                        <p>
                            {new Date(
                                resolution.meeting.heldOn,
                            ).toLocaleDateString()}{' '}
                            · {resolution.meeting.venue}
                        </p>
                        <p className="text-muted-foreground">
                            Minutes: {resolution.meeting.minutesReference}
                            {resolution.meeting.chair
                                ? ` · Chair: ${resolution.meeting.chair}`
                                : ''}
                        </p>
                    </div>
                </div>
            ) : (
                <Badge variant="outline">
                    Historical record — formal meeting not linked
                </Badge>
            )}
            {(resolution.dependencies.length > 0 ||
                resolution.dependents.length > 0) && (
                <div className="grid gap-3 rounded-lg border p-3 text-sm md:grid-cols-2">
                    <DependencyList
                        title="Prerequisites"
                        dependencies={resolution.dependencies}
                    />
                    <DependencyList
                        title="Dependent resolutions"
                        dependencies={resolution.dependents}
                    />
                </div>
            )}
            <div className="flex flex-wrap gap-3">
                {resolution.assignments.map((party, index) => (
                    <div
                        key={`${party.user}-${index}`}
                        className="flex items-center gap-2 rounded-lg border px-2.5 py-2"
                    >
                        <div className="flex flex-col gap-1">
                            <span className="text-sm font-medium">
                                {party.user ?? party.organization}
                            </span>
                            <Badge variant="outline">
                                {party.lead ? 'Lead · ' : ''}
                                {party.role}
                            </Badge>
                        </div>
                        {party.county && (
                            <CountyIdentity county={party.county} compact />
                        )}
                    </div>
                ))}
            </div>
            {resolution.gap && (
                <div className="flex gap-2 rounded-lg bg-muted p-3 text-sm">
                    <TriangleAlert aria-hidden="true" />
                    <div>
                        <p className="font-medium">Implementation gap</p>
                        <p className="text-muted-foreground">
                            {resolution.gap}
                        </p>
                    </div>
                </div>
            )}
            {resolution.gaps.length > 0 && (
                <div className="flex flex-col gap-3 rounded-lg border p-3">
                    <p className="font-medium">Governed implementation gaps</p>
                    {resolution.gaps.map((gap) => (
                        <ResolutionGapCard
                            key={gap.id}
                            gap={gap}
                            resolutionId={resolution.id}
                            capabilities={capabilities}
                        />
                    ))}
                </div>
            )}
            {(capabilities.update || capabilities.manage) &&
                ['open', 'in_progress'].includes(resolution.status) && (
                    <FormSheet
                        title="Report implementation gap"
                        triggerLabel="Report gap"
                        description={`Classify, assign and deadline a gap affecting ${resolution.number}.`}
                    >
                        <Form
                            action={storeGap({ resolution: resolution.id })}
                            className="grid gap-4"
                            resetOnSuccess
                        >
                            {({ processing }) => (
                                <>
                                    <Field label="Gap category">
                                        <Select
                                            name="igr_gap_category_id"
                                            options={gapCategories}
                                        />
                                    </Field>
                                    <Field label="Gap title">
                                        <Input name="title" required />
                                    </Field>
                                    <Field label="Description">
                                        <textarea
                                            name="description"
                                            required
                                            minLength={20}
                                            className={textareaClass}
                                        />
                                    </Field>
                                    <Field label="Implementation impact">
                                        <textarea
                                            name="impact"
                                            required
                                            minLength={20}
                                            className={textareaClass}
                                        />
                                    </Field>
                                    <Field label="Severity">
                                        <StaticSearchableSelect
                                            id={`igr-gap-severity-${resolution.id}`}
                                            name="severity"
                                            values={[
                                                'medium',
                                                'high',
                                                'critical',
                                                'low',
                                            ]}
                                        />
                                    </Field>
                                    <Field label="Accountable owner">
                                        <Select
                                            name="owner_user_id"
                                            options={responsibleUsers}
                                        />
                                    </Field>
                                    <Field label="Affected county">
                                        <Select
                                            name="county_id"
                                            options={countyOptions.filter(
                                                (county) =>
                                                    assignedCountyIds.has(
                                                        county.id,
                                                    ),
                                            )}
                                            optional
                                        />
                                    </Field>
                                    <DatePickerField
                                        name="due_on"
                                        label="Mitigation due date"
                                        required
                                    />
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            gapCategories.length === 0 ||
                                            responsibleUsers.length === 0
                                        }
                                    >
                                        Assign gap
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            <IgrDocumentControls
                resolutionId={resolution.id}
                status={resolution.status}
                documents={resolution.documents}
                canUpload={
                    (capabilities.manage || capabilities.update) &&
                    ['open', 'in_progress'].includes(resolution.status)
                }
            />
            {capabilities.manage &&
                !['closure_review', 'closed'].includes(resolution.status) && (
                    <FormSheet
                        title="Add resolution dependency"
                        triggerLabel="Add dependency"
                        description={`Record the prerequisite that governs ${resolution.number}. Circular and cross-scope links are rejected.`}
                    >
                        <Form
                            action={storeDependency({
                                resolution: resolution.id,
                            })}
                            className="grid gap-4"
                            resetOnSuccess
                        >
                            {({ processing }) => (
                                <>
                                    <Field label="Prerequisite resolution">
                                        <Select
                                            name="prerequisite_resolution_id"
                                            options={resolutionOptions.filter(
                                                (option) =>
                                                    option.id !== resolution.id,
                                            )}
                                        />
                                    </Field>
                                    <Field label="Dependency type">
                                        <StaticSearchableSelect
                                            id={`igr-dependency-type-${resolution.id}`}
                                            name="dependency_type"
                                            values={['blocks', 'informs']}
                                        />
                                    </Field>
                                    <Field label="Dependency rationale">
                                        <textarea
                                            name="rationale"
                                            required
                                            minLength={20}
                                            className={textareaClass}
                                        />
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        Add dependency
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            {capabilities.update &&
                ['open', 'in_progress'].includes(resolution.status) && (
                    <FormSheet
                        title="Record implementation progress"
                        triggerLabel="Record progress"
                        description={`Update ${resolution.number} with evidence and any implementation gap.`}
                    >
                        <Form
                            action={storeUpdate({ resolution: resolution.id })}
                            className="grid gap-4"
                        >
                            {({ processing }) => (
                                <>
                                    <Field label="Progress percentage">
                                        <Input
                                            name="progress_percentage"
                                            type="number"
                                            min={resolution.progress}
                                            max="100"
                                            required
                                            defaultValue={resolution.progress}
                                        />
                                    </Field>
                                    <Field label="Evidence note or reference (optional)">
                                        <Input
                                            name="evidence_reference"
                                            placeholder="Add context for the linked repository evidence"
                                        />
                                    </Field>
                                    <Field label="Implementation narrative">
                                        <textarea
                                            name="narrative"
                                            required
                                            className={textareaClass}
                                        />
                                    </Field>
                                    <Button type="submit" disabled={processing}>
                                        Record progress
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            <div className="flex flex-wrap gap-2">
                {resolution.status === 'open' && capabilities.update && (
                    <Transition
                        resolutionId={resolution.id}
                        name="start"
                        label="Start implementation"
                    />
                )}
                {resolution.status === 'in_progress' &&
                    resolution.progress === 100 &&
                    capabilities.update && (
                        <Transition
                            resolutionId={resolution.id}
                            name="submit_closure"
                            label="Submit for closure"
                            disabled={
                                !hasCleanImplementationEvidence ||
                                hasUnacceptedGaps
                            }
                            disabledReason={
                                hasUnacceptedGaps
                                    ? 'Resolve and obtain independent acceptance for every implementation gap before closure.'
                                    : 'Upload a clean implementation-evidence record before submitting for closure.'
                            }
                        />
                    )}
                {resolution.status === 'closure_review' &&
                    capabilities.close && (
                        <>
                            <Transition
                                resolutionId={resolution.id}
                                name="approve_closure"
                                label="Approve closure"
                            />
                            <Transition
                                resolutionId={resolution.id}
                                name="reject_closure"
                                label="Return for action"
                                variant="outline"
                            />
                        </>
                    )}
            </div>
            {resolution.updates.length > 0 && (
                <details>
                    <summary className="cursor-pointer text-sm font-medium">
                        Recent implementation history (
                        {resolution.updates.length})
                    </summary>
                    <div className="mt-3 flex flex-col gap-2">
                        {resolution.updates.map((update) => (
                            <div
                                key={update.id}
                                className="rounded-md bg-muted/40 p-3 text-sm"
                            >
                                <p className="font-medium">
                                    {update.progress}% ·{' '}
                                    {new Date(
                                        update.reportedAt,
                                    ).toLocaleString()}
                                </p>
                                <p>{update.narrative}</p>
                                {update.evidence && (
                                    <p className="text-muted-foreground">
                                        Evidence: {update.evidence}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                </details>
            )}
        </article>
    );
}

function DependencyList({
    title,
    dependencies,
}: {
    title: string;
    dependencies: Dependency[];
}) {
    return (
        <div>
            <p className="font-medium">{title}</p>
            {dependencies.length === 0 ? (
                <p className="mt-1 text-muted-foreground">None recorded.</p>
            ) : (
                <ul className="mt-2 space-y-2">
                    {dependencies.map((dependency) => (
                        <li
                            key={dependency.id}
                            className="rounded-md bg-muted/50 p-2"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">
                                    {dependency.number}
                                </span>
                                <Badge variant="outline">
                                    {dependency.type}
                                </Badge>
                                <Badge variant="secondary">
                                    {dependency.status.replaceAll('_', ' ')}
                                </Badge>
                            </div>
                            <p className="mt-1 text-muted-foreground">
                                {dependency.title}
                            </p>
                            {dependency.rationale && (
                                <p className="mt-1">{dependency.rationale}</p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function ResolutionGapCard({
    gap,
    resolutionId,
    capabilities,
}: {
    gap: ResolutionGap;
    resolutionId: string;
    capabilities: Props['capabilities'];
}) {
    return (
        <div className="rounded-lg bg-muted/40 p-3 text-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="outline">{gap.category}</Badge>
                        <Badge
                            variant={
                                gap.severity === 'critical'
                                    ? 'destructive'
                                    : 'secondary'
                            }
                        >
                            {gap.severity}
                        </Badge>
                        <Badge variant="outline">{gap.status}</Badge>
                        {gap.overdue && (
                            <Badge variant="destructive">Overdue</Badge>
                        )}
                    </div>
                    <p className="mt-2 font-medium">{gap.title}</p>
                    <p className="text-muted-foreground">
                        Owner: {gap.owner} · due{' '}
                        {new Date(gap.dueOn).toLocaleDateString()}
                    </p>
                </div>
                {gap.county && <CountyIdentity county={gap.county} compact />}
            </div>
            <p className="mt-2">{gap.description}</p>
            <p className="mt-1 text-muted-foreground">Impact: {gap.impact}</p>
            {gap.mitigationPlan && (
                <p className="mt-2">Mitigation: {gap.mitigationPlan}</p>
            )}
            {gap.resolutionNote && (
                <p className="mt-1">Resolution: {gap.resolutionNote}</p>
            )}
            <div className="mt-3 flex flex-wrap gap-2">
                {gap.status === 'open' &&
                    (capabilities.update || capabilities.manage) && (
                        <GapTransition
                            resolutionId={resolutionId}
                            gapId={gap.id}
                            transition="start_mitigation"
                            label="Start mitigation"
                        />
                    )}
                {['open', 'mitigating'].includes(gap.status) &&
                    (capabilities.update || capabilities.manage) && (
                        <GapTransition
                            resolutionId={resolutionId}
                            gapId={gap.id}
                            transition="resolve"
                            label="Submit resolution"
                        />
                    )}
                {gap.status === 'resolved' && capabilities.close && (
                    <>
                        <GapTransition
                            resolutionId={resolutionId}
                            gapId={gap.id}
                            transition="accept"
                            label="Accept resolution"
                        />
                        <GapTransition
                            resolutionId={resolutionId}
                            gapId={gap.id}
                            transition="reject"
                            label="Return mitigation"
                        />
                    </>
                )}
            </div>
        </div>
    );
}

function GapTransition({
    resolutionId,
    gapId,
    transition: transitionName,
    label,
}: {
    resolutionId: string;
    gapId: string;
    transition: string;
    label: string;
}) {
    return (
        <FormSheet
            title={label}
            triggerLabel={label}
            description="Record the decision basis for the governed implementation-gap lifecycle."
        >
            <Form
                action={transitionGap({ resolution: resolutionId, gap: gapId })}
                className="grid gap-4"
            >
                {({ processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="transition"
                            value={transitionName}
                        />
                        <Field label="Decision rationale">
                            <textarea
                                name="rationale"
                                required
                                minLength={20}
                                className={textareaClass}
                            />
                        </Field>
                        <Button type="submit" disabled={processing}>
                            {label}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function Transition({
    resolutionId,
    name,
    label,
    variant = 'default',
    disabled = false,
    disabledReason,
}: {
    resolutionId: string;
    name: string;
    label: string;
    variant?: 'default' | 'outline';
    disabled?: boolean;
    disabledReason?: string;
}) {
    return (
        <FormSheet
            title={label}
            triggerLabel={label}
            description={
                disabled
                    ? (disabledReason ?? 'This transition is not available.')
                    : `Record the decision basis for ${label.toLowerCase()}.`
            }
            triggerDisabled={disabled}
            triggerTitle={disabledReason}
        >
            <Form
                action={transition({ resolution: resolutionId })}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="transition" value={name} />
                        <Field label="Decision comment">
                            <textarea
                                name="comment"
                                required
                                minLength={10}
                                className={textareaClass}
                                aria-invalid={Boolean(errors.comment)}
                            />
                        </Field>
                        {disabledReason && disabled && (
                            <p className="text-sm text-muted-foreground">
                                {disabledReason}
                            </p>
                        )}
                        <Button
                            type="submit"
                            variant={variant}
                            disabled={processing || disabled}
                        >
                            {label}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
