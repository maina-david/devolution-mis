import { Form, Head, usePage } from '@inertiajs/react';
import {
    Activity,
    DownloadIcon,
    Eye,
    MoreHorizontal,
    Pencil,
    Scale,
    Plus,
    Target,
} from 'lucide-react';
import { useState } from 'react';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import PerformancePlanDocumentControls from '@/components/performance-plan-document-controls';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspaceDocument,
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { store as storeCycle } from '@/routes/departmental-performance/cycles';
import {
    store as storePlan,
    transition,
} from '@/routes/departmental-performance/plans';
import { store as storeGoalAmendmentDecision } from '@/routes/departmental-performance/plans/goal-amendments/decisions';
import { store as storeGoalAmendment } from '@/routes/departmental-performance/plans/goals/amendments';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string; status?: string };
type Goal = {
    id: string;
    code: string;
    title: string;
    description: string;
    kpi: string;
    unit: string;
    baseline: string | null;
    target: string;
    actual: string | null;
    weight: string;
    selfRating: string | null;
    supervisorRating: string | null;
    employeeNarrative: string | null;
    supervisorComment: string | null;
    evidenceReference: string | null;
    versions: Array<{
        id: string;
        version: number;
        snapshot: GoalDefinition;
        checksum: string;
        createdBy: string;
        effectiveAt: string;
    }>;
    amendments: GoalAmendment[];
};
type GoalDefinition = {
    code: string;
    title: string;
    description: string;
    kpi: string;
    unit_of_measure: string;
    baseline_value: string | null;
    target_value: string;
    weight: string;
};
type GoalAmendment = {
    id: string;
    requestVersion: number;
    proposed: GoalDefinition;
    reason: string;
    requester: string;
    requestedAt: string;
    requestChecksum: string;
    decision: null | {
        decision: 'approved' | 'rejected';
        rationale: string;
        decider: string;
        decidedAt: string;
        checksum: string;
        appliedVersionId: string | null;
    };
};
type Review = {
    id: string;
    reviewer: string;
    stage: string;
    rating: string | null;
    comments: string;
    capacityGaps: string | null;
    developmentActions: string | null;
    reviewedAt: string;
};
type PerformancePlan = {
    id: string;
    cycle: string;
    cycleId: string;
    employee: string;
    employeeId: string;
    supervisor: string;
    supervisorId: string;
    organization: string | null;
    referenceData: null | {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    };
    planType: string;
    hrisReference: string | null;
    jobTitle: string | null;
    expectations: string;
    status: string;
    selfScore: string | null;
    supervisorScore: string | null;
    finalScore: string | null;
    capacityGapSummary: string | null;
    integrationStatus: string;
    decisionDueAt: string | null;
    goals: Goal[];
    reviews: Review[];
    documents: WorkspaceDocument[];
};
type Props = {
    plans: {
        data: PerformancePlan[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: Record<string, string | undefined>;
    capabilities: { submit: boolean; review: boolean; manageCycles: boolean };
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
    options: {
        cycles: Option[];
        supervisors: Option[];
        organizations: Option[];
    };
    analytics: {
        summary: {
            finalized: number;
            averageScore: number | null;
            capacityGaps: number;
        };
        trends: Array<{
            id: string;
            cycle: string;
            completed: number;
            averageScore: number;
        }>;
        organizations: Array<{
            id: string;
            organization: string;
            completed: number;
            averageScore: number;
        }>;
        capacityGaps: Array<{ id: string; gap: string; affectedPlans: number }>;
    };
};

const statuses = [
    'draft',
    'goal_review',
    'active',
    'self_review',
    'supervisor_review',
    'finalized',
].map((value) => ({ id: value, name: humanize(value) }));

export default function DepartmentalPerformance({
    plans,
    filters,
    capabilities,
    catalogue,
    options,
    analytics,
}: Props) {
    const { routeContext, auth } = usePage().props;

    if (!routeContext) {
        return null;
    }

    const rows: WorkspaceRow[] = plans.data.map((plan) => ({
        id: plan.id,
        status: plan.status,
        cells: [
            plan.employee,
            plan.cycle,
            plan.jobTitle ?? '—',
            plan.supervisor,
            plan.goals.length,
            plan.finalScore ?? plan.selfScore ?? '—',
            humanize(plan.status),
        ],
    }));
    const pagination: WorkspacePagination = {
        currentPage: plans.current_page,
        lastPage: plans.last_page,
        perPage: plans.per_page,
        total: plans.total,
    };

    return (
        <>
            <Head title="Departmental performance" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                SDD results and accountability
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Departmental performance
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                Agree weighted staff goals, conduct
                                evidence-backed reviews, identify capacity gaps,
                                and preserve an auditable appraisal history.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {capabilities.manageCycles && <CycleForm />}
                            {capabilities.submit && (
                                <PlanForm
                                    options={options}
                                    catalogue={catalogue}
                                />
                            )}
                        </div>
                    </div>
                </section>
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'cycle_id',
                            label: 'Performance cycle',
                            options: options.cycles,
                            value: filters.cycle_id,
                        },
                        {
                            key: 'status',
                            label: 'Plan status',
                            options: statuses,
                            value: filters.status,
                        },
                    ]}
                />
                <section className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        label="Finalized appraisals"
                        value={analytics.summary.finalized.toLocaleString()}
                    />
                    <MetricCard
                        label="Average final score"
                        value={
                            analytics.summary.averageScore === null
                                ? '—'
                                : `${analytics.summary.averageScore}%`
                        }
                    />
                    <MetricCard
                        label="Distinct capacity gaps"
                        value={analytics.summary.capacityGaps.toLocaleString()}
                    />
                </section>
                <section className="grid gap-4 xl:grid-cols-3">
                    <AnalyticsTable
                        title="Performance trend"
                        description="Finalized scores by appraisal cycle in your authorized scope."
                        columns={['Cycle', 'Completed', 'Average score']}
                        rows={analytics.trends.map((item) => ({
                            id: item.id,
                            cells: [
                                item.cycle,
                                item.completed,
                                `${item.averageScore}%`,
                            ],
                        }))}
                    />
                    <AnalyticsTable
                        title="Department rollup"
                        description="Finalized appraisal outcomes grouped by department or organization."
                        columns={['Department', 'Completed', 'Average score']}
                        rows={analytics.organizations.map((item) => ({
                            id: item.id,
                            cells: [
                                item.organization,
                                item.completed,
                                `${item.averageScore}%`,
                            ],
                        }))}
                    />
                    <AnalyticsTable
                        title="Capacity priorities"
                        description="Recurring development needs from finalized supervisor reviews."
                        columns={['Capacity gap', 'Affected plans']}
                        rows={analytics.capacityGaps.map((item) => ({
                            id: item.id,
                            cells: [item.gap, item.affectedPlans],
                        }))}
                    />
                </section>
                <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
                    <div className="flex items-center justify-between border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">Performance register</h2>
                            <p className="text-sm text-muted-foreground">
                                {plans.total.toLocaleString()} plans in your
                                authorized scope
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
                                                            workspace:
                                                                'departmental-performance',
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
                            <Activity className="size-5 text-[#147a55]" />
                        </div>
                    </div>
                    {rows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Employee',
                                'Cycle',
                                'Role',
                                'Supervisor',
                                'Goals',
                                'Score',
                                'Status',
                            ]}
                            rows={rows}
                            pagination={pagination}
                            bulkExport={{
                                workspace: 'departmental-performance',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const plan = plans.data.find(
                                    (item) => item.id === row.id,
                                );

                                return plan ? (
                                    <PlanActions
                                        plan={plan}
                                        currentUserId={auth.user.id}
                                        capabilities={capabilities}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No matching performance plans"
                            description="Adjust the filters or create the first weighted plan for an open appraisal cycle."
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
                        description="Finalized appraisals will populate this analysis."
                        className="min-h-48 border-0"
                    />
                )}
            </CardContent>
        </Card>
    );
}

