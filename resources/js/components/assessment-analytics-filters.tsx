import { Form } from '@inertiajs/react';
import { ListFilter } from 'lucide-react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/assessments/analytics';

type Option = { id: string; name: string; logoUrl?: string | null };

export default function AssessmentAnalyticsFilters({
    teamSlug,
    filters,
    cycles,
    counties,
}: {
    teamSlug: string;
    filters: {
        from: string | null;
        to: string | null;
        cycle_id: string | null;
        county_id: string | null;
    };
    cycles: Option[];
    counties: Option[];
}) {
    return (
        <FormSheet
            title="Filter assessment analytics"
            description="Limit immutable published results by publication date, assessment cycle or an authorized county."
            triggerLabel="Filter analysis"
            icon={ListFilter}
        >
            <Form {...index.form(teamSlug)} className="flex flex-col gap-4">
                {({ processing, errors }) => (
                    <>
                        <DatePickerField
                            name="from"
                            label="Published from"
                            defaultValue={filters.from ?? ''}
                            error={errors.from}
                        />
                        <DatePickerField
                            name="to"
                            label="Published to"
                            defaultValue={filters.to ?? ''}
                            error={errors.to}
                        />
                        <SearchableSelect
                            id="analytics-cycle"
                            name="cycle_id"
                            label="Assessment cycle"
                            options={cycles}
                            defaultValue={filters.cycle_id ?? ''}
                            optional
                            error={errors.cycle_id}
                        />
                        <SearchableSelect
                            id="analytics-county"
                            name="county_id"
                            label="County"
                            options={counties}
                            defaultValue={filters.county_id ?? ''}
                            optional
                            error={errors.county_id}
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={processing}>
                                Apply filters
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <a href={index.url(teamSlug)}>Clear</a>
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
