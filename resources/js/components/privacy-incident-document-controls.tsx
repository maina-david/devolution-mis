import { Form } from '@inertiajs/react';
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
                        <Files /> Records ({documents.length})
                    </Button>
                </SheetTrigger>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Private breach records</SheetTitle>
                        <SheetDescription>
                            Checksum-bound scanned and born-digital
                            investigation, notification and closure evidence.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-3 px-4 pb-8">
                        {documents.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No governed evidence records have been linked.
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
                                                'Repository record'}{' '}
                                            · {document.sourceType}
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
                                            {document.scanStatus}
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
                                                <Eye /> Preview
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
                                                <Download /> Download
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
                            {previewDocument?.title ?? 'Incident evidence'}
                        </SheetTitle>
                        <SheetDescription>
                            Authorized preview from the private repository.
                        </SheetDescription>
                    </SheetHeader>
                    {previewDocument && (
                        <iframe
                            title={`Preview ${previewDocument.title}`}
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
    const purposes = [{ id: 'investigation', name: 'Investigation evidence' }];

    if (['notification_required', 'remediation'].includes(status)) {
        purposes.push({ id: 'notification', name: 'Notification evidence' });
    }

    if (status === 'remediation') {
        purposes.push({ id: 'closure', name: 'Closure evidence' });
    }

    return (
        <FormSheet
            title="Upload incident evidence"
            triggerLabel="Upload evidence"
            icon={Upload}
            description="Add a private scanned or born-digital breach record."
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
                            label="Record purpose"
                            options={purposes}
                            defaultValue={purposes[0].id}
                        />
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`incident-document-title-${incidentId}`}
                            >
                                Record title
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
                                Category
                            </Label>
                            <Input
                                id={`incident-document-category-${incidentId}`}
                                name="category"
                                defaultValue="Personal data breach evidence"
                                required
                            />
                        </div>
                        <SearchableSelect
                            id={`incident-source-${incidentId}`}
                            name="source_type"
                            label="Source type"
                            options={[
                                { id: 'scanned', name: 'Scanned original' },
                                { id: 'digital', name: 'Born digital' },
                            ]}
                            defaultValue="digital"
                        />
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`incident-document-file-${incidentId}`}
                            >
                                Document file
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
                                Uploading {progress.percentage}%
                            </p>
                        )}
                        <Button type="submit" disabled={processing}>
                            <Upload />{' '}
                            {processing
                                ? 'Uploading…'
                                : 'Upload secure evidence'}
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
