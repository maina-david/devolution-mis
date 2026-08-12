import { Form } from '@inertiajs/react';
import {
    Download,
    Ellipsis,
    FileCheck2,
    Play,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import FormSheet from '@/components/form-sheet';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { download, store } from '@/routes/audit-assurance';

export function AuditAssuranceRunControl() {
    return (
        <FormSheet
            title="Run audit integrity assurance"
            description="Verify the complete predecessor chain and every reproducible v2 event hash, then retain a private checksum-bound anchor artifact."
            triggerLabel="Run assurance"
            icon={Play}
        >
            <Form {...store.form()} className="flex flex-col gap-4">
                {({ processing, errors }) => (
                    <>
                        <Alert>
                            <ShieldCheck aria-hidden="true" />
                            <AlertTitle>Fail-closed verification</AlertTitle>
                            <AlertDescription>
                                Any predecessor or event-hash mismatch produces
                                a failed run. Legacy hashes and missing
                                dedicated signing keys are reported as warnings
                                rather than silently treated as verified.
                            </AlertDescription>
                        </Alert>
                        {errors.assurance && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {errors.assurance}
                            </p>
                        )}
                        <Button type="submit" disabled={processing}>
                            <Play data-icon="inline-start" />
                            {processing
                                ? 'Verifying…'
                                : 'Verify and retain evidence'}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

export function AuditAssuranceRowAction({
    runId,
    status,
    meta,
}: {
    runId: string;
    status?: string;
    meta?: Record<string, string | null>;
}) {
    const artifactAvailable = meta?.artifactAvailable === 'true';
    const [evidenceOpen, setEvidenceOpen] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Open assurance actions"
                    >
                        <Ellipsis aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={() => setEvidenceOpen(true)}
                        >
                            <FileCheck2 data-icon="inline-start" />
                            View evidence
                        </DropdownMenuItem>
                        {artifactAvailable && (
                            <DropdownMenuItem asChild>
                                <a
                                    href={download.url({
                                        auditAssuranceRun: runId,
                                    })}
                                >
                                    <Download data-icon="inline-start" />
                                    Download artifact
                                </a>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={evidenceOpen} onOpenChange={setEvidenceOpen}>
                <SheetContent className="overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Audit assurance evidence</SheetTitle>
                        <SheetDescription>
                            Verification boundary, findings and cryptographic
                            evidence for this immutable run.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-4 px-4 pb-8">
                        <Badge variant="outline">{status ?? 'unknown'}</Badge>
                        <Evidence label="Events" value={meta?.eventCount} />
                        <Evidence
                            label="Reproducible hashes verified"
                            value={meta?.verifiedEventCount}
                        />
                        <Evidence
                            label="Legacy hashes"
                            value={meta?.legacyEventCount}
                        />
                        <Evidence
                            label="Mismatches"
                            value={meta?.mismatchCount}
                        />
                        <Evidence
                            label="First event"
                            value={meta?.firstEventId}
                        />
                        <Evidence
                            label="Covered through"
                            value={meta?.lastEventId}
                        />
                        <Evidence
                            label="Chain root SHA-256"
                            value={meta?.chainRootChecksum}
                        />
                        <Evidence
                            label="Artifact SHA-256"
                            value={meta?.artifactChecksum}
                        />
                        <Evidence label="Signature" value={meta?.signature} />
                        <Evidence
                            label="Signing key reference"
                            value={meta?.signingKeyReference}
                        />
                        <Evidence
                            label="Evidence SHA-256"
                            value={meta?.evidenceChecksum}
                        />
                        <Evidence
                            label="Findings"
                            value={meta?.findingCodes || 'None'}
                        />
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function Evidence({ label, value }: { label: string; value?: string | null }) {
    return (
        <div className="flex flex-col gap-1 border-b border-border pb-3">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <code className="text-sm break-all">{value || '—'}</code>
        </div>
    );
}
