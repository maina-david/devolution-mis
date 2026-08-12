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
import { download, preview } from '@/routes/evidence';
import { store } from '@/routes/igr-resolutions/documents';

type Props = {
    resolutionId: string;
    status: string;
    documents: WorkspaceDocument[];
    canUpload: boolean;
};

export default function IgrDocumentControls(props: Props) {
    const [previewDocument, setPreviewDocument] =
        useState<WorkspaceDocument | null>(null);

    return (
        <div className="flex flex-wrap gap-2">
            {props.canUpload && <UploadRecord {...props} />}
            <Sheet>
                <SheetTrigger asChild>
                    <Button type="button" size="sm" variant="outline">
                        <Files aria-hidden="true" />
                        Records ({props.documents.length})
                    </Button>
                </SheetTrigger>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Governed IGR records</SheetTitle>
                        <SheetDescription>
                            Private, checksum-bound resolution and
                            implementation records.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-3 px-4 pb-8">
                        {props.documents.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No repository records have been linked yet.
                            </p>
                        )}
                        {props.documents.map((document) => (
                            <div
                                key={document.id}
                                className="grid gap-3 rounded-lg border p-3"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="grid gap-1">
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
                                                .replace('igr-', '')
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
                                                <Eye aria-hidden="true" />
                                                Preview
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
                                                <Download aria-hidden="true" />
                                                Download
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
                            {previewDocument?.title ?? 'IGR record'}
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

function UploadRecord(props: Props) {
    const purposes =
        props.status === 'open'
            ? [
                  { id: 'resolution', name: 'Adopted resolution' },
                  {
                      id: 'implementation_evidence',
                      name: 'Implementation evidence',
                  },
              ]
            : [
                  {
                      id: 'implementation_evidence',
                      name: 'Implementation evidence',
                  },
              ];

    return (
        <FormSheet
            title="Upload IGR record"
            triggerLabel="Upload record"
            icon={Upload}
            description="Add a private scanned or born-digital resolution or implementation record."
        >
            <Form
                {...store.form({ resolution: props.resolutionId })}
                resetOnSuccess
                className="grid gap-4"
            >
                {({ errors, processing, progress }) => (
                    <>
                        <SearchableSelect
                            id={`igr-purpose-${props.resolutionId}`}
                            name="record_purpose"
                            label="Record purpose"
                            defaultValue={purposes[0].id}
                            options={purposes}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor={`igr-title-${props.resolutionId}`}>
                                Record title
                            </Label>
                            <Input
                                id={`igr-title-${props.resolutionId}`}
                                name="title"
                                required
                                aria-invalid={Boolean(errors.title)}
                                aria-describedby={
                                    errors.title
                                        ? `igr-title-error-${props.resolutionId}`
                                        : undefined
                                }
                            />
                            {errors.title && (
                                <p
                                    id={`igr-title-error-${props.resolutionId}`}
                                    className="text-sm text-destructive"
                                >
                                    {errors.title}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`igr-category-${props.resolutionId}`}
                            >
                                Category
                            </Label>
                            <Input
                                id={`igr-category-${props.resolutionId}`}
                                name="category"
                                defaultValue="IGR resolution record"
                                required
                                aria-invalid={Boolean(errors.category)}
                            />
                        </div>
                        <SearchableSelect
                            id={`igr-source-${props.resolutionId}`}
                            name="source_type"
                            label="Source type"
                            defaultValue="digital"
                            options={[
                                { id: 'digital', name: 'Born-digital' },
                                { id: 'scanned', name: 'Scanned original' },
                            ]}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor={`igr-file-${props.resolutionId}`}>
                                File
                            </Label>
                            <Input
                                id={`igr-file-${props.resolutionId}`}
                                name="document"
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                aria-invalid={Boolean(errors.document)}
                                aria-describedby={
                                    errors.document
                                        ? `igr-file-error-${props.resolutionId}`
                                        : undefined
                                }
                            />
                            {errors.document && (
                                <p
                                    id={`igr-file-error-${props.resolutionId}`}
                                    className="text-sm text-destructive"
                                >
                                    {errors.document}
                                </p>
                            )}
                        </div>
                        {progress && (
                            <p role="status" className="text-sm">
                                Uploading: {progress.percentage}%
                            </p>
                        )}
                        <Button type="submit" disabled={processing}>
                            Upload securely
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function supportsPreview(document: WorkspaceDocument): boolean {
    return (
        document.mimeType === 'application/pdf' ||
        document.mimeType?.startsWith('image/') === true ||
        document.mimeType === 'text/plain'
    );
}
