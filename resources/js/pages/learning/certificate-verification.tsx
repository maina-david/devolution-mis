import { Form, Head } from '@inertiajs/react';
import { Award, CheckCircle2, Search, ShieldAlert } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/public-layout';
import { verify } from '@/routes/learning/certificates';

type Certificate = {
    number: string;
    learner: string;
    courseCode: string;
    courseTitle: string;
    finalScore: string;
    issuedAt: string;
    expiresAt: string | null;
    status: 'valid' | 'expired';
    checksum: string;
};

export default function CertificateVerification({
    query,
    searched,
    certificate,
}: {
    query: string | null;
    searched: boolean;
    certificate: Certificate | null;
}) {
    return (
        <PublicLayout>
            <Head title="Verify learning certificate">
                <meta
                    name="description"
                    content="Verify an IDMIS learning certificate issued by the State Department for Devolution."
                />
            </Head>
            <main id="main-content" tabIndex={-1}>
                <section className="mx-auto grid max-w-6xl gap-8 px-5 py-12 sm:px-8 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)] lg:px-12 lg:py-20">
                    <div>
                        <div className="flex size-12 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <Award aria-hidden="true" />
                        </div>
                        <h1 className="mt-6 text-4xl font-semibold tracking-tight text-foreground">
                            Verify a learning certificate
                        </h1>
                        <p className="mt-4 max-w-xl leading-7 text-muted-foreground">
                            Enter the 24-character verification code printed on
                            the certificate. Results come directly from the
                            governed IDMIS certificate register.
                        </p>
                        <Form
                            {...verify.form()}
                            method="get"
                            className="mt-8 grid max-w-xl gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Label htmlFor="verification-code">
                                        Verification code
                                    </Label>
                                    <div className="flex flex-col gap-3 sm:flex-row">
                                        <Input
                                            id="verification-code"
                                            name="code"
                                            defaultValue={query ?? ''}
                                            minLength={24}
                                            maxLength={24}
                                            autoComplete="off"
                                            spellCheck={false}
                                            required
                                            className="font-mono uppercase"
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="shrink-0"
                                        >
                                            <Search aria-hidden="true" />
                                            Verify
                                        </Button>
                                    </div>
                                    <InputError message={errors.code} />
                                </>
                            )}
                        </Form>
                    </div>

                    <div aria-live="polite">
                        {!searched && (
                            <Card className="border-dashed bg-card/60">
                                <CardContent className="flex min-h-72 flex-col items-center justify-center text-center">
                                    <Search className="size-9 text-muted-foreground" />
                                    <p className="mt-4 font-semibold">
                                        Certificate result
                                    </p>
                                    <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                                        A verified record will show its holder,
                                        course, issue date and integrity
                                        checksum here.
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {searched && !certificate && (
                            <Card className="border-destructive/40">
                                <CardContent className="flex min-h-72 flex-col items-center justify-center text-center">
                                    <ShieldAlert className="size-10 text-destructive" />
                                    <p className="mt-4 text-lg font-semibold">
                                        Certificate not verified
                                    </p>
                                    <p className="mt-2 max-w-md text-sm text-muted-foreground">
                                        No issued certificate matches that exact
                                        code. Check every character against the
                                        original certificate.
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {certificate && (
                            <Card className="border-primary/40">
                                <CardHeader className="border-b">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="text-sm font-medium text-primary">
                                                IDMIS certificate register
                                            </p>
                                            <CardTitle className="mt-1">
                                                {certificate.number}
                                            </CardTitle>
                                        </div>
                                        <Badge
                                            variant={
                                                certificate.status === 'valid'
                                                    ? 'default'
                                                    : 'destructive'
                                            }
                                        >
                                            <CheckCircle2 />
                                            {certificate.status === 'valid'
                                                ? 'Valid'
                                                : 'Expired'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <dl className="divide-y">
                                        {[
                                            [
                                                'Certificate holder',
                                                certificate.learner,
                                            ],
                                            [
                                                'Course',
                                                `${certificate.courseCode} · ${certificate.courseTitle}`,
                                            ],
                                            [
                                                'Final score',
                                                `${certificate.finalScore}%`,
                                            ],
                                            ['Issued', certificate.issuedAt],
                                            [
                                                'Expires',
                                                certificate.expiresAt ??
                                                    'No expiry recorded',
                                            ],
                                        ].map(([label, value]) => (
                                            <div
                                                key={label}
                                                className="grid gap-1 py-4 sm:grid-cols-[10rem_1fr] sm:gap-5"
                                            >
                                                <dt className="text-sm text-muted-foreground">
                                                    {label}
                                                </dt>
                                                <dd className="font-medium">
                                                    {value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                    <div className="mt-4 rounded-lg bg-muted p-4">
                                        <p className="text-xs font-semibold tracking-wide uppercase">
                                            Content checksum
                                        </p>
                                        <p className="mt-2 font-mono text-xs break-all text-muted-foreground">
                                            {certificate.checksum}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </section>
            </main>
        </PublicLayout>
    );
}
