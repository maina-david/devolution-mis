import { Form, usePage } from '@inertiajs/react';
import { Download, Eye, Files, Upload } from 'lucide-react';
import { useState } from 'react';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { WorkspaceDocument } from '@/components/workspace-data-table';
import { interpolate, useCommonCopy } from '@/hooks/use-localization';
import { store } from '@/routes/data-governance/privacy-incidents/documents';
import { download, preview } from '@/routes/evidence';

export default function PrivacyIncidentDocumentControls({
    incidentId,
    status,
    documents,
    canUpload,
}: {
    incidentId: string;
    status: string;
    documents: WorkspaceDocument[];
    canUpload: boolean;
}) {
    const commonCopy = useCommonCopy();
    const copy = usePage().props.localization.privacyDocuments;
    const [previewDocument, setPreviewDocument] =
        useState<WorkspaceDocument | null>(null);

    return (
        <div className="flex flex-wrap gap-2">
            {canUpload && status !== 'closed' && (
                <UploadRecord incidentId={incidentId} status={status} />
            )}
            <Sheet>
                <SheetTrigger asChild>
                    <Button type="button" size="sm" variant="outline">
                        <Files /> {copy.records} {'('}
                        {documents.length}
                        {')'}
                    </Button>
                </SheetTrigger>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{copy.private_breach_records}</SheetTitle>
                        <SheetDescription>
                            {copy.private_records_description}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-3 px-4 pb-8">
                        {documents.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {copy.no_records}
                            </p>
                        )}
                        {documents.map((document) => (
                            <div
                                key={document.id}
                                className="grid gap-3 rounded-lg border p-3"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="text-sm font-medium">
                                            {document.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {document.originalName ??
                                                copy.repository_record}{' '}
                                            {'·'}{' '}
                                            {copy[document.sourceType] ??
                                                document.sourceType}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Badge variant="outline">
                                            {document.purpose
                                                .replace(
                                                    'privacy-incident-',
                                                    '',
                                                )
                                                .replaceAll('-', ' ')}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {copy[document.scanStatus] ??
                                                document.scanStatus}
                                        </Badge>
                                    </div>
                                </div>
                                {document.scanStatus === 'clean' && (
                                    <div className="flex flex-wrap gap-2">
                                        {supportsPreview(document) && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setPreviewDocument(document)
                                                }
                                            >
                                                <Eye /> {copy.preview}
                                            </Button>
                                        )}
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a
                                                href={download.url({
                                                    document: document.id,
                                                })}
                                            >
                                                <Download /> {copy.download}
                                            </a>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </SheetContent>
            </Sheet>
            <Sheet
                open={previewDocument !== null}
                onOpenChange={(open) => !open && setPreviewDocument(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-4xl">
                    <SheetHeader>
                        <SheetTitle>
                            {previewDocument?.title ?? copy.incident_evidence}
                        </SheetTitle>
                        <SheetDescription>
                            {copy.authorized_preview}
                        </SheetDescription>
                    </SheetHeader>
                    {previewDocument && (
                        <iframe
                            title={interpolate(commonCopy.preview_document, {
                                title: previewDocument.title,
                            })}
                            src={preview.url({ document: previewDocument.id })}
                            className="h-[75vh] w-full border-0 px-4 pb-4"
                        />
                    )}
                </SheetContent>
            </Sheet>
        </div>
    );
}

function UploadRecord({
    incidentId,
    status,
}: {
    incidentId: string;
    status: string;
}) {
    const copy = usePage().props.localization.privacyDocuments;
    const purposes = [
        { id: 'investigation', name: copy.investigation_evidence },
    ];

    if (['notification_required', 'remediation'].includes(status)) {
        purposes.push({ id: 'notification', name: copy.notification_evidence });
    }

    if (status === 'remediation') {
        purposes.push({ id: 'closure', name: copy.closure_evidence });
    }

    return (
        <FormSheet
            title={copy.upload_incident_evidence}
            triggerLabel={copy.upload_evidence}
            icon={Upload}
            description={copy.upload_description}
        >
            <Form
                {...store.form({ privacyIncident: incidentId })}
                resetOnSuccess
                className="grid gap-4"
            >
                {({ errors, processing, progress }) => (
                    <>
                        <SearchableSelect
                            id={`incident-purpose-${incidentId}`}
                            name="record_purpose"
                            label={copy.record_purpose}
                            options={purposes}
                            defaultValue={purposes[0].id}
                        />
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`incident-document-title-${incidentId}`}
                            >
                                {copy.record_title}
                            </Label>
                            <Input
                                id={`incident-document-title-${incidentId}`}
                                name="title"
                                required
                                aria-invalid={Boolean(errors.title)}
                            />
                            {errors.title && (
                                <p className="text-sm text-destructive">
                                    {errors.title}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`incident-document-category-${incidentId}`}
                            >
                                {copy.category}
                            </Label>
                            <Input
                                id={`incident-document-category-${incidentId}`}
                                name="category"
                                defaultValue={copy.breach_evidence}
                                required
                            />
                        </div>
                        <SearchableSelect
                            id={`incident-source-${incidentId}`}
                            name="source_type"
                            label={copy.source_type}
                            options={[
                                { id: 'scanned', name: copy.scanned },
                                { id: 'digital', name: copy.digital },
                            ]}
                            defaultValue="digital"
                        />
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`incident-document-file-${incidentId}`}
                            >
                                {copy.document_file}
                            </Label>
                            <Input
                                id={`incident-document-file-${incidentId}`}
                                name="document"
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            />
                            {errors.document && (
                                <p className="text-sm text-destructive">
                                    {errors.document}
                                </p>
                            )}
                        </div>
                        {progress && (
                            <p className="text-sm text-muted-foreground">
                                {copy.uploading} {progress.percentage}
                                {'%'}
                            </p>
                        )}
                        <Button type="submit" disabled={processing}>
                            <Upload />{' '}
                            {processing
                                ? copy.uploading_progress
                                : copy.upload_secure_evidence}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function supportsPreview(document: WorkspaceDocument): boolean {
    return [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
    ].includes(document.mimeType ?? '');
}
