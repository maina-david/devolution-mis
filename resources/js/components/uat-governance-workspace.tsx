import { Form } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    Download,
    Eye,
    MoreHorizontal,
    PlayCircle,
    Plus,
    Send,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import SearchableMultiSelect from '@/components/searchable-multi-select';
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
import { update as decideAcceptance } from '@/routes/change-readiness/uat/acceptances';
import {
    store as storeCampaign,
    submit as submitCampaign,
} from '@/routes/change-readiness/uat/campaigns';
import { store as storeExecution } from '@/routes/change-readiness/uat/executions';
import { update as updateFinding } from '@/routes/change-readiness/uat/findings';
import { store as storeScenario } from '@/routes/change-readiness/uat/scenarios';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string; logoUrl?: string | null };
type County = Option & { code: number };
type ReferenceData = {
    version: number;
    effectiveFrom: string | null;
    checksum: string;
};
type Finding = {
    id: string;
    severity: string;
    title: string;
    description: string;
    status: string;
    dueOn: string;
    resolution: string | null;
    owner: string;
    resolver: string | null;
    verifier: string | null;
};
type Execution = {
    id: string;
    county: County | null;
    tester: string;
    environment: string;
    outcome: string;
    actualResult: string;
    evidenceReferences: string[];
    startedAt: string;
    completedAt: string;
    checksum: string;
    findings: Finding[];
};
type Scenario = {
    id: string;
    code: string;
    module: string;
    title: string;
    actorRole: string;
    priority: string;
    journey: string;
    preconditions: string[];
    steps: string[];
    expectedResult: string;
    accessibilityNeeds: string | null;
    lowConnectivityVariant: string | null;
    required: boolean;
    status: string;
    executions: Execution[];
};
type Acceptance = {
    id: string;
    decision: string;
    checksum: string;
    decisionReason: string | null;
    submitter: string;
    decisionMaker: string | null;
    submittedAt: string;
    decidedAt: string | null;
};
export type UatCampaign = {
    id: string;
    code: string;
    name: string;
    objective: string;
    environment: string;
    startsOn: string;
    endsOn: string;
    status: string;
    acceptanceCriteria: string[];
    requiredRoles: string[];
    minimumCounties: number;
    creator: string;
    referenceData: ReferenceData;
    counties: Array<County & { participationStatus: string }>;
    scenarios: Scenario[];
    acceptances: Acceptance[];
};
export type UatPageSet = {
    data: UatCampaign[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    campaigns: UatPageSet;
    filters: Record<string, string | undefined>;
    counties: County[];
    users: Option[];
    roles: Array<{ value: string; label: string }>;
    capabilities: { manage: boolean; record: boolean; approve: boolean };
    catalogueAvailable: boolean;
    copy: Record<string, string>;
};

const modules = [
    'citizen-feedback',
    'e-learning',
    'partner-coordination',
    'dswg-coordination',
    'project-management',
    'departmental-performance',
    'monitoring-evaluation',
    'grievance-redress',
    'central-repository',
    'analytics-reporting',
    'igr-resolutions',
    'devolution-assessment',
    'travel-clearance',
    'knowledge-management',
    'shared-platform',
];

export default function UatGovernanceWorkspace({
    campaigns,
    filters,
    counties,
    users,
    roles,
    capabilities,
    catalogueAvailable,
    copy,
}: Props) {
    const scenarios = campaigns.data.flatMap((campaign) => campaign.scenarios);
    const executions = scenarios.flatMap((scenario) => scenario.executions);
    const openFindings = executions
        .flatMap((execution) => execution.findings)
        .filter((finding) => finding.status !== 'verified').length;
    const rows: WorkspaceRow[] = campaigns.data.map((campaign) => ({
        id: campaign.id,
        status: campaign.status,
        cells: [
            `${campaign.code} · ${campaign.name}`,
            campaign.environment,
            campaign.counties.length,
            campaign.scenarios.length,
            campaign.scenarios.flatMap((scenario) => scenario.executions)
                .length,
            campaign.acceptances[0]?.decision ?? copy.not_submitted,
            campaign.status,
        ],
    }));
    const pagination: WorkspacePagination = {
        currentPage: campaigns.current_page,
        lastPage: campaigns.last_page,
        perPage: campaigns.per_page,
        total: campaigns.total,
        pageName: 'uat_page',
        perPageName: 'uat_per_page',
    };

    return (
        <section aria-labelledby="pilot-uat-heading" className="grid gap-5">
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div className="max-w-3xl">
                    <p className="text-xs font-bold tracking-[0.16em] text-muted-foreground uppercase">
                        {copy.eyebrow}
                    </p>
                    <h2
                        id="pilot-uat-heading"
                        className="mt-2 text-2xl font-bold"
                    >
                        {copy.title}
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {copy.description}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline">
                                <Download aria-hidden="true" />{' '}
                                {copy.export_evidence}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                                <DropdownMenuItem key={format} asChild>
                                    <a
                                        href={exportMethod.url(
                                            {
                                                workspace: 'uat-campaigns',
                                                format,
                                            },
                                            {
                                                query: {
                                                    from: filters.uat_from,
                                                    to: filters.uat_to,
                                                    search: filters.uat_search,
                                                    status: filters.uat_status,
                                                    county_id:
                                                        filters.uat_county_id,
                                                },
                                            },
                                        )}
                                    >
                                        {format.toUpperCase()}
                                    </a>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                    {capabilities.manage && (
                        <CampaignForm
                            counties={counties}
                            roles={roles}
                            disabled={!catalogueAvailable}
                            copy={copy}
                        />
                    )}
                </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Metric label={copy.campaigns} value={campaigns.total} />
                <Metric label={copy.scenarios} value={scenarios.length} />
                <Metric label={copy.executions} value={executions.length} />
                <Metric label={copy.open_findings} value={openFindings} />
            </div>
            <DateRangeFilter
                initialFrom={filters.uat_from}
                initialTo={filters.uat_to}
                initialSearch={filters.uat_search}
                fromKey="uat_from"
                toKey="uat_to"
                searchKey="uat_search"
                perPageKey="uat_per_page"
                searchPlaceholder={copy.search}
                selectFilters={[
                    {
                        key: 'uat_status',
                        label: copy.status,
                        options: [
                            'planning',
                            'executing',
                            'review',
                            'accepted',
                            'rejected',
                        ].map(option),
                        value: filters.uat_status,
                    },
                    {
                        key: 'uat_county_id',
                        label: copy.county,
                        options: counties,
                        value: filters.uat_county_id,
                    },
                ]}
            />
            <div className="overflow-hidden rounded-xl border bg-card">
                {rows.length ? (
                    <WorkspaceDataTable
                        columns={[
                            copy.campaign,
                            copy.environment,
                            copy.counties,
                            copy.scenarios,
                            copy.executions,
                            copy.acceptance,
                            copy.status,
                        ]}
                        rows={rows}
                        pagination={pagination}
                        renderActionControl={(row) => {
                            const campaign = campaigns.data.find(
                                (candidate) => candidate.id === row.id,
                            );

                            return campaign ? (
                                <CampaignActions
                                    campaign={campaign}
                                    counties={counties}
                                    users={users}
                                    roles={roles}
                                    capabilities={capabilities}
                                    copy={copy}
                                />
                            ) : null;
                        }}
                    />
                ) : (
                    <WorkspaceEmptyState
                        title={copy.empty_title}
                        description={copy.empty_description}
                        className="min-h-64 border-0"
                    />
                )}
            </div>
        </section>
    );
}

function CampaignActions({
    campaign,
    users,
    roles,
    capabilities,
    copy,
}: Omit<Props, 'campaigns' | 'filters' | 'catalogueAvailable'> & {
    campaign: UatCampaign;
}) {
    const [open, setOpen] = useState(false);
    const pendingAcceptance = campaign.acceptances.find(
        (acceptance) => acceptance.decision === 'pending',
    );

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={copy.open_actions}
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setOpen(true)}>
                        <Eye aria-hidden="true" /> {copy.open_record}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-4xl">
                    <SheetHeader>
                        <SheetTitle>
                            {campaign.code} · {campaign.name}
                        </SheetTitle>
                        <SheetDescription>
                            {campaign.objective}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 px-4 pb-8">
                        <div className="flex flex-wrap gap-2">
                            <Badge>{humanize(campaign.status)}</Badge>
                            <Badge variant="outline">
                                {campaign.environment}
                            </Badge>
                            <Badge variant="outline">
                                {copy.catalogue}{' '}
                                {campaign.referenceData.version} ·{' '}
                                {campaign.referenceData.checksum.slice(0, 12)}…
                            </Badge>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Detail
                                label={copy.period}
                                value={`${formatDate(campaign.startsOn)} – ${formatDate(campaign.endsOn)}`}
                            />
                            <Detail
                                label={copy.creator}
                                value={campaign.creator}
                            />
                            <Detail
                                label={copy.required_roles}
                                value={campaign.requiredRoles
                                    .map(humanize)
                                    .join(', ')}
                            />
                            <Detail
                                label={copy.counties}
                                value={campaign.counties
                                    .map((county) => county.name)
                                    .join(', ')}
                            />
                        </div>
                        <div>
                            <h3 className="font-semibold">
                                {copy.acceptance_criteria}
                            </h3>
                            <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                {campaign.acceptanceCriteria.map(
                                    (criterion) => (
                                        <li key={criterion}>{criterion}</li>
                                    ),
                                )}
                            </ul>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {capabilities.manage &&
                                campaign.status === 'planning' && (
                                    <ScenarioForm
                                        campaign={campaign}
                                        roles={roles}
                                        copy={copy}
                                    />
                                )}
                            {capabilities.manage &&
                                ['executing', 'rejected'].includes(
                                    campaign.status,
                                ) && (
                                    <SubmitForm
                                        campaign={campaign}
                                        copy={copy}
                                    />
                                )}
                            {capabilities.approve && pendingAcceptance && (
                                <DecisionForm
                                    acceptance={pendingAcceptance}
                                    copy={copy}
                                />
                            )}
                        </div>
                        <div className="grid gap-4">
                            {campaign.scenarios.map((scenario) => (
                                <ScenarioCard
                                    key={scenario.id}
                                    scenario={scenario}
                                    campaign={campaign}
                                    users={users}
                                    capabilities={capabilities}
                                    copy={copy}
                                />
                            ))}
                            {!campaign.scenarios.length && (
                                <WorkspaceEmptyState
                                    title={copy.no_scenarios}
                                    description={copy.no_scenarios_description}
                                />
                            )}
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ScenarioCard({
    scenario,
    campaign,
    users,
    capabilities,
    copy,
}: {
    scenario: Scenario;
    campaign: UatCampaign;
    users: Option[];
    capabilities: Props['capabilities'];
    copy: Record<string, string>;
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle className="text-base">
                            {scenario.code} · {scenario.title}
                        </CardTitle>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {humanize(scenario.module)} ·{' '}
                            {humanize(scenario.actorRole)}
                        </p>
                    </div>
                    <Badge variant="outline">
                        {humanize(scenario.priority)}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="grid gap-3">
                <p className="text-sm text-muted-foreground">
                    {scenario.journey}
                </p>
                <div className="flex flex-wrap gap-2">
                    {capabilities.record &&
                        ['planning', 'executing', 'rejected'].includes(campaign.status) && (
                            <ExecutionForm
                                scenario={scenario}
                                counties={campaign.counties}
                                users={users}
                                copy={copy}
                            />
                        )}
                    <Badge variant="secondary">
                        {scenario.executions.length}{' '}
                        {copy.executions.toLocaleLowerCase()}
                    </Badge>
                </div>
                {scenario.executions.map((execution) => (
                    <div
                        key={execution.id}
                        className="grid gap-2 rounded-lg border p-3 text-sm"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="font-medium">
                                {execution.county?.name ?? copy.national} ·{' '}
                                {execution.tester}
                            </span>
                            <Badge
                                variant={
                                    execution.outcome === 'pass'
                                        ? 'default'
                                        : 'destructive'
                                }
                            >
                                {humanize(execution.outcome)}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground">
                            {execution.actualResult}
                        </p>
                        <p className="font-mono text-xs text-muted-foreground">
                            SHA-256 {execution.checksum}
                        </p>
                        {execution.findings.map((finding) => (
                            <div
                                key={finding.id}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted p-3"
                            >
                                <span>
                                    <strong>{finding.title}</strong> ·{' '}
                                    {humanize(finding.status)} · {finding.owner}
                                </span>
                                {(capabilities.record ||
                                    capabilities.approve) && (
                                    <FindingForm
                                        finding={finding}
                                        canResolve={capabilities.record}
                                        canReview={capabilities.approve}
                                        copy={copy}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function CampaignForm({
    counties,
    roles,
    disabled,
    copy,
}: {
    counties: County[];
    roles: Props['roles'];
    disabled: boolean;
    copy: Record<string, string>;
}) {
    return (
        <FormSheet
            title={copy.new_campaign}
            description={copy.new_campaign_description}
            triggerLabel={copy.new_campaign}
            icon={Plus}
            size="xl"
            triggerDisabled={disabled}
            triggerTitle={disabled ? copy.catalogue_required : undefined}
        >
            <Form action={storeCampaign()} className="grid gap-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="code"
                                label={copy.code}
                                error={errors.code}
                            />
                            <Field
                                name="name"
                                label={copy.name}
                                error={errors.name}
                            />
                        </div>
                        <TextField
                            name="objective"
                            label={copy.objective}
                            error={errors.objective}
                        />
                        <Field
                            name="environment"
                            label={copy.environment}
                            defaultValue="government-hosting-uat"
                            error={errors.environment}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="starts_on"
                                label={copy.starts_on}
                                required
                            />
                            <DatePickerField
                                name="ends_on"
                                label={copy.ends_on}
                                required
                            />
                        </div>
                        <SearchableMultiSelect
                            name="county_ids"
                            label={copy.counties}
                            options={counties}
                            error={errors.county_ids}
                        />
                        <SearchableMultiSelect
                            name="required_roles"
                            label={copy.required_roles}
                            options={roles.map((role) => ({
                                id: role.value,
                                name: role.label,
                            }))}
                            error={errors.required_roles}
                        />
                        <Field
                            name="minimum_counties"
                            label={copy.minimum_counties}
                            type="number"
                            defaultValue="1"
                            error={errors.minimum_counties}
                        />
                        {[0, 1, 2].map((index) => (
                            <Field
                                key={index}
                                name="acceptance_criteria[]"
                                label={`${copy.acceptance_criterion} ${index + 1}`}
                                error={errors[`acceptance_criteria.${index}`]}
                            />
                        ))}
                        <Button type="submit" disabled={processing}>
                            <ClipboardCheck aria-hidden="true" />{' '}
                            {copy.save_plan}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ScenarioForm({
    campaign,
    roles,
    copy,
}: {
    campaign: UatCampaign;
    roles: Props['roles'];
    copy: Record<string, string>;
}) {
    return (
        <FormSheet
            title={copy.add_scenario}
            description={copy.add_scenario_description}
            triggerLabel={copy.add_scenario}
            icon={Plus}
            size="xl"
        >
            <Form
                action={storeScenario({ campaign: campaign.id })}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="code"
                                label={copy.code}
                                error={errors.code}
                            />
                            <Field
                                name="title"
                                label={copy.title_label}
                                error={errors.title}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <SearchableSelect
                                id={`module-${campaign.id}`}
                                name="module"
                                label={copy.module}
                                options={modules.map(option)}
                                error={errors.module}
                            />
                            <SearchableSelect
                                id={`role-${campaign.id}`}
                                name="actor_role"
                                label={copy.actor_role}
                                options={roles.map((role) => ({
                                    id: role.value,
                                    name: role.label,
                                }))}
                                error={errors.actor_role}
                            />
                            <SearchableSelect
                                id={`priority-${campaign.id}`}
                                name="priority"
                                label={copy.priority}
                                options={[
                                    'critical',
                                    'high',
                                    'normal',
                                    'low',
                                ].map(option)}
                                error={errors.priority}
                            />
                        </div>
                        <TextField
                            name="journey"
                            label={copy.journey}
                            error={errors.journey}
                        />
                        {[0, 1].map((index) => (
                            <Field
                                key={index}
                                name="preconditions[]"
                                label={`${copy.precondition} ${index + 1}`}
                                error={errors[`preconditions.${index}`]}
                            />
                        ))}
                        {[0, 1, 2].map((index) => (
                            <Field
                                key={index}
                                name="steps[]"
                                label={`${copy.step} ${index + 1}`}
                                error={errors[`steps.${index}`]}
                            />
                        ))}
                        <TextField
                            name="expected_result"
                            label={copy.expected_result}
                            error={errors.expected_result}
                        />
                        <TextField
                            name="accessibility_needs"
                            label={copy.accessibility_variant}
                            optional
                            error={errors.accessibility_needs}
                        />
                        <TextField
                            name="low_connectivity_variant"
                            label={copy.low_connectivity_variant}
                            optional
                            error={errors.low_connectivity_variant}
                        />
                        <input type="hidden" name="required" value="1" />
                        <Button type="submit" disabled={processing}>
                            <Plus aria-hidden="true" /> {copy.save_scenario}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ExecutionForm({
    scenario,
    counties,
    users,
    copy,
}: {
    scenario: Scenario;
    counties: County[];
    users: Option[];
    copy: Record<string, string>;
}) {
    const [outcome, setOutcome] = useState('pass');

    return (
        <FormSheet
            title={copy.record_execution}
            description={`${copy.representative_role}: ${humanize(scenario.actorRole)}`}
            triggerLabel={copy.record_execution}
            icon={PlayCircle}
            size="xl"
        >
            <Form
                action={storeExecution({ scenario: scenario.id })}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <SearchableSelect
                                id={`execution-county-${scenario.id}`}
                                name="county_id"
                                label={copy.county}
                                options={counties}
                                error={errors.county_id}
                            />
                            <Field
                                name="environment"
                                label={copy.environment}
                                defaultValue="government-hosting-uat"
                                error={errors.environment}
                            />
                            <SearchableSelect
                                id={`execution-outcome-${scenario.id}`}
                                name="outcome"
                                label={copy.outcome}
                                options={['pass', 'fail', 'blocked'].map(
                                    option,
                                )}
                                value={outcome}
                                onValueChange={setOutcome}
                                error={errors.outcome}
                            />
                        </div>
                        <TextField
                            name="actual_result"
                            label={copy.actual_result}
                            error={errors.actual_result}
                        />
                        {[0, 1].map((index) => (
                            <Field
                                key={index}
                                name="evidence_references[]"
                                label={`${copy.evidence_reference} ${index + 1}`}
                                optional={index > 0}
                                error={errors[`evidence_references.${index}`]}
                            />
                        ))}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="started_at"
                                label={copy.started_at}
                                includeTime
                                required
                            />
                            <DatePickerField
                                name="completed_at"
                                label={copy.completed_at}
                                includeTime
                                required
                            />
                        </div>
                        {outcome !== 'pass' && (
                            <div className="grid gap-4 rounded-lg border p-4">
                                <SearchableSelect
                                    id={`finding-owner-${scenario.id}`}
                                    name="finding_owner_id"
                                    label={copy.finding_owner}
                                    options={users}
                                    error={errors.finding_owner_id}
                                />
                                <SearchableSelect
                                    id={`finding-severity-${scenario.id}`}
                                    name="finding_severity"
                                    label={copy.severity}
                                    options={[
                                        'critical',
                                        'high',
                                        'medium',
                                        'low',
                                    ].map(option)}
                                    error={errors.finding_severity}
                                />
                                <Field
                                    name="finding_title"
                                    label={copy.finding_title}
                                    error={errors.finding_title}
                                />
                                <TextField
                                    name="finding_description"
                                    label={copy.finding_description}
                                    error={errors.finding_description}
                                />
                                <DatePickerField
                                    name="finding_due_on"
                                    label={copy.finding_due_on}
                                    required
                                />
                            </div>
                        )}
                        <Button type="submit" disabled={processing}>
                            <CheckCircle2 aria-hidden="true" />{' '}
                            {copy.record_immutable_evidence}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function FindingForm({
    finding,
    canResolve,
    canReview,
    copy,
}: {
    finding: Finding;
    canResolve: boolean;
    canReview: boolean;
    copy: Record<string, string>;
}) {
    const actions = [
        ...(canResolve && ['open', 'reopened'].includes(finding.status)
            ? ['resolve']
            : []),
        ...(canReview && finding.status === 'resolved'
            ? ['verify', 'reopen']
            : []),
        ...(canReview && finding.status === 'verified' ? ['reopen'] : []),
    ];

    if (!actions.length) {
        return null;
    }

    return (
        <FormSheet
            title={copy.transition_finding}
            description={finding.description}
            triggerLabel={copy.review_finding}
            icon={ShieldCheck}
        >
            <Form
                action={updateFinding({ finding: finding.id })}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id={`finding-action-${finding.id}`}
                            name="action"
                            label={copy.action}
                            options={actions.map(option)}
                            error={errors.action}
                        />
                        <TextField
                            name="resolution"
                            label={copy.resolution}
                            error={errors.resolution}
                        />
                        <Button type="submit" disabled={processing}>
                            {copy.save_transition}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function SubmitForm({
    campaign,
    copy,
}: {
    campaign: UatCampaign;
    copy: Record<string, string>;
}) {
    return (
        <FormSheet
            title={copy.submit_acceptance}
            description={copy.submit_acceptance_description}
            triggerLabel={copy.submit_acceptance}
            icon={Send}
        >
            <Form
                action={submitCampaign({ campaign: campaign.id })}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <label className="flex gap-3 rounded-lg border p-4 text-sm">
                            <input
                                type="checkbox"
                                name="criteria_confirmed"
                                value="1"
                                required
                            />{' '}
                            {copy.confirm_criteria}
                        </label>
                        {errors.status && (
                            <p
                                className="text-sm text-destructive"
                                role="alert"
                            >
                                {errors.status}
                            </p>
                        )}
                        <Button type="submit" disabled={processing}>
                            <Send aria-hidden="true" /> {copy.submit_acceptance}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function DecisionForm({
    acceptance,
    copy,
}: {
    acceptance: Acceptance;
    copy: Record<string, string>;
}) {
    return (
        <FormSheet
            title={copy.decide_acceptance}
            description={`${copy.evidence_checksum}: ${acceptance.checksum}`}
            triggerLabel={copy.decide_acceptance}
            icon={ShieldCheck}
        >
            <Form
                action={decideAcceptance({ acceptance: acceptance.id })}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id={`acceptance-decision-${acceptance.id}`}
                            name="decision"
                            label={copy.decision}
                            options={['accepted', 'rejected'].map(option)}
                            error={errors.decision}
                        />
                        <TextField
                            name="decision_reason"
                            label={copy.decision_reason}
                            error={errors.decision_reason}
                        />
                        <Button type="submit" disabled={processing}>
                            <ShieldCheck aria-hidden="true" />{' '}
                            {copy.record_decision}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">{value.toLocaleString()}</p>
            </CardContent>
        </Card>
    );
}

function Field({
    name,
    label,
    type = 'text',
    defaultValue,
    optional = false,
    error,
}: {
    name: string;
    label: string;
    type?: 'text' | 'number';
    defaultValue?: string;
    optional?: boolean;
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
                required={!optional}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
            />
            {error && (
                <p
                    id={`${id}-error`}
                    className="text-sm text-destructive"
                    role="alert"
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
    const id = name.replaceAll(/[^a-zA-Z0-9_-]/g, '-');

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Textarea
                id={id}
                name={name}
                required={!optional}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
            />
            {error && (
                <p
                    id={`${id}-error`}
                    className="text-sm text-destructive"
                    role="alert"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 text-sm">{value}</p>
        </div>
    );
}

function option(value: string) {
    return { id: value, name: humanize(value) };
}

function humanize(value: string) {
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${value}T00:00:00`),
    );
}
