import { Form } from '@inertiajs/react';
import { ChartSpline } from 'lucide-react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/monitoring-evaluation/indicators';

type Option = { id: string; name: string };

export default function IndicatorDefinitionForm({
    teamSlug,
    sectors,
    programmes,
    catalogue,
}: {
    teamSlug: string;
    sectors: Option[];
    programmes: Option[];
    catalogue: { available: boolean };
}) {
    return (
        <FormSheet
            title="Define an indicator"
            triggerLabel="Define indicator"
            icon={ChartSpline}
            size="xl"
            description="Create a versioned results-chain definition for independent approval before data entry."
            triggerDisabled={!catalogue.available}
            triggerTitle={!catalogue.available ? 'Publish an approved reference-data catalogue before defining indicators.' : undefined}
        >
            <Form
                {...store.form({ current_team: teamSlug })}
                className="grid gap-4 md:grid-cols-2"
                resetOnSuccess
            >
                {({ processing, errors }) => (
                    <>
                        <Field label="Code" error={errors.code}>
                            <Input
                                name="code"
                                required
                                placeholder="M07-OUT-01"
                            />
                        </Field>
                        <Field label="Indicator name" error={errors.name}>
                            <Input name="name" required />
                        </Field>
                        <Field
                            label="Results level"
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
                            />
                        </Field>
                        <Field label="Frequency" error={errors.frequency}>
                            <Select
                                name="frequency"
                                values={[
                                    'monthly',
                                    'quarterly',
                                    'semiannual',
                                    'annual',
                                    'ad_hoc',
                                ]}
                            />
                        </Field>
                        <Field label="Unit" error={errors.unit_of_measure}>
                            <Input
                                name="unit_of_measure"
                                required
                                placeholder="percent, days, count…"
                            />
                        </Field>
                        <Field label="Value type" error={errors.value_type}>
                            <Select
                                name="value_type"
                                values={[
                                    'number',
                                    'percentage',
                                    'currency',
                                    'count',
                                    'text',
                                ]}
                            />
                        </Field>
                        <Field label="Direction" error={errors.direction}>
                            <Select
                                name="direction"
                                values={['increase', 'decrease', 'maintain']}
                            />
                        </Field>
                        <Field label="Sector" error={errors.sector_id}>
                            <OptionSelect
                                name="sector_id"
                                options={sectors}
                                optional
                            />
                        </Field>
                        <Field label="Programme" error={errors.programme_id}>
                            <OptionSelect
                                name="programme_id"
                                options={programmes}
                                optional
                            />
                        </Field>
                        <Field label="Data source" error={errors.data_source}>
                            <Input name="data_source" required />
                        </Field>
                        <Field
                            label="Verification method"
                            error={errors.verification_method}
                        >
                            <Input name="verification_method" required />
                        </Field>
                        <DatePickerField
                            name="effective_from"
                            label="Effective from"
                            error={errors.effective_from}
                        />
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="indicator-description">
                                Description
                            </Label>
                            <textarea
                                id="indicator-description"
                                name="description"
                                required
                                className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            {errors.description && (
                                <p className="text-xs text-destructive">
                                    {errors.description}
                                </p>
                            )}
                        </div>
                        <div className="md:col-span-2">
                            <Button type="submit" disabled={processing}>
                                Create draft indicator
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
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
function Select({ name, values }: { name: string; values: string[] }) {
    return (
        <StaticSearchableSelect
            id={`indicator-${name}`}
            name={name}
            values={values}
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
