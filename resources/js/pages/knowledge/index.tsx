import { Form, Head, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Bell,
    BellOff,
    Download,
    Eye,
    FileText,
    Flag,
    Lightbulb,
    MessageSquare,
    MoreHorizontal,
    Plus,
    Send,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
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
import { preview as previewEvidence } from '@/routes/evidence';
import { transition as transitionCommunityReport } from '@/routes/knowledge/community-reports';
import {
    store as storeDiscussion,
    subscription as updateDiscussionSubscription,
} from '@/routes/knowledge/discussions';
import {
    store as storeInnovation,
    transition as transitionInnovation,
} from '@/routes/knowledge/innovations';
import { store as storeFundingDecision } from '@/routes/knowledge/innovations/funding-decisions';
import {
    store as storeMilestone,
    update as updateMilestone,
    verify as verifyMilestone,
} from '@/routes/knowledge/innovations/milestones';
import { store as storePanelReview } from '@/routes/knowledge/innovations/panel-reviews';
import {
    store as storeItem,
    transition as transitionItem,
} from '@/routes/knowledge/items';
import {
    moderate as moderatePost,
    store as storePost,
} from '@/routes/knowledge/posts';
import { store as storeCommunityReport } from '@/routes/knowledge/posts/reports';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name?: string; label?: string };
type Discussion = {
    id: string;
    title: string;
    prompt: string;
    creator: string;
    subscribed: boolean;
    posts: Array<{
        id: string;
        body: string;
        author: string;
        postedAt: string;
        moderationStatus: 'visible' | 'hidden';
        moderationReason: string | null;
        moderator: string | null;
        moderatedAt: string | null;
        canReport: boolean;
    }>;
};
type KnowledgeItem = {
    id: string;
    reference: string;
    type: string;
    title: string;
    summary: string;
    searchExcerpt: string | null;
    content: string | null;
    tags: string[];
    visibility: string;
    status: string;
    publishedOn: string | null;
    reviewDueAt: string | null;
    sourceOrganization: string | null;
    externalUrl: string | null;
    language: string;
    county: CountyIdentityValue | null;
    sector: string | null;
    referenceData: null | {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    };
    author: string;
    document: null | {
        id: string;
        title: string;
        mimeType: string | null;
        originalName: string | null;
    };
    courses: Array<{ id: string; code: string; title: string }>;
    discussions: Discussion[];
    createdAt: string;
};
type Innovation = {
    id: string;
    reference: string;
    title: string;
    problem: string;
    solution: string;
    impact: string;
    maturity: string;
    stage: string;
    status: string;
    incubationSupport: string | null;
    evidenceReference: string | null;
    county: CountyIdentityValue | null;
    sector: string | null;
    referenceData: null | {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    };
    submitter: string;
    reviewer: string | null;
    submittedAt: string | null;
    decisionDueAt: string | null;
    createdAt: string;
    panelSummary: {
        count: number;
        average: number | null;
        advanceCount: number;
        ready: boolean;
    };
    panelReviews: Array<{
        id: string;
        reviewer: string;
        strategicFit: number;
        feasibility: number;
        inclusion: number;
        evidence: number;
        weightedScore: number;
        recommendation: string;
        rationale: string;
        rubricCode: string;
        rubricChecksum: string;
        evidenceChecksum: string;
        reviewedAt: string | null;
    }>;
    fundingDecisions: Array<{
        id: string;
        version: number;
        decision: string;
        amount: number;
        currency: string;
        fundingType: string;
        reference: string;
        rationale: string;
        decisionMaker: string;
        decidedAt: string | null;
        previousChecksum: string | null;
        evidenceChecksum: string;
    }>;
    fundingReady: boolean;
    milestones: Array<{
        id: string;
        title: string;
        hypothesis: string;
        successMetric: string;
        baselineValue: string;
        targetValue: string;
        actualValue: string | null;
        dueAt: string | null;
        status: string;
        outcomeSummary: string | null;
        owner: string;
        submitter: string | null;
        submittedAt: string | null;
        verificationDecision: string;
        verificationRationale: string | null;
        verifier: string | null;
        verifiedAt: string | null;
        document: null | {
            id: string;
            title: string;
            originalName: string | null;
            mimeType: string | null;
        };
    }>;
    pilotVerified: boolean;
};
type CommunityReport = {
    id: string;
    reference: string;
    postId: string;
    discussion: string;
    postBody: string;
    postAuthor: string;
    postModerationStatus: string;
    county: CountyIdentityValue | null;
    reporter: string;
    category: string;
    severity: string;
    description: string;
    status: 'reported' | 'investigating' | 'resolved' | 'dismissed';
    triager: string | null;
    decisionMaker: string | null;
    resolution: string | null;
    postAction: string | null;
    dueAt: string | null;
    createdAt: string;
    decidedAt: string | null;
};
type PageSet<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    items: PageSet<KnowledgeItem>;
    innovations: PageSet<Innovation>;
    reports: PageSet<CommunityReport>;
    filters: Record<string, string | undefined>;
    capabilities: { contribute: boolean; curate: boolean; manage: boolean };
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
    options: {
        counties: CountyIdentityValue[];
        sectors: Option[];
        documents: Option[];
        courses: Option[];
        tags: string[];
        milestoneOwners: Option[];
    };
};

const itemTypes = [
    'best_practice',
    'case_study',
    'research',
    'publication',
    'toolkit',
    'blog',
].map(option);
const itemStatuses = ['draft', 'editorial_review', 'published', 'archived'].map(
    option,
);

