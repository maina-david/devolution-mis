import { Form, usePage } from '@inertiajs/react';
import FormSheet from '@/components/form-sheet';
import InputError from '@/components/input-error';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { verify } from '@/routes/monitoring-evaluation/observations';

export default function IndicatorVerificationAction({
    observationId,
    status,
}: {
    observationId: string;
    status?: string;
}) {
    const copy = usePage().props.localization.monitoringResults;

    if (status === 'verified') {
        return null;
    }

    return (
        <FormSheet
            title={copy.verify_observation}
            triggerLabel={copy.review_observation}
            description={copy.verify_observation_description}
        >
            <Form
                {...verify.form({ observation: observationId })}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <StaticSearchableSelect
                            id="verification-status"
                            name="verification_status"
                            values={[
                                'verified',
                                'clarification_requested',
                                'rejected',
                            ]}
                            labels={copy}
                            error={errors.verification_status}
                        />
                        <StaticSearchableSelect
                            id="quality-status"
                            name="quality_status"
                            values={['accepted', 'warning', 'rejected']}
                            labels={copy}
                            error={errors.quality_status}
                        />
                        <div className="flex gap-2">
                            <Input
                                name="rationale"
                                required
                                placeholder={copy.verification_rationale}
                                aria-invalid={Boolean(errors.rationale)}
                                aria-describedby={
                                    errors.rationale
                                        ? 'verification-rationale-error'
                                        : undefined
                                }
                            />
                            <Button
                                size="sm"
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                {copy.record_decision}
                            </Button>
                        </div>
                        <InputError
                            id="verification-rationale-error"
                            message={errors.rationale}
                        />
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
