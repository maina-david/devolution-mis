import { useHttp, usePage } from '@inertiajs/react';
import { FlaskConical, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DatePickerField from '@/components/date-picker-field';
import SearchableSelect from '@/components/searchable-select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/reference-catalog';
import { simulate } from '@/routes/workflows/versions';

type UserOption = { id: string; name: string; email: string; roles: string[] };
type Transition = { name: string; from: string; to: string };
type ContextValue = string | number | boolean | null;
type Step = {
    transition_name: string;
    actor_id: string;
    context_changes: Record<string, ContextValue>;
    occurred_at?: string | null;
};
type Simulation = {
    passed: boolean;
    completed: boolean;
    initialState: string;
    finalState: string;
    message: string;
    scenarioChecksum: string;
    version: { checksum: string };
    steps: Array<{
        index: number;
        transitionName: string;
        fromState: string;
        toState: string | null;
        actor: { name: string } | null;
        status: string;
        message: string;
        authorized: boolean;
        separationPassed: boolean;
        terminal: boolean;
        dueAt: string | null;
        ruleEvaluation: { results: Array<{ field: string; passed: boolean }> };
    }>;
};

export default function WorkflowSimulatorSheet({
    workflowId,
    workflowName,
    version,
    users,
}: {
    workflowId: string;
    workflowName: string;
    version: {
        id: string;
        version: number;
        configuration: { transitions: Transition[] };
    };
    users: UserOption[];
}) {
    const copy = usePage().props.localization.workflowSimulator;
    const [startedAt, setStartedAt] = useState(
        new Date().toISOString().slice(0, 16),
    );
    const [startedBy, setStartedBy] = useState('');
    const [initialContext, setInitialContext] = useState('{}');
    const [steps, setSteps] = useState<Array<Step & { contextJson: string }>>(
        [],
    );
    const http = useHttp<
        {
            started_at: string;
            started_by: string;
            initial_context: Record<string, ContextValue>;
            steps: Step[];
        },
        { simulation: Simulation }
    >({
        started_at: startedAt,
        started_by: startedBy,
        initial_context: {},
        steps: [],
    });

    function updateStep(
        index: number,
        values: Partial<(typeof steps)[number]>,
    ) {
        setSteps((current) =>
            current.map((step, stepIndex) =>
                stepIndex === index ? { ...step, ...values } : step,
            ),
        );
    }

    async function runSimulation() {
        try {
            const payload = {
                started_at: startedAt,
                started_by: startedBy,
                initial_context: JSON.parse(initialContext) as Record<
                    string,
                    ContextValue
                >,
                steps: steps.map(({ contextJson, ...step }) => ({
                    ...step,
                    context_changes: JSON.parse(contextJson) as Record<
                        string,
                        ContextValue
                    >,
                })),
            };
            http.clearErrors();
            http.transform(() => payload);
            await http.post(
                simulate({
                    workflowDefinition: workflowId,
                    workflowVersion: version.id,
                }).url,
            );
        } catch {
            http.setError('initial_context', copy.invalid_json);
        }
    }

    const result = http.response?.simulation;

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline" size="sm">
                    <FlaskConical /> {copy.simulate} {'v'}
                    {version.version}
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full overflow-y-auto sm:max-w-4xl">
                <SheetHeader>
                    <SheetTitle>{copy.title}</SheetTitle>
                    <SheetDescription>
                        {interpolate(copy.description, {
                            workflow: workflowName,
                            version: String(version.version),
                        })}
                    </SheetDescription>
                </SheetHeader>
                <div className="grid gap-5 px-4 pb-8">
                    <div className="grid gap-4 md:grid-cols-2">
                        <DatePickerField
                            name="started_at"
                            label={copy.scenario_start}
                            includeTime
                            required
                            defaultValue={startedAt}
                            onValueChange={setStartedAt}
                        />
                        <SearchableSelect
                            id={`simulation-starter-${version.id}`}
                            name="started_by"
                            label={copy.starter_identity}
                            value={startedBy}
                            onValueChange={setStartedBy}
                            options={users.map((user) => ({
                                id: user.id,
                                name: `${user.name} · ${user.roles.join(', ') || copy.no_role}`,
                            }))}
                            error={http.errors.started_by as string | undefined}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`simulation-context-${version.id}`}>
                            {copy.initial_context}
                        </Label>
                        <Textarea
                            id={`simulation-context-${version.id}`}
                            className="min-h-28 font-mono text-xs"
                            value={initialContext}
                            onChange={(event) =>
                                setInitialContext(event.target.value)
                            }
                            aria-invalid={Boolean(http.errors.initial_context)}
                        />
                        {http.errors.initial_context && (
                            <p className="text-xs text-destructive">
                                {String(http.errors.initial_context)}
                            </p>
                        )}
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h3 className="font-semibold">
                                {copy.scenario_steps}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {copy.scenario_steps_description}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setSteps((current) => [
                                    ...current,
                                    {
                                        transition_name: '',
                                        actor_id: '',
                                        context_changes: {},
                                        contextJson: '{}',
                                        occurred_at: null,
                                    },
                                ])
                            }
                        >
                            <Plus /> {copy.add_step}
                        </Button>
                    </div>
                    {steps.map((step, index) => (
                        <div
                            key={index}
                            className="grid gap-4 rounded-xl border p-4"
                        >
                            <div className="flex items-center justify-between">
                                <Badge variant="outline">
                                    {copy.step} {index + 1}
                                </Badge>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={interpolate(copy.remove_step, {
                                        number: String(index + 1),
                                    })}
                                    onClick={() =>
                                        setSteps((current) =>
                                            current.filter(
                                                (_, itemIndex) =>
                                                    itemIndex !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <SearchableSelect
                                    id={`simulation-transition-${version.id}-${index}`}
                                    name={`steps[${index}][transition_name]`}
                                    label={copy.transition}
                                    value={step.transition_name}
                                    onValueChange={(value) =>
                                        updateStep(index, {
                                            transition_name: value,
                                        })
                                    }
                                    options={version.configuration.transitions.map(
                                        (transition) => ({
                                            id: transition.name,
                                            name: `${transition.name} · ${transition.from} → ${transition.to}`,
                                        }),
                                    )}
                                />
                                <SearchableSelect
                                    id={`simulation-actor-${version.id}-${index}`}
                                    name={`steps[${index}][actor_id]`}
                                    label={copy.actor_identity}
                                    value={step.actor_id}
                                    onValueChange={(value) =>
                                        updateStep(index, { actor_id: value })
                                    }
                                    options={users.map((user) => ({
                                        id: user.id,
                                        name: `${user.name} · ${user.roles.join(', ') || copy.no_role}`,
                                    }))}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`simulation-step-context-${version.id}-${index}`}
                                >
                                    {copy.context_changes}
                                </Label>
                                <Textarea
                                    id={`simulation-step-context-${version.id}-${index}`}
                                    className="font-mono text-xs"
                                    value={step.contextJson}
                                    onChange={(event) =>
                                        updateStep(index, {
                                            contextJson: event.target.value,
                                        })
                                    }
                                />
                            </div>
                        </div>
                    ))}
                    <Button
                        type="button"
                        onClick={runSimulation}
                        disabled={http.processing || !startedBy}
                    >
                        <FlaskConical />{' '}
                        {http.processing
                            ? copy.running_controls
                            : copy.run_simulation}
                    </Button>
                    {result && (
                        <div className="grid gap-4 border-t pt-5">
                            <Alert
                                variant={
                                    result.passed ? 'default' : 'destructive'
                                }
                            >
                                <AlertTitle>
                                    {result.passed
                                        ? copy.control_path_passed
                                        : copy.control_path_failed}
                                </AlertTitle>
                                <AlertDescription>
                                    {result.message}
                                </AlertDescription>
                            </Alert>
                            <div className="flex flex-wrap gap-2">
                                <Badge>
                                    {result.initialState} {'→'}{' '}
                                    {result.finalState}
                                </Badge>
                                <Badge variant="outline">
                                    {result.completed
                                        ? copy.terminal_reached
                                        : copy.remains_active}
                                </Badge>
                            </div>
                            {result.steps.map((step) => (
                                <div
                                    key={step.index}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-semibold">
                                            {step.index}
                                            {'.'} {step.transitionName}
                                        </p>
                                        <Badge
                                            variant={
                                                step.status === 'passed'
                                                    ? 'default'
                                                    : 'destructive'
                                            }
                                        >
                                            {copy[step.status] ?? step.status}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-sm">
                                        {step.fromState} {'→ '}
                                        {step.toState ?? copy.blocked} {'· '}
                                        {step.actor?.name ?? copy.not_evaluated}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {step.message}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {copy.permission}{' '}
                                        {step.authorized
                                            ? copy.passed
                                            : copy.failed}{' '}
                                        {'·'} {copy.separation}{' '}
                                        {step.separationPassed
                                            ? copy.passed
                                            : copy.failed}{' '}
                                        {'·'} {copy.rules}{' '}
                                        {step.ruleEvaluation.results.length
                                            ? `${step.ruleEvaluation.results.filter((rule) => rule.passed).length}/${step.ruleEvaluation.results.length}`
                                            : copy.not_configured}
                                        {step.dueAt
                                            ? ` · ${copy.due} ${formatDateTime(step.dueAt)}`
                                            : ''}
                                    </p>
                                </div>
                            ))}
                            <p className="font-mono text-xs break-all text-muted-foreground">
                                {copy.scenario_checksum}{' '}
                                {result.scenarioChecksum}
                                <br />
                                {copy.version_checksum}{' '}
                                {result.version.checksum}
                            </p>
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
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
