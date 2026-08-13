import { Form, Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    GitBranch,
    MoreHorizontal,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
import SearchableMultiSelect from '@/components/searchable-multi-select';
import SearchableSelect from '@/components/searchable-select';
import TimePickerField from '@/components/time-picker-field';
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
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import {
    publish as publishCalendar,
    store as storeCalendar,
} from '@/routes/business-calendars';
import {
    destroy as destroyHoliday,
    store as storeHoliday,
} from '@/routes/business-calendars/holidays';
import { index, store } from '@/routes/workflows';
import {
    publish,
    store as storeVersion,
    update,
} from '@/routes/workflows/versions';
import { exportMethod } from '@/routes/workspace';
import WorkflowSimulatorSheet from './workflow-simulator-sheet';

type WorkflowConfiguration = {
    initial_state: string;
    states: string[];
    transitions: Array<{
        name: string;
        from: string;
        to: string;
        permission?: string;
        rules?: Array<Record<string, string | number | boolean | null>>;
        separation_from?: string[];
        sla_hours?: number;
        terminal?: boolean;
    }>;
    rules: Array<Record<string, string | number | boolean | null>>;
    state_slas?: Record<string, number>;
    terminal_states?: string[];
    start_permission?: string;
    escalation_user_id?: string;
    escalation_permission?: string;
    business_calendar_id?: string;
};

type WorkflowVersion = {
    id: string;
    version: number;
    status: 'draft' | 'published' | 'retired';
    configuration: WorkflowConfiguration;
    checksum: string | null;
    effectiveFrom: string | null;
    effectiveTo: string | null;
    publishedBy: string | null;
    publishedAt: string | null;
};

type Workflow = {
    id: string;
    code: string;
    name: string;
    module: string;
    description: string | null;
    status: string;
    activeInstances: number;
    overdueInstances: number;
    versions: WorkflowVersion[];
};

type BusinessCalendar = {
    id: string;
    code: string;
    version: number;
    name: string;
    timezone: string;
    workingDays: number[];
    workdayStartsAt: string;
    workdayEndsAt: string;
    effectiveFrom: string;
    effectiveTo: string | null;
    status: string;
    creator: string;
    publisher: string | null;
    publishedAt: string | null;
    checksum: string | null;
    holidays: Array<{
        id: string;
        date: string;
        name: string;
        category: string;
        sourceReference: string;
        creator: string;
    }>;
};

type Props = {
    calendars: BusinessCalendar[];
    filters: { search?: string };
    users: Array<{
        id: string;
        name: string;
        email: string;
        roles: string[];
    }>;
    workflows: {
        data: Workflow[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

const modules = [
    'citizen-feedback',
    'e-learning',
    'partner-coordination',
    'dswg',
    'project-management',
    'departmental-performance',
    'monitoring-evaluation',
    'grievance-redress',
    'data-repository',
    'analytics',
    'intergovernmental-relations',
    'performance-assessment',
    'travel-clearance',
    'knowledge-management',
];

const starterConfiguration: WorkflowConfiguration = {
    initial_state: 'draft',
    states: ['draft', 'submitted', 'approved'],
    transitions: [
        { name: 'submit', from: 'draft', to: 'submitted', sla_hours: 48 },
        { name: 'approve', from: 'submitted', to: 'approved', terminal: true },
    ],
    rules: [],
    state_slas: { draft: 24, submitted: 48 },
    terminal_states: ['approved'],
    escalation_permission: 'workflows:manage',
};

function useWorkflowCopy(): Record<string, string> {
    return usePage().props.localization.workflowManagement;
}

function DraftEditor({
    workflow,
    version,
    calendars,
}: {
    workflow: Workflow;
    version: WorkflowVersion;
    calendars: BusinessCalendar[];
}) {
    const copy = useWorkflowCopy();
    const form = useForm<{
        configuration: WorkflowConfiguration;
        effective_from: string;
        effective_to: string;
    }>({
        configuration: version.configuration,
        effective_from: version.effectiveFrom ?? '',
        effective_to: version.effectiveTo ?? '',
    });
    const [configurationJson, setConfigurationJson] = useState(() =>
        JSON.stringify(version.configuration, null, 2),
    );
    const [selectedCalendarId, setSelectedCalendarId] = useState(
        version.configuration.business_calendar_id ?? '',
    );

    function selectCalendar(calendarId: string) {
        try {
            const configuration = JSON.parse(
                configurationJson,
            ) as WorkflowConfiguration;

            if (calendarId) {
                configuration.business_calendar_id = calendarId;
            } else {
                delete configuration.business_calendar_id;
            }

            setSelectedCalendarId(calendarId);
            setConfigurationJson(JSON.stringify(configuration, null, 2));
            form.clearErrors('configuration');
        } catch {
            form.setError('configuration', copy.correct_configuration_json);
        }
    }

    function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        try {
            const configuration = JSON.parse(
                configurationJson,
            ) as WorkflowConfiguration;
            form.clearErrors('configuration');
            form.transform((data) => ({ ...data, configuration }));
        } catch {
            form.setError('configuration', copy.configuration_valid_json);

            return;
        }

        form.patch(update.url([workflow.id, version.id]), {
            preserveScroll: true,
        });
    }

    return (
        <FormSheet
            title={`${copy.edit} ${workflow.name} ${copy.version} ${version.version}`}
            description={copy.edit_description}
            triggerLabel={`${copy.edit_draft} ${copy.version_prefix}${version.version}`}
            icon={GitBranch}
            size="xl"
        >
            <form onSubmit={save} className="grid gap-4 pt-4">
                <Textarea
                    value={configurationJson}
                    onChange={(event) =>
                        setConfigurationJson(event.target.value)
                    }
                    className="min-h-96 font-mono text-xs"
                    aria-label={`${copy.workflow} ${workflow.name} ${copy.configuration}`}
                />
                <SearchableSelect
                    id={`workflow-calendar-${version.id}`}
                    name="business_calendar_selector"
                    label={copy.published_sla_calendar}
                    options={calendars
                        .filter((calendar) => calendar.status === 'published')
                        .map((calendar) => ({
                            id: calendar.id,
                            name: `${calendar.code} v${calendar.version} · ${calendar.name}`,
                        }))}
                    optional
                    value={selectedCalendarId}
                    onValueChange={selectCalendar}
                />
                {form.errors.configuration && (
                    <p className="text-sm text-destructive">
                        {form.errors.configuration}
                    </p>
                )}
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        size="sm"
                        disabled={
                            form.processing || !!form.errors.configuration
                        }
                    >
                        {copy.save_draft}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.patch(
                                publish.url([workflow.id, version.id]),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <ShieldCheck data-icon="inline-start" />{' '}
                        {copy.publish_version}
                    </Button>
                </div>
            </form>
        </FormSheet>
    );
}

function WorkflowForm() {
    const copy = useWorkflowCopy();

    return (
        <FormSheet
            title={copy.create_workflow_definition}
            description={copy.create_workflow_description}
            triggerLabel={copy.new_workflow}
            icon={Plus}
            size="lg"
        >
            <Form {...store.form()} resetOnSuccess className="grid gap-4 pt-4">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="workflow-code">{copy.code}</Label>
                            <Input
                                id="workflow-code"
                                name="code"
                                required
                                aria-invalid={!!errors.code}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="workflow-name">{copy.name}</Label>
                            <Input
                                id="workflow-name"
                                name="name"
                                required
                                aria-invalid={!!errors.name}
                            />
                        </div>
                        <SearchableSelect
                            id="workflow-module"
                            name="module"
                            label={copy.module}
                            options={modules.map((module) => ({
                                id: module,
                                name: module.replaceAll('-', ' '),
                            }))}
                        />
                        <input type="hidden" name="status" value="active" />
                        <div className="grid gap-2">
                            <Label htmlFor="workflow-description">
                                {copy.description_label}
                            </Label>
                            <Textarea
                                id="workflow-description"
                                name="description"
                            />
                        </div>
                        <Button disabled={processing}>
                            {copy.create_workflow}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function weekdays(copy: Record<string, string>) {
    return [
        { id: '1', name: copy.monday },
        { id: '2', name: copy.tuesday },
        { id: '3', name: copy.wednesday },
        { id: '4', name: copy.thursday },
        { id: '5', name: copy.friday },
        { id: '6', name: copy.saturday },
        { id: '7', name: copy.sunday },
    ];
}

function CalendarForm() {
    const copy = useWorkflowCopy();

    return (
        <FormSheet
            title={copy.create_calendar_version}
            description={copy.create_calendar_description}
            triggerLabel={copy.new_calendar}
            icon={CalendarDays}
            size="xl"
        >
            <Form
                {...storeCalendar.form()}
                resetOnSuccess
                className="grid gap-5 pt-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="calendar-code">
                                    {copy.code}
                                </Label>
                                <Input
                                    id="calendar-code"
                                    name="code"
                                    placeholder="KENYA-GOVERNMENT"
                                    aria-invalid={!!errors.code}
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="calendar-name">
                                    {copy.name}
                                </Label>
                                <Input
                                    id="calendar-name"
                                    name="name"
                                    aria-invalid={!!errors.name}
                                    required
                                />
                            </div>
                            <ReferenceCatalogSelect
                                id="calendar-timezone"
                                name="timezone"
                                label={copy.timezone}
                                catalog="timezone"
                            />
                            <SearchableMultiSelect
                                name="working_days"
                                label={copy.working_days}
                                options={weekdays(copy)}
                                defaultValues={['1', '2', '3', '4', '5']}
                            />
                            <TimePickerField
                                name="workday_starts_at"
                                label={copy.workday_starts}
                                defaultValue="08:00"
                                required
                            />
                            <TimePickerField
                                name="workday_ends_at"
                                label={copy.workday_ends}
                                defaultValue="17:00"
                                required
                            />
                            <DatePickerField
                                name="effective_from"
                                label={copy.effective_from}
                                required
                            />
                            <DatePickerField
                                name="effective_to"
                                label={copy.effective_to}
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            <Plus data-icon="inline-start" />{' '}
                            {copy.create_draft}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function BusinessCalendarCard({ calendar }: { calendar: BusinessCalendar }) {
    const copy = useWorkflowCopy();
    const [sheet, setSheet] = useState<'details' | 'holiday' | null>(null);
    const workingDayNames = calendar.workingDays
        .map(
            (day) =>
                weekdays(copy).find((option) => option.id === String(day))
                    ?.name,
        )
        .filter(Boolean)
        .join(', ');

    return (
        <>
            <Card>
                <CardHeader className="flex-row items-start justify-between gap-3">
                    <div>
                        <CardTitle>{calendar.name}</CardTitle>
                        <CardDescription>
                            {calendar.code} {copy.separator}{' '}
                            {copy.version_prefix}
                            {calendar.version}
                        </CardDescription>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Actions for ${calendar.code} version ${calendar.version}`}
                            >
                                <MoreHorizontal />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    onSelect={() => setSheet('details')}
                                >
                                    <CalendarDays /> {copy.view_calendar}
                                </DropdownMenuItem>
                                {calendar.status === 'draft' && (
                                    <>
                                        <DropdownMenuItem
                                            onSelect={() => setSheet('holiday')}
                                        >
                                            <Plus /> {copy.add_exception}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onSelect={() =>
                                                router.patch(
                                                    publishCalendar.url({
                                                        businessCalendar:
                                                            calendar.id,
                                                    }),
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                )
                                            }
                                        >
                                            <ShieldCheck />{' '}
                                            {copy.publish_independently}
                                        </DropdownMenuItem>
                                    </>
                                )}
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </CardHeader>
                <CardContent className="grid gap-3">
                    <div className="flex flex-wrap gap-2">
                        <Badge>{calendar.status}</Badge>
                        <Badge variant="outline">
                            {calendar.holidays.length} {copy.exceptions}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {workingDayNames} {copy.separator}{' '}
                        {calendar.workdayStartsAt.slice(0, 5)}
                        {copy.range_separator}
                        {calendar.workdayEndsAt.slice(0, 5)} {calendar.timezone}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {copy.effective} {calendar.effectiveFrom}
                        {calendar.effectiveTo
                            ? ` ${copy.to} ${calendar.effectiveTo}`
                            : ` ${copy.no_end_date}`}
                    </p>
                </CardContent>
            </Card>
            <Sheet
                open={sheet !== null}
                onOpenChange={(open) => !open && setSheet(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>
                            {sheet === 'holiday'
                                ? copy.add_calendar_exception
                                : `${calendar.code} v${calendar.version}`}
                        </SheetTitle>
                        <SheetDescription>
                            {sheet === 'holiday'
                                ? copy.exception_description
                                : `${calendar.name} ${copy.separator} ${calendar.status}`}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-8">
                        {sheet === 'holiday' ? (
                            <Form
                                {...storeHoliday.form({
                                    businessCalendar: calendar.id,
                                })}
                                resetOnSuccess
                                className="grid gap-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <DatePickerField
                                            name="holiday_date"
                                            label={copy.exception_date}
                                            required
                                        />
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`holiday-name-${calendar.id}`}
                                            >
                                                {copy.name}
                                            </Label>
                                            <Input
                                                id={`holiday-name-${calendar.id}`}
                                                name="name"
                                                required
                                            />
                                        </div>
                                        <SearchableSelect
                                            id={`holiday-category-${calendar.id}`}
                                            name="category"
                                            label={copy.category}
                                            options={[
                                                'public_holiday',
                                                'government_closure',
                                                'exception',
                                            ].map((value) => ({
                                                id: value,
                                                name: value.replaceAll(
                                                    '_',
                                                    ' ',
                                                ),
                                            }))}
                                        />
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`holiday-source-${calendar.id}`}
                                            >
                                                {copy.authority_reference}
                                            </Label>
                                            <Input
                                                id={`holiday-source-${calendar.id}`}
                                                name="source_reference"
                                                required
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {copy.add_exception}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <div className="grid gap-3">
                                {calendar.holidays.map((holiday) => (
                                    <Card key={holiday.id}>
                                        <CardHeader className="flex-row items-start justify-between gap-3">
                                            <div>
                                                <CardTitle className="text-base">
                                                    {holiday.name}
                                                </CardTitle>
                                                <CardDescription>
                                                    {holiday.date}{' '}
                                                    {copy.separator}{' '}
                                                    {holiday.category.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </CardDescription>
                                            </div>
                                            {calendar.status === 'draft' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Remove ${holiday.name}`}
                                                    onClick={() =>
                                                        router.delete(
                                                            destroyHoliday.url({
                                                                businessCalendar:
                                                                    calendar.id,
                                                                businessCalendarHoliday:
                                                                    holiday.id,
                                                            }),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-sm">
                                                {holiday.sourceReference}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {copy.recorded_by}{' '}
                                                {holiday.creator}
                                            </p>
                                        </CardContent>
                                    </Card>
                                ))}
                                {calendar.holidays.length === 0 && (
                                    <WorkspaceEmptyState
                                        title={copy.no_calendar_exceptions}
                                        description={
                                            copy.no_calendar_exceptions_description
                                        }
                                        className="min-h-48"
                                    />
                                )}
                                {calendar.checksum && (
                                    <p className="font-mono text-xs break-all text-muted-foreground">
                                        {copy.sha256} {calendar.checksum}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

export default function WorkflowRegistry({
    calendars,
    filters,
    users,
    workflows,
}: Props) {
    const copy = useWorkflowCopy();
    function search(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get(
            index.url(),
            { search: data.get('search')?.toString() ?? '' },
            { preserveState: true },
        );
    }

    return (
        <>
            <Head title={copy.head_title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        {copy.eyebrow}
                    </p>
                    <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        {copy.title}
                    </h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-[#c7d6dd] sm:text-base">
                        {copy.description}
                    </p>
                </section>

                <section className="flex flex-col gap-4">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <h2 className="text-xl font-bold">
                                {copy.business_calendars}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {copy.business_calendars_description}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                                <Button
                                    key={format}
                                    variant="outline"
                                    size="sm"
                                    asChild
                                >
                                    <a
                                        href={
                                            exportMethod({
                                                workspace: 'business-calendars',
                                                format,
                                            }).url
                                        }
                                    >
                                        {format.toUpperCase()}
                                    </a>
                                </Button>
                            ))}
                            <CalendarForm />
                        </div>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                        {calendars.map((calendar) => (
                            <BusinessCalendarCard
                                key={calendar.id}
                                calendar={calendar}
                            />
                        ))}
                        {calendars.length === 0 && (
                            <WorkspaceEmptyState
                                title={copy.no_business_calendars}
                                description={
                                    copy.no_business_calendars_description
                                }
                                className="min-h-56 lg:col-span-2 xl:col-span-3"
                            />
                        )}
                    </div>
                </section>

                <section className="grid gap-4">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <h2 className="text-xl font-bold">
                                {copy.workflow_definitions}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {copy.workflow_definitions_description}
                            </p>
                        </div>
                        <WorkflowForm />
                    </div>
                    <div className="space-y-4">
                        <form onSubmit={search} className="flex gap-2">
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder={copy.search_workflows}
                                aria-label={copy.search_workflows}
                            />
                            <Button type="submit" variant="outline">
                                <Search data-icon="inline-start" />{' '}
                                {copy.search}
                            </Button>
                        </form>
                        {workflows.data.map((workflow) => {
                            const draft = workflow.versions.find(
                                (version) => version.status === 'draft',
                            );

                            return (
                                <Card key={workflow.id}>
                                    <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2">
                                                <GitBranch aria-hidden="true" />{' '}
                                                {workflow.name}
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                {workflow.code} {copy.separator}{' '}
                                                {workflow.module.replaceAll(
                                                    '-',
                                                    ' ',
                                                )}{' '}
                                                {copy.separator}{' '}
                                                {workflow.description ??
                                                    copy.no_description}
                                            </CardDescription>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Badge>{workflow.status}</Badge>
                                            <Badge variant="secondary">
                                                {workflow.activeInstances}{' '}
                                                {copy.active}
                                            </Badge>
                                            {workflow.overdueInstances > 0 && (
                                                <Badge variant="destructive">
                                                    {workflow.overdueInstances}{' '}
                                                    {copy.overdue}
                                                </Badge>
                                            )}
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex flex-wrap gap-2">
                                            {workflow.versions.map(
                                                (version) => (
                                                    <Badge
                                                        key={version.id}
                                                        variant={
                                                            version.status ===
                                                            'published'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {copy.version_prefix}
                                                        {version.version}{' '}
                                                        {copy.separator}{' '}
                                                        {version.status}
                                                        {version.checksum
                                                            ? ` · ${version.checksum.slice(0, 8)}`
                                                            : ''}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {workflow.versions.map(
                                                (version) => (
                                                    <WorkflowSimulatorSheet
                                                        key={`simulation-${version.id}`}
                                                        workflowId={workflow.id}
                                                        workflowName={
                                                            workflow.name
                                                        }
                                                        version={version}
                                                        users={users}
                                                    />
                                                ),
                                            )}
                                        </div>
                                        {draft ? (
                                            <DraftEditor
                                                workflow={workflow}
                                                version={draft}
                                                calendars={calendars}
                                            />
                                        ) : (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="mt-4"
                                                onClick={() =>
                                                    router.post(
                                                        storeVersion.url([
                                                            workflow.id,
                                                        ]),
                                                        {
                                                            configuration:
                                                                starterConfiguration,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Plus data-icon="inline-start" />{' '}
                                                {copy.create_next_draft}
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                        {workflows.data.length === 0 && (
                            <WorkspaceEmptyState
                                title={copy.no_matching_workflows}
                                description={
                                    copy.no_matching_workflows_description
                                }
                                className="min-h-64 bg-card"
                            />
                        )}
                        <div className="flex items-center justify-between text-sm text-muted-foreground">
                            <span>
                                {workflows.total.toLocaleString()}{' '}
                                {copy.definitions} {copy.separator} {copy.page}{' '}
                                {workflows.current_page} {copy.of}{' '}
                                {workflows.last_page}
                            </span>
                            <div className="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={!workflows.prev_page_url}
                                    onClick={() =>
                                        workflows.prev_page_url &&
                                        router.visit(workflows.prev_page_url)
                                    }
                                >
                                    {copy.previous}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={!workflows.next_page_url}
                                    onClick={() =>
                                        workflows.next_page_url &&
                                        router.visit(workflows.next_page_url)
                                    }
                                >
                                    {copy.next}
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
