import { Form } from '@inertiajs/react';
import {
    DownloadIcon,
    EyeIcon,
    FilePenIcon,
    MoreHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { DEFAULT_LOCALE } from '@/lib/reference-catalog';
import {
    destroy,
    download,
    extract,
    preview,
    update,
    verify,
} from '@/routes/evidence';
import {
    decide as decideDisposition,
    execute as executeDisposition,
    store as storeDisposition,
} from '@/routes/evidence/dispositions';
import { store as storeLegalHold } from '@/routes/evidence/legal-holds';
import { release as releaseLegalHold } from '@/routes/evidence/legal-holds';
import {
    download as downloadVersion,
    preview as previewVersion,
    store as storeVersion,
} from '@/routes/evidence/versions';

type DocumentVersion = {
    id: string;
    number: number;
    originalName: string;
    mimeType: string;
    sizeBytes: number;
    checksum: string;
    scanStatus: string;
    ocrStatus: string;
    changeSummary: string | null;
    uploadedBy: string;
    createdAt: string;
    isCurrent: boolean;
    extractionAttempts: ExtractionAttempt[];
};

type ExtractionAttempt = {
    id: string;
    number: number;
    status: string;
    engine: string | null;
    language?: string;
    triggerSource: string;
    initiatedBy: string;
    characterCount?: number;
    pageCount?: number | null;
    errorCode: string | null;
    errorDetail?: string | null;
    startedAt: string;
    completedAt: string | null;
    durationMs: number | null;
    checksum?: string | null;
};

type LegalHold = {
    id: string;
    reference: string;
    reason: string;
    authority: string;
    placedBy: string;
    placedAt: string;
    releasedBy: string | null;
    releasedAt: string | null;
    releaseReason: string | null;
    canRelease: boolean;
};

type DocumentDisposition = {
    id: string;
    action: string;
    reason: string;
    authorityReference: string;
    retentionDueAt: string;
    scheduledFor: string;
    status: string;
    requestedBy: string;
    requestedAt: string;
    reviewedBy: string | null;
    reviewedAt: string | null;
    decisionReason: string | null;
    executedBy: string | null;
    executedAt: string | null;
    manifestChecksum: string | null;
    objectCount: number;
    totalBytes: number;
    executionError: string | null;
    canDecide: boolean;
    canExecute: boolean;
};

type EvidenceMeta = {
    title?: string | null;
    category?: string | null;
    sourceType?: string | null;
    description?: string | null;
    documentDate?: string | null;
    retentionUntil?: string | null;
    tags?: string | null;
    mimeType?: string | null;
    originalName?: string | null;
    sizeBytes?: string | null;
    checksum?: string | null;
    scanStatus?: string | null;
    ocrStatus?: string | null;
    extractionEngine?: string | null;
    extractionCompletedAt?: string | null;
    extractionError?: string | null;
    extractedTextPreview?: string | null;
    extractionAttempts?: ExtractionAttempt[];
    recordStatus?: string | null;
    version?: string | null;
    activeLegalHold?: string | null;
    countyId?: string | null;
    countyName?: string | null;
    countyCode?: string | null;
    countyLogoUrl?: string | null;
    versions?: DocumentVersion[];
    legalHolds?: LegalHold[];
    dispositions?: DocumentDisposition[];
};

export default function EvidenceRowAction({
    teamSlug,
    documentId,
    status,
    canVerify,
    canManage,
    canManageRecords,
    meta = {},
}: {
    teamSlug: string;
    documentId: string;
    status?: string;
    canVerify: boolean;
    canManage: boolean;
    canManageRecords: boolean;
    meta?: EvidenceMeta;
}) {
    const [activeSheet, setActiveSheet] = useState<'preview' | 'manage' | null>(
        null,
    );
    const args = { current_team: teamSlug, document: documentId };
    const versions = meta.versions ?? [];
    const legalHolds = meta.legalHolds ?? [];
    const dispositions = meta.dispositions ?? [];
    const hasOpenDisposition = dispositions.some((disposition) =>
        ['pending', 'approved', 'executing', 'execution_failed'].includes(
            disposition.status,
        ),
    );
    const mimeType = meta.mimeType ?? '';
    const isVideo = mimeType.startsWith('video/');
    const isAudio = mimeType.startsWith('audio/');
    const canPreview =
        [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/plain',
            'video/mp4',
            'video/webm',
            'audio/mpeg',
            'audio/mp4',
            'audio/ogg',
            'audio/wav',
        ].includes(mimeType) && meta.scanStatus === 'clean';
    const county =
        meta.countyId && meta.countyName && meta.countyCode
            ? {
                  kind: 'county' as const,
                  id: meta.countyId,
                  name: meta.countyName,
                  code: Number(meta.countyCode),
                  logoUrl: meta.countyLogoUrl ?? null,
              }
            : null;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Open evidence actions"
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-56">
                    {canPreview && (
                        <DropdownMenuItem
                            onSelect={() => setActiveSheet('preview')}
                        >
                            <EyeIcon aria-hidden="true" /> Preview document
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem asChild>
                        <a href={download.url(args)}>
                            <DownloadIcon aria-hidden="true" /> Download
                            original
                        </a>
                    </DropdownMenuItem>
                    {canManage && (
                        <DropdownMenuItem
                            onSelect={() => setActiveSheet('manage')}
                        >
                            <FilePenIcon aria-hidden="true" /> Manage record
                        </DropdownMenuItem>
                    )}
                    {canManageRecords && meta.scanStatus === 'clean' && (
                        <Form {...extract.form(args)}>
                            <DropdownMenuItem asChild>
                                <button type="submit" className="w-full">
                                    Reprocess searchable text
                                </button>
                            </DropdownMenuItem>
                        </Form>
                    )}
                    {canVerify && status === 'pending' && (
                        <DropdownMenuSeparator />
                    )}
                    {canVerify && status === 'pending' && (
                        <>
                            <Form {...verify.form(args)}>
                                <input
                                    type="hidden"
                                    name="status"
                                    value="verified"
                                />
                                <DropdownMenuItem asChild>
                                    <button type="submit" className="w-full">
                                        Verify evidence
                                    </button>
                                </DropdownMenuItem>
                            </Form>
                            <Form {...verify.form(args)}>
                                <input
                                    type="hidden"
                                    name="status"
                                    value="rejected"
                                />
                                <DropdownMenuItem asChild>
                                    <button
                                        type="submit"
                                        className="w-full text-destructive"
                                    >
                                        Reject evidence
                                    </button>
                                </DropdownMenuItem>
                            </Form>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Sheet
                open={activeSheet === 'preview'}
                onOpenChange={(open) => !open && setActiveSheet(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-5xl">
                    <SheetHeader>
                        <SheetTitle>
                            {meta.title ?? 'Document preview'}
                        </SheetTitle>
                        <SheetDescription>
                            {meta.originalName ?? 'Secure evidence document'} ·{' '}
                            {meta.sourceType === 'scanned'
                                ? 'Scanned copy'
                                : 'Digital file'}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-6">
                        {county && <CountyIdentity county={county} />}
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                Text extraction:{' '}
                                {meta.ocrStatus ?? 'not requested'}
                            </Badge>
                            {meta.extractionEngine && (
                                <Badge variant="secondary">
                                    {meta.extractionEngine}
                                </Badge>
                            )}
                        </div>
                        {meta.extractionError && (
                            <Alert>
                                <AlertTitle>
                                    Searchable text unavailable
                                </AlertTitle>
                                <AlertDescription>
                                    {meta.extractionError}
                                </AlertDescription>
                            </Alert>
                        )}
                        {(meta.extractionAttempts?.length ?? 0) > 0 && (
                            <section className="flex flex-col gap-2">
                                <div>
                                    <h3 className="font-semibold">
                                        Extraction attempt evidence
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Append-only processing and retry history
                                        for the current immutable version.
                                    </p>
                                </div>
                                <div className="overflow-x-auto rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Attempt</TableHead>
                                                <TableHead>Source</TableHead>
                                                <TableHead>Actor</TableHead>
                                                <TableHead>Engine</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead>Duration</TableHead>
                                                <TableHead>Result</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {meta.extractionAttempts?.map(
                                                (attempt) => (
                                                    <TableRow key={attempt.id}>
                                                        <TableCell>
                                                            #{attempt.number}
                                                        </TableCell>
                                                        <TableCell>
                                                            {attempt.triggerSource.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {
                                                                attempt.initiatedBy
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            {attempt.engine ??
                                                                '—'}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge variant="outline">
                                                                {attempt.status}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            {attempt.durationMs !==
                                                            null
                                                                ? `${attempt.durationMs.toLocaleString(DEFAULT_LOCALE)} ms`
                                                                : 'In progress'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {attempt.errorCode ??
                                                                `${(attempt.characterCount ?? 0).toLocaleString(DEFAULT_LOCALE)} characters`}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>
                        )}
                        {isVideo ? (
                            <video
                                controls
                                controlsList="nodownload"
                                preload="metadata"
                                aria-label={`Preview of ${meta.title ?? 'document'}`}
                                className="max-h-[72vh] w-full rounded-lg border bg-black"
                            >
                                <source
                                    src={preview.url(args)}
                                    type={mimeType}
                                />
                            </video>
                        ) : isAudio ? (
                            <audio
                                controls
                                controlsList="nodownload"
                                preload="metadata"
                                aria-label={`Preview of ${meta.title ?? 'document'}`}
                                className="w-full"
                            >
                                <source
                                    src={preview.url(args)}
                                    type={mimeType}
                                />
                            </audio>
                        ) : (
                            <iframe
                                title={`Preview of ${meta.title ?? 'document'}`}
                                src={preview.url(args)}
                                className="h-[72vh] w-full rounded-lg border bg-muted"
                            />
                        )}
                        {meta.extractedTextPreview && (
                            <section className="flex flex-col gap-2">
                                <h3 className="font-semibold">
                                    Extracted text preview
                                </h3>
                                <p className="max-h-72 overflow-y-auto rounded-lg border bg-muted p-4 text-sm leading-6 whitespace-pre-wrap">
                                    {meta.extractedTextPreview}
                                </p>
                            </section>
                        )}
                        <Button asChild variant="outline">
                            <a href={download.url(args)}>
                                Download original document
                            </a>
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>

            <Sheet
                open={activeSheet === 'manage'}
                onOpenChange={(open) => !open && setActiveSheet(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Manage document</SheetTitle>
                        <SheetDescription>
                            Update governed metadata, versions, retention, and
                            legal holds.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-6 px-4 pb-6">
                        {county && <CountyIdentity county={county} />}
                        <Form {...update.form(args)} className="grid gap-4">
                            {({ processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor={`title-${documentId}`}>
                                            Title
                                        </Label>
                                        <Input
                                            id={`title-${documentId}`}
                                            name="title"
                                            defaultValue={meta.title ?? ''}
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`category-${documentId}`}
                                            >
                                                Category
                                            </Label>
                                            <Input
                                                id={`category-${documentId}`}
                                                name="category"
                                                defaultValue={
                                                    meta.category ?? ''
                                                }
                                                required
                                            />
                                        </div>
                                        <DatePickerField
                                            name="document_date"
                                            label="Document date"
                                            defaultValue={
                                                meta.documentDate ?? ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`description-${documentId}`}
                                        >
                                            Description
                                        </Label>
                                        <Input
                                            id={`description-${documentId}`}
                                            name="description"
                                            defaultValue={
                                                meta.description ?? ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`tags-${documentId}`}
                                            >
                                                Tags
                                            </Label>
                                            <Input
                                                id={`tags-${documentId}`}
                                                name="tags"
                                                defaultValue={meta.tags ?? ''}
                                                placeholder="planning, FY2025"
                                            />
                                        </div>
                                        <DatePickerField
                                            name="retention_until"
                                            label="Retain until"
                                            defaultValue={
                                                meta.retentionUntil ?? ''
                                            }
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Save metadata
                                    </Button>
                                </>
                            )}
                        </Form>
                        <section className="grid gap-3 border-t pt-5">
                            <div>
                                <h3 className="font-semibold">
                                    Immutable versions
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Version {meta.version ?? '1'} · SHA-256{' '}
                                    {meta.checksum?.slice(0, 12) ??
                                        'legacy record'}{' '}
                                    · scan {meta.scanStatus ?? 'pending'} · text
                                    extraction{' '}
                                    {meta.ocrStatus ?? 'not requested'}
                                    {meta.extractionCompletedAt
                                        ? ` · completed ${new Date(meta.extractionCompletedAt).toLocaleString(DEFAULT_LOCALE)}`
                                        : ''}
                                </p>
                            </div>
                            <Form
                                {...storeVersion.form(args)}
                                className="grid gap-3"
                                resetOnSuccess
                            >
                                {({ processing }) => (
                                    <>
                                        <Input
                                            name="document"
                                            type="file"
                                            required
                                            accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                        />
                                        <Input
                                            name="change_summary"
                                            required
                                            placeholder="Reason for this replacement"
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Upload new version
                                        </Button>
                                    </>
                                )}
                            </Form>
                            <div className="grid gap-3">
                                {versions.length === 0 ? (
                                    <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                        No retained version history is available
                                        for this legacy record.
                                    </p>
                                ) : (
                                    versions.map((version) => {
                                        const versionArgs = {
                                            ...args,
                                            version: version.id,
                                        };
                                        const canPreviewVersion = [
                                            'application/pdf',
                                            'image/jpeg',
                                            'image/png',
                                            'image/webp',
                                            'text/plain',
                                        ].includes(version.mimeType);

                                        return (
                                            <article
                                                key={version.id}
                                                className="grid gap-3 rounded-lg border p-4"
                                            >
                                                <div className="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <h4 className="font-medium">
                                                                Version{' '}
                                                                {version.number}
                                                            </h4>
                                                            {version.isCurrent && (
                                                                <Badge>
                                                                    Current
                                                                </Badge>
                                                            )}
                                                            <Badge variant="outline">
                                                                {
                                                                    version.scanStatus
                                                                }
                                                            </Badge>
                                                        </div>
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                version.originalName
                                                            }{' '}
                                                            · uploaded by{' '}
                                                            {version.uploadedBy}{' '}
                                                            ·{' '}
                                                            {new Date(
                                                                version.createdAt,
                                                            ).toLocaleString(
                                                                DEFAULT_LOCALE,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <div className="flex gap-2">
                                                        {canPreviewVersion &&
                                                            version.scanStatus ===
                                                                'clean' && (
                                                                <Button
                                                                    asChild
                                                                    size="sm"
                                                                    variant="outline"
                                                                >
                                                                    <a
                                                                        href={previewVersion.url(
                                                                            versionArgs,
                                                                        )}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                    >
                                                                        Preview
                                                                    </a>
                                                                </Button>
                                                            )}
                                                        {version.scanStatus ===
                                                            'clean' && (
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                <a
                                                                    href={downloadVersion.url(
                                                                        versionArgs,
                                                                    )}
                                                                >
                                                                    Download
                                                                </a>
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                                <p className="text-sm">
                                                    {version.changeSummary ??
                                                        'Initial governed version'}
                                                </p>
                                                <p className="font-mono text-[11px] break-all text-muted-foreground">
                                                    SHA-256 {version.checksum} ·{' '}
                                                    {version.sizeBytes.toLocaleString(
                                                        DEFAULT_LOCALE,
                                                    )}{' '}
                                                    bytes · text{' '}
                                                    {version.ocrStatus}
                                                </p>
                                            </article>
                                        );
                                    })
                                )}
                            </div>
                        </section>
                        {canManageRecords &&
                            meta.activeLegalHold !== 'true' && (
                                <section className="grid gap-3 border-t pt-5">
                                    <div>
                                        <h3 className="font-semibold">
                                            Place legal hold
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Prevent replacement, archival, and
                                            retention changes.
                                        </p>
                                    </div>
                                    <Form
                                        {...storeLegalHold.form(args)}
                                        className="grid gap-3"
                                        resetOnSuccess
                                    >
                                        {({ processing }) => (
                                            <>
                                                <Input
                                                    name="reference"
                                                    required
                                                    placeholder="Hold reference"
                                                />
                                                <Input
                                                    name="authority"
                                                    required
                                                    placeholder="Issuing authority"
                                                />
                                                <Input
                                                    name="reason"
                                                    required
                                                    placeholder="Legal or regulatory reason"
                                                />
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    Place legal hold
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </section>
                            )}
                        {meta.activeLegalHold === 'true' && (
                            <p className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                                This record is under legal hold. Replacement,
                                retention changes, and archival are locked.
                            </p>
                        )}
                        <section className="grid gap-3 border-t pt-5">
                            <div>
                                <h3 className="font-semibold">
                                    Legal-hold history
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Placement and release decisions remain
                                    visible after a hold is released.
                                </p>
                            </div>
                            {legalHolds.length === 0 ? (
                                <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                    No legal holds have been recorded.
                                </p>
                            ) : (
                                legalHolds.map((hold) => (
                                    <article
                                        key={hold.id}
                                        className="grid gap-3 rounded-lg border p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <h4 className="font-medium">
                                                    {hold.reference}
                                                </h4>
                                                <p className="text-xs text-muted-foreground">
                                                    {hold.authority} · placed by{' '}
                                                    {hold.placedBy} ·{' '}
                                                    {new Date(
                                                        hold.placedAt,
                                                    ).toLocaleString(
                                                        DEFAULT_LOCALE,
                                                    )}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    hold.releasedAt
                                                        ? 'secondary'
                                                        : 'destructive'
                                                }
                                            >
                                                {hold.releasedAt
                                                    ? 'Released'
                                                    : 'Active'}
                                            </Badge>
                                        </div>
                                        <p className="text-sm">{hold.reason}</p>
                                        {hold.releasedAt && (
                                            <p className="rounded-md bg-muted p-3 text-xs">
                                                Released by{' '}
                                                {hold.releasedBy ?? 'Unknown'}{' '}
                                                on{' '}
                                                {new Date(
                                                    hold.releasedAt,
                                                ).toLocaleString(
                                                    DEFAULT_LOCALE,
                                                )}
                                                . {hold.releaseReason}
                                            </p>
                                        )}
                                        {hold.canRelease &&
                                            canManageRecords && (
                                                <Form
                                                    {...releaseLegalHold.form({
                                                        ...args,
                                                        legalHold: hold.id,
                                                    })}
                                                    className="grid gap-2"
                                                    resetOnSuccess
                                                >
                                                    {({ processing }) => (
                                                        <>
                                                            <Label
                                                                htmlFor={`release-reason-${hold.id}`}
                                                            >
                                                                Release reason
                                                            </Label>
                                                            <Input
                                                                id={`release-reason-${hold.id}`}
                                                                name="release_reason"
                                                                required
                                                                placeholder="Authority and reason for releasing this hold"
                                                            />
                                                            <Button
                                                                type="submit"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Release legal
                                                                hold
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            )}
                                    </article>
                                ))
                            )}
                        </section>
                        <DispositionControls
                            args={args}
                            documentId={documentId}
                            dispositions={dispositions}
                            canManageRecords={canManageRecords}
                            hasOpenDisposition={hasOpenDisposition}
                            retentionUntil={meta.retentionUntil ?? null}
                            recordStatus={meta.recordStatus ?? null}
                        />
                        {canManage && meta.recordStatus !== 'disposed' && (
                            <Form {...destroy.form(args)}>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    className="w-full"
                                >
                                    Archive document
                                </Button>
                            </Form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function DispositionControls({
    args,
    documentId,
    dispositions,
    canManageRecords,
    hasOpenDisposition,
    retentionUntil,
    recordStatus,
}: {
    args: { current_team: string; document: string };
    documentId: string;
    dispositions: DocumentDisposition[];
    canManageRecords: boolean;
    hasOpenDisposition: boolean;
    retentionUntil: string | null;
    recordStatus: string | null;
}) {
    return (
        <>
            {canManageRecords &&
                recordStatus !== 'disposed' &&
                !hasOpenDisposition && (
                    <section className="grid gap-3 border-t pt-5">
                        <div>
                            <h3 className="font-semibold">
                                Request controlled disposition
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Requires independent review and a separate
                                executing officer. Active legal holds and
                                integrity failures stop execution.
                            </p>
                        </div>
                        {!retentionUntil ? (
                            <Alert>
                                <AlertTitle>Retention date required</AlertTitle>
                                <AlertDescription>
                                    Set an approved retain-until date before
                                    requesting secure destruction.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <Form
                                {...storeDisposition.form(args)}
                                className="grid gap-3"
                                resetOnSuccess
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`disposition-authority-${documentId}`}
                                            >
                                                Authority reference
                                            </Label>
                                            <Input
                                                id={`disposition-authority-${documentId}`}
                                                name="authority_reference"
                                                required
                                                placeholder="Approved schedule or disposal authority"
                                            />
                                        </div>
                                        <DatePickerField
                                            name="scheduled_for"
                                            label="Scheduled execution date"
                                            min={retentionUntil}
                                            required
                                        />
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`disposition-reason-${documentId}`}
                                            >
                                                Disposition reason
                                            </Label>
                                            <Textarea
                                                id={`disposition-reason-${documentId}`}
                                                name="reason"
                                                required
                                                placeholder="Record class, retention trigger and reason destruction is authorized"
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            Submit disposition for review
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </section>
                )}
            <section className="grid gap-3 border-t pt-5">
                <div>
                    <h3 className="font-semibold">Disposition history</h3>
                    <p className="text-xs text-muted-foreground">
                        Requests, independent decisions, execution failures and
                        immutable destruction evidence.
                    </p>
                </div>
                {dispositions.length === 0 ? (
                    <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        No disposition requests have been recorded.
                    </p>
                ) : (
                    dispositions.map((disposition) => (
                        <DispositionRecord
                            key={disposition.id}
                            args={args}
                            disposition={disposition}
                            canManageRecords={canManageRecords}
                        />
                    ))
                )}
            </section>
        </>
    );
}

function DispositionRecord({
    args,
    disposition,
    canManageRecords,
}: {
    args: { current_team: string; document: string };
    disposition: DocumentDisposition;
    canManageRecords: boolean;
}) {
    const dispositionArgs = { ...args, disposition: disposition.id };

    return (
        <article className="grid gap-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h4 className="font-medium">
                        Secure destruction · {disposition.authorityReference}
                    </h4>
                    <p className="text-xs text-muted-foreground">
                        Requested by {disposition.requestedBy} on{' '}
                        {new Date(disposition.requestedAt).toLocaleString(
                            DEFAULT_LOCALE,
                        )}
                    </p>
                </div>
                <Badge variant="outline">{disposition.status}</Badge>
            </div>
            <p className="text-sm">{disposition.reason}</p>
            <p className="text-xs text-muted-foreground">
                Retention due {disposition.retentionDueAt} · scheduled{' '}
                {disposition.scheduledFor}
            </p>
            {disposition.reviewedAt && (
                <p className="rounded-md bg-muted p-3 text-xs">
                    Reviewed by {disposition.reviewedBy} on{' '}
                    {new Date(disposition.reviewedAt).toLocaleString(
                        DEFAULT_LOCALE,
                    )}
                    . {disposition.decisionReason}
                </p>
            )}
            {disposition.executionError && (
                <Alert variant="destructive">
                    <AlertTitle>Execution stopped</AlertTitle>
                    <AlertDescription>
                        {disposition.executionError}
                    </AlertDescription>
                </Alert>
            )}
            {disposition.executedAt && (
                <div className="grid gap-1 rounded-md bg-muted p-3 text-xs">
                    <p>
                        Executed by {disposition.executedBy} on{' '}
                        {new Date(disposition.executedAt).toLocaleString(
                            DEFAULT_LOCALE,
                        )}
                        . {disposition.objectCount} objects /{' '}
                        {disposition.totalBytes.toLocaleString(DEFAULT_LOCALE)}{' '}
                        bytes.
                    </p>
                    <p className="font-mono break-all">
                        Manifest SHA-256 {disposition.manifestChecksum}
                    </p>
                </div>
            )}
            {disposition.canDecide && canManageRecords && (
                <Form
                    {...decideDisposition.form(dispositionArgs)}
                    className="grid gap-2"
                >
                    {({ processing }) => (
                        <>
                            <Label
                                htmlFor={`decision-reason-${disposition.id}`}
                            >
                                Independent review reason
                            </Label>
                            <Textarea
                                id={`decision-reason-${disposition.id}`}
                                name="decision_reason"
                                required
                            />
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    type="submit"
                                    name="decision"
                                    value="approved"
                                    disabled={processing}
                                >
                                    Approve
                                </Button>
                                <Button
                                    type="submit"
                                    name="decision"
                                    value="rejected"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Reject
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}
            {disposition.canExecute && canManageRecords && (
                <Form {...executeDisposition.form(dispositionArgs)}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="destructive"
                            className="w-full"
                            disabled={processing}
                        >
                            Execute approved secure destruction
                        </Button>
                    )}
                </Form>
            )}
        </article>
    );
}
