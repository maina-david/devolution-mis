import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, Copy, Printer } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import PublicLayout from '@/layouts/public-layout';
import { index as dataRights } from '@/routes/data-rights';
import { show as privacyNotice } from '@/routes/privacy-notice';

type Receipt = { reference: string; receivedAt: string; dueAt: string };

export default function DataRightsReceipt({ receipt }: { receipt: Receipt }) {
    const { localization } = usePage().props;
    const copy = localization.dataRights;
    const [copyStatus, setCopyStatus] = useState('');
    const formatter = new Intl.DateTimeFormat(localization.current, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
    const copyReceipt = async () => {
        try {
            await navigator.clipboard.writeText(
                `${copy.reference}: ${receipt.reference}\n${copy.received_at}: ${formatter.format(new Date(receipt.receivedAt))}\n${copy.target_due_at}: ${formatter.format(new Date(receipt.dueAt))}`,
            );
            setCopyStatus(copy.receipt_copied);
        } catch {
            setCopyStatus(copy.receipt_copy_failed);
        }
    };

    return (
        <PublicLayout>
            <Head title={copy.receipt_title} />
            <main id="main-content" tabIndex={-1}>
                <div className="mx-auto flex max-w-2xl flex-col gap-6 px-5 py-12 sm:px-8 lg:py-16">
                    <div className="flex items-center gap-3 text-primary">
                        <CheckCircle2 aria-hidden="true" />
                        <p className="font-semibold">
                            {copy.received_securely}
                        </p>
                    </div>
                    <h1 className="text-4xl font-bold tracking-tight">
                        {copy.keep_reference}
                    </h1>
                    <Alert>
                        <AlertTitle>{copy.safety_title}</AlertTitle>
                        <AlertDescription>{copy.receipt_help}</AlertDescription>
                    </Alert>
                    <Card>
                        <CardHeader>
                            <CardTitle>{copy.receipt_title}</CardTitle>
                            <CardDescription>
                                {copy.receipt_help}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-5">
                                <ReceiptValue
                                    label={copy.reference}
                                    value={receipt.reference}
                                    mono
                                />
                                <ReceiptValue
                                    label={copy.received_at}
                                    value={formatter.format(
                                        new Date(receipt.receivedAt),
                                    )}
                                />
                                <ReceiptValue
                                    label={copy.target_due_at}
                                    value={formatter.format(
                                        new Date(receipt.dueAt),
                                    )}
                                />
                            </dl>
                            <div className="mt-6 flex flex-wrap gap-3">
                                <Button type="button" onClick={copyReceipt}>
                                    <Copy aria-hidden="true" />
                                    {copy.copy_receipt}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.print()}
                                >
                                    <Printer aria-hidden="true" />
                                    {copy.print}
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
                    <div className="flex flex-wrap gap-3">
                        <Button asChild variant="outline">
                            <Link href={dataRights()}>
                                {copy.return_to_rights}
                            </Link>
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href={privacyNotice()}>
                                {copy.return_to_notice}
                            </Link>
                        </Button>
                    </div>
                </div>
            </main>
        </PublicLayout>
    );
}

function ReceiptValue({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: string;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd
                className={`mt-1 text-lg font-semibold ${mono ? 'font-mono' : ''}`}
            >
                {value}
            </dd>
        </div>
    );
}
