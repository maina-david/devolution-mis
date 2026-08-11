import { Form, Head, Link } from '@inertiajs/react';
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
    return (
        <CitizenEngagementShell>
            <Head title={`Track ${citizenCase.reference}`} />
            <div className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-12 sm:px-6">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {citizenCase.status.replaceAll('_', ' ')}
                    </Badge>
                    <Badge variant="secondary">{citizenCase.type}</Badge>
                    <Badge variant="secondary">{citizenCase.category}</Badge>
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
                            Case status
                        </CardTitle>
                        <CardDescription>
                            Target resolution date:{' '}
                            {new Date(
                                citizenCase.resolutionDueAt,
                            ).toLocaleString()}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {citizenCase.resolutionSummary ? (
                            <div>
                                <p className="font-medium">Resolution</p>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {citizenCase.resolutionSummary}
                                </p>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                The responsible team is processing this case.
                                Public updates appear below.
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MessageSquare aria-hidden="true" />
                            Public updates
                        </CardTitle>
                        <CardDescription>
                            Internal investigation notes are never displayed
                            here.
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
                                                    ? 'Your submission'
                                                    : 'Official response'}
                                            </span>
                                            <time dateTime={message.postedAt}>
                                                {new Date(
                                                    message.postedAt,
                                                ).toLocaleString()}
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
                                No public updates have been posted.
                            </p>
                        )}
                    </CardContent>
                </Card>
                {['resolved', 'closed'].includes(citizenCase.status) &&
                    !citizenCase.satisfactionRating && <RatingSheet />}
                <Button asChild variant="outline">
                    <Link href={index()}>Return to citizen engagement</Link>
                </Button>
            </div>
        </CitizenEngagementShell>
    );
}

function RatingSheet() {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button>
                    <Star aria-hidden="true" />
                    Rate this resolution
                </Button>
            </SheetTrigger>
            <SheetContent>
                <SheetHeader>
                    <SheetTitle>Rate your experience</SheetTitle>
                    <SheetDescription>
                        Your aggregate rating helps identify satisfaction
                        trends.
                    </SheetDescription>
                </SheetHeader>
                <Form action={rate()} className="flex flex-col gap-5 px-4">
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="satisfaction_rating">
                                    Rating from 1 to 5
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
                                    Comment (optional)
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
                                Submit rating
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