export default function KnowledgeManagement({
    items,
    innovations,
    reports,
    filters,
    capabilities,
    catalogue,
    options,
}: Props) {
    const { currentTeam } = usePage().props;

    if (!currentTeam) {
        return null;
    }

    const itemRows: WorkspaceRow[] = items.data.map((item) => ({
        id: item.id,
        status: item.status,
        cells: [
            item.reference,
            item.title,
            item.searchExcerpt ?? item.summary,
            humanize(item.type),
            item.county ?? 'National',
            item.sector ?? 'Cross-sector',
            item.referenceData
                ? `v${item.referenceData.version}`
                : 'Legacy · unpinned',
            item.referenceData?.checksum ?? 'Legacy · unpinned',
            item.tags.join(', ') || '—',
            `${item.courses.length} course links · ${item.discussions.length} discussions`,
            humanize(item.status),
        ],
    }));
    const innovationRows: WorkspaceRow[] = innovations.data.map(
        (innovation) => ({
            id: innovation.id,
            status: innovation.status,
            cells: [
                innovation.reference,
                innovation.title,
                innovation.county ?? 'National',
                innovation.sector ?? 'Cross-sector',
                innovation.referenceData
                    ? `v${innovation.referenceData.version}`
                    : 'Legacy · unpinned',
                innovation.referenceData?.checksum ?? 'Legacy · unpinned',
                humanize(innovation.maturity),
                humanize(innovation.stage),
                innovation.submitter,
                humanize(innovation.status),
            ],
        }),
    );
    const reportRows: WorkspaceRow[] = reports.data.map((report) => ({
        id: report.id,
        status: report.status,
        cells: [
            report.reference,
            report.discussion,
            report.county ?? 'National',
            humanize(report.category),
            humanize(report.severity),
            report.reporter,
            report.dueAt
                ? new Date(report.dueAt).toLocaleString(DEFAULT_LOCALE)
                : 'Completed',
            humanize(report.status),
        ],
    }));

    return (
        <>
            <Head title="Knowledge Management" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Institutional memory and innovation
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Devolution knowledge commons
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                Curate evidence-backed practices, connect
                                repository records to learning, convene
                                communities of practice, and govern innovations
                                from concept to scale.
                            </p>
                        </div>
                        {capabilities.contribute && (
                            <div className="flex flex-wrap gap-2">
                                <DiscussionForm
                                    teamSlug={currentTeam.slug}
                                    items={items.data}
                                    counties={options.counties}
                                />
                                <InnovationForm
                                    teamSlug={currentTeam.slug}
                                    counties={options.counties}
                                    sectors={options.sectors}
                                    catalogue={catalogue}
                                />
                                <ItemForm
                                    teamSlug={currentTeam.slug}
                                    options={options}
                                    catalogue={catalogue}
                                />
                            </div>
                        )}
                    </div>
                </section>
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'item_type',
                            label: 'Resource type',
                            options: itemTypes,
                            value: filters.item_type,
                        },
                        {
                            key: 'status',
                            label: 'Publication status',
                            options: itemStatuses,
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
                            options: options.sectors.map(toNamed),
                            value: filters.sector_id,
                        },
                        {
                            key: 'tag',
                            label: 'Tag',
                            options: options.tags.map((tag) => ({
                                id: tag,
                                name: humanize(tag),
                            })),
                            value: filters.tag,
                        },
                    ]}
                />
                <RepositoryTable
                    items={items}
                    rows={itemRows}
                    teamSlug={currentTeam.slug}
                    capabilities={capabilities}
                    filters={filters}
                />
                <InnovationTable
                    innovations={innovations}
                    rows={innovationRows}
                    teamSlug={currentTeam.slug}
                    capabilities={capabilities}
                    filters={filters}
                    options={options}
                />
                <ModerationQueue
                    reports={reports}
                    rows={reportRows}
                    teamSlug={currentTeam.slug}
                    capabilities={capabilities}
                    filters={filters}
                />
            </div>
        </>
    );
}

function RepositoryTable({
    items,
    rows,
    teamSlug,
    capabilities,
    filters,
}: {
    items: PageSet<KnowledgeItem>;
    rows: WorkspaceRow[];
    teamSlug: string;
    capabilities: Props['capabilities'];
    filters: Props['filters'];
}) {
    const pagination = page(items);

    return (
        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
            <TableHeader
                title="Curated repository"
                description={`${items.total.toLocaleString()} authorized resources`}
                teamSlug={teamSlug}
                filters={filters}
            />
            {rows.length ? (
                <WorkspaceDataTable
                    columns={[
                        'Reference',
                        'Resource',
                        'Matching context',
                        'Type',
                        'County scope',
                        'Sector',
                        'Reference release',
                        'Reference checksum',
                        'Tags',
                        'Learning & community',
                        'Status',
                    ]}
                    rows={rows}
                    pagination={pagination}
                    bulkExport={{
                        teamSlug,
                        workspace: 'knowledge',
                        filters,
                    }}
                    renderActionControl={(row) => {
                        const item = items.data.find(
                            (entry) => entry.id === row.id,
                        );

                        return item ? (
                            <ItemActions
                                item={item}
                                teamSlug={teamSlug}
                                capabilities={capabilities}
                            />
                        ) : null;
                    }}
                />
            ) : (
                <WorkspaceEmptyState
                    title="No knowledge resources found"
                    description="Adjust the filters or contribute the first evidence-backed practice, case study, research output, toolkit, or blog."
                    className="min-h-72 border-0"
                />
            )}
        </section>
    );
}

function InnovationTable({
    innovations,
    rows,
    teamSlug,
    capabilities,
    filters,
    options,
}: {
    innovations: PageSet<Innovation>;
    rows: WorkspaceRow[];
    teamSlug: string;
    capabilities: Props['capabilities'];
    filters: Props['filters'];
    options: Props['options'];
}) {
    return (
        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div className="flex items-center justify-between border-b px-5 py-4 sm:px-6">
                <div>
                    <h2 className="font-bold">Innovations hub</h2>
                    <p className="text-sm text-muted-foreground">
                        {innovations.total.toLocaleString()} concepts under
                        governed screening, incubation, piloting, or scale-up
                    </p>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline">
                            <Download /> Export
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                            <DropdownMenuItem key={format} asChild>
                                <a
                                    href={exportMethod.url(
                                        {
                                            current_team: teamSlug,
                                            workspace: 'knowledge-innovations',
                                            format,
                                        },
                                        { query: filters },
                                    )}
                                >
                                    {format.toUpperCase()}
                                </a>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            {rows.length ? (
                <WorkspaceDataTable
                    columns={[
                        'Reference',
                        'Innovation',
                        'County',
                        'Sector',
                        'Reference release',
                        'Reference checksum',
                        'Maturity',
                        'Stage',
                        'Submitter',
                        'Status',
                    ]}
                    rows={rows}
                    pagination={{
                        ...page(innovations),
                        pageName: 'innovation_page',
                    }}
                    bulkExport={{
                        teamSlug,
                        workspace: 'knowledge-innovations',
                        filters,
                    }}
                    renderActionControl={(row) => {
                        const innovation = innovations.data.find(
                            (entry) => entry.id === row.id,
                        );

                        return innovation ? (
                            <InnovationActions
                                innovation={innovation}
                                teamSlug={teamSlug}
                                capabilities={capabilities}
                                options={options}
                            />
                        ) : null;
                    }}
                />
            ) : (
                <WorkspaceEmptyState
                    title="No matching innovations"
                    description="Submit a locally developed solution for screening and incubation."
                    className="min-h-64 border-0"
                />
            )}
        </section>
    );
}

