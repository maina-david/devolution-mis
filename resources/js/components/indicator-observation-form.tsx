import { Form, usePage } from '@inertiajs/react';
import { Send } from 'lucide-react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/monitoring-evaluation/observations';

type Indicator = {
    id: string;
    code: string;
    name: string;
    value_type: string;
    unit_of_measure: string;
};
type Option = { id: string; name: string };

export default function IndicatorObservationForm({
    indicators,
    counties,
    programmes,
}: {
    indicators: Indicator[];
    counties: Option[];
    programmes: Option[];
}) {
    const copy = usePage().props.localization.monitoringResults;

    if (!indicators.length || !counties.length || !programmes.length) {
        return null;
    }

    return (
        <FormSheet
            title={copy.submit_target_or_result}
            triggerLabel={copy.submit_result}
            icon={Send}
            size="xl"
            description={copy.submit_result_description}
        >
            <Form
                {...store.form({})}
                className="grid gap-4 md:grid-cols-2"
                resetOnSuccess
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            label={copy.indicator}
                            error={errors.indicator_definition_id}
                        >
                            <SearchableSelect
                                id="observation-indicator"
                                name="indicator_definition_id"
                                label=""
                                options={indicators.map((item) => ({
                                    id: item.id,
                                    name: `${item.code} · ${item.name}`,
                                }))}
                            />
                        </Field>
                        <Field label={copy.county} error={errors.county_id}>
                            <Options name="county_id" options={counties} />
                        </Field>
                        <Field
                            label={copy.programme}
                            error={errors.programme_id}
                        >
                            <Options name="programme_id" options={programmes} />
                        </Field>
                        <Field label={copy.measure} error={errors.measure_type}>
                            <StaticSearchableSelect
                                id="observation-measure"
                                name="measure_type"
                                values={['target', 'actual', 'baseline']}
                                labels={copy}
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
                        <Field
                            label={copy.numeric_value}
                            error={errors.numeric_value}
                        >
                            <Input
                                name="numeric_value"
                                type="number"
                                step="any"
                            />
                        </Field>
                        <Field
                            label={copy.narrative_value}
                            error={errors.narrative_value}
                        >
                            <Input name="narrative_value" />
                        </Field>
                        <Field
                            label={copy.source_reference}
                            error={errors.source_reference}
                        >
                            <Input
                                name="source_reference"
                                required
                                placeholder={copy.source_reference_placeholder}
                            />
                        </Field>
                        <Field
                            label={copy.source_system}
                            error={errors['provenance.source_system']}
                        >
                            <Input
                                name="provenance[source_system]"
                                required
                                defaultValue="county-mis"
                            />
                        </Field>
                        <DatePickerField
                            name="provenance[captured_at]"
                            label={copy.captured_at}
                            error={errors['provenance.captured_at']}
                            required
                            includeTime
                        />
                        <Field
                            label={copy.import_batch}
                            error={errors['provenance.import_batch']}
                        >
                            <Input name="provenance[import_batch]" />
                        </Field>
                        <div className="md:col-span-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                <Send aria-hidden="true" />
                                {copy.submit_for_verification}
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
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function Options({ name, options }: { name: string; options: Option[] }) {
    return (
        <SearchableSelect
            id={`observation-${name}`}
            name={name}
            label=""
            options={options}
        />
    );
}
