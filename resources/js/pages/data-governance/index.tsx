import { Form, Head, usePage } from '@inertiajs/react';
import {
    DatabaseZap,
    Eye,
    FileClock,
    MoreHorizontal,
    Plus,
    Scale,
    ShieldCheck,
    ShieldAlert,
    UserRoundSearch,
} from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import PrivacyIncidentDocumentControls from '@/components/privacy-incident-document-controls';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
    WorkspaceDocument,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { DEFAULT_LOCALE } from '@/lib/reference-catalog';
import { store as storeAsset } from '@/routes/data-governance/assets';
import {
    advance,
    store as storeDataSubjectRequest,
} from '@/routes/data-governance/data-subject-requests';
import {
    advance as advancePrivacyIncident,
    store as storePrivacyIncident,
} from '@/routes/data-governance/privacy-incidents';
import {
    review,
    store as storeProcessingActivity,
} from '@/routes/data-governance/processing-activities';
import {
    review as reviewRetentionSchedule,
    store as storeRetentionSchedule,
} from '@/routes/data-governance/retention-schedules';
import { exportMethod } from '@/routes/workspace';

type Option = { value: string; label: string };
type Asset = {
    id: string;
    code: string;
    name: string;
    description: string;
    module: string;
    authoritativeSource: string;
    classification: string;
    containsPersonalData: boolean;
    containsSensitivePersonalData: boolean;
    personalDataCategories: string[];
    dataSubjectCategories: string[];
    storageLocations: string[];
    residencyCountry: string;
    qualityStandard: string | null;
    status: string;
    owner: string | null;
    steward: string | null;
    processingActivityCount: number;
};
type RetentionSchedule = {
    id: string;
    code: string;
    recordClass: string;
    triggerEvent: string;
    retentionMonths: number;
    dispositionAction: string;
    legalAuthority: string;
    legalHoldRule: string;
    status: string;
    effectiveFrom: string | null;
    nextReviewAt: string | null;
    approver: string | null;
    submission: {
        id: string;
        submitter: string;
        reviewer: string | null;
        snapshotChecksum: string;
        decisionReason: string | null;
        submittedAt: string;
        reviewedAt: string | null;
    } | null;
};
type Activity = {
    id: string;
    reference: string;
    name: string;
    purpose: string;
    lawfulBasis: string;
    lawfulBasisReference: string;
    controllerName: string;
    processorNames: string[];
    recipientCategories: string[];
    processingOperations: string[];
    automatedDecisionMaking: boolean;
    crossBorderTransfer: boolean;
    transferCountries: string[];
    transferSafeguards: string | null;
    dpiaStatus: string;
    dpiaReference: string | null;
    riskSummary: string | null;
    securityMeasures: string;
    status: string;
    submittedAt: string | null;
    reviewedAt: string | null;
    nextReviewAt: string | null;
    asset: {
        id: string;
        code: string;
        name: string;
        classification: string;
        personal: boolean;
        sensitive: boolean;
    };
    retentionSchedule: {
        id: string | null;
        code: string | null;
        name: string | null;
    };
    submitter: string | null;
    reviewer: string | null;
};
type DataRequest = {
    id: string;
    reference: string;
    requestType: string;
    requesterName: string;
    requesterContact: string;
    contactChannel: string;
    scope: string;
    identityStatus: string;
    identityEvidenceReference: string | null;
    status: string;
    receivedAt: string;
    dueAt: string;
    acknowledgedAt: string | null;
    decidedAt: string | null;
    decision: string | null;
    decisionReason: string | null;
    responseEvidenceReference: string | null;
    assignee: string | null;
    identityVerifier: string | null;
    decisionMaker: string | null;
    overdue: boolean;
};
type PrivacyIncident = {
    id: string;
    reference: string;
    title: string;
    controllerRole: string;
    breachType: string;
    description: string;
    personalDataCategories: string[];
    estimatedDataSubjects: number | null;
    containsSensitiveData: boolean;
    status: string;
    severity: string;
    realRiskOfHarm: string;
    occurredAt: string | null;
    discoveredAt: string;
    controllerNotificationDueAt: string | null;
    regulatorNotificationDueAt: string;
    containedAt: string | null;
    assessedAt: string | null;
    regulatorNotifiedAt: string | null;
    dataSubjectsNotifiedAt: string | null;
    closedAt: string | null;
    containmentActions: string | null;
    riskAssessment: string | null;
    regulatorNotificationReference: string | null;
    regulatorDelayReason: string | null;
    subjectNotificationDecision: string;
    subjectNotificationRationale: string | null;
    rootCause: string | null;
    remediationActions: string | null;
    closureEvidenceReference: string | null;
    overdue: boolean;
    dataAsset: {
        id: string;
        code: string;
        name: string;
        classification: string;
    } | null;
    county: CountyIdentityValue | null;
    reporter: string;
    incidentLead: string;
    assessor: string | null;
    closer: string | null;
    documents: WorkspaceDocument[];
};
type CountyOption = Option & { county: CountyIdentityValue };
type PageSet<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    assets: Asset[];
    retentionSchedules: RetentionSchedule[];
    activities: PageSet<Activity>;
    dataSubjectRequests: PageSet<DataRequest>;
    privacyIncidents: PageSet<PrivacyIncident>;
    counties: CountyOption[];
    users: Option[];
    filters: Record<string, string | undefined>;
    capabilities: { manage: boolean };
    targets: {
        dataSubjectRequestDays: number;
        processingReviewMonths: number;
        controllerNotificationHours: number;
        regulatorNotificationHours: number;
    };
};