function ModerationQueue({
    reports,
    rows,
    teamSlug,
    capabilities,
    filters,
}: {
    reports: PageSet<CommunityReport>;
    rows: WorkspaceRow[];
    teamSlug: string;
    capabilities: Props['capabilities'];
    filters: Props['filters'];
}) {
    const exportFilters = {
        from: filters.from,
        to: filters.to,
        search: filters.report_search,
        status: filters.report_status,
    };

    return (
        <section className="grid gap-4">
            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div>
                    <h2 className="text-xl font-bold">Community moderation</h2>
                    <p className="text-sm text-muted-foreground">
                        {reports.total.toLocaleString()} scoped reports with
                        independent triage, SLA tracking and final decisions
                    </p>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline">
                            <Download data-icon="inline-start" /> Export queue
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                            <DropdownMenuItem key={format} asChild>
                                <a
                                    href={exportMethod.url(
                                        {
                                            current_team: teamSlug,
                                            workspace: 'knowledge-moderation',
                                            format,
                                        },
                                        { query: exportFilters },
                                    )}
                                >
                                    {format.toUpperCase()}
                                </a>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <DateRangeFilter
                initialFrom={filters.from}
                initialTo={filters.to}
                initialSearch={filters.report_search}
                searchKey="report_search"
                searchPlaceholder="Search moderation reports"
                selectFilters={[
                    {
                        key: 'report_status',
                        label: 'Report status',
                        options: [
                            'reported',
                            'investigating',
                            'resolved',
                            'dismissed',
                        ].map(option),
                        value: filters.report_status,
                    },
                ]}
            />
            <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                {rows.length ? (
                    <WorkspaceDataTable
                        columns={[
                            'Reference',
                            'Discussion',
                            'County',
                            'Category',
                            'Severity',
                            'Reporter',
                            'SLA due',
                            'Status',
                        ]}
                        rows={rows}
                        pagination={{
                            ...page(reports),
                            pageName: 'report_page',
                        }}
                        bulkExport={{
                            teamSlug,
                            workspace: 'knowledge-moderation',
                            filters: {
                                ...filters,
                                search: filters.report_search,
                                status: filters.report_status,
                            },
                        }}
                        renderActionControl={(row) => {
                            const report = reports.data.find(
                                (entry) => entry.id === row.id,
                            );

                            return report ? (
                                <CommunityReportActions
                                    report={report}
                                    teamSlug={teamSlug}
                                    capabilities={capabilities}
                                />
                            ) : null;
                        }}
                    />
                ) : (
                    <WorkspaceEmptyState
                        title="No matching community reports"
                        description="Reports within your authorized scope will appear here with their SLA and decision state."
                        className="min-h-64 border-0"
                    />
                )}
            </section>
        </section>
    );
}

