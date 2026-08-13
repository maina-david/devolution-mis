import { Form, Head, Link, usePage } from '@inertiajs/react';
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
import { show as privacyNotice } from '@/routes/privacy-notice';

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
        issueAnalytics: {
            categories: Array<{ label: string; total: number }>;
            monthlyTrend: Array<{
                month: string;
                total: number;
                resolved: number;
            }>;
            overdue: number;
            averageResolutionHours: number;
            minimumPublishedCount: number;
            satisfaction: SatisfactionAnalytics;
        };
    };
    privacyNoticeVersion: string;
};

export default function CitizenEngagementIndex({
    counties,
    sectors,
    catalogue,
    dashboard,
    privacyNoticeVersion,
}: Props) {
    const copy = usePage().props.localization.citizen;

    return (
        <CitizenEngagementShell>
            <Head title={copy.page_title} />
            <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-10 sm:px-6 lg:py-16">
                <section className="grid items-end gap-8 lg:grid-cols-[1fr_0.7fr]">
                    <div>
                        <p className="text-sm font-semibold tracking-[0.14em] text-primary uppercase">
                            {copy.eyebrow}
                        </p>
                        <h1 className="mt-4 max-w-4xl text-4xl font-bold tracking-tight sm:text-5xl">
                            {copy.hero_title}
                        </h1>
                        <p className="mt-5 max-w-2xl text-lg text-muted-foreground">
                            {copy.hero_description}
                        </p>
                        <div className="mt-7 flex flex-wrap gap-3">
                            <IntakeSheet
                                counties={counties}
                                sectors={sectors}
                                catalogue={catalogue}
                                privacyNoticeVersion={privacyNoticeVersion}
                                copy={copy}
                            />
                            <TrackingSheet copy={copy} />
                        </div>
                        {!catalogue.available && (
                            <Alert className="mt-5" variant="destructive">
                                <LockKeyhole aria-hidden="true" />
                                <AlertTitle>
                                    {copy.intake_unavailable_title}
                                </AlertTitle>
                                <AlertDescription>
                                    {copy.intake_unavailable_description}
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>
                    <Alert>
                        <LockKeyhole aria-hidden="true" />
                        <AlertTitle>{copy.private_code_title}</AlertTitle>
                        <AlertDescription>
                            {copy.private_code_description}
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
                                {copy.dashboard_title}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {copy.dashboard_description}
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Metric
                            label={copy.cases_received}
                            value={dashboard.total}
                        />
                        <Metric
                            label={copy.resolved}
                            value={dashboard.resolved}
                        />
                        <Metric
                            label={copy.pending}
                            value={dashboard.pending}
                        />
                        <Metric
                            label={copy.average_satisfaction}
                            value={
                                dashboard.satisfaction
                                    ? `${dashboard.satisfaction} / 5`
                                    : copy.not_yet_rated
                            }
                        />
                    </div>
                </section>
                <section className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Accessibility aria-hidden="true" />
                                {copy.accessible_participation}
                            </CardTitle>
                            <CardDescription>
                                {copy.support_prompt}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            {copy.accessibility_description}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>{copy.recurring_signals}</CardTitle>
                            <CardDescription>
                                {copy.recurring_description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {dashboard.issueAnalytics.categories.length ? (
                                <ul className="flex flex-col gap-2">
                                    {dashboard.issueAnalytics.categories.map(
                                        (issue) => (
                                            <li
                                                key={issue.label}
                                                className="flex justify-between gap-3 text-sm"
                                            >
                                                <span className="capitalize">
                                                    {copy[issue.label] ??
                                                        issue.label}
                                                </span>
                                                <strong>{issue.total}</strong>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {copy.no_recurring_signals}
                                </p>
                            )}
                            <p className="mt-4 text-xs text-muted-foreground">
                                {copy.recurring_privacy_threshold.replace(
                                    ':count',
                                    String(
                                        dashboard.issueAnalytics
                                            .minimumPublishedCount,
                                    ),
                                )}
                            </p>
                        </CardContent>
                    </Card>
                </section>
                <SatisfactionInsights
                    analytics={dashboard.issueAnalytics.satisfaction}
                    copy={copy}
                />
            </div>
        </CitizenEngagementShell>
    );
}

type SatisfactionAnalytics = {
    responses: number | null;
    responseRate: number | null;
    averageRating: number | null;
    distribution: Array<{ rating: number; total: number }>;
    byCategory: Array<{
        label: string;
        responses: number;
        averageRating: number;
    }>;
    byChannel: Array<{
        label: string;
        responses: number;
        averageRating: number;
    }>;
    resolutionTimeCorrelation: {
        samples: number | null;
        coefficient: number | null;
    };
};

function SatisfactionInsights({
    analytics,
    copy,
}: {
    analytics: SatisfactionAnalytics;
    copy: Record<string, string>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{copy.satisfaction_insights}</CardTitle>
                <CardDescription>
                    {copy.satisfaction_insights_description}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {analytics.responses === null ? (
                    <p className="text-sm text-muted-foreground">
                        {copy.insufficient_satisfaction_data}
                    </p>
                ) : (
                    <div className="grid gap-5 md:grid-cols-3">
                        <Metric
                            label={copy.rating_responses}
                            value={analytics.responses}
                        />
                        <Metric
                            label={copy.rating_response_rate}
                            value={`${analytics.responseRate}%`}
                        />
                        <Metric
                            label={copy.resolution_rating_correlation}
                            value={
                                analytics.resolutionTimeCorrelation
                                    .coefficient ?? '—'
                            }
                        />
                    </div>
                )}
            </CardContent>
        </Card>
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
    privacyNoticeVersion,
    copy,
}: {
    counties: CountyIdentityValue[];
    sectors: Option[];
    catalogue: Props['catalogue'];
    privacyNoticeVersion: string;
    copy: Record<string, string>;
}) {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button size="lg" disabled={!catalogue.available}>
                    <MessageSquarePlus aria-hidden="true" />
                    {copy.submit_action}
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>{copy.submit_title}</SheetTitle>
                    <SheetDescription>
                        {copy.submit_description}
                        {catalogue.available && catalogue.version
                            ? ` ${copy.catalogue_version.replace(':version', String(catalogue.version))}`
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
                                label={copy.case_type}
                                error={errors.case_type}
                                options={[
                                    {
                                        id: 'feedback',
                                        name: copy.citizen_feedback,
                                    },
                                    {
                                        id: 'grievance',
                                        name: copy.formal_grievance,
                                    },
                                ]}
                                defaultValue="feedback"
                            />
                            <LabeledSelect
                                id="category"
                                name="category"
                                label={copy.category}
                                error={errors.category}
                                options={[
                                    { id: 'complaint', name: copy.complaint },
                                    { id: 'suggestion', name: copy.suggestion },
                                    { id: 'compliment', name: copy.compliment },
                                    { id: 'inquiry', name: copy.inquiry },
                                    { id: 'grievance', name: copy.grievance },
                                ]}
                                defaultValue="complaint"
                            />
                            <input type="hidden" name="channel" value="web" />
                            <LabeledSelect
                                id="county_id"
                                name="county_id"
                                label={copy.county}
                                error={errors.county_id}
                                options={counties}
                            />
                            <LabeledSelect
                                id="sector_id"
                                name="sector_id"
                                label={copy.sector_optional}
                                error={errors.sector_id}
                                options={sectors}
                                optional
                            />
                            <LabeledInput
                                id="subject"
                                name="subject"
                                label={copy.subject}
                                error={errors.subject}
                                required
                            />
                            <LabeledTextarea
                                id="description"
                                name="description"
                                label={copy.description}
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
                                        {copy.anonymous}
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        {copy.anonymous_help}
                                    </p>
                                </div>
                            </div>
                            <LabeledInput
                                id="citizen_name"
                                name="citizen_name"
                                label={copy.citizen_name}
                                error={errors.citizen_name}
                            />
                            <LabeledInput
                                id="citizen_email"
                                name="citizen_email"
                                type="email"
                                label={copy.email_optional}
                                error={errors.citizen_email}
                            />
                            <LabeledInput
                                id="citizen_phone"
                                name="citizen_phone"
                                type="tel"
                                label={copy.phone_optional}
                                error={errors.citizen_phone}
                            />
                            <LabeledSelect
                                id="preferred_contact"
                                name="preferred_contact"
                                label={copy.preferred_contact}
                                error={errors.preferred_contact}
                                options={[
                                    { id: 'none', name: copy.no_contact },
                                    { id: 'email', name: copy.email },
                                    { id: 'sms', name: 'SMS' },
                                    { id: 'phone', name: copy.phone_call },
                                ]}
                                defaultValue="none"
                            />
                            <LabeledTextarea
                                id="accessibility_needs"
                                name="accessibility_needs"
                                label={copy.accessibility_optional}
                                error={errors.accessibility_needs}
                            />
                            <LabeledSelect
                                id="source_type"
                                name="source_type"
                                label={copy.attachment_source}
                                error={errors.source_type}
                                options={[
                                    {
                                        id: 'born_digital',
                                        name: copy.born_digital,
                                    },
                                    {
                                        id: 'scanned',
                                        name: copy.scanned,
                                    },
                                ]}
                                defaultValue="born_digital"
                            />
                            <LabeledInput
                                id="attachment"
                                name="attachment"
                                type="file"
                                label={copy.supporting_document}
                                error={errors.attachment}
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.doc,.docx"
                            />
                            <input
                                type="hidden"
                                name="privacy_notice_version"
                                value={privacyNoticeVersion}
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
                                        {copy.consent}
                                    </Label>
                                    <p
                                        id="consent-description"
                                        className="text-xs text-muted-foreground"
                                    >
                                        {copy.consent_description}{' '}
                                        <Link
                                            href={privacyNotice()}
                                            target="_blank"
                                            className="font-medium text-foreground underline underline-offset-4"
                                        >
                                            {copy.read_privacy_notice}
                                        </Link>
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
                                    aria-label={copy.upload_progress}
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
                                    ? copy.submitting
                                    : copy.submit_securely}
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

function TrackingSheet({ copy }: { copy: Record<string, string> }) {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button size="lg" variant="outline">
                    <Search aria-hidden="true" />
                    {copy.track_action}
                </Button>
            </SheetTrigger>
            <SheetContent>
                <SheetHeader>
                    <SheetTitle>{copy.track_title}</SheetTitle>
                    <SheetDescription>
                        {copy.track_description}
                    </SheetDescription>
                </SheetHeader>
                <Form action={track()} className="flex flex-col gap-5 px-4">
                    {({ errors, processing }) => (
                        <>
                            <LabeledInput
                                id="tracking-reference"
                                name="reference"
                                label={copy.case_reference}
                                error={errors.reference}
                                required
                            />
                            <LabeledInput
                                id="tracking-token"
                                name="tracking_token"
                                label={copy.tracking_code}
                                error={errors.tracking_token}
                                required
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                {copy.open_status}
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