export default function DataGovernance({
    assets,
    retentionSchedules,
    activities,
    dataSubjectRequests,
    privacyIncidents,
    counties,
    users,
    filters,
    capabilities,
    targets,
}: Props) {
    const { currentTeam, localization } = usePage().props;
    const governanceCopy = localization.dataGovernance;

    if (!currentTeam) {
        return null;
    }

    const activityRows: WorkspaceRow[] = activities.data.map((activity) => ({
        id: activity.id,
        status: activity.status,
        cells: [
            activity.reference,
            activity.name,
            `${activity.asset.code} · ${activity.asset.name}`,
            humanize(activity.lawfulBasis),
            humanize(activity.dpiaStatus),
            activity.crossBorderTransfer
                ? activity.transferCountries.join(', ')
                : 'Kenya only',
            activity.retentionSchedule.code ?? '—',
            humanize(activity.status),
        ],
    }));
    const requestRows: WorkspaceRow[] = dataSubjectRequests.data.map(
        (request) => ({
            id: request.id,
            status: request.status,
            cells: [
                request.reference,
                humanize(request.requestType),
                request.requesterName,
                request.scope,
                humanize(request.identityStatus),
                formatDate(request.dueAt),
                request.overdue ? 'Overdue' : humanize(request.status),
            ],
        }),
    );
    const incidentRows: WorkspaceRow[] = privacyIncidents.data.map(
        (incident) => ({
            id: incident.id,
            status: incident.status,
            meta: { countyId: incident.county?.id ?? null },
            cells: [
                incident.reference,
                incident.title,
                incident.county ?? 'National',
                humanize(incident.breachType),
                incident.estimatedDataSubjects ?? 'Unknown',
                humanize(incident.realRiskOfHarm),
                humanize(incident.severity),
                formatDate(incident.regulatorNotificationDueAt),
                incident.overdue ? 'Overdue' : humanize(incident.status),
            ],
        }),
    );

    return (
        <>
            <Head title="Data governance" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 xl:flex-row xl:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Privacy and information governance
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Data governance control centre
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                Governed inventory, processing purposes,
                                lawful-basis evidence, DPIA screening, retention
                                schedules and controlled data-subject request
                                handling.
                            </p>
                        </div>
                        {capabilities.manage && (
                            <div className="flex flex-wrap gap-2">
                                <AssetForm
                                    teamSlug={currentTeam.slug}
                                    users={users}
                                />
                                <RetentionForm
                                    teamSlug={currentTeam.slug}
                                    copy={governanceCopy}
                                />
                                <ActivityForm
                                    teamSlug={currentTeam.slug}
                                    assets={assets}
                                    schedules={retentionSchedules.filter(
                                        (schedule) =>
                                            schedule.status === 'approved',
                                    )}
                                />
                                <DataRequestForm
                                    teamSlug={currentTeam.slug}
                                    users={users}
                                />
                                <PrivacyIncidentForm
                                    teamSlug={currentTeam.slug}
                                    users={users}
                                    assets={assets}
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
                            label: 'Lifecycle status',
                            options: [
                                'draft',
                                'submitted',
                                'approved',
                                'rejected',
                                'received',
                                'identity_verified',
                                'in_progress',
                                'completed',
                                'reported',
                                'contained',
                                'notification_required',
                                'remediation',
                                'closed',
                            ].map(option),
                            value: filters.status,
                        },
                        {
                            key: 'county_id',
                            label: 'County',
                            options: counties.map(toSearchOption),
                            value: filters.county_id,
                        },
                    ]}
                />
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Metric
                        icon={DatabaseZap}
                        label="Registered assets"
                        value={assets.length}
                        detail={`${assets.filter((asset) => asset.containsPersonalData).length} contain personal data`}
                    />
                    <Metric
                        icon={Scale}
                        label="Processing activities"
                        value={activities.total}
                        detail={`${activities.data.filter((item) => item.status === 'approved').length} approved on this page`}
                    />
                    <Metric
                        icon={FileClock}
                        label="Retention schedules"
                        value={retentionSchedules.length}
                        detail={`${targets.processingReviewMonths}-month processing review target`}
                    />
                    <Metric
                        icon={UserRoundSearch}
                        label="Privacy requests"
                        value={dataSubjectRequests.total}
                        detail={`${targets.dataSubjectRequestDays}-day configurable service target`}
                    />
                    <Metric
                        icon={ShieldAlert}
                        label="Breach incidents"
                        value={privacyIncidents.total}
                        detail={`${privacyIncidents.data.filter((incident) => incident.overdue).length} overdue on this page · ${targets.regulatorNotificationHours}h target`}
                    />
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <RegisterHeader
                        title="Personal data breach register"
                        description={`${privacyIncidents.total.toLocaleString()} controlled incident records`}
                        teamSlug={currentTeam.slug}
                        filters={filters}
                        workspace="privacy-incidents"
                    />
                    {incidentRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Reference',
                                'Incident',
                                'County',
                                'Type',
                                'Subjects',
                                'Real risk',
                                'Severity',
                                'Regulator due',
                                'Status',
                            ]}
                            rows={incidentRows}
                            pagination={pagination(
                                privacyIncidents,
                                'incidents_page',
                            )}
                            bulkExport={{
                                teamSlug: currentTeam.slug,
                                workspace: 'privacy-incidents',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const incident = privacyIncidents.data.find(
                                    (item) => item.id === row.id,
                                );

                                return incident ? (
                                    <PrivacyIncidentAction
                                        incident={incident}
                                        teamSlug={currentTeam.slug}
                                        canManage={capabilities.manage}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No privacy incidents"
                            description="No breach incidents match the current filters. Report suspected incidents immediately when they occur."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section>
                    <div className="mb-3 flex items-end justify-between">
                        <div>
                            <h2 className="text-lg font-bold">
                                Data inventory
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Authoritative sources, ownership, classification
                                and storage locations.
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                        {assets.map((asset) => (
                            <AssetCard key={asset.id} asset={asset} />
                        ))}
                        {assets.length === 0 && (
                            <WorkspaceEmptyState
                                title="No data assets registered"
                                description="Register the first authoritative dataset before approving personal-data processing."
                                className="min-h-56 lg:col-span-2 xl:col-span-3"
                            />
                        )}
                    </div>
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <RegisterHeader
                        title="Processing activity register"
                        description={`${activities.total.toLocaleString()} purpose-bound processing records`}
                        teamSlug={currentTeam.slug}
                        filters={filters}
                    />
                    {activityRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Reference',
                                'Activity',
                                'Data asset',
                                'Lawful basis',
                                'DPIA',
                                'Transfer',
                                'Retention',
                                'Status',
                            ]}
                            rows={activityRows}
                            pagination={pagination(
                                activities,
                                'activities_page',
                            )}
                            bulkExport={{
                                teamSlug: currentTeam.slug,
                                workspace: 'data-governance',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const activity = activities.data.find(
                                    (item) => item.id === row.id,
                                );

                                return activity ? (
                                    <ActivityAction
                                        activity={activity}
                                        teamSlug={currentTeam.slug}
                                        canManage={capabilities.manage}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No processing activities"
                            description="Submit a purpose-bound processing record or adjust the current filters."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <div className="border-b px-5 py-4">
                            <h2 className="font-bold">Data-subject requests</h2>
                            <p className="text-sm text-muted-foreground">
                                Identity-controlled access, correction, erasure,
                                restriction and objection workflow.
                            </p>
                        </div>
                        {requestRows.length ? (
                            <WorkspaceDataTable
                                columns={[
                                    'Reference',
                                    'Type',
                                    'Requester',
                                    'Scope',
                                    'Identity',
                                    'Due',
                                    'Status',
                                ]}
                                rows={requestRows}
                                pagination={pagination(
                                    dataSubjectRequests,
                                    'requests_page',
                                )}
                                renderActionControl={(row) => {
                                    const request =
                                        dataSubjectRequests.data.find(
                                            (item) => item.id === row.id,
                                        );

                                    return request ? (
                                        <RequestAction
                                            request={request}
                                            teamSlug={currentTeam.slug}
                                            canManage={capabilities.manage}
                                        />
                                    ) : null;
                                }}
                            />
                        ) : (
                            <WorkspaceEmptyState
                                title="No privacy requests"
                                description="No data-subject requests match the current filters."
                                className="min-h-64 border-0"
                            />
                        )}
                    </div>
                    <div className="grid content-start gap-3">
                        <h2 className="font-bold">
                            {governanceCopy.retention_section}
                        </h2>
                        {retentionSchedules.map((schedule) => (
                            <Card key={schedule.id}>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {schedule.code} · {schedule.recordClass}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3 text-sm">
                                    <div className="flex flex-wrap gap-2">
                                        <Badge>
                                            {schedule.retentionMonths} months
                                        </Badge>
                                        <Badge variant="outline">
                                            {humanize(
                                                schedule.dispositionAction,
                                            )}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground">
                                        {governanceCopy.retention_trigger_label}
                                        : {schedule.triggerEvent}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {governanceCopy.retention_hold_label}:{' '}
                                        {schedule.legalHoldRule}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {schedule.status === 'approved'
                                            ? `${governanceCopy.retention_approved_by} ${schedule.approver ?? '—'}`
                                            : governanceCopy.retention_pending}{' '}
                                        · {governanceCopy.retention_review_due}{' '}
                                        {schedule.nextReviewAt ??
                                            governanceCopy.retention_not_scheduled}
                                    </p>
                                    {schedule.submission ? (
                                        <div className="grid gap-1 border-t pt-3 text-xs text-muted-foreground">
                                            <p>
                                                {
                                                    governanceCopy.retention_submitter
                                                }
                                                :{' '}
                                                {schedule.submission.submitter}
                                            </p>
                                            <p className="font-mono break-all">
                                                {
                                                    governanceCopy.retention_checksum
                                                }
                                                :{' '}
                                                {
                                                    schedule.submission
                                                        .snapshotChecksum
                                                }
                                            </p>
                                            {schedule.submission.reviewer && (
                                                <p>
                                                    {
                                                        governanceCopy.retention_reviewer
                                                    }
                                                    :{' '}
                                                    {
                                                        schedule.submission
                                                            .reviewer
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            {governanceCopy.retention_legacy}
                                        </p>
                                    )}
                                    {capabilities.manage &&
                                        schedule.status === 'submitted' &&
                                        schedule.submission && (
                                            <RetentionReviewForm
                                                teamSlug={currentTeam.slug}
                                                schedule={schedule}
                                                copy={governanceCopy}
                                            />
                                        )}
                                </CardContent>
                            </Card>
                        ))}
                        {retentionSchedules.length === 0 && (
                            <WorkspaceEmptyState
                                title={governanceCopy.retention_empty_title}
                                description={
                                    governanceCopy.retention_empty_description
                                }
                                className="min-h-56"
                            />
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

function AssetCard({ asset }: { asset: Asset }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Card>
                <CardHeader className="flex-row items-start justify-between">
                    <div>
                        <CardTitle>
                            {asset.code} · {asset.name}
                        </CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {asset.module}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setOpen(true)}
                        aria-label={`View ${asset.name}`}
                    >
                        <Eye />
                    </Button>
                </CardHeader>
                <CardContent className="grid gap-3">
                    <p className="line-clamp-3 text-sm text-muted-foreground">
                        {asset.description}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Badge>{humanize(asset.classification)}</Badge>
                        {asset.containsSensitivePersonalData && (
                            <Badge variant="destructive">
                                Sensitive personal data
                            </Badge>
                        )}
                        {asset.containsPersonalData &&
                            !asset.containsSensitivePersonalData && (
                                <Badge variant="outline">Personal data</Badge>
                            )}
                        <Badge variant="outline">
                            {asset.processingActivityCount} activities
                        </Badge>
                    </div>
                </CardContent>
            </Card>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>
                            {asset.code} · {asset.name}
                        </SheetTitle>
                        <SheetDescription>
                            Authoritative data inventory record
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-8">
                        <Details
                            entries={[
                                ['Owner', asset.owner],
                                ['Steward', asset.steward],
                                [
                                    'Authoritative source',
                                    asset.authoritativeSource,
                                ],
                                [
                                    'Classification',
                                    humanize(asset.classification),
                                ],
                                ['Residency', asset.residencyCountry],
                                [
                                    'Personal-data categories',
                                    asset.personalDataCategories.join(', ') ||
                                        'None',
                                ],
                                [
                                    'Data subjects',
                                    asset.dataSubjectCategories.join(', ') ||
                                        'None',
                                ],
                                ['Storage', asset.storageLocations.join(', ')],
                                ['Quality standard', asset.qualityStandard],
                            ]}
                        />
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ActivityAction({
    activity,
    teamSlug,
    canManage,
}: {
    activity: Activity;
    teamSlug: string;
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
                        aria-label={`Actions for ${activity.reference}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setSurface('detail')}>
                            <Eye /> View governance record
                        </DropdownMenuItem>
                        {canManage && activity.status === 'submitted' && (
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
                                ? 'Independent processing review'
                                : activity.name}
                        </SheetTitle>
                        <SheetDescription>
                            {activity.reference} · {activity.asset.code} ·{' '}
                            {humanize(activity.status)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-8">
                        {surface === 'review' ? (
                            <Form
                                action={review({
                                    current_team: teamSlug,
                                    processingActivity: activity.id,
                                })}
                                className="grid gap-4"
                            >
                                <p className="rounded-lg border bg-muted/40 p-3 text-sm">
                                    Approval requires an approved retention
                                    schedule, documented transfer safeguards
                                    where applicable, and a completed DPIA for
                                    sensitive personal data. The submitter
                                    cannot review their own record.
                                </p>
                                <SearchableSelect
                                    id={`activity-decision-${activity.id}`}
                                    name="decision"
                                    label="Decision"
                                    options={['approved', 'rejected'].map(
                                        option,
                                    )}
                                />
                                <TextField
                                    name="review_note"
                                    label="Independent review findings"
                                />
                                <Button type="submit">
                                    <ShieldCheck /> Record review
                                </Button>
                            </Form>
                        ) : (
                            <Details
                                entries={[
                                    ['Purpose', activity.purpose],
                                    [
                                        'Lawful basis',
                                        humanize(activity.lawfulBasis),
                                    ],
                                    [
                                        'Legal reference',
                                        activity.lawfulBasisReference,
                                    ],
                                    ['Controller', activity.controllerName],
                                    [
                                        'Processors',
                                        activity.processorNames.join(', ') ||
                                            'None',
                                    ],
                                    [
                                        'Recipients',
                                        activity.recipientCategories.join(
                                            ', ',
                                        ) || 'None',
                                    ],
                                    [
                                        'Operations',
                                        activity.processingOperations.join(
                                            ', ',
                                        ),
                                    ],
                                    [
                                        'Automated decision-making',
                                        activity.automatedDecisionMaking
                                            ? 'Yes'
                                            : 'No',
                                    ],
                                    [
                                        'Cross-border transfer',
                                        activity.crossBorderTransfer
                                            ? activity.transferCountries.join(
                                                  ', ',
                                              )
                                            : 'No',
                                    ],
                                    [
                                        'Transfer safeguards',
                                        activity.transferSafeguards,
                                    ],
                                    [
                                        'DPIA',
                                        `${humanize(activity.dpiaStatus)}${activity.dpiaReference ? ` · ${activity.dpiaReference}` : ''}`,
                                    ],
                                    ['Risk summary', activity.riskSummary],
                                    [
                                        'Security measures',
                                        activity.securityMeasures,
                                    ],
                                    ['Submitter', activity.submitter],
                                    ['Reviewer', activity.reviewer],
                                    ['Next review', activity.nextReviewAt],
                                ]}
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function RequestAction({
    request,
    teamSlug,
    canManage,
}: {
    request: DataRequest;
    teamSlug: string;
    canManage: boolean;
}) {
    const [surface, setSurface] = useState<'detail' | 'advance' | null>(null);
    const transitions =
        request.status === 'received'
            ? ['verify_identity']
            : request.status === 'identity_verified'
              ? ['start_review', 'reject']
              : request.status === 'in_progress'
                ? ['complete', 'reject']
                : [];

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${request.reference}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setSurface('detail')}>
                            <Eye /> View request
                        </DropdownMenuItem>
                        {canManage && transitions.length > 0 && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('advance')}
                            >
                                <ShieldCheck /> Advance workflow
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'advance'
                                ? 'Advance privacy request'
                                : request.reference}
                        </SheetTitle>
                        <SheetDescription>
                            {humanize(request.requestType)} · due{' '}
                            {formatDate(request.dueAt)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-8">
                        {surface === 'advance' ? (
                            <RequestTransitionForm
                                teamSlug={teamSlug}
                                request={request}
                                transitions={transitions}
                            />
                        ) : (
                            <Details
                                entries={[
                                    ['Requester', request.requesterName],
                                    ['Contact', request.requesterContact],
                                    [
                                        'Channel',
                                        humanize(request.contactChannel),
                                    ],
                                    ['Scope', request.scope],
                                    [
                                        'Identity',
                                        humanize(request.identityStatus),
                                    ],
                                    [
                                        'Identity evidence',
                                        request.identityEvidenceReference,
                                    ],
                                    ['Assignee', request.assignee],
                                    [
                                        'Identity verifier',
                                        request.identityVerifier,
                                    ],
                                    ['Decision maker', request.decisionMaker],
                                    ['Decision', request.decision],
                                    ['Decision reason', request.decisionReason],
                                    [
                                        'Response evidence',
                                        request.responseEvidenceReference,
                                    ],
                                    [
                                        'Received',
                                        formatDate(request.receivedAt),
                                    ],
                                    ['Due', formatDate(request.dueAt)],
                                ]}
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function RequestTransitionForm({
    teamSlug,
    request,
    transitions,
}: {
    teamSlug: string;
    request: DataRequest;
    transitions: string[];
}) {
    const [transition, setTransition] = useState(transitions[0] ?? '');

    return (
        <Form
            action={advance({
                current_team: teamSlug,
                dataSubjectRequest: request.id,
            })}
            className="grid gap-4"
        >
            <SearchableSelect
                id={`request-transition-${request.id}`}
                name="transition"
                label="Workflow transition"
                options={transitions.map(option)}
                value={transition}
                onValueChange={setTransition}
            />
            {transition === 'verify_identity' && (
                <Field
                    name="identity_evidence_reference"
                    label="Identity-evidence reference"
                />
            )}
            {['complete', 'reject'].includes(transition) && (
                <>
                    <TextField name="decision" label="Decision or response" />
                    <TextField
                        name="decision_reason"
                        label="Reason and legal assessment"
                    />
                    {transition === 'complete' && (
                        <Field
                            name="response_evidence_reference"
                            label="Response evidence reference"
                        />
                    )}
                </>
            )}
            <Button type="submit">
                <ShieldCheck /> Apply controlled transition
            </Button>
        </Form>
    );
}

function AssetForm({ teamSlug, users }: { teamSlug: string; users: Option[] }) {
    return (
        <FormSheet
            title="Register data asset"
            description="Record ownership, authoritative source, classification, residency and personal-data categories before use."
            triggerLabel="Data asset"
            icon={Plus}
            size="xl"
        >
            <Form action={storeAsset(teamSlug)} className="grid gap-5 pt-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <Field name="code" label="Asset code" />
                    <Field name="name" label="Asset name" />
                    <SearchableSelect
                        id="asset-owner"
                        name="data_owner_id"
                        label="Accountable data owner"
                        options={users.map(toSearchOption)}
                    />
                    <SearchableSelect
                        id="asset-steward"
                        name="steward_id"
                        label="Operational data steward"
                        options={users.map(toSearchOption)}
                    />
                    <Field name="module" label="ToR module or shared service" />
                    <Field
                        name="authoritative_source"
                        label="Authoritative source"
                    />
                    <SearchableSelect
                        id="asset-classification"
                        name="classification"
                        label="Classification"
                        options={[
                            'public',
                            'official',
                            'confidential',
                            'restricted',
                        ].map(option)}
                        defaultValue="official"
                    />
                    <ReferenceCatalogSelect
                        id="asset-residency-country"
                        name="residency_country"
                        label="Residency country"
                        catalog="country-code"
                    />
                    <BooleanField
                        name="contains_personal_data"
                        label="Contains personal data"
                    />
                    <BooleanField
                        name="contains_sensitive_personal_data"
                        label="Contains sensitive personal data"
                    />
                </div>
                <TextField
                    name="description"
                    label="Dataset scope and intended use"
                />
                <TextField
                    name="personal_data_categories"
                    label="Personal-data categories (comma separated)"
                    optional
                />
                <TextField
                    name="data_subject_categories"
                    label="Data-subject categories (comma separated)"
                    optional
                />
                <TextField
                    name="storage_locations"
                    label="Approved storage locations (comma separated)"
                />
                <TextField
                    name="quality_standard"
                    label="Quality and provenance standard"
                    optional
                />
                <Button type="submit">Register data asset</Button>
            </Form>
        </FormSheet>
    );
}

function RetentionForm({
    teamSlug,
    copy,
}: {
    teamSlug: string;
    copy: Record<string, string>;
}) {
    return (
        <FormSheet
            title={copy.retention_submit_title}
            description={copy.retention_submit_description}
            triggerLabel={copy.retention_trigger}
            icon={FileClock}
            size="xl"
        >
            <Form
                action={storeRetentionSchedule(teamSlug)}
                className="grid gap-4 pt-4"
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field name="code" label="Schedule code" />
                    <Field name="record_class" label="Record class" />
                    <Field
                        name="retention_months"
                        label="Retention months"
                        type="number"
                    />
                    <SearchableSelect
                        id="retention-action"
                        name="disposition_action"
                        label="Disposition action"
                        options={[
                            'review_then_destroy',
                            'transfer_to_archive',
                            'permanent_preservation',
                            'anonymize',
                        ].map(option)}
                    />
                    <DatePickerField
                        name="next_review_at"
                        label="Next review date"
                        required
                    />
                </div>
                <TextField
                    name="trigger_event"
                    label="Retention trigger event"
                />
                <TextField
                    name="legal_authority"
                    label="Legal or approved records authority"
                />
                <TextField
                    name="legal_hold_rule"
                    label="Legal-hold suspension rule"
                />
                <Button type="submit">{copy.retention_submit_button}</Button>
            </Form>
        </FormSheet>
    );
}

function RetentionReviewForm({
    teamSlug,
    schedule,
    copy,
}: {
    teamSlug: string;
    schedule: RetentionSchedule;
    copy: Record<string, string>;
}) {
    return (
        <FormSheet
            title={copy.retention_review_title}
            description={copy.retention_review_description}
            triggerLabel={copy.retention_review_action}
            icon={ShieldCheck}
        >
            <Form
                action={reviewRetentionSchedule([teamSlug, schedule.id])}
                className="grid gap-4 pt-4"
            >
                <SearchableSelect
                    id={`retention-decision-${schedule.id}`}
                    name="decision"
                    label={copy.retention_decision}
                    options={[
                        { id: 'approved', name: copy.retention_approve },
                        { id: 'rejected', name: copy.retention_reject },
                    ]}
                    defaultValue="approved"
                />
                <TextField
                    name="decision_reason"
                    label={copy.retention_decision_reason}
                />
                <Button type="submit">{copy.retention_record_decision}</Button>
            </Form>
        </FormSheet>
    );
}

function ActivityForm({
    teamSlug,
    assets,
    schedules,
}: {
    teamSlug: string;
    assets: Asset[];
    schedules: RetentionSchedule[];
}) {
    return (
        <FormSheet
            title="Submit processing activity"
            description="Create a ROPA-style record for independent privacy review. Comma-separate multi-value entries."
            triggerLabel="Processing"
            icon={Scale}
            size="xl"
        >
            <Form
                action={storeProcessingActivity(teamSlug)}
                className="grid gap-5 pt-4"
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <SearchableSelect
                        id="processing-asset"
                        name="data_asset_id"
                        label="Data asset"
                        options={assets.map((asset) => ({
                            id: asset.id,
                            name: `${asset.code} · ${asset.name}`,
                        }))}
                    />
                    <SearchableSelect
                        id="processing-retention"
                        name="retention_schedule_id"
                        label="Retention schedule"
                        options={schedules.map((schedule) => ({
                            id: schedule.id,
                            name: `${schedule.code} · ${schedule.recordClass}`,
                        }))}
                    />
                    <Field name="reference" label="Processing reference" />
                    <Field name="name" label="Activity name" />
                    <SearchableSelect
                        id="processing-basis"
                        name="lawful_basis"
                        label="Lawful basis"
                        options={[
                            'consent',
                            'contract',
                            'legal_obligation',
                            'vital_interests',
                            'public_task',
                            'legitimate_interests',
                        ].map(option)}
                    />
                    <Field
                        name="controller_name"
                        label="Controller"
                        defaultValue="State Department for Devolution"
                    />
                    <SearchableSelect
                        id="processing-dpia"
                        name="dpia_status"
                        label="DPIA status"
                        options={[
                            'not_required',
                            'required',
                            'in_progress',
                            'completed',
                        ].map(option)}
                        defaultValue="required"
                    />
                    <Field
                        name="dpia_reference"
                        label="DPIA evidence reference"
                        optional
                    />
                    <BooleanField
                        name="automated_decision_making"
                        label="Uses automated decision-making"
                    />
                    <BooleanField
                        name="cross_border_transfer"
                        label="Includes cross-border transfer"
                    />
                    <DatePickerField
                        name="next_review_at"
                        label="Next review date"
                        required
                    />
                </div>
                <TextField name="purpose" label="Specific processing purpose" />
                <TextField
                    name="lawful_basis_reference"
                    label="Lawful-basis authority and assessment"
                />
                <TextField
                    name="processor_names"
                    label="Processors (comma separated)"
                    optional
                />
                <TextField
                    name="recipient_categories"
                    label="Recipient categories (comma separated)"
                    optional
                />
                <TextField
                    name="processing_operations"
                    label="Processing operations (comma separated)"
                />
                <TextField
                    name="transfer_countries"
                    label="Transfer countries (comma separated)"
                    optional
                />
                <TextField
                    name="transfer_safeguards"
                    label="Transfer safeguards"
                    optional
                />
                <TextField name="risk_summary" label="Privacy risk summary" />
                <TextField
                    name="security_measures"
                    label="Technical and organizational measures"
                />
                <Button type="submit">Submit for independent review</Button>
            </Form>
        </FormSheet>
    );
}

function DataRequestForm({
    teamSlug,
    users,
}: {
    teamSlug: string;
    users: Option[];
}) {
    return (
        <FormSheet
            title="Record data-subject request"
            description="Capture only the minimum contact information needed to verify and respond. Requester fields are encrypted at rest."
            triggerLabel="Privacy request"
            icon={UserRoundSearch}
            size="xl"
        >
            <Form
                action={storeDataSubjectRequest(teamSlug)}
                className="grid gap-4 pt-4"
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <SearchableSelect
                        id="dsr-type"
                        name="request_type"
                        label="Request type"
                        options={[
                            'access',
                            'rectification',
                            'erasure',
                            'restriction',
                            'objection',
                            'portability',
                        ].map(option)}
                    />
                    <SearchableSelect
                        id="dsr-assignee"
                        name="assigned_to"
                        label="Assigned privacy operator"
                        options={users.map(toSearchOption)}
                    />
                    <Field name="requester_name" label="Requester name" />
                    <Field
                        name="requester_contact"
                        label="Controlled contact"
                    />
                    <SearchableSelect
                        id="dsr-channel"
                        name="contact_channel"
                        label="Contact channel"
                        options={['email', 'phone', 'letter', 'in_person'].map(
                            option,
                        )}
                    />
                    <DatePickerField
                        name="received_at"
                        label="Received date and time"
                        includeTime
                        required
                    />
                </div>
                <TextField
                    name="scope"
                    label="Requested personal data and service scope"
                />
                <Button type="submit">Record privacy request</Button>
            </Form>
        </FormSheet>
    );
}

function PrivacyIncidentForm({
    teamSlug,
    users,
    assets,
    counties,
}: {
    teamSlug: string;
    users: Option[];
    assets: Asset[];
    counties: CountyOption[];
}) {
    return (
        <FormSheet
            title="Report personal data breach"
            description="Record the minimum controlled facts needed to contain, independently assess and notify. Detailed narratives are encrypted at rest."
            triggerLabel="Report breach"
            icon={ShieldAlert}
            size="xl"
        >
            <Form
                action={storePrivacyIncident(teamSlug)}
                className="grid gap-4 pt-4"
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field name="title" label="Incident title" />
                    <SearchableSelect
                        id="incident-lead"
                        name="incident_lead_id"
                        label="Incident lead"
                        options={users.map(toSearchOption)}
                    />
                    <SearchableSelect
                        id="incident-asset"
                        name="data_asset_id"
                        label="Affected data asset"
                        options={assets.map((asset) => ({
                            id: asset.id,
                            name: `${asset.code} · ${asset.name}`,
                        }))}
                        optional
                    />
                    <SearchableSelect
                        id="incident-county"
                        name="county_id"
                        label="Affected county"
                        options={counties.map(toSearchOption)}
                        optional
                    />
                    <SearchableSelect
                        id="incident-role"
                        name="controller_role"
                        label="SDD role"
                        options={['controller', 'processor'].map(option)}
                    />
                    <SearchableSelect
                        id="incident-type"
                        name="breach_type"
                        label="Breach type"
                        options={[
                            'confidentiality',
                            'integrity',
                            'availability',
                            'combined',
                        ].map(option)}
                    />
                    <Field
                        name="estimated_data_subjects"
                        label="Estimated affected people"
                        type="number"
                        optional
                    />
                    <BooleanField
                        name="contains_sensitive_data"
                        label="Sensitive personal data may be involved"
                    />
                    <DatePickerField
                        name="occurred_at"
                        label="Occurred date and time"
                        includeTime
                        required={false}
                    />
                    <DatePickerField
                        name="discovered_at"
                        label="Discovered date and time"
                        includeTime
                        required
                    />
                </div>
                <TextField
                    name="personal_data_categories"
                    label="Personal-data categories (comma separated)"
                />
                <TextField
                    name="description"
                    label="Controlled incident description"
                />
                <Button type="submit">
                    <ShieldAlert /> Record incident and deadlines
                </Button>
            </Form>
        </FormSheet>
    );
}

function PrivacyIncidentAction({
    incident,
    teamSlug,
    canManage,
}: {
    incident: PrivacyIncident;
    teamSlug: string;
    canManage: boolean;
}) {
    const [surface, setSurface] = useState<'detail' | 'workflow' | null>(null);
    const transition =
        incident.status === 'reported'
            ? 'contain'
            : incident.status === 'contained'
              ? 'assess'
              : incident.status === 'notification_required'
                ? 'record_notifications'
                : incident.status === 'remediation'
                  ? 'close'
                  : null;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${incident.reference}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setSurface('detail')}>
                            <Eye /> View incident record
                        </DropdownMenuItem>
                        {canManage && transition && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('workflow')}
                            >
                                <ShieldCheck /> Continue controlled workflow
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
                            {surface === 'workflow'
                                ? 'Advance privacy incident'
                                : incident.title}
                        </SheetTitle>
                        <SheetDescription>
                            {incident.reference} · {humanize(incident.status)} ·
                            discovered {formatDate(incident.discoveredAt)}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-8">
                        {surface === 'workflow' && transition ? (
                            <PrivacyIncidentTransitionForm
                                incident={incident}
                                teamSlug={teamSlug}
                                transition={transition}
                            />
                        ) : (
                            <>
                                <Details
                                    entries={[
                                        [
                                            'County',
                                            incident.county?.name ?? 'National',
                                        ],
                                        [
                                            'Data asset',
                                            incident.dataAsset
                                                ? `${incident.dataAsset.code} · ${incident.dataAsset.name}`
                                                : 'Not linked',
                                        ],
                                        [
                                            'Controller role',
                                            humanize(incident.controllerRole),
                                        ],
                                        [
                                            'Breach type',
                                            humanize(incident.breachType),
                                        ],
                                        ['Description', incident.description],
                                        [
                                            'Personal data',
                                            incident.personalDataCategories.join(
                                                ', ',
                                            ),
                                        ],
                                        [
                                            'Estimated people',
                                            incident.estimatedDataSubjects?.toLocaleString() ??
                                                'Unknown',
                                        ],
                                        [
                                            'Sensitive data',
                                            incident.containsSensitiveData
                                                ? 'Yes'
                                                : 'No',
                                        ],
                                        [
                                            'Regulator due',
                                            formatDate(
                                                incident.regulatorNotificationDueAt,
                                            ),
                                        ],
                                        [
                                            'Real risk of harm',
                                            humanize(incident.realRiskOfHarm),
                                        ],
                                        [
                                            'Severity',
                                            humanize(incident.severity),
                                        ],
                                        [
                                            'Containment',
                                            incident.containmentActions,
                                        ],
                                        [
                                            'Risk assessment',
                                            incident.riskAssessment,
                                        ],
                                        [
                                            'ODPC reference',
                                            incident.regulatorNotificationReference,
                                        ],
                                        [
                                            'Subject notification',
                                            humanize(
                                                incident.subjectNotificationDecision,
                                            ),
                                        ],
                                        ['Root cause', incident.rootCause],
                                        [
                                            'Remediation',
                                            incident.remediationActions,
                                        ],
                                        [
                                            'Closure evidence',
                                            incident.closureEvidenceReference,
                                        ],
                                        ['Reporter', incident.reporter],
                                        [
                                            'Incident lead',
                                            incident.incidentLead,
                                        ],
                                        ['Assessor', incident.assessor],
                                        ['Closer', incident.closer],
                                    ]}
                                />
                                {incident.county && (
                                    <div className="rounded-xl border p-4">
                                        <CountyIdentity
                                            county={incident.county}
                                        />
                                    </div>
                                )}
                                <PrivacyIncidentDocumentControls
                                    teamSlug={teamSlug}
                                    incidentId={incident.id}
                                    status={incident.status}
                                    documents={incident.documents}
                                    canUpload={canManage}
                                />
                            </>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function PrivacyIncidentTransitionForm({
    incident,
    teamSlug,
    transition,
}: {
    incident: PrivacyIncident;
    teamSlug: string;
    transition: string;
}) {
    return (
        <Form
            action={advancePrivacyIncident({
                current_team: teamSlug,
                privacyIncident: incident.id,
            })}
            className="grid gap-4"
        >
            <input type="hidden" name="transition" value={transition} />
            {transition === 'contain' && (
                <TextField
                    name="containment_actions"
                    label="Containment actions completed"
                />
            )}
            {transition === 'assess' && (
                <>
                    <SearchableSelect
                        id={`incident-severity-${incident.id}`}
                        name="severity"
                        label="Assessed severity"
                        options={['low', 'medium', 'high', 'critical'].map(
                            option,
                        )}
                    />
                    <SearchableSelect
                        id={`incident-risk-${incident.id}`}
                        name="real_risk_of_harm"
                        label="Real risk of harm"
                        options={['yes', 'no'].map(option)}
                    />
                    <TextField
                        name="risk_assessment"
                        label="Independent risk assessment and legal reasoning"
                    />
                </>
            )}
            {transition === 'record_notifications' && (
                <>
                    <DatePickerField
                        name="regulator_notified_at"
                        label="ODPC notified date and time"
                        includeTime
                        required
                    />
                    <Field
                        name="regulator_notification_reference"
                        label="ODPC notification reference"
                    />
                    <TextField
                        name="regulator_delay_reason"
                        label="Delay reason, when after 72 hours"
                        optional
                    />
                    <SearchableSelect
                        id={`incident-subject-notice-${incident.id}`}
                        name="subject_notification_decision"
                        label="Affected-person communication"
                        options={['notified', 'not_required', 'delayed'].map(
                            option,
                        )}
                    />
                    <DatePickerField
                        name="data_subjects_notified_at"
                        label="Affected people notified date and time"
                        includeTime
                        required={false}
                    />
                    <TextField
                        name="subject_notification_rationale"
                        label="No-notice or delay rationale"
                        optional
                    />
                </>
            )}
            {transition === 'close' && (
                <>
                    <TextField name="root_cause" label="Verified root cause" />
                    <TextField
                        name="remediation_actions"
                        label="Completed corrective and preventive actions"
                    />
                    <Field
                        name="closure_evidence_reference"
                        label="Controlled closure evidence reference"
                    />
                </>
            )}
            <Button type="submit">
                <ShieldCheck /> Record {humanize(transition)}
            </Button>
        </Form>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof DatabaseZap;
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
function RegisterHeader({
    title,
    description,
    teamSlug,
    filters,
    workspace = 'data-governance',
}: {
    title: string;
    description: string;
    teamSlug: string;
    filters: Record<string, string | undefined>;
    workspace?: 'data-governance' | 'privacy-incidents';
}) {
    return (
        <div className="flex flex-col justify-between gap-3 border-b px-5 py-4 sm:flex-row sm:items-center">
            <div>
                <h2 className="font-bold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <div className="flex flex-wrap gap-2">
                {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                    <Button key={format} variant="outline" size="sm" asChild>
                        <a
                            href={
                                exportMethod(
                                    {
                                        current_team: teamSlug,
                                        workspace,
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
    defaultValue,
    optional = false,
}: {
    name: string;
    label: string;
    type?: string;
    defaultValue?: string;
    optional?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>
                {label}
                {optional ? ' (optional)' : ''}
            </Label>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={defaultValue}
                required={!optional}
            />
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
            <Textarea id={name} name={name} required={!optional} rows={3} />
        </div>
    );
}
function BooleanField({ name, label }: { name: string; label: string }) {
    const [checked, setChecked] = useState(false);

    return (
        <div className="flex items-center gap-3 rounded-lg border p-3">
            <input type="hidden" name={name} value={checked ? '1' : '0'} />
            <Checkbox
                id={name}
                checked={checked}
                onCheckedChange={(value) => setChecked(value === true)}
            />
            <Label htmlFor={name}>{label}</Label>
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
