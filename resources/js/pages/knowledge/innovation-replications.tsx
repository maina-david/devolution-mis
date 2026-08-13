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
    const { localization } = page.props;
    const copy = localization.innovationReplications;
    const locale = localization.current;
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
                : copy.legacy_unpinned,
            replication.referenceData?.checksum ?? copy.legacy_unpinned,
            replication.accountableAdopter,
            replication.successMeasure,
            replication.actualValue ?? copy.not_available,
            formatDate(replication.targetCompletionOn, locale),
            translateValue(copy, replication.status),
        ],
        href: preserveDrilldownFilters(
            showCounty.url({ county: replication.targetCounty.id }),
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
            <Head title={copy.page_title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-sm font-medium opacity-80">
                                {copy.eyebrow}
                            </p>
                            <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                {copy.title}
                            </h1>
                            <p className="mt-3 text-sm opacity-80 sm:text-base">
                                {copy.description}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {props.capabilities.manage && (
                                <CreateReplicationSheet
                                    options={props.options}
                                    catalogue={props.catalogue}
                                />
                            )}
                            <ExportMenu query={query} />
                        </div>
                    </div>
                </section>

                <DateRangeFilter
                    cycles={[]}
                    initialFrom={props.filters.from}
                    initialTo={props.filters.to}
                    initialSearch={props.filters.search ?? ''}
                    searchPlaceholder={copy.search_placeholder}
                    selectFilters={[
                        {
                            key: 'county_id',
                            label: copy.target_county,
                            value: props.filters.county_id,
                            options: props.options.counties,
                        },
                        {
                            key: 'status',
                            label: copy.replication_status,
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
                                name: translateValue(copy, value),
                            })),
                        },
                    ]}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label={copy.replication_portfolio}
                        value={props.summary.total}
                    />
                    <Summary
                        label={copy.active_pilots}
                        value={props.summary.piloting}
                    />
                    <Summary
                        label={copy.awaiting_verification}
                        value={props.summary.awaitingVerification}
                    />
                    <Summary
                        label={copy.verified_adoptions}
                        value={props.summary.adopted}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.register_title}</CardTitle>
                        <CardDescription>
                            {copy.register_description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {rows.length === 0 ? (
                            <WorkspaceEmptyState
                                title={copy.empty_title}
                                description={copy.empty_description}
                            />
                        ) : (
                            <WorkspaceDataTable
                                columns={[
                                    copy.reference,
                                    copy.source_innovation,
                                    copy.source_county,
                                    copy.target_county,
                                    copy.catalogue,
                                    copy.catalogue_checksum,
                                    copy.accountable_adopter,
                                    copy.success_measure,
                                    copy.actual,
                                    copy.target_completion,
                                    copy.status,
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
    options,
    catalogue,
}: {
    options: Props['options'];
    catalogue: Props['catalogue'];
}) {
    const copy = useInnovationReplicationCopy();

    return (
        <FormSheet
            title={copy.create_title}
            description={copy.create_description}
            triggerLabel={copy.create_replication}
            icon={Plus}
            size="xl"
            triggerDisabled={
                !catalogue.available || options.innovations.length === 0
            }
            triggerTitle={
                !catalogue.available
                    ? copy.catalogue_required
                    : options.innovations.length === 0
                      ? copy.no_scale_ready_innovation
                      : undefined
            }
        >
            <Form {...store.form()} className="grid gap-4 pt-4" resetOnSuccess>
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id="replication-innovation"
                            name="devolution_innovation_id"
                            label={copy.scale_ready_source}
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
                                label={copy.target_county}
                                options={options.counties}
                                error={errors.target_county_id}
                            />
                            <SearchableSelect
                                id="replication-adopter"
                                name="accountable_user_id"
                                label={copy.accountable_adopter}
                                options={options.adopters}
                                error={errors.accountable_user_id}
                            />
                            <NumberField
                                name="baseline_value"
                                label={copy.baseline_value}
                                error={errors.baseline_value}
                            />
                            <NumberField
                                name="target_value"
                                label={copy.target_value}
                                error={errors.target_value}
                            />
                            <DatePickerField
                                name="starts_on"
                                label={copy.starts_on}
                                required
                                error={errors.starts_on}
                            />
                            <DatePickerField
                                name="target_completion_on"
                                label={copy.target_completion}
                                required
                                error={errors.target_completion_on}
                            />
                        </div>
                        <TextField
                            name="adaptation_plan"
                            label={copy.local_adaptation_plan}
                            error={errors.adaptation_plan}
                        />
                        <Field
                            name="success_measure"
                            label={copy.success_measure}
                            error={errors.success_measure}
                        />
                        <Button type="submit" disabled={processing}>
                            {copy.create_governed_replication}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function ReplicationActions({
    replication,
    capabilities,
}: {
    replication: Replication;
    capabilities: Props['capabilities'];
}) {
    const copy = useInnovationReplicationCopy();
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
                        aria-label={interpolate(copy.actions_for, {
                            reference: replication.reference,
                        })}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setSurface('view')}>
                            <Eye /> {copy.view_replication}
                        </DropdownMenuItem>
                        {canTransition && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('transition')}
                            >
                                {copy.update_workflow}
                            </DropdownMenuItem>
                        )}
                        {canEvidence && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('evidence')}
                            >
                                <FileUp /> {copy.upload_evidence}
                            </DropdownMenuItem>
                        )}
                        {canVerify && (
                            <DropdownMenuItem
                                onSelect={() => setSurface('verify')}
                            >
                                <ShieldCheck /> {copy.verify_adoption}
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
                                  ? copy.upload_replication_evidence
                                  : surface === 'verify'
                                    ? copy.independent_adoption_decision
                                    : copy.update_replication_workflow}
                        </SheetTitle>
                        <SheetDescription>
                            {replication.innovation.title} {'· '}
                            {replication.targetCounty.name}
                            {' · '}
                            {replication.referenceData
                                ? `${copy.catalogue} v${replication.referenceData.version}`
                                : copy.legacy_lineage_unpinned}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-5 px-4 pb-8">
                        {surface === 'view' && (
                            <ReplicationDetail replication={replication} />
                        )}
                        {surface === 'transition' && (
                            <TransitionForm
                                replication={replication}
                                canManage={capabilities.manage}
                                canContribute={capabilities.contribute}
                            />
                        )}
                        {surface === 'evidence' && (
                            <EvidenceForm replication={replication} />
                        )}
                        {surface === 'verify' && (
                            <VerificationForm replication={replication} />
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ReplicationDetail({ replication }: { replication: Replication }) {
    const copy = useInnovationReplicationCopy();

    return (
        <div className="flex flex-col gap-4 text-sm">
            <div>
                <p className="font-medium">{copy.adaptation_plan}</p>
                <p className="text-muted-foreground">
                    {replication.adaptationPlan}
                </p>
            </div>
            <div>
                <p className="font-medium">{copy.measure}</p>
                <p className="text-muted-foreground">
                    {replication.successMeasure}
                    {': '} {replication.baselineValue} {'→ '}
                    {replication.targetValue}
                    {'; '} {copy.actual_lowercase}{' '}
                    {replication.actualValue ?? copy.pending}
                </p>
            </div>
            <div>
                <p className="font-medium">{copy.outcome}</p>
                <p className="text-muted-foreground">
                    {replication.outcomeSummary ?? copy.not_submitted}
                </p>
            </div>
            <div>
                <p className="font-medium">{copy.independent_decision}</p>
                <p className="text-muted-foreground">
                    {translateValue(copy, replication.verificationDecision)}
                    {replication.verifier
                        ? ` ${copy.by} ${replication.verifier}`
                        : ''}
                </p>
            </div>
            <div className="flex flex-col gap-2">
                <p className="font-medium">{copy.evidence}</p>
                {replication.documents.length === 0 ? (
                    <p className="text-muted-foreground">
                        {copy.no_evidence_uploaded}
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
                                    document: document.id,
                                })}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Eye /> {document.title} {'· '}
                                {translateValue(copy, document.scanStatus)}
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
    canManage,
    canContribute,
}: {
    replication: Replication;
    canManage: boolean;
    canContribute: boolean;
}) {
    const copy = useInnovationReplicationCopy();
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
            {...update.form({ replication: replication.id })}
            className="grid gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <SearchableSelect
                        id="replication-transition"
                        name="transition"
                        label={copy.workflow_action}
                        options={transitions.map((value) => ({
                            id: value,
                            name: translateValue(copy, value),
                        }))}
                        error={errors.transition}
                    />
                    {replication.status === 'piloting' && (
                        <>
                            <NumberField
                                name="actual_value"
                                label={copy.measured_actual_value}
                                error={errors.actual_value}
                            />
                            <TextField
                                name="outcome_summary"
                                label={copy.measured_outcome_summary}
                                error={errors.outcome_summary}
                            />
                        </>
                    )}
                    <TextField
                        name="rationale"
                        label={copy.attributed_rationale}
                        error={errors.rationale}
                    />
                    <Button type="submit" disabled={processing}>
                        {copy.apply_workflow_action}
                    </Button>
                </>
            )}
        </Form>
    );
}

