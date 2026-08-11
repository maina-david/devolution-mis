import { Form } from '@inertiajs/react';
import { ChartNoAxesCombined, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import type { SearchableSelectOption } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as storeProgress } from '@/routes/projects/progress-updates';

type Indicator = {
    id: string;
    code: string;
    name: string;
    unit_of_measure: string;
    value_type: string;
    status: string;
};

type ResultRow = {
    key: number;
    indicatorId: string;
    countyId: string;
    dimensionName: string;
    dimensionValue: string;
};

export default function ProjectProgressForm({
    teamSlug,
    projectId,
    indicators,
    counties,
}: {
    teamSlug: string;
    projectId: string;
    indicators: Indicator[];
    counties: SearchableSelectOption[];
}) {
    const [nextKey, setNextKey] = useState(1);
    const [results, setResults] = useState<ResultRow[]>([]);
    const indicatorOptions = indicators.map((indicator) => ({
        id: indicator.id,
        name: `${indicator.code} · ${indicator.name}`,
    }));
    const addResult = () => {
        setResults((current) => [
            ...current,
            {
                key: nextKey,
                indicatorId: '',
                countyId: counties.length === 1 ? counties[0].id : '',
                dimensionName: '',
                dimensionValue: '',
            },
        ]);
        setNextKey((current) => current + 1);
    };
    const updateResult = (key: number, values: Partial<ResultRow>) =>
        setResults((current) =>
            current.map((result) =>
                result.key === key ? { ...result, ...values } : result,
            ),
        );

    return (
        <FormSheet
            title="Submit project progress"
            triggerLabel="Submit progress update"
            icon={ChartNoAxesCombined}
            size="xl"
            description="Submit portfolio progress and optional indicator results. Indicator results enter the M&E quality queue only after independent project verification."
        >
            <Form
                {...storeProgress.form({
                    current_team: teamSlug,
                    project: projectId,
                })}
                className="flex flex-col gap-5"
                resetOnSuccess
                onSuccess={() => setResults([])}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <DatePickerField
                                name="reporting_date"
                                label="Reporting date"
                                required
                                error={errors.reporting_date}
                            />
                            <LabeledInput
                                id="project-physical-progress"
                                name="physical_progress"
                                label="Physical progress (%)"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                error={errors.physical_progress}
                            />
                            <LabeledInput
                                id="project-financial-progress"
                                name="financial_progress"
                                label="Financial progress (%)"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                error={errors.financial_progress}
                            />
                            <LabeledInput
                                id="project-source-system"
                                name="provenance[source_system]"
                                label="Source system"
                                defaultValue="project-report"
                                required
                                error={errors['provenance.source_system']}
                            />
                            <DatePickerField
                                name="provenance[captured_at]"
                                label="Captured at"
                                required
                                includeTime
                                error={errors['provenance.captured_at']}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="project-progress-narrative">
                                Progress narrative
                            </Label>
                            <Textarea
                                id="project-progress-narrative"
                                name="narrative"
                                required
                                aria-invalid={Boolean(errors.narrative)}
                            />
                            <FieldError error={errors.narrative} />
                        </div>

                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h3 className="font-semibold">
                                    Indicator results
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Add a result only when this progress report
                                    contains a measured output or outcome.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={addResult}
                                disabled={indicatorOptions.length === 0}
                            >
                                <Plus data-icon="inline-start" />
                                Add result
                            </Button>
                        </div>

                        {results.map((result, index) => {
                            const indicator = indicators.find(
                                (item) => item.id === result.indicatorId,
                            );
                            const prefix = `indicator_results.${index}`;
                            const dimensionKey =
                                result.dimensionName && result.dimensionValue
                                    ? `${result.dimensionName}:${result.dimensionValue}`
                                    : 'total';

                            return (
                                <Card key={result.key}>
                                    <CardHeader className="flex-row items-center justify-between">
                                        <CardTitle className="text-base">
                                            Result {index + 1}
                                        </CardTitle>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            aria-label={`Remove result ${index + 1}`}
                                            onClick={() =>
                                                setResults((current) =>
                                                    current.filter(
                                                        (item) =>
                                                            item.key !==
                                                            result.key,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 md:grid-cols-2">
                                        <SearchableSelect
                                            id={`project-result-indicator-${result.key}`}
                                            name={`indicator_results[${index}][indicator_definition_id]`}
                                            label="Indicator"
                                            options={indicatorOptions}
                                            value={result.indicatorId}
                                            onValueChange={(indicatorId) =>
                                                updateResult(result.key, {
                                                    indicatorId,
                                                })
                                            }
                                            error={
                                                errors[
                                                    `${prefix}.indicator_definition_id`
                                                ]
                                            }
                                        />
                                        <SearchableSelect
                                            id={`project-result-county-${result.key}`}
                                            name={`indicator_results[${index}][county_id]`}
                                            label="Result county"
                                            options={counties}
                                            value={result.countyId}
                                            onValueChange={(countyId) =>
                                                updateResult(result.key, {
                                                    countyId,
                                                })
                                            }
                                            error={
                                                errors[`${prefix}.county_id`]
                                            }
                                        />
                                        <DatePickerField
                                            name={`indicator_results[${index}][period_start]`}
                                            label="Period start"
                                            required
                                            error={
                                                errors[`${prefix}.period_start`]
                                            }
                                        />
                                        <DatePickerField
                                            name={`indicator_results[${index}][period_end]`}
                                            label="Period end"
                                            required
                                            error={
                                                errors[`${prefix}.period_end`]
                                            }
                                        />
                                        <LabeledInput
                                            id={`project-result-dimension-${result.key}`}
                                            label="Disaggregation dimension"
                                            value={result.dimensionName}
                                            onChange={(event) =>
                                                updateResult(result.key, {
                                                    dimensionName:
                                                        event.target.value,
                                                })
                                            }
                                            placeholder="e.g. sex"
                                        />
                                        <LabeledInput
                                            id={`project-result-category-${result.key}`}
                                            label="Disaggregation category"
                                            value={result.dimensionValue}
                                            onChange={(event) =>
                                                updateResult(result.key, {
                                                    dimensionValue:
                                                        event.target.value,
                                                })
                                            }
                                            placeholder="e.g. female"
                                        />
                                        <input
                                            type="hidden"
                                            name={`indicator_results[${index}][dimension_key]`}
                                            value={dimensionKey}
                                        />
                                        {result.dimensionName &&
                                            result.dimensionValue && (
                                                <input
                                                    type="hidden"
                                                    name={`indicator_results[${index}][disaggregation][${result.dimensionName}]`}
                                                    value={
                                                        result.dimensionValue
                                                    }
                                                />
                                            )}
                                        <LabeledInput
                                            id={`project-result-value-${result.key}`}
                                            name={`indicator_results[${index}][${indicator?.value_type === 'text' ? 'narrative_value' : 'numeric_value'}]`}
                                            label={`Result value${indicator?.unit_of_measure ? ` (${indicator.unit_of_measure})` : ''}`}
                                            type={
                                                indicator?.value_type === 'text'
                                                    ? 'text'
                                                    : 'number'
                                            }
                                            step="any"
                                            required
                                            error={
                                                errors[
                                                    `${prefix}.${indicator?.value_type === 'text' ? 'narrative_value' : 'numeric_value'}`
                                                ]
                                            }
                                        />
                                    </CardContent>
                                </Card>
                            );
                        })}
                        <Button type="submit" disabled={processing}>
                            Submit for independent verification
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function LabeledInput({
    id,
    label,
    error,
    ...props
}: React.ComponentProps<typeof Input> & {
    id: string;
    label: string;
    error?: string;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} aria-invalid={Boolean(error)} {...props} />
            <FieldError error={error} />
        </div>
    );
}

function FieldError({ error }: { error?: string }) {
    return error ? (
        <p role="alert" className="text-sm text-destructive">
            {error}
        </p>
    ) : null;
}
