import { Form, Head, usePage } from '@inertiajs/react';
import { Download, Handshake, Radar } from 'lucide-react';
import {
    analyze,
    resolveAlert,
} from '@/actions/App/Http/Controllers/PartnerCoordinationController';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import PartnerAgreementRegister from '@/components/partner-agreement-register';
import type { PartnerAgreement } from '@/components/partner-agreement-register';
import PartnerCollaborationPlans from '@/components/partner-collaboration-plans';
import type { CollaborationPlan } from '@/components/partner-collaboration-plans';
import PartnerContributionRegister from '@/components/partner-contribution-register';
import type { PartnerContribution } from '@/components/partner-contribution-register';
import PartnerCoordinationForms from '@/components/partner-coordination-forms';
import PartnerOperationalAlerts from '@/components/partner-operational-alerts';
import type { PartnerOperationalAlert } from '@/components/partner-operational-alerts';
import PartnerPortfolioMap from '@/components/partner-portfolio-map';
import type { PartnerPortfolioCounty } from '@/components/partner-portfolio-map';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { exportMethod } from '@/routes/workspace';

type Option = {
    id: string;
    name: string;
    code?: string;
    email?: string;
    title?: string;
};
type Workspace = {
    title: string;
    description: string;
    columns: string[];
    rows: WorkspaceRow[];
    pagination: WorkspacePagination;
};
type Alert = {
    id: string;
    type: string;
    severity: string;
    status: string;
    summary: string;
    primaryPartner: string;
    relatedPartner: string;
    detectedAt: string | null;
    resolution: string | null;
};
type Props = {
    workspace: Workspace;
    filters: {
        from?: string;
        to?: string;
        search?: string;
        county_id?: string;
        sector_id?: string;
        status?: string;
    };
    capabilities: {
        manage: boolean;
        submitData: boolean;
        resolveAlerts: boolean;
        approveAgreements: boolean;
    };
    alerts: Alert[];
    operationalAlerts: PartnerOperationalAlert[];
    portfolioMap: {
        showFullCountry: boolean;
        counties: PartnerPortfolioCounty[];
    };
    collaborationPlans: CollaborationPlan[];
    agreements: PartnerAgreement[];
    contributions: PartnerContribution[];
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
    options: {
        organizations: Option[];
        counties: Option[];
        sectors: Option[];
        users: Option[];
        partners: Option[];
        projects: Option[];
        actionUsers: Option[];
        actionOrganizations: Option[];
    };
};