function EvidenceForm({ replication }: { replication: Replication }) {
    const copy = useInnovationReplicationCopy();

    return (
        <Form
            {...storeDocument.form({ replication: replication.id })}
            className="grid gap-4"
            resetOnSuccess
        >
            {({ errors, processing }) => (
                <>
                    <Field
                        name="title"
                        label={copy.evidence_title}
                        error={errors.title}
                    />
                    <Field
                        name="category"
                        label={copy.record_category}
                        error={errors.category}
                        defaultValue={copy.replication_evidence}
                    />
                    <SearchableSelect
                        id="replication-source-type"
                        name="source_type"
                        label={copy.source_type}
                        options={[
                            { id: 'scanned', name: copy.scanned_original },
                            { id: 'digital', name: copy.born_digital },
                        ]}
                        error={errors.source_type}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="replication-document">
                            {copy.document}
                        </Label>
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
                        {copy.upload_secure_evidence}
                    </Button>
                </>
            )}
        </Form>
    );
}

function VerificationForm({ replication }: { replication: Replication }) {
    const copy = useInnovationReplicationCopy();

    return (
        <Form
            {...verify.form({ replication: replication.id })}
            className="grid gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <SearchableSelect
                        id="replication-decision"
                        name="decision"
                        label={copy.independent_decision}
                        options={[
                            { id: 'approve', name: copy.approve_adoption },
                            { id: 'return', name: copy.return_for_adaptation },
                        ]}
                        error={errors.decision}
                    />
                    <TextField
                        name="rationale"
                        label={copy.verification_rationale}
                        error={errors.rationale}
                    />
                    <Button type="submit" disabled={processing}>
                        {copy.record_immutable_decision}
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
    const locale = usePage().props.localization.current;

    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
            </CardHeader>
            <CardContent className="text-3xl font-bold">
                {value.toLocaleString(locale)}
            </CardContent>
        </Card>
    );
}

function ExportMenu({ query }: { query: Record<string, string | undefined> }) {
    const copy = useInnovationReplicationCopy();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="secondary">
                    <Download /> {copy.export_evidence}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a href={exportMethod.url({ format }, { query })}>
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

function useInnovationReplicationCopy(): Record<string, string> {
    return usePage().props.localization.innovationReplications;
}

function translateValue(copy: Record<string, string>, value: string): string {
    return copy[value] ?? humanize(value);
}

function interpolate(
    template: string,
    replacements: Record<string, string>,
): string {
    return Object.entries(replacements).reduce(
        (message, [key, value]) => message.replace(`:${key}`, value),
        template,
    );
}

function formatDate(value: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(
        new Date(`${value}T00:00:00`),
    );
}
