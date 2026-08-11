import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Copy, Printer } from 'lucide-react';
import { useState } from 'react';
import CitizenEngagementShell from '@/components/citizen-engagement-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index } from '@/routes/citizen-engagement';

export default function CitizenCaseReceipt({
    receipt,
}: {
    receipt: { reference: string; trackingToken: string };
}) {
    const [copyStatus, setCopyStatus] = useState('');
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(
                `Reference: ${receipt.reference}\nPrivate tracking code: ${receipt.trackingToken}`,
            );
            setCopyStatus('Receipt copied to clipboard.');
        } catch {
            setCopyStatus(
                'The receipt could not be copied. Select the reference and tracking code manually.',
            );
        }
    };

    return (
        <CitizenEngagementShell>
            <Head title="Case receipt" />
            <div className="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-16 sm:px-6">
                <div className="flex items-center gap-3 text-primary">
                    <CheckCircle2 aria-hidden="true" />
                    <p className="font-semibold">Submitted securely</p>
                </div>
                <h1 className="text-4xl font-bold">Keep this receipt.</h1>
                <Alert>
                    <AlertTitle>
                        The tracking code is displayed only on this receipt
                    </AlertTitle>
                    <AlertDescription>
                        Save or print it now. Staff cannot recover the original
                        private code because IDMIS stores only a cryptographic
                        hash.
                    </AlertDescription>
                </Alert>
                <Card>
                    <CardHeader>
                        <CardTitle>Citizen case receipt</CardTitle>
                        <CardDescription>
                            Use both values when checking your case.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="flex flex-col gap-5">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Case reference
                                </dt>
                                <dd className="mt-1 font-mono text-lg font-bold">
                                    {receipt.reference}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Private tracking code
                                </dt>
                                <dd className="mt-1 font-mono text-lg font-bold break-all">
                                    {receipt.trackingToken}
                                </dd>
                            </div>
                        </dl>
                        <div className="mt-6 flex flex-wrap gap-3">
                            <Button type="button" onClick={copy}>
                                <Copy aria-hidden="true" />
                                Copy receipt
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer aria-hidden="true" />
                                Print
                            </Button>
                        </div>
                        <p
                            className="mt-3 text-sm text-muted-foreground"
                            role="status"
                            aria-live="polite"
                        >
                            {copyStatus}
                        </p>
                    </CardContent>
                </Card>
                <Button asChild variant="outline">
                    <Link href={index()}>Return to citizen engagement</Link>
                </Button>
            </div>
        </CitizenEngagementShell>
    );
}
