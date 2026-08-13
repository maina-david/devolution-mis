import { Form, usePage } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { CountyIdentityValue } from '@/components/county-identity';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { interpolate } from '@/hooks/use-localization';
import { store } from '@/routes/assessments';

type AssessmentCreationOptions = {
    counties: CountyIdentityValue[];
    cycles: Array<{ id: string; name: string }>;
    pairs: Array<{ countyId: string; cycleId: string }>;
};

export default function AssessmentCreateForm({
    options,
}: {
    options: AssessmentCreationOptions;
}) {
    const copy = usePage().props.localization.assessmentRecord;
    const [countyId, setCountyId] = useState('');
    const [cycleId, setCycleId] = useState('');
    const unavailable = options.pairs.length === 0;
    const eligibleCycleIds = useMemo(
        () =>
            new Set(
                options.pairs
                    .filter((pair) => !countyId || pair.countyId === countyId)
                    .map((pair) => pair.cycleId),
            ),
        [countyId, options.pairs],
    );
    const cycles = options.cycles.filter((cycle) =>
        eligibleCycleIds.has(cycle.id),
    );

    const selectCounty = (nextCountyId: string) => {
        setCountyId(nextCountyId);

        if (
            cycleId &&
            !options.pairs.some(
                (pair) =>
                    pair.countyId === nextCountyId && pair.cycleId === cycleId,
            )
        ) {
            setCycleId('');
        }
    };

    return (
        <FormSheet
            title={copy.initiate_county_assessment}
            description={copy.initiate_county_assessment_description}
            triggerLabel={copy.initiate_assessment}
            triggerDisabled={unavailable}
            triggerTitle={
                unavailable ? copy.no_available_assessment_cycle : undefined
            }
            icon={ClipboardCheck}
        >
            <Form {...store.form({})} className="grid gap-5" resetOnSuccess>
                {({ processing, errors }) => (
                    <>
                        <SearchableSelect
                            id="assessment-county"
                            name="county_id"
                            label={copy.county}
                            error={errors.county_id}
                            value={countyId}
                            onValueChange={selectCounty}
                            options={options.counties.map((county) => ({
                                id: county.id,
                                name: interpolate(copy.county_option, {
                                    county: county.name,
                                    code: String(county.code).padStart(3, '0'),
                                }),
                                logoUrl: county.logoUrl,
                            }))}
                        />
                        <SearchableSelect
                            id="assessment-cycle"
                            name="assessment_cycle_id"
                            label={copy.assessment_cycle}
                            error={errors.assessment_cycle_id}
                            value={cycleId}
                            onValueChange={setCycleId}
                            options={cycles}
                        />
                        <p className="text-sm leading-6 text-muted-foreground">
                            {copy.assessment_creation_lineage}
                        </p>
                        <Button
                            type="submit"
                            disabled={processing || !countyId || !cycleId}
                            aria-busy={processing}
                        >
                            {processing
                                ? copy.initiating_assessment
                                : copy.initiate_assessment}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
