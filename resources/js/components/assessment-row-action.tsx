import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { approve, review, score, submit } from '@/routes/assessments';

type Props = {
    assessmentId: string;
    status?: string;
    teamSlug: string;
    capabilities: Record<string, boolean>;
};

export default function AssessmentRowAction({
    assessmentId,
    status,
    teamSlug,
    capabilities,
}: Props) {
    const routeArguments = {
        current_team: teamSlug,
        assessment: assessmentId,
    };

    if (
        capabilities.submit &&
        ['draft', 'evidence_collection'].includes(status ?? '')
    ) {
        return (
            <Form {...submit.form(routeArguments)}>
                {({ processing }) => (
                    <Button type="submit" size="sm" disabled={processing}>
                        Submit
                    </Button>
                )}
            </Form>
        );
    }

    if (capabilities.review && status === 'submitted') {
        return (
            <Form {...review.form(routeArguments)}>
                {({ processing }) => (
                    <Button type="submit" size="sm" disabled={processing}>
                        Start review
                    </Button>
                )}
            </Form>
        );
    }

    if (capabilities.score && status === 'under_assessment') {
        return (
            <Form
                {...score.form(routeArguments)}
                className="ml-auto flex w-40 gap-2"
            >
                {({ processing }) => (
                    <>
                        <Input
                            name="score"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            aria-label="Assessment score"
                            required
                            className="h-8"
                        />
                        <Button type="submit" size="sm" disabled={processing}>
                            Score
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
                    <Button type="submit" size="sm" disabled={processing}>
                        Approve
                    </Button>
                )}
            </Form>
        );
    }

    return <span className="text-xs text-muted-foreground">No action</span>;
}
