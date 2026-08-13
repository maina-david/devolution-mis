import { Form, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { approve, review, score, show, submit } from '@/routes/assessments';

type Props = {
    assessmentId: string;
    status?: string;
    capabilities: Record<string, boolean>;
    isLegacy: boolean;
};

export default function AssessmentRowAction({
    assessmentId,
    status,
    capabilities,
    isLegacy,
}: Props) {
    const copy = usePage().props.localization.assessmentRecord;
    const routeArguments = { assessment: assessmentId };

    if (
        capabilities.submit &&
        ['draft', 'evidence_collection'].includes(status ?? '')
    ) {
        return (
            <Form {...submit.form(routeArguments)}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing}
                        aria-busy={processing}
                    >
                        {copy.submit_assessment}
                    </Button>
                )}
            </Form>
        );
    }

    if (capabilities.review && status === 'submitted') {
        return (
            <Form {...review.form(routeArguments)}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing}
                        aria-busy={processing}
                    >
                        {copy.start_review}
                    </Button>
                )}
            </Form>
        );
    }

    if (capabilities.score && status === 'under_assessment' && !isLegacy) {
        return (
            <Button asChild size="sm" variant="outline">
                <Link href={show(routeArguments)}>{copy.open_criteria}</Link>
            </Button>
        );
    }

    if (capabilities.score && status === 'under_assessment' && isLegacy) {
        return (
            <Form
                {...score.form(routeArguments)}
                className="ml-auto flex w-40 gap-2"
            >
                {({ processing, errors }) => (
                    <>
                        <div>
                            <Input
                                name="score"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                aria-label={copy.assessment_score}
                                aria-invalid={Boolean(errors.score)}
                                aria-describedby={
                                    errors.score
                                        ? `assessment-${assessmentId}-score-error`
                                        : undefined
                                }
                                required
                                className="h-8"
                            />
                            <InputError
                                id={`assessment-${assessmentId}-score-error`}
                                message={errors.score}
                            />
                        </div>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing}
                            aria-busy={processing}
                        >
                            {copy.record_legacy_score}
                        </Button>
                    </>
                )}
            </Form>
        );
    }

    if (capabilities.approve && status === 'assessed') {
        return (
            <Form {...approve.form(routeArguments)}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing}
                        aria-busy={processing}
                    >
                        {copy.approve_assessment}
                    </Button>
                )}
            </Form>
        );
    }

    return (
        <span className="text-xs text-muted-foreground">{copy.no_action}</span>
    );
}
