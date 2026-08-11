import { Form } from '@inertiajs/react';
import { Send } from 'lucide-react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
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
    teamSlug,
    indicators,
    counties,
    programmes,
}: {
    teamSlug: string;
    indicators: Indicator[];
    counties: Option[];
    programmes: Option[];
}) {
    if (!indicators.length || !counties.length || !programmes.length) {
        return null;
    }

    return (
        <FormSheet
            title="Submit target or result"
            triggerLabel="Submit result"
            icon={Send}
            size="xl"
            description="Every value retains source provenance and enters independent data-quality verification."
        >
            <Form
                {...store.form({ current_team: teamSlug })}
                className="grid gap-4 md:grid-cols-2"
                resetOnSuccess
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            label="Indicator"
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
                        <Field label="County" error={errors.county_id}>
                            <Options name="county_id" options={counties} />
                        </Field>
                        <Field label="Programme" error={errors.programme_id}>
                            <Options name="programme_id" options={programmes} />
                        </Field>
                        <Field label="Measure" error={errors.measure_type}>
                            <StaticSearchableSelect
                                id="observation-measure"
                                name="measure_type"
                                values={['target', 'actual', 'baseline']}
                            />
                        </Field>
                        <DatePickerField
                            name="period_start"
                            label="Period start"
                            error={errors.period_start}
                            required
                        />
                        <DatePickerField
                            name="period_end"
                            label="Period end"
                            error={errors.period_end}
                            required
                        />
                        <Field
                            label="Numeric value"
                            error={errors.numeric_value}
                        >
                            <Input
                                name="numeric_value"
                                type="number"
                                step="any"
                            />
                        </Field>
                        <Field
                            label="Narrative value"
                            error={errors.narrative_value}
                        >
                            <Input name="narrative_value" />
                        </Field>
                        <Field
                            label="Source reference"
                            error={errors.source_reference}
                        >
                            <Input
                                name="source_reference"
                                required
                                placeholder="Report, ledger, URL or document reference"
                            />
                        </Field>
                        <Field
                            label="Source system"
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
                            label="Captured at"
                            error={errors['provenance.captured_at']}
                            required
                            includeTime
                        />
                        <Field
                            label="Import batch"
                            error={errors['provenance.import_batch']}
                        >
                            <Input name="provenance[import_batch]" />
                        </Field>
                        <div className="md:col-span-2">
                            <Button type="submit" disabled={processing}>
                                <Send aria-hidden="true" />
                                Submit for verification
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
            {error && <p className="text-xs text-destructive">{error}</p>}
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
