import { Form, usePage } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';
import DatePickerField from '@/components/date-picker-field';
import type { IndicatorDefinitionItem } from '@/components/indicator-definition-register';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { supersede } from '@/routes/monitoring-evaluation/indicators';

export default function IndicatorSupersessionSheet({
    indicator,
    open,
    onOpenChange,
}: {
    indicator: IndicatorDefinitionItem | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const copy = usePage().props.localization.indicatorDefinitions;
    const optionLabels = {
        input: copy.input,
        activity: copy.activity,
        output: copy.output,
        outcome: copy.outcome,
        impact: copy.impact,
        number: copy.number,
        percentage: copy.percentage,
        currency: copy.currency,
        count: copy.count,
        text: copy.text,
        increase: copy.increase,
        decrease: copy.decrease,
        maintain: copy.maintain,
        monthly: copy.monthly,
        quarterly: copy.quarterly,
        semiannual: copy.semiannual,
        annual: copy.annual,
        ad_hoc: copy.ad_hoc,
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="overflow-y-auto sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>
                        {copy.supersede_title
                            .replace(':code', indicator?.code ?? '')
                            .replace(
                                ':version',
                                String(indicator?.version ?? ''),
                            )}
                    </SheetTitle>
                    <SheetDescription>
                        {copy.supersede_description}
                    </SheetDescription>
                </SheetHeader>
                {indicator && (
                    <Form
                        {...supersede.form({ indicator: indicator.id })}
                        className="flex flex-col gap-4 px-4 pb-8"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <FormField
                                    label={copy.indicator_name}
                                    htmlFor="supersession-name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="supersession-name"
                                        name="name"
                                        defaultValue={indicator.name}
                                        required
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                </FormField>
                                <FormField
                                    label={copy.results_level}
                                    htmlFor="supersession-results-level"
                                    error={errors.results_level}
                                >
                                    <StaticSearchableSelect
                                        name="results_level"
                                        id="supersession-results-level"
                                        defaultValue={indicator.resultsLevel}
                                        values={[
                                            'input',
                                            'activity',
                                            'output',
                                            'outcome',
                                            'impact',
                                        ]}
                                        labels={optionLabels}
                                    />
                                </FormField>
                                <FormField
                                    label={copy.unit_of_measure}
                                    htmlFor="supersession-unit"
                                    error={errors.unit_of_measure}
                                >
                                    <Input
                                        id="supersession-unit"
                                        name="unit_of_measure"
                                        defaultValue={indicator.unitOfMeasure}
                                        required
                                        aria-invalid={Boolean(
                                            errors.unit_of_measure,
                                        )}
                                    />
                                </FormField>
                                <FormField
                                    label={copy.value_type}
                                    htmlFor="supersession-value-type"
                                    error={errors.value_type}
                                >
                                    <StaticSearchableSelect
                                        name="value_type"
                                        id="supersession-value-type"
                                        defaultValue={indicator.valueType}
                                        values={[
                                            'number',
                                            'percentage',
                                            'currency',
                                            'count',
                                            'text',
                                        ]}
                                        labels={optionLabels}
                                    />
                                </FormField>
                                <FormField
                                    label={copy.direction}
                                    htmlFor="supersession-direction"
                                    error={errors.direction}
                                >
                                    <StaticSearchableSelect
                                        name="direction"
                                        id="supersession-direction"
                                        defaultValue={indicator.direction}
                                        values={[
                                            'increase',
                                            'decrease',
                                            'maintain',
                                        ]}
                                        labels={optionLabels}
                                    />
                                </FormField>
                                <FormField
                                    label={copy.frequency}
                                    htmlFor="supersession-frequency"
                                    error={errors.frequency}
                                >
                                    <StaticSearchableSelect
                                        name="frequency"
                                        id="supersession-frequency"
                                        defaultValue={indicator.frequency}
                                        values={[
                                            'monthly',
                                            'quarterly',
                                            'semiannual',
                                            'annual',
                                            'ad_hoc',
                                        ]}
                                        labels={optionLabels}
                                    />
                                </FormField>
                                <DatePickerField
                                    name="effective_from"
                                    label={copy.effective_from}
                                    error={errors.effective_from}
                                />
                                <FormField
                                    label={copy.definition_description}
                                    htmlFor="supersession-description"
                                    error={errors.description}
                                >
                                    <Textarea
                                        id="supersession-description"
                                        name="description"
                                        defaultValue={indicator.description}
                                        required
                                        aria-invalid={Boolean(
                                            errors.description,
                                        )}
                                    />
                                </FormField>
                                <FormField
                                    label={copy.data_source}
                                    htmlFor="supersession-data-source"
                                    error={errors.data_source}
                                >
                                    <Textarea
                                        id="supersession-data-source"
                                        name="data_source"
                                        defaultValue={indicator.dataSource}
                                        required
                                        aria-invalid={Boolean(
                                            errors.data_source,
                                        )}
                                    />
                                </FormField>
                                <FormField
                                    label={copy.verification_method}
                                    htmlFor="supersession-verification"
                                    error={errors.verification_method}
                                >
                                    <Textarea
                                        id="supersession-verification"
                                        name="verification_method"
                                        defaultValue={
                                            indicator.verificationMethod
                                        }
                                        required
                                        aria-invalid={Boolean(
                                            errors.verification_method,
                                        )}
                                    />
                                </FormField>
                                <FormField
                                    label={copy.change_summary}
                                    htmlFor="supersession-change-summary"
                                    error={errors.change_summary}
                                >
                                    <Textarea
                                        id="supersession-change-summary"
                                        name="change_summary"
                                        required
                                        aria-invalid={Boolean(
                                            errors.change_summary,
                                        )}
                                        placeholder={copy.change_summary_help}
                                    />
                                </FormField>
                                <Button type="submit" disabled={processing}>
                                    <GitBranch data-icon="inline-start" />
                                    {copy.create_successor_draft}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </SheetContent>
        </Sheet>
    );
}

function FormField({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {error && (
                <p className="text-sm text-destructive" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}
