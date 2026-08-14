import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    ChartNoAxesCombined,
    CircleCheckBig,
    ClipboardList,
    FileCheck2,
    Gauge,
    MapPinned,
    ShieldAlert,
    TrendingUp,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    XAxis,
    YAxis,
} from 'recharts';
import CountyIdentity from '@/components/county-identity';
import DateRangeFilter from '@/components/date-range-filter';
import KenyaCountyMap from '@/components/kenya-county-map';
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
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { interpolate } from '@/hooks/use-localization';
import { preserveDrilldownFilters } from '@/lib/preserve-drilldown-filters';
import { formatCurrency } from '@/lib/reference-catalog';
import { dashboard } from '@/routes';
import { show as showCounty } from '@/routes/counties';

type CountyMetric = {
    id: string;
    code: number;
    name: string;
    slug: string;
    region: string | null;
    mapX: number;
    mapY: number;
    logoUrl: string | null;
    assessmentStatus: string;
    latestCycle: string | null;
    latestScore: number | null;
    documents: number;
    allocatedGrant: number;
    disbursedGrant: number;
};

type CycleMetric = {
    id: string;
    code: string;
    name: string;
    status: string;
    periodStart: string;
    periodEnd: string;
    selected: boolean;
    countiesAssessed: number;
    countiesTotal: number;
    completionPercent: number;
    averageScore: number | null;
    evidenceDocuments: number;
};

type Props = {
    dashboardProfile: {
        role: string;
        roleLabel: string;
        mapScope: 'country' | 'county' | 'portfolio' | 'none';
        eyebrow: string;
        title: string;
        description: string;
    };
    stats: {
        counties: number;
        assessed: number;
        pending: number;
        documents: number;
        averageScore: number | null;
        allocatedGrants: number;
        disbursedGrants: number;
    };
    counties: CountyMetric[];
    cycleOverview: CycleMetric[];
    operationalSignals: {
        activeProjects: number;
        overdueCitizenCases: number;
        delayedExchequerRequests: number;
        overdueEvaluationFindings: number;
        openPartnerAlerts: number;
        evidenceAwaitingReview: number;
        evidenceScanAttention: number;
    };
    roleFocus: string[];
    filters: { from?: string; to?: string; search?: string; cycle_id?: string };
};

const formatCompactCurrency = (value: number) =>
    formatCurrency(value, undefined, {
        notation: 'compact',
        maximumFractionDigits: 1,
    });

