import { Form } from '@inertiajs/react';
import {
    ClipboardCheck,
    Download,
    Eye,
    MoreHorizontal,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import {
    download as downloadEvidence,
    preview as previewEvidence,
} from '@/routes/evidence';
import { store } from '@/routes/monitoring-evaluation/evaluations/findings';
import { verify as verifyActionUpdate } from '@/routes/monitoring-evaluation/finding-action-updates';
import { store as storeActionDocument } from '@/routes/monitoring-evaluation/finding-actions/documents';
import { store as storeActionUpdate } from '@/routes/monitoring-evaluation/finding-actions/updates';
import { verify } from '@/routes/monitoring-evaluation/finding-updates';
import { close } from '@/routes/monitoring-evaluation/findings';
import { store as storeFindingAction } from '@/routes/monitoring-evaluation/findings/actions';
import { store as storeFindingDocument } from '@/routes/monitoring-evaluation/findings/documents';
import { store as storeFindingUpdate } from '@/routes/monitoring-evaluation/findings/updates';

type Option = { id: string; name: string; countyId?: string | null };
type EvaluationOption = Option & { status: string };
export type EvaluationFindingItem = {
    id: string;
    evaluation: string;
    reference: string;
    title: string;
    recommendation: string;
    severity: string;
    status: string;
    dueAt: string;
    reminderSentAt: string | null;
    escalatedAt: string | null;
    progress: number;
    owner: string;
    ownerId: string;
    county: CountyIdentityValue | null;
    documents: {
        id: string;
        title: string;
        scanStatus: string;
        originalName: string;
    }[];
    updates: {
        id: string;
        progress: number;
        narrative: string;
        status: string;
        submittedBy: string;
    }[];
    actions: EvaluationFindingActionItem[];
};

type EvaluationFindingActionItem = {
    id: string;
    code: string;
    title: string;
    description: string;
    successIndicator: string;
    target: string;
    ownerId: string;
    owner: string;
    dueAt: string;
    weight: number;
    progress: number;
    status: string;
    documents: Array<{
        id: string;
        title: string;
        originalName: string | null;
        sourceType: string;
        scanStatus: string;
    }>;
    updates: Array<{
        id: string;
        progress: number;
        narrative: string;
        status: string;
        submittedBy: string;
        verifiedBy: string | null;
    }>;
};

export default function EvaluationFindingRegister({
    findings,
    evaluations,
    owners,
    canManage,
    canVerify,
    currentUserId,
}: {
    findings: EvaluationFindingItem[];
    evaluations: EvaluationOption[];
    owners: Option[];
    canManage: boolean;
    canVerify: boolean;
    currentUserId: string;
}) {
    const approved = evaluations.filter((item) => item.status === 'approved');
    const [evaluationId, setEvaluationId] = useState(approved[0]?.id ?? '');

    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
            <div className="flex flex-col justify-between gap-4 border-b p-5 sm:flex-row sm:items-start sm:p-6">
                <div className="flex gap-3">
                    <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <ClipboardCheck aria-hidden="true" />
                    </span>
                    <div>
                        <h2 className="font-bold">
                            Evaluation recommendations
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Governed findings, accountable owners, due dates,
                            evidence responses, and independent closure.
                        </p>
                    </div>
                </div>
                {canManage && approved.length > 0 && (
                    <FormSheet
                        title="Issue evaluation finding"
                        description="Bind a recommendation to an approved evaluation and assign a scoped accountable owner."
                        triggerLabel="Issue finding"
                        icon={ClipboardCheck}
                        size="lg"
                    >
                        <Form
                            {...store.form({ evaluation: evaluationId })}
                            className="flex flex-col gap-4"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <SearchableSelect
                                        id="finding-evaluation"
                                        name="evaluation_selector"
                                        label="Approved evaluation"
                                        options={approved}
                                        value={evaluationId}
                                        onValueChange={setEvaluationId}
                                    />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            id="finding-reference"
                                            label="Reference"
                                            error={errors.reference}
                                        >
                                            <Input
                                                id="finding-reference"
                                                name="reference"
                                                required
                                                aria-invalid={Boolean(
                                                    errors.reference,
                                                )}
                                            />
                                        </Field>
                                        <Field
                                            id="finding-title"
                                            label="Title"
                                            error={errors.title}
                                        >
                                            <Input
                                                id="finding-title"
                                                name="title"
                                                required
                                                aria-invalid={Boolean(
                                                    errors.title,
                                                )}
                                            />
                                        </Field>
                                        <Field
                                            id="finding-severity"
                                            label="Severity"
                                            error={errors.severity}
                                        >
                                            <StaticSearchableSelect
                                                id="finding-severity"
                                                name="severity"
                                                values={[
                                                    'low',
                                                    'moderate',
                                                    'high',
                                                    'critical',
                                                ]}
                                            />
                                        </Field>
                                        <SearchableSelect
                                            id="finding-owner"
                                            name="accountable_owner_id"
                                            label="Accountable owner"
                                            options={owners}
                                            error={errors.accountable_owner_id}
                                        />
                                    </div>
                                    <Field
                                        id="finding-text"
                                        label="Finding"
                                        error={errors.finding}
                                    >
                                        <Textarea
                                            id="finding-text"
                                            name="finding"
                                            required
                                            rows={4}
                                            aria-invalid={Boolean(
                                                errors.finding,
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        id="finding-recommendation"
                                        label="Recommendation"
                                        error={errors.recommendation}
                                    >
                                        <Textarea
                                            id="finding-recommendation"
                                            name="recommendation"
                                            required
                                            rows={4}
                                            aria-invalid={Boolean(
                                                errors.recommendation,
                                            )}
                                        />
                                    </Field>
                                    <DatePickerField
                                        name="due_at"
                                        label="Response due date"
                                        min={new Date()
                                            .toISOString()
                                            .slice(0, 10)}
                                        required
                                        error={errors.due_at}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing || !evaluationId}
                                    >
                                        {processing
                                            ? 'Issuing…'
                                            : 'Issue finding'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                )}
            </div>
            {findings.length === 0 ? (
                <WorkspaceEmptyState
                    title="No evaluation findings"
                    description="Approved evaluation findings and recommendation follow-up will appear here."
                    className="min-h-56 border-0"
                />
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Finding</TableHead>
                            <TableHead>Scope</TableHead>
                            <TableHead>Owner / due</TableHead>
                            <TableHead>Progress</TableHead>
                            <TableHead>
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {findings.map((item) => (
                            <TableRow key={item.id}>
                                <TableCell>
                                    <div className="flex max-w-md flex-col gap-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-semibold">
                                                {item.reference}
                                            </span>
                                            <Badge variant="outline">
                                                {item.severity}
                                            </Badge>
                                            <Badge variant="secondary">
                                                {item.status}
                                            </Badge>
                                        </div>
                                        <span>{item.title}</span>
                                        <span className="line-clamp-2 text-xs text-muted-foreground">
                                            {item.recommendation}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {item.county ? (
                                        <CountyIdentity
                                            county={item.county}
                                            compact
                                        />
                                    ) : (
                                        <span className="text-sm">
                                            National
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-col gap-1 text-sm">
                                        <span>{item.owner}</span>
                                        <span className="text-xs text-muted-foreground">
                                            Due {item.dueAt}
                                        </span>
                                        {item.escalatedAt ? (
                                            <Badge variant="destructive">
                                                Overdue · escalated
                                            </Badge>
                                        ) : item.reminderSentAt ? (
                                            <Badge variant="outline">
                                                Reminder sent
                                            </Badge>
                                        ) : null}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex min-w-32 flex-col gap-2">
                                        <Progress
                                            value={item.progress}
                                            aria-label={`${item.reference} progress: ${item.progress}%`}
                                        />
                                        <span className="text-xs text-muted-foreground">
                                            {item.progress}% verified
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <FindingActions
                                        item={item}
                                        currentUserId={currentUserId}
                                        canVerify={canVerify}
                                        canManage={canManage}
                                        owners={owners.filter(
                                            (owner) =>
                                                !item.county ||
                                                owner.countyId ===
                                                    item.county.id,
                                        )}
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </section>
    );
}

type ActionMode = 'upload' | 'respond' | 'verify' | 'close' | 'plan' | null;

function FindingActions({
    item,
    currentUserId,
    canVerify,
    canManage,
    owners,
}: {
    item: EvaluationFindingItem;
    currentUserId: string;
    canVerify: boolean;
    canManage: boolean;
    owners: Option[];
}) {
    const [mode, setMode] = useState<ActionMode>(null);
    const pending = item.updates.find(
        (update) => update.status === 'pending_verification',
    );
    const isOwner = item.ownerId === currentUserId;
    const canRespond = isOwner && item.status === 'open';
    const content = {
        upload: [
            'Upload response evidence',
            'Add a clean scanned or digital record to this recommendation.',
        ],
        respond: [
            'Submit recommendation response',
            'Report increasing progress against retained evidence.',
        ],
        verify: [
            'Verify recommendation response',
            'Independently accept or reject the submitted evidence and progress.',
        ],
        close: [
            'Close evaluation finding',
            'Record the independent closure decision after verified 100% progress.',
        ],
        plan: [
            'Recommendation action plan',
            'Coordinate weighted actions, owners, deadlines, evidence and independent verification.',
        ],
    } as const;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${item.reference}`}
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setMode('plan')}>
                            Manage action plan
                        </DropdownMenuItem>
                        {canRespond && (
                            <DropdownMenuItem
                                onSelect={() => setMode('upload')}
                            >
                                Upload evidence
                            </DropdownMenuItem>
                        )}
                        {canRespond &&
                            item.documents.length > 0 &&
                            !pending && (
                                <DropdownMenuItem
                                    onSelect={() => setMode('respond')}
                                >
                                    Submit response
                                </DropdownMenuItem>
                            )}
                        {canVerify && pending && (
                            <DropdownMenuItem
                                onSelect={() => setMode('verify')}
                            >
                                Verify response
                            </DropdownMenuItem>
                        )}
                        {canVerify &&
                            item.progress === 100 &&
                            item.status === 'open' && (
                                <DropdownMenuItem
                                    onSelect={() => setMode('close')}
                                >
                                    Close finding
                                </DropdownMenuItem>
                            )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet
                open={mode !== null}
                onOpenChange={(open) => !open && setMode(null)}
            >
                <SheetContent className="overflow-y-auto sm:max-w-3xl">
                    <SheetHeader>
                        <SheetTitle>
                            {mode ? content[mode][0] : 'Finding action'}
                        </SheetTitle>
                        <SheetDescription>
                            {mode ? content[mode][1] : 'Choose an action.'}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-8">
                        {mode === 'upload' && (
                            <Form
                                {...storeFindingDocument.form({
                                    finding: item.id,
                                })}
                                className="flex flex-col gap-4"
                                resetOnSuccess
                            >
                                <Field id="evidence-title" label="Record title">
                                    <Input
                                        id="evidence-title"
                                        name="title"
                                        required
                                    />
                                </Field>
                                <input
                                    type="hidden"
                                    name="category"
                                    value="Evaluation recommendation evidence"
                                />
                                <StaticSearchableSelect
                                    id="evidence-source"
                                    name="source_type"
                                    values={['digital', 'scanned']}
                                />
                                <Field id="evidence-file" label="Document">
                                    <Input
                                        id="evidence-file"
                                        name="document"
                                        type="file"
                                        required
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg"
                                    />
                                </Field>
                                <Button type="submit">Upload securely</Button>
                            </Form>
                        )}
                        {mode === 'plan' && (
                            <FindingActionPlan
                                finding={item}
                                owners={owners}
                                currentUserId={currentUserId}
                                canManage={canManage}
                                canVerify={canVerify}
                            />
                        )}
                        {mode === 'respond' && (
                            <Form
                                {...storeFindingUpdate.form({
                                    finding: item.id,
                                })}
                                className="flex flex-col gap-4"
                                resetOnSuccess
                            >
                                <SearchableSelect
                                    id="response-evidence"
                                    name="assessment_document_id"
                                    label="Retained evidence"
                                    options={item.documents
                                        .filter(
                                            (document) =>
                                                document.scanStatus === 'clean',
                                        )
                                        .map((document) => ({
                                            id: document.id,
                                            name: document.title,
                                        }))}
                                />
                                <Field
                                    id="response-progress"
                                    label="Verified progress requested (%)"
                                >
                                    <Input
                                        id="response-progress"
                                        name="progress_percentage"
                                        type="number"
                                        min={item.progress + 0.01}
                                        max="100"
                                        step="0.01"
                                        required
                                    />
                                </Field>
                                <Field
                                    id="response-narrative"
                                    label="Implementation narrative"
                                >
                                    <Textarea
                                        id="response-narrative"
                                        name="narrative"
                                        rows={5}
                                        required
                                    />
                                </Field>
                                <Button type="submit">
                                    Submit for verification
                                </Button>
                            </Form>
                        )}
                        {mode === 'verify' && pending && (
                            <Form
                                {...verify.form({ update: pending.id })}
                                className="flex flex-col gap-4"
                                resetOnSuccess
                            >
                                <StaticSearchableSelect
                                    id="response-decision"
                                    name="decision"
                                    values={['verified', 'rejected']}
                                />
                                <Field id="response-note" label="Decision note">
                                    <Textarea
                                        id="response-note"
                                        name="note"
                                        rows={5}
                                        required
                                    />
                                </Field>
                                <Button type="submit">Record decision</Button>
                            </Form>
                        )}
                        {mode === 'close' && (
                            <Form
                                {...close.form({ finding: item.id })}
                                className="flex flex-col gap-4"
                                resetOnSuccess
                            >
                                <Field
                                    id="closure-note"
                                    label="Closure decision"
                                >
                                    <Textarea
                                        id="closure-note"
                                        name="note"
                                        rows={5}
                                        required
                                    />
                                </Field>
                                <Button type="submit">Close finding</Button>
                            </Form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

type PlanMode = 'add' | 'upload' | 'progress' | 'verify' | null;

function FindingActionPlan({
    finding,
    owners,
    currentUserId,
    canManage,
    canVerify,
}: {
    finding: EvaluationFindingItem;
    owners: Option[];
    currentUserId: string;
    canManage: boolean;
    canVerify: boolean;
}) {
    const [mode, setMode] = useState<PlanMode>(null);
    const [selected, setSelected] =
        useState<EvaluationFindingActionItem | null>(null);
    const totalWeight = finding.actions.reduce(
        (sum, action) => sum + action.weight,
        0,
    );
    const canAdd =
        finding.status === 'open' &&
        totalWeight < 100 &&
        (canManage || finding.ownerId === currentUserId);
    const openAction = (
        action: EvaluationFindingActionItem,
        nextMode: PlanMode,
    ) => {
        setSelected(action);
        setMode(nextMode);
    };
    const pending = selected?.updates.find(
        (update) => update.status === 'pending_verification',
    );

    return (
        <div className="flex flex-col gap-5">
            <div className="rounded-lg border bg-muted/30 p-4">
                <div className="flex items-center justify-between gap-4 text-sm">
                    <span>Allocated action weight</span>
                    <strong>{totalWeight}% / 100%</strong>
                </div>
                <Progress
                    value={totalWeight}
                    className="mt-3"
                    aria-label={`${finding.reference} action weight: ${totalWeight}% of 100%`}
                />
                {totalWeight !== 100 && (
                    <p className="mt-2 text-xs text-muted-foreground">
                        Closure remains locked until action weights total
                        exactly 100% and every action is independently verified
                        complete.
                    </p>
                )}
            </div>
            {canAdd && (
                <Button
                    type="button"
                    variant="outline"
                    className="self-start"
                    onClick={() => {
                        setSelected(null);
                        setMode('add');
                    }}
                >
                    <Plus aria-hidden="true" /> Add action
                </Button>
            )}
            {finding.actions.length === 0 ? (
                <WorkspaceEmptyState
                    title="No recommendation actions"
                    description="Add separately owned and weighted actions to create the implementation plan."
                    className="min-h-40"
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Action</TableHead>
                                <TableHead>Owner / deadline</TableHead>
                                <TableHead>Weight / progress</TableHead>
                                <TableHead>
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {finding.actions.map((action) => {
                                const actionPending = action.updates.some(
                                    (update) =>
                                        update.status ===
                                        'pending_verification',
                                );
                                const isOwner =
                                    action.ownerId === currentUserId;

                                return (
                                    <TableRow key={action.id}>
                                        <TableCell>
                                            <div className="max-w-xs">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <strong>
                                                        {action.code}
                                                    </strong>
                                                    <Badge variant="secondary">
                                                        {action.status.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1">
                                                    {action.title}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {action.successIndicator} ·
                                                    Target: {action.target}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="text-sm">
                                                <p>{action.owner}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    Due {action.dueAt}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="min-w-28 text-sm">
                                                <p>{action.weight}% weight</p>
                                                <Progress
                                                    value={action.progress}
                                                    className="mt-2"
                                                    aria-label={`${action.code} verified progress: ${action.progress}%`}
                                                />
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {action.progress}% verified
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Actions for ${action.code}`}
                                                    >
                                                        <MoreHorizontal aria-hidden="true" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuGroup>
                                                        {isOwner &&
                                                            action.status !==
                                                                'completed' && (
                                                                <DropdownMenuItem
                                                                    onSelect={() =>
                                                                        openAction(
                                                                            action,
                                                                            'upload',
                                                                        )
                                                                    }
                                                                >
                                                                    Upload
                                                                    evidence
                                                                </DropdownMenuItem>
                                                            )}
                                                        {isOwner &&
                                                            action.documents.some(
                                                                (document) =>
                                                                    document.scanStatus ===
                                                                    'clean',
                                                            ) &&
                                                            !actionPending &&
                                                            action.status !==
                                                                'completed' && (
                                                                <DropdownMenuItem
                                                                    onSelect={() =>
                                                                        openAction(
                                                                            action,
                                                                            'progress',
                                                                        )
                                                                    }
                                                                >
                                                                    Submit
                                                                    progress
                                                                </DropdownMenuItem>
                                                            )}
                                                        {canVerify &&
                                                            actionPending && (
                                                                <DropdownMenuItem
                                                                    onSelect={() =>
                                                                        openAction(
                                                                            action,
                                                                            'verify',
                                                                        )
                                                                    }
                                                                >
                                                                    Verify
                                                                    progress
                                                                </DropdownMenuItem>
                                                            )}
                                                    </DropdownMenuGroup>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            )}
            {finding.actions.flatMap((action) => action.documents).length >
                0 && (
                <div>
                    <h3 className="mb-2 text-sm font-semibold">
                        Action evidence
                    </h3>
                    <ul className="flex flex-col gap-2">
                        {finding.actions
                            .flatMap((action) =>
                                action.documents.map((document) => ({
                                    action,
                                    document,
                                })),
                            )
                            .map(({ action, document }) => (
                                <li
                                    key={document.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3 text-sm"
                                >
                                    <span>
                                        <strong>{action.code}</strong> ·{' '}
                                        {document.title}{' '}
                                        <Badge variant="outline">
                                            {document.sourceType}
                                        </Badge>
                                    </span>
                                    <span className="flex gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a
                                                href={previewEvidence.url({
                                                    document: document.id,
                                                })}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Eye aria-hidden="true" />{' '}
                                                Preview
                                            </a>
                                        </Button>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a
                                                href={downloadEvidence.url({
                                                    document: document.id,
                                                })}
                                            >
                                                <Download aria-hidden="true" />{' '}
                                                Download
                                            </a>
                                        </Button>
                                    </span>
                                </li>
                            ))}
                    </ul>
                </div>
            )}
            {mode === 'add' && (
                <Form
                    {...storeFindingAction.form({ finding: finding.id })}
                    className="grid gap-4 rounded-lg border p-4"
                    resetOnSuccess
                >
                    {({ errors, processing }) => (
                        <>
                            <h3 className="font-semibold">
                                Add weighted action
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="action-code"
                                    label="Action code"
                                    error={errors.code}
                                >
                                    <Input
                                        id="action-code"
                                        name="code"
                                        required
                                    />
                                </Field>
                                <SearchableSelect
                                    id="action-owner"
                                    name="accountable_owner_id"
                                    label="Accountable owner"
                                    options={owners}
                                    error={errors.accountable_owner_id}
                                />
                                <Field
                                    id="action-title"
                                    label="Title"
                                    error={errors.title}
                                >
                                    <Input
                                        id="action-title"
                                        name="title"
                                        required
                                    />
                                </Field>
                                <Field
                                    id="action-weight"
                                    label="Weight (%)"
                                    error={errors.weight_percentage}
                                >
                                    <Input
                                        id="action-weight"
                                        name="weight_percentage"
                                        type="number"
                                        min="0.01"
                                        max={100 - totalWeight}
                                        step="0.01"
                                        required
                                    />
                                </Field>
                                <DatePickerField
                                    name="due_at"
                                    label="Deadline"
                                    required
                                    min={new Date().toISOString().slice(0, 10)}
                                    error={errors.due_at}
                                />
                                <Field
                                    id="action-indicator"
                                    label="Success indicator"
                                    error={errors.success_indicator}
                                >
                                    <Input
                                        id="action-indicator"
                                        name="success_indicator"
                                        required
                                    />
                                </Field>
                            </div>
                            <Field
                                id="action-description"
                                label="Action description"
                                error={errors.description}
                            >
                                <Textarea
                                    id="action-description"
                                    name="description"
                                    required
                                />
                            </Field>
                            <Field
                                id="action-target"
                                label="Target"
                                error={errors.target}
                            >
                                <Input
                                    id="action-target"
                                    name="target"
                                    required
                                />
                            </Field>
                            <Button type="submit" disabled={processing}>
                                Add action
                            </Button>
                        </>
                    )}
                </Form>
            )}
            {mode === 'upload' && selected && (
                <Form
                    {...storeActionDocument.form({ action: selected.id })}
                    className="grid gap-4 rounded-lg border p-4"
                    resetOnSuccess
                >
                    <h3 className="font-semibold">
                        Upload evidence for {selected.code}
                    </h3>
                    <Field id="action-evidence-title" label="Record title">
                        <Input
                            id="action-evidence-title"
                            name="title"
                            required
                        />
                    </Field>
                    <input
                        type="hidden"
                        name="category"
                        value="Evaluation recommendation action evidence"
                    />
                    <StaticSearchableSelect
                        id="action-evidence-source"
                        name="source_type"
                        values={['digital', 'scanned']}
                    />
                    <Field id="action-evidence-file" label="Document">
                        <Input
                            id="action-evidence-file"
                            name="document"
                            type="file"
                            required
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.tif,.tiff"
                        />
                    </Field>
                    <Button type="submit">Upload securely</Button>
                </Form>
            )}
            {mode === 'progress' && selected && (
                <Form
                    {...storeActionUpdate.form({ action: selected.id })}
                    className="grid gap-4 rounded-lg border p-4"
                    resetOnSuccess
                >
                    <h3 className="font-semibold">
                        Submit progress for {selected.code}
                    </h3>
                    <SearchableSelect
                        id="action-progress-evidence"
                        name="assessment_document_id"
                        label="Clean action evidence"
                        options={selected.documents
                            .filter(
                                (document) => document.scanStatus === 'clean',
                            )
                            .map((document) => ({
                                id: document.id,
                                name: document.title,
                            }))}
                    />
                    <Field id="action-progress" label="Progress (%)">
                        <Input
                            id="action-progress"
                            name="progress_percentage"
                            type="number"
                            min={selected.progress + 0.01}
                            max="100"
                            step="0.01"
                            required
                        />
                    </Field>
                    <Field
                        id="action-narrative"
                        label="Implementation narrative"
                    >
                        <Textarea
                            id="action-narrative"
                            name="narrative"
                            rows={4}
                            required
                        />
                    </Field>
                    <Button type="submit">Submit for verification</Button>
                </Form>
            )}
            {mode === 'verify' && selected && pending && (
                <Form
                    {...verifyActionUpdate.form({ update: pending.id })}
                    className="grid gap-4 rounded-lg border p-4"
                    resetOnSuccess
                >
                    <h3 className="font-semibold">
                        Verify {selected.code} progress
                    </h3>
                    <StaticSearchableSelect
                        id="action-decision"
                        name="decision"
                        values={['verified', 'rejected']}
                    />
                    <Field id="action-decision-note" label="Decision note">
                        <Textarea
                            id="action-decision-note"
                            name="note"
                            rows={4}
                            required
                        />
                    </Field>
                    <Button type="submit">Record decision</Button>
                </Form>
            )}
        </div>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {error && (
                <p role="alert" className="text-xs text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
