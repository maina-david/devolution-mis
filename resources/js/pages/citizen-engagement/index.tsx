import { Form, Head } from '@inertiajs/react';
import {
    Accessibility,
    BarChart3,
    LockKeyhole,
    MessageSquarePlus,
    Search,
} from 'lucide-react';
import type { ComponentProps } from 'react';
import {
    store,
    track,
} from '@/actions/App/Http/Controllers/PublicCitizenCaseController';
import CitizenEngagementShell from '@/components/citizen-engagement-shell';
import type { CountyIdentityValue } from '@/components/county-identity';
import SearchableSelect from '@/components/searchable-select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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

type Option = { id: string; name: string };
type Props = {
    counties: CountyIdentityValue[];
    sectors: Option[];
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
    dashboard: {
        total: number;
        resolved: number;
        pending: number;
        satisfaction: string | null;
        recurringIssues: Array<{ category: string; total: number }>;
    };
};

export default function CitizenEngagementIndex({
    counties,
    sectors,
    catalogue,
    dashboard,
}: Props) {
    return (
        <CitizenEngagementShell>
            <Head title="Citizen feedback and grievance redress" />
            <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-10 sm:px-6 lg:py-16">
                <section className="grid items-end gap-8 lg:grid-cols-[1fr_0.7fr]">
                    <div>
                        <p className="text-sm font-semibold tracking-[0.14em] text-primary uppercase">
                            Your voice in devolution
                        </p>
                        <h1 className="mt-4 max-w-4xl text-4xl font-bold tracking-tight sm:text-5xl">
                            Submit feedback or a grievance, then follow every
                            public update.
                        </h1>
                        <p className="mt-5 max-w-2xl text-lg text-muted-foreground">
                            Use an accessible, private channel to report a
                            complaint, suggestion, compliment, inquiry or formal
                            grievance. You may submit anonymously.
                        </p>
                        <div className="mt-7 flex flex-wrap gap-3">
                            <IntakeSheet
                                counties={counties}
                                sectors={sectors}
                                catalogue={catalogue}
                            />
                            <TrackingSheet />
                        </div>
                        {!catalogue.available && (
                            <Alert className="mt-5" variant="destructive">
                                <LockKeyhole aria-hidden="true" />
                                <AlertTitle>
                                    New submissions are temporarily unavailable
                                </AlertTitle>
                                <AlertDescription>
                                    The governed county and sector catalogue is
                                    unavailable or failed integrity validation.
                                    Case tracking and the public accountability
                                    dashboard remain available.
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>
                    <Alert>
                        <LockKeyhole aria-hidden="true" />
                        <AlertTitle>Your tracking code is private</AlertTitle>
                        <AlertDescription>
                            Personal contact details are encrypted. Your receipt
                            provides a one-time tracking code; IDMIS never
                            publishes case-level personal information.
                        </AlertDescription>
                    </Alert>
                </section>
                <section aria-labelledby="public-dashboard-heading">
                    <div className="mb-4 flex items-center gap-3">
                        <BarChart3 aria-hidden="true" />
                        <div>
                            <h2
                                id="public-dashboard-heading"
                                className="text-2xl font-bold"
                            >
                                Public accountability snapshot
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Aggregate resolved and pending cases only.
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Metric
                            label="Cases received"
                            value={dashboard.total}
                        />
                        <Metric label="Resolved" value={dashboard.resolved} />
                        <Metric label="Pending" value={dashboard.pending} />
                        <Metric
                            label="Average satisfaction"
                            value={
                                dashboard.satisfaction
                                    ? `${dashboard.satisfaction} / 5`
                                    : 'Not yet rated'
                            }
                        />
                    </div>
                </section>
                <section className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Accessibility aria-hidden="true" />
                                Accessible participation
                            </CardTitle>
                            <CardDescription>
                                Tell us what support you need.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            The intake form supports keyboard navigation and
                            screen readers. Record sign-language, large-print,
                            call-back or other assistance needs in the
                            accessibility field.
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Recurring issue signals</CardTitle>
                            <CardDescription>
                                Aggregate complaint and grievance categories.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {dashboard.recurringIssues.length ? (
                                <ul className="flex flex-col gap-2">
                                    {dashboard.recurringIssues.map((issue) => (
                                        <li
                                            key={issue.category}
                                            className="flex justify-between gap-3 text-sm"
                                        >
                                            <span className="capitalize">
                                                {issue.category}
                                            </span>
                                            <strong>{issue.total}</strong>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No recurring issue pattern is available yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>
        </CitizenEngagementShell>
    );
}

function Metric({ label, value }: { label: string; value: number | string }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

function IntakeSheet({
    counties,
    sectors,
    catalogue,
}: {
    counties: CountyIdentityValue[];
    sectors: Option[];
    catalogue: Props['catalogue'];
}) {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button size="lg" disabled={!catalogue.available}>
                    <MessageSquarePlus aria-hidden="true" />
                    Submit feedback or grievance
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>Submit a citizen case</SheetTitle>
                    <SheetDescription>
                        Required fields are marked. Do not include passwords,
                        banking PINs or unnecessary sensitive information.
                        {catalogue.available && catalogue.version
                            ? ' County and sector choices use governed catalogue v' +
                              catalogue.version +
                              '.'
                            : ''}
                    </SheetDescription>
                </SheetHeader>
                <Form
                    action={store()}
                    className="flex flex-col gap-5 px-4 pb-8"
                    resetOnSuccess
                >
                    {({ errors, processing, progress }) => (
                        <>
                            <input
                                type="text"
                                name="website"
                                tabIndex={-1}
                                autoComplete="off"
                                className="hidden"
                                aria-hidden="true"
                            />
                            <LabeledSelect
                                id="case_type"
                                name="case_type"
                                label="Case type"
                                error={errors.case_type}
                                options={[
                                    {
                                        id: 'feedback',
                                        name: 'Citizen feedback',
                                    },
                                    {
                                        id: 'grievance',
                                        name: 'Formal grievance',
                                    },
                                ]}
                                defaultValue="feedback"
                            />
                            <LabeledSelect
                                id="category"
                                name="category"
                                label="Category"
                                error={errors.category}
                                options={[
                                    { id: 'complaint', name: 'Complaint' },
                                    { id: 'suggestion', name: 'Suggestion' },
                                    { id: 'compliment', name: 'Compliment' },
                                    { id: 'inquiry', name: 'Inquiry' },
                                    { id: 'grievance', name: 'Grievance' },
                                ]}
                                defaultValue="complaint"
                            />
                            <input type="hidden" name="channel" value="web" />
                            <LabeledSelect
                                id="county_id"
                                name="county_id"
                                label="County"
                                error={errors.county_id}
                                options={counties}
                            />
                            <LabeledSelect
                                id="sector_id"
                                name="sector_id"
                                label="Sector (optional)"
                                error={errors.sector_id}
                                options={sectors}
                                optional
                            />
                            <LabeledInput
                                id="subject"
                                name="subject"
                                label="Subject"
                                error={errors.subject}
                                required
                            />
                            <LabeledTextarea
                                id="description"
                                name="description"
                                label="What happened or what would you like to share?"
                                error={errors.description}
                                required
                            />
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="is_anonymous"
                                    name="is_anonymous"
                                    value="1"
                                />
                                <div>
                                    <Label htmlFor="is_anonymous">
                                        Submit anonymously
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Leave this unchecked if you want a
                                        direct response.
                                    </p>
                                </div>
                            </div>
                            <LabeledInput
                                id="citizen_name"
                                name="citizen_name"
                                label="Your name (required when not anonymous)"
                                error={errors.citizen_name}
                            />
                            <LabeledInput
                                id="citizen_email"
                                name="citizen_email"
                                type="email"
                                label="Email (optional)"
                                error={errors.citizen_email}
                            />
                            <LabeledInput
                                id="citizen_phone"
                                name="citizen_phone"
                                type="tel"
                                label="Phone (optional)"
                                error={errors.citizen_phone}
                            />
                            <LabeledSelect
                                id="preferred_contact"
                                name="preferred_contact"
                                label="Preferred contact"
                                error={errors.preferred_contact}
                                options={[
                                    { id: 'none', name: 'No direct contact' },
                                    { id: 'email', name: 'Email' },
                                    { id: 'sms', name: 'SMS' },
                                    { id: 'phone', name: 'Phone call' },
                                ]}
                                defaultValue="none"
                            />
                            <LabeledTextarea
                                id="accessibility_needs"
                                name="accessibility_needs"
                                label="Accessibility or communication support (optional)"
                                error={errors.accessibility_needs}
                            />
                            <LabeledSelect
                                id="source_type"
                                name="source_type"
                                label="Attachment source"
                                error={errors.source_type}
                                options={[
                                    {
                                        id: 'born_digital',
                                        name: 'Born-digital file',
                                    },
                                    {
                                        id: 'scanned',
                                        name: 'Scanned paper record',
                                    },
                                ]}
                                defaultValue="born_digital"
                            />
                            <LabeledInput
                                id="attachment"
                                name="attachment"
                                type="file"
                                label="Supporting document (optional, max 10 MB)"
                                error={errors.attachment}
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.doc,.docx"
                            />
                            <input
                                type="hidden"
                                name="privacy_notice_version"
                                value="2026-08"
                            />
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="consent_given"
                                    name="consent_given"
                                    value="1"
                                    required
                                    aria-invalid={Boolean(errors.consent_given)}
                                    aria-describedby={
                                        errors.consent_given
                                            ? 'consent-description consent-error'
                                            : 'consent-description'
                                    }
                                />
                                <div>
                                    <Label htmlFor="consent_given">
                                        I consent to case processing
                                    </Label>
                                    <p
                                        id="consent-description"
                                        className="text-xs text-muted-foreground"
                                    >
                                        I understand my information will be used
                                        to route, investigate and respond to
                                        this case under applicable government
                                        records and privacy controls.
                                    </p>
                                    {errors.consent_given && (
                                        <p
                                            id="consent-error"
                                            role="alert"
                                            className="text-xs text-destructive"
                                        >
                                            {errors.consent_given}
                                        </p>
                                    )}
                                </div>
                            </div>
                            {progress && (
                                <progress
                                    value={progress.percentage}
                                    max="100"
                                    aria-label="Upload progress"
                                    className="w-full"
                                >
                                    {progress.percentage}%
                                </progress>
                            )}
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                {processing
                                    ? 'Submitting securely…'
                                    : 'Submit securely'}
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

function TrackingSheet() {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button size="lg" variant="outline">
                    <Search aria-hidden="true" />
                    Track a case
                </Button>
            </SheetTrigger>
            <SheetContent>
                <SheetHeader>
                    <SheetTitle>Track your case</SheetTitle>
                    <SheetDescription>
                        Enter the reference and private code from your receipt.
                    </SheetDescription>
                </SheetHeader>
                <Form action={track()} className="flex flex-col gap-5 px-4">
                    {({ errors, processing }) => (
                        <>
                            <LabeledInput
                                id="tracking-reference"
                                name="reference"
                                label="Case reference"
                                error={errors.reference}
                                required
                            />
                            <LabeledInput
                                id="tracking-token"
                                name="tracking_token"
                                label="Private tracking code"
                                error={errors.tracking_token}
                                required
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                Open case status
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

function LabeledInput({
    id,
    label,
    error,
    ...props
}: ComponentProps<typeof Input> & {
    id: string;
    label: string;
    error?: string;
}) {
    const errorId = `${id}-error`;

    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? errorId : undefined}
                {...props}
            />
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
function LabeledTextarea({
    id,
    label,
    error,
    ...props
}: ComponentProps<typeof Textarea> & {
    id: string;
    label: string;
    error?: string;
}) {
    const errorId = `${id}-error`;

    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Textarea
                id={id}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? errorId : undefined}
                {...props}
            />
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
function LabeledSelect({
    id,
    name,
    label,
    error,
    options,
    optional = false,
    defaultValue,
}: {
    id: string;
    name: string;
    label: string;
    error?: string;
    options: Option[];
    optional?: boolean;
    defaultValue?: string;
}) {
    return (
        <SearchableSelect
            id={id}
            name={name}
            label={label}
            options={options}
            optional={optional}
            defaultValue={defaultValue}
            error={error}
        />
    );
}
