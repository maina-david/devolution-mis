import { Form, Head, Link, usePage } from '@inertiajs/react';
import { CalendarClock, MessageSquare, Star } from 'lucide-react';
import { rate } from '@/actions/App/Http/Controllers/PublicCitizenCaseController';
import CitizenEngagementShell from '@/components/citizen-engagement-shell';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/citizen-engagement';

type Case = {
    reference: string;
    type: string;
    category: string;
    subject: string;
    county: CountyIdentityValue;
    status: string;
    submittedAt: string | null;
    resolutionDueAt: string;
    resolutionSummary: string | null;
    satisfactionRating: number | null;
    messages: Array<{
        id: string;
        direction: string;
        body: string;
        postedAt: string;
    }>;
};

export default function CitizenCaseTracking({
    case: citizenCase,
}: {
    case: Case;
}) {
    const { current: locale, citizen: copy } = usePage().props.localization;
    const localizedDate = (value: string) =>
        new Date(value).toLocaleString(locale);

    return (
        <CitizenEngagementShell>
            <Head
                title={copy.track_reference_title.replace(
                    ':reference',
                    citizenCase.reference,
                )}
            />
            <div className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-12 sm:px-6">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {copy[citizenCase.status] ??
                            citizenCase.status.replaceAll('_', ' ')}
                    </Badge>
                    <Badge variant="secondary">
                        {citizenCase.type === 'feedback'
                            ? copy.citizen_feedback
                            : copy.formal_grievance}
                    </Badge>
                    <Badge variant="secondary">
                        {copy[citizenCase.category] ?? citizenCase.category}
                    </Badge>
                </div>
                <div>
                    <p className="font-mono text-sm text-muted-foreground">
                        {citizenCase.reference}
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">
                        {citizenCase.subject}
                    </h1>
                    <CountyIdentity
                        county={citizenCase.county}
                        className="mt-4"
                    />
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CalendarClock aria-hidden="true" />
                            {copy.case_status}
                        </CardTitle>
                        <CardDescription>
                            {copy.target_resolution.replace(
                                ':date',
                                localizedDate(citizenCase.resolutionDueAt),
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {citizenCase.resolutionSummary ? (
                            <div>
                                <p className="font-medium">{copy.resolution}</p>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {citizenCase.resolutionSummary}
                                </p>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {copy.processing_case}
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MessageSquare aria-hidden="true" />
                            {copy.public_updates}
                        </CardTitle>
                        <CardDescription>
                            {copy.internal_notes_hidden}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {citizenCase.messages.length ? (
                            <ol className="flex flex-col gap-4">
                                {citizenCase.messages.map((message) => (
                                    <li
                                        key={message.id}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                                            <span>
                                                {message.direction === 'inbound'
                                                    ? copy.your_submission
                                                    : copy.official_response}
                                            </span>
                                            <time dateTime={message.postedAt}>
                                                {localizedDate(
                                                    message.postedAt,
                                                )}
                                            </time>
                                        </div>
                                        <p className="mt-2 text-sm">
                                            {message.body}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {copy.no_public_updates}
                            </p>
                        )}
                    </CardContent>
                </Card>
                {['resolved', 'closed'].includes(citizenCase.status) &&
                    !citizenCase.satisfactionRating && (
                        <RatingSheet copy={copy} />
                    )}
                <Button asChild variant="outline">
                    <Link href={index()}>{copy.return_to_engagement}</Link>
                </Button>
            </div>
        </CitizenEngagementShell>
    );
}

function RatingSheet({ copy }: { copy: Record<string, string> }) {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button>
                    <Star aria-hidden="true" />
                    {copy.rate_resolution}
                </Button>
            </SheetTrigger>
            <SheetContent>
                <SheetHeader>
                    <SheetTitle>{copy.rate_experience}</SheetTitle>
                    <SheetDescription>{copy.rating_help}</SheetDescription>
                </SheetHeader>
                <Form action={rate()} className="flex flex-col gap-5 px-4">
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="satisfaction_rating">
                                    {copy.rating_label}
                                </Label>
                                <Input
                                    id="satisfaction_rating"
                                    name="satisfaction_rating"
                                    type="number"
                                    min="1"
                                    max="5"
                                    required
                                    aria-invalid={Boolean(
                                        errors.satisfaction_rating,
                                    )}
                                    aria-describedby={
                                        errors.satisfaction_rating
                                            ? 'rating-error'
                                            : undefined
                                    }
                                />
                                {errors.satisfaction_rating && (
                                    <p
                                        id="rating-error"
                                        role="alert"
                                        className="text-xs text-destructive"
                                    >
                                        {errors.satisfaction_rating}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="satisfaction_comment">
                                    {copy.comment_optional}
                                </Label>
                                <Textarea
                                    id="satisfaction_comment"
                                    name="satisfaction_comment"
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                {copy.submit_rating}
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
