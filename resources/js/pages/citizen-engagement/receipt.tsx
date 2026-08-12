import { Head, Link, usePage } from '@inertiajs/react';
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
    const copyText = usePage().props.localization.citizen;
    const [copyStatus, setCopyStatus] = useState('');
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(
                `${copyText.case_reference}: ${receipt.reference}\n${copyText.tracking_code}: ${receipt.trackingToken}`,
            );
            setCopyStatus(copyText.receipt_copied);
        } catch {
            setCopyStatus(copyText.receipt_copy_failed);
        }
    };

    return (
        <CitizenEngagementShell>
            <Head title={copyText.receipt_title} />
            <div className="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-16 sm:px-6">
                <div className="flex items-center gap-3 text-primary">
                    <CheckCircle2 aria-hidden="true" />
                    <p className="font-semibold">
                        {copyText.submitted_securely}
                    </p>
                </div>
                <h1 className="text-4xl font-bold">{copyText.keep_receipt}</h1>
                <Alert>
                    <AlertTitle>{copyText.receipt_private_title}</AlertTitle>
                    <AlertDescription>
                        {copyText.receipt_private_description}
                    </AlertDescription>
                </Alert>
                <Card>
                    <CardHeader>
                        <CardTitle>{copyText.citizen_case_receipt}</CardTitle>
                        <CardDescription>
                            {copyText.receipt_values_help}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="flex flex-col gap-5">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    {copyText.case_reference}
                                </dt>
                                <dd className="mt-1 font-mono text-lg font-bold">
                                    {receipt.reference}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    {copyText.tracking_code}
                                </dt>
                                <dd className="mt-1 font-mono text-lg font-bold break-all">
                                    {receipt.trackingToken}
                                </dd>
                            </div>
                        </dl>
                        <div className="mt-6 flex flex-wrap gap-3">
                            <Button type="button" onClick={copy}>
                                <Copy aria-hidden="true" />
                                {copyText.copy_receipt}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer aria-hidden="true" />
                                {copyText.print}
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
                    <Link href={index()}>{copyText.return_to_engagement}</Link>
                </Button>
            </div>
        </CitizenEngagementShell>
    );
}
