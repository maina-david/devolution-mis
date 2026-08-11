import { Head, Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    ChartNoAxesCombined,
    FileCheck2,
    Gauge,
    MapPinned,
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
import PendingInvitationsModal from '@/components/pending-invitations-modal';
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
import { preserveDrilldownFilters } from '@/lib/preserve-drilldown-filters';
import { formatCurrency } from '@/lib/reference-catalog';
import { dashboard } from '@/routes';
import { show as showCounty } from '@/routes/counties';
import type { DashboardInvitation } from '@/types';

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
    pendingInvitations?: DashboardInvitation[];
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
    filters: { from?: string; to?: string; search?: string; cycle_id?: string };
};

const formatCompactCurrency = (value: number) =>
    formatCurrency(value, undefined, {
        notation: 'compact',
        maximumFractionDigits: 1,
    });

const cycleChartConfig = {
    averageScore: { label: 'Average score', color: 'var(--chart-1)' },
    completionPercent: { label: 'Cycle completion', color: 'var(--chart-2)' },
} satisfies ChartConfig;

const fundingChartConfig = {
    allocatedGrant: { label: 'Allocated', color: 'var(--chart-2)' },
    disbursedGrant: { label: 'Disbursed', color: 'var(--chart-1)' },
} satisfies ChartConfig;

const evidenceChartConfig = {
    documents: { label: 'Evidence documents', color: 'var(--chart-3)' },
} satisfies ChartConfig;

export default function Dashboard({
    pendingInvitations = [],
    dashboardProfile,
    stats,
    counties,
    cycleOverview,
    filters,
}: Props) {
    const page = usePage();
    const { currentTeam } = page.props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const [selectedCounty, setSelectedCounty] = useState<CountyMetric | null>(
        dashboardProfile.mapScope === 'none' ? null : (counties[0] ?? null),
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
    const cards = [
        {
            label: 'Counties in view',
            value: stats.counties,
            detail: `${stats.assessed} assessed · ${stats.pending} pending`,
            icon: MapPinned,
        },
        {
            label: 'Average score',
            value: stats.averageScore === null ? '—' : `${stats.averageScore}%`,
            detail: 'Across completed assessments',
            icon: TrendingUp,
        },
        {
            label: 'Evidence documents',
            value: stats.documents.toLocaleString(),
            detail: 'Within the selected period',
            icon: FileCheck2,
        },
        {
            label: 'Grant disbursement',
            value: `${disbursementRate}%`,
            detail: `${formatCompactCurrency(stats.disbursedGrants)} disbursed`,
            icon: Banknote,
        },
    ];

    return (
        <>
            <Head title={`${dashboardProfile.roleLabel} dashboard`} />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
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
                        <Badge className="w-fit border-white/20 bg-white/10 px-3 py-1.5 text-white">
                            {dashboardProfile.roleLabel}
                        </Badge>
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
                            <Gauge /> Overview
                        </TabsTrigger>
                        <TabsTrigger value="cycles">
                            <ChartNoAxesCombined /> Assessment cycles
                        </TabsTrigger>
                        <TabsTrigger value="delivery">
                            <Banknote /> Funds & evidence
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent
                        value="overview"
                        className="flex flex-col gap-5"
                    >
                        <section
                            aria-label="Portfolio statistics"
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

                        {dashboardProfile.mapScope !== 'none' && (
                            <section className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(19rem,0.7fr)]">
                                <Card className="min-w-0 gap-0 overflow-hidden py-0">
                                    <CardHeader className="py-6">
                                        <CardDescription>
                                            County coverage
                                        </CardDescription>
                                        <CardTitle>
                                            Kenya assessment map
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
                                            County detail
                                        </CardDescription>
                                        <CardTitle>
                                            {selectedCounty?.name ??
                                                'No county assigned'}
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
                                                            'Latest cycle',
                                                            selectedCounty.latestCycle ??
                                                                'Not started',
                                                        ],
                                                        [
                                                            'Assessment score',
                                                            selectedCounty.latestScore ===
                                                            null
                                                                ? 'Pending'
                                                                : `${selectedCounty.latestScore}%`,
                                                        ],
                                                        [
                                                            'Documents collected',
                                                            selectedCounty.documents.toLocaleString(),
                                                        ],
                                                        [
                                                            'Grant allocated',
                                                            formatCompactCurrency(
                                                                selectedCounty.allocatedGrant,
                                                            ),
                                                        ],
                                                        [
                                                            'Grant disbursed',
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
                                                {currentTeam && (
                                                    <Button
                                                        asChild
                                                        className="w-full"
                                                    >
                                                        <Link
                                                            href={preserveDrilldownFilters(
                                                                showCounty.url({
                                                                    current_team:
                                                                        currentTeam.slug,
                                                                    county: selectedCounty.id,
                                                                }),
                                                                page.url,
                                                            )}
                                                        >
                                                            Open complete county
                                                            record
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Contact an administrator to
                                                assign county access.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            </section>
                        )}
                    </TabsContent>

                    <TabsContent
                        value="cycles"
                        className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(19rem,0.8fr)]"
                    >
                        <Card className="min-w-0">
                            <CardHeader>
                                <CardTitle>Performance across cycles</CardTitle>
                                <CardDescription>
                                    Average verified score and county completion
                                    for the authorized portfolio.
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
                                                    {cycle.periodStart} –{' '}
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
                                                    County completion
                                                </span>
                                                <strong>
                                                    {cycle.countiesAssessed}/
                                                    {cycle.countiesTotal} ·{' '}
                                                    {cycle.completionPercent}%
                                                </strong>
                                            </div>
                                            <Progress
                                                value={cycle.completionPercent}
                                            />
                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        Average score
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
                                                        Evidence
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
                                            No assessment cycles
                                        </CardTitle>
                                        <CardDescription>
                                            Cycle analytics will appear after an
                                            assessment cycle is configured.
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
                                    Grant allocation and disbursement
                                </CardTitle>
                                <CardDescription>
                                    Top counties by allocated value in the
                                    current filtered portfolio.
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
                                <CardTitle>Evidence coverage</CardTitle>
                                <CardDescription>
                                    Counties with the largest document
                                    collections in the selected period.
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

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
