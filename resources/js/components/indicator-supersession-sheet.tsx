import { Form } from '@inertiajs/react';
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
    teamSlug,
    indicator,
    open,
    onOpenChange,
}: {
    teamSlug: string;
    indicator: IndicatorDefinitionItem | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="overflow-y-auto sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>
                        Supersede {indicator?.code} v{indicator?.version}
                    </SheetTitle>
                    <SheetDescription>
                        Create the next draft version. The released definition
                        and its historical observations remain immutable.
                    </SheetDescription>
                </SheetHeader>
                {indicator && (
                    <Form
                        {...supersede.form({
                            current_team: teamSlug,
                            indicator: indicator.id,
                        })}
                        className="flex flex-col gap-4 px-4 pb-8"
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <FormField
                                    label="Indicator name"
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
                                    label="Results level"
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
                                    />
                                </FormField>
                                <FormField
                                    label="Unit of measure"
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
                                    label="Value type"
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
                                    />
                                </FormField>
                                <FormField
                                    label="Direction"
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
                                    />
                                </FormField>
                                <FormField
                                    label="Frequency"
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
                                    />
                                </FormField>
                                <DatePickerField
                                    name="effective_from"
                                    label="Effective from"
                                    error={errors.effective_from}
                                />
                                <FormField
                                    label="Description"
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
                                    label="Data source"
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
                                    label="Verification method"
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
                                    label="Change summary"
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
                                        placeholder="Explain why a new version is required and what changed."
                                    />
                                </FormField>
                                <Button type="submit" disabled={processing}>
                                    <GitBranch data-icon="inline-start" />
                                    Create successor draft
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