export default function Dashboard({
    dashboardProfile,
    stats,
    counties,
    cycleOverview,
    operationalSignals,
    roleFocus,
    filters,
}: Props) {
    const page = usePage();
    const copy = page.props.localization.dashboard;
    setLayoutProps({
        breadcrumbs: [
            {
                title: page.props.localization.common.dashboard,
                href: dashboard(),
            },
        ],
    });
    const cycleChartConfig = {
        averageScore: { label: copy.average_score, color: 'var(--chart-1)' },
        completionPercent: {
            label: copy.cycle_completion,
            color: 'var(--chart-2)',
        },
    } satisfies ChartConfig;
    const fundingChartConfig = {
        allocatedGrant: { label: copy.allocated, color: 'var(--chart-2)' },
        disbursedGrant: { label: copy.disbursed, color: 'var(--chart-1)' },
    } satisfies ChartConfig;
    const evidenceChartConfig = {
        documents: { label: copy.evidence_documents, color: 'var(--chart-3)' },
    } satisfies ChartConfig;
    const [selectedCounty, setSelectedCounty] = useState<CountyMetric | null>(
        counties.length === 1 ? counties[0] : null,
    );
    const cycleTrend = useMemo(
        () => [...cycleOverview].reverse(),
        [cycleOverview],
    );
    const fundingSeries = useMemo(
        () =>
            [...counties]
                .sort((a, b) => b.allocatedGrant - a.allocatedGrant)
                .slice(0, 10),
        [counties],
    );
    const evidenceSeries = useMemo(
        () =>
            [...counties]
                .sort((a, b) => b.documents - a.documents)
                .slice(0, 10),
        [counties],
    );
    const disbursementRate =
        stats.allocatedGrants > 0
            ? Math.round((stats.disbursedGrants / stats.allocatedGrants) * 100)
            : 0;
    const exceptionCount =
        operationalSignals.overdueCitizenCases +
        operationalSignals.delayedExchequerRequests +
        operationalSignals.overdueEvaluationFindings +
        operationalSignals.openPartnerAlerts +
        operationalSignals.evidenceAwaitingReview +
        operationalSignals.evidenceScanAttention;
    const countyAttention = useMemo(
        () =>
            counties
                .map((county) => {
                    const grantGap = Math.max(
                        0,
                        county.allocatedGrant - county.disbursedGrant,
                    );
                    const reasons = [
                        !['assessed', 'approved', 'published'].includes(
                            county.assessmentStatus,
                        ) && copy.assessment_incomplete,
                        county.documents === 0 && copy.no_evidence_indexed,
                        county.latestScore !== null &&
                            county.latestScore < 60 &&
                            copy.score_below_threshold,
                        grantGap > 0 && copy.undisbursed_allocation,
                    ].filter((reason): reason is string => Boolean(reason));

                    return { county, grantGap, reasons };
                })
                .filter((item) => item.reasons.length > 0)
                .sort(
                    (a, b) =>
                        b.reasons.length - a.reasons.length ||
                        b.grantGap - a.grantGap,
                )
                .slice(0, 12),
        [counties, copy],
    );
    const operationalRows = [
        {
            label: copy.evidence_awaiting_review,
            value: operationalSignals.evidenceAwaitingReview,
            detail: copy.evidence_awaiting_review_detail,
            tone: 'warning' as const,
        },
        {
            label: copy.evidence_scan_attention,
            value: operationalSignals.evidenceScanAttention,
            detail: copy.evidence_scan_attention_detail,
            tone: 'critical' as const,
        },
        {
            label: copy.overdue_citizen_cases,
            value: operationalSignals.overdueCitizenCases,
            detail: copy.overdue_citizen_cases_detail,
            tone: 'critical' as const,
        },
        {
            label: copy.delayed_exchequer_requests,
            value: operationalSignals.delayedExchequerRequests,
            detail: copy.delayed_exchequer_requests_detail,
            tone: 'critical' as const,
        },
        {
            label: copy.overdue_evaluation_findings,
            value: operationalSignals.overdueEvaluationFindings,
            detail: copy.overdue_evaluation_findings_detail,
            tone: 'warning' as const,
        },
        {
            label: copy.open_partner_alerts,
            value: operationalSignals.openPartnerAlerts,
            detail: copy.open_partner_alerts_detail,
            tone: 'warning' as const,
        },
    ];
    const cards = [
        {
            label: copy.counties_in_view,
            value: stats.counties,
            detail: `${stats.assessed} ${copy.assessed} ${copy.separator} ${stats.pending} ${copy.pending}`,
            icon: MapPinned,
        },
        {
            label: copy.average_score,
            value: stats.averageScore === null ? '—' : `${stats.averageScore}%`,
            detail: copy.completed_assessments_detail,
            icon: TrendingUp,
        },
        {
            label: copy.evidence_documents,
            value: stats.documents.toLocaleString(),
            detail: copy.selected_period_detail,
            icon: FileCheck2,
        },
        {
            label: copy.grant_disbursement,
            value: `${disbursementRate}%`,
            detail: `${formatCompactCurrency(stats.disbursedGrants)} ${copy.disbursed_lower}`,
            icon: Banknote,
        },
    ];

    return (
        <>
            <Head
                title={interpolate(copy.dashboard_title, {
                    role: dashboardProfile.roleLabel,
                })}
            />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="max-w-3xl">
                        <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                            {dashboardProfile.eyebrow}
                        </p>
                        <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                            {dashboardProfile.title}
                        </h1>
                        <p className="mt-3 max-w-2xl text-sm leading-6 text-[#c7d6dd] sm:text-base">
                            {dashboardProfile.description}
                        </p>
                    </div>
                </section>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    initialCycleId={filters.cycle_id}
                />

                <Tabs defaultValue="overview" className="min-w-0 gap-6">
                    <TabsList
                        variant="line"
                        className="max-w-full overflow-x-auto"
                    >
                        <TabsTrigger value="overview">
                            <Gauge /> {copy.overview}
                        </TabsTrigger>
                        <TabsTrigger value="cycles">
                            <ChartNoAxesCombined /> {copy.assessment_cycles}
                        </TabsTrigger>
                        <TabsTrigger value="action-queue">
                            <ClipboardList /> {copy.action_queue}
                            {exceptionCount > 0 && (
                                <Badge variant="destructive">
                                    {exceptionCount}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger value="delivery">
                            <Banknote /> {copy.funds_evidence}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent
                        value="overview"
                        className="flex flex-col gap-5"
                    >
                        <section
                            aria-label={copy.portfolio_statistics}
                            className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            {cards.map(
                                ({ label, value, detail, icon: Icon }) => (
                                    <Card key={label} className="gap-4 py-5">
                                        <CardHeader className="flex-row items-center justify-between gap-4">
                                            <CardDescription className="font-semibold">
                                                {label}
                                            </CardDescription>
                                            <Icon
                                                className="size-5 text-primary"
                                                aria-hidden="true"
                                            />
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-3xl font-bold tracking-tight">
                                                {value}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {detail}
                                            </p>
                                        </CardContent>
                                    </Card>
                                ),
                            )}
                        </section>

                        <section className="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.6fr)]">
                            <Card className="gap-0 py-0">
                                <CardHeader className="border-b py-5">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <CardTitle>
                                                {copy.operational_control}
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                {
                                                    copy.operational_control_description
                                                }
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={
                                                exceptionCount > 0
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {exceptionCount > 0
                                                ? interpolate(
                                                      copy.require_attention,
                                                      {
                                                          count: exceptionCount,
                                                      },
                                                  )
                                                : copy.no_active_exceptions}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid gap-px bg-border p-0 sm:grid-cols-2 xl:grid-cols-3">
                                    {operationalRows.map((signal) => (
                                        <OperationalSignal
                                            key={signal.label}
                                            {...signal}
                                        />
                                    ))}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>{copy.role_brief}</CardTitle>
                                    <CardDescription>
                                        {interpolate(
                                            copy.priority_decisions_for_role,
                                            {
                                                role: dashboardProfile.roleLabel,
                                            },
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ol className="grid gap-4">
                                        {roleFocus.map((focus, index) => (
                                            <li
                                                key={focus}
                                                className="flex items-start gap-3"
                                            >
                                                <span className="grid size-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                                    {index + 1}
                                                </span>
                                                <span className="pt-1 text-sm font-medium">
                                                    {focus}
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                    <div className="mt-5 border-t pt-4 text-sm text-muted-foreground">
                                        {operationalSignals.activeProjects.toLocaleString()}{' '}
                                        {copy.active_projects_visible}
                                    </div>
                                </CardContent>
                            </Card>
                        </section>

                        {dashboardProfile.mapScope !== 'none' && (
                            <section className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(19rem,0.7fr)]">
                                <Card className="min-w-0 gap-0 overflow-hidden py-0">
                                    <CardHeader className="py-6">
                                        <CardDescription>
                                            {copy.county_coverage}
                                        </CardDescription>
                                        <CardTitle>
                                            {copy.kenya_assessment_map}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <KenyaCountyMap
                                            counties={counties}
                                            showFullCountry={
                                                dashboardProfile.mapScope ===
                                                'country'
                                            }
                                            selectedCountyId={
                                                selectedCounty?.id
                                            }
                                            onSelect={setSelectedCounty}
                                            className="rounded-none border-x-0 border-b-0"
                                        />
                                    </CardContent>
                                </Card>

                                <Card aria-live="polite">
                                    <CardHeader>
                                        <CardDescription>
                                            {copy.county_detail}
                                        </CardDescription>
                                        <CardTitle>
                                            {selectedCounty?.name ??
                                                (counties.length === 0
                                                    ? copy.no_county_assigned
                                                    : copy.select_authorized_county)}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {selectedCounty ? (
                                            <div className="flex flex-col gap-5">
                                                <div className="flex items-start justify-between gap-3">
                                                    <CountyIdentity
                                                        county={{
                                                            kind: 'county',
                                                            id: selectedCounty.id,
                                                            code: selectedCounty.code,
                                                            name: selectedCounty.name,
                                                            logoUrl:
                                                                selectedCounty.logoUrl,
                                                        }}
                                                    />
                                                    <Badge variant="outline">
                                                        {selectedCounty.assessmentStatus.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </Badge>
                                                </div>
                                                <dl className="divide-y divide-border">
                                                    {[
                                                        [
                                                            copy.latest_cycle,
                                                            selectedCounty.latestCycle ??
                                                                copy.not_started,
                                                        ],
                                                        [
                                                            copy.assessment_score,
                                                            selectedCounty.latestScore ===
                                                            null
                                                                ? copy.pending
                                                                : `${selectedCounty.latestScore}%`,
                                                        ],
                                                        [
                                                            copy.documents_collected,
                                                            selectedCounty.documents.toLocaleString(),
                                                        ],
                                                        [
                                                            copy.grant_allocated,
                                                            formatCompactCurrency(
                                                                selectedCounty.allocatedGrant,
                                                            ),
                                                        ],
                                                        [
                                                            copy.grant_disbursed,
                                                            formatCompactCurrency(
                                                                selectedCounty.disbursedGrant,
                                                            ),
                                                        ],
                                                    ].map(([term, value]) => (
                                                        <div
                                                            key={term}
                                                            className="flex items-center justify-between gap-4 py-3"
                                                        >
                                                            <dt className="text-sm text-muted-foreground">
                                                                {term}
                                                            </dt>
                                                            <dd className="text-right text-sm font-bold">
                                                                {value}
                                                            </dd>
                                                        </div>
                                                    ))}
                                                </dl>
                                                <Button
                                                    asChild
                                                    className="w-full"
                                                >
                                                    <Link
                                                        href={preserveDrilldownFilters(
                                                            showCounty.url({
                                                                county: selectedCounty.id,
                                                            }),
                                                            page.url,
                                                        )}
                                                    >
                                                        {
                                                            copy.open_county_record
                                                        }
                                                    </Link>
                                                </Button>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                {counties.length === 0
                                                    ? copy.contact_admin_for_county_access
                                                    : copy.select_county_map_description}
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            </section>
                        )}
                    </TabsContent>

                    <TabsContent
                        value="action-queue"
                        className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.55fr)]"
                    >
                        <Card className="min-w-0 gap-0 py-0">
                            <CardHeader className="border-b py-5">
                                <CardTitle>
                                    {copy.county_intervention_queue}
                                </CardTitle>
                                <CardDescription>
                                    {copy.county_intervention_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {countyAttention.length > 0 ? (
                                    <div className="divide-y">
                                        {countyAttention.map(
                                            ({ county, grantGap, reasons }) => (
                                                <div
                                                    key={county.id}
                                                    className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center"
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <CountyIdentity
                                                            county={{
                                                                kind: 'county',
                                                                id: county.id,
                                                                code: county.code,
                                                                name: county.name,
                                                                logoUrl:
                                                                    county.logoUrl,
                                                            }}
                                                        />
                                                        <div className="mt-2 flex flex-wrap gap-2">
                                                            {reasons.map(
                                                                (reason) => (
                                                                    <Badge
                                                                        key={
                                                                            reason
                                                                        }
                                                                        variant="outline"
                                                                    >
                                                                        {reason}
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="grid shrink-0 grid-cols-2 gap-x-6 gap-y-1 text-sm sm:text-right">
                                                        <span className="text-muted-foreground">
                                                            {copy.score}
                                                        </span>
                                                        <strong>
                                                            {county.latestScore ===
                                                            null
                                                                ? 'Pending'
                                                                : `${county.latestScore}%`}
                                                        </strong>
                                                        <span className="text-muted-foreground">
                                                            {copy.funding_gap}
                                                        </span>
                                                        <strong>
                                                            {formatCompactCurrency(
                                                                grantGap,
                                                            )}
                                                        </strong>
                                                    </div>
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="icon"
                                                    >
                                                        <Link
                                                            href={preserveDrilldownFilters(
                                                                showCounty.url({
                                                                    county: county.id,
                                                                }),
                                                                page.url,
                                                            )}
                                                            aria-label={interpolate(
                                                                copy.open_county_record_label,
                                                                {
                                                                    county: county.name,
                                                                },
                                                            )}
                                                        >
                                                            <ArrowRight />
                                                        </Link>
                                                    </Button>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <div className="grid min-h-64 place-items-center p-8 text-center">
                                        <div className="max-w-sm">
                                            <CircleCheckBig className="mx-auto size-9 text-primary" />
                                            <h3 className="mt-3 font-semibold">
                                                {copy.no_county_flags}
                                            </h3>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {
                                                    copy.no_county_flags_description
                                                }
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{copy.exception_register}</CardTitle>
                                <CardDescription>
                                    {copy.exception_register_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {operationalRows.map((signal) => (
                                    <div
                                        key={signal.label}
                                        className="flex items-start justify-between gap-4 border-b pb-3 last:border-0 last:pb-0"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {signal.label}
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {signal.detail}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                signal.value > 0
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {signal.value}
                                        </Badge>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent
                        value="cycles"
                        className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(19rem,0.8fr)]"
                    >
                        <Card className="min-w-0">
                            <CardHeader>
                                <CardTitle>
                                    {copy.performance_across_cycles}
                                </CardTitle>
                                <CardDescription>
                                    {copy.performance_across_cycles_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ChartContainer
                                    config={cycleChartConfig}
                                    className="h-80 w-full"
                                >
                                    <LineChart
                                        data={cycleTrend}
                                        accessibilityLayer
                                        margin={{ left: 8, right: 8 }}
                                    >
                                        <CartesianGrid vertical={false} />
                                        <XAxis
                                            dataKey="code"
                                            tickLine={false}
                                            axisLine={false}
                                            minTickGap={24}
                                        />
                                        <YAxis
                                            domain={[0, 100]}
                                            tickLine={false}
                                            axisLine={false}
                                            width={30}
                                        />
                                        <ChartTooltip
                                            content={<ChartTooltipContent />}
                                        />
                                        <ChartLegend
                                            content={<ChartLegendContent />}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="averageScore"
                                            stroke="var(--color-averageScore)"
                                            strokeWidth={3}
                                            dot={{ r: 3 }}
                                            connectNulls
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="completionPercent"
                                            stroke="var(--color-completionPercent)"
                                            strokeWidth={3}
                                            dot={{ r: 3 }}
                                        />
                                    </LineChart>
                                </ChartContainer>
                            </CardContent>
                        </Card>

                        <div className="flex min-w-0 flex-col gap-4">
                            {cycleOverview.length > 0 ? (
                                cycleOverview.map((cycle) => (
                                    <Card key={cycle.id} className="gap-4 py-5">
                                        <CardHeader className="flex-row items-start justify-between gap-3">
                                            <div className="flex min-w-0 flex-col gap-1">
                                                <CardTitle className="truncate">
                                                    {cycle.name}
                                                </CardTitle>
                                                <CardDescription>
                                                    {cycle.periodStart}{' '}
                                                    {copy.range_separator}{' '}
                                                    {cycle.periodEnd}
                                                </CardDescription>
                                            </div>
                                            <Badge
                                                variant={
                                                    cycle.selected
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {cycle.selected
                                                    ? 'Selected'
                                                    : cycle.status}
                                            </Badge>
                                        </CardHeader>
                                        <CardContent className="flex flex-col gap-3">
                                            <div className="flex items-center justify-between gap-3 text-sm">
                                                <span className="text-muted-foreground">
                                                    {copy.county_completion}
                                                </span>
                                                <strong>
                                                    {cycle.countiesAssessed}
                                                    {copy.slash}
                                                    {cycle.countiesTotal}{' '}
                                                    {copy.separator}{' '}
                                                    {cycle.completionPercent}
                                                    {copy.percent}
                                                </strong>
                                            </div>
                                            <Progress
                                                value={cycle.completionPercent}
                                            />
                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        {copy.average_score}
                                                    </p>
                                                    <p className="font-bold">
                                                        {cycle.averageScore ===
                                                        null
                                                            ? '—'
                                                            : `${cycle.averageScore}%`}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        {copy.evidence}
                                                    </p>
                                                    <p className="font-bold">
                                                        {cycle.evidenceDocuments.toLocaleString()}
                                                    </p>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))
                            ) : (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            {copy.no_assessment_cycles}
                                        </CardTitle>
                                        <CardDescription>
                                            {
                                                copy.no_assessment_cycles_description
                                            }
                                        </CardDescription>
                                    </CardHeader>
                                </Card>
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent
                        value="delivery"
                        className="grid min-w-0 gap-5 xl:grid-cols-2"
                    >
                        <Card className="min-w-0">
                            <CardHeader>
                                <CardTitle>
                                    {copy.grant_allocation_disbursement}
                                </CardTitle>
                                <CardDescription>
                                    {copy.grant_chart_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ChartContainer
                                    config={fundingChartConfig}
                                    className="h-80 w-full"
                                >
                                    <BarChart
                                        data={fundingSeries}
                                        accessibilityLayer
                                        margin={{ left: 8, right: 8 }}
                                    >
                                        <CartesianGrid vertical={false} />
                                        <XAxis
                                            dataKey="name"
                                            tickLine={false}
                                            axisLine={false}
                                            minTickGap={24}
                                        />
                                        <YAxis
                                            tickFormatter={
                                                formatCompactCurrency
                                            }
                                            tickLine={false}
                                            axisLine={false}
                                            width={62}
                                        />
                                        <ChartTooltip
                                            content={
                                                <ChartTooltipContent
                                                    formatter={(value) =>
                                                        formatCompactCurrency(
                                                            Number(value),
                                                        )
                                                    }
                                                />
                                            }
                                        />
                                        <ChartLegend
                                            content={<ChartLegendContent />}
                                        />
                                        <Bar
                                            dataKey="allocatedGrant"
                                            fill="var(--color-allocatedGrant)"
                                            radius={4}
                                        />
                                        <Bar
                                            dataKey="disbursedGrant"
                                            fill="var(--color-disbursedGrant)"
                                            radius={4}
                                        />
                                    </BarChart>
                                </ChartContainer>
                            </CardContent>
                        </Card>

                        <Card className="min-w-0">
                            <CardHeader>
                                <CardTitle>{copy.evidence_coverage}</CardTitle>
                                <CardDescription>
                                    {copy.evidence_coverage_description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ChartContainer
                                    config={evidenceChartConfig}
                                    className="h-80 w-full"
                                >
                                    <BarChart
                                        data={evidenceSeries}
                                        accessibilityLayer
                                        layout="vertical"
                                        margin={{ left: 8, right: 8 }}
                                    >
                                        <CartesianGrid horizontal={false} />
                                        <XAxis
                                            type="number"
                                            tickLine={false}
                                            axisLine={false}
                                        />
                                        <YAxis
                                            dataKey="name"
                                            type="category"
                                            tickLine={false}
                                            axisLine={false}
                                            width={82}
                                        />
                                        <ChartTooltip
                                            content={<ChartTooltipContent />}
                                        />
                                        <Bar
                                            dataKey="documents"
                                            fill="var(--color-documents)"
                                            radius={4}
                                        />
                                    </BarChart>
                                </ChartContainer>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

function OperationalSignal({
    label,
    value,
    detail,
    tone,
}: {
    label: string;
    value: number;
    detail: string;
    tone: 'warning' | 'critical';
}) {
    const Icon = tone === 'critical' ? ShieldAlert : AlertTriangle;

    return (
        <div className="flex min-h-28 gap-3 bg-card p-4">
            <Icon
                className={
                    value > 0
                        ? 'mt-0.5 size-5 shrink-0 text-destructive'
                        : 'mt-0.5 size-5 shrink-0 text-muted-foreground'
                }
                aria-hidden="true"
            />
            <div className="min-w-0">
                <div className="flex items-baseline gap-2">
                    <strong className="text-2xl tracking-tight">
                        {value.toLocaleString()}
                    </strong>
                    <span className="text-sm font-medium">{label}</span>
                </div>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    {detail}
                </p>
            </div>
        </div>
    );
}

Dashboard.layout = { breadcrumbs: [] };
