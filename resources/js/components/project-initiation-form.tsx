import { Form, usePage } from '@inertiajs/react';
import { BriefcaseBusiness } from 'lucide-react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import InputError from '@/components/input-error';
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
    const copy = usePage().props.localization.projects;

    return (
        <FormSheet
            title={copy.initiate_project}
            triggerLabel={copy.initiate_project}
            icon={BriefcaseBusiness}
            size="xl"
            description={copy.initiate_project_description}
        >
            <Form
                {...store.form({})}
                className="grid gap-4 md:grid-cols-2"
                resetOnSuccess
            >
                {({ processing, errors }) => (
                    <>
                        <Field label={copy.project_code} error={errors.code}>
                            <Input
                                name="code"
                                required
                                placeholder="PIM-2026-001"
                            />
                        </Field>
                        <Field label={copy.title} error={errors.title}>
                            <Input name="title" required />
                        </Field>
                        <Field label={copy.sector} error={errors.sector_id}>
                            <Select name="sector_id" options={sectors} />
                        </Field>
                        <Field
                            label={copy.programme}
                            error={errors.programme_id}
                        >
                            <Select
                                name="programme_id"
                                options={programmes}
                                optional
                            />
                        </Field>
                        <Field
                            label={copy.lead_county}
                            error={errors.lead_county_id}
                        >
                            <Select name="lead_county_id" options={counties} />
                        </Field>
                        <SearchableMultiSelect
                            name="county_ids[]"
                            label={copy.participating_counties}
                            options={counties}
                            error={errors.county_ids}
                        />
                        <Field
                            label={copy.funding_organization}
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
                            label={copy.me_indicators}
                            options={indicators.map((item) => ({
                                id: item.id,
                                name: `${item.code ? `${item.code} · ` : ''}${item.name}`,
                            }))}
                            error={errors.indicator_ids}
                            optional
                        />
                        <DatePickerField
                            name="planned_start_date"
                            label={copy.planned_start}
                            error={errors.planned_start_date}
                            required
                        />
                        <DatePickerField
                            name="planned_end_date"
                            label={copy.planned_end}
                            error={errors.planned_end_date}
                            required
                        />
                        <Field
                            label={copy.approved_budget}
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
                            label={copy.currency}
                            catalog="currency"
                            error={errors.currency}
                        />
                        <Field
                            label={copy.investment_registry_reference}
                            error={errors.investment_registry_reference}
                        >
                            <Input name="investment_registry_reference" />
                        </Field>
                        <Field
                            label={copy.funding_source}
                            error={errors.funding_source}
                        >
                            <Input name="funding_source" />
                        </Field>
                        <Field
                            label={copy.climate_risk_rating}
                            error={errors['climate_risk_screening.rating']}
                        >
                            <SearchableSelect
                                id="project-climate-rating"
                                name="climate_risk_screening[rating]"
                                label=""
                                defaultValue="moderate"
                                options={[
                                    { id: 'low', name: copy.low },
                                    { id: 'moderate', name: copy.moderate },
                                    { id: 'high', name: copy.high },
                                ]}
                            />
                        </Field>
                        <Field
                            label={copy.climate_screening_notes}
                            error={errors['climate_risk_screening.notes']}
                        >
                            <Input name="climate_risk_screening[notes]" />
                        </Field>
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="project-description">
                                {copy.description}
                            </Label>
                            <textarea
                                id="project-description"
                                name="description"
                                required
                                aria-invalid={Boolean(errors.description)}
                                aria-describedby={
                                    errors.description
                                        ? 'project-description-error'
                                        : undefined
                                }
                                className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                id="project-description-error"
                                message={errors.description}
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                {copy.initiate_governed_project}
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
