import { Form, usePage } from '@inertiajs/react';
import { ChartSpline } from 'lucide-react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/monitoring-evaluation/indicators';

type Option = { id: string; name: string };

export default function IndicatorDefinitionForm({
    sectors,
    programmes,
    catalogue,
}: {
    sectors: Option[];
    programmes: Option[];
    catalogue: { available: boolean };
}) {
    const copy = usePage().props.localization.indicatorDefinitions;

    return (
        <FormSheet
            title={copy.define_indicator}
            triggerLabel={copy.define_indicator}
            icon={ChartSpline}
            size="xl"
            description={copy.define_indicator_description}
            triggerDisabled={!catalogue.available}
            triggerTitle={
                !catalogue.available ? copy.catalogue_required : undefined
            }
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
                                placeholder="M07-OUT-01"
                            />
                        </Field>
                        <Field label={copy.indicator_name} error={errors.name}>
                            <Input name="name" required />
                        </Field>
                        <Field
                            label={copy.results_level}
                            error={errors.results_level}
                        >
                            <Select
                                name="results_level"
                                values={[
                                    'input',
                                    'activity',
                                    'output',
                                    'outcome',
                                    'impact',
                                ]}
                                labels={copy}
                            />
                        </Field>
                        <Field label={copy.frequency} error={errors.frequency}>
                            <Select
                                name="frequency"
                                values={[
                                    'monthly',
                                    'quarterly',
                                    'semiannual',
                                    'annual',
                                    'ad_hoc',
                                ]}
                                labels={copy}
                            />
                        </Field>
                        <Field label={copy.unit} error={errors.unit_of_measure}>
                            <Input
                                name="unit_of_measure"
                                required
                                placeholder={copy.unit_placeholder}
                            />
                        </Field>
                        <Field
                            label={copy.value_type}
                            error={errors.value_type}
                        >
                            <Select
                                name="value_type"
                                values={[
                                    'number',
                                    'percentage',
                                    'currency',
                                    'count',
                                    'text',
                                ]}
                                labels={copy}
                            />
                        </Field>
                        <Field label={copy.direction} error={errors.direction}>
                            <Select
                                name="direction"
                                values={['increase', 'decrease', 'maintain']}
                                labels={copy}
                            />
                        </Field>
                        <Field label={copy.sector} error={errors.sector_id}>
                            <OptionSelect
                                name="sector_id"
                                options={sectors}
                                optional
                            />
                        </Field>
                        <Field
                            label={copy.programme}
                            error={errors.programme_id}
                        >
                            <OptionSelect
                                name="programme_id"
                                options={programmes}
                                optional
                            />
                        </Field>
                        <Field
                            label={copy.data_source}
                            error={errors.data_source}
                        >
                            <Input name="data_source" required />
                        </Field>
                        <Field
                            label={copy.verification_method}
                            error={errors.verification_method}
                        >
                            <Input name="verification_method" required />
                        </Field>
                        <DatePickerField
                            name="effective_from"
                            label={copy.effective_from}
                            error={errors.effective_from}
                        />
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="indicator-description">
                                {copy.definition_description}
                            </Label>
                            <textarea
                                id="indicator-description"
                                name="description"
                                required
                                aria-invalid={Boolean(errors.description)}
                                aria-describedby={
                                    errors.description
                                        ? 'indicator-description-error'
                                        : undefined
                                }
                                className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                id="indicator-description-error"
                                message={errors.description}
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                {copy.create_draft_indicator}
                            </Button>
                        </div>
                    </>
                )}
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
    const id = `indicator-${label.toLowerCase().replaceAll(' ', '-')}`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div id={id}>{children}</div>
            <InputError message={error} />
        </div>
    );
}
function Select({
    name,
    values,
    labels,
}: {
    name: string;
    values: string[];
    labels: Record<string, string>;
}) {
    return (
        <StaticSearchableSelect
            id={`indicator-${name}`}
            name={name}
            values={values}
            labels={labels}
        />
    );
}
function OptionSelect({
    name,
    options,
    optional = false,
}: {
    name: string;
    options: Option[];
    optional?: boolean;
}) {
    return (
        <SearchableSelect
            id={`indicator-${name}`}
            name={name}
            label=""
            options={options}
            optional={optional}
        />
    );
}
