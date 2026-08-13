import { Form, usePage } from '@inertiajs/react';
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

function MeetingSeriesForm({ workingGroups, users }: Props) {
    const copy = usePage().props.localization.dswg;

    return (
        <FormSheet
            title={copy.form_create_series_title}
            triggerLabel={copy.form_create_series_trigger}
            icon={CalendarClock}
            description={copy.form_create_series_description}
        >
            <Form
                action={storeMeetingSeries()}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <Field
                            label={copy.form_working_group}
                            error={errors.dswg_working_group_id}
                        >
                            <Select
                                name="dswg_working_group_id"
                                options={workingGroups}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={copy.form_reference_prefix}
                                error={errors.reference_prefix}
                            >
                                <Input
                                    name="reference_prefix"
                                    placeholder="DSWG-WASH-QTR"
                                    required
                                />
                            </Field>
                            <Field
                                label={copy.form_series_title}
                                error={errors.title}
                            >
                                <Input name="title" required />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label={copy.form_frequency}
                                error={errors.frequency}
                            >
                                <StaticSearchableSelect
                                    id="dswg-series-frequency"
                                    name="frequency"
                                    values={['weekly', 'monthly', 'quarterly']}
                                />
                            </Field>
                            <Field
                                label={copy.form_every}
                                error={errors.interval}
                            >
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
                                label={copy.form_duration_minutes}
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
                            label={copy.form_timezone}
                            catalog="timezone"
                            error={errors.timezone}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="first_starts_at"
                                label={copy.form_first_meeting}
                                error={errors.first_starts_at}
                                required
                                includeTime
                            />
                            <DatePickerField
                                name="ends_on"
                                label={copy.form_series_ends}
                                error={errors.ends_on}
                                required
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label={copy.form_mode}
                                error={errors.meeting_mode}
                            >
                                <StaticSearchableSelect
                                    id="dswg-series-mode"
                                    name="meeting_mode"
                                    values={['physical', 'virtual', 'hybrid']}
                                />
                            </Field>
                            <Field label={copy.form_venue} error={errors.venue}>
                                <Input name="venue" />
                            </Field>
                            <Field
                                label={copy.form_virtual_link}
                                error={errors.virtual_link}
                            >
                                <Input name="virtual_link" type="url" />
                            </Field>
                        </div>
                        <Field
                            label={copy.form_standing_agenda}
                            error={errors.agenda}
                        >
                            <textarea
                                name="agenda"
                                required
                                className={textareaClass}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label={copy.form_invitees}
                                error={errors.invitee_ids}
                            >
                                <Multi name="invitee_ids[]" options={users} />
                            </Field>
                            <Field
                                label={copy.form_quorum}
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
                                label={copy.form_generate_days_ahead}
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
                            aria-busy={processing}
                        >
                            {copy.form_create_series_submit}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function WorkingGroupForm({ counties, sectors, organizations, users }: Props) {
    const copy = usePage().props.localization.dswg;

    return (
        <FormSheet
            title={copy.form_establish_group_title}
            triggerLabel={copy.form_establish_group_trigger}
            icon={Network}
            description={copy.form_establish_group_description}
        >
            <Form
                action={storeWorkingGroup()}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={copy.form_code} error={errors.code}>
                                <Input
                                    name="code"
                                    required
                                    placeholder="DSWG-WASH"
                                />
                            </Field>
                            <Field label={copy.form_name} error={errors.name}>
                                <Input name="name" required />
                            </Field>
                        </div>
                        <Field label={copy.form_mandate} error={errors.mandate}>
                            <textarea
                                name="mandate"
                                required
                                className={textareaClass}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={copy.form_scope} error={errors.scope}>
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
                                label={copy.form_meeting_frequency}
                                error={errors.meeting_frequency}
                            >
                                <Input
                                    name="meeting_frequency"
                                    defaultValue="Quarterly"
                                />
                            </Field>
                        </div>
                        <Field
                            label={copy.form_lead_organization}
                            error={errors.lead_organization_id}
                        >
                            <Select
                                name="lead_organization_id"
                                options={organizations}
                                optional
                            />
                        </Field>
                        <Field
                            label={copy.form_secretariat_lead}
                            error={errors.secretariat_user_id}
                        >
                            <Select
                                name="secretariat_user_id"
                                options={users}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label={copy.form_counties}
                                error={errors.county_ids}
                            >
                                <Multi name="county_ids[]" options={counties} />
                            </Field>
                            <Field
                                label={copy.form_sectors}
                                error={errors.sector_ids}
                            >
                                <Multi name="sector_ids[]" options={sectors} />
                            </Field>
                            <Field
                                label={copy.form_members}
                                error={errors.member_ids}
                            >
                                <Multi name="member_ids[]" options={users} />
                            </Field>
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing}
                        >
                            {copy.form_establish_group_submit}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function MeetingForm({ workingGroups, users }: Props) {
    const copy = usePage().props.localization.dswg;

    return (
        <FormSheet
            title={copy.form_schedule_meeting_title}
            triggerLabel={copy.form_schedule_meeting_trigger}
            icon={CalendarPlus}
            description={copy.form_schedule_meeting_description}
        >
            <Form action={storeMeeting()} className="grid gap-4" resetOnSuccess>
                {({ errors, processing }) => (
                    <>
                        <Field
                            label={copy.form_working_group}
                            error={errors.dswg_working_group_id}
                        >
                            <Select
                                name="dswg_working_group_id"
                                options={workingGroups}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={copy.form_reference}
                                error={errors.reference}
                            >
                                <Input name="reference" required />
                            </Field>
                            <Field label={copy.form_title} error={errors.title}>
                                <Input name="title" required />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <DatePickerField
                                name="starts_at"
                                label={copy.form_starts}
                                error={errors.starts_at}
                                required
                                includeTime
                            />
                            <DatePickerField
                                name="ends_at"
                                label={copy.form_ends}
                                error={errors.ends_at}
                                required
                                includeTime
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label={copy.form_mode}
                                error={errors.meeting_mode}
                            >
                                <StaticSearchableSelect
                                    id="dswg-meeting-mode"
                                    name="meeting_mode"
                                    values={['physical', 'virtual', 'hybrid']}
                                />
                            </Field>
                            <Field label={copy.form_venue} error={errors.venue}>
                                <Input name="venue" />
                            </Field>
                            <Field
                                label={copy.form_virtual_link}
                                error={errors.virtual_link}
                            >
                                <Input name="virtual_link" type="url" />
                            </Field>
                        </div>
                        <Field label={copy.form_agenda} error={errors.agenda}>
                            <textarea
                                name="agenda"
                                required
                                className={textareaClass}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={copy.form_invitees}
                                error={errors.invitee_ids}
                            >
                                <Multi name="invitee_ids[]" options={users} />
                            </Field>
                            <Field
                                label={copy.form_quorum}
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
                            aria-busy={processing}
                        >
                            {copy.form_schedule_meeting_submit}
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
