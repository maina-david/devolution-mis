import { Form, Head, usePage } from '@inertiajs/react';
import {
    Download,
    Eye,
    Fingerprint,
    KeyRound,
    MoreHorizontal,
    PackageCheck,
    RadioTower,
    RefreshCw,
    Plus,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
    UserX,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import type { CountyIdentityValue } from '@/components/county-identity';
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
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { DEFAULT_LOCALE } from '@/lib/reference-catalog';
import {
    download as downloadEvidence,
    preview as previewEvidence,
} from '@/routes/evidence';
import {
    decide as decideDelegation,
    review as reviewDelegation,
    revoke as revokeDelegation,
    store as storeDelegation,
} from '@/routes/security-governance/access-delegations';
import {
    decide,
    reinstate,
} from '@/routes/security-governance/access-review-items';
import { store as launchCampaign } from '@/routes/security-governance/access-reviews';
import {
    decide as decideIdentityLifecycle,
    store as storeIdentityLifecycle,
} from '@/routes/security-governance/identity-lifecycle';
import {
    store as storeSecurityIncident,
    transition as transitionSecurityIncident,
} from '@/routes/security-governance/incidents';
import { store as storeSecurityIncidentDocument } from '@/routes/security-governance/incidents/documents';
import { download as downloadSupplyChainArtifact } from '@/routes/security-governance/supply-chain-scans';
import {
    review as reviewThreat,
    store as storeThreat,
} from '@/routes/security-governance/threats';
import { exportMethod } from '@/routes/workspace';

type Option = { value: string; label: string };
type Threat = {
    id: string;
    reference: string;
    title: string;
    category: string;
    asset: string;
    scenario: string;
    threatActor: string | null;
    entryPoints: string[];
    likelihood: number;
    impact: number;
    inherentRiskScore: number;
    existingControls: string[];
    treatmentPlan: string;
    treatmentStatus: string;
    residualRiskScore: number | null;
    riskAcceptanceReference: string | null;
    status: string;
    submittedAt: string;
    reviewedAt: string | null;
    reviewDueAt: string;
    evidenceReferences: string[];
    owner: string | null;
    submitter: string | null;
    reviewer: string | null;
};
type Campaign = {
    id: string;
    reference: string;
    name: string;
    scope: string;
    roleScope: string[];
    status: string;
    periodFrom: string;
    periodTo: string;
    dueAt: string;
    launchedAt: string;
    completedAt: string | null;
    itemCount: number;
    retainedCount: number;
    revokedCount: number;
    remediationCount: number;
    checksum: string | null;
    launcher: string | null;
    reviewer: string | null;
};
type AccessItem = {
    id: string;
    campaign: {
        id: string;
        reference: string;
        name: string;
        status: string;
        reviewerId: string | null;
        dueAt: string;
        checksum: string | null;
    };
    user: {
        id: string | null;
        name: string;
        email: string;
        accessRevokedAt: string | null;
    };
    role: string;
    permissions: string[];
    homeCounty: CountyIdentityValue | null;
    assignedCounties: Array<{ id: string; name: string }>;
    mfaEnabled: boolean;
    passkeyEnabled: boolean;
    lastAuthenticatedAt: string | null;
    decision: string;
    rationale: string | null;
    remediationAction: string | null;
    remediationDueAt: string | null;
    reviewedAt: string | null;
    revokedAt: string | null;
    sessionsRevoked: number;
    reviewer: string | null;
    reinstatedAt: string | null;
    reinstater: string | null;
    reinstatementRationale: string | null;
};
type DelegationUser = Option & { strongAuth: boolean };
type IdentityUser = Option & { revoked: boolean; role: string | null };
type IdentityLifecycle = {
    id: string;
    sourceSystem: string;
    sourceEventId: string;
    sourceEvidenceReference: string;
    sourceChecksum: string;
    eventType: string;
    effectiveAt: string;
    currentAccess: {
        role: string | null;
        home_county_id: string | null;
        assigned_county_ids: string[];
        access_revoked_at: string | null;
    };
    proposedRole: string | null;
    proposedHomeCounty: CountyIdentityValue | null;
    proposedAssignedCountyIds: string[];
    businessReason: string;
    status: string;
    decisionRationale: string | null;
    decidedAt: string | null;
    appliedAt: string | null;
    applicationAttempts: number;
    lastApplicationAttemptAt: string | null;
    applicationErrorCode: string | null;
    sessionsRevoked: number;
    evidenceChecksum: string | null;
    user: {
        id: string;
        name: string;
        email: string;
        accessRevokedAt: string | null;
    };
    requester: string;
    decider: string | null;
    applier: string | null;
};
type AccessDelegation = {
    id: string;
    reference: string;
    accessType: string;
    scopeType: string;
    permissions: string[];
    counties: CountyIdentityValue[];
    businessJustification: string;
    incidentReference: string | null;
    compensatingControls: string | null;
    status: string;
    startsAt: string;
    expiresAt: string;
    approvedAt: string | null;
    activatedAt: string | null;
    expiredAt: string | null;
    revokedAt: string | null;
    reviewedAt: string | null;
    decisionRationale: string | null;
    revocationReason: string | null;
    postUseOutcome: string | null;
    postUseFindings: string | null;
    approvalChecksum: string | null;
    requester: string;
    beneficiary: {
        id: string;
        name: string;
        email: string;
        strongAuth: boolean;
    };
    approver: string | null;
    revoker: string | null;
    reviewer: string | null;
};
type SupplyChainScan = {
    id: string;
    environment: string;
    sourceRevision: string | null;
    sourceState: string;
    composerLockChecksum: string;
    javascriptLockChecksum: string;
    javascriptLockfile: string;
    composerComponentCount: number;
    javascriptComponentCount: number;
    composerAdvisoryCount: number;
    npmInfoCount: number;
    npmLowCount: number;
    npmModerateCount: number;
    npmHighCount: number;
    npmCriticalCount: number;
    findingCodes: string[];
    toolVersions: Record<string, string>;
    sbomFormat: string;
    sbomSpecVersion: string;
    sizeBytes: number | null;
    artifactChecksum: string | null;
    evidenceChecksum: string;
    outcome: string;
    failureCategory: string | null;
    initiatedBy: string;
    startedAt: string;
    completedAt: string;
    downloadable: boolean;
};
type SecurityIncident = {
    id: string;
    reference: string;
    recordType: string;
    playbook: string;
    title: string;
    summary: string;
    affectedServices: string[];
    dataExposure: string;
    severity: string;
    status: string;
    businessImpact: string | null;
    externalReference: string | null;
    exerciseObjectives: string[] | null;
    exerciseOutcome: string;
    detectedAt: string;
    acknowledgementDueAt: string;
    containmentDueAt: string;
    acknowledgedAt: string | null;
    containedAt: string | null;
    eradicatedAt: string | null;
    recoveredAt: string | null;
    closedAt: string | null;
    escalatedAt: string | null;
    nextExerciseDueAt: string | null;
    rootCause: string | null;
    correctiveActions: string | null;
    lessonsLearned: string | null;
    reporter: { id: string; name: string };
    incidentLead: { id: string; name: string };
    closer: string | null;
    events: Array<{
        id: string;
        actorName: string;
        transition: string;
        fromStatus: string;
        toStatus: string;
        narrative: string;
        evidenceReference: string | null;
        occurredAt: string;
        evidenceChecksum: string;
    }>;
    documents: Array<{
        id: string;
        title: string;
        purpose: string;
        sourceType: string;
        scanStatus: string;
        ocrStatus: string;
        checksum: string;
        mimeType: string | null;
        originalName: string | null;
    }>;
};
type PageSet<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    threats: PageSet<Threat>;
    campaigns: Campaign[];
    accessItems: PageSet<AccessItem>;
    delegations: PageSet<AccessDelegation>;
    delegationReferenceData: Record<
        string,
        null | {
            version: number;
            effectiveFrom: string | null;
            checksum: string;
        }
    >;
    supplyChainScans: PageSet<SupplyChainScan>;
    securityIncidents: PageSet<SecurityIncident>;
    identityLifecycle: PageSet<IdentityLifecycle>;
    users: Option[];
    delegationUsers: DelegationUser[];
    identityUsers: IdentityUser[];
    delegablePermissions: Option[];
    counties: CountyIdentityValue[];
    referenceDataCatalogue: {
        available: boolean;
        version?: number;
        effectiveFrom?: string | null;
        checksum?: string;
    };
    roles: Option[];
    filters: Record<string, string | undefined>;
    capabilities: { manage: boolean; certify: boolean; userId: string | null };
};

