import { Form } from '@inertiajs/react';
import { CalendarClock, CalendarPlus, Network } from 'lucide-react';
import {
    storeMeeting,
    storeMeetingSeries,
    storeWorkingGroup,
} from '@/actions/App/Http/Controllers/DswgCoordinationController';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
import SearchableMultiSelect from '@/components/searchable-multi-select';
import SearchableSelect from '@/components/searchable-select';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type DswgOption = {
    id: string;
    name: string;
    code?: string;
    email?: string;
    title?: string;
};
type Props = {
    teamSlug: string;
    workingGroups: DswgOption[];
    counties: DswgOption[];
    sectors: DswgOption[];
    organizations: DswgOption[];
    users: DswgOption[];
};

export default function DswgCoordinationForms(props: Props) {
    return (
        <div className="grid gap-5 xl:grid-cols-3">
            <WorkingGroupForm {...props} />
            <MeetingForm {...props} />
            <MeetingSeriesForm {...props} />
        </div>
    );
}

function MeetingSeriesForm({ teamSlug, workingGroups, users }: Props) {
    return (
        <FormSheet
            title="Create a recurring meeting series"
            triggerLabel="Create recurring series"
            icon={CalendarClock}
            description="Generate an idempotent rolling schedule with governed workflows and tracked invitations."
        >
            <Form
                action={storeMeetingSeries(teamSlug)}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label="Working group"
                            error={errors.dswg_working_group_id}
                        >
                            <Select
                                name="dswg_working_group_id"
                                options={workingGroups}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Reference prefix"
                                error={errors.reference_prefix}
                            >
                                <Input
                                    name="reference_prefix"
                                    placeholder="DSWG-WASH-QTR"
                                    required
                                />
                            </Field>
                            <Field label="Series title" error={errors.title}>
                                <Input name="title" required />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Frequency" error={errors.frequency}>
                                <StaticSearchableSelect
                                    id="dswg-series-frequency"
                                    name="frequency"
                                    values={['weekly', 'monthly', 'quarterly']}
                                />
                            </Field>
                            <Field label="Every" error={errors.interval}>
                                <Input
                                    name="interval"
                                    type="number"
                                    min="1"
                                    max="12"
                                    defaultValue="1"
                                    required
                                />
                            </Field>
                            <Field
                                label="Duration (minutes)"
                                error={errors.duration_minutes}
                            >
                                <Input
                                    name="duration_minutes"
                                    type="number"
                                    min="15"
                                    max="1440"
                                    defaultValue="120"
                                    required
                                />
                            </Field>
                        </div>
                        <ReferenceCatalogSelect
                            id="dswg-series-timezone"
                            name="timezone"
                            label="Timezone"
                            catalog="timezone"
                            error={errors.timezone}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="first_starts_at"
                                label="First meeting"
                                error={errors.first_starts_at}
                                required
                                includeTime
                            />
                            <DatePickerField
                                name="ends_on"
                                label="Series ends"
                                error={errors.ends_on}
                                required
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Mode" error={errors.meeting_mode}>
                                <StaticSearchableSelect
                                    id="dswg-series-mode"
                                    name="meeting_mode"
                                    values={['physical', 'virtual', 'hybrid']}
                                />
                            </Field>
                            <Field label="Venue" error={errors.venue}>
                                <Input name="venue" />
                            </Field>
                            <Field
                                label="Virtual link"
                                error={errors.virtual_link}
                            >
                                <Input name="virtual_link" type="url" />
                            </Field>
                        </div>
                        <Field label="Standing agenda" error={errors.agenda}>
                            <textarea
                                name="agenda"
                                required
                                className={textareaClass}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Invitees" error={errors.invitee_ids}>
                                <Multi name="invitee_ids[]" options={users} />
                            </Field>
                            <Field
                                label="Quorum"
                                error={errors.quorum_required}
                            >
                                <Input
                                    name="quorum_required"
                                    type="number"
                                    min="1"
                                    required
                                />
                            </Field>
                            <Field
                                label="Generate days ahead"
                                error={errors.generation_horizon_days}
                            >
                                <Input
                                    name="generation_horizon_days"
                                    type="number"
                                    min="7"
                                    max="365"
                                    defaultValue="90"
                                    required
                                />
                            </Field>
                        </div>
                        <Button
                            type="submit"
                            disabled={processing || workingGroups.length === 0}
                        >
                            Create series and schedule
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function WorkingGroupForm({
    teamSlug,
    counties,
    sectors,
    organizations,
    users,
}: Props) {
    return (
        <FormSheet
            title="Establish a working group"
            triggerLabel="Establish working group"
            icon={Network}
            description="Define mandate, secretariat, membership, county reach and sector coverage."
        >
            <Form
                action={storeWorkingGroup(teamSlug)}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Code" error={errors.code}>
                                <Input
                                    name="code"
                                    required
                                    placeholder="DSWG-WASH"
                                />
                            </Field>
                            <Field label="Name" error={errors.name}>
                                <Input name="name" required />
                            </Field>
                        </div>
                        <Field label="Mandate" error={errors.mandate}>
                            <textarea
                                name="mandate"
                                required
                                className={textareaClass}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Scope" error={errors.scope}>
                                <StaticSearchableSelect
                                    id="dswg-scope"
                                    name="scope"
                                    values={[
                                        'national',
                                        'regional',
                                        'county',
                                        'sector',
                                    ]}
                                />
                            </Field>
                            <Field
                                label="Meeting frequency"
                                error={errors.meeting_frequency}
                            >
                                <Input
                                    name="meeting_frequency"
                                    defaultValue="Quarterly"
                                />
                            </Field>
                        </div>
                        <Field
                            label="Lead organization"
                            error={errors.lead_organization_id}
                        >
                            <Select
                                name="lead_organization_id"
                                options={organizations}
                                optional
                            />
                        </Field>
                        <Field
                            label="Secretariat lead"
                            error={errors.secretariat_user_id}
                        >
                            <Select
                                name="secretariat_user_id"
                                options={users}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Counties" error={errors.county_ids}>
                                <Multi name="county_ids[]" options={counties} />
                            </Field>
                            <Field label="Sectors" error={errors.sector_ids}>
                                <Multi name="sector_ids[]" options={sectors} />
                            </Field>
                            <Field label="Members" error={errors.member_ids}>
                                <Multi name="member_ids[]" options={users} />
                            </Field>
                        </div>
                        <Button type="submit" disabled={processing}>
                            Establish working group
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function MeetingForm({ teamSlug, workingGroups, users }: Props) {
    return (
        <FormSheet
            title="Schedule a meeting"
            triggerLabel="Schedule meeting"
            icon={CalendarPlus}
            description="Start the governed meeting lifecycle and send tracked invitations."
        >
            <Form
                action={storeMeeting(teamSlug)}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label="Working group"
                            error={errors.dswg_working_group_id}
                        >
                            <Select
                                name="dswg_working_group_id"
                                options={workingGroups}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Reference" error={errors.reference}>
                                <Input name="reference" required />
                            </Field>
                            <Field label="Title" error={errors.title}>
                                <Input name="title" required />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="starts_at"
                                label="Starts"
                                error={errors.starts_at}
                                required
                                includeTime
                            />
                            <DatePickerField
                                name="ends_at"
                                label="Ends"
                                error={errors.ends_at}
                                required
                                includeTime
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Mode" error={errors.meeting_mode}>
                                <StaticSearchableSelect
                                    id="dswg-meeting-mode"
                                    name="meeting_mode"
                                    values={['physical', 'virtual', 'hybrid']}
                                />
                            </Field>
                            <Field label="Venue" error={errors.venue}>
                                <Input name="venue" />
                            </Field>
                            <Field
                                label="Virtual link"
                                error={errors.virtual_link}
                            >
                                <Input name="virtual_link" type="url" />
                            </Field>
                        </div>
                        <Field label="Agenda" error={errors.agenda}>
                            <textarea
                                name="agenda"
                                required
                                className={textareaClass}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Invitees" error={errors.invitee_ids}>
                                <Multi name="invitee_ids[]" options={users} />
                            </Field>
                            <Field
                                label="Quorum"
                                error={errors.quorum_required}
                            >
                                <Input
                                    name="quorum_required"
                                    type="number"
                                    min="1"
                                    required
                                />
                            </Field>
                        </div>
                        <Button
                            type="submit"
                            disabled={processing || workingGroups.length === 0}
                        >
                            Schedule and invite
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

export function Field({
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
export function Select({
    name,
    options,
    optional = false,
}: {
    name: string;
    options: DswgOption[];
    optional?: boolean;
}) {
    return (
        <SearchableSelect
            id={`dswg-${name}`}
            name={name}
            label=""
            options={options.map((option) => ({
                id: option.id,
                name: `${option.code ? `${option.code} · ` : ''}${option.title ?? option.name}${option.email ? ` · ${option.email}` : ''}`,
            }))}
            optional={optional}
        />
    );
}
export function Multi({
    name,
    options,
}: {
    name: string;
    options: DswgOption[];
}) {
    return (
        <SearchableMultiSelect
            name={name}
            label=""
            options={options.map((option) => ({
                id: option.id,
                name: `${option.name}${option.email ? ` · ${option.email}` : ''}`,
            }))}
        />
    );
}
export const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm';
export const textareaClass =
    'min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm';
