import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    BookOpenCheck,
    Download,
    Eye,
    GraduationCap,
    MoreHorizontal,
    Plus,
    ShieldCheck,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
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
import { Progress } from '@/components/ui/progress';
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
import { store as assessParticipant } from '@/routes/change-readiness/assessments';
import { store as storeCohort } from '@/routes/change-readiness/cohorts';
import { store as storeParticipant } from '@/routes/change-readiness/participants';
import {
    approve as approveWave,
    store as storeWave,
} from '@/routes/change-readiness/waves';
import { show as countyShow } from '@/routes/counties';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string };
type County = Option & { code: number; logoUrl: string | null };
type ReferenceData = {
    version: number;
    effectiveFrom: string | null;
    checksum: string;
};
type Assessment = {
    type: string;
    score: string;
    outcome: string;
    feedback: string;
    evidenceReferences: string[];
    assessor: string;
    assessedAt: string;
};
type Participant = {
    id: string;
    reference: string;
    name: string;
    county: County | null;
    roleTitle: string;
    attendedHours: string;
    attendanceStatus: string;
    competencyStatus: string;
    completedAt: string | null;
    assessments: Assessment[];
};
type Cohort = {
    id: string;
    referenceData: ReferenceData | null;
    wave: { id: string; code: string; name: string };
    code: string;
    name: string;
    county: County | null;
    audienceRole: string;
    deliveryMode: string;
    language: string;
    venue: string | null;
    seatCapacity: number;
    participantCount: number;
    completedCount: number;
    minimumAttendanceHours: string;
    passingScore: string;
    startsAt: string;
    endsAt: string;
    status: string;
    facilitator: string | null;
    participants: Participant[];
};
type Wave = {
    id: string;
    referenceData: ReferenceData | null;
    code: string;
    name: string;
    objective: string;
    startsOn: string;
    endsOn: string;
    plannedParticipants: number;
    completedParticipants: number;
    status: string;
    entryCriteria: string[];
    supportChannels: string[];
    helpDeskRehearsed: boolean;
    trainingMaterialsApproved: boolean;
    readinessNotes: string | null;
    approvedAt: string | null;
    creator: string;
    approver: string | null;
    counties: County[];
    cohortCount: number;
};
type PageSet<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    waves: Wave[];
    cohorts: PageSet<Cohort>;
    filters: Record<string, string | undefined>;
    catalogue: { available: false } | ({ available: true } & ReferenceData);
    options: {
        counties: County[];
        users: Option[];
        roles: Array<{ value: string; label: string }>;
    };
    capabilities: {
        manage: boolean;
        recordEvidence: boolean;
        approve: boolean;
    };
};

