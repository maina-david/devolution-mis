import { Form } from '@inertiajs/react';
import { BriefcaseBusiness } from 'lucide-react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import ReferenceCatalogSelect from '@/components/reference-catalog-select';
import SearchableMultiSelect from '@/components/searchable-multi-select';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/projects';

type Option = { id: string; name: string; code?: string };
export default function ProjectInitiationForm({
    counties,
    sectors,
    programmes,
    organizations,
    indicators,
}: {
    counties: Option[];
    sectors: Option[];
    programmes: Option[];
    organizations: Option[];
    indicators: Option[];
}) {
    return (
        <FormSheet
            title="Initiate a project"
            triggerLabel="Initiate project"
            icon={BriefcaseBusiness}
            size="xl"
            description="Start the published project lifecycle and record county, sector, investment, climate, budget and M&E scope."
        >
            <Form
                {...store.form({})}
                className="grid gap-4 md:grid-cols-2"
                resetOnSuccess
            >
                {({ processing, errors }) => (
                    <>
                        <Field label="Project code" error={errors.code}>
                            <Input
                                name="code"
                                required
                                placeholder="PIM-2026-001"
                            />
                        </Field>
                        <Field label="Title" error={errors.title}>
                            <Input name="title" required />
                        </Field>
                        <Field label="Sector" error={errors.sector_id}>
                            <Select name="sector_id" options={sectors} />
                        </Field>
                        <Field label="Programme" error={errors.programme_id}>
                            <Select
                                name="programme_id"
                                options={programmes}
                                optional
                            />
                        </Field>
                        <Field
                            label="Lead county"
                            error={errors.lead_county_id}
                        >
                            <Select name="lead_county_id" options={counties} />
                        </Field>
                        <SearchableMultiSelect
                            name="county_ids[]"
                            label="Participating counties"
                            options={counties}
                            error={errors.county_ids}
                        />
                        <Field
                            label="Funding organization"
                            error={errors.funding_organization_id}
                        >
                            <Select
                                name="funding_organization_id"
                                options={organizations}
                                optional
                            />
                        </Field>
                        <SearchableMultiSelect
                            name="indicator_ids[]"
                            label="M&E indicators"
                            options={indicators.map((item) => ({
                                id: item.id,
                                name: `${item.code ? `${item.code} · ` : ''}${item.name}`,
                            }))}
                            error={errors.indicator_ids}
                            optional
                        />
                        <DatePickerField
                            name="planned_start_date"
                            label="Planned start"
                            error={errors.planned_start_date}
                            required
                        />
                        <DatePickerField
                            name="planned_end_date"
                            label="Planned end"
                            error={errors.planned_end_date}
                            required
                        />
                        <Field
                            label="Approved budget"
                            error={errors.approved_budget}
                        >
                            <Input
                                name="approved_budget"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                            />
                        </Field>
                        <ReferenceCatalogSelect
                            id="project-currency"
                            name="currency"
                            label="Currency"
                            catalog="currency"
                            error={errors.currency}
                        />
                        <Field
                            label="Investment registry reference"
                            error={errors.investment_registry_reference}
                        >
                            <Input name="investment_registry_reference" />
                        </Field>
                        <Field
                            label="Funding source"
                            error={errors.funding_source}
                        >
                            <Input name="funding_source" />
                        </Field>
                        <Field
                            label="Climate risk rating"
                            error={errors['climate_risk_screening.rating']}
                        >
                            <SearchableSelect
                                id="project-climate-rating"
                                name="climate_risk_screening[rating]"
                                label=""
                                defaultValue="moderate"
                                options={[
                                    { id: 'low', name: 'Low' },
                                    { id: 'moderate', name: 'Moderate' },
                                    { id: 'high', name: 'High' },
                                ]}
                            />
                        </Field>
                        <Field
                            label="Climate screening notes"
                            error={errors['climate_risk_screening.notes']}
                        >
                            <Input name="climate_risk_screening[notes]" />
                        </Field>
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Description</Label>
                            <textarea
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
                                Initiate governed project
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
function Select({
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
            id={`project-${name}`}
            name={name}
            label=""
            options={options}
            optional={optional}
        />
    );
}
