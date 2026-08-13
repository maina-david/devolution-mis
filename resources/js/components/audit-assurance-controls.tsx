import { Form, usePage } from '@inertiajs/react';
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
    const copy = usePage().props.localization.auditAssurance;

    return (
        <FormSheet
            title={copy.run_title}
            description={copy.run_description}
            triggerLabel={copy.run_assurance}
            icon={Play}
        >
            <Form {...store.form()} className="flex flex-col gap-4">
                {({ processing, errors }) => (
                    <>
                        <Alert>
                            <ShieldCheck aria-hidden="true" />
                            <AlertTitle>{copy.fail_closed}</AlertTitle>
                            <AlertDescription>
                                {copy.fail_closed_description}
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
                            <Play data-icon="inline-start" aria-hidden="true" />
                            {processing ? copy.verifying : copy.verify_retain}
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
    const copy = usePage().props.localization.auditAssurance;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={copy.open_actions}
                    >
                        <Ellipsis aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            onSelect={() => setEvidenceOpen(true)}
                        >
                            <FileCheck2
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                            {copy.view_evidence}
                        </DropdownMenuItem>
                        {artifactAvailable && (
                            <DropdownMenuItem asChild>
                                <a
                                    href={download.url({
                                        auditAssuranceRun: runId,
                                    })}
                                >
                                    <Download
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    {copy.download_artifact}
                                </a>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={evidenceOpen} onOpenChange={setEvidenceOpen}>
                <SheetContent className="overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{copy.evidence_title}</SheetTitle>
                        <SheetDescription>
                            {copy.evidence_description}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-4 px-4 pb-8">
                        <Badge variant="outline">
                            {status ?? copy.unknown}
                        </Badge>
                        <Evidence
                            label={copy.events}
                            value={meta?.eventCount}
                        />
                        <Evidence
                            label={copy.verified_hashes}
                            value={meta?.verifiedEventCount}
                        />
                        <Evidence
                            label={copy.legacy_hashes}
                            value={meta?.legacyEventCount}
                        />
                        <Evidence
                            label={copy.mismatches}
                            value={meta?.mismatchCount}
                        />
                        <Evidence
                            label={copy.first_event}
                            value={meta?.firstEventId}
                        />
                        <Evidence
                            label={copy.covered_through}
                            value={meta?.lastEventId}
                        />
                        <Evidence
                            label={copy.chain_root}
                            value={meta?.chainRootChecksum}
                        />
                        <Evidence
                            label={copy.artifact_checksum}
                            value={meta?.artifactChecksum}
                        />
                        <Evidence
                            label={copy.signature}
                            value={meta?.signature}
                        />
                        <Evidence
                            label={copy.signing_key}
                            value={meta?.signingKeyReference}
                        />
                        <Evidence
                            label={copy.evidence_checksum}
                            value={meta?.evidenceChecksum}
                        />
                        <Evidence
                            label={copy.findings}
                            value={meta?.findingCodes || copy.none}
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
