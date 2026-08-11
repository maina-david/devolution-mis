import { Head, usePage } from '@inertiajs/react';
import { DownloadIcon, ShieldCheck } from 'lucide-react';
import DateRangeFilter from '@/components/date-range-filter';
import EvaluationFindingRegister from '@/components/evaluation-finding-register';
import type { EvaluationFindingItem } from '@/components/evaluation-finding-register';
import IndicatorDefinitionForm from '@/components/indicator-definition-form';
import IndicatorDefinitionRegister from '@/components/indicator-definition-register';
import type { IndicatorDefinitionItem } from '@/components/indicator-definition-register';
import IndicatorObservationForm from '@/components/indicator-observation-form';
import IndicatorVerificationAction from '@/components/indicator-verification-action';
import MonitoringResultsDashboard from '@/components/monitoring-results-dashboard';
import type { MonitoringResults } from '@/components/monitoring-results-dashboard';
import ProgrammeEvaluationPanel from '@/components/programme-evaluation-panel';
import type { EvaluationItem } from '@/components/programme-evaluation-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string };
type Indicator = Option & {
    code: string;
    value_type: string;
    unit_of_measure: string;
};
type Props = {
    workspace: {
        title: string;
        description: string;
        columns: string[];
        rows: WorkspaceRow[];
        pagination: WorkspacePagination;
    };
    capabilities: {
        manageIndicators: boolean;
        submitData: boolean;
        verifyData: boolean;
        manageEvaluations: boolean;
        approveEvaluations: boolean;
    };
    filters: {
        from?: string;
        to?: string;
        search?: string;
        countyId?: string;
        sectorId?: string;
        status?: string;
    };
    results: MonitoringResults;
    catalogue: { available: boolean; version?: number; effectiveFrom?: string | null; checksum?: string };
    options: {
        indicators: Indicator[];
        definitions: IndicatorDefinitionItem[];
        counties: Option[];
        programmes: Option[];
        sectors: Option[];
        evaluations: EvaluationItem[];
        findings: EvaluationFindingItem[];
        findingOwners: Option[];
        verificationStatuses: Option[];
    };
};

export default function MonitoringEvaluationIndex({
    workspace,
    capabilities,
    filters,
    results,
    options,
    catalogue,
}: Props) {
    const { auth, currentTeam } = usePage().props;

    if (!currentTeam) {
        return null;
    }

    const badges = [
        capabilities.manageIndicators && 'Manage indicators',
        capabilities.submitData && 'Submit data',
        capabilities.verifyData && 'Verify data',
    ].filter(Boolean) as string[];

    return (
        <>
            <Head title={workspace.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Results and learning control plane
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {workspace.title}
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-[#c7d6dd] sm:text-base">
                                {workspace.description}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {badges.map((badge) => (
                                <Badge
                                    key={badge}
                                    className="border-white/20 bg-white/10 text-white"
                                >
                                    <ShieldCheck aria-hidden="true" />
                                    {badge}
                                </Badge>
                            ))}
                        </div>
                    </div>
                </section>

                {capabilities.manageIndicators && (
                    <IndicatorDefinitionForm
                        teamSlug={currentTeam.slug}
                        sectors={options.sectors}
                        programmes={options.programmes}
                        catalogue={catalogue}
                    />
                )}
                {capabilities.manageIndicators && (
                    <IndicatorDefinitionRegister
                        teamSlug={currentTeam.slug}
                        definitions={options.definitions}
                        currentUserId={auth.user.id}
                    />
                )}
                {(capabilities.manageEvaluations ||
                    capabilities.approveEvaluations) && (
                    <ProgrammeEvaluationPanel
                        teamSlug={currentTeam.slug}
                        programmes={options.programmes}
                        counties={options.counties}
                        evaluations={options.evaluations}
                        canManage={capabilities.manageEvaluations}
                        canApprove={capabilities.approveEvaluations}
                        filters={filters}
                    />
                )}
                <EvaluationFindingRegister
                    teamSlug={currentTeam.slug}
                    findings={options.findings}
                    evaluations={options.evaluations.map((evaluation) => ({
                        id: evaluation.id,
                        name: evaluation.title,
                        status: evaluation.status,
                    }))}
                    owners={options.findingOwners}
                    canManage={capabilities.manageEvaluations}
                    canVerify={capabilities.approveEvaluations}
                    currentUserId={auth.user.id}
                />
                {capabilities.submitData && (
                    <IndicatorObservationForm
                        teamSlug={currentTeam.slug}
                        indicators={options.indicators}
                        counties={options.counties}
                        programmes={options.programmes}
                    />
                )}

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'county_id',
                            label: 'County',
                            options: options.counties,
                            value: filters.countyId,
                        },
                        {
                            key: 'sector_id',
                            label: 'Sector',
                            options: options.sectors,
                            value: filters.sectorId,
                        },
                        {
                            key: 'status',
                            label: 'Verification status',
                            options: options.verificationStatuses,
                            value: filters.status,
                        },
                    ]}
                />
                <MonitoringResultsDashboard
                    teamSlug={currentTeam.slug}
                    results={results}
                />
                <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">
                                Indicator data register
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {workspace.pagination.total.toLocaleString()}{' '}
                                scoped observations with traceable provenance
                            </p>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <DownloadIcon data-icon="inline-start" />
                                    Export
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>
                                    Observation register
                                </DropdownMenuLabel>
                                <DropdownMenuGroup>
                                    {['csv', 'xlsx', 'pdf', 'json'].map(
                                        (format) => (
                                            <DropdownMenuItem
                                                key={format}
                                                asChild
                                            >
                                                <a
                                                    href={exportMethod.url(
                                                        {
                                                            current_team:
                                                                currentTeam.slug,
                                                            workspace:
                                                                'monitoring-evaluation',
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
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel>
                                    Target performance
                                </DropdownMenuLabel>
                                <DropdownMenuGroup>
                                    {['csv', 'xlsx', 'pdf', 'json'].map(
                                        (format) => (
                                            <DropdownMenuItem
                                                key={`performance-${format}`}
                                                asChild
                                            >
                                                <a
                                                    href={exportMethod.url(
                                                        {
                                                            current_team:
                                                                currentTeam.slug,
                                                            workspace:
                                                                'monitoring-performance',
                                                            format,
                                                        },
                                                        { query: filters },
                                                    )}
                                                >
                                                    {format.toUpperCase()}{' '}
                                                    variance report
                                                </a>
                                            </DropdownMenuItem>
                                        ),
                                    )}
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    {workspace.rows.length ? (
                        <WorkspaceDataTable
                            columns={workspace.columns}
                            rows={workspace.rows}
                            pagination={workspace.pagination}
                            bulkExport={{
                                teamSlug: currentTeam.slug,
                                workspace: 'monitoring-evaluation',
                                filters,
                            }}
                            renderActions={
                                capabilities.verifyData
                                    ? (row) => (
                                          <IndicatorVerificationAction
                                              teamSlug={currentTeam.slug}
                                              observationId={row.id}
                                              status={row.status}
                                          />
                                      )
                                    : undefined
                            }
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No indicator observations"
                            description="Approve an indicator, then submit a target, baseline, or actual for an authorized county and reporting period."
                            className="min-h-72 border-0"
                        />
                    )}
                </section>
            </div>
        </>
    );
}
