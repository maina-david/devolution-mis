import { Form, usePage } from '@inertiajs/react';
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
import { interpolate } from '@/hooks/use-localization';
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
    projectId,
    indicators,
    counties,
}: {
    projectId: string;
    indicators: Indicator[];
    counties: SearchableSelectOption[];
}) {
    const copy = usePage().props.localization.projects;
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
            title={copy.submit_project_progress}
            triggerLabel={copy.submit_progress_update}
            icon={ChartNoAxesCombined}
            size="xl"
            description={copy.submit_progress_description}
        >
            <Form
                {...storeProgress.form({ project: projectId })}
                className="flex flex-col gap-5"
                resetOnSuccess
                onSuccess={() => setResults([])}
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <DatePickerField
                                name="reporting_date"
                                label={copy.reporting_date}
                                required
                                error={errors.reporting_date}
                            />
                            <LabeledInput
                                id="project-physical-progress"
                                name="physical_progress"
                                label={copy.physical_progress_percent}
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
                                label={copy.financial_progress_percent}
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
                                label={copy.source_system}
                                defaultValue="project-report"
                                required
                                error={errors['provenance.source_system']}
                            />
                            <DatePickerField
                                name="provenance[captured_at]"
                                label={copy.captured_at}
                                required
                                includeTime
                                error={errors['provenance.captured_at']}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="project-progress-narrative">
                                {copy.progress_narrative}
                            </Label>
                            <Textarea
                                id="project-progress-narrative"
                                name="narrative"
                                required
                                aria-invalid={Boolean(errors.narrative)}
                                aria-describedby={
                                    errors.narrative
                                        ? 'project-progress-narrative-error'
                                        : undefined
                                }
                            />
                            <FieldError
                                id="project-progress-narrative-error"
                                error={errors.narrative}
                            />
                        </div>

                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h3 className="font-semibold">
                                    {copy.indicator_results}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {copy.indicator_results_help}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={addResult}
                                disabled={indicatorOptions.length === 0}
                            >
                                <Plus data-icon="inline-start" />
                                {copy.add_result}
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
                                            {interpolate(copy.result_number, {
                                                number: index + 1,
                                            })}
                                        </CardTitle>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            aria-label={interpolate(
                                                copy.remove_result,
                                                { number: index + 1 },
                                            )}
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
                                            label={copy.indicator}
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
                                            label={copy.result_county}
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
                                            label={copy.period_start}
                                            required
                                            error={
                                                errors[`${prefix}.period_start`]
                                            }
                                        />
                                        <DatePickerField
                                            name={`indicator_results[${index}][period_end]`}
                                            label={copy.period_end}
                                            required
                                            error={
                                                errors[`${prefix}.period_end`]
                                            }
                                        />
                                        <LabeledInput
                                            id={`project-result-dimension-${result.key}`}
                                            label={
                                                copy.disaggregation_dimension
                                            }
                                            value={result.dimensionName}
                                            onChange={(event) =>
                                                updateResult(result.key, {
                                                    dimensionName:
                                                        event.target.value,
                                                })
                                            }
                                            placeholder={copy.dimension_example}
                                        />
                                        <LabeledInput
                                            id={`project-result-category-${result.key}`}
                                            label={copy.disaggregation_category}
                                            value={result.dimensionValue}
                                            onChange={(event) =>
                                                updateResult(result.key, {
                                                    dimensionValue:
                                                        event.target.value,
                                                })
                                            }
                                            placeholder={copy.category_example}
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
                                            label={
                                                indicator?.unit_of_measure
                                                    ? interpolate(
                                                          copy.result_value_with_unit,
                                                          {
                                                              unit: indicator.unit_of_measure,
                                                          },
                                                      )
                                                    : copy.result_value
                                            }
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
                        <Button
                            type="submit"
                            disabled={processing}
                            aria-busy={processing}
                        >
                            {copy.submit_for_independent_verification}
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
            <Input
                id={id}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                {...props}
            />
            <FieldError id={`${id}-error`} error={error} />
        </div>
    );
}

function FieldError({ id, error }: { id?: string; error?: string }) {
    return error ? (
        <p id={id} role="alert" className="text-sm text-destructive">
            {error}
        </p>
    ) : null;
}
