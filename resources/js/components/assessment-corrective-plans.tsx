import { Form } from '@inertiajs/react';
import { ClipboardCheck, FileUp, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { review, store } from '@/routes/assessments/corrective-plans';
import {
    store as storeUpdate,
    verify as verifyUpdate,
} from '@/routes/assessments/corrective-plans/actions/updates';
import {
    decide as decideClosure,
    store as requestClosure,
} from '@/routes/assessments/corrective-plans/closure';

type Option = { value: string; label: string };
type Update = {
    id: string;
    progress: number;
    narrative: string;
    status: string;
    decisionNote: string | null;
    checksum: string;
    document: { id: string; title: string };
    submittedBy: string;
    verifiedBy: string | null;
};
type Action = {
    id: string;
    code: string;
    title: string;
    description: string;
    successIndicator: string;
    target: string;
    dueAt: string;
    progress: number;
    status: string;
    owner: string;
    updates: Update[];
};
type Plan = {
    id: string;
    reference: string;
    title: string;
    rootCause: string;
    expectedOutcome: string;
    status: string;
    dueAt: string;
    checksum: string;
    source: { type: string; id: string; label: string };
    submittedBy: string;
    reviewedBy: string | null;
    reviewNote: string | null;
    closedBy: string | null;
    closureDecision: string | null;
    actions: Action[];
};

export default function AssessmentCorrectivePlans({
    teamSlug,
    assessmentId,
    plans,
    options,
    capabilities,
}: {
    teamSlug: string;
    assessmentId: string;
    plans: Plan[];
    options: { sources: Option[]; evidence: Option[]; owners: Option[] };
    capabilities: Record<string, boolean>;
}) {
    const base = { current_team: teamSlug, assessment: assessmentId };

    return (
        <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle>Corrective action register</CardTitle>
                    <CardDescription>
                        Evidence-backed remediation, independent verification
                        and controlled closure.
                    </CardDescription>
                </div>
                {capabilities.submit &&
                    options.sources.length > 0 &&
                    options.owners.length > 0 && (
                        <CreatePlanSheet base={base} options={options} />
                    )}
            </CardHeader>
            <CardContent className="grid gap-4">
                {plans.length === 0 && (
                    <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No corrective plan is required or has been raised for
                        this published assessment.
                    </div>
                )}
                {plans.map((plan) => (
                    <PlanCard
                        key={plan.id}
                        base={base}
                        plan={plan}
                        evidence={options.evidence}
                        capabilities={capabilities}
                    />
                ))}
            </CardContent>
        </Card>
    );
}

function CreatePlanSheet({
    base,
    options,
}: {
    base: { current_team: string; assessment: string };
    options: { sources: Option[]; owners: Option[] };
}) {
    const [source, setSource] = useState('');
    const [sourceType, sourceId] = source.split(':');

    return (
        <FormSheet
            title="Create corrective plan"
            description="Link a major finding or accepted appeal to a time-bound, accountable action."
            triggerLabel="Create plan"
            icon={ClipboardCheck}
            size="xl"
        >
            <Form
                {...store.form(base)}
                resetOnSuccess
                className="grid gap-4 md:grid-cols-2"
            >
                {({ processing, errors }) => (
                    <>
                        <SearchableSelect
                            id="corrective-source"
                            name="source_selector"
                            label="Finding or appeal"
                            value={source}
                            onValueChange={setSource}
                            options={options.sources.map((option) => ({
                                id: option.value,
                                name: option.label,
                            }))}
                        />
                        <input
                            type="hidden"
                            name="assessment_finding_id"
                            value={sourceType === 'finding' ? sourceId : ''}
                        />
                        <input
                            type="hidden"
                            name="assessment_appeal_id"
                            value={sourceType === 'appeal' ? sourceId : ''}
                        />
                        <Field label="Reference" error={errors.reference}>
                            <Input
                                name="reference"
                                placeholder="CAP-2026-001"
                                required
                            />
                        </Field>
                        <Field label="Plan title" error={errors.title}>
                            <Input name="title" required />
                        </Field>
                        <TextField
                            name="root_cause"
                            label="Root cause"
                            error={errors.root_cause}
                        />
                        <TextField
                            name="expected_outcome"
                            label="Expected outcome"
                            error={errors.expected_outcome}
                        />
                        <DatePickerField
                            name="due_at"
                            label="Plan due date"
                            error={errors.due_at}
                            min={new Date().toISOString().slice(0, 10)}
                            required
                        />
                        <div className="rounded-lg border p-4 md:col-span-2">
                            <h3 className="mb-4 font-medium">
                                First accountable action
                            </h3>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    label="Action code"
                                    error={errors['actions.0.code']}
                                >
                                    <Input
                                        name="actions[0][code]"
                                        placeholder="ACT-01"
                                        required
                                    />
                                </Field>
                                <Field
                                    label="Action title"
                                    error={errors['actions.0.title']}
                                >
                                    <Input name="actions[0][title]" required />
                                </Field>
                                <SearchableSelect
                                    id="corrective-owner"
                                    name="actions[0][accountable_owner_id]"
                                    label="Accountable owner"
                                    options={options.owners.map((option) => ({
                                        id: option.value,
                                        name: option.label,
                                    }))}
                                />
                                <DatePickerField
                                    name="actions[0][due_at]"
                                    label="Action due date"
                                    error={errors['actions.0.due_at']}
                                    min={new Date().toISOString().slice(0, 10)}
                                    required
                                />
                                <TextField
                                    name="actions[0][description]"
                                    label="Action description"
                                    error={errors['actions.0.description']}
                                />
                                <TextField
                                    name="actions[0][success_indicator]"
                                    label="Success indicator"
                                    error={
                                        errors['actions.0.success_indicator']
                                    }
                                />
                                <Field
                                    label="Target"
                                    error={errors['actions.0.target']}
                                >
                                    <Input name="actions[0][target]" required />
                                </Field>
                            </div>
                        </div>
                        <Button className="md:col-span-2" disabled={processing}>
                            Submit for independent review
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function PlanCard({
    base,
    plan,
    evidence,
    capabilities,
}: {
    base: { current_team: string; assessment: string };
    plan: Plan;
    evidence: Option[];
    capabilities: Record<string, boolean>;
}) {
    const args = { ...base, plan: plan.id };
    const complete =
        plan.actions.length > 0 &&
        plan.actions.every((action) => action.status === 'completed');

    return (
        <section
            className="rounded-xl border p-4"
            aria-labelledby={`plan-${plan.id}`}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 id={`plan-${plan.id}`} className="font-semibold">
                        {plan.reference} · {plan.title}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {plan.source.label} · due {plan.dueAt} · submitted by{' '}
                        {plan.submittedBy}
                    </p>
                </div>
                <Badge variant="outline">
                    {plan.status.replaceAll('_', ' ')}
                </Badge>
            </div>
            <div className="mt-4 grid gap-3">
                {plan.actions.map((action) => (
                    <div key={action.id} className="rounded-lg bg-muted/40 p-4">
                        <div className="flex flex-wrap justify-between gap-3">
                            <div>
                                <p className="font-medium">
                                    {action.code} · {action.title}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Owner: {action.owner} · target:{' '}
                                    {action.target} · due {action.dueAt}
                                </p>
                            </div>
                            <Badge>{action.progress}%</Badge>
                        </div>
                        <Progress value={action.progress} className="mt-3" />
                        <p className="mt-3 text-sm">{action.description}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {capabilities.submit &&
                                plan.status === 'active' &&
                                action.status !== 'completed' &&
                                evidence.length > 0 && (
                                    <UpdateSheet
                                        base={args}
                                        action={action}
                                        evidence={evidence}
                                    />
                                )}
                            {action.updates
                                .filter(
                                    (update) =>
                                        update.status ===
                                        'pending_verification',
                                )
                                .map(
                                    (update) =>
                                        capabilities.review && (
                                            <VerifySheet
                                                key={update.id}
                                                base={args}
                                                action={action}
                                                update={update}
                                            />
                                        ),
                                )}
                        </div>
                        {action.updates.map((update) => (
                            <p
                                key={update.id}
                                className="mt-2 text-xs text-muted-foreground"
                            >
                                {update.progress}% · {update.document.title} ·{' '}
                                {update.status.replaceAll('_', ' ')} ·{' '}
                                {update.submittedBy}
                            </p>
                        ))}
                    </div>
                ))}
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
                {capabilities.review &&
                    ['submitted', 'returned'].includes(plan.status) && (
                        <DecisionSheet
                            title="Review corrective plan"
                            trigger="Review plan"
                            form={review.form(args)}
                            decisions={['activate', 'return']}
                            noteName="review_note"
                        />
                    )}
                {capabilities.submit &&
                    plan.status === 'active' &&
                    complete && (
                        <Form {...requestClosure.form(args)}>
                            <Button variant="outline">Request closure</Button>
                        </Form>
                    )}
                {capabilities.approve &&
                    plan.status === 'closure_requested' && (
                        <DecisionSheet
                            title="Decide closure"
                            trigger="Decide closure"
                            form={decideClosure.form(args)}
                            decisions={['closed', 'returned']}
                            noteName="decision_reason"
                        />
                    )}
            </div>
        </section>
    );
}

function UpdateSheet({
    base,
    action,
    evidence,
}: {
    base: { current_team: string; assessment: string; plan: string };
    action: Action;
    evidence: Option[];
}) {
    return (
        <FormSheet
            title={`Update ${action.code}`}
            description="Submit verified repository evidence for independent progress review."
            triggerLabel="Submit progress"
            icon={FileUp}
        >
            <Form
                {...storeUpdate.form({ ...base, correctiveAction: action.id })}
                resetOnSuccess
                className="grid gap-4"
            >
                <SearchableSelect
                    id={`evidence-${action.id}`}
                    name="assessment_document_id"
                    label="Verified evidence"
                    options={evidence.map((option) => ({
                        id: option.value,
                        name: option.label,
                    }))}
                />
                <Field label="Progress percentage">
                    <Input
                        name="progress_percentage"
                        type="number"
                        min={action.progress + 0.01}
                        max="100"
                        step="0.01"
                        required
                    />
                </Field>
                <TextField name="narrative" label="Progress narrative" />
                <Button>Submit for verification</Button>
            </Form>
        </FormSheet>
    );
}

function VerifySheet({
    base,
    action,
    update,
}: {
    base: { current_team: string; assessment: string; plan: string };
    action: Action;
    update: Update;
}) {
    return (
        <DecisionSheet
            title={`Verify ${action.code} progress`}
            trigger={`Verify ${update.progress}% update`}
            form={verifyUpdate.form({
                ...base,
                correctiveAction: action.id,
                update: update.id,
            })}
            decisions={['verified', 'rejected']}
            noteName="decision_note"
        />
    );
}

function DecisionSheet({
    title,
    trigger,
    form,
    decisions,
    noteName,
}: {
    title: string;
    trigger: string;
    form: { action: string; method: 'post' };
    decisions: string[];
    noteName: string;
}) {
    return (
        <FormSheet
            title={title}
            description="Record a reasoned, auditable decision."
            triggerLabel={trigger}
            icon={ShieldCheck}
        >
            <Form {...form} resetOnSuccess className="grid gap-4">
                <div className="grid gap-2">
                    <Label>Decision</Label>
                    <StaticSearchableSelect
                        id={`${noteName}-decision`}
                        name="decision"
                        values={decisions}
                    />
                </div>
                <TextField name={noteName} label="Decision note" />
                <Button>Record decision</Button>
            </Form>
        </FormSheet>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
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
        <Field label={label} error={error}>
            <textarea
                name={name}
                required
                minLength={20}
                className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
        </Field>
    );
}