function CommunityReportActions({
    report,
    teamSlug,
    capabilities,
}: {
    report: CommunityReport;
    teamSlug: string;
    capabilities: Props['capabilities'];
}) {
    const [surface, setSurface] = useState<string | null>(null);
    const transitions = [
        ['triage', capabilities.curate && report.status === 'reported'],
        ['resolve', capabilities.manage && report.status === 'investigating'],
        ['dismiss', capabilities.manage && report.status === 'investigating'],
    ] as const;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${report.reference}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setSurface('details')}>
                        <Eye /> Open report
                    </DropdownMenuItem>
                    {transitions
                        .filter(([, visible]) => visible)
                        .map(([transition]) => (
                            <DropdownMenuItem
                                key={transition}
                                onSelect={() => setSurface(transition)}
                            >
                                <ShieldCheck /> {humanize(transition)}
                            </DropdownMenuItem>
                        ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'details'
                                ? report.reference
                                : `${humanize(surface ?? '')} ${report.reference}`}
                        </SheetTitle>
                        <SheetDescription>
                            {humanize(report.category)} ·{' '}
                            {humanize(report.severity)} severity ·{' '}
                            {humanize(report.status)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <div className="flex flex-wrap gap-2">
                                    {report.county && (
                                        <CountyIdentity
                                            county={report.county}
                                        />
                                    )}
                                    <Badge>{humanize(report.status)}</Badge>
                                    <Badge variant="outline">
                                        {humanize(report.severity)} severity
                                    </Badge>
                                </div>
                                <Detail
                                    label="Discussion"
                                    value={report.discussion}
                                />
                                <Detail
                                    label="Reported contribution"
                                    value={`${report.postAuthor}: ${report.postBody}`}
                                />
                                <Detail
                                    label="Report"
                                    value={report.description}
                                />
                                <Detail
                                    label="Reporter"
                                    value={report.reporter}
                                />
                                <Detail
                                    label="SLA due"
                                    value={
                                        report.dueAt
                                            ? new Date(
                                                  report.dueAt,
                                              ).toLocaleString(DEFAULT_LOCALE)
                                            : 'Workflow completed'
                                    }
                                />
                                <Detail
                                    label="Decision"
                                    value={report.resolution ?? 'Pending'}
                                />
                            </>
                        ) : surface ? (
                            <Form
                                action={transitionCommunityReport({
                                    current_team: teamSlug,
                                    report: report.id,
                                })}
                                className="grid gap-4"
                                onSuccess={() => setSurface(null)}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="transition"
                                            value={surface}
                                        />
                                        <TextField
                                            name="rationale"
                                            label="Decision rationale"
                                            error={errors.rationale}
                                        />
                                        {surface !== 'triage' && (
                                            <>
                                                <SearchableSelect
                                                    id={`report-post-action-${report.id}`}
                                                    name="post_action"
                                                    label="Contribution action"
                                                    options={[
                                                        {
                                                            id: 'hide',
                                                            name: 'Hide contribution',
                                                        },
                                                        {
                                                            id: 'keep_visible',
                                                            name: 'Keep contribution visible',
                                                        },
                                                    ]}
                                                />
                                                <TextField
                                                    name="resolution"
                                                    label="Resolution and safeguards"
                                                    error={errors.resolution}
                                                />
                                            </>
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Confirm {humanize(surface)}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function TableHeader({
    title,
    description,
    teamSlug,
    filters,
}: {
    title: string;
    description: string;
    teamSlug: string;
    filters: Props['filters'];
}) {
    return (
        <div className="flex items-center justify-between border-b px-5 py-4 sm:px-6">
            <div>
                <h2 className="font-bold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <Download /> Export
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a
                                href={exportMethod.url(
                                    {
                                        current_team: teamSlug,
                                        workspace: 'knowledge',
                                        format,
                                    },
                                    { query: filters },
                                )}
                            >
                                {format.toUpperCase()}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

function ItemForm({
    teamSlug,
    options,
    catalogue,
}: {
    teamSlug: string;
    options: Props['options'];
    catalogue: Props['catalogue'];
}) {
    return (
        <FormSheet
            title="Contribute knowledge resource"
            description="Create an evidence-backed resource and link it to secure repository evidence or e-learning."
            triggerLabel="New resource"
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `Using governed catalogue release v${catalogue.version}`
                    : 'A checksum-verified, effective reference-data release is required.'
            }
            icon={Plus}
            size="xl"
        >
            <Form action={storeItem(teamSlug)} className="grid gap-5 pt-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                name="title"
                                label="Resource title"
                                error={errors.title}
                            />
                            <SearchableSelect
                                id="knowledge-type"
                                name="item_type"
                                label="Resource type"
                                options={itemTypes}
                            />
                            <SearchableSelect
                                id="knowledge-visibility"
                                name="visibility"
                                label="Visibility"
                                options={['national', 'county', 'internal'].map(
                                    option,
                                )}
                                defaultValue="national"
                            />
                            <SearchableSelect
                                id="knowledge-county"
                                name="county_id"
                                label="County scope"
                                options={options.counties}
                                optional
                            />
                            <SearchableSelect
                                id="knowledge-sector"
                                name="sector_id"
                                label="Sector"
                                options={options.sectors.map(toNamed)}
                                optional
                            />
                            <SearchableSelect
                                id="knowledge-document"
                                name="assessment_document_id"
                                label="Repository document"
                                options={options.documents.map(toNamed)}
                                optional
                            />
                            <SearchableSelect
                                id="knowledge-course"
                                name="course_ids[]"
                                label="Linked e-learning course"
                                options={options.courses.map(toNamed)}
                                optional
                            />
                            <Field
                                name="source_organization"
                                label="Source organization"
                                optional
                            />
                            <Field
                                name="external_url"
                                label="External source URL"
                                optional
                            />
                            <ReferenceCatalogSelect
                                id="knowledge-language"
                                name="language"
                                label="Language"
                                catalog="language"
                            />
                            <Field
                                name="tags"
                                label="Tags (comma separated)"
                                error={errors.tags}
                            />
                        </div>
                        <TextField
                            name="summary"
                            label="Executive summary"
                            error={errors.summary}
                        />
                        <TextField
                            name="content_body"
                            label="Curated content"
                            optional
                            error={errors.content_body}
                        />
                        <Button type="submit" disabled={processing}>
                            Save governed draft
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function DiscussionForm({
    teamSlug,
    items,
    counties,
}: {
    teamSlug: string;
    items: KnowledgeItem[];
    counties: CountyIdentityValue[];
}) {
    return (
        <FormSheet
            title="Open community discussion"
            description="Convene a moderated community of practice around a resource or county challenge."
            triggerLabel="New discussion"
            icon={MessageSquare}
        >
            <Form
                action={storeDiscussion(teamSlug)}
                className="grid gap-4 pt-4"
            >
                {({ processing }) => (
                    <>
                        <Field name="title" label="Discussion title" />
                        <TextField name="prompt" label="Opening prompt" />
                        <SearchableSelect
                            id="discussion-item"
                            name="knowledge_item_id"
                            label="Linked resource"
                            options={items.map((item) => ({
                                id: item.id,
                                name: `${item.reference} · ${item.title}`,
                            }))}
                            optional
                        />
                        <SearchableSelect
                            id="discussion-county"
                            name="county_id"
                            label="County scope"
                            options={counties}
                            optional
                        />
                        <SearchableSelect
                            id="discussion-visibility"
                            name="visibility"
                            label="Visibility"
                            options={['national', 'county', 'internal'].map(
                                option,
                            )}
                            defaultValue="national"
                        />
                        <Button type="submit" disabled={processing}>
                            Open discussion
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function InnovationForm({
    teamSlug,
    counties,
    sectors,
    catalogue,
}: {
    teamSlug: string;
    counties: CountyIdentityValue[];
    sectors: Option[];
    catalogue: Props['catalogue'];
}) {
    return (
        <FormSheet
            title="Submit devolution innovation"
            description="Register a solution for independent screening, incubation, piloting, and scale-up."
            triggerLabel="Submit innovation"
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `Using governed catalogue release v${catalogue.version}`
                    : 'A checksum-verified, effective reference-data release is required.'
            }
            icon={Lightbulb}
            size="xl"
        >
            <Form
                action={storeInnovation(teamSlug)}
                className="grid gap-4 pt-4"
            >
                {({ processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field name="title" label="Innovation title" />
                            <SearchableSelect
                                id="innovation-maturity"
                                name="maturity_level"
                                label="Maturity level"
                                options={[
                                    'idea',
                                    'prototype',
                                    'validated',
                                    'operational',
                                ].map(option)}
                                defaultValue="idea"
                            />
                            <SearchableSelect
                                id="innovation-county"
                                name="county_id"
                                label="County"
                                options={counties}
                                optional
                            />
                            <SearchableSelect
                                id="innovation-sector"
                                name="sector_id"
                                label="Sector"
                                options={sectors.map(toNamed)}
                                optional
                            />
                            <Field
                                name="evidence_reference"
                                label="Evidence or prototype URL"
                                optional
                            />
                        </div>
                        <TextField
                            name="problem_statement"
                            label="Problem statement"
                        />
                        <TextField
                            name="proposed_solution"
                            label="Proposed solution"
                        />
                        <TextField
                            name="expected_impact"
                            label="Expected impact"
                        />
                        <TextField
                            name="incubation_support"
                            label="Support requested"
                            optional
                        />
                        <Button type="submit" disabled={processing}>
                            Save innovation draft
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ItemActions({
    item,
    teamSlug,
    capabilities,
}: {
    item: KnowledgeItem;
    teamSlug: string;
    capabilities: Props['capabilities'];
}) {
    const [surface, setSurface] = useState<string | null>(null);
    const lifecycle = [
        ['submit_review', capabilities.contribute && item.status === 'draft'],
        ['publish', capabilities.curate && item.status === 'editorial_review'],
        ['return', capabilities.curate && item.status === 'editorial_review'],
        ['archive', capabilities.manage && item.status === 'published'],
    ] as const;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${item.title}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setSurface('details')}>
                        <Eye /> Open resource
                    </DropdownMenuItem>
                    {lifecycle
                        .filter(([, visible]) => visible)
                        .map(([transition]) => (
                            <DropdownMenuItem
                                key={transition}
                                onSelect={() => setSurface(transition)}
                            >
                                <Send /> {humanize(transition)}
                            </DropdownMenuItem>
                        ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-4xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'details'
                                ? item.title
                                : humanize(surface ?? '')}
                        </SheetTitle>
                        <SheetDescription>
                            {item.reference} · {item.summary}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-8">
                        {surface === 'details' ? (
                            <ItemDetails
                                item={item}
                                teamSlug={teamSlug}
                                capabilities={capabilities}
                            />
                        ) : surface ? (
                            <Form
                                action={transitionItem({
                                    current_team: teamSlug,
                                    item: item.id,
                                })}
                                className="grid gap-4 pt-4"
                            >
                                <input
                                    type="hidden"
                                    name="transition"
                                    value={surface}
                                />
                                <TextField
                                    name="rationale"
                                    label="Decision rationale"
                                />
                                <Button type="submit">
                                    {humanize(surface)}
                                </Button>
                            </Form>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ItemDetails({
    item,
    teamSlug,
    capabilities,
}: {
    item: KnowledgeItem;
    teamSlug: string;
    capabilities: Props['capabilities'];
}) {
    return (
        <div className="grid gap-6 pt-4">
            <div className="flex flex-wrap items-center gap-2">
                {item.county && <CountyIdentity county={item.county} />}
                <Badge>{humanize(item.status)}</Badge>
                <Badge variant="outline">{humanize(item.type)}</Badge>
                {item.tags.map((tag) => (
                    <Badge key={tag} variant="secondary">
                        {tag}
                    </Badge>
                ))}
            </div>
            <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                <p className="font-semibold">Reference-data lineage</p>
                {item.referenceData ? (
                    <p className="mt-1 break-all text-muted-foreground">
                        Release v{item.referenceData.version} ·{' '}
                        {item.referenceData.checksum}
                    </p>
                ) : (
                    <p className="mt-1 text-muted-foreground">
                        Legacy record · unpinned
                    </p>
                )}
            </div>
            {item.content && (
                <p className="text-sm leading-7 whitespace-pre-wrap">
                    {item.content}
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                {item.document && (
                    <Button asChild variant="outline">
                        <a
                            href={previewEvidence.url({
                                current_team: teamSlug,
                                document: item.document.id,
                            })}
                            target="_blank"
                        >
                            <FileText /> Preview {item.document.title}
                        </a>
                    </Button>
                )}
                {item.externalUrl && (
                    <Button asChild variant="outline">
                        <a
                            href={item.externalUrl}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Eye /> Open source
                        </a>
                    </Button>
                )}
            </div>
            {item.courses.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <BookOpen className="size-4" /> Connected learning
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {item.courses.map((course) => (
                            <p key={course.id} className="text-sm">
                                <strong>{course.code}</strong> · {course.title}
                            </p>
                        ))}
                    </CardContent>
                </Card>
            )}
            {item.discussions.map((discussion) => (
                <Card key={discussion.id}>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-4">
                            <CardTitle className="text-base">
                                {discussion.title}
                            </CardTitle>
                            <DiscussionSubscription
                                teamSlug={teamSlug}
                                discussion={discussion}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {discussion.prompt}
                        </p>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {discussion.posts.map((post) => (
                            <div
                                key={post.id}
                                className="flex items-start justify-between gap-3 rounded-lg bg-muted/50 p-3"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-sm">{post.body}</p>
                                        {post.moderationStatus === 'hidden' && (
                                            <Badge variant="destructive">
                                                Hidden
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {post.author} ·{' '}
                                        {new Date(post.postedAt).toLocaleString(
                                            DEFAULT_LOCALE,
                                        )}
                                    </p>
                                    {post.moderationReason && (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            Moderation: {post.moderationReason}
                                            {post.moderator
                                                ? ` · ${post.moderator}`
                                                : ''}
                                        </p>
                                    )}
                                </div>
                                {(post.canReport || capabilities.curate) && (
                                    <PostActions
                                        teamSlug={teamSlug}
                                        post={post}
                                        canModerate={capabilities.curate}
                                    />
                                )}
                            </div>
                        ))}
                        <PostForm teamSlug={teamSlug} discussion={discussion} />
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function DiscussionSubscription({
    teamSlug,
    discussion,
}: {
    teamSlug: string;
    discussion: Discussion;
}) {
    return (
        <FormSheet
            title="Discussion notifications"
            description={`Choose whether new contributions to ${discussion.title} appear in your notification centre.`}
            triggerLabel={discussion.subscribed ? 'Following' : 'Follow'}
            icon={discussion.subscribed ? BellOff : Bell}
        >
            <Form
                action={updateDiscussionSubscription({
                    current_team: teamSlug,
                    discussion: discussion.id,
                })}
                className="grid gap-4 pt-4"
            >
                {({ processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="subscribed"
                            value={discussion.subscribed ? '0' : '1'}
                        />
                        <p className="text-sm text-muted-foreground">
                            {discussion.subscribed
                                ? 'Stop receiving notifications for new contributions. Existing notifications remain in your notification centre.'
                                : 'Receive an in-app notification whenever another participant adds a visible contribution.'}
                        </p>
                        <Button type="submit" disabled={processing}>
                            {discussion.subscribed ? (
                                <BellOff data-icon="inline-start" />
                            ) : (
                                <Bell data-icon="inline-start" />
                            )}
                            {discussion.subscribed
                                ? 'Stop following'
                                : 'Follow discussion'}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function PostActions({
    teamSlug,
    post,
    canModerate,
}: {
    teamSlug: string;
    post: Discussion['posts'][number];
    canModerate: boolean;
}) {
    const [surface, setSurface] = useState<'report' | 'moderate' | null>(null);
    const nextStatus =
        post.moderationStatus === 'visible' ? 'hidden' : 'visible';

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for contribution by ${post.author}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {post.canReport && (
                        <DropdownMenuItem onSelect={() => setSurface('report')}>
                            <Flag /> Report contribution
                        </DropdownMenuItem>
                    )}
                    {canModerate && (
                        <DropdownMenuItem
                            onSelect={() => setSurface('moderate')}
                        >
                            <ShieldCheck />
                            {nextStatus === 'hidden'
                                ? 'Hide contribution'
                                : 'Restore contribution'}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'report'
                                ? 'Report contribution'
                                : nextStatus === 'hidden'
                                  ? 'Hide contribution'
                                  : 'Restore contribution'}
                        </SheetTitle>
                        <SheetDescription>
                            {surface === 'report'
                                ? 'Submit a traceable report for independent triage and SLA-controlled resolution.'
                                : 'Record an attributable moderation decision. The original contribution remains preserved for audit.'}
                        </SheetDescription>
                    </SheetHeader>
                    {surface === 'report' ? (
                        <Form
                            action={storeCommunityReport({
                                current_team: teamSlug,
                                post: post.id,
                            })}
                            className="grid gap-4 px-4 pt-4 pb-8"
                            onSuccess={() => setSurface(null)}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <SearchableSelect
                                        id={`report-category-${post.id}`}
                                        name="category"
                                        label="Report category"
                                        options={[
                                            'misinformation',
                                            'harassment',
                                            'privacy',
                                            'security',
                                            'spam',
                                            'other',
                                        ].map(option)}
                                    />
                                    <SearchableSelect
                                        id={`report-severity-${post.id}`}
                                        name="severity"
                                        label="Potential severity"
                                        options={[
                                            'low',
                                            'medium',
                                            'high',
                                            'critical',
                                        ].map(option)}
                                        defaultValue="medium"
                                    />
                                    <TextField
                                        name="description"
                                        label="What should the moderation team review?"
                                        error={errors.description}
                                    />
                                    <Button type="submit" disabled={processing}>
                                        <Flag data-icon="inline-start" />
                                        Submit report
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : surface === 'moderate' ? (
                        <Form
                            action={moderatePost({
                                current_team: teamSlug,
                                post: post.id,
                            })}
                            className="grid gap-4 px-4 pt-4 pb-8"
                            onSuccess={() => setSurface(null)}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="moderation_status"
                                        value={nextStatus}
                                    />
                                    <TextField
                                        name="moderation_reason"
                                        label="Moderation rationale"
                                        error={errors.moderation_reason}
                                    />
                                    <Button type="submit" disabled={processing}>
                                        <ShieldCheck data-icon="inline-start" />
                                        Confirm {nextStatus}
                                    </Button>
                                </>
                            )}
                        </Form>
                    ) : null}
                </SheetContent>
            </Sheet>
        </>
    );
}

function PostForm({
    teamSlug,
    discussion,
}: {
    teamSlug: string;
    discussion: Discussion;
}) {
    return (
        <FormSheet
            title={`Contribute to ${discussion.title}`}
            description="Add a traceable contribution to this community of practice."
            triggerLabel="Add contribution"
            icon={MessageSquare}
        >
            <Form
                action={storePost({
                    current_team: teamSlug,
                    discussion: discussion.id,
                })}
                className="grid gap-4 pt-4"
            >
                <TextField name="body" label="Contribution" />
                <Button type="submit">Post contribution</Button>
            </Form>
        </FormSheet>
    );
}

function InnovationActions({
    innovation,
    teamSlug,
    capabilities,
    options,
}: {
    innovation: Innovation;
    teamSlug: string;
    capabilities: Props['capabilities'];
    options: Props['options'];
}) {
    const [surface, setSurface] = useState<string | null>(null);
    const transitions = [
        ['submit', capabilities.contribute && innovation.status === 'draft'],
        [
            'accept_incubation',
            capabilities.curate &&
                innovation.status === 'screening' &&
                innovation.panelSummary.ready,
        ],
        ['reject', capabilities.curate && innovation.status === 'screening'],
        [
            'start_pilot',
            capabilities.manage &&
                innovation.status === 'incubating' &&
                innovation.fundingReady &&
                innovation.milestones.length > 0,
        ],
        [
            'scale',
            capabilities.curate &&
                innovation.status === 'piloting' &&
                innovation.pilotVerified,
        ],
    ] as const;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${innovation.title}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setSurface('details')}>
                        <Eye /> Open innovation
                    </DropdownMenuItem>
                    {capabilities.curate &&
                        innovation.status === 'screening' && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('panel')}
                            >
                                <ShieldCheck /> Record panel review
                            </DropdownMenuItem>
                        )}
                    {capabilities.manage &&
                        innovation.status === 'incubating' && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('funding')}
                            >
                                <FileText /> Record funding decision
                            </DropdownMenuItem>
                        )}
                    {capabilities.manage &&
                        innovation.status === 'incubating' && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('milestone')}
                            >
                                <Plus /> Define experiment milestone
                            </DropdownMenuItem>
                        )}
                    {transitions
                        .filter(([, visible]) => visible)
                        .map(([transition]) => (
                            <DropdownMenuItem
                                key={transition}
                                onSelect={() => setSurface(transition)}
                            >
                                <Lightbulb /> {humanize(transition)}
                            </DropdownMenuItem>
                        ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-3xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'details'
                                ? innovation.title
                                : humanize(surface ?? '')}
                        </SheetTitle>
                        <SheetDescription>
                            {innovation.reference} ·{' '}
                            {humanize(innovation.stage)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <div className="flex flex-wrap gap-2">
                                    {innovation.county && (
                                        <CountyIdentity
                                            county={innovation.county}
                                        />
                                    )}
                                    <Badge>{humanize(innovation.status)}</Badge>
                                    <Badge variant="outline">
                                        {humanize(innovation.maturity)}
                                    </Badge>
                                </div>
                                <Detail
                                    label="Problem"
                                    value={innovation.problem}
                                />
                                <Detail
                                    label="Reference-data lineage"
                                    value={
                                        innovation.referenceData
                                            ? `Release v${innovation.referenceData.version} · ${innovation.referenceData.checksum}`
                                            : 'Legacy record · unpinned'
                                    }
                                />
                                <Detail
                                    label="Proposed solution"
                                    value={innovation.solution}
                                />
                                <Detail
                                    label="Expected impact"
                                    value={innovation.impact}
                                />
                                <Detail
                                    label="Incubation support"
                                    value={
                                        innovation.incubationSupport ??
                                        'Not yet defined'
                                    }
                                />
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Panel assurance
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-3 text-sm">
                                        <p>
                                            {innovation.panelSummary.count}{' '}
                                            reviews · average{' '}
                                            {innovation.panelSummary.average ??
                                                'Pending'}{' '}
                                            ·{' '}
                                            {
                                                innovation.panelSummary
                                                    .advanceCount
                                            }{' '}
                                            advance recommendations
                                        </p>
                                        {innovation.panelReviews.map(
                                            (review) => (
                                                <div
                                                    key={review.id}
                                                    className="rounded-lg border p-3"
                                                >
                                                    <div className="flex justify-between gap-3">
                                                        <strong>
                                                            {review.reviewer}
                                                        </strong>
                                                        <Badge variant="outline">
                                                            {
                                                                review.weightedScore
                                                            }{' '}
                                                            ·{' '}
                                                            {humanize(
                                                                review.recommendation,
                                                            )}
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-2 text-muted-foreground">
                                                        {review.rationale}
                                                    </p>
                                                    <p className="mt-2 font-mono text-xs break-all text-muted-foreground">
                                                        {review.rubricCode} ·{' '}
                                                        {
                                                            review.evidenceChecksum
                                                        }
                                                    </p>
                                                </div>
                                            ),
                                        )}
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Funding decision chain
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-3">
                                        {innovation.fundingDecisions.length ? (
                                            innovation.fundingDecisions.map(
                                                (decision) => (
                                                    <div
                                                        key={decision.id}
                                                        className="rounded-lg border p-3 text-sm"
                                                    >
                                                        <div className="flex justify-between">
                                                            <strong>
                                                                Version{' '}
                                                                {
                                                                    decision.version
                                                                }{' '}
                                                                ·{' '}
                                                                {humanize(
                                                                    decision.decision,
                                                                )}
                                                            </strong>
                                                            <span>
                                                                {
                                                                    decision.currency
                                                                }{' '}
                                                                {decision.amount.toLocaleString(
                                                                    DEFAULT_LOCALE,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="mt-2 text-muted-foreground">
                                                            {decision.reference}{' '}
                                                            ·{' '}
                                                            {
                                                                decision.decisionMaker
                                                            }
                                                        </p>
                                                        <p className="mt-2 font-mono text-xs break-all text-muted-foreground">
                                                            {
                                                                decision.evidenceChecksum
                                                            }
                                                        </p>
                                                    </div>
                                                ),
                                            )
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                No funding decision recorded.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Experiment milestones
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-3">
                                        {innovation.milestones.length ? (
                                            innovation.milestones.map(
                                                (milestone) => (
                                                    <MilestoneCard
                                                        key={milestone.id}
                                                        innovation={innovation}
                                                        milestone={milestone}
                                                        teamSlug={teamSlug}
                                                        capabilities={
                                                            capabilities
                                                        }
                                                        options={options}
                                                    />
                                                ),
                                            )
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                No experiment milestones
                                                defined.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            </>
                        ) : surface === 'panel' ? (
                            <Form
                                action={storePanelReview({
                                    current_team: teamSlug,
                                    innovation: innovation.id,
                                })}
                                className="grid gap-4"
                                onSuccess={() => setSurface(null)}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            {[
                                                [
                                                    'strategic_fit_score',
                                                    'Strategic fit',
                                                ],
                                                [
                                                    'feasibility_score',
                                                    'Feasibility',
                                                ],
                                                [
                                                    'inclusion_score',
                                                    'Inclusion',
                                                ],
                                                [
                                                    'evidence_score',
                                                    'Evidence quality',
                                                ],
                                            ].map(([name, label]) => (
                                                <Field
                                                    key={name}
                                                    name={name}
                                                    label={`${label} (0–100)`}
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    error={errors[name]}
                                                />
                                            ))}
                                        </div>
                                        <SearchableSelect
                                            id={`panel-recommendation-${innovation.id}`}
                                            name="recommendation"
                                            label="Recommendation"
                                            options={[
                                                'advance',
                                                'revise',
                                                'reject',
                                            ].map(option)}
                                        />
                                        <TextField
                                            name="rationale"
                                            label="Independent assessment rationale"
                                            error={errors.rationale}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save immutable review
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : surface === 'funding' ? (
                            <Form
                                action={storeFundingDecision({
                                    current_team: teamSlug,
                                    innovation: innovation.id,
                                })}
                                className="grid gap-4"
                                onSuccess={() => setSurface(null)}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <SearchableSelect
                                            id={`funding-decision-${innovation.id}`}
                                            name="decision"
                                            label="Decision"
                                            options={[
                                                'approved',
                                                'declined',
                                                'not_required',
                                            ].map(option)}
                                        />
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Field
                                                name="amount"
                                                label="Amount"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                error={errors.amount}
                                            />
                                            <ReferenceCatalogSelect
                                                id={`innovation-currency-${innovation.id}`}
                                                name="currency"
                                                label="Currency"
                                                catalog="currency"
                                                error={errors.currency}
                                            />
                                            <SearchableSelect
                                                id={`funding-type-${innovation.id}`}
                                                name="funding_type"
                                                label="Funding type"
                                                options={[
                                                    'grant',
                                                    'in_kind',
                                                    'blended',
                                                    'not_applicable',
                                                ].map(option)}
                                            />
                                            <Field
                                                name="decision_reference"
                                                label="Decision reference"
                                                error={
                                                    errors.decision_reference
                                                }
                                            />
                                        </div>
                                        <TextField
                                            name="rationale"
                                            label="Funding rationale"
                                            error={errors.rationale}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Append funding decision
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : surface === 'milestone' ? (
                            <Form
                                action={storeMilestone({
                                    current_team: teamSlug,
                                    innovation: innovation.id,
                                })}
                                className="grid gap-4"
                                onSuccess={() => setSurface(null)}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <SearchableSelect
                                            id={`milestone-owner-${innovation.id}`}
                                            name="owner_id"
                                            label="Accountable owner"
                                            options={options.milestoneOwners.map(
                                                toNamed,
                                            )}
                                        />
                                        <Field
                                            name="title"
                                            label="Milestone title"
                                            error={errors.title}
                                        />
                                        <TextField
                                            name="hypothesis"
                                            label="Testable hypothesis"
                                            error={errors.hypothesis}
                                        />
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <Field
                                                name="success_metric"
                                                label="Success metric"
                                                error={errors.success_metric}
                                            />
                                            <Field
                                                name="baseline_value"
                                                label="Baseline"
                                                error={errors.baseline_value}
                                            />
                                            <Field
                                                name="target_value"
                                                label="Target"
                                                error={errors.target_value}
                                            />
                                        </div>
                                        <DatePickerField
                                            name="due_at"
                                            label="Due date"
                                            error={errors.due_at}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create milestone
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : surface ? (
                            <Form
                                action={transitionInnovation({
                                    current_team: teamSlug,
                                    innovation: innovation.id,
                                })}
                                className="grid gap-4"
                            >
                                <input
                                    type="hidden"
                                    name="transition"
                                    value={surface}
                                />
                                <TextField
                                    name="rationale"
                                    label="Decision rationale"
                                />
                                <TextField
                                    name="incubation_support"
                                    label="Incubation support and next steps"
                                    optional
                                />
                                <Field
                                    name="evidence_reference"
                                    label="Pilot evidence reference"
                                    optional
                                />
                                <Button type="submit">
                                    {humanize(surface)}
                                </Button>
                            </Form>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function MilestoneCard({
    innovation,
    milestone,
    teamSlug,
    capabilities,
    options,
}: {
    innovation: Innovation;
    milestone: Innovation['milestones'][number];
    teamSlug: string;
    capabilities: Props['capabilities'];
    options: Props['options'];
}) {
    const [surface, setSurface] = useState<'update' | 'verify' | null>(null);
    const canUpdate =
        capabilities.manage &&
        innovation.status === 'piloting' &&
        ['planned', 'in_progress'].includes(milestone.status);
    const canVerify =
        capabilities.curate &&
        innovation.status === 'piloting' &&
        ['completed', 'failed'].includes(milestone.status) &&
        milestone.verificationDecision === 'pending';

    return (
        <div className="rounded-lg border p-3 text-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <strong>{milestone.title}</strong>
                    <p className="text-muted-foreground">
                        {milestone.owner} · due {milestone.dueAt ?? 'Not set'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant="outline">
                        {humanize(milestone.status)}
                    </Badge>
                    {(canUpdate || canVerify) && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Actions for ${milestone.title}`}
                                >
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {canUpdate && (
                                    <DropdownMenuItem
                                        onSelect={() => setSurface('update')}
                                    >
                                        <FileText /> Update result
                                    </DropdownMenuItem>
                                )}
                                {canVerify && (
                                    <DropdownMenuItem
                                        onSelect={() => setSurface('verify')}
                                    >
                                        <ShieldCheck /> Verify independently
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>
            <p className="mt-2">{milestone.hypothesis}</p>
            <p className="mt-2 text-muted-foreground">
                {milestone.successMetric}: {milestone.baselineValue} →{' '}
                {milestone.targetValue}
                {milestone.actualValue
                    ? ` · actual ${milestone.actualValue}`
                    : ''}
            </p>
            {milestone.outcomeSummary && (
                <p className="mt-2 text-muted-foreground">
                    {milestone.outcomeSummary}
                </p>
            )}
            <div className="mt-3 flex items-center justify-between gap-3">
                <span>
                    Verification: {humanize(milestone.verificationDecision)}
                </span>
                {milestone.document && (
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={previewEvidence.url({
                                current_team: teamSlug,
                                document: milestone.document.id,
                            })}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Eye /> Preview evidence
                        </a>
                    </Button>
                )}
            </div>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'verify'
                                ? 'Verify experiment result'
                                : 'Update experiment result'}
                        </SheetTitle>
                        <SheetDescription>
                            {milestone.title} · {innovation.reference}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pt-4 pb-8">
                        {surface === 'update' ? (
                            <Form
                                action={updateMilestone({
                                    current_team: teamSlug,
                                    innovation: innovation.id,
                                    milestone: milestone.id,
                                })}
                                className="grid gap-4"
                                onSuccess={() => setSurface(null)}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <SearchableSelect
                                            id={`milestone-status-${milestone.id}`}
                                            name="status"
                                            label="Result status"
                                            options={(milestone.status ===
                                            'planned'
                                                ? ['in_progress']
                                                : ['completed', 'failed']
                                            ).map(option)}
                                        />
                                        <Field
                                            name="actual_value"
                                            label="Actual value"
                                            optional={
                                                milestone.status === 'planned'
                                            }
                                            error={errors.actual_value}
                                        />
                                        <TextField
                                            name="outcome_summary"
                                            label="Outcome summary"
                                            optional={
                                                milestone.status === 'planned'
                                            }
                                            error={errors.outcome_summary}
                                        />
                                        <SearchableSelect
                                            id={`milestone-document-${milestone.id}`}
                                            name="assessment_document_id"
                                            label="Clean repository evidence"
                                            options={options.documents.map(
                                                toNamed,
                                            )}
                                            optional={
                                                milestone.status === 'planned'
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save result
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : surface === 'verify' ? (
                            <Form
                                action={verifyMilestone({
                                    current_team: teamSlug,
                                    innovation: innovation.id,
                                    milestone: milestone.id,
                                })}
                                className="grid gap-4"
                                onSuccess={() => setSurface(null)}
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <SearchableSelect
                                            id={`milestone-verification-${milestone.id}`}
                                            name="verification_decision"
                                            label="Verification decision"
                                            options={[
                                                'verified',
                                                'rejected',
                                            ].map(option)}
                                        />
                                        <TextField
                                            name="verification_rationale"
                                            label="Independent verification rationale"
                                            error={
                                                errors.verification_rationale
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Record verification
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    );
}

function Field({
    name,
    label,
    defaultValue,
    optional = false,
    error,
    type = 'text',
    min,
    max,
    step,
}: {
    name: string;
    label: string;
    defaultValue?: string;
    optional?: boolean;
    error?: string;
    type?: string;
    min?: string;
    max?: string;
    step?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                defaultValue={defaultValue}
                required={!optional}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${name}-error` : undefined}
                type={type}
                min={min}
                max={max}
                step={step}
            />
            {error && (
                <p
                    id={`${name}-error`}
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
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Textarea
                id={name}
                name={name}
                rows={4}
                required={!optional}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${name}-error` : undefined}
            />
            {error && (
                <p
                    id={`${name}-error`}
                    role="alert"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
function Detail({ label, value }: { label: string; value: string }) {
    return (
        <section>
            <h3 className="text-sm font-semibold">{label}</h3>
            <p className="mt-1 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                {value}
            </p>
        </section>
    );
}
function option(id: string) {
    return { id, name: humanize(id) };
}
function toNamed(value: Option) {
    return { id: value.id, name: value.name ?? value.label ?? value.id };
}
function humanize(value: string) {
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}
function page<T>(records: PageSet<T>): WorkspacePagination {
    return {
        currentPage: records.current_page,
        lastPage: records.last_page,
        perPage: records.per_page,
        total: records.total,
    };
}
