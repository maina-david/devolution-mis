import { Form, Head, usePage } from '@inertiajs/react';
import { Download, Handshake, Radar } from 'lucide-react';
import {
    analyze,
    resolveAlert,
} from '@/actions/App/Http/Controllers/PartnerCoordinationController';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import InputError from '@/components/input-error';
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
import { interpolate } from '@/hooks/use-localization';
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
    const copy = usePage().props.localization.partnerCoordination;

    return (
        <>
            <Head title={copy.partner_coordination} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        {copy.development_cooperation_intelligence}
                    </p>
                    <h1 className="mt-3 text-3xl font-bold">
                        {copy.partner_coordination}
                    </h1>
                    <p className="mt-3 max-w-3xl text-[#c7d6dd]">
                        {copy.partner_coordination_description}
                    </p>
                </section>
                <PartnerCoordinationForms
                    capabilities={capabilities}
                    {...options}
                />
                <PartnerPortfolioMap {...portfolioMap} />
                <PartnerCollaborationPlans
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
                    agreements={agreements}
                    canManage={capabilities.manage}
                    canApprove={capabilities.approveAgreements}
                />
                <PartnerContributionRegister contributions={contributions} />
                <PartnerOperationalAlerts
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
                            label: copy.county,
                            options: options.counties.map((county) => ({
                                id: county.id,
                                name: county.name,
                            })),
                            value: filters.county_id,
                        },
                        {
                            key: 'sector_id',
                            label: copy.sector,
                            options: options.sectors.map((sector) => ({
                                id: sector.id,
                                name: sector.name,
                            })),
                            value: filters.sector_id,
                        },
                        {
                            key: 'status',
                            label: copy.status,
                            options: [
                                { id: 'draft', name: copy.status_draft },
                                {
                                    id: 'pending_approval',
                                    name: copy.status_pending_approval,
                                },
                                { id: 'active', name: copy.status_active },
                                { id: 'open', name: copy.status_open },
                                {
                                    id: 'in_progress',
                                    name: copy.status_in_progress,
                                },
                                {
                                    id: 'completed',
                                    name: copy.status_completed,
                                },
                                {
                                    id: 'suspended',
                                    name: copy.status_suspended,
                                },
                                { id: 'rejected', name: copy.status_rejected },
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
                                            { workspace: 'partners', format },
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
                                    workspace: 'partners',
                                    filters,
                                }}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title={copy.no_matching_partner_profiles}
                                description={
                                    copy.no_matching_partner_profiles_description
                                }
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
                                    {copy.collaboration_intelligence}
                                </CardTitle>
                                <CardDescription>
                                    {
                                        copy.collaboration_intelligence_description
                                    }
                                </CardDescription>
                            </div>
                        </div>
                        {capabilities.manage && (
                            <Form action={analyze()}>
                                {({ processing }) => (
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                        aria-busy={processing}
                                    >
                                        {copy.refresh_analysis}
                                    </Button>
                                )}
                            </Form>
                        )}
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {alerts.length === 0 && (
                            <WorkspaceEmptyState
                                title={copy.no_collaboration_alerts}
                                description={
                                    copy.no_collaboration_alerts_description
                                }
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
                                            {copy[`alert_type_${alert.type}`] ??
                                                alert.type}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {copy[
                                                `severity_${alert.severity}`
                                            ] ?? alert.severity}
                                        </Badge>
                                        <Badge variant="outline">
                                            {copy[`status_${alert.status}`] ??
                                                alert.status}
                                        </Badge>
                                    </div>
                                    <p className="font-medium">
                                        {interpolate(copy.partner_pair, {
                                            primary: alert.primaryPartner,
                                            related: alert.relatedPartner,
                                        })}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {alert.summary}
                                    </p>
                                    {alert.resolution && (
                                        <p className="text-sm">
                                            {interpolate(
                                                copy.resolution_value,
                                                {
                                                    resolution:
                                                        alert.resolution,
                                                },
                                            )}
                                        </p>
                                    )}
                                </div>
                                {capabilities.resolveAlerts &&
                                    alert.status === 'open' && (
                                        <FormSheet
                                            title={
                                                copy.resolve_collaboration_alert
                                            }
                                            triggerLabel={copy.resolve_alert}
                                            description={interpolate(
                                                copy.resolve_collaboration_alert_description,
                                                {
                                                    primary:
                                                        alert.primaryPartner,
                                                    related:
                                                        alert.relatedPartner,
                                                },
                                            )}
                                        >
                                            <Form
                                                action={resolveAlert({
                                                    alert: alert.id,
                                                })}
                                                className="grid gap-4"
                                            >
                                                {({ processing, errors }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="status"
                                                            value="resolved"
                                                        />
                                                        <Input
                                                            name="resolution"
                                                            required
                                                            placeholder={
                                                                copy.resolution_and_next_action
                                                            }
                                                            aria-label={
                                                                copy.resolution
                                                            }
                                                            aria-invalid={Boolean(
                                                                errors.resolution,
                                                            )}
                                                            aria-describedby={
                                                                errors.resolution
                                                                    ? `partner-alert-${alert.id}-resolution-error`
                                                                    : undefined
                                                            }
                                                        />
                                                        <InputError
                                                            id={`partner-alert-${alert.id}-resolution-error`}
                                                            message={
                                                                errors.resolution
                                                            }
                                                        />
                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                            aria-busy={
                                                                processing
                                                            }
                                                        >
                                                            {copy.resolve_alert}
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