export default function PartnerCoordinationIndex({
    workspace,
    filters,
    capabilities,
    alerts,
    operationalAlerts,
    portfolioMap,
    collaborationPlans,
    agreements,
    contributions,
    catalogue,
    options,
}: Props) {
    const { currentTeam } = usePage().props;

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="Partner coordination" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        Development cooperation intelligence
                    </p>
                    <h1 className="mt-3 text-3xl font-bold">
                        Partner coordination
                    </h1>
                    <p className="mt-3 max-w-3xl text-[#c7d6dd]">
                        A governed directory of partners, agreements, financial
                        and in-kind contributions, geographic coverage,
                        overlaps, and collaboration opportunities.
                    </p>
                </section>
                <PartnerCoordinationForms
                    teamSlug={currentTeam.slug}
                    capabilities={capabilities}
                    {...options}
                />
                <PartnerPortfolioMap {...portfolioMap} />
                <PartnerCollaborationPlans
                    teamSlug={currentTeam.slug}
                    plans={collaborationPlans}
                    partners={options.partners}
                    counties={options.counties}
                    actionUsers={options.actionUsers}
                    actionOrganizations={options.actionOrganizations}
                    catalogue={catalogue}
                    canManage={capabilities.manage}
                    filters={filters}
                />
                <PartnerAgreementRegister
                    teamSlug={currentTeam.slug}
                    agreements={agreements}
                    canManage={capabilities.manage}
                    canApprove={capabilities.approveAgreements}
                />
                <PartnerContributionRegister
                    teamSlug={currentTeam.slug}
                    contributions={contributions}
                />
                <PartnerOperationalAlerts
                    teamSlug={currentTeam.slug}
                    alerts={operationalAlerts}
                    canResolve={capabilities.resolveAlerts}
                />
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'county_id',
                            label: 'County',
                            options: options.counties.map((county) => ({
                                id: county.id,
                                name: county.name,
                            })),
                            value: filters.county_id,
                        },
                        {
                            key: 'sector_id',
                            label: 'Sector',
                            options: options.sectors.map((sector) => ({
                                id: sector.id,
                                name: sector.name,
                            })),
                            value: filters.sector_id,
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            options: [
                                { id: 'draft', name: 'Draft' },
                                {
                                    id: 'pending_approval',
                                    name: 'Pending approval',
                                },
                                { id: 'active', name: 'Active' },
                                { id: 'open', name: 'Open' },
                                {
                                    id: 'in_progress',
                                    name: 'In progress',
                                },
                                { id: 'completed', name: 'Completed' },
                                { id: 'suspended', name: 'Suspended' },
                                { id: 'rejected', name: 'Rejected' },
                            ],
                            value: filters.status,
                        },
                    ]}
                />
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="flex gap-3">
                            <Handshake
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
                                                current_team: currentTeam.slug,
                                                workspace: 'partners',
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
                                    teamSlug: currentTeam.slug,
                                    workspace: 'partners',
                                    filters,
                                }}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title="No matching partner profiles"
                                description="Adjust the search or reporting dates, or add an authorized development partner profile."
                                className="min-h-72 border-0"
                            />
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="flex gap-3">
                            <Radar
                                className="text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <CardTitle>
                                    Collaboration intelligence
                                </CardTitle>
                                <CardDescription>
                                    Potential overlaps and synergies detected
                                    from shared projects, counties, and sectors.
                                </CardDescription>
                            </div>
                        </div>
                        {capabilities.manage && (
                            <Form action={analyze(currentTeam.slug)}>
                                {({ processing }) => (
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Refresh analysis
                                    </Button>
                                )}
                            </Form>
                        )}
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {alerts.length === 0 && (
                            <WorkspaceEmptyState
                                title="No collaboration alerts"
                                description="Run the portfolio analysis after partner activities and commitments have been recorded."
                                className="min-h-52 border"
                            />
                        )}
                        {alerts.map((alert) => (
                            <div
                                key={alert.id}
                                className="grid gap-3 rounded-lg border p-4 lg:grid-cols-[1fr_auto] lg:items-center"
                            >
                                <div className="grid gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {alert.type}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {alert.severity}
                                        </Badge>
                                        <Badge variant="outline">
                                            {alert.status}
                                        </Badge>
                                    </div>
                                    <p className="font-medium">
                                        {alert.primaryPartner} ×{' '}
                                        {alert.relatedPartner}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {alert.summary}
                                    </p>
                                    {alert.resolution && (
                                        <p className="text-sm">
                                            Resolution: {alert.resolution}
                                        </p>
                                    )}
                                </div>
                                {capabilities.resolveAlerts &&
                                    alert.status === 'open' && (
                                        <FormSheet
                                            title="Resolve collaboration alert"
                                            triggerLabel="Resolve alert"
                                            description={`${alert.primaryPartner} and ${alert.relatedPartner}: record the agreed resolution and next action.`}
                                        >
                                            <Form
                                                action={resolveAlert({
                                                    current_team:
                                                        currentTeam.slug,
                                                    alert: alert.id,
                                                })}
                                                className="grid gap-4"
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="status"
                                                            value="resolved"
                                                        />
                                                        <Input
                                                            name="resolution"
                                                            required
                                                            placeholder="Resolution and next action"
                                                            aria-label="Resolution"
                                                        />
                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Resolve alert
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        </FormSheet>
                                    )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