export default function SecurityGovernance({
    threats,
    campaigns,
    accessItems,
    delegations,
    delegationReferenceData,
    supplyChainScans,
    securityIncidents,
    identityLifecycle,
    users,
    delegationUsers,
    identityUsers,
    delegablePermissions,
    counties,
    referenceDataCatalogue,
    roles,
    filters,
    capabilities,
}: Props) {
    const { routeContext } = usePage().props;

    if (!routeContext) {
        return null;
    }

    const threatRows: WorkspaceRow[] = threats.data.map((threat) => ({
        id: threat.id,
        status: threat.status,
        cells: [
            threat.reference,
            threat.title,
            humanize(threat.category),
            threat.asset,
            `${threat.inherentRiskScore}/25`,
            threat.residualRiskScore ?? 'Pending',
            humanize(threat.treatmentStatus),
            humanize(threat.status),
        ],
    }));
    const identityRows: WorkspaceRow[] = identityLifecycle.data.map(
        (change) => ({
            id: change.id,
            status: change.status,
            cells: [
                change.sourceSystem,
                change.sourceEventId,
                humanize(change.eventType),
                change.user.name,
                change.currentAccess.role
                    ? humanize(change.currentAccess.role)
                    : 'No role',
                change.proposedRole
                    ? humanize(change.proposedRole)
                    : 'Access removal',
                formatDate(change.effectiveAt),
                humanize(change.status),
            ],
        }),
    );
    const accessRows: WorkspaceRow[] = accessItems.data.map((item) => ({
        id: item.id,
        status: item.decision,
        cells: [
            item.campaign.reference,
            item.user.name,
            humanize(item.role),
            item.homeCounty ??
                (item.assignedCounties.length
                    ? `${item.assignedCounties.length} assigned counties`
                    : 'National'),
            item.mfaEnabled || item.passkeyEnabled ? 'Strong auth' : 'Missing',
            item.permissions.length,
            item.lastAuthenticatedAt
                ? formatDate(item.lastAuthenticatedAt)
                : 'No evidence',
            humanize(item.decision),
        ],
    }));
    const delegationRows: WorkspaceRow[] = delegations.data.map(
        (delegation) => ({
            id: delegation.id,
            status: delegation.status,
            cells: [
                delegation.reference,
                humanize(delegation.accessType),
                delegation.beneficiary.name,
                delegation.counties[0] ?? 'National',
                delegationReferenceData[delegation.id]
                    ? `v${delegationReferenceData[delegation.id]!.version} · ${delegationReferenceData[delegation.id]!.checksum}`
                    : 'Legacy · unpinned',
                delegation.permissions.length,
                formatDate(delegation.startsAt),
                formatDate(delegation.expiresAt),
                humanize(delegation.status),
            ],
        }),
    );
    const supplyChainRows: WorkspaceRow[] = supplyChainScans.data.map(
        (scan) => ({
            id: scan.id,
            status: scan.outcome,
            cells: [
                formatDate(scan.startedAt),
                scan.environment,
                scan.sourceRevision?.slice(0, 12) ?? 'Unversioned',
                humanize(scan.sourceState),
                scan.composerComponentCount,
                scan.javascriptComponentCount,
                scan.composerAdvisoryCount,
                `${scan.npmHighCount} / ${scan.npmCriticalCount}`,
                humanize(scan.outcome),
            ],
        }),
    );
    const incidentRows: WorkspaceRow[] = securityIncidents.data.map(
        (incident) => ({
            id: incident.id,
            status: incident.status,
            cells: [
                incident.reference,
                humanize(incident.recordType),
                humanize(incident.playbook),
                incident.title,
                humanize(incident.severity),
                incident.affectedServices.length,
                humanize(incident.dataExposure),
                incident.incidentLead.name,
                formatDate(incident.detectedAt),
                humanize(incident.status),
            ],
        }),
    );
    const highThreats = threats.data.filter(
        (threat) => threat.inherentRiskScore >= 15,
    ).length;
    const pendingAccess = accessItems.data.filter(
        (item) => item.decision === 'pending',
    ).length;

    return (
        <>
            <Head title="Security governance" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 xl:flex-row xl:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Security authorization evidence
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Threat and access assurance centre
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                STRIDE-aligned risk treatment,
                                strong-authentication gates, point-in-time
                                access snapshots, independent certification,
                                session revocation and controlled reinstatement.
                            </p>
                        </div>
                        {capabilities.manage && (
                            <div className="flex flex-wrap gap-2">
                                <ThreatForm users={users} />
                                <CampaignForm users={users} roles={roles} />
                                <DelegationForm
                                    users={delegationUsers}
                                    permissions={delegablePermissions}
                                    counties={counties}
                                    catalogue={referenceDataCatalogue}
                                />
                                <SecurityIncidentForm users={users} />
                                <IdentityLifecycleForm
                                    users={identityUsers}
                                    roles={roles}
                                    counties={counties}
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
                            key: 'status',
                            label: 'Security status',
                            options: [
                                'submitted',
                                'accepted',
                                'rejected',
                                'planned',
                                'in_progress',
                                'mitigated',
                                'pending',
                                'retain',
                                'revoke',
                                'remediate',
                                'scheduled',
                                'active',
                                'expired',
                                'review_pending',
                                'reviewed',
                                'detected',
                                'acknowledged',
                                'contained',
                                'eradicated',
                                'recovered',
                                'closed',
                                'applied',
                                'approved',
                                'application_exception',
                                'rejected',
                            ].map(option),
                            value: filters.status,
                        },
                        {
                            key: 'county_id',
                            label: 'County scope',
                            options: counties.map((county) => ({
                                id: county.id,
                                name: county.name,
                            })),
                            value: filters.county_id,
                        },
                    ]}
                />
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <Metric
                        icon={ShieldAlert}
                        label="Threat scenarios"
                        value={threats.total}
                        detail={`${highThreats} high-risk on this page`}
                    />
                    <Metric
                        icon={Fingerprint}
                        label="Certification campaigns"
                        value={campaigns.length}
                        detail={`${campaigns.filter((campaign) => campaign.status === 'open').length} currently open`}
                    />
                    <Metric
                        icon={UserCheck}
                        label="Access decisions"
                        value={accessItems.total - pendingAccess}
                        detail={`${pendingAccess} pending on this page`}
                    />
                    <Metric
                        icon={UserX}
                        label="Revocations"
                        value={campaigns.reduce(
                            (sum, campaign) => sum + campaign.revokedCount,
                            0,
                        )}
                        detail="Sessions invalidated on revoke"
                    />
                    <Metric
                        icon={PackageCheck}
                        label="Supply-chain scans"
                        value={supplyChainScans.total}
                        detail={`${supplyChainScans.data.filter((scan) => scan.outcome === 'fail').length} failed on this page`}
                    />
                    <Metric
                        icon={RadioTower}
                        label="Security response"
                        value={securityIncidents.total}
                        detail={`${securityIncidents.data.filter((incident) => incident.recordType === 'exercise').length} exercises on this page`}
                    />
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <div className="border-b px-5 py-4">
                        <h2 className="font-bold">
                            Joiner–mover–leaver reconciliation
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Source-referenced identity changes with
                            current/proposed access snapshots, independent
                            approval, effective-time application, controlled
                            exceptions, session revocation and immutable
                            terminal evidence.
                        </p>
                    </div>
                    {identityRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Source',
                                'Event ID',
                                'Change',
                                'Identity',
                                'Current role',
                                'Proposed role',
                                'Effective',
                                'Status',
                            ]}
                            rows={identityRows}
                            pagination={pagination(
                                identityLifecycle,
                                'identity_page',
                            )}
                            bulkExport={{
                                workspace: 'identity-lifecycle',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const change = identityLifecycle.data.find(
                                    (item) => item.id === row.id,
                                );

                                return change ? (
                                    <IdentityLifecycleAction
                                        change={change}
                                        mayDecide={
                                            capabilities.certify &&
                                            change.status === 'pending'
                                        }
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No lifecycle requests"
                            description="Stage a verified joiner, mover or leaver source event, or adjust the current filters."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <SecurityIncidentRegisterHeader
                        filters={filters}
                        count={securityIncidents.total}
                    />
                    {incidentRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Reference',
                                'Record type',
                                'Playbook',
                                'Incident',
                                'Severity',
                                'Services',
                                'Data exposure',
                                'Lead',
                                'Detected',
                                'Status',
                            ]}
                            rows={incidentRows}
                            pagination={pagination(
                                securityIncidents,
                                'incident_page',
                            )}
                            bulkExport={{
                                workspace: 'security-incidents',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const incident = securityIncidents.data.find(
                                    (item) => item.id === row.id,
                                );

                                return incident ? (
                                    <SecurityIncidentAction
                                        incident={incident}
                                        canManage={capabilities.manage}
                                        currentUserId={capabilities.userId}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No security incidents or exercises"
                            description="Record a live incident or explicitly labelled exercise, or adjust the current filters."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <div className="border-b px-5 py-4">
                        <h2 className="font-bold">
                            Software supply-chain evidence
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Lock-derived CycloneDX inventories, dependency
                            advisories, source state and checksum-verified
                            artifacts. Warning and failed scans remain retained.
                        </p>
                    </div>
                    {supplyChainRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Started',
                                'Environment',
                                'Revision',
                                'Source state',
                                'Composer',
                                'JavaScript',
                                'Composer advisories',
                                'NPM high / critical',
                                'Outcome',
                            ]}
                            rows={supplyChainRows}
                            pagination={pagination(
                                supplyChainScans,
                                'scan_page',
                            )}
                            renderActionControl={(row) => {
                                const scan = supplyChainScans.data.find(
                                    (item) => item.id === row.id,
                                );

                                return scan ? (
                                    <SupplyChainScanAction scan={scan} />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No supply-chain evidence"
                            description="Run the controlled supply-chain assurance command or adjust the current date and status filters."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <DelegationRegisterHeader
                        filters={filters}
                        count={delegations.total}
                    />
                    {delegationRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Reference',
                                'Type',
                                'Beneficiary',
                                'County scope',
                                'Catalogue lineage',
                                'Permissions',
                                'Starts',
                                'Expires',
                                'Status',
                            ]}
                            rows={delegationRows}
                            pagination={pagination(
                                delegations,
                                'delegations_page',
                            )}
                            bulkExport={{
                                workspace: 'access-delegations',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const delegation = delegations.data.find(
                                    (item) => item.id === row.id,
                                );

                                return delegation ? (
                                    <DelegationAction
                                        delegation={delegation}
                                        capabilities={capabilities}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No temporary access grants"
                            description="No delegated or emergency access records match the current filters."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <div className="border-b px-5 py-4">
                        <h2 className="font-bold">
                            Threat and treatment register
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Scenarios, inherent risk, controls, independent
                            review and residual exposure.
                        </p>
                    </div>
                    {threatRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Reference',
                                'Threat',
                                'Category',
                                'Asset',
                                'Inherent risk',
                                'Residual risk',
                                'Treatment',
                                'Status',
                            ]}
                            rows={threatRows}
                            pagination={pagination(threats, 'threat_page')}
                            renderActionControl={(row) => {
                                const threat = threats.data.find(
                                    (item) => item.id === row.id,
                                );

                                return threat ? (
                                    <ThreatAction
                                        threat={threat}
                                        canManage={capabilities.manage}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No matching threats"
                            description="Register a threat scenario or adjust the filters."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section>
                    <div className="mb-3">
                        <h2 className="text-lg font-bold">
                            Access certification campaigns
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Immutable campaign evidence closes only after every
                            identity has a decision.
                        </p>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                        {campaigns.map((campaign) => (
                            <CampaignCard
                                key={campaign.id}
                                campaign={campaign}
                            />
                        ))}
                        {campaigns.length === 0 && (
                            <WorkspaceEmptyState
                                title="No access reviews launched"
                                description="Launch the first role-scoped certification campaign with an independent reviewer."
                                className="min-h-56 lg:col-span-2 xl:col-span-3"
                            />
                        )}
                    </div>
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <RegisterHeader
                        filters={filters}
                        count={accessItems.total}
                    />
                    {accessRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Campaign',
                                'Identity',
                                'Role',
                                'County scope',
                                'Authentication',
                                'Permissions',
                                'Last active',
                                'Decision',
                            ]}
                            rows={accessRows}
                            pagination={pagination(accessItems, 'access_page')}
                            bulkExport={{
                                workspace: 'security-governance',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const item = accessItems.data.find(
                                    (entry) => entry.id === row.id,
                                );

                                return item ? (
                                    <AccessAction
                                        item={item}
                                        capabilities={capabilities}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No access review items"
                            description="No certification items match the current filters."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
            </div>
        </>
    );
}

function IdentityLifecycleForm({
    users,
    roles,
    counties,
}: {
    users: IdentityUser[];
    roles: Option[];
    counties: CountyIdentityValue[];
}) {
    const [eventType, setEventType] = useState('mover');

    return (
        <FormSheet
            title="Stage identity lifecycle change"
            description="Capture an already verified HR or IAM source event. A different access certifier must decide it before IDMIS access changes."
            triggerLabel="Identity lifecycle"
            icon={RefreshCw}
            size="xl"
        >
            <Form action={storeIdentityLifecycle()} className="grid gap-5 pt-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <SearchableSelect
                        id="identity-event-type"
                        name="event_type"
                        label="Lifecycle event"
                        options={['joiner', 'mover', 'leaver'].map(option)}
                        value={eventType}
                        onValueChange={setEventType}
                    />
                    <SearchableSelect
                        id="identity-target-user"
                        name="user_id"
                        label="Target identity"
                        options={users.map(toSearchOption)}
                    />
                    <Field
                        name="source_system"
                        label="Authoritative source system"
                    />
                    <Field
                        name="source_event_id"
                        label="Unique source event ID"
                    />
                    <Field
                        name="source_evidence_reference"
                        label="Source evidence reference"
                    />
                    <DatePickerField
                        name="effective_at"
                        label="Effective at"
                        includeTime
                        required
                        defaultValue={new Date().toISOString()}
                    />
                    {eventType !== 'leaver' && (
                        <SearchableSelect
                            id="identity-proposed-role"
                            name="proposed_role"
                            label="Proposed role"
                            options={roles.map(toSearchOption)}
                        />
                    )}
                    {eventType !== 'leaver' && (
                        <SearchableSelect
                            id="identity-home-county"
                            name="proposed_home_county_id"
                            label="Proposed home county"
                            options={counties.map((county) => ({
                                id: county.id,
                                name: county.name,
                                logoUrl: county.logoUrl,
                            }))}
                            optional
                        />
                    )}
                </div>
                {eventType !== 'leaver' && (
                    <SearchableMultiSelect
                        name="proposed_assigned_county_ids[]"
                        label="Proposed assigned counties"
                        options={counties.map((county) => ({
                            id: county.id,
                            name: county.name,
                            logoUrl: county.logoUrl,
                        }))}
                        optional
                    />
                )}
                <TextField
                    name="business_reason"
                    label="Business reason and source reconciliation note"
                />
                <Button type="submit">
                    <RefreshCw aria-hidden="true" /> Stage for independent
                    decision
                </Button>
            </Form>
        </FormSheet>
    );
}

function IdentityLifecycleAction({
    change,
    mayDecide,
}: {
    change: IdentityLifecycle;
    mayDecide: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label={`Actions for ${change.sourceEventId}`}
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setOpen(true)}>
                            <Eye aria-hidden="true" /> Review lifecycle evidence
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>
                            {humanize(change.eventType)} · {change.user.name}
                        </SheetTitle>
                        <SheetDescription>
                            Source, before/after scope, decision and checksum
                            evidence.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 px-4 pb-8">
                        <Details
                            entries={[
                                [
                                    'Source event',
                                    `${change.sourceSystem} · ${change.sourceEventId}`,
                                ],
                                [
                                    'Source evidence',
                                    change.sourceEvidenceReference,
                                ],
                                [
                                    'Target identity',
                                    `${change.user.name} · ${change.user.email}`,
                                ],
                                ['Effective', formatDate(change.effectiveAt)],
                                [
                                    'Current role',
                                    change.currentAccess.role
                                        ? humanize(change.currentAccess.role)
                                        : 'None',
                                ],
                                [
                                    'Proposed role',
                                    change.proposedRole
                                        ? humanize(change.proposedRole)
                                        : 'Remove access',
                                ],
                                [
                                    'Proposed home county',
                                    change.proposedHomeCounty?.name ?? 'None',
                                ],
                                [
                                    'Assigned county count',
                                    String(
                                        change.proposedAssignedCountyIds.length,
                                    ),
                                ],
                                ['Status', humanize(change.status)],
                                ['Requester', change.requester],
                                ['Decider', change.decider],
                                ['Applied by', change.applier],
                                [
                                    'Applied at',
                                    change.appliedAt
                                        ? formatDate(change.appliedAt)
                                        : null,
                                ],
                                [
                                    'Application attempts',
                                    String(change.applicationAttempts),
                                ],
                                [
                                    'Last application attempt',
                                    change.lastApplicationAttemptAt
                                        ? formatDate(
                                              change.lastApplicationAttemptAt,
                                          )
                                        : null,
                                ],
                                [
                                    'Application exception',
                                    change.applicationErrorCode
                                        ? humanize(change.applicationErrorCode)
                                        : null,
                                ],
                                [
                                    'Sessions revoked',
                                    String(change.sessionsRevoked),
                                ],
                                ['Source checksum', change.sourceChecksum],
                                ['Evidence checksum', change.evidenceChecksum],
                            ]}
                        />
                        <div className="rounded-xl border p-4">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Business reason
                            </p>
                            <p className="mt-2 text-sm whitespace-pre-wrap">
                                {change.businessReason}
                            </p>
                        </div>
                        {mayDecide && (
                            <Form
                                action={decideIdentityLifecycle({
                                    identityLifecycleRequest: change.id,
                                })}
                                className="grid gap-4 rounded-xl border p-4"
                            >
                                <SearchableSelect
                                    id={`identity-decision-${change.id}`}
                                    name="decision"
                                    label="Independent decision"
                                    options={['approve', 'reject'].map(option)}
                                />
                                <TextField
                                    name="rationale"
                                    label="Decision rationale"
                                />
                                <Button type="submit">
                                    <ShieldCheck aria-hidden="true" /> Record
                                    independent decision
                                </Button>
                            </Form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function SecurityIncidentForm({ users }: { users: Option[] }) {
    const [recordType, setRecordType] = useState('live');

    return (
        <FormSheet
            title="Record security response"
            description="Create a live incident or an explicitly labelled exercise. Severity snapshots the acknowledgement and containment targets."
            triggerLabel="Security response"
            icon={RadioTower}
            size="xl"
        >
            <Form action={storeSecurityIncident()} className="grid gap-5 pt-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <SearchableSelect
                        id="security-incident-type"
                        name="record_type"
                        label="Record type"
                        options={['live', 'exercise'].map(option)}
                        value={recordType}
                        onValueChange={setRecordType}
                    />
                    <SearchableSelect
                        id="security-incident-lead"
                        name="incident_lead_id"
                        label="Assigned incident lead"
                        options={users.map(toSearchOption)}
                    />
                    <SearchableSelect
                        id="security-incident-playbook"
                        name="playbook"
                        label="Response playbook"
                        options={[
                            'credential_compromise',
                            'ransomware',
                            'data_exfiltration',
                            'supplier_compromise',
                            'availability_disruption',
                            'malware',
                            'other',
                        ].map(option)}
                    />
                    <SearchableSelect
                        id="security-incident-severity"
                        name="severity"
                        label="Initial severity"
                        options={['sev1', 'sev2', 'sev3', 'sev4'].map(option)}
                    />
                    <SearchableSelect
                        id="security-incident-exposure"
                        name="data_exposure"
                        label="Data exposure"
                        options={[
                            'none',
                            'suspected',
                            'confirmed',
                            'unknown',
                        ].map(option)}
                    />
                    <DatePickerField
                        name="detected_at"
                        label={
                            recordType === 'exercise'
                                ? 'Exercise started'
                                : 'Detected at'
                        }
                        includeTime
                        required
                    />
                </div>
                <Field name="title" label="Incident or exercise title" />
                <TextField name="summary" label="Controlled incident summary" />
                <Field
                    name="affected_services"
                    label="Affected services (comma-separated)"
                />
                <TextField
                    name="business_impact"
                    label="Observed or simulated business impact"
                    optional
                />
                <Field
                    name="external_reference"
                    label="SOC, service desk, privacy or legal reference"
                    optional
                />
                {recordType === 'exercise' && (
                    <TextField
                        name="exercise_objectives"
                        label="Exercise objectives (comma-separated)"
                    />
                )}
                <Button type="submit">
                    <RadioTower className="size-4" /> Record{' '}
                    {humanize(recordType)}
                </Button>
            </Form>
        </FormSheet>
    );
}

function SecurityIncidentAction({
    incident,
    canManage,
    currentUserId,
}: {
    incident: SecurityIncident;
    canManage: boolean;
    currentUserId: string | null;
}) {
    const [surface, setSurface] = useState<
        'details' | 'transition' | 'upload' | null
    >(null);
    const nextTransition: Record<string, string | undefined> = {
        detected: 'acknowledge',
        acknowledged: 'contain',
        contained: 'eradicate',
        eradicated: 'recover',
        recovered: 'close',
    };
    const transition = nextTransition[incident.status];
    const canTransition =
        canManage &&
        Boolean(transition) &&
        (transition !== 'acknowledge' ||
            incident.incidentLead.id === currentUserId) &&
        (transition !== 'close' ||
            ![incident.reporter.id, incident.incidentLead.id].includes(
                currentUserId ?? '',
            ));

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${incident.reference}`}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-56">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={() => setSurface('details')}
                        >
                            <Eye className="size-4" /> Review response evidence
                        </DropdownMenuItem>
                        {canManage && incident.status !== 'closed' && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('upload')}
                            >
                                <Upload className="size-4" /> Upload evidence
                            </DropdownMenuItem>
                        )}
                        {canTransition && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('transition')}
                            >
                                <ShieldCheck className="size-4" /> Record{' '}
                                {humanize(transition ?? '')}
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-3xl">
                    <SheetHeader>
                        <SheetTitle>{incident.title}</SheetTitle>
                        <SheetDescription>
                            {incident.reference} ·{' '}
                            {humanize(incident.recordType)} ·{' '}
                            {humanize(incident.severity)} ·{' '}
                            {humanize(incident.status)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 py-6">
                        {surface === 'transition' && transition ? (
                            <SecurityIncidentTransitionForm
                                incident={incident}
                                transition={transition}
                            />
                        ) : surface === 'upload' ? (
                            <SecurityIncidentDocumentForm incident={incident} />
                        ) : (
                            <SecurityIncidentDetails incident={incident} />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function SecurityIncidentDetails({ incident }: { incident: SecurityIncident }) {
    return (
        <>
            <dl className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                <EvidenceDetail
                    label="Playbook"
                    value={humanize(incident.playbook)}
                />
                <EvidenceDetail
                    label="Incident lead"
                    value={incident.incidentLead.name}
                />
                <EvidenceDetail label="Summary" value={incident.summary} />
                <EvidenceDetail
                    label="Affected services"
                    value={incident.affectedServices.join(', ')}
                />
                <EvidenceDetail
                    label="Data exposure"
                    value={humanize(incident.dataExposure)}
                />
                <EvidenceDetail
                    label="Business impact"
                    value={incident.businessImpact ?? 'Not recorded'}
                />
                <EvidenceDetail
                    label="Acknowledgement target"
                    value={formatDate(incident.acknowledgementDueAt)}
                />
                <EvidenceDetail
                    label="Containment target"
                    value={formatDate(incident.containmentDueAt)}
                />
                <EvidenceDetail
                    label="External reference"
                    value={incident.externalReference ?? 'Not linked'}
                />
                <EvidenceDetail
                    label="Exercise outcome"
                    value={humanize(incident.exerciseOutcome)}
                />
                <EvidenceDetail
                    label="Root cause"
                    value={incident.rootCause ?? 'Open'}
                />
                <EvidenceDetail
                    label="Corrective actions"
                    value={incident.correctiveActions ?? 'Open'}
                />
            </dl>
            <div>
                <h3 className="font-semibold">Immutable response timeline</h3>
                <div className="mt-3 grid gap-3">
                    {incident.events.map((event) => (
                        <div key={event.id} className="rounded-xl border p-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <Badge variant="outline">
                                    {humanize(event.transition)}
                                </Badge>
                                <span className="text-xs text-muted-foreground">
                                    {formatDate(event.occurredAt)}
                                </span>
                            </div>
                            <p className="mt-2 text-sm">{event.narrative}</p>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {event.actorName} · {event.fromStatus} →{' '}
                                {event.toStatus}
                            </p>
                            <p className="mt-1 font-mono text-[11px] break-all text-muted-foreground">
                                {event.evidenceChecksum}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
            <div>
                <h3 className="font-semibold">Private incident records</h3>
                <div className="mt-3 grid gap-3">
                    {incident.documents.length ? (
                        incident.documents.map((document) => (
                            <div
                                key={document.id}
                                className="flex flex-col justify-between gap-3 rounded-xl border p-4 sm:flex-row sm:items-center"
                            >
                                <div>
                                    <p className="font-medium">
                                        {document.title}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {humanize(document.purpose)} ·{' '}
                                        {document.sourceType} ·{' '}
                                        {document.scanStatus}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    {document.scanStatus === 'clean' &&
                                        document.mimeType &&
                                        [
                                            'application/pdf',
                                            'image/jpeg',
                                            'image/png',
                                            'image/webp',
                                            'text/plain',
                                        ].includes(document.mimeType) && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    href={previewEvidence.url({
                                                        document: document.id,
                                                    })}
                                                >
                                                    Preview
                                                </a>
                                            </Button>
                                        )}
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={downloadEvidence.url({
                                                document: document.id,
                                            })}
                                        >
                                            Download
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No incident records uploaded.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

function SecurityIncidentTransitionForm({
    incident,
    transition,
}: {
    incident: SecurityIncident;
    transition: string;
}) {
    return (
        <Form
            action={transitionSecurityIncident({
                securityIncident: incident.id,
            })}
            className="grid gap-4"
        >
            <input type="hidden" name="transition" value={transition} />
            <TextField
                name="narrative"
                label={`${humanize(transition)} actions and outcome`}
            />
            {['eradicate', 'recover'].includes(transition) && (
                <Field
                    name="evidence_reference"
                    label="Controlled evidence reference"
                />
            )}
            {transition === 'close' && (
                <>
                    <TextField name="root_cause" label="Verified root cause" />
                    <TextField
                        name="corrective_actions"
                        label="Completed and tracked corrective actions"
                    />
                    <TextField
                        name="lessons_learned"
                        label="Lessons and playbook improvements"
                    />
                    {incident.recordType === 'exercise' && (
                        <>
                            <SearchableSelect
                                id={`exercise-outcome-${incident.id}`}
                                name="exercise_outcome"
                                label="Exercise outcome"
                                options={[
                                    'effective',
                                    'partially_effective',
                                    'ineffective',
                                ].map(option)}
                            />
                            <DatePickerField
                                name="next_exercise_due_at"
                                label="Next exercise due"
                                required
                            />
                        </>
                    )}
                </>
            )}
            <Button type="submit">
                <ShieldCheck className="size-4" /> Record {humanize(transition)}
            </Button>
        </Form>
    );
}

function SecurityIncidentDocumentForm({
    incident,
}: {
    incident: SecurityIncident;
}) {
    return (
        <Form
            action={storeSecurityIncidentDocument({
                securityIncident: incident.id,
            })}
            className="grid gap-4"
        >
            <Field name="title" label="Record title" />
            <Field name="category" label="Records category" />
            <SearchableSelect
                id={`incident-document-source-${incident.id}`}
                name="source_type"
                label="Source type"
                options={['scanned', 'digital'].map(option)}
            />
            <SearchableSelect
                id={`incident-document-purpose-${incident.id}`}
                name="record_purpose"
                label="Evidence purpose"
                options={[
                    'investigation',
                    'containment',
                    'recovery',
                    'closure',
                ].map(option)}
            />
            <div className="grid gap-2">
                <Label htmlFor={`incident-document-${incident.id}`}>
                    Scanned or born-digital record
                </Label>
                <Input
                    id={`incident-document-${incident.id}`}
                    name="document"
                    type="file"
                    required
                />
            </div>
            <Button type="submit">
                <Upload className="size-4" /> Upload private evidence
            </Button>
        </Form>
    );
}

function SupplyChainScanAction({ scan }: { scan: SupplyChainScan }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for supply-chain scan ${scan.id}`}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setOpen(true)}>
                            <Eye className="size-4" />
                            Review evidence
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-3xl">
                    <SheetHeader>
                        <SheetTitle>Supply-chain scan evidence</SheetTitle>
                        <SheetDescription>
                            Immutable scan {scan.id} captured from exact
                            dependency lockfiles and source state.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 py-6">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {humanize(scan.outcome)}
                            </Badge>
                            <Badge variant="secondary">
                                {scan.sbomFormat} {scan.sbomSpecVersion}
                            </Badge>
                            <span className="text-sm text-muted-foreground">
                                {formatDate(scan.completedAt)} ·{' '}
                                {scan.initiatedBy}
                            </span>
                        </div>
                        <dl className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                            <EvidenceDetail
                                label="Environment"
                                value={scan.environment}
                            />
                            <EvidenceDetail
                                label="Source"
                                value={`${scan.sourceRevision ?? 'unversioned'} (${humanize(scan.sourceState)})`}
                            />
                            <EvidenceDetail
                                label="Composer components"
                                value={String(scan.composerComponentCount)}
                            />
                            <EvidenceDetail
                                label="JavaScript components"
                                value={String(scan.javascriptComponentCount)}
                            />
                            <EvidenceDetail
                                label="Composer advisories"
                                value={String(scan.composerAdvisoryCount)}
                            />
                            <EvidenceDetail
                                label="NPM vulnerabilities"
                                value={`${scan.npmInfoCount} info · ${scan.npmLowCount} low · ${scan.npmModerateCount} moderate · ${scan.npmHighCount} high · ${scan.npmCriticalCount} critical`}
                            />
                        </dl>
                        <div>
                            <h3 className="text-sm font-semibold">Findings</h3>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {scan.findingCodes.length ? (
                                    scan.findingCodes.map((finding) => (
                                        <Badge key={finding} variant="outline">
                                            {humanize(finding)}
                                        </Badge>
                                    ))
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        No findings recorded.
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="grid gap-3 rounded-xl bg-muted/50 p-4 font-mono text-xs break-all">
                            <EvidenceDetail
                                label="Composer lock SHA-256"
                                value={scan.composerLockChecksum}
                            />
                            <EvidenceDetail
                                label={`${scan.javascriptLockfile} SHA-256`}
                                value={scan.javascriptLockChecksum}
                            />
                            <EvidenceDetail
                                label="Artifact SHA-256"
                                value={scan.artifactChecksum ?? 'Unavailable'}
                            />
                            <EvidenceDetail
                                label="Evidence SHA-256"
                                value={scan.evidenceChecksum}
                            />
                        </div>
                        {scan.downloadable && (
                            <Button asChild className="w-full sm:w-fit">
                                <a
                                    href={downloadSupplyChainArtifact.url([
                                        scan.id,
                                    ])}
                                >
                                    <Download className="size-4" />
                                    Download verified CycloneDX SBOM
                                </a>
                            </Button>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function EvidenceDetail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1">{value}</dd>
        </div>
    );
}

function DelegationForm({
    users,
    permissions,
    counties,
    catalogue,
}: {
    users: DelegationUser[];
    permissions: Option[];
    counties: CountyIdentityValue[];
    catalogue: Props['referenceDataCatalogue'];
}) {
    const [accessType, setAccessType] = useState('delegated');
    const [scopeType, setScopeType] = useState('county_portfolio');

    return (
        <FormSheet
            title="Request temporary access"
            description="Request a time-bound, least-privilege grant for independent approval. Emergency grants are limited to four hours and require post-use review."
            triggerLabel="Temporary access"
            icon={KeyRound}
            size="xl"
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `Using governed catalogue release v${catalogue.version}`
                    : 'Publish an effective reference-data catalogue before requesting temporary access.'
            }
        >
            <Form action={storeDelegation()} className="grid gap-5 pt-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <SearchableSelect
                        id="delegation-beneficiary"
                        name="beneficiary_id"
                        label="Beneficiary with strong authentication"
                        options={users
                            .filter((user) => user.strongAuth)
                            .map(toSearchOption)}
                    />
                    <SearchableSelect
                        id="delegation-type"
                        name="access_type"
                        label="Access type"
                        options={['delegated', 'emergency'].map(option)}
                        value={accessType}
                        onValueChange={setAccessType}
                    />
                    <SearchableSelect
                        id="delegation-scope"
                        name="scope_type"
                        label="Geographic scope"
                        options={['county_portfolio', 'national'].map(option)}
                        value={scopeType}
                        onValueChange={setScopeType}
                    />
                    <SearchableMultiSelect
                        name="permission_scope"
                        label="Least-privilege permissions"
                        options={permissions.map(toSearchOption)}
                    />
                    {scopeType === 'county_portfolio' && (
                        <SearchableMultiSelect
                            name="county_ids"
                            label="Counties in scope"
                            options={counties.map((county) => ({
                                id: county.id,
                                name: county.name,
                            }))}
                        />
                    )}
                    <DatePickerField
                        name="starts_at"
                        label="Access starts"
                        includeTime
                        required
                    />
                    <DatePickerField
                        name="expires_at"
                        label="Access expires"
                        includeTime
                        required
                    />
                    {accessType === 'emergency' && (
                        <Field
                            name="incident_reference"
                            label="Security incident reference"
                        />
                    )}
                </div>
                <TextField
                    name="business_justification"
                    label="Business justification and expected outcome"
                />
                {accessType === 'emergency' && (
                    <TextField
                        name="compensating_controls"
                        label="Compensating controls and monitoring"
                    />
                )}
                <Button type="submit">
                    <KeyRound /> Submit for independent approval
                </Button>
            </Form>
        </FormSheet>
    );
}

function DelegationAction({
    delegation,
    capabilities,
}: {
    delegation: AccessDelegation;
    capabilities: Props['capabilities'];
}) {
    const [surface, setSurface] = useState<
        'detail' | 'decide' | 'revoke' | 'review' | null
    >(null);
    const mayDecide =
        capabilities.certify &&
        delegation.status === 'pending' &&
        delegation.beneficiary.id !== capabilities.userId;
    const mayRevoke =
        (capabilities.manage || capabilities.certify) &&
        ['scheduled', 'active'].includes(delegation.status);
    const mayReview =
        capabilities.certify && delegation.status === 'review_pending';

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${delegation.reference}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setSurface('detail')}>
                            <Eye /> View authorization evidence
                        </DropdownMenuItem>
                        {mayDecide && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('decide')}
                            >
                                <ShieldCheck /> Approve or reject
                            </DropdownMenuItem>
                        )}
                        {mayRevoke && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('revoke')}
                            >
                                <UserX /> Revoke immediately
                            </DropdownMenuItem>
                        )}
                        {mayReview && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('review')}
                            >
                                <Fingerprint /> Post-use review
                            </DropdownMenuItem>
                        )}
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
                            {surface === 'decide'
                                ? 'Independent access decision'
                                : surface === 'revoke'
                                  ? 'Revoke temporary access'
                                  : surface === 'review'
                                    ? 'Emergency post-use review'
                                    : delegation.reference}
                        </SheetTitle>
                        <SheetDescription>
                            {delegation.beneficiary.name} ·{' '}
                            {humanize(delegation.accessType)} ·{' '}
                            {humanize(delegation.status)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-8">
                        {surface === 'decide' ? (
                            <Form
                                action={decideDelegation({
                                    accessDelegation: delegation.id,
                                })}
                                className="grid gap-4"
                            >
                                <SearchableSelect
                                    id={`delegation-decision-${delegation.id}`}
                                    name="decision"
                                    label="Decision"
                                    options={['approve', 'reject'].map(option)}
                                />
                                <TextField
                                    name="decision_rationale"
                                    label="Independent evidence-based rationale"
                                />
                                <Button type="submit">
                                    <ShieldCheck /> Record decision
                                </Button>
                            </Form>
                        ) : surface === 'revoke' ? (
                            <Form
                                action={revokeDelegation({
                                    accessDelegation: delegation.id,
                                })}
                                className="grid gap-4"
                            >
                                <TextField
                                    name="revocation_reason"
                                    label="Immediate revocation reason"
                                />
                                <Button type="submit" variant="destructive">
                                    <UserX /> Revoke access
                                </Button>
                            </Form>
                        ) : surface === 'review' ? (
                            <Form
                                action={reviewDelegation({
                                    accessDelegation: delegation.id,
                                })}
                                className="grid gap-4"
                            >
                                <SearchableSelect
                                    id={`post-use-outcome-${delegation.id}`}
                                    name="post_use_outcome"
                                    label="Post-use outcome"
                                    options={[
                                        'appropriate',
                                        'exception_noted',
                                        'investigation_required',
                                    ].map(option)}
                                />
                                <TextField
                                    name="post_use_findings"
                                    label="Audit findings and follow-up"
                                />
                                <Button type="submit">
                                    <Fingerprint /> Complete independent review
                                </Button>
                            </Form>
                        ) : (
                            <Details
                                entries={[
                                    ['Email', delegation.beneficiary.email],
                                    [
                                        'Authentication',
                                        delegation.beneficiary.strongAuth
                                            ? 'Strong authentication verified'
                                            : 'Not verified',
                                    ],
                                    [
                                        'County scope',
                                        delegation.counties
                                            .map((county) => county.name)
                                            .join(', ') || 'National',
                                    ],
                                    [
                                        'Permissions',
                                        delegation.permissions
                                            .map(humanize)
                                            .join(', '),
                                    ],
                                    [
                                        'Window',
                                        `${formatDate(delegation.startsAt)} – ${formatDate(delegation.expiresAt)}`,
                                    ],
                                    [
                                        'Business justification',
                                        delegation.businessJustification,
                                    ],
                                    [
                                        'Incident reference',
                                        delegation.incidentReference,
                                    ],
                                    [
                                        'Compensating controls',
                                        delegation.compensatingControls,
                                    ],
                                    ['Requester', delegation.requester],
                                    ['Approver', delegation.approver],
                                    [
                                        'Decision rationale',
                                        delegation.decisionRationale,
                                    ],
                                    ['Revoker', delegation.revoker],
                                    [
                                        'Revocation reason',
                                        delegation.revocationReason,
                                    ],
                                    ['Post-use reviewer', delegation.reviewer],
                                    [
                                        'Post-use outcome',
                                        delegation.postUseOutcome
                                            ? humanize(
                                                  delegation.postUseOutcome,
                                              )
                                            : null,
                                    ],
                                    [
                                        'Post-use findings',
                                        delegation.postUseFindings,
                                    ],
                                    [
                                        'Approval checksum',
                                        delegation.approvalChecksum,
                                    ],
                                ]}
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ThreatAction({
    threat,
    canManage,
}: {
    threat: Threat;
    canManage: boolean;
}) {
    const [surface, setSurface] = useState<'detail' | 'review' | null>(null);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${threat.reference}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setSurface('detail')}>
                            <Eye /> View threat
                        </DropdownMenuItem>
                        {canManage && threat.status === 'submitted' && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('review')}
                            >
                                <ShieldCheck /> Independent review
                            </DropdownMenuItem>
                        )}
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
                            {surface === 'review'
                                ? 'Independent threat review'
                                : threat.title}
                        </SheetTitle>
                        <SheetDescription>
                            {threat.reference} · {humanize(threat.category)} ·
                            inherent risk {threat.inherentRiskScore}/25
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-8">
                        {surface === 'review' ? (
                            <ThreatReviewForm threat={threat} />
                        ) : (
                            <Details
                                entries={[
                                    ['Asset', threat.asset],
                                    ['Scenario', threat.scenario],
                                    ['Threat actor', threat.threatActor],
                                    [
                                        'Entry points',
                                        threat.entryPoints.join(', '),
                                    ],
                                    [
                                        'Existing controls',
                                        threat.existingControls.join(', '),
                                    ],
                                    ['Treatment plan', threat.treatmentPlan],
                                    [
                                        'Treatment status',
                                        humanize(threat.treatmentStatus),
                                    ],
                                    [
                                        'Residual risk',
                                        threat.residualRiskScore?.toString() ??
                                            'Pending',
                                    ],
                                    [
                                        'Risk acceptance',
                                        threat.riskAcceptanceReference,
                                    ],
                                    [
                                        'Evidence',
                                        threat.evidenceReferences.join(', ') ||
                                            'None',
                                    ],
                                    ['Owner', threat.owner],
                                    ['Submitter', threat.submitter],
                                    ['Reviewer', threat.reviewer],
                                    ['Review due', threat.reviewDueAt],
                                ]}
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ThreatReviewForm({ threat }: { threat: Threat }) {
    return (
        <Form
            action={reviewThreat({ securityThreat: threat.id })}
            className="grid gap-4"
        >
            <p className="rounded-lg border bg-muted/40 p-3 text-sm">
                The author cannot review this record. Risk acceptance requires
                an accountable external approval reference.
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                <SearchableSelect
                    id={`threat-decision-${threat.id}`}
                    name="decision"
                    label="Review decision"
                    options={['accepted', 'rejected'].map(option)}
                />
                <SearchableSelect
                    id={`treatment-status-${threat.id}`}
                    name="treatment_status"
                    label="Treatment status"
                    options={[
                        'planned',
                        'in_progress',
                        'mitigated',
                        'accepted',
                    ].map(option)}
                />
                <Field
                    name="residual_likelihood"
                    label="Residual likelihood (1–5)"
                    type="number"
                />
                <Field
                    name="residual_impact"
                    label="Residual impact (1–5)"
                    type="number"
                />
                <Field
                    name="risk_acceptance_reference"
                    label="Risk-acceptance reference"
                    optional
                />
                <Field
                    name="evidence_references"
                    label="Evidence references (comma separated)"
                    optional
                />
            </div>
            <TextField name="review_note" label="Independent findings" />
            <Button type="submit">
                <ShieldCheck /> Record threat review
            </Button>
        </Form>
    );
}

function CampaignCard({ campaign }: { campaign: Campaign }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Card>
                <CardHeader className="flex-row items-start justify-between">
                    <div>
                        <CardTitle>
                            {campaign.reference} · {campaign.name}
                        </CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Reviewer: {campaign.reviewer ?? 'Unassigned'}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`View ${campaign.reference}`}
                        onClick={() => setOpen(true)}
                    >
                        <Eye />
                    </Button>
                </CardHeader>
                <CardContent className="grid gap-3">
                    <div className="flex flex-wrap gap-2">
                        <Badge>{humanize(campaign.status)}</Badge>
                        <Badge variant="outline">
                            {campaign.itemCount} identities
                        </Badge>
                        <Badge variant="outline">
                            {campaign.revokedCount} revoked
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {campaign.scope}
                    </p>
                    {campaign.checksum && (
                        <p className="truncate font-mono text-xs text-muted-foreground">
                            SHA-256 {campaign.checksum}
                        </p>
                    )}
                </CardContent>
            </Card>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{campaign.reference}</SheetTitle>
                        <SheetDescription>
                            {campaign.periodFrom} – {campaign.periodTo}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-8">
                        <Details
                            entries={[
                                ['Scope', campaign.scope],
                                [
                                    'Roles',
                                    campaign.roleScope.map(humanize).join(', '),
                                ],
                                ['Launcher', campaign.launcher],
                                ['Reviewer', campaign.reviewer],
                                ['Due', formatDate(campaign.dueAt)],
                                ['Retained', campaign.retainedCount.toString()],
                                ['Revoked', campaign.revokedCount.toString()],
                                [
                                    'Remediation',
                                    campaign.remediationCount.toString(),
                                ],
                                [
                                    'Completed',
                                    campaign.completedAt
                                        ? formatDate(campaign.completedAt)
                                        : null,
                                ],
                                ['Evidence checksum', campaign.checksum],
                            ]}
                        />
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function AccessAction({
    item,
    capabilities,
}: {
    item: AccessItem;
    capabilities: Props['capabilities'];
}) {
    const [surface, setSurface] = useState<
        'detail' | 'decide' | 'reinstate' | null
    >(null);
    const mayDecide =
        capabilities.certify &&
        item.decision === 'pending' &&
        item.campaign.reviewerId === capabilities.userId &&
        item.user.id !== capabilities.userId;
    const mayReinstate =
        capabilities.certify &&
        item.decision === 'revoke' &&
        item.user.accessRevokedAt &&
        !item.reinstatedAt;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${item.user.name}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setSurface('detail')}>
                            <Eye /> View access snapshot
                        </DropdownMenuItem>
                        {mayDecide && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('decide')}
                            >
                                <UserCheck /> Record decision
                            </DropdownMenuItem>
                        )}
                        {mayReinstate && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('reinstate')}
                            >
                                <KeyRound /> Reinstate access
                            </DropdownMenuItem>
                        )}
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
                            {surface === 'decide'
                                ? 'Certify access'
                                : surface === 'reinstate'
                                  ? 'Independently reinstate access'
                                  : item.user.name}
                        </SheetTitle>
                        <SheetDescription>
                            {item.campaign.reference} · {humanize(item.role)} ·{' '}
                            {humanize(item.decision)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-8">
                        {surface === 'decide' ? (
                            <AccessDecisionForm item={item} />
                        ) : surface === 'reinstate' ? (
                            <Form
                                action={reinstate({
                                    accessReviewItem: item.id,
                                })}
                                className="grid gap-4"
                            >
                                <p className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm">
                                    Reinstatement requires a different actor
                                    from the revoker, a formal approval
                                    reference, and strong authentication for
                                    privileged roles.
                                </p>
                                <Field
                                    name="approval_reference"
                                    label="Reinstatement approval reference"
                                />
                                <TextField
                                    name="rationale"
                                    label="Remediation and reinstatement rationale"
                                />
                                <Button type="submit">
                                    <KeyRound /> Reinstate controlled access
                                </Button>
                            </Form>
                        ) : (
                            <Details
                                entries={[
                                    ['Email', item.user.email],
                                    ['Role', humanize(item.role)],
                                    [
                                        'Home county',
                                        item.homeCounty?.name ??
                                            'National / portfolio',
                                    ],
                                    [
                                        'Assigned counties',
                                        item.assignedCounties
                                            .map((county) => county.name)
                                            .join(', ') || 'None',
                                    ],
                                    [
                                        'MFA at snapshot',
                                        item.mfaEnabled
                                            ? 'Enabled'
                                            : 'Not enabled',
                                    ],
                                    [
                                        'Passkey at snapshot',
                                        item.passkeyEnabled
                                            ? 'Registered'
                                            : 'Not registered',
                                    ],
                                    [
                                        'Permissions',
                                        item.permissions.join(', '),
                                    ],
                                    [
                                        'Last authenticated',
                                        item.lastAuthenticatedAt
                                            ? formatDate(
                                                  item.lastAuthenticatedAt,
                                              )
                                            : 'No evidence',
                                    ],
                                    ['Decision', humanize(item.decision)],
                                    ['Rationale', item.rationale],
                                    ['Remediation', item.remediationAction],
                                    [
                                        'Sessions revoked',
                                        item.sessionsRevoked.toString(),
                                    ],
                                    ['Reviewer', item.reviewer],
                                    [
                                        'Reinstated',
                                        item.reinstatedAt
                                            ? formatDate(item.reinstatedAt)
                                            : null,
                                    ],
                                    ['Reinstater', item.reinstater],
                                    [
                                        'Reinstatement rationale',
                                        item.reinstatementRationale,
                                    ],
                                ]}
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function AccessDecisionForm({ item }: { item: AccessItem }) {
    const [decision, setDecision] = useState('');

    return (
        <Form
            action={decide({ accessReviewItem: item.id })}
            className="grid gap-4"
        >
            <p className="rounded-lg border bg-muted/40 p-3 text-sm">
                Retaining privileged access requires current MFA or a passkey.
                Revocation removes role/county assignments and invalidates every
                database session.
            </p>
            <SearchableSelect
                id={`access-decision-${item.id}`}
                name="decision"
                label="Certification decision"
                options={['retain', 'revoke', 'remediate'].map(option)}
                value={decision}
                onValueChange={setDecision}
            />
            <TextField name="rationale" label="Evidence-based rationale" />
            {decision === 'remediate' && (
                <>
                    <TextField
                        name="remediation_action"
                        label="Required remediation"
                    />
                    <DatePickerField
                        name="remediation_due_at"
                        label="Remediation due date"
                        required
                    />
                </>
            )}
            <Button type="submit">
                <UserCheck /> Record certification decision
            </Button>
        </Form>
    );
}

function ThreatForm({ users }: { users: Option[] }) {
    return (
        <FormSheet
            title="Register threat scenario"
            description="Capture the affected asset, STRIDE category, entry points, inherent risk and treatment before independent review."
            triggerLabel="Threat"
            icon={Plus}
            size="xl"
        >
            <Form action={storeThreat()} className="grid gap-5 pt-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <Field name="reference" label="Threat reference" />
                    <Field name="title" label="Threat title" />
                    <SearchableSelect
                        id="threat-category"
                        name="stride_category"
                        label="Threat category"
                        options={[
                            'spoofing',
                            'tampering',
                            'repudiation',
                            'information_disclosure',
                            'denial_of_service',
                            'elevation_of_privilege',
                            'supply_chain',
                            'privacy',
                        ].map(option)}
                    />
                    <SearchableSelect
                        id="threat-owner"
                        name="owner_id"
                        label="Risk owner"
                        options={users.map(toSearchOption)}
                    />
                    <Field name="asset" label="Affected asset or service" />
                    <Field name="threat_actor" label="Threat actor" optional />
                    <Field
                        name="likelihood"
                        label="Likelihood (1–5)"
                        type="number"
                    />
                    <Field name="impact" label="Impact (1–5)" type="number" />
                    <DatePickerField
                        name="review_due_at"
                        label="Review due date"
                        required
                    />
                </div>
                <TextField name="scenario" label="Abuse or failure scenario" />
                <TextField
                    name="entry_points"
                    label="Entry points (comma separated)"
                />
                <TextField
                    name="existing_controls"
                    label="Existing controls (comma separated)"
                />
                <TextField
                    name="treatment_plan"
                    label="Treatment plan and accountable outcome"
                />
                <TextField
                    name="evidence_references"
                    label="Evidence references (comma separated)"
                    optional
                />
                <Button type="submit">Submit threat for review</Button>
            </Form>
        </FormSheet>
    );
}

function CampaignForm({ users, roles }: { users: Option[]; roles: Option[] }) {
    return (
        <FormSheet
            title="Launch access certification"
            description="Create immutable access snapshots for selected programme roles and assign an independent reviewer."
            triggerLabel="Access review"
            icon={Fingerprint}
            size="xl"
        >
            <Form action={launchCampaign()} className="grid gap-5 pt-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <Field name="reference" label="Campaign reference" />
                    <Field name="name" label="Campaign name" />
                    <SearchableSelect
                        id="campaign-reviewer"
                        name="reviewer_id"
                        label="Independent reviewer"
                        options={users.map(toSearchOption)}
                    />
                    <SearchableMultiSelect
                        name="role_scope"
                        label="Roles in scope"
                        options={roles.map(toSearchOption)}
                    />
                    <DatePickerField
                        name="period_from"
                        label="Review period from"
                        required
                    />
                    <DatePickerField
                        name="period_to"
                        label="Review period to"
                        required
                    />
                    <DatePickerField
                        name="due_at"
                        label="Certification due"
                        includeTime
                        required
                    />
                </div>
                <TextField
                    name="scope"
                    label="Scope, criteria and exclusions"
                />
                <Button type="submit">Launch certification campaign</Button>
            </Form>
        </FormSheet>
    );
}

function RegisterHeader({
    filters,
    count,
}: {
    filters: Record<string, string | undefined>;
    count: number;
}) {
    return (
        <div className="flex flex-col justify-between gap-3 border-b px-5 py-4 sm:flex-row sm:items-center">
            <div>
                <h2 className="font-bold">Access certification register</h2>
                <p className="text-sm text-muted-foreground">
                    {count.toLocaleString()} point-in-time identity snapshots
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                    <Button key={format} variant="outline" size="sm" asChild>
                        <a
                            href={
                                exportMethod(
                                    {
                                        workspace: 'security-governance',
                                        format,
                                    },
                                    { query: filters },
                                ).url
                            }
                        >
                            {format.toUpperCase()}
                        </a>
                    </Button>
                ))}
            </div>
        </div>
    );
}

function SecurityIncidentRegisterHeader({
    filters,
    count,
}: {
    filters: Record<string, string | undefined>;
    count: number;
}) {
    return (
        <div className="flex flex-col justify-between gap-3 border-b px-5 py-4 sm:flex-row sm:items-center">
            <div>
                <h2 className="font-bold">
                    Security incident and exercise register
                </h2>
                <p className="text-sm text-muted-foreground">
                    {count.toLocaleString()} live or explicitly labelled
                    exercise records with immutable response evidence
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                    <Button key={format} variant="outline" size="sm" asChild>
                        <a
                            href={
                                exportMethod(
                                    { workspace: 'security-incidents', format },
                                    { query: filters },
                                ).url
                            }
                        >
                            {format.toUpperCase()}
                        </a>
                    </Button>
                ))}
            </div>
        </div>
    );
}

function DelegationRegisterHeader({
    filters,
    count,
}: {
    filters: Record<string, string | undefined>;
    count: number;
}) {
    return (
        <div className="flex flex-col justify-between gap-3 border-b px-5 py-4 sm:flex-row sm:items-center">
            <div>
                <h2 className="font-bold">
                    Temporary and emergency access register
                </h2>
                <p className="text-sm text-muted-foreground">
                    {count.toLocaleString()} governed, time-bound authorization
                    records
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                    <Button key={format} variant="outline" size="sm" asChild>
                        <a
                            href={
                                exportMethod(
                                    { workspace: 'access-delegations', format },
                                    { query: filters },
                                ).url
                            }
                        >
                            {format.toUpperCase()}
                        </a>
                    </Button>
                ))}
            </div>
        </div>
    );
}
function Metric({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof ShieldAlert;
    label: string;
    value: number;
    detail: string;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="text-sm text-muted-foreground">
                    {label}
                </CardTitle>
                <Icon aria-hidden="true" />
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">{value.toLocaleString()}</p>
                <p className="mt-1 text-xs text-muted-foreground">{detail}</p>
            </CardContent>
        </Card>
    );
}
function Details({ entries }: { entries: Array<[string, string | null]> }) {
    return (
        <dl className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
            {entries.map(([label, value]) => (
                <div key={label} className="grid gap-1">
                    <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {label}
                    </dt>
                    <dd className="text-sm whitespace-pre-wrap">
                        {value || '—'}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
function Field({
    name,
    label,
    type = 'text',
    optional = false,
}: {
    name: string;
    label: string;
    type?: string;
    optional?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>
                {label}
                {optional ? ' (optional)' : ''}
            </Label>
            <Input id={name} name={name} type={type} required={!optional} />
        </div>
    );
}
function TextField({
    name,
    label,
    optional = false,
}: {
    name: string;
    label: string;
    optional?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>
                {label}
                {optional ? ' (optional)' : ''}
            </Label>
            <Textarea id={name} name={name} rows={3} required={!optional} />
        </div>
    );
}
function option(id: string) {
    return { id, name: humanize(id) };
}
function toSearchOption(item: Option) {
    return { id: item.value, name: item.label };
}
function humanize(value: string) {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}
function formatDate(value: string) {
    return new Date(value).toLocaleString(DEFAULT_LOCALE, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
function pagination<T>(set: PageSet<T>, pageName: string): WorkspacePagination {
    return {
        currentPage: set.current_page,
        lastPage: set.last_page,
        perPage: set.per_page,
        total: set.total,
        pageName,
    };
}
