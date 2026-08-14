import { Form, usePage } from '@inertiajs/react';
import {
    DownloadIcon,
    EyeIcon,
    FilePenIcon,
    MoreHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import CountyIdentity from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DocumentPreviewCarousel from '@/components/document-preview-carousel';
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
import { interpolate } from '@/hooks/use-localization';
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
    documentId,
    status,
    canVerify,
    canManage,
    canManageRecords,
    meta = {},
}: {
    documentId: string;
    status?: string;
    canVerify: boolean;
    canManage: boolean;
    canManageRecords: boolean;
    meta?: EvidenceMeta;
}) {
    const { current: locale, evidence: copy } = usePage().props.localization;
    const [activeSheet, setActiveSheet] = useState<'preview' | 'manage' | null>(
        null,
    );
    const args = { document: documentId };
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
                        aria-label={copy.open_actions}
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-56">
                    {canPreview && (
                        <DropdownMenuItem
                            onSelect={() => setActiveSheet('preview')}
                        >
                            <EyeIcon aria-hidden="true" />
                            {copy.preview_document}
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem asChild>
                        <a href={download.url(args)}>
                            <DownloadIcon aria-hidden="true" />
                            {copy.download_original}
                        </a>
                    </DropdownMenuItem>
                    {canManage && (
                        <DropdownMenuItem
                            onSelect={() => setActiveSheet('manage')}
                        >
                            <FilePenIcon aria-hidden="true" />
                            {copy.manage_record}
                        </DropdownMenuItem>
                    )}
                    {canManageRecords && meta.scanStatus === 'clean' && (
                        <Form {...extract.form(args)}>
                            <DropdownMenuItem asChild>
                                <button type="submit" className="w-full">
                                    {copy.reprocess_text}
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
                                        {copy.verify_evidence}
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
                                        {copy.reject_evidence}
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
                            {meta.title ?? copy.document_preview}
                        </SheetTitle>
                        <SheetDescription>
                            {meta.originalName ?? copy.secure_document}{' '}
                            {meta.sourceType === 'scanned'
                                ? copy.scanned_copy
                                : copy.digital_file}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pb-6">
                        {county && <CountyIdentity county={county} />}
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {interpolate(copy.text_extraction, {
                                    status:
                                        meta.ocrStatus ?? copy.not_requested,
                                })}
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
                                    {copy.searchable_unavailable}
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
                                        {copy.attempts_title}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {copy.attempts_description}
                                    </p>
                                </div>
                                <div className="overflow-x-auto rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    {copy.attempt}
                                                </TableHead>
                                                <TableHead>
                                                    {copy.source}
                                                </TableHead>
                                                <TableHead>
                                                    {copy.actor}
                                                </TableHead>
                                                <TableHead>
                                                    {copy.engine}
                                                </TableHead>
                                                <TableHead>
                                                    {copy.status}
                                                </TableHead>
                                                <TableHead>
                                                    {copy.duration}
                                                </TableHead>
                                                <TableHead>
                                                    {copy.result}
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {meta.extractionAttempts?.map(
                                                (attempt) => (
                                                    <TableRow key={attempt.id}>
                                                        <TableCell>
                                                            {'#'}
                                                            {attempt.number}
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
                                                                ? `${attempt.durationMs.toLocaleString(locale)} ms`
                                                                : copy.in_progress}
                                                        </TableCell>
                                                        <TableCell>
                                                            {attempt.errorCode ??
                                                                interpolate(
                                                                    copy.characters,
                                                                    {
                                                                        count: (
                                                                            attempt.characterCount ??
                                                                            0
                                                                        ).toLocaleString(
                                                                            locale,
                                                                        ),
                                                                    },
                                                                )}
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
                                aria-label={interpolate(copy.preview_of, {
                                    title: meta.title ?? copy.document,
                                })}
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
                                aria-label={interpolate(copy.preview_of, {
                                    title: meta.title ?? copy.document,
                                })}
                                className="w-full"
                            >
                                <source
                                    src={preview.url(args)}
                                    type={mimeType}
                                />
                            </audio>
                        ) : (
                            <DocumentPreviewCarousel
                                items={
                                    versions.length > 0
                                        ? versions
                                              .filter(
                                                  (version) =>
                                                      version.scanStatus ===
                                                          'clean' &&
                                                      (version.mimeType ===
                                                          'application/pdf' ||
                                                          version.mimeType.startsWith(
                                                              'image/',
                                                          )),
                                              )
                                              .map((version) => ({
                                                  id: version.id,
                                                  title: interpolate(
                                                      copy.version_number,
                                                      {
                                                          number: version.number,
                                                      },
                                                  ),
                                                  url: previewVersion.url({
                                                      document: documentId,
                                                      version: version.id,
                                                  }),
                                                  mimeType: version.mimeType,
                                                  checksum: version.checksum,
                                                  version: String(
                                                      version.number,
                                                  ),
                                                  source:
                                                      meta.sourceType ===
                                                      'scanned'
                                                          ? copy.scanned_copy
                                                          : copy.digital_file,
                                                  uploadedBy: interpolate(
                                                      copy.uploaded_by_marker,
                                                      {
                                                          actor: version.uploadedBy,
                                                      },
                                                  ),
                                              }))
                                        : [
                                              {
                                                  id: documentId,
                                                  title:
                                                      meta.title ??
                                                      copy.document,
                                                  url: preview.url(args),
                                                  mimeType,
                                                  checksum: meta.checksum,
                                                  version: meta.version,
                                                  source:
                                                      meta.sourceType ===
                                                      'scanned'
                                                          ? copy.scanned_copy
                                                          : copy.digital_file,
                                              },
                                          ]
                                }
                                pageLabel={(page, total) =>
                                    interpolate(copy.preview_page, {
                                        page,
                                        total,
                                    })
                                }
                                verifiedLabel={copy.sha256_verified}
                                previousLabel={copy.previous_preview}
                                nextLabel={copy.next_preview}
                                separator={copy.separator}
                            />
                        )}
                        {meta.extractedTextPreview && (
                            <section className="flex flex-col gap-2">
                                <h3 className="font-semibold">
                                    {copy.extracted_preview}
                                </h3>
                                <p className="max-h-72 overflow-y-auto rounded-lg border bg-muted p-4 text-sm leading-6 whitespace-pre-wrap">
                                    {meta.extractedTextPreview}
                                </p>
                            </section>
                        )}
                        <Button asChild variant="outline">
                            <a href={download.url(args)}>
                                {copy.download_original_document}
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
                        <SheetTitle>{copy.manage_document}</SheetTitle>
                        <SheetDescription>
                            {copy.manage_description}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-6 px-4 pb-6">
                        {county && <CountyIdentity county={county} />}
                        <Form {...update.form(args)} className="grid gap-4">
                            {({ processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor={`title-${documentId}`}>
                                            {copy.title}
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
                                                {copy.category}
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
                                            label={copy.document_date}
                                            defaultValue={
                                                meta.documentDate ?? ''
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`description-${documentId}`}
                                        >
                                            {copy.description}
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
                                                {copy.tags}
                                            </Label>
                                            <Input
                                                id={`tags-${documentId}`}
                                                name="tags"
                                                defaultValue={meta.tags ?? ''}
                                                placeholder={
                                                    copy.tags_placeholder
                                                }
                                            />
                                        </div>
                                        <DatePickerField
                                            name="retention_until"
                                            label={copy.retain_until}
                                            defaultValue={
                                                meta.retentionUntil ?? ''
                                            }
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {copy.save_metadata}
                                    </Button>
                                </>
                            )}
                        </Form>
                        <section className="grid gap-3 border-t pt-5">
                            <div>
                                <h3 className="font-semibold">
                                    {copy.versions_title}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    {interpolate(copy.version_integrity, {
                                        version: meta.version ?? '1',
                                        checksum:
                                            meta.checksum?.slice(0, 12) ?? '—',
                                        scan: meta.scanStatus ?? 'pending',
                                        ocr:
                                            meta.ocrStatus ??
                                            copy.not_requested,
                                    })}
                                    {meta.extractionCompletedAt
                                        ? interpolate(copy.completed_at, {
                                              date: new Date(
                                                  meta.extractionCompletedAt,
                                              ).toLocaleString(locale),
                                          })
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
                                            placeholder={
                                                copy.replacement_reason
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            {copy.upload_version}
                                        </Button>
                                    </>
                                )}
                            </Form>
                            <div className="grid gap-3">
                                {versions.length === 0 ? (
                                    <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                        {copy.no_versions}
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
                                                                {interpolate(
                                                                    copy.version_number,
                                                                    {
                                                                        number: version.number,
                                                                    },
                                                                )}
                                                            </h4>
                                                            {version.isCurrent && (
                                                                <Badge>
                                                                    {
                                                                        copy.current
                                                                    }
                                                                </Badge>
                                                            )}
                                                            <Badge variant="outline">
                                                                {
                                                                    version.scanStatus
                                                                }
                                                            </Badge>
                                                        </div>
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {interpolate(
                                                                copy.uploaded_by,
                                                                {
                                                                    name: version.originalName,
                                                                    actor: version.uploadedBy,
                                                                    date: new Date(
                                                                        version.createdAt,
                                                                    ).toLocaleString(
                                                                        locale,
                                                                    ),
                                                                },
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
                                                                        {
                                                                            copy.preview
                                                                        }
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
                                                                    {
                                                                        copy.download
                                                                    }
                                                                </a>
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                                <p className="text-sm">
                                                    {version.changeSummary ??
                                                        copy.initial_version}
                                                </p>
                                                <p className="font-mono text-[11px] break-all text-muted-foreground">
                                                    {interpolate(
                                                        copy.version_manifest,
                                                        {
                                                            checksum:
                                                                version.checksum,
                                                            bytes: version.sizeBytes.toLocaleString(
                                                                locale,
                                                            ),
                                                            status: version.ocrStatus,
                                                        },
                                                    )}
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
                                            {copy.place_hold}
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            {copy.place_hold_description}
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
                                                    placeholder={
                                                        copy.hold_reference
                                                    }
                                                />
                                                <Input
                                                    name="authority"
                                                    required
                                                    placeholder={
                                                        copy.issuing_authority
                                                    }
                                                />
                                                <Input
                                                    name="reason"
                                                    required
                                                    placeholder={
                                                        copy.hold_reason
                                                    }
                                                />
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    {copy.place_hold}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </section>
                            )}
                        {meta.activeLegalHold === 'true' && (
                            <p className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                                {copy.under_hold}
                            </p>
                        )}
                        <section className="grid gap-3 border-t pt-5">
                            <div>
                                <h3 className="font-semibold">
                                    {copy.hold_history}
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    {copy.hold_history_description}
                                </p>
                            </div>
                            {legalHolds.length === 0 ? (
                                <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                    {copy.no_holds}
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
                                                    {interpolate(
                                                        copy.hold_placed,
                                                        {
                                                            authority:
                                                                hold.authority,
                                                            actor: hold.placedBy,
                                                            date: new Date(
                                                                hold.placedAt,
                                                            ).toLocaleString(
                                                                locale,
                                                            ),
                                                        },
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
                                                    ? copy.released
                                                    : copy.active}
                                            </Badge>
                                        </div>
                                        <p className="text-sm">{hold.reason}</p>
                                        {hold.releasedAt && (
                                            <p className="rounded-md bg-muted p-3 text-xs">
                                                {interpolate(
                                                    copy.hold_released,
                                                    {
                                                        actor:
                                                            hold.releasedBy ??
                                                            copy.unknown,
                                                        date: new Date(
                                                            hold.releasedAt,
                                                        ).toLocaleString(
                                                            locale,
                                                        ),
                                                        reason:
                                                            hold.releaseReason ??
                                                            '',
                                                    },
                                                )}
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
                                                                {
                                                                    copy.release_reason
                                                                }
                                                            </Label>
                                                            <Input
                                                                id={`release-reason-${hold.id}`}
                                                                name="release_reason"
                                                                required
                                                                placeholder={
                                                                    copy.release_placeholder
                                                                }
                                                            />
                                                            <Button
                                                                type="submit"
                                                                variant="outline"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {
                                                                    copy.release_hold
                                                                }
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
                                    {copy.archive_document}
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
    args: { document: string };
    documentId: string;
    dispositions: DocumentDisposition[];
    canManageRecords: boolean;
    hasOpenDisposition: boolean;
    retentionUntil: string | null;
    recordStatus: string | null;
}) {
    const copy = usePage().props.localization.evidence;

    return (
        <>
            {canManageRecords &&
                recordStatus !== 'disposed' &&
                !hasOpenDisposition && (
                    <section className="grid gap-3 border-t pt-5">
                        <div>
                            <h3 className="font-semibold">
                                {copy.request_disposition}
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                {copy.disposition_description}
                            </p>
                        </div>
                        {!retentionUntil ? (
                            <Alert>
                                <AlertTitle>
                                    {copy.retention_required}
                                </AlertTitle>
                                <AlertDescription>
                                    {copy.retention_required_description}
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
                                                {copy.authority_reference}
                                            </Label>
                                            <Input
                                                id={`disposition-authority-${documentId}`}
                                                name="authority_reference"
                                                required
                                                placeholder={
                                                    copy.authority_placeholder
                                                }
                                            />
                                        </div>
                                        <DatePickerField
                                            name="scheduled_for"
                                            label={copy.scheduled_date}
                                            min={retentionUntil}
                                            required
                                        />
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`disposition-reason-${documentId}`}
                                            >
                                                {copy.disposition_reason}
                                            </Label>
                                            <Textarea
                                                id={`disposition-reason-${documentId}`}
                                                name="reason"
                                                required
                                                placeholder={
                                                    copy.disposition_placeholder
                                                }
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {copy.submit_disposition}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </section>
                )}
            <section className="grid gap-3 border-t pt-5">
                <div>
                    <h3 className="font-semibold">
                        {copy.disposition_history}
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        {copy.disposition_history_description}
                    </p>
                </div>
                {dispositions.length === 0 ? (
                    <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        {copy.no_dispositions}
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
    args: { document: string };
    disposition: DocumentDisposition;
    canManageRecords: boolean;
}) {
    const { current: locale, evidence: copy } = usePage().props.localization;
    const dispositionArgs = { ...args, disposition: disposition.id };

    return (
        <article className="grid gap-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h4 className="font-medium">
                        {interpolate(copy.secure_destruction, {
                            reference: disposition.authorityReference,
                        })}
                    </h4>
                    <p className="text-xs text-muted-foreground">
                        {interpolate(copy.requested_by, {
                            actor: disposition.requestedBy,
                            date: new Date(
                                disposition.requestedAt,
                            ).toLocaleString(locale),
                        })}
                    </p>
                </div>
                <Badge variant="outline">{disposition.status}</Badge>
            </div>
            <p className="text-sm">{disposition.reason}</p>
            <p className="text-xs text-muted-foreground">
                {interpolate(copy.retention_schedule, {
                    due: disposition.retentionDueAt,
                    scheduled: disposition.scheduledFor,
                })}
            </p>
            {disposition.reviewedAt && (
                <p className="rounded-md bg-muted p-3 text-xs">
                    {interpolate(copy.reviewed_by, {
                        actor: disposition.reviewedBy ?? copy.unknown,
                        date: new Date(disposition.reviewedAt).toLocaleString(
                            locale,
                        ),
                        reason: disposition.decisionReason ?? '',
                    })}
                </p>
            )}
            {disposition.executionError && (
                <Alert variant="destructive">
                    <AlertTitle>{copy.execution_stopped}</AlertTitle>
                    <AlertDescription>
                        {disposition.executionError}
                    </AlertDescription>
                </Alert>
            )}
            {disposition.executedAt && (
                <div className="grid gap-1 rounded-md bg-muted p-3 text-xs">
                    <p>
                        {interpolate(copy.executed_by, {
                            actor: disposition.executedBy ?? copy.unknown,
                            date: new Date(
                                disposition.executedAt,
                            ).toLocaleString(locale),
                            objects: disposition.objectCount,
                            bytes: disposition.totalBytes.toLocaleString(
                                locale,
                            ),
                        })}
                    </p>
                    <p className="font-mono break-all">
                        {interpolate(copy.manifest_checksum, {
                            checksum: disposition.manifestChecksum ?? '—',
                        })}
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
                                {copy.independent_reason}
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
                                    {copy.approve}
                                </Button>
                                <Button
                                    type="submit"
                                    name="decision"
                                    value="rejected"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    {copy.reject}
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
                            {copy.execute_destruction}
                        </Button>
                    )}
                </Form>
            )}
        </article>
    );
}
