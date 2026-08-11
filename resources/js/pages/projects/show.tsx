import { Form, Head, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    CalendarCheck2,
    CircleAlert,
    ClipboardList,
    Download,
    Eye,
    FileText,
    GitBranch,
    MoreHorizontal,
    Pencil,
    UsersRound,
    ShoppingCart,
    Upload,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import ProjectProgressForm from '@/components/project-progress-form';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
import SearchableMultiSelect from '@/components/searchable-multi-select';
import SearchableSelect from '@/components/searchable-select';
import StaticSearchableSelect from '@/components/static-searchable-select';
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
import { formatCurrency, formatNumber } from '@/lib/reference-catalog';
import {
    download as downloadEvidence,
    preview as previewEvidence,
} from '@/routes/evidence';
import { index, transition } from '@/routes/projects';
import {
    store as storeBudget,
    update as updateBudget,
} from '@/routes/projects/budget-lines';
import { store as storeProjectDocument } from '@/routes/projects/documents';
import {
    store as storeMilestone,
    update as updateMilestone,
} from '@/routes/projects/milestones';
import {
    store as storeProcurement,
    update as updateProcurement,
} from '@/routes/projects/procurements';
import { verify as verifyProgress } from '@/routes/projects/progress-updates';
import { store as storeResourceAllocation } from '@/routes/projects/resource-allocations';
import { store as storeResource } from '@/routes/projects/resources';
import {
    store as storeRisk,
    update as updateRisk,
} from '@/routes/projects/risks';
import {
    decide as decideScheduleBaseline,
    store as storeScheduleBaseline,
} from '@/routes/projects/schedule-baselines';

type Entity = Record<string, unknown> & { id: string };
type Project = Entity & {
    code: string;
    title: string;
    description: string;
    lifecycle_stage: string;
    status: string;
    approved_budget: string;
    actual_expenditure: string;
    currency: string;
    physical_progress: string;
    lead_county: {
        id: string;
        code: number;
        name: string;
        logo_path: string | null;
    };
    sector: { name: string };
    programme: { name: string } | null;
    counties: Array<{ id: string; name: string }>;
    indicators: Array<{
        id: string;
        code: string;
        name: string;
        unit_of_measure: string;
    }>;
    milestones: Entity[];
    budget_lines: Entity[];
    risks: Entity[];
    procurements: Entity[];
    progress_updates: Entity[];
};
type ProjectDocument = {
    id: string;
    title: string;
    category: string;
    sourceType: string;
    originalName: string | null;
    mimeType: string | null;
    scanStatus: string;
    ocrStatus: string;
    uploadedAt: string | null;
    purpose: string;
};
type Capabilities = {
    manage: boolean;
    submitUpdates: boolean;
    verifyUpdates: boolean;
    uploadDocuments: boolean;
};
type ReferenceRelease = {
    version: number;
    checksum: string;
    effectiveFrom: string;
    status: string;
};
type ScheduleBaseline = {
    id: string;
    version: number;
    status: string;
    baselineReason: string;
    snapshotChecksum: string;
    decisionChecksum: string | null;
    requesterId: string;
    requester: string;
    decider: string | null;
    decisionRationale: string | null;
    decidedAt: string | null;
    createdAt: string | null;
    analysis: {
        project_start: string;
        project_finish: string;
        duration_days: number;
        critical_path_codes: string[];
    };
};
type ScheduleAnalysis = {
    as_of: string;
    baseline_version: number | null;
    baseline_finish: string | null;
    current_finish: string;
    forecast_finish: string;
    planned_variance_days: number | null;
    forecast_variance_days: number | null;
    critical_path_ids: string[];
    critical_path_codes: string[];
    milestones: Array<{
        id: string;
        code: string;
        is_critical: boolean;
        total_float_days: number;
        baseline_end: string | null;
        current_end: string;
        forecast_end: string;
        planned_variance_days: number | null;
        forecast_variance_days: number | null;
    }>;
};
type EarnedValueAnalysis = {
    available: boolean;
    as_of: string;
    method: string;
    baseline_version: number | null;
    budget_at_completion: number;
    planned_value: number | null;
    earned_value: number;
    actual_cost: number;
    cost_performance_index: number | null;
    schedule_performance_index: number | null;
    estimate_at_completion: number | null;
    estimate_to_complete: number | null;
    variance_at_completion: number | null;
    to_complete_performance_index: number | null;
    planned_completion_percent: number | null;
};
type ProjectResource = {
    id: string;
    code: string;
    name: string;
    type: string;
    capacityUnit: string;
    capacityPerDay: number;
    costRate: number;
    currency: string;
    availableFrom: string;
    availableTo: string;
    status: string;
    creator: string;
    plannedCost: number;
    allocations: Array<{
        id: string;
        milestoneId: string;
        milestone: string;
        startsOn: string;
        endsOn: string;
        plannedUnitsPerDay: number;
        plannedUnits: number;
        plannedCost: number;
        notes: string | null;
        checksum: string;
        creator: string;
    }>;
};
export default function ProjectShow({
    project,
    documents,
    capabilities,
    resultOptions,
    scheduleBaselines,
    scheduleAnalysis,
    earnedValueAnalysis,
    resourcePlan,
    referenceRelease,
}: {
    project: Project;
    documents: ProjectDocument[];
    capabilities: Capabilities;
    scheduleBaselines: ScheduleBaseline[];
    scheduleAnalysis: ScheduleAnalysis | null;
    earnedValueAnalysis: EarnedValueAnalysis;
    resourcePlan: ProjectResource[];
    referenceRelease: ReferenceRelease | null;
    resultOptions: {
        indicators: Array<{
            id: string;
            code: string;
            name: string;
            unit_of_measure: string;
            value_type: string;
            status: string;
        }>;
        counties: Array<{ id: string; name: string; logoUrl?: string | null }>;
    };
}) {
    const { currentTeam } = usePage().props;
    const [previewDocument, setPreviewDocument] =
        useState<ProjectDocument | null>(null);

    if (!currentTeam) {
        return null;
    }

    const args = { current_team: currentTeam.slug, project: project.id };

    return (
        <>
            <Head title={project.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <Button asChild variant="ghost" className="w-fit">
                    <a href={index.url(currentTeam.slug)}>
                        <ArrowLeft aria-hidden="true" />
                        Projects
                    </a>
                </Button>
                <section className="authenticated-page-header">
                    <CountyIdentity
                        county={{
                            kind: 'county',
                            id: project.lead_county.id,
                            code: project.lead_county.code,
                            name: project.lead_county.name,
                            logoUrl: project.lead_county.logo_path,
                        }}
                        inverse
                        className="mb-5"
                    />
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        {project.code} · {project.lifecycle_stage}
                    </p>
                    <h1 className="mt-3 text-3xl font-bold">{project.title}</h1>
                    <p className="mt-3 max-w-3xl text-[#c7d6dd]">
                        {project.description}
                    </p>
                    <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Metric
                            label="Physical progress"
                            value={`${Number(project.physical_progress)}%`}
                        />
                        <Metric
                            label="Approved budget"
                            value={formatCurrency(
                                Number(project.approved_budget),
                                project.currency,
                            )}
                        />
                        <Metric
                            label="Expenditure"
                            value={formatCurrency(
                                Number(project.actual_expenditure),
                                project.currency,
                            )}
                        />
                        <Metric
                            label="Scope"
                            value={`${project.counties.length} county${project.counties.length === 1 ? '' : 'ies'}`}
                        />
                    </div>
                </section>
                <section className="grid gap-4 lg:grid-cols-3">
                    <Info
                        title="Delivery scope"
                        values={[
                            project.lead_county.name,
                            project.sector.name,
                            project.programme?.name ?? 'No programme',
                            project.counties
                                .map((item) => item.name)
                                .join(', '),
                        ]}
                    />
                    <Info
                        title="Results links"
                        values={
                            project.indicators.length
                                ? project.indicators.map(
                                      (item) => `${item.code} · ${item.name}`,
                                  )
                                : ['No indicators linked']
                        }
                    />
                    <Info
                        title="Control status"
                        values={[
                            `Lifecycle: ${project.lifecycle_stage}`,
                            `Record status: ${project.status}`,
                            referenceRelease
                                ? `Reference catalogue: v${referenceRelease.version} · effective ${referenceRelease.effectiveFrom}`
                                : 'Reference catalogue: Legacy unpinned',
                            referenceRelease
                                ? `Catalogue checksum: ${referenceRelease.checksum}`
                                : 'No historical catalogue pin recorded',
                        ]}
                    />
                </section>
                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="flex flex-col gap-1.5">
                            <CardTitle>Project document register</CardTitle>
                            <CardDescription>
                                Governed lifecycle records with private storage,
                                malware quarantine, checksums, OCR eligibility,
                                immutable versions, and audited retrieval.
                            </CardDescription>
                        </div>
                        {capabilities.uploadDocuments &&
                            project.status !== 'closed' &&
                            project.lifecycle_stage !== 'closed' && (
                                <Panel
                                    icon={Upload}
                                    title="Upload project record"
                                >
                                    <Form
                                        {...storeProjectDocument.form(args)}
                                        resetOnSuccess
                                        className="grid gap-4"
                                    >
                                        {({ errors, processing, progress }) => (
                                            <>
                                                <Field
                                                    id="project-document-title"
                                                    name="title"
                                                    label="Document title"
                                                    error={errors.title}
                                                />
                                                <SearchableSelect
                                                    id="project-document-purpose"
                                                    name="record_purpose"
                                                    label="Record purpose"
                                                    options={[
                                                        {
                                                            id: 'lifecycle_record',
                                                            name: 'Project lifecycle record',
                                                        },
                                                        ...(project.lifecycle_stage ===
                                                        'execution'
                                                            ? [
                                                                  {
                                                                      id: 'closure_report',
                                                                      name: 'Signed project closure report',
                                                                  },
                                                              ]
                                                            : []),
                                                    ]}
                                                    defaultValue="lifecycle_record"
                                                    error={
                                                        errors.record_purpose
                                                    }
                                                />
                                                <Field
                                                    id="project-document-category"
                                                    name="category"
                                                    label="Record category"
                                                    error={errors.category}
                                                />
                                                <div className="grid gap-2">
                                                    <Label htmlFor="project-document-source-type">
                                                        Source type
                                                    </Label>
                                                    <SearchableSelect
                                                        id="project-document-source-type"
                                                        name="source_type"
                                                        label=""
                                                        options={[
                                                            {
                                                                id: 'digital',
                                                                name: 'Born-digital file',
                                                            },
                                                            {
                                                                id: 'scanned',
                                                                name: 'Scanned copy',
                                                            },
                                                        ]}
                                                        defaultValue="digital"
                                                        error={
                                                            errors.source_type
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="project-document-file">
                                                        Document
                                                    </Label>
                                                    <Input
                                                        id="project-document-file"
                                                        name="document"
                                                        type="file"
                                                        accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                                        required
                                                        aria-invalid={Boolean(
                                                            errors.document,
                                                        )}
                                                        aria-describedby={
                                                            errors.document
                                                                ? 'project-document-file-error'
                                                                : undefined
                                                        }
                                                    />
                                                    {errors.document && (
                                                        <p
                                                            id="project-document-file-error"
                                                            role="alert"
                                                            className="text-xs text-destructive"
                                                        >
                                                            {errors.document}
                                                        </p>
                                                    )}
                                                </div>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    <Upload data-icon="inline-start" />
                                                    {progress
                                                        ? `Uploading ${progress.percentage}%`
                                                        : 'Upload project record'}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </Panel>
                            )}
                    </CardHeader>
                    <CardContent>
                        {documents.length ? (
                            <div className="flex flex-col gap-3">
                                {documents.map((document) => (
                                    <div
                                        key={document.id}
                                        className="flex flex-col justify-between gap-4 rounded-lg border p-4 sm:flex-row sm:items-center"
                                    >
                                        <div className="flex min-w-0 items-start gap-3">
                                            <FileText
                                                className="mt-1 size-5 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {document.title}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {document.category} ·{' '}
                                                    {document.sourceType ===
                                                    'scanned'
                                                        ? 'Scanned copy'
                                                        : 'Born-digital file'}{' '}
                                                    ·{' '}
                                                    {document.originalName ??
                                                        document.mimeType ??
                                                        'File'}
                                                </p>
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    <Badge variant="outline">
                                                        Scan:{' '}
                                                        {document.scanStatus}
                                                    </Badge>
                                                    <Badge variant="outline">
                                                        {document.purpose
                                                            .replace(
                                                                'project-',
                                                                '',
                                                            )
                                                            .replaceAll(
                                                                '-',
                                                                ' ',
                                                            )}
                                                    </Badge>
                                                    <Badge variant="secondary">
                                                        OCR:{' '}
                                                        {document.ocrStatus}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </div>
                                        {document.scanStatus === 'clean' && (
                                            <div className="flex gap-2">
                                                {canPreview(document) && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setPreviewDocument(
                                                                document,
                                                            )
                                                        }
                                                    >
                                                        <Eye data-icon="inline-start" />
                                                        Preview
                                                    </Button>
                                                )}
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <a
                                                        href={downloadEvidence.url(
                                                            {
                                                                current_team:
                                                                    currentTeam.slug,
                                                                document:
                                                                    document.id,
                                                            },
                                                        )}
                                                    >
                                                        <Download data-icon="inline-start" />
                                                        Download
                                                    </a>
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No project lifecycle records have been uploaded.
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Sheet
                    open={previewDocument !== null}
                    onOpenChange={(open) => !open && setPreviewDocument(null)}
                >
                    <SheetContent className="w-full overflow-y-auto sm:max-w-5xl">
                        <SheetHeader>
                            <SheetTitle>
                                {previewDocument?.title ?? 'Project document'}
                            </SheetTitle>
                            <SheetDescription>
                                {previewDocument?.originalName ??
                                    'Secure project record'}{' '}
                                · {previewDocument?.category}
                            </SheetDescription>
                        </SheetHeader>
                        {previewDocument && (
                            <div className="flex flex-col gap-4 px-4 pb-6">
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        Scan: {previewDocument.scanStatus}
                                    </Badge>
                                    <Badge variant="secondary">
                                        OCR: {previewDocument.ocrStatus}
                                    </Badge>
                                </div>
                                <iframe
                                    title={`Preview of ${previewDocument.title}`}
                                    src={previewEvidence.url({
                                        current_team: currentTeam.slug,
                                        document: previewDocument.id,
                                    })}
                                    className="h-[72vh] w-full rounded-lg border bg-muted"
                                />
                                <Button asChild variant="outline">
                                    <a
                                        href={downloadEvidence.url({
                                            current_team: currentTeam.slug,
                                            document: previewDocument.id,
                                        })}
                                    >
                                        <Download data-icon="inline-start" />
                                        Download original document
                                    </a>
                                </Button>
                            </div>
                        )}
                    </SheetContent>
                </Sheet>
                {capabilities.manage && (
                    <div className="grid gap-5 xl:grid-cols-2">
                        <Panel icon={CalendarCheck2} title="Add milestone">
                            <Form
                                {...storeMilestone.form(args)}
                                className="grid gap-3 sm:grid-cols-2"
                                resetOnSuccess
                            >
                                <Input
                                    name="code"
                                    required
                                    placeholder="MS-01"
                                />
                                <Input
                                    name="title"
                                    required
                                    placeholder="Milestone title"
                                />
                                <DatePickerField
                                    name="planned_start_date"
                                    label="Planned start"
                                    required
                                />
                                <DatePickerField
                                    name="planned_end_date"
                                    label="Planned end"
                                    required
                                />
                                <Input
                                    name="weight"
                                    type="number"
                                    min="0.01"
                                    max="100"
                                    step="0.01"
                                    required
                                    placeholder="Weight %"
                                />
                                <div className="sm:col-span-2">
                                    <SearchableMultiSelect
                                        name="dependencies"
                                        label="Dependencies"
                                        optional
                                        options={project.milestones.map(
                                            (milestone) => ({
                                                id: milestone.id,
                                                name: `${String(milestone.code)} · ${String(milestone.title)}`,
                                            }),
                                        )}
                                    />
                                </div>
                                <Button type="submit">Add milestone</Button>
                            </Form>
                        </Panel>
                        <Panel icon={Banknote} title="Add budget line">
                            <Form
                                {...storeBudget.form(args)}
                                className="grid gap-3 sm:grid-cols-2"
                                resetOnSuccess
                            >
                                <Input
                                    name="code"
                                    required
                                    placeholder="BL-01"
                                />
                                <Input
                                    name="category"
                                    required
                                    placeholder="Category"
                                />
                                <Input
                                    name="description"
                                    required
                                    placeholder="Description"
                                />
                                <Input
                                    name="approved_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    placeholder="Approved amount"
                                />
                                <ReferenceCatalogSelect
                                    id={`budget-currency-${project.id}`}
                                    name="currency"
                                    label="Currency"
                                    catalog="currency"
                                />
                                <Input
                                    name="financial_year"
                                    required
                                    placeholder="2026/27"
                                />
                                <Button type="submit">Add budget line</Button>
                            </Form>
                        </Panel>
                        <Panel icon={CircleAlert} title="Register risk">
                            <Form
                                {...storeRisk.form(args)}
                                className="grid gap-3 sm:grid-cols-2"
                                resetOnSuccess
                            >
                                <Input
                                    name="code"
                                    required
                                    placeholder="RSK-01"
                                />
                                <Input
                                    name="category"
                                    required
                                    placeholder="Category"
                                />
                                <Input
                                    name="description"
                                    required
                                    placeholder="Risk description"
                                />
                                <Input
                                    name="probability"
                                    type="number"
                                    min="1"
                                    max="5"
                                    required
                                    placeholder="Probability 1–5"
                                />
                                <Input
                                    name="impact"
                                    type="number"
                                    min="1"
                                    max="5"
                                    required
                                    placeholder="Impact 1–5"
                                />
                                <Input
                                    name="mitigation"
                                    required
                                    placeholder="Mitigation"
                                />
                                <Button type="submit">Register risk</Button>
                            </Form>
                        </Panel>
                        <Panel icon={ShoppingCart} title="Add procurement">
                            <Form
                                {...storeProcurement.form(args)}
                                className="grid gap-3 sm:grid-cols-2"
                                resetOnSuccess
                            >
                                <Input
                                    name="reference"
                                    required
                                    placeholder="Tender reference"
                                />
                                <Input
                                    name="title"
                                    required
                                    placeholder="Procurement title"
                                />
                                <Input
                                    name="method"
                                    required
                                    placeholder="Method"
                                />
                                <Input
                                    name="estimated_value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    placeholder="Estimated value"
                                />
                                <ReferenceCatalogSelect
                                    id={`procurement-currency-${project.id}`}
                                    name="currency"
                                    label="Currency"
                                    catalog="currency"
                                />
                                <DatePickerField
                                    name="planned_notice_date"
                                    label="Planned notice date"
                                />
                                <Button type="submit">Add procurement</Button>
                            </Form>
                        </Panel>
                    </div>
                )}
                {capabilities.submitUpdates && (
                    <ProjectProgressForm
                        teamSlug={currentTeam.slug}
                        projectId={project.id}
                        indicators={resultOptions.indicators}
                        counties={resultOptions.counties}
                    />
                )}
                {(capabilities.manage || capabilities.verifyUpdates) &&
                    lifecycleTransition(project, capabilities) && (
                        <Panel
                            icon={ClipboardList}
                            title={
                                lifecycleTransition(project, capabilities)
                                    ?.label ?? 'Advance lifecycle'
                            }
                        >
                            <Form
                                {...transition.form(args)}
                                className="grid gap-4"
                            >
                                <input
                                    type="hidden"
                                    name="transition"
                                    value={
                                        lifecycleTransition(
                                            project,
                                            capabilities,
                                        )?.id
                                    }
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="project-transition-comment">
                                        Decision rationale
                                    </Label>
                                    <Input
                                        id="project-transition-comment"
                                        name="comment"
                                        required
                                        minLength={10}
                                        placeholder="Record the evidence-based lifecycle decision"
                                    />
                                </div>
                                <Button type="submit">
                                    {lifecycleTransition(project, capabilities)
                                        ?.label ?? 'Advance lifecycle'}
                                </Button>
                            </Form>
                        </Panel>
                    )}
                {capabilities.verifyUpdates && (
                    <section className="rounded-xl border bg-card p-5">
                        <h2 className="font-bold">
                            Progress verification queue
                        </h2>
                        <div className="mt-3 space-y-3">
                            {project.progress_updates
                                .filter(
                                    (item) =>
                                        item.verification_status !== 'verified',
                                )
                                .map((item) => (
                                    <FormSheet
                                        key={item.id}
                                        title="Verify progress update"
                                        triggerLabel={`Review ${String(item.reporting_date)}`}
                                        description={`Independently verify the ${String(item.physical_progress)}% physical-progress submission.`}
                                    >
                                        <Form
                                            {...verifyProgress.form({
                                                ...args,
                                                progressUpdate: item.id,
                                            })}
                                            className="grid gap-3"
                                        >
                                            <span className="text-sm">
                                                {String(item.reporting_date)} ·{' '}
                                                {String(item.physical_progress)}
                                                %
                                            </span>
                                            <StaticSearchableSelect
                                                id={`project-verification-${item.id}`}
                                                name="status"
                                                values={[
                                                    'verified',
                                                    'clarification_requested',
                                                    'rejected',
                                                ]}
                                            />
                                            <Input
                                                name="rationale"
                                                required
                                                placeholder="Independent verification rationale"
                                            />
                                            <Button type="submit">
                                                Record
                                            </Button>
                                        </Form>
                                    </FormSheet>
                                ))}
                        </div>
                    </section>
                )}
                <ProjectSchedule
                    milestones={project.milestones}
                    baselines={scheduleBaselines}
                    analysis={scheduleAnalysis}
                    args={args}
                    canManage={capabilities.manage}
                    canReview={capabilities.verifyUpdates}
                    locked={project.status === 'closed'}
                />
                <ProjectResourcePlan
                    resources={resourcePlan}
                    milestones={project.milestones}
                    analysis={earnedValueAnalysis}
                    projectCurrency={project.currency}
                    args={args}
                    canManage={capabilities.manage}
                    locked={project.status === 'closed'}
                />
                <div className="grid gap-5 xl:grid-cols-2">
                    <Register
                        title="Milestones"
                        items={project.milestones}
                        primary="title"
                        secondary="status"
                        kind="milestone"
                        args={args}
                        canManage={capabilities.manage}
                        locked={project.status === 'closed'}
                        milestoneOptions={project.milestones}
                    />
                    <Register
                        title="Budget lines"
                        items={project.budget_lines}
                        primary="description"
                        secondary="approved_amount"
                        kind="budget"
                        args={args}
                        canManage={capabilities.manage}
                        locked={project.status === 'closed'}
                    />
                    <Register
                        title="Risks"
                        items={project.risks}
                        primary="description"
                        secondary="status"
                        kind="risk"
                        args={args}
                        canManage={capabilities.manage}
                        locked={project.status === 'closed'}
                    />
                    <Register
                        title="Procurements"
                        items={project.procurements}
                        primary="title"
                        secondary="status"
                        kind="procurement"
                        args={args}
                        canManage={capabilities.manage}
                        locked={project.status === 'closed'}
                    />
                    <Register
                        title="Progress history"
                        items={project.progress_updates}
                        primary="narrative"
                        secondary="verification_status"
                        kind="progress"
                        args={args}
                        canManage={false}
                        locked
                    />
                </div>
            </div>
        </>
    );
}
function canPreview(document: ProjectDocument): boolean {
    return [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
    ].includes(document.mimeType ?? '');
}
function lifecycleTransition(
    project: Project,
    capabilities: Capabilities,
): { id: string; label: string } | null {
    if (capabilities.manage && project.lifecycle_stage === 'initiation') {
        return { id: 'plan', label: 'Move to planning' };
    }

    if (capabilities.manage && project.lifecycle_stage === 'planning') {
        return { id: 'start_execution', label: 'Start execution' };
    }

    if (capabilities.manage && project.lifecycle_stage === 'execution') {
        return { id: 'submit_closure', label: 'Submit closure' };
    }

    if (
        capabilities.verifyUpdates &&
        project.lifecycle_stage === 'closure_review'
    ) {
        return { id: 'approve_closure', label: 'Approve closure' };
    }

    return null;
}
function Field({
    id,
    name,
    label,
    error,
}: {
    id: string;
    name: string;
    label: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={name}
                required
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
            />
            {error && (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg bg-white/10 p-3">
            <p className="text-xs text-[#c7d6dd]">{label}</p>
            <p className="mt-1 font-bold">{value}</p>
        </div>
    );
}
function Info({ title, values }: { title: string; values: string[] }) {
    return (
        <div className="rounded-xl border bg-card p-5">
            <h2 className="font-bold">{title}</h2>
            <ul className="mt-3 space-y-1 text-sm text-muted-foreground">
                {values.map((value) => (
                    <li key={value}>{value}</li>
                ))}
            </ul>
        </div>
    );
}
function Panel({
    icon: Icon,
    title,
    children,
}: {
    icon: LucideIcon;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <FormSheet
            title={title}
            triggerLabel={title}
            icon={Icon}
            description="Complete the governed project action and record it in the audit trail."
        >
            {children}
        </FormSheet>
    );
}

function ProjectSchedule({
    milestones,
    baselines,
    analysis,
    args,
    canManage,
    canReview,
    locked,
}: {
    milestones: Entity[];
    baselines: ScheduleBaseline[];
    analysis: ScheduleAnalysis | null;
    args: { current_team: string; project: string };
    canManage: boolean;
    canReview: boolean;
    locked: boolean;
}) {
    const scheduled = milestones
        .map((milestone) => ({
            milestone,
            start: parseScheduleDate(milestone.planned_start_date),
            end: parseScheduleDate(milestone.planned_end_date),
        }))
        .filter(
            (
                item,
            ): item is {
                milestone: Entity;
                start: number;
                end: number;
            } => item.start !== null && item.end !== null,
        );

    const minimum = Math.min(...scheduled.map(({ start }) => start));
    const maximum = Math.max(...scheduled.map(({ end }) => end));
    const duration = Math.max(1, maximum - minimum);
    const milestoneById = new Map(
        milestones.map((milestone) => [milestone.id, milestone]),
    );
    const timingById = new Map(
        (analysis?.milestones ?? []).map((milestone) => [
            milestone.id,
            milestone,
        ]),
    );
    const pendingBaseline = baselines.find(
        (baseline) => baseline.status === 'pending',
    );

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div className="flex flex-col gap-1.5">
                    <CardTitle>Delivery schedule assurance</CardTitle>
                    <CardDescription>
                        Approved schedule baselines, deterministic critical
                        path, float and forecast variance as at{' '}
                        {analysis?.as_of ?? '—'}.
                    </CardDescription>
                </div>
                {canManage && !locked && !pendingBaseline && (
                    <FormSheet
                        title="Capture schedule baseline"
                        triggerLabel="Capture baseline"
                        icon={GitBranch}
                        description="Freeze the complete current milestone plan for independent approval and future variance measurement."
                    >
                        <Form
                            {...storeScheduleBaseline.form(args)}
                            className="flex flex-col gap-4"
                            resetOnSuccess
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="schedule-baseline-reason">
                                            Baseline rationale
                                        </Label>
                                        <Textarea
                                            id="schedule-baseline-reason"
                                            name="baseline_reason"
                                            required
                                            minLength={20}
                                            aria-invalid={Boolean(
                                                errors.baseline_reason,
                                            )}
                                            placeholder="Record the planning evidence and authority supporting this proposed baseline."
                                        />
                                        {errors.baseline_reason && (
                                            <p
                                                role="alert"
                                                className="text-sm text-destructive"
                                            >
                                                {errors.baseline_reason}
                                            </p>
                                        )}
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Submit baseline for review
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                {analysis && (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <ScheduleMetric
                            label="Approved baseline"
                            value={
                                analysis.baseline_version === null
                                    ? 'Not approved'
                                    : `Version ${analysis.baseline_version}`
                            }
                        />
                        <ScheduleMetric
                            label="Critical path"
                            value={
                                analysis.critical_path_codes.join(' → ') || '—'
                            }
                        />
                        <ScheduleMetric
                            label="Forecast finish"
                            value={analysis.forecast_finish}
                        />
                        <ScheduleMetric
                            label="Forecast variance"
                            value={formatVariance(
                                analysis.forecast_variance_days,
                            )}
                        />
                    </div>
                )}
                {pendingBaseline && canReview && (
                    <FormSheet
                        title={`Review schedule baseline v${pendingBaseline.version}`}
                        triggerLabel={`Review pending baseline v${pendingBaseline.version}`}
                        icon={GitBranch}
                        description="Independently approve the checksum-bound milestone snapshot or reject it with evidence."
                    >
                        <div className="flex flex-col gap-4">
                            <p className="text-sm text-muted-foreground">
                                Requested by {pendingBaseline.requester}.
                                Critical path:{' '}
                                {pendingBaseline.analysis.critical_path_codes.join(
                                    ' → ',
                                ) || '—'}
                            </p>
                            <Form
                                {...decideScheduleBaseline.form({
                                    ...args,
                                    scheduleBaseline: pendingBaseline.id,
                                })}
                                className="flex flex-col gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <StaticSearchableSelect
                                            id={`schedule-baseline-decision-${pendingBaseline.id}`}
                                            name="decision"
                                            values={['approve', 'reject']}
                                        />
                                        <Textarea
                                            name="decision_rationale"
                                            required
                                            minLength={20}
                                            aria-invalid={Boolean(
                                                errors.decision_rationale,
                                            )}
                                            placeholder="Record the independent schedule review evidence and decision rationale."
                                        />
                                        {(errors.decision ||
                                            errors.decision_rationale) && (
                                            <p
                                                role="alert"
                                                className="text-sm text-destructive"
                                            >
                                                {errors.decision ??
                                                    errors.decision_rationale}
                                            </p>
                                        )}
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Record independent decision
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </div>
                    </FormSheet>
                )}
                {baselines.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {baselines.map((baseline) => (
                            <Badge
                                key={baseline.id}
                                variant={
                                    baseline.status === 'approved'
                                        ? 'default'
                                        : 'outline'
                                }
                                title={baseline.snapshotChecksum}
                            >
                                v{baseline.version} · {baseline.status} ·{' '}
                                {baseline.analysis.project_finish}
                            </Badge>
                        ))}
                    </div>
                )}
                {scheduled.length === 0 ? (
                    <div className="rounded-lg border border-dashed px-6 py-10 text-center">
                        <CalendarCheck2
                            className="mx-auto size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="mt-3 font-medium">
                            No scheduled milestones
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Add a milestone with planned dates to build the
                            delivery timeline.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto" tabIndex={0}>
                        <div className="min-w-[48rem]">
                            <div className="grid grid-cols-[15rem_1fr] gap-4 border-b pb-2 text-xs text-muted-foreground">
                                <span>Milestone and dependencies</span>
                                <span className="flex justify-between">
                                    <time dateTime={scheduleDate(minimum)}>
                                        {scheduleDate(minimum)}
                                    </time>
                                    <time dateTime={scheduleDate(maximum)}>
                                        {scheduleDate(maximum)}
                                    </time>
                                </span>
                            </div>
                            <div className="divide-y">
                                {scheduled.map(({ milestone, start, end }) => {
                                    const timing = timingById.get(milestone.id);
                                    const left =
                                        ((start - minimum) / duration) * 100;
                                    const width = Math.max(
                                        2,
                                        ((end - start) / duration) * 100,
                                    );
                                    const progress = Math.min(
                                        100,
                                        Math.max(
                                            0,
                                            Number(milestone.progress ?? 0),
                                        ),
                                    );
                                    const dependencies = entityStringList(
                                        milestone,
                                        'dependencies',
                                    )
                                        .map((id) => milestoneById.get(id))
                                        .filter(
                                            (
                                                dependency,
                                            ): dependency is Entity =>
                                                dependency !== undefined,
                                        );

                                    return (
                                        <div
                                            key={milestone.id}
                                            className="grid grid-cols-[15rem_1fr] gap-4 py-4"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {String(milestone.code)} ·{' '}
                                                    {String(milestone.title)}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {dependencies.length
                                                        ? `Depends on ${dependencies.map((dependency) => String(dependency.code)).join(', ')}`
                                                        : 'No dependencies'}
                                                </p>
                                                {timing?.is_critical && (
                                                    <Badge className="mt-2">
                                                        Critical path
                                                    </Badge>
                                                )}
                                            </div>
                                            <div>
                                                <div className="relative mt-1 h-6 rounded bg-muted">
                                                    <div
                                                        className="absolute top-0 h-6 overflow-hidden rounded border border-primary/30 bg-primary/15"
                                                        style={{
                                                            left: `${left}%`,
                                                            width: `${width}%`,
                                                        }}
                                                        aria-hidden="true"
                                                    >
                                                        <div
                                                            className="h-full bg-primary/70"
                                                            style={{
                                                                width: `${progress}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    <time
                                                        dateTime={scheduleDate(
                                                            start,
                                                        )}
                                                    >
                                                        {scheduleDate(start)}
                                                    </time>{' '}
                                                    to{' '}
                                                    <time
                                                        dateTime={scheduleDate(
                                                            end,
                                                        )}
                                                    >
                                                        {scheduleDate(end)}
                                                    </time>{' '}
                                                    · {progress}% complete ·{' '}
                                                    {String(milestone.status)}
                                                    {timing
                                                        ? ` · ${timing.total_float_days}d float · forecast ${timing.forecast_end} · ${formatVariance(timing.forecast_variance_days)}`
                                                        : ''}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function ScheduleMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border bg-muted/30 p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-semibold">{value}</p>
        </div>
    );
}

function ProjectResourcePlan({
    resources,
    milestones,
    analysis,
    projectCurrency,
    args,
    canManage,
    locked,
}: {
    resources: ProjectResource[];
    milestones: Entity[];
    analysis: EarnedValueAnalysis;
    projectCurrency: string;
    args: { current_team: string; project: string };
    canManage: boolean;
    locked: boolean;
}) {
    return (
        <div className="grid gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(20rem,0.7fr)]">
            <Card>
                <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1.5">
                        <CardTitle>Resource and capacity plan</CardTitle>
                        <CardDescription>
                            Daily capacity, milestone allocations and derived
                            costs in the project currency. Overlapping work
                            cannot exceed registered capacity.
                        </CardDescription>
                    </div>
                    {canManage && !locked && (
                        <div className="flex flex-wrap gap-2">
                            <FormSheet
                                title="Register project resource"
                                triggerLabel="Add resource"
                                icon={UsersRound}
                                description={`Capacity and rates are recorded in ${projectCurrency}, inherited from the project.`}
                            >
                                <Form
                                    {...storeResource.form(args)}
                                    resetOnSuccess
                                    className="grid gap-4"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <Field
                                                id="resource-code"
                                                name="code"
                                                label="Resource code"
                                                error={errors.code}
                                            />
                                            <Field
                                                id="resource-name"
                                                name="name"
                                                label="Resource name"
                                                error={errors.name}
                                            />
                                            <SearchableSelect
                                                id="resource-type"
                                                name="resource_type"
                                                label="Resource type"
                                                options={[
                                                    {
                                                        id: 'human',
                                                        name: 'Human resource',
                                                    },
                                                    {
                                                        id: 'equipment',
                                                        name: 'Equipment',
                                                    },
                                                    {
                                                        id: 'material',
                                                        name: 'Material',
                                                    },
                                                    {
                                                        id: 'service',
                                                        name: 'Service',
                                                    },
                                                ]}
                                                error={errors.resource_type}
                                            />
                                            <SearchableSelect
                                                id="resource-capacity-unit"
                                                name="capacity_unit"
                                                label="Capacity unit"
                                                options={[
                                                    {
                                                        id: 'hours',
                                                        name: 'Hours',
                                                    },
                                                    {
                                                        id: 'days',
                                                        name: 'Days',
                                                    },
                                                    {
                                                        id: 'units',
                                                        name: 'Units',
                                                    },
                                                ]}
                                                error={errors.capacity_unit}
                                            />
                                            <div className="grid gap-2">
                                                <Label htmlFor="resource-capacity">
                                                    Capacity per day
                                                </Label>
                                                <Input
                                                    id="resource-capacity"
                                                    name="capacity_per_day"
                                                    type="number"
                                                    min="0.0001"
                                                    step="0.0001"
                                                    required
                                                    aria-invalid={Boolean(
                                                        errors.capacity_per_day,
                                                    )}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="resource-rate">
                                                    Cost per unit (
                                                    {projectCurrency})
                                                </Label>
                                                <Input
                                                    id="resource-rate"
                                                    name="cost_rate"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    required
                                                    aria-invalid={Boolean(
                                                        errors.cost_rate,
                                                    )}
                                                />
                                            </div>
                                            <DatePickerField
                                                name="available_from"
                                                label="Available from"
                                                required
                                                error={errors.available_from}
                                            />
                                            <DatePickerField
                                                name="available_to"
                                                label="Available to"
                                                required
                                                error={errors.available_to}
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Register resource
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </FormSheet>
                            {resources.length > 0 && milestones.length > 0 && (
                                <FormSheet
                                    title="Allocate project resource"
                                    triggerLabel="Add allocation"
                                    icon={CalendarCheck2}
                                    description="Allocate uniform daily capacity within both the resource and milestone periods."
                                >
                                    <Form
                                        {...storeResourceAllocation.form(args)}
                                        resetOnSuccess
                                        className="grid gap-4"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <SearchableSelect
                                                    id="allocation-resource"
                                                    name="project_resource_id"
                                                    label="Resource"
                                                    options={resources
                                                        .filter(
                                                            (resource) =>
                                                                resource.status ===
                                                                'active',
                                                        )
                                                        .map((resource) => ({
                                                            id: resource.id,
                                                            name: `${resource.code} · ${resource.name}`,
                                                        }))}
                                                    error={
                                                        errors.project_resource_id
                                                    }
                                                />
                                                <SearchableSelect
                                                    id="allocation-milestone"
                                                    name="project_milestone_id"
                                                    label="Milestone"
                                                    options={milestones.map(
                                                        (milestone) => ({
                                                            id: milestone.id,
                                                            name: `${String(milestone.code)} · ${String(milestone.title)}`,
                                                        }),
                                                    )}
                                                    error={
                                                        errors.project_milestone_id
                                                    }
                                                />
                                                <DatePickerField
                                                    name="starts_on"
                                                    label="Allocation start"
                                                    required
                                                    error={errors.starts_on}
                                                />
                                                <DatePickerField
                                                    name="ends_on"
                                                    label="Allocation end"
                                                    required
                                                    error={errors.ends_on}
                                                />
                                                <div className="grid gap-2">
                                                    <Label htmlFor="allocation-rate">
                                                        Planned units per day
                                                    </Label>
                                                    <Input
                                                        id="allocation-rate"
                                                        name="planned_units_per_day"
                                                        type="number"
                                                        min="0.0001"
                                                        step="0.0001"
                                                        required
                                                        aria-invalid={Boolean(
                                                            errors.planned_units_per_day,
                                                        )}
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="allocation-notes">
                                                        Planning notes
                                                    </Label>
                                                    <Textarea
                                                        id="allocation-notes"
                                                        name="notes"
                                                        maxLength={2000}
                                                    />
                                                </div>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    Allocate capacity
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </FormSheet>
                            )}
                        </div>
                    )}
                </CardHeader>
                <CardContent>
                    {resources.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                            No project resources have been registered.
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {resources.map((resource) => (
                                <div
                                    key={resource.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">
                                                {resource.code} ·{' '}
                                                {resource.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {formatNumber(
                                                    resource.capacityPerDay,
                                                )}{' '}
                                                {resource.capacityUnit}/day ·{' '}
                                                {formatCurrency(
                                                    resource.costRate,
                                                    resource.currency,
                                                )}{' '}
                                                per unit
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            <Badge variant="outline">
                                                {resource.type}
                                            </Badge>
                                            <Badge variant="secondary">
                                                {resource.status}
                                            </Badge>
                                        </div>
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Available {resource.availableFrom} –{' '}
                                        {resource.availableTo} · planned cost{' '}
                                        {formatCurrency(
                                            resource.plannedCost,
                                            resource.currency,
                                        )}
                                    </p>
                                    {resource.allocations.length > 0 ? (
                                        <div className="mt-4 space-y-2">
                                            {resource.allocations.map(
                                                (allocation) => (
                                                    <div
                                                        key={allocation.id}
                                                        className="grid gap-1 rounded-lg bg-muted/40 p-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto]"
                                                    >
                                                        <div>
                                                            <p className="font-medium">
                                                                {
                                                                    allocation.milestone
                                                                }
                                                            </p>
                                                            <p className="text-muted-foreground">
                                                                {
                                                                    allocation.startsOn
                                                                }{' '}
                                                                –{' '}
                                                                {
                                                                    allocation.endsOn
                                                                }{' '}
                                                                ·{' '}
                                                                {formatNumber(
                                                                    allocation.plannedUnits,
                                                                )}{' '}
                                                                {
                                                                    resource.capacityUnit
                                                                }
                                                            </p>
                                                        </div>
                                                        <p className="font-medium">
                                                            {formatCurrency(
                                                                allocation.plannedCost,
                                                                resource.currency,
                                                            )}
                                                        </p>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    ) : (
                                        <p className="mt-4 text-sm text-muted-foreground">
                                            No milestone allocations yet.
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Earned-value forecast</CardTitle>
                    <CardDescription>
                        {analysis.method} · as of {analysis.as_of}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {!analysis.available ? (
                        <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                            Approve a complete weighted schedule baseline and
                            record a positive budget to enable forecasting.
                        </div>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <ScheduleMetric
                                label="Planned value"
                                value={formatCurrency(
                                    analysis.planned_value ?? 0,
                                    projectCurrency,
                                )}
                            />
                            <ScheduleMetric
                                label="Earned value"
                                value={formatCurrency(
                                    analysis.earned_value,
                                    projectCurrency,
                                )}
                            />
                            <ScheduleMetric
                                label="Actual cost"
                                value={formatCurrency(
                                    analysis.actual_cost,
                                    projectCurrency,
                                )}
                            />
                            <ScheduleMetric
                                label="Estimate at completion"
                                value={
                                    analysis.estimate_at_completion === null
                                        ? 'Unavailable'
                                        : formatCurrency(
                                              analysis.estimate_at_completion,
                                              projectCurrency,
                                          )
                                }
                            />
                            <ScheduleMetric
                                label="Cost performance index"
                                value={
                                    analysis.cost_performance_index?.toFixed(
                                        4,
                                    ) ?? 'Unavailable'
                                }
                            />
                            <ScheduleMetric
                                label="Schedule performance index"
                                value={
                                    analysis.schedule_performance_index?.toFixed(
                                        4,
                                    ) ?? 'Unavailable'
                                }
                            />
                            <ScheduleMetric
                                label="Variance at completion"
                                value={
                                    analysis.variance_at_completion === null
                                        ? 'Unavailable'
                                        : formatCurrency(
                                              analysis.variance_at_completion,
                                              projectCurrency,
                                          )
                                }
                            />
                            <ScheduleMetric
                                label="To-complete index"
                                value={
                                    analysis.to_complete_performance_index?.toFixed(
                                        4,
                                    ) ?? 'Unavailable'
                                }
                            />
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function formatVariance(days: number | null): string {
    if (days === null) {
        return 'Awaiting approved baseline';
    }

    if (days === 0) {
        return 'On baseline';
    }

    return `${Math.abs(days)} day${Math.abs(days) === 1 ? '' : 's'} ${days > 0 ? 'late' : 'early'}`;
}

function parseScheduleDate(value: unknown): number | null {
    if (typeof value !== 'string') {
        return null;
    }

    const timestamp = Date.parse(`${value.slice(0, 10)}T00:00:00Z`);

    return Number.isNaN(timestamp) ? null : timestamp;
}

function scheduleDate(timestamp: number): string {
    return new Date(timestamp).toISOString().slice(0, 10);
}
function Register({
    title,
    items,
    primary,
    secondary,
    kind,
    args,
    canManage,
    locked,
    milestoneOptions = [],
}: {
    title: string;
    items: Entity[];
    primary: string;
    secondary: string;
    kind: RegisterKind;
    args: { current_team: string; project: string };
    canManage: boolean;
    locked: boolean;
    milestoneOptions?: Entity[];
}) {
    return (
        <section className="rounded-xl border bg-card p-5">
            <h2 className="font-bold">{title}</h2>
            <div className="mt-3 divide-y">
                {items.length ? (
                    items.map((item) => (
                        <RegisterRow
                            key={item.id}
                            item={item}
                            primary={primary}
                            secondary={secondary}
                            kind={kind}
                            args={args}
                            canManage={canManage}
                            locked={locked}
                            milestoneOptions={milestoneOptions}
                        />
                    ))
                ) : (
                    <p className="py-6 text-sm text-muted-foreground">
                        No records yet.
                    </p>
                )}
            </div>
        </section>
    );
}

type RegisterKind =
    'milestone' | 'budget' | 'risk' | 'procurement' | 'progress';

function RegisterRow({
    item,
    primary,
    secondary,
    kind,
    args,
    canManage,
    locked,
    milestoneOptions,
}: {
    item: Entity;
    primary: string;
    secondary: string;
    kind: RegisterKind;
    args: { current_team: string; project: string };
    canManage: boolean;
    locked: boolean;
    milestoneOptions: Entity[];
}) {
    const [editing, setEditing] = useState(false);
    const editable = canManage && kind !== 'progress' && !locked;

    return (
        <>
            <div className="flex items-center justify-between gap-3 py-3 text-sm">
                <span>{String(item[primary] ?? '—')}</span>
                <div className="flex items-center gap-2">
                    <span className="text-muted-foreground">
                        {String(item[secondary] ?? '—')}
                    </span>
                    {editable && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    aria-label={`Actions for ${String(item[primary] ?? kind)}`}
                                >
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuGroup>
                                    <DropdownMenuItem
                                        onSelect={() => setEditing(true)}
                                    >
                                        <Pencil /> Amend record
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>
            <Sheet open={editing} onOpenChange={setEditing}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Amend {registerLabel(kind)}</SheetTitle>
                        <SheetDescription>
                            Record the updated control values and the attributed
                            reason retained in immutable audit history.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-8">
                        <RegisterEditForm
                            item={item}
                            kind={kind}
                            args={args}
                            milestoneOptions={milestoneOptions}
                        />
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function RegisterEditForm({
    item,
    kind,
    args,
    milestoneOptions,
}: {
    item: Entity;
    kind: RegisterKind;
    args: { current_team: string; project: string };
    milestoneOptions: Entity[];
}) {
    const route =
        kind === 'milestone'
            ? updateMilestone.form({ ...args, milestone: item.id })
            : kind === 'budget'
              ? updateBudget.form({ ...args, budgetLine: item.id })
              : kind === 'risk'
                ? updateRisk.form({ ...args, risk: item.id })
                : updateProcurement.form({
                      ...args,
                      procurement: item.id,
                  });

    return (
        <Form {...route} className="grid gap-4">
            {({ errors, processing }) => (
                <>
                    {kind === 'milestone' && (
                        <>
                            <EditInput item={item} name="title" label="Title" />
                            <EditInput
                                item={item}
                                name="description"
                                label="Description"
                            />
                            <DatePickerField
                                name="planned_start_date"
                                label="Planned start"
                                required
                                defaultValue={entityValue(
                                    item,
                                    'planned_start_date',
                                )}
                            />
                            <DatePickerField
                                name="planned_end_date"
                                label="Planned end"
                                required
                                defaultValue={entityValue(
                                    item,
                                    'planned_end_date',
                                )}
                            />
                            <DatePickerField
                                name="actual_start_date"
                                label="Actual start"
                                defaultValue={entityValue(
                                    item,
                                    'actual_start_date',
                                )}
                            />
                            <DatePickerField
                                name="actual_end_date"
                                label="Actual end"
                                defaultValue={entityValue(
                                    item,
                                    'actual_end_date',
                                )}
                            />
                            <EditInput
                                item={item}
                                name="weight"
                                label="Weight (%)"
                                type="number"
                            />
                            <SearchableMultiSelect
                                name="dependencies"
                                label="Dependencies"
                                optional
                                options={milestoneOptions
                                    .filter(
                                        (milestone) => milestone.id !== item.id,
                                    )
                                    .map((milestone) => ({
                                        id: milestone.id,
                                        name: `${String(milestone.code)} · ${String(milestone.title)}`,
                                    }))}
                                defaultValues={entityStringList(
                                    item,
                                    'dependencies',
                                )}
                                error={errors.dependencies}
                            />
                            <EditInput
                                item={item}
                                name="progress"
                                label="Progress (%)"
                                type="number"
                            />
                            <StaticSearchableSelect
                                id={`milestone-status-${item.id}`}
                                name="status"
                                values={[
                                    'not_started',
                                    'in_progress',
                                    'delayed',
                                    'completed',
                                ]}
                                defaultValue={entityValue(item, 'status')}
                            />
                        </>
                    )}
                    {kind === 'budget' && (
                        <>
                            <EditInput
                                item={item}
                                name="category"
                                label="Category"
                            />
                            <EditInput
                                item={item}
                                name="description"
                                label="Description"
                            />
                            {[
                                ['approved_amount', 'Approved amount'],
                                ['committed_amount', 'Committed amount'],
                                ['actual_amount', 'Actual amount'],
                            ].map(([name, label]) => (
                                <EditInput
                                    key={name}
                                    item={item}
                                    name={name}
                                    label={label}
                                    type="number"
                                />
                            ))}
                            <EditInput
                                item={item}
                                name="funding_source"
                                label="Funding source"
                            />
                        </>
                    )}
                    {kind === 'risk' && (
                        <>
                            <EditInput
                                item={item}
                                name="category"
                                label="Category"
                            />
                            <EditInput
                                item={item}
                                name="description"
                                label="Description"
                            />
                            {[
                                ['probability', 'Probability (1–5)'],
                                ['impact', 'Impact (1–5)'],
                                [
                                    'residual_probability',
                                    'Residual probability (1–5)',
                                ],
                                ['residual_impact', 'Residual impact (1–5)'],
                            ].map(([name, label]) => (
                                <EditInput
                                    key={name}
                                    item={item}
                                    name={name}
                                    label={label}
                                    type="number"
                                />
                            ))}
                            <EditInput
                                item={item}
                                name="mitigation"
                                label="Mitigation"
                            />
                            <StaticSearchableSelect
                                id={`risk-status-${item.id}`}
                                name="status"
                                values={[
                                    'open',
                                    'monitoring',
                                    'mitigated',
                                    'closed',
                                ]}
                                defaultValue={entityValue(item, 'status')}
                            />
                            <DatePickerField
                                name="review_due_date"
                                label="Review due"
                                defaultValue={entityValue(
                                    item,
                                    'review_due_date',
                                )}
                            />
                        </>
                    )}
                    {kind === 'procurement' && (
                        <>
                            <EditInput item={item} name="title" label="Title" />
                            <EditInput
                                item={item}
                                name="method"
                                label="Method"
                            />
                            <StaticSearchableSelect
                                id={`procurement-status-${item.id}`}
                                name="status"
                                values={[
                                    'planned',
                                    'advertised',
                                    'evaluation',
                                    'awarded',
                                    'contracted',
                                    'completed',
                                    'cancelled',
                                ]}
                                defaultValue={entityValue(item, 'status')}
                            />
                            <EditInput
                                item={item}
                                name="estimated_value"
                                label="Estimated value"
                                type="number"
                            />
                            <EditInput
                                item={item}
                                name="contract_value"
                                label="Contract value"
                                type="number"
                            />
                            <DatePickerField
                                name="planned_notice_date"
                                label="Planned notice date"
                                defaultValue={entityValue(
                                    item,
                                    'planned_notice_date',
                                )}
                            />
                            <DatePickerField
                                name="award_date"
                                label="Award date"
                                defaultValue={entityValue(item, 'award_date')}
                            />
                            <EditInput
                                item={item}
                                name="supplier_name"
                                label="Supplier name"
                            />
                            <EditInput
                                item={item}
                                name="contract_reference"
                                label="Contract reference"
                            />
                        </>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor={`amendment-reason-${item.id}`}>
                            Amendment reason
                        </Label>
                        <Input
                            id={`amendment-reason-${item.id}`}
                            name="amendment_reason"
                            required
                            minLength={10}
                            aria-invalid={Boolean(errors.amendment_reason)}
                        />
                        {errors.amendment_reason && (
                            <p
                                role="alert"
                                className="text-xs text-destructive"
                            >
                                {errors.amendment_reason}
                            </p>
                        )}
                    </div>
                    <Button type="submit" disabled={processing}>
                        Record amendment
                    </Button>
                </>
            )}
        </Form>
    );
}

function EditInput({
    item,
    name,
    label,
    type = 'text',
}: {
    item: Entity;
    name: string;
    label: string;
    type?: 'text' | 'number';
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${name}-${item.id}`}>{label}</Label>
            <Input
                id={`${name}-${item.id}`}
                name={name}
                type={type}
                step={type === 'number' ? '0.01' : undefined}
                defaultValue={entityValue(item, name)}
            />
        </div>
    );
}

function entityValue(item: Entity, key: string): string {
    return item[key] === null || item[key] === undefined
        ? ''
        : String(item[key]);
}

function entityStringList(item: Entity, key: string): string[] {
    return Array.isArray(item[key])
        ? item[key].filter(
              (value): value is string => typeof value === 'string',
          )
        : [];
}

function registerLabel(kind: RegisterKind): string {
    return kind === 'budget' ? 'budget line' : kind;
}
