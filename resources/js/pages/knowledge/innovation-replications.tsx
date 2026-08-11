import { Form, Head, usePage } from '@inertiajs/react';
import {
    Download,
    Eye,
    FileUp,
    MoreHorizontal,
    Plus,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import type { WorkspaceRow } from '@/components/workspace-data-table';
import WorkspaceDataTable from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { preserveDrilldownFilters } from '@/lib/preserve-drilldown-filters';
import { show as showCounty } from '@/routes/counties';
import { preview as previewEvidence } from '@/routes/evidence';
import {
    exportMethod,
    store,
    update,
    verify,
} from '@/routes/knowledge/innovation-replications';
import { store as storeDocument } from '@/routes/knowledge/innovation-replications/documents';

type Replication = {
    id: string;
    reference: string;
    referenceData: {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    } | null;
    innovation: { id: string; reference: string; title: string };
    sourceCounty: CountyIdentityValue;
    targetCounty: CountyIdentityValue;
    accountableAdopter: string;
    creator: string;
    submitter: string | null;
    verifier: string | null;
    adaptationPlan: string;
    successMeasure: string;
    baselineValue: number;
    targetValue: number;
    actualValue: number | null;
    startsOn: string;
    targetCompletionOn: string;
    outcomeSummary: string | null;
    status: string;
    verificationDecision: string;
    verificationRationale: string | null;
    decisionChecksum: string | null;
    submittedAt: string | null;
    verifiedAt: string | null;
    documents: Array<{
        id: string;
        title: string;
        originalName: string | null;
        mimeType: string | null;
        scanStatus: string;
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
    replications: PageSet<Replication>;
    summary: {
        total: number;
        piloting: number;
        awaitingVerification: number;
        adopted: number;
    };
    filters: Record<string, string | undefined>;
    options: {
        counties: CountyIdentityValue[];
        innovations: Array<{
            id: string;
            label: string;
            county: CountyIdentityValue | null;
        }>;
        adopters: Array<{ id: string; name: string; county_id: string | null }>;
    };
    capabilities: { manage: boolean; contribute: boolean; verify: boolean };
    catalogue: {
        available: boolean;
        version?: number;
        effectiveFrom?: string | null;
        checksum?: string;
    };
};

export default function InnovationReplications(props: Props) {
    const page = usePage();
    const teamSlug = page.props.currentTeam!.slug;
    const rows: WorkspaceRow[] = props.replications.data.map((replication) => ({
        id: replication.id,
        status: replication.status,
        cells: [
            replication.reference,
            `${replication.innovation.reference} · ${replication.innovation.title}`,
            replication.sourceCounty,
            replication.targetCounty,
            replication.referenceData
                ? `v${replication.referenceData.version}`
                : 'Legacy unpinned',
            replication.referenceData?.checksum ?? 'Legacy unpinned',
            replication.accountableAdopter,
            replication.successMeasure,
            replication.actualValue ?? '—',
            replication.targetCompletionOn,
            replication.status,
        ],
        href: preserveDrilldownFilters(
            showCounty.url({
                current_team: teamSlug,
                county: replication.targetCounty.id,
            }),
            page.url,
        ),
    }));
    const query = {
        from: props.filters.from,
        to: props.filters.to,
        county_id: props.filters.county_id,
        status: props.filters.status,
        search: props.filters.search,
    };

    return (
        <>
            <Head title="Innovation replication portfolio" />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-sm font-medium opacity-80">
                                Knowledge Management
                            </p>
                            <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                Innovation replication
                            </h1>
                            <p className="mt-3 text-sm opacity-80 sm:text-base">
                                Govern cross-county adaptation, measurable
                                pilots, evidence and independent adoption
                                decisions.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {props.capabilities.manage && (
                                <CreateReplicationSheet
                                    teamSlug={teamSlug}
                                    options={props.options}
                                    catalogue={props.catalogue}
                                />
                            )}
                            <ExportMenu teamSlug={teamSlug} query={query} />
                        </div>
                    </div>
                </section>

                <DateRangeFilter
                    cycles={[]}
                    initialFrom={props.filters.from}
                    initialTo={props.filters.to}
                    initialSearch={props.filters.search ?? ''}
                    searchPlaceholder="Search replications"
                    selectFilters={[
                        {
                            key: 'county_id',
                            label: 'Target county',
                            value: props.filters.county_id,
                            options: props.options.counties,
                        },
                        {
                            key: 'status',
                            label: 'Replication status',
                            value: props.filters.status,
                            options: [
                                'planned',
                                'adapting',
                                'piloting',
                                'verification',
                                'adopted',
                                'abandoned',
                            ].map((value) => ({
                                id: value,
                                name: humanize(value),
                            })),
                        },
                    ]}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label="Replication portfolio"
                        value={props.summary.total}
                    />
                    <Summary
                        label="Active pilots"
                        value={props.summary.piloting}
                    />
                    <Summary
                        label="Awaiting verification"
                        value={props.summary.awaitingVerification}
                    />
                    <Summary
                        label="Verified adoptions"
                        value={props.summary.adopted}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Cross-county replication register</CardTitle>
                        <CardDescription>
                            Numbered, sortable and server-paginated records.
                            Clicking the county opens its filtered county
                            workspace; row actions remain explicit.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {rows.length === 0 ? (
                            <WorkspaceEmptyState
                                title="No matching replications"
                                description="Adjust the filters or create a replication from an innovation approved for scale-up."
                            />
                        ) : (
                            <WorkspaceDataTable
                                columns={[
                                    'Reference',
                                    'Source innovation',
                                    'Source county',
                                    'Target county',
                                    'Catalogue',
                                    'Catalogue checksum',
                                    'Accountable adopter',
                                    'Success measure',
                                    'Actual',
                                    'Target completion',
                                    'Status',
                                ]}
                                rows={rows}
                                pagination={{
                                    currentPage:
                                        props.replications.current_page,
                                    lastPage: props.replications.last_page,
                                    perPage: props.replications.per_page,
                                    total: props.replications.total,
                                }}
                                renderActionControl={(row) => {
                                    const replication =
                                        props.replications.data.find(
                                            (item) => item.id === row.id,
                                        )!;

                                    return (
                                        <ReplicationActions
                                            replication={replication}
                                            teamSlug={teamSlug}
                                            capabilities={props.capabilities}
                                        />
                                    );
                                }}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function CreateReplicationSheet({
    teamSlug,
    options,
    catalogue,
}: {
    teamSlug: string;
    options: Props['options'];
    catalogue: Props['catalogue'];
}) {
    return (
        <FormSheet
            title="Create cross-county replication"
            description="Choose a scale-ready source innovation, a different target county and an accountable target-county adopter."
            triggerLabel="Create replication"
            icon={Plus}
            size="xl"
            triggerDisabled={
                !catalogue.available || options.innovations.length === 0
            }
            triggerTitle={
                !catalogue.available
                    ? 'Publish an approved reference-data catalogue before creating replications.'
                    : options.innovations.length === 0
                      ? 'No lineage-bearing innovation is approved for scale-up.'
                      : undefined
            }
        >
            <Form
                {...store.form(teamSlug)}
                className="grid gap-4 pt-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id="replication-innovation"
                            name="devolution_innovation_id"
                            label="Scale-ready source innovation"
                            options={options.innovations.map((item) => ({
                                id: item.id,
                                name: item.label,
                            }))}
                            error={errors.devolution_innovation_id}
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <SearchableSelect
                                id="replication-target-county"
                                name="target_county_id"
                                label="Target county"
                                options={options.counties}
                                error={errors.target_county_id}
                            />
                            <SearchableSelect
                                id="replication-adopter"
                                name="accountable_user_id"
                                label="Accountable adopter"
                                options={options.adopters}
                                error={errors.accountable_user_id}
                            />
                            <NumberField
                                name="baseline_value"
                                label="Baseline value"
                                error={errors.baseline_value}
                            />
                            <NumberField
                                name="target_value"
                                label="Target value"
                                error={errors.target_value}
                            />
                            <DatePickerField
                                name="starts_on"
                                label="Starts on"
                                required
                                error={errors.starts_on}
                            />
                            <DatePickerField
                                name="target_completion_on"
                                label="Target completion"
                                required
                                error={errors.target_completion_on}
                            />
                        </div>
                        <TextField
                            name="adaptation_plan"
                            label="Local adaptation plan"
                            error={errors.adaptation_plan}
                        />
                        <Field
                            name="success_measure"
                            label="Success measure"
                            error={errors.success_measure}
                        />
                        <Button type="submit" disabled={processing}>
                            Create governed replication
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ReplicationActions({
    replication,
    teamSlug,
    capabilities,
}: {
    replication: Replication;
    teamSlug: string;
    capabilities: Props['capabilities'];
}) {
    const [surface, setSurface] = useState<
        'view' | 'transition' | 'evidence' | 'verify' | null
    >(null);
    const canTransition =
        (replication.status === 'planned' && capabilities.manage) ||
        (['adapting', 'piloting'].includes(replication.status) &&
            (capabilities.manage || capabilities.contribute));
    const canEvidence =
        (capabilities.manage || capabilities.contribute) &&
        ['adapting', 'piloting'].includes(replication.status);
    const canVerify =
        capabilities.verify && replication.status === 'verification';

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${replication.reference}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setSurface('view')}>
                            <Eye /> View replication
                        </DropdownMenuItem>
                        {canTransition && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('transition')}
                            >
                                Update workflow
                            </DropdownMenuItem>
                        )}
                        {canEvidence && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('evidence')}
                            >
                                <FileUp /> Upload evidence
                            </DropdownMenuItem>
                        )}
                        {canVerify && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('verify')}
                            >
                                <ShieldCheck /> Verify adoption
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
                            {surface === 'view'
                                ? replication.reference
                                : surface === 'evidence'
                                  ? 'Upload replication evidence'
                                  : surface === 'verify'
                                    ? 'Independent adoption decision'
                                    : 'Update replication workflow'}
                        </SheetTitle>
                        <SheetDescription>
                            {replication.innovation.title} ·{' '}
                            {replication.targetCounty.name}
                            {' · '}
                            {replication.referenceData
                                ? `Catalogue v${replication.referenceData.version}`
                                : 'Legacy lineage unpinned'}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-5 px-4 pb-8">
                        {surface === 'view' && (
                            <ReplicationDetail
                                replication={replication}
                                teamSlug={teamSlug}
                            />
                        )}
                        {surface === 'transition' && (
                            <TransitionForm
                                replication={replication}
                                teamSlug={teamSlug}
                                canManage={capabilities.manage}
                                canContribute={capabilities.contribute}
                            />
                        )}
                        {surface === 'evidence' && (
                            <EvidenceForm
                                replication={replication}
                                teamSlug={teamSlug}
                            />
                        )}
                        {surface === 'verify' && (
                            <VerificationForm
                                replication={replication}
                                teamSlug={teamSlug}
                            />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ReplicationDetail({
    replication,
    teamSlug,
}: {
    replication: Replication;
    teamSlug: string;
}) {
    return (
        <div className="flex flex-col gap-4 text-sm">
            <div>
                <p className="font-medium">Adaptation plan</p>
                <p className="text-muted-foreground">
                    {replication.adaptationPlan}
                </p>
            </div>
            <div>
                <p className="font-medium">Measure</p>
                <p className="text-muted-foreground">
                    {replication.successMeasure}: {replication.baselineValue} →{' '}
                    {replication.targetValue}; actual{' '}
                    {replication.actualValue ?? 'pending'}
                </p>
            </div>
            <div>
                <p className="font-medium">Outcome</p>
                <p className="text-muted-foreground">
                    {replication.outcomeSummary ?? 'Not submitted'}
                </p>
            </div>
            <div>
                <p className="font-medium">Independent decision</p>
                <p className="text-muted-foreground">
                    {humanize(replication.verificationDecision)}
                    {replication.verifier ? ` by ${replication.verifier}` : ''}
                </p>
            </div>
            <div className="flex flex-col gap-2">
                <p className="font-medium">Evidence</p>
                {replication.documents.length === 0 ? (
                    <p className="text-muted-foreground">
                        No evidence uploaded.
                    </p>
                ) : (
                    replication.documents.map((document) => (
                        <Button
                            key={document.id}
                            variant="outline"
                            asChild
                            className="justify-start"
                        >
                            <a
                                href={previewEvidence.url({
                                    current_team: teamSlug,
                                    document: document.id,
                                })}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Eye /> {document.title} ·{' '}
                                {humanize(document.scanStatus)}
                            </a>
                        </Button>
                    ))
                )}
            </div>
        </div>
    );
}

function TransitionForm({
    replication,
    teamSlug,
    canManage,
    canContribute,
}: {
    replication: Replication;
    teamSlug: string;
    canManage: boolean;
    canContribute: boolean;
}) {
    const lifecycleTransitions =
        replication.status === 'planned'
            ? canManage
                ? ['activate']
                : []
            : replication.status === 'adapting'
              ? canContribute
                  ? ['start_pilot']
                  : []
              : canContribute
                ? ['submit_verification']
                : [];
    const transitions = canManage
        ? [...lifecycleTransitions, 'abandon']
        : lifecycleTransitions;

    return (
        <Form
            {...update.form({
                current_team: teamSlug,
                replication: replication.id,
            })}
            className="grid gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <SearchableSelect
                        id="replication-transition"
                        name="transition"
                        label="Workflow action"
                        options={transitions.map((value) => ({
                            id: value,
                            name: humanize(value),
                        }))}
                        error={errors.transition}
                    />
                    {replication.status === 'piloting' && (
                        <>
                            <NumberField
                                name="actual_value"
                                label="Measured actual value"
                                error={errors.actual_value}
                            />
                            <TextField
                                name="outcome_summary"
                                label="Measured outcome summary"
                                error={errors.outcome_summary}
                            />
                        </>
                    )}
                    <TextField
                        name="rationale"
                        label="Attributed rationale"
                        error={errors.rationale}
                    />
                    <Button type="submit" disabled={processing}>
                        Apply workflow action
                    </Button>
                </>
            )}
        </Form>
    );
}

function EvidenceForm({
    replication,
    teamSlug,
}: {
    replication: Replication;
    teamSlug: string;
}) {
    return (
        <Form
            {...storeDocument.form({
                current_team: teamSlug,
                replication: replication.id,
            })}
            className="grid gap-4"
            resetOnSuccess
        >
            {({ errors, processing }) => (
                <>
                    <Field
                        name="title"
                        label="Evidence title"
                        error={errors.title}
                    />
                    <Field
                        name="category"
                        label="Record category"
                        error={errors.category}
                        defaultValue="Replication evidence"
                    />
                    <SearchableSelect
                        id="replication-source-type"
                        name="source_type"
                        label="Source type"
                        options={[
                            { id: 'scanned', name: 'Scanned original' },
                            { id: 'digital', name: 'Born digital' },
                        ]}
                        error={errors.source_type}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="replication-document">Document</Label>
                        <Input
                            id="replication-document"
                            name="document"
                            type="file"
                            required
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            aria-invalid={Boolean(errors.document)}
                        />
                        {errors.document && (
                            <p className="text-xs text-destructive">
                                {errors.document}
                            </p>
                        )}
                    </div>
                    <Button type="submit" disabled={processing}>
                        Upload secure evidence
                    </Button>
                </>
            )}
        </Form>
    );
}

function VerificationForm({
    replication,
    teamSlug,
}: {
    replication: Replication;
    teamSlug: string;
}) {
    return (
        <Form
            {...verify.form({
                current_team: teamSlug,
                replication: replication.id,
            })}
            className="grid gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <SearchableSelect
                        id="replication-decision"
                        name="decision"
                        label="Independent decision"
                        options={[
                            { id: 'approve', name: 'Approve adoption' },
                            { id: 'return', name: 'Return for adaptation' },
                        ]}
                        error={errors.decision}
                    />
                    <TextField
                        name="rationale"
                        label="Verification rationale"
                        error={errors.rationale}
                    />
                    <Button type="submit" disabled={processing}>
                        Record immutable decision
                    </Button>
                </>
            )}
        </Form>
    );
}

function Field({
    name,
    label,
    error,
    defaultValue,
}: {
    name: string;
    label: string;
    error?: string;
    defaultValue?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                defaultValue={defaultValue}
                required
                aria-invalid={Boolean(error)}
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

function NumberField({
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
            <Input
                id={name}
                name={name}
                type="number"
                step="any"
                required
                aria-invalid={Boolean(error)}
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
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
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

function Summary({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
            </CardHeader>
            <CardContent className="text-3xl font-bold">
                {value.toLocaleString()}
            </CardContent>
        </Card>
    );
}

function ExportMenu({
    teamSlug,
    query,
}: {
    teamSlug: string;
    query: Record<string, string | undefined>;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="secondary">
                    <Download /> Export evidence
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a
                                href={exportMethod.url(
                                    { current_team: teamSlug, format },
                                    { query },
                                )}
                            >
                                {format.toUpperCase()}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function humanize(value: string) {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}