export default function ChangeReadiness({
    waves,
    cohorts,
    filters,
    options,
    capabilities,
    catalogue,
}: Props) {
    const { currentTeam } = usePage().props;

    if (!currentTeam) {
        return null;
    }

    const targetCount = waves.reduce(
        (sum, wave) => sum + wave.plannedParticipants,
        0,
    );
    const completedCount = waves.reduce(
        (sum, wave) => sum + wave.completedParticipants,
        0,
    );
    const countyCount = new Set(
        waves.flatMap((wave) => wave.counties.map((county) => county.id)),
    ).size;
    const rows: WorkspaceRow[] = cohorts.data.map((cohort) => ({
        id: cohort.id,
        status: cohort.status,
        meta: { countyId: cohort.county?.id ?? null },
        cells: [
            cohort.code,
            cohort.wave.code,
            cohort.county
                ? countyCell(cohort.county, currentTeam.slug)
                : 'National',
            humanize(cohort.audienceRole),
            `${cohort.participantCount}/${cohort.seatCapacity}`,
            cohort.completedCount,
            formatDate(cohort.startsAt),
            humanize(cohort.status),
        ],
    }));
    const pagination: WorkspacePagination = {
        currentPage: cohorts.current_page,
        lastPage: cohorts.last_page,
        perPage: cohorts.per_page,
        total: cohorts.total,
    };

    return (
        <>
            <Head title="Rollout and training readiness" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] uppercase opacity-75">
                                Adoption, support and capability transfer
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Rollout readiness centre
                            </h1>
                            <p className="mt-3 max-w-2xl opacity-80">
                                Govern the 47-county rollout, the ToR training
                                baseline, attendance, competency, support
                                rehearsal and independent wave approval without
                                presenting plans as delivered outcomes.
                            </p>
                        </div>
                        {capabilities.manage && (
                            <div className="flex flex-wrap gap-2">
                                <WaveForm
                                    team={currentTeam.slug}
                                    counties={options.counties}
                                    catalogue={catalogue}
                                />
                                <CohortForm
                                    team={currentTeam.slug}
                                    waves={waves}
                                    counties={options.counties}
                                    users={options.users}
                                    roles={options.roles}
                                    catalogue={catalogue}
                                />
                            </div>
                        )}
                    </div>
                </section>
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        title="Planned training seats"
                        value={targetCount.toLocaleString()}
                        detail="Target capacity, not attendance"
                    />
                    <Metric
                        title="Competent completions"
                        value={completedCount.toLocaleString()}
                        detail={`${targetCount ? Math.round((completedCount / targetCount) * 100) : 0}% of planned capacity evidenced`}
                    />
                    <Metric
                        title="Counties scheduled"
                        value={`${countyCount}/47`}
                        detail="Unique counties across waves"
                    />
                    <Metric
                        title="Readiness approvals"
                        value={waves
                            .filter((wave) => wave.status === 'approved')
                            .length.toString()}
                        detail="Independently approved waves"
                    />
                </section>
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'status',
                            label: 'Cohort status',
                            options: [
                                'planned',
                                'scheduled',
                                'in_progress',
                                'completed',
                                'cancelled',
                            ].map(option),
                            value: filters.status,
                        },
                        {
                            key: 'county_id',
                            label: 'County',
                            options: options.counties,
                            value: filters.county_id,
                        },
                    ]}
                />
                <section className="grid gap-4 lg:grid-cols-2">
                    {waves.map((wave) => (
                        <WaveCard
                            key={wave.id}
                            wave={wave}
                            team={currentTeam.slug}
                            canApprove={capabilities.approve}
                        />
                    ))}
                    {waves.length === 0 && (
                        <WorkspaceEmptyState
                            title="No rollout waves in scope"
                            description="Create a phased wave with target counties, planned capacity, entry criteria and support channels."
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <RegisterHeader
                        team={currentTeam.slug}
                        filters={filters}
                        total={cohorts.total}
                    />
                    {rows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Cohort',
                                'Wave',
                                'County',
                                'Audience',
                                'Seats',
                                'Competent',
                                'Starts',
                                'Status',
                            ]}
                            rows={rows}
                            pagination={pagination}
                            renderActionControl={(row) => {
                                const cohort = cohorts.data.find(
                                    (item) => item.id === row.id,
                                );

                                return cohort ? (
                                    <CohortAction
                                        cohort={cohort}
                                        team={currentTeam.slug}
                                        counties={options.counties}
                                        users={options.users}
                                        canManage={capabilities.manage}
                                        canAssess={capabilities.recordEvidence}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title="No training cohorts"
                            description="Plan the first role-based cohort inside an authorized rollout wave."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
            </main>
        </>
    );
}

function WaveCard({
    wave,
    team,
    canApprove,
}: {
    wave: Wave;
    team: string;
    canApprove: boolean;
}) {
    const [open, setOpen] = useState(false);
    const progress = wave.plannedParticipants
        ? Math.min(
              100,
              (wave.completedParticipants / wave.plannedParticipants) * 100,
          )
        : 0;

    return (
        <>
            <Card>
                <CardHeader>
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <CardTitle>
                                {wave.code} · {wave.name}
                            </CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {formatDate(wave.startsOn)} –{' '}
                                {formatDate(wave.endsOn)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {wave.referenceData
                                    ? `Catalogue ${wave.referenceData.version} · ${wave.referenceData.checksum.slice(0, 12)}…`
                                    : 'Legacy record · lineage not pinned'}
                            </p>
                        </div>
                        <Badge
                            variant={
                                wave.status === 'approved'
                                    ? 'default'
                                    : 'outline'
                            }
                        >
                            {humanize(wave.status)}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <p className="text-sm text-muted-foreground">
                        {wave.objective}
                    </p>
                    <div>
                        <div className="mb-2 flex justify-between text-sm">
                            <span>Competent completions</span>
                            <span>
                                {wave.completedParticipants}/
                                {wave.plannedParticipants}
                            </span>
                        </div>
                        <Progress value={progress} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {wave.counties.slice(0, 6).map((county) => (
                            <Button
                                key={county.id}
                                variant="outline"
                                size="sm"
                                asChild
                            >
                                <Link
                                    href={countyShow({
                                        current_team: team,
                                        county: county.id,
                                    })}
                                >
                                    <CountyIdentity
                                        county={countyCell(county, team)}
                                        compact
                                    />
                                </Link>
                            </Button>
                        ))}
                        {wave.counties.length > 6 && (
                            <Badge variant="secondary">
                                +{wave.counties.length - 6} counties
                            </Badge>
                        )}
                    </div>
                    <Button variant="outline" onClick={() => setOpen(true)}>
                        <Eye /> View readiness evidence
                    </Button>
                </CardContent>
            </Card>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{wave.code} rollout evidence</SheetTitle>
                        <SheetDescription>
                            {wave.name} · created by {wave.creator}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 px-4 pt-4 pb-8">
                        <Detail
                            label="Entry criteria"
                            value={wave.entryCriteria.join(' · ')}
                        />
                        <Detail
                            label="Support channels"
                            value={wave.supportChannels.join(' · ')}
                        />
                        <Detail
                            label="Help-desk rehearsal"
                            value={
                                wave.helpDeskRehearsed ? 'Recorded' : 'Pending'
                            }
                        />
                        <Detail
                            label="Training material approval"
                            value={
                                wave.trainingMaterialsApproved
                                    ? 'Recorded'
                                    : 'Pending'
                            }
                        />
                        <Detail
                            label="Approval"
                            value={
                                wave.approver
                                    ? `${wave.approver} · ${formatDate(wave.approvedAt)}`
                                    : 'Pending independent approval'
                            }
                        />
                        {canApprove && wave.status !== 'approved' && (
                            <Form
                                action={approveWave({
                                    current_team: team,
                                    wave: wave.id,
                                })}
                                className="grid gap-3 rounded-lg border p-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <TextField
                                            name="readiness_notes"
                                            label="Independent readiness review"
                                            error={
                                                errors.readiness_notes ??
                                                errors.status
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <ShieldCheck /> Approve only if
                                            evidence is complete
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function CohortAction({
    cohort,
    team,
    counties,
    users,
    canManage,
    canAssess,
}: {
    cohort: Cohort;
    team: string;
    counties: County[];
    users: Option[];
    canManage: boolean;
    canAssess: boolean;
}) {
    const [surface, setSurface] = useState<string | null>(null);
    const [participantId, setParticipantId] = useState('');
    const participant = cohort.participants.find(
        (item) => item.id === participantId,
    );

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${cohort.code}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setSurface('details')}>
                        <Eye /> View cohort
                    </DropdownMenuItem>
                    {canManage && (
                        <DropdownMenuItem onSelect={() => setSurface('enroll')}>
                            <UserPlus /> Register participant
                        </DropdownMenuItem>
                    )}
                    {canAssess && cohort.participants.length > 0 && (
                        <DropdownMenuItem onSelect={() => setSurface('assess')}>
                            <BookOpenCheck /> Record competency
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'enroll'
                                ? 'Register participant'
                                : surface === 'assess'
                                  ? 'Record competency evidence'
                                  : cohort.name}
                        </SheetTitle>
                        <SheetDescription>
                            {cohort.code} · {cohort.wave.name}
                            {' · '}
                            {cohort.referenceData
                                ? `Catalogue ${cohort.referenceData.version}`
                                : 'Legacy lineage unpinned'}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <>
                                <Detail
                                    label="Audience and delivery"
                                    value={`${humanize(cohort.audienceRole)} · ${humanize(cohort.deliveryMode)} · ${cohort.language}`}
                                />
                                <Detail
                                    label="Schedule"
                                    value={`${formatDate(cohort.startsAt)} – ${formatDate(cohort.endsAt)}`}
                                />
                                <Detail
                                    label="Evidence threshold"
                                    value={`${cohort.minimumAttendanceHours} hours attendance · ${cohort.passingScore}% pass score`}
                                />
                                {cohort.participants.map((item) => (
                                    <div
                                        key={item.id}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="flex justify-between gap-3">
                                            <p className="font-medium">
                                                {item.name} · {item.reference}
                                            </p>
                                            <Badge
                                                variant={
                                                    item.completedAt
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {humanize(
                                                    item.competencyStatus,
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {item.roleTitle} ·{' '}
                                            {item.attendedHours} hours
                                        </p>
                                    </div>
                                ))}
                            </>
                        ) : surface === 'enroll' ? (
                            <Form
                                action={storeParticipant(team)}
                                className="grid gap-4"
                            >
                                <input
                                    type="hidden"
                                    name="training_cohort_id"
                                    value={cohort.id}
                                />
                                <SearchableSelect
                                    id={`participant-user-${cohort.id}`}
                                    name="user_id"
                                    label="Existing IDMIS user"
                                    options={users}
                                    optional
                                />
                                <SearchableSelect
                                    id={`participant-county-${cohort.id}`}
                                    name="county_id"
                                    label="County"
                                    options={counties}
                                    defaultValue={cohort.county?.id}
                                    optional
                                />
                                <Field
                                    name="participant_reference"
                                    label="Participant reference"
                                />
                                <Field
                                    name="role_title"
                                    label="Role or job title"
                                />
                                <Button type="submit">
                                    <UserPlus /> Register participant
                                </Button>
                            </Form>
                        ) : surface === 'assess' ? (
                            <>
                                <SearchableSelect
                                    id={`assessment-participant-${cohort.id}`}
                                    name="participant_selector"
                                    label="Participant"
                                    options={cohort.participants.map(
                                        (item) => ({
                                            id: item.id,
                                            name: `${item.name} · ${item.reference}`,
                                        }),
                                    )}
                                    value={participantId}
                                    onValueChange={setParticipantId}
                                />
                                {participant && (
                                    <Form
                                        action={assessParticipant({
                                            current_team: team,
                                            participant: participant.id,
                                        })}
                                        className="grid gap-4"
                                    >
                                        <SearchableSelect
                                            id={`assessment-type-${participant.id}`}
                                            name="assessment_type"
                                            label="Assessment type"
                                            options={[
                                                'pre_training',
                                                'post_training',
                                                'practical',
                                            ].map(option)}
                                        />
                                        <Field
                                            name="score"
                                            label="Score (%)"
                                            type="number"
                                        />
                                        <Field
                                            name="attended_hours"
                                            label="Verified attendance hours"
                                            type="number"
                                        />
                                        <TextField
                                            name="feedback"
                                            label="Competency feedback"
                                        />
                                        <TextField
                                            name="evidence_references[]"
                                            label="Evidence reference"
                                        />
                                        <Button type="submit">
                                            <BookOpenCheck /> Record immutable
                                            assessment
                                        </Button>
                                    </Form>
                                )}
                            </>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function WaveForm({
    team,
    counties,
    catalogue,
}: {
    team: string;
    counties: County[];
    catalogue: Props['catalogue'];
}) {
    return (
        <FormSheet
            title="Plan rollout wave"
            description="Define target counties, capacity, entry criteria and support readiness. Planning records do not count as delivery evidence."
            triggerLabel="Plan wave"
            icon={Plus}
            size="xl"
            triggerDisabled={!catalogue.available}
            triggerTitle={
                !catalogue.available
                    ? 'Publish an approved reference-data catalogue before planning rollout.'
                    : undefined
            }
        >
            <Form action={storeWave(team)} className="grid gap-4 pt-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field name="code" label="Wave code" />
                            <Field name="name" label="Wave name" />
                        </div>
                        <TextField name="objective" label="Wave objective" />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="starts_on"
                                label="Start date"
                                required
                            />
                            <DatePickerField
                                name="ends_on"
                                label="End date"
                                required
                            />
                            <Field
                                name="planned_participants"
                                label="Planned participants"
                                type="number"
                            />
                        </div>
                        <SearchableMultiSelect
                            name="county_ids[]"
                            label="Target counties"
                            options={counties}
                            error={errors.county_ids}
                        />
                        <TextField
                            name="entry_criteria[]"
                            label="Entry criterion"
                        />
                        <TextField
                            name="support_channels[]"
                            label="Support channel"
                        />
                        <div className="grid gap-3 rounded-lg border p-4">
                            <Check
                                name="help_desk_rehearsed"
                                label="Help-desk scenario rehearsed"
                            />
                            <Check
                                name="training_materials_approved"
                                label="Training materials approved"
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            <GraduationCap /> Save rollout plan
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
function CohortForm({
    team,
    waves,
    counties,
    users,
    roles,
    catalogue,
}: {
    team: string;
    waves: Wave[];
    counties: County[];
    users: Option[];
    roles: Array<{ value: string; label: string }>;
    catalogue: Props['catalogue'];
}) {
    return (
        <FormSheet
            title="Plan training cohort"
            description="Allocate role-based seats inside an existing rollout wave."
            triggerLabel="Plan cohort"
            icon={GraduationCap}
            size="xl"
            triggerDisabled={
                !catalogue.available ||
                waves.filter((wave) => wave.referenceData !== null).length === 0
            }
            triggerTitle={
                !catalogue.available
                    ? 'Publish an approved reference-data catalogue before planning cohorts.'
                    : undefined
            }
        >
            <Form action={storeCohort(team)} className="grid gap-4 pt-4">
                <SearchableSelect
                    id="cohort-wave"
                    name="rollout_wave_id"
                    label="Rollout wave"
                    options={waves
                        .filter((wave) => wave.referenceData !== null)
                        .map((wave) => ({
                            id: wave.id,
                            name: `${wave.code} · ${wave.name}`,
                        }))}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field name="code" label="Cohort code" />
                    <Field name="name" label="Cohort name" />
                    <SearchableSelect
                        id="cohort-county"
                        name="county_id"
                        label="County"
                        options={counties}
                        optional
                    />
                    <SearchableSelect
                        id="cohort-facilitator"
                        name="facilitator_id"
                        label="Facilitator"
                        options={users}
                        optional
                    />
                    <SearchableSelect
                        id="cohort-role"
                        name="audience_role"
                        label="Audience role"
                        options={roles.map((role) => ({
                            id: role.value,
                            name: role.label,
                        }))}
                    />
                    <SearchableSelect
                        id="cohort-mode"
                        name="delivery_mode"
                        label="Delivery mode"
                        options={['in_person', 'virtual', 'blended'].map(
                            option,
                        )}
                    />
                    <ReferenceCatalogSelect
                        id="cohort-language"
                        name="language"
                        label="Language"
                        catalog="language"
                    />
                    <Field
                        name="venue"
                        label="Venue or meeting channel"
                        optional
                    />
                    <Field
                        name="seat_capacity"
                        label="Seat capacity"
                        type="number"
                    />
                    <Field
                        name="minimum_attendance_hours"
                        label="Minimum attendance hours"
                        type="number"
                        defaultValue="6"
                    />
                    <Field
                        name="passing_score"
                        label="Passing score (%)"
                        type="number"
                        defaultValue="70"
                    />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <DatePickerField
                        name="starts_at"
                        label="Starts"
                        includeTime
                        required
                    />
                    <DatePickerField
                        name="ends_at"
                        label="Ends"
                        includeTime
                        required
                    />
                </div>
                <Button type="submit">
                    <GraduationCap /> Save cohort plan
                </Button>
            </Form>
        </FormSheet>
    );
}
function RegisterHeader({
    team,
    filters,
    total,
}: {
    team: string;
    filters: Props['filters'];
    total: number;
}) {
    return (
        <div className="flex items-center justify-between border-b px-5 py-4">
            <div>
                <h2 className="font-bold">Training cohort register</h2>
                <p className="text-sm text-muted-foreground">
                    {total.toLocaleString()} cohorts in scope
                </p>
            </div>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <Download /> Export evidence
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a
                                href={exportMethod.url(
                                    {
                                        current_team: team,
                                        workspace: 'change-readiness',
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
function Metric({
    title,
    value,
    detail,
}: {
    title: string;
    value: string;
    detail: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">{value}</p>
                <p className="mt-2 text-xs text-muted-foreground">{detail}</p>
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
}: {
    name: string;
    label: string;
    type?: 'text' | 'number';
    defaultValue?: string;
    optional?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
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
    error,
}: {
    name: string;
    label: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Textarea
                id={name}
                name={name}
                required
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${name}-error` : undefined}
            />
            {error && (
                <p
                    id={`${name}-error`}
                    className="text-sm text-destructive"
                    role="alert"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
function Check({ name, label }: { name: string; label: string }) {
    return (
        <label className="flex items-center gap-3 text-sm">
            <input type="hidden" name={name} value="0" />
            <input type="checkbox" name={name} value="1" className="size-4" />
            {label}
        </label>
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
function countyCell(county: County, team: string) {
    return {
        kind: 'county' as const,
        id: county.id,
        name: county.name,
        code: county.code,
        logoUrl: county.logoUrl,
        href: countyShow.url({ current_team: team, county: county.id }),
    };
}
function option(id: string) {
    return { id, name: humanize(id) };
}
function humanize(value: string) {
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}
function formatDate(value: string | null) {
    return value
        ? new Date(value).toLocaleString(DEFAULT_LOCALE, {
              dateStyle: 'medium',
              timeStyle: value.includes('T') ? 'short' : undefined,
          })
        : '—';
}