function CycleForm() {
    return (
        <FormSheet
            title="Create performance cycle"
            description="Configure the goal-setting and appraisal calendar."
            triggerLabel="New cycle"
            icon={Plus}
        >
            <Form action={storeCycle()} className="grid gap-4 pt-4">
                {({ errors, processing }) => (
                    <>
                        <Field
                            name="code"
                            label="Cycle code"
                            error={errors.code}
                        />
                        <Field
                            name="name"
                            label="Cycle name"
                            error={errors.name}
                        />
                        <DatePickerField
                            name="period_start"
                            label="Period start"
                            required
                            error={errors.period_start}
                        />
                        <DatePickerField
                            name="period_end"
                            label="Period end"
                            required
                            error={errors.period_end}
                        />
                        <DatePickerField
                            name="goal_setting_deadline"
                            label="Goal-setting deadline"
                            required
                            error={errors.goal_setting_deadline}
                        />
                        <DatePickerField
                            name="midterm_review_deadline"
                            label="Midterm review deadline"
                            error={errors.midterm_review_deadline}
                        />
                        <DatePickerField
                            name="final_review_deadline"
                            label="Final review deadline"
                            required
                            error={errors.final_review_deadline}
                        />
                        <SearchableSelect
                            id="cycle-status"
                            name="status"
                            label="Status"
                            options={['draft', 'open', 'closed'].map((id) => ({
                                id,
                                name: humanize(id),
                            }))}
                            defaultValue="open"
                            error={errors.status}
                        />
                        <Button type="submit" disabled={processing}>
                            Create cycle
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function PlanForm({
    options,
    catalogue,
}: {
    options: Props['options'];
    catalogue: Props['catalogue'];
}) {
    const [goals, setGoals] = useState([0]);

    return (
        <FormSheet
            title="New performance plan"
            description="Define measurable goals whose weights total exactly 100%."
            triggerLabel="New plan"
            icon={Target}
            size="xl"
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `Using governed catalogue release v${catalogue.version}`
                    : 'No checksum-valid published reference catalogue is currently effective.'
            }
        >
            <Form action={storePlan()} className="grid gap-6 pt-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <SearchableSelect
                                id="performance-cycle"
                                name="performance_cycle_id"
                                label="Performance cycle"
                                options={options.cycles.filter(
                                    (item) => item.status === 'open',
                                )}
                                error={errors.performance_cycle_id}
                            />
                            <SearchableSelect
                                id="performance-supervisor"
                                name="supervisor_id"
                                label="Supervisor"
                                options={options.supervisors}
                                error={errors.supervisor_id}
                            />
                            <SearchableSelect
                                id="performance-organization"
                                name="organization_id"
                                label="Department / organization"
                                options={options.organizations}
                                optional
                                error={errors.organization_id}
                            />
                            <SearchableSelect
                                id="plan-type"
                                name="plan_type"
                                label="Plan type"
                                options={[
                                    { id: 'individual', name: 'Individual' },
                                    {
                                        id: 'departmental',
                                        name: 'Departmental',
                                    },
                                ]}
                                defaultValue="individual"
                            />
                            <Field
                                name="hris_employee_reference"
                                label="HRIS employee reference"
                                optional
                                error={errors.hris_employee_reference}
                            />
                            <Field
                                name="job_title"
                                label="Job title"
                                optional
                                error={errors.job_title}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="performance-expectations">
                                Overall expectations
                            </Label>
                            <Textarea
                                id="performance-expectations"
                                name="overall_expectations"
                                rows={4}
                                required
                            />
                            <ErrorText value={errors.overall_expectations} />
                        </div>
                        <div className="grid gap-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="font-semibold">
                                        Weighted goals
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Together the weights must equal 100%.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setGoals((items) => [
                                            ...items,
                                            Math.max(...items) + 1,
                                        ])
                                    }
                                >
                                    Add goal
                                </Button>
                            </div>
                            {goals.map((key, index) => (
                                <Card key={key}>
                                    <CardHeader className="flex-row items-center justify-between">
                                        <CardTitle className="text-base">
                                            Goal {index + 1}
                                        </CardTitle>
                                        {goals.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setGoals((items) =>
                                                        items.filter(
                                                            (item) =>
                                                                item !== key,
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
                                            name={`goals[${index}][code]`}
                                            label="Code"
                                            error={
                                                errors[`goals.${index}.code`]
                                            }
                                        />
                                        <Field
                                            name={`goals[${index}][title]`}
                                            label="Goal title"
                                            error={
                                                errors[`goals.${index}.title`]
                                            }
                                        />
                                        <Field
                                            name={`goals[${index}][kpi]`}
                                            label="KPI"
                                            error={errors[`goals.${index}.kpi`]}
                                        />
                                        <Field
                                            name={`goals[${index}][unit_of_measure]`}
                                            label="Unit of measure"
                                            error={
                                                errors[
                                                    `goals.${index}.unit_of_measure`
                                                ]
                                            }
                                        />
                                        <Field
                                            name={`goals[${index}][baseline_value]`}
                                            label="Baseline"
                                            type="number"
                                            optional
                                            error={
                                                errors[
                                                    `goals.${index}.baseline_value`
                                                ]
                                            }
                                        />
                                        <Field
                                            name={`goals[${index}][target_value]`}
                                            label="Target"
                                            type="number"
                                            error={
                                                errors[
                                                    `goals.${index}.target_value`
                                                ]
                                            }
                                        />
                                        <Field
                                            name={`goals[${index}][weight]`}
                                            label="Weight (%)"
                                            type="number"
                                            error={
                                                errors[`goals.${index}.weight`]
                                            }
                                        />
                                        <div className="grid gap-2 md:col-span-2">
                                            <Label
                                                htmlFor={`goal-description-${index}`}
                                            >
                                                Description
                                            </Label>
                                            <Textarea
                                                id={`goal-description-${index}`}
                                                name={`goals[${index}][description]`}
                                                required
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                            <ErrorText value={errors.goals} />
                        </div>
                        <Button type="submit" disabled={processing}>
                            Save draft plan
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function PlanActions({
    plan,
    currentUserId,
    capabilities,
}: {
    plan: PerformancePlan;
    currentUserId: string;
    capabilities: Props['capabilities'];
}) {
    const [surface, setSurface] = useState<string | null>(null);
    const cleanPurposes = new Set(
        plan.documents
            .filter((document) => document.scanStatus === 'clean')
            .map((document) => document.purpose),
    );
    const evidenceGate = (transitionName: string): string | undefined => {
        if (
            transitionName === 'submit_goals' &&
            !cleanPurposes.has('performance-goal-plan')
        ) {
            return 'Upload a clean signed goal plan before submission.';
        }

        if (
            transitionName === 'submit_self_review' &&
            !cleanPurposes.has('performance-self-review-evidence')
        ) {
            return 'Upload clean self-review evidence before submission.';
        }

        if (
            transitionName === 'finalize_review' &&
            !cleanPurposes.has('performance-final-appraisal')
        ) {
            return 'Upload a clean signed final appraisal before finalization.';
        }

        return undefined;
    };
    const transitions = [
        [
            'submit_goals',
            'Submit goals',
            capabilities.submit &&
                plan.employeeId === currentUserId &&
                plan.status === 'draft',
        ],
        [
            'approve_goals',
            'Approve goals',
            capabilities.review &&
                plan.supervisorId === currentUserId &&
                plan.status === 'goal_review',
        ],
        [
            'return_goals',
            'Return goals',
            capabilities.review &&
                plan.supervisorId === currentUserId &&
                plan.status === 'goal_review',
        ],
        [
            'start_review',
            'Start self-review',
            capabilities.submit &&
                plan.employeeId === currentUserId &&
                plan.status === 'active',
        ],
        [
            'submit_self_review',
            'Submit self-review',
            capabilities.submit &&
                plan.employeeId === currentUserId &&
                plan.status === 'self_review',
        ],
        [
            'finalize_review',
            'Finalize appraisal',
            capabilities.review &&
                plan.supervisorId === currentUserId &&
                plan.status === 'supervisor_review',
        ],
    ] as const;
    const amendmentGoal = surface?.startsWith('amend:')
        ? plan.goals.find((goal) => goal.id === surface.slice(6))
        : undefined;
    const decisionAmendment = surface?.startsWith('decide:')
        ? plan.goals
              .flatMap((goal) =>
                  goal.amendments.map((amendment) => ({ goal, amendment })),
              )
              .find(({ amendment }) => amendment.id === surface.slice(7))
        : undefined;
    const pendingAmendments = plan.goals.flatMap((goal) =>
        goal.amendments
            .filter((amendment) => amendment.decision === null)
            .map((amendment) => ({ goal, amendment })),
    );

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${plan.employee}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-56">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={() => setSurface('details')}
                        >
                            <Eye /> View plan
                        </DropdownMenuItem>
                        {transitions
                            .filter(([, , visible]) => visible)
                            .map(([id, label]) => (
                                <DropdownMenuItem
                                    key={id}
                                    onSelect={() => setSurface(id)}
                                >
                                    <Target /> {label}
                                </DropdownMenuItem>
                            ))}
                        {capabilities.submit &&
                            plan.employeeId === currentUserId &&
                            plan.status === 'active' &&
                            pendingAmendments.length === 0 &&
                            plan.goals.map((goal) => (
                                <DropdownMenuItem
                                    key={`amend-${goal.id}`}
                                    onSelect={() =>
                                        setSurface(`amend:${goal.id}`)
                                    }
                                >
                                    <Pencil /> Amend {goal.code}
                                </DropdownMenuItem>
                            ))}
                        {capabilities.review &&
                            plan.supervisorId === currentUserId &&
                            plan.status === 'active' &&
                            pendingAmendments.map(({ goal, amendment }) => (
                                <DropdownMenuItem
                                    key={`decide-${amendment.id}`}
                                    onSelect={() =>
                                        setSurface(`decide:${amendment.id}`)
                                    }
                                >
                                    <Scale /> Decide {goal.code} amendment
                                </DropdownMenuItem>
                            ))}
                    </DropdownMenuGroup>
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
                                ? `${plan.employee} · ${plan.cycle}`
                                : amendmentGoal
                                  ? `Amend ${amendmentGoal.code}`
                                  : decisionAmendment
                                    ? `Decide ${decisionAmendment.goal.code} amendment`
                                    : humanize(surface ?? '')}
                        </SheetTitle>
                        <SheetDescription>
                            {surface === 'details'
                                ? 'Goals, scores, HRIS boundary and review history.'
                                : amendmentGoal
                                  ? 'Propose a complete replacement definition for independent supervisor review.'
                                  : decisionAmendment
                                    ? 'Review the proposed definition and retain an independent decision.'
                                    : 'Record an attributed performance lifecycle decision.'}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-8">
                        {surface === 'details' ? (
                            <PlanDetails
                                plan={plan}
                                currentUserId={currentUserId}
                                capabilities={capabilities}
                            />
                        ) : amendmentGoal ? (
                            <GoalAmendmentForm
                                plan={plan}
                                goal={amendmentGoal}
                            />
                        ) : decisionAmendment ? (
                            <GoalAmendmentDecisionForm
                                plan={plan}
                                goal={decisionAmendment.goal}
                                amendment={decisionAmendment.amendment}
                            />
                        ) : surface ? (
                            <TransitionForm
                                plan={plan}
                                transitionName={surface}
                                disabledReason={evidenceGate(surface)}
                            />
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function TransitionForm({
    plan,
    transitionName,
    disabledReason,
}: {
    plan: PerformancePlan;
    transitionName: string;
    disabledReason?: string;
}) {
    const ratings = ['submit_self_review', 'finalize_review'].includes(
        transitionName,
    );

    return (
        <Form
            action={transition({ performancePlan: plan.id })}
            className="grid gap-5 pt-4"
        >
            {({ errors, processing }) => (
                <>
                    {disabledReason && (
                        <p
                            role="status"
                            className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"
                        >
                            {disabledReason}
                        </p>
                    )}
                    <input
                        type="hidden"
                        name="transition"
                        value={transitionName}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor={`rationale-${plan.id}`}>
                            Decision rationale
                        </Label>
                        <Textarea
                            id={`rationale-${plan.id}`}
                            name="rationale"
                            rows={4}
                            required
                        />
                        <ErrorText value={errors.rationale} />
                    </div>
                    {ratings &&
                        plan.goals.map((goal, index) => (
                            <Card key={goal.id}>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {goal.code} · {goal.title}{' '}
                                        <Badge variant="outline">
                                            {goal.weight}%
                                        </Badge>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-2">
                                    <input
                                        type="hidden"
                                        name={`goals[${index}][id]`}
                                        value={goal.id}
                                    />
                                    {transitionName ===
                                        'submit_self_review' && (
                                        <Field
                                            name={`goals[${index}][actual_value]`}
                                            label={`Actual (${goal.unit})`}
                                            type="number"
                                            optional
                                            error={
                                                errors[
                                                    `goals.${index}.actual_value`
                                                ]
                                            }
                                        />
                                    )}
                                    <Field
                                        name={`goals[${index}][rating]`}
                                        label="Rating (0–100)"
                                        type="number"
                                        error={errors[`goals.${index}.rating`]}
                                    />
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor={`narrative-${goal.id}`}>
                                            {transitionName ===
                                            'submit_self_review'
                                                ? 'Achievement narrative'
                                                : 'Supervisor comment'}
                                        </Label>
                                        <Textarea
                                            id={`narrative-${goal.id}`}
                                            name={`goals[${index}][narrative]`}
                                            rows={3}
                                        />
                                    </div>
                                    {transitionName ===
                                        'submit_self_review' && (
                                        <Field
                                            name={`goals[${index}][evidence_reference]`}
                                            label="Evidence reference"
                                            optional
                                            error={
                                                errors[
                                                    `goals.${index}.evidence_reference`
                                                ]
                                            }
                                        />
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    {transitionName === 'finalize_review' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="capacity-gaps">
                                    Capacity gaps
                                </Label>
                                <Textarea
                                    id="capacity-gaps"
                                    name="capacity_gaps"
                                    required
                                    rows={4}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="development-actions">
                                    Development actions
                                </Label>
                                <Textarea
                                    id="development-actions"
                                    name="development_actions"
                                    required
                                    rows={4}
                                />
                            </div>
                        </>
                    )}
                    <Button
                        type="submit"
                        disabled={processing || Boolean(disabledReason)}
                    >
                        {humanize(transitionName)}
                    </Button>
                </>
            )}
        </Form>
    );
}

function GoalAmendmentForm({
    plan,
    goal,
}: {
    plan: PerformancePlan;
    goal: Goal;
}) {
    return (
        <Form
            action={storeGoalAmendment({
                performancePlan: plan.id,
                performanceGoal: goal.id,
            })}
            className="grid gap-5 pt-4"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            name="code"
                            label="Goal code"
                            defaultValue={goal.code}
                            error={errors.code}
                        />
                        <Field
                            name="title"
                            label="Goal title"
                            defaultValue={goal.title}
                            error={errors.title}
                        />
                        <Field
                            name="kpi"
                            label="KPI"
                            defaultValue={goal.kpi}
                            error={errors.kpi}
                        />
                        <Field
                            name="unit_of_measure"
                            label="Unit of measure"
                            defaultValue={goal.unit}
                            error={errors.unit_of_measure}
                        />
                        <Field
                            name="baseline_value"
                            label="Baseline"
                            type="number"
                            optional
                            defaultValue={goal.baseline ?? ''}
                            error={errors.baseline_value}
                        />
                        <Field
                            name="target_value"
                            label="Target"
                            type="number"
                            defaultValue={goal.target}
                            error={errors.target_value}
                        />
                        <Field
                            name="weight"
                            label="Weight (%)"
                            type="number"
                            defaultValue={goal.weight}
                            error={errors.weight}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`amend-description-${goal.id}`}>
                            Goal description
                        </Label>
                        <Textarea
                            id={`amend-description-${goal.id}`}
                            name="description"
                            rows={4}
                            defaultValue={goal.description}
                            required
                            aria-invalid={Boolean(errors.description)}
                        />
                        <ErrorText value={errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`amend-reason-${goal.id}`}>
                            Amendment rationale
                        </Label>
                        <Textarea
                            id={`amend-reason-${goal.id}`}
                            name="reason"
                            rows={4}
                            required
                            aria-invalid={Boolean(errors.reason)}
                        />
                        <ErrorText value={errors.reason} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Submit amendment request
                    </Button>
                </>
            )}
        </Form>
    );
}

function GoalAmendmentDecisionForm({
    plan,
    goal,
    amendment,
}: {
    plan: PerformancePlan;
    goal: Goal;
    amendment: GoalAmendment;
}) {
    return (
        <Form
            action={storeGoalAmendmentDecision({
                performancePlan: plan.id,
                performanceGoalAmendment: amendment.id,
            })}
            className="grid gap-5 pt-4"
        >
            {({ errors, processing }) => (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Requested by {amendment.requester}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <p className="text-muted-foreground">
                                {amendment.reason}
                            </p>
                            {[
                                ['Title', goal.title, amendment.proposed.title],
                                ['KPI', goal.kpi, amendment.proposed.kpi],
                                [
                                    'Target',
                                    `${goal.target} ${goal.unit}`,
                                    `${amendment.proposed.target_value} ${amendment.proposed.unit_of_measure}`,
                                ],
                                [
                                    'Weight',
                                    `${goal.weight}%`,
                                    `${amendment.proposed.weight}%`,
                                ],
                            ].map(([label, current, proposed]) => (
                                <div
                                    key={label}
                                    className="grid gap-1 rounded-lg border p-3"
                                >
                                    <p className="font-medium">{label}</p>
                                    <p className="text-muted-foreground">
                                        Current: {current}
                                    </p>
                                    <p>Proposed: {proposed}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <SearchableSelect
                        id={`amendment-decision-${amendment.id}`}
                        name="decision"
                        label="Decision"
                        options={[
                            { id: 'approved', name: 'Approve' },
                            { id: 'rejected', name: 'Reject' },
                        ]}
                        error={errors.decision}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor={`decision-rationale-${amendment.id}`}>
                            Decision rationale
                        </Label>
                        <Textarea
                            id={`decision-rationale-${amendment.id}`}
                            name="rationale"
                            rows={4}
                            required
                            aria-invalid={Boolean(errors.rationale)}
                        />
                        <ErrorText value={errors.rationale} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Retain decision
                    </Button>
                </>
            )}
        </Form>
    );
}

function PlanDetails({
    plan,
    currentUserId,
    capabilities,
}: {
    plan: PerformancePlan;
    currentUserId: string;
    capabilities: Props['capabilities'];
}) {
    const isEmployee = plan.employeeId === currentUserId;
    const isSupervisor = plan.supervisorId === currentUserId;
    const canUpload =
        (capabilities.submit &&
            isEmployee &&
            ['draft', 'self_review'].includes(plan.status)) ||
        (capabilities.review &&
            isSupervisor &&
            plan.status === 'supervisor_review');

    return (
        <div className="grid gap-6 pt-4">
            <PerformancePlanDocumentControls
                planId={plan.id}
                status={plan.status}
                documents={plan.documents}
                canUpload={canUpload}
                isEmployee={isEmployee}
            />
            <div className="grid gap-3 sm:grid-cols-2">
                {[
                    ['Employee', plan.employee],
                    ['Supervisor', plan.supervisor],
                    ['Department', plan.organization ?? '—'],
                    [
                        'Reference catalogue',
                        plan.referenceData
                            ? `v${plan.referenceData.version} · ${plan.referenceData.checksum}`
                            : 'Legacy · unpinned',
                    ],
                    ['Job title', plan.jobTitle ?? '—'],
                    ['HRIS reference', plan.hrisReference ?? 'Not linked'],
                    ['Integration', humanize(plan.integrationStatus)],
                    ['Self score', plan.selfScore ?? '—'],
                    ['Final score', plan.finalScore ?? '—'],
                ].map(([label, value]) => (
                    <div key={label} className="rounded-lg border p-3">
                        <p className="text-xs text-muted-foreground">{label}</p>
                        <p className="mt-1 font-medium">{value}</p>
                    </div>
                ))}
            </div>
            <div>
                <h3 className="font-semibold">Overall expectations</h3>
                <p className="mt-2 text-sm text-muted-foreground">
                    {plan.expectations}
                </p>
            </div>
            <div className="grid gap-3">
                <h3 className="font-semibold">Weighted goals</h3>
                {plan.goals.map((goal) => (
                    <div key={goal.id} className="rounded-lg border p-4">
                        <div className="flex flex-wrap justify-between gap-2">
                            <p className="font-medium">
                                {goal.code} · {goal.title}
                            </p>
                            <Badge variant="outline">{goal.weight}%</Badge>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {goal.kpi}: target {goal.target} {goal.unit} ·
                            actual {goal.actual ?? 'pending'} · self{' '}
                            {goal.selfRating ?? '—'} · supervisor{' '}
                            {goal.supervisorRating ?? '—'}
                        </p>
                        <div className="mt-4 grid gap-2">
                            <p className="text-xs font-medium text-muted-foreground">
                                {goal.versions.length} retained definition{' '}
                                {goal.versions.length === 1
                                    ? 'version'
                                    : 'versions'}
                            </p>
                            {goal.amendments.map((amendment) => (
                                <div
                                    key={amendment.id}
                                    className="rounded-lg border p-3 text-sm"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-medium">
                                            Amendment v
                                            {amendment.requestVersion} ·{' '}
                                            {amendment.requester}
                                        </p>
                                        <Badge
                                            variant={
                                                amendment.decision
                                                    ? 'outline'
                                                    : 'secondary'
                                            }
                                        >
                                            {amendment.decision?.decision ??
                                                'Pending'}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-muted-foreground">
                                        {amendment.reason}
                                    </p>
                                    {amendment.decision && (
                                        <p className="mt-2">
                                            {amendment.decision.decider}:{' '}
                                            {amendment.decision.rationale}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
            <div className="grid gap-3">
                <h3 className="font-semibold">Review history</h3>
                {plan.reviews.length ? (
                    plan.reviews.map((review) => (
                        <div key={review.id} className="rounded-lg border p-4">
                            <p className="font-medium">
                                {humanize(review.stage)} · {review.reviewer}
                            </p>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {review.comments}
                            </p>
                        </div>
                    ))
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No review decisions recorded yet.
                    </p>
                )}
            </div>
        </div>
    );
}

function Field({
    name,
    label,
    type = 'text',
    optional = false,
    defaultValue,
    error,
}: {
    name: string;
    label: string;
    type?: 'text' | 'number';
    optional?: boolean;
    defaultValue?: string | number;
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
                step={type === 'number' ? '0.01' : undefined}
                required={!optional}
                defaultValue={defaultValue}
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
