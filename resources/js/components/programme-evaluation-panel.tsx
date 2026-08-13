import { Form, usePage } from '@inertiajs/react';
import { ClipboardList, DownloadIcon } from 'lucide-react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import ProgrammeEvaluationDocumentControls from '@/components/programme-evaluation-document-controls';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { WorkspaceDocument } from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { store, transition } from '@/routes/monitoring-evaluation/evaluations';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string };
export type EvaluationItem = {
    id: string;
    code: string;
    title: string;
    type: string;
    status: string;
    programme: string | null;
    county: CountyIdentityValue | null;
    period: string;
    referenceRelease: string;
    referenceChecksum: string | null;
    documents: WorkspaceDocument[];
};

export default function ProgrammeEvaluationPanel({
    programmes,
    counties,
    evaluations,
    canManage,
    canApprove,
    filters,
}: {
    programmes: Option[];
    counties: Option[];
    evaluations: EvaluationItem[];
    canManage: boolean;
    canApprove: boolean;
    filters: Record<string, string | undefined>;
}) {
    const copy = usePage().props.localization.evaluationPanel;

    return (
        <section className="rounded-xl border border-border bg-card shadow-xs">
            <div className="flex items-start justify-between gap-4 border-b p-5 sm:p-6">
                <div className="flex items-start gap-3">
                    <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <ClipboardList aria-hidden="true" />
                    </span>
                    <div>
                        <h2 className="font-bold">{copy.title}</h2>
                        <p className="text-sm text-muted-foreground">
                            {copy.description}
                        </p>
                    </div>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline">
                            <DownloadIcon data-icon="inline-start" />
                            {copy.export}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>
                            {copy.register}
                        </DropdownMenuLabel>
                        <DropdownMenuGroup>
                            {['csv', 'xlsx', 'pdf', 'json'].map((format) => (
                                <DropdownMenuItem key={format} asChild>
                                    <a
                                        href={exportMethod.url(
                                            {
                                                workspace:
                                                    'programme-evaluations',
                                                format,
                                            },
                                            { query: filters },
                                        )}
                                    >
                                        {format.toUpperCase()}
                                    </a>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            {canManage && (
                <div className="border-b p-5 sm:p-6">
                    <FormSheet
                        title={copy.register_evaluation}
                        triggerLabel={copy.register_evaluation}
                        icon={ClipboardList}
                        size="xl"
                        description={copy.register_description}
                    >
                        <Form
                            {...store.form({})}
                            className="grid gap-4 md:grid-cols-2"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field label={copy.code} error={errors.code}>
                                        <Input
                                            name="code"
                                            required
                                            placeholder="EVAL-2026-01"
                                        />
                                    </Field>
                                    <Field label={copy.evaluation} error={errors.title}>
                                        <Input name="title" required />
                                    </Field>
                                    <Field
                                        label={copy.type}
                                        error={errors.evaluation_type}
                                    >
                                        <SearchableSelect
                                            id="evaluation-type"
                                            name="evaluation_type"
                                            label=""
                                            options={[
                                                'baseline',
                                                'midline',
                                                'endline',
                                                'process',
                                                'impact',
                                            ].map((value) => ({
                                                id: value,
                                                name: copy[value],
                                            }))}
                                        />
                                    </Field>
                                    <Field
                                        label={copy.programme}
                                        error={errors.programme_id}
                                    >
                                        <Options
                                            name="programme_id"
                                            options={programmes}
                                            optional
                                        />
                                    </Field>
                                    <Field
                                        label={copy.county}
                                        error={errors.county_id}
                                    >
                                        <Options
                                            name="county_id"
                                            options={counties}
                                            optional
                                        />
                                    </Field>
                                    <DatePickerField
                                        name="period_start"
                                        label={copy.period_start}
                                        error={errors.period_start}
                                        required
                                    />
                                    <DatePickerField
                                        name="period_end"
                                        label={copy.period_end}
                                        error={errors.period_end}
                                        required
                                    />
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label>{copy.terms_of_reference}</Label>
                                        <textarea
                                            name="terms_of_reference"
                                            required
                                            className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        />
                                        {errors.terms_of_reference && (
                                            <p className="text-xs text-destructive">
                                                {errors.terms_of_reference}
                                            </p>
                                        )}
                                    </div>
                                    <div className="md:col-span-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {copy.register_evaluation}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </FormSheet>
                </div>
            )}
            {evaluations.length === 0 ? (
                <WorkspaceEmptyState
                    title={copy.empty_title}
                    description={copy.empty_description}
                    className="min-h-56 border-0"
                />
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{copy.code}</TableHead>
                            <TableHead>{copy.evaluation}</TableHead>
                            <TableHead>{copy.type}</TableHead>
                            <TableHead>{copy.scope}</TableHead>
                            <TableHead>{copy.period}</TableHead>
                            <TableHead>{copy.reference_release}</TableHead>
                            <TableHead>{copy.status}</TableHead>
                            <TableHead className="text-right">
                                {copy.actions}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {evaluations.map((item) => (
                            <TableRow key={item.id}>
                                <TableCell className="font-mono text-xs">
                                    {item.code}
                                </TableCell>
                                <TableCell>{item.title}</TableCell>
                                <TableCell>{copy[item.type] ?? item.type}</TableCell>
                                <TableCell>
                                    <div className="flex flex-col gap-1.5">
                                        {item.county ? (
                                            <CountyIdentity
                                                county={item.county}
                                                compact
                                            />
                                        ) : (
                                            <span>{copy.national}</span>
                                        )}
                                        {item.programme && (
                                            <span className="text-xs text-muted-foreground">
                                                {item.programme}
                                            </span>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>{item.period}</TableCell>
                                <TableCell>
                                    <div className="flex flex-col gap-1">
                                        <span>{item.referenceRelease}</span>
                                        {item.referenceChecksum && (
                                            <span
                                                className="max-w-32 truncate font-mono text-xs text-muted-foreground"
                                                title={item.referenceChecksum}
                                            >
                                                {item.referenceChecksum}
                                            </span>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">
                                        {copy[item.status] ?? item.status}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap justify-end gap-2">
                                        <ProgrammeEvaluationDocumentControls
                                            evaluationId={item.id}
                                            status={item.status}
                                            documents={item.documents}
                                            canUpload={
                                                canManage &&
                                                [
                                                    'planned',
                                                    'in_progress',
                                                ].includes(item.status)
                                            }
                                        />
                                        {item.status === 'planned' &&
                                            canManage && (
                                                <EvaluationTransition
                                                    evaluationId={item.id}
                                                    name="start"
                                                    label={copy.start_evaluation}
                                                    disabled={
                                                        !hasCleanDocument(
                                                            item,
                                                            'programme-evaluation-tor',
                                                        )
                                                    }
                                                    disabledReason={copy.tor_required}
                                                />
                                            )}
                                        {item.status === 'in_progress' &&
                                            canManage && (
                                                <EvaluationTransition
                                                    evaluationId={item.id}
                                                    name="submit_review"
                                                    label={copy.submit_for_review}
                                                    disabled={
                                                        !hasCleanDocument(
                                                            item,
                                                            'programme-evaluation-report',
                                                        )
                                                    }
                                                    disabledReason={copy.report_required}
                                                />
                                            )}
                                        {item.status === 'review' &&
                                            canApprove && (
                                                <>
                                                    <EvaluationTransition
                                                        evaluationId={item.id}
                                                        name="approve"
                                                        label={copy.approve}
                                                    />
                                                    <EvaluationTransition
                                                        evaluationId={item.id}
                                                        name="return"
                                                        label={copy.return}
                                                    />
                                                </>
                                            )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </section>
    );
}

function EvaluationTransition({
    evaluationId,
    name,
    label,
    disabled = false,
    disabledReason,
}: {
    evaluationId: string;
    name: string;
    label: string;
    disabled?: boolean;
    disabledReason?: string;
}) {
    const copy = usePage().props.localization.evaluationPanel;

    return (
        <FormSheet
            title={label}
            triggerLabel={label}
            description={
                disabled
                    ? (disabledReason ?? copy.transition_unavailable)
                    : `${copy.decision_basis} ${label.toLocaleLowerCase()}.`
            }
            triggerDisabled={disabled}
            triggerTitle={disabledReason}
            size="md"
        >
            <Form
                {...transition.form({ evaluation: evaluationId })}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="transition" value={name} />
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`evaluation-comment-${evaluationId}-${name}`}
                            >
                                {copy.decision_comment}
                            </Label>
                            <textarea
                                id={`evaluation-comment-${evaluationId}-${name}`}
                                name="comment"
                                required
                                minLength={10}
                                className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                aria-invalid={Boolean(errors.comment)}
                                aria-describedby={
                                    errors.comment
                                        ? `evaluation-comment-error-${evaluationId}-${name}`
                                        : undefined
                                }
                            />
                            {errors.comment && (
                                <p
                                    id={`evaluation-comment-error-${evaluationId}-${name}`}
                                    className="text-sm text-destructive"
                                >
                                    {errors.comment}
                                </p>
                            )}
                        </div>
                        <Button type="submit" disabled={processing || disabled}>
                            {label}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function hasCleanDocument(item: EvaluationItem, purpose: string): boolean {
    return item.documents.some(
        (document) =>
            document.purpose === purpose && document.scanStatus === 'clean',
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
function Options({
    name,
    options,
    optional,
}: {
    name: string;
    options: Option[];
    optional?: boolean;
}) {
    return (
        <SearchableSelect
            id={`evaluation-${name}`}
            name={name}
            label=""
            options={options}
            optional={optional}
        />
    );
}
