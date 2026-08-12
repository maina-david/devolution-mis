import { Form } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { CountyIdentityValue } from '@/components/county-identity';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
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
            title="Initiate county assessment"
            description="Create one governed assessment for a county and released cycle. The scorecard and effective reference catalogue are pinned automatically."
            triggerLabel="Initiate assessment"
            triggerDisabled={unavailable}
            triggerTitle={
                unavailable
                    ? 'No authorized county and released planned/open cycle are available.'
                    : undefined
            }
            icon={ClipboardCheck}
        >
            <Form {...store.form({})} className="grid gap-5" resetOnSuccess>
                {({ processing, errors }) => (
                    <>
                        <SearchableSelect
                            id="assessment-county"
                            name="county_id"
                            label="County"
                            error={errors.county_id}
                            value={countyId}
                            onValueChange={selectCounty}
                            options={options.counties.map((county) => ({
                                id: county.id,
                                name: `${county.name} · County ${String(county.code).padStart(3, '0')}`,
                                logoUrl: county.logoUrl,
                            }))}
                        />
                        <SearchableSelect
                            id="assessment-cycle"
                            name="assessment_cycle_id"
                            label="Assessment cycle"
                            error={errors.assessment_cycle_id}
                            value={cycleId}
                            onValueChange={setCycleId}
                            options={cycles}
                        />
                        <p className="text-sm leading-6 text-muted-foreground">
                            The county, cycle, released scorecard checksum,
                            effective catalogue version, creator and audit event
                            are retained as creation lineage.
                        </p>
                        <Button
                            type="submit"
                            disabled={processing || !countyId || !cycleId}
                        >
                            {processing
                                ? 'Initiating assessment…'
                                : 'Initiate assessment'}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
