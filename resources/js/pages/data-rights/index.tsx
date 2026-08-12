import { Form, Head, Link, usePage } from '@inertiajs/react';
import { FileKey2, LockKeyhole, ShieldCheck } from 'lucide-react';
import { store } from '@/actions/App/Http/Controllers/PublicDataSubjectRequestController';
import InputError from '@/components/input-error';
import SearchableSelect from '@/components/searchable-select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
import PublicLayout from '@/layouts/public-layout';
import { show as privacyNotice } from '@/routes/privacy-notice';

export default function DataRightsIndex({
    noticeVersion,
    targetDays,
}: {
    noticeVersion: string;
    targetDays: number;
}) {
    const copy = usePage().props.localization.dataRights;

    return (
        <PublicLayout>
            <Head title={copy.page_title} />
            <main id="main-content" tabIndex={-1}>
                <div className="mx-auto max-w-6xl px-5 py-10 sm:px-8 lg:py-16">
                    <section className="grid items-end gap-10 border-b pb-10 lg:grid-cols-[1fr_0.65fr]">
                        <div>
                            <p className="text-sm font-semibold tracking-[0.14em] text-primary uppercase">
                                {copy.eyebrow}
                            </p>
                            <h1 className="mt-4 max-w-4xl text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
                                {copy.title}
                            </h1>
                            <p className="mt-5 max-w-3xl text-lg leading-8 text-muted-foreground">
                                {copy.summary}
                            </p>
                            <div className="mt-7 flex flex-wrap gap-3">
                                <RequestSheet
                                    copy={copy}
                                    noticeVersion={noticeVersion}
                                />
                                <Button asChild variant="outline" size="lg">
                                    <Link href={privacyNotice()}>
                                        {copy.read_notice}
                                    </Link>
                                </Button>
                            </div>
                        </div>
                        <Alert>
                            <LockKeyhole aria-hidden="true" />
                            <AlertTitle>{copy.safety_title}</AlertTitle>
                            <AlertDescription>
                                {copy.safety_body}
                            </AlertDescription>
                        </Alert>
                    </section>
                    <section className="grid divide-y border-b md:grid-cols-2 md:divide-x md:divide-y-0">
                        <div className="py-8 md:pr-10">
                            <div className="flex items-center gap-3">
                                <ShieldCheck
                                    className="text-primary"
                                    aria-hidden="true"
                                />
                                <h2 className="text-xl font-semibold">
                                    {copy.process_title}
                                </h2>
                            </div>
                            <p className="mt-4 leading-7 text-muted-foreground">
                                {copy.process_body}
                            </p>
                        </div>
                        <div className="py-8 md:pl-10">
                            <div className="flex items-center gap-3">
                                <FileKey2
                                    className="text-primary"
                                    aria-hidden="true"
                                />
                                <h2 className="text-xl font-semibold">
                                    {copy.target_title}
                                </h2>
                            </div>
                            <p className="mt-4 leading-7 text-muted-foreground">
                                {copy.target_body.replace(
                                    ':days',
                                    String(targetDays),
                                )}
                            </p>
                        </div>
                    </section>
                </div>
            </main>
        </PublicLayout>
    );
}

function RequestSheet({
    copy,
    noticeVersion,
}: {
    copy: Record<string, string>;
    noticeVersion: string;
}) {
    const requestTypes = [
        'access',
        'rectification',
        'erasure',
        'restriction',
        'objection',
        'portability',
    ].map((id) => ({ id, name: copy[id] }));
    const contactChannels = ['email', 'phone', 'letter'].map((id) => ({
        id,
        name: copy[id],
    }));

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button size="lg">
                    <FileKey2 aria-hidden="true" />
                    {copy.submit_request}
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>{copy.submit_title}</SheetTitle>
                    <SheetDescription>
                        {copy.submit_description}
                    </SheetDescription>
                </SheetHeader>
                <Form
                    action={store()}
                    className="grid gap-5 px-4 pb-8"
                    resetOnSuccess
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                name="website"
                                type="text"
                                tabIndex={-1}
                                autoComplete="off"
                                className="hidden"
                                aria-hidden="true"
                            />
                            <SearchableSelect
                                id="request_type"
                                name="request_type"
                                label={copy.request_type}
                                options={requestTypes}
                                defaultValue="access"
                                error={errors.request_type}
                            />
                            <Field
                                id="requester_name"
                                name="requester_name"
                                label={copy.full_name}
                                error={errors.requester_name}
                                autoComplete="name"
                            />
                            <SearchableSelect
                                id="contact_channel"
                                name="contact_channel"
                                label={copy.contact_channel}
                                options={contactChannels}
                                defaultValue="email"
                                error={errors.contact_channel}
                            />
                            <Field
                                id="requester_contact"
                                name="requester_contact"
                                label={copy.contact_value}
                                help={copy.contact_help}
                                error={errors.requester_contact}
                                autoComplete="email"
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="scope">{copy.scope}</Label>
                                <Textarea
                                    id="scope"
                                    name="scope"
                                    required
                                    rows={7}
                                    aria-invalid={Boolean(errors.scope)}
                                    aria-describedby={
                                        errors.scope
                                            ? 'scope-help scope-error'
                                            : 'scope-help'
                                    }
                                />
                                <p
                                    id="scope-help"
                                    className="text-xs text-muted-foreground"
                                >
                                    {copy.scope_help}
                                </p>
                                <InputError
                                    id="scope-error"
                                    message={errors.scope}
                                />
                            </div>
                            <input
                                type="hidden"
                                name="privacy_notice_version"
                                value={noticeVersion}
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
                                            ? 'rights-consent-help rights-consent-error'
                                            : 'rights-consent-help'
                                    }
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="consent_given">
                                        {copy.consent}
                                    </Label>
                                    <p
                                        id="rights-consent-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        {copy.notice_version.replace(
                                            ':version',
                                            noticeVersion,
                                        )}{' '}
                                        <Link
                                            href={privacyNotice()}
                                            target="_blank"
                                            className="font-medium text-foreground underline underline-offset-4"
                                        >
                                            {copy.read_notice}
                                        </Link>
                                    </p>
                                    <InputError
                                        id="rights-consent-error"
                                        message={errors.consent_given}
                                    />
                                </div>
                            </div>
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

function Field({
    id,
    name,
    label,
    help,
    error,
    autoComplete,
}: {
    id: string;
    name: string;
    label: string;
    help?: string;
    error?: string;
    autoComplete?: string;
}) {
    const helpId = help ? `${id}-help` : undefined;
    const errorId = error ? `${id}-error` : undefined;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={name}
                required
                autoComplete={autoComplete}
                aria-invalid={Boolean(error)}
                aria-describedby={
                    [helpId, errorId].filter(Boolean).join(' ') || undefined
                }
            />
            {help && (
                <p id={helpId} className="text-xs text-muted-foreground">
                    {help}
                </p>
            )}
            <InputError id={errorId} message={error} />
        </div>
    );
}
