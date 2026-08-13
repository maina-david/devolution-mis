import { Form, usePage } from '@inertiajs/react';
import { ListFilter } from 'lucide-react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/assessments/analytics';

type Option = { id: string; name: string; logoUrl?: string | null };

export default function AssessmentAnalyticsFilters({
    filters,
    cycles,
    counties,
}: {
    filters: {
        from: string | null;
        to: string | null;
        cycle_id: string | null;
        county_id: string | null;
    };
    cycles: Option[];
    counties: Option[];
}) {
    const copy = usePage().props.localization.assessmentAnalytics;

    return (
        <FormSheet
            title={copy.filter_title}
            description={copy.filter_description}
            triggerLabel={copy.filter_analysis}
            icon={ListFilter}
        >
            <Form {...index.form()} className="flex flex-col gap-4">
                {({ processing, errors }) => (
                    <>
                        <DatePickerField
                            name="from"
                            label={copy.published_from}
                            defaultValue={filters.from ?? ''}
                            error={errors.from}
                        />
                        <DatePickerField
                            name="to"
                            label={copy.published_to}
                            defaultValue={filters.to ?? ''}
                            error={errors.to}
                        />
                        <SearchableSelect
                            id="analytics-cycle"
                            name="cycle_id"
                            label={copy.assessment_cycle}
                            options={cycles}
                            defaultValue={filters.cycle_id ?? ''}
                            optional
                            error={errors.cycle_id}
                        />
                        <SearchableSelect
                            id="analytics-county"
                            name="county_id"
                            label={copy.county}
                            options={counties}
                            defaultValue={filters.county_id ?? ''}
                            optional
                            error={errors.county_id}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={processing}>
                                {copy.apply_filters}
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <a href={index.url()}>{copy.clear}</a>
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
