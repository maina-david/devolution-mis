import { Form } from '@inertiajs/react';
import FormSheet from '@/components/form-sheet';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { verify } from '@/routes/monitoring-evaluation/observations';

export default function IndicatorVerificationAction({
    teamSlug,
    observationId,
    status,
}: {
    teamSlug: string;
    observationId: string;
    status?: string;
}) {
    if (status === 'verified') {
        return null;
    }

    return (
        <FormSheet
            title="Verify indicator observation"
            triggerLabel="Review observation"
            description="Record an independent data-quality decision and rationale."
        >
            <Form
                {...verify.form({
                    current_team: teamSlug,
                    observation: observationId,
                })}
                className="grid gap-4"
            >
                {({ processing }) => (
                    <>
                        <StaticSearchableSelect
                            id="verification-status"
                            name="verification_status"
                            values={[
                                'verified',
                                'clarification_requested',
                                'rejected',
                            ]}
                        />
                        <StaticSearchableSelect
                            id="quality-status"
                            name="quality_status"
                            values={['accepted', 'warning', 'rejected']}
                        />
                        <div className="flex gap-2">
                            <Input
                                name="rationale"
                                required
                                placeholder="Verification rationale"
                            />
                            <Button
                                size="sm"
                                type="submit"
                                disabled={processing}
                            >
                                Record
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}
