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
import { store as storeActionDocument } from '@/routes/dswg/actions/documents';
import { store as storeMeetingDocument } from '@/routes/dswg/meetings/documents';
import { download, preview } from '@/routes/evidence';

type Props = {
    teamSlug: string;
    subjectId: string;
    subjectType: 'meeting' | 'action';
    documents: WorkspaceDocument[];
    canUpload: boolean;
    meetingStatus?: string;
};

export default function DswgDocumentControls(props: Props) {
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
                        <SheetTitle>Governed DSWG records</SheetTitle>
                        <SheetDescription>
                            Private, checksum-bound records linked to this{' '}
                            {props.subjectType}.
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
                                                .replace('dswg-', '')
                                                .replace('-record', '')
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
                                                    current_team:
                                                        props.teamSlug,
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
                            {previewDocument?.title ?? 'DSWG record'}
                        </SheetTitle>
                        <SheetDescription>
                            Authorized preview from the private repository.
                        </SheetDescription>
                    </SheetHeader>
                    {previewDocument && (
                        <iframe
                            title={`Preview ${previewDocument.title}`}
                            src={preview.url({
                                current_team: props.teamSlug,
                                document: previewDocument.id,
                            })}
                            className="h-[75vh] w-full border-0 px-4 pb-4"
                        />
                    )}
                </SheetContent>
            </Sheet>
        </div>
    );
}

function UploadRecord(props: Props) {
    const meetingPurposes =
        props.meetingStatus === 'scheduled'
            ? [
                  { id: 'agenda', name: 'Agenda' },
                  { id: 'supporting', name: 'Supporting material' },
              ]
            : [
                  { id: 'minutes', name: 'Minutes' },
                  { id: 'supporting', name: 'Supporting material' },
              ];
    const route =
        props.subjectType === 'meeting'
            ? storeMeetingDocument.form({
                  current_team: props.teamSlug,
                  meeting: props.subjectId,
              })
            : storeActionDocument.form({
                  current_team: props.teamSlug,
                  action: props.subjectId,
              });

    return (
        <FormSheet
            title={`Upload ${props.subjectType} record`}
            triggerLabel="Upload record"
            icon={Upload}
            description="Add a private scanned or born-digital record. The repository records integrity, security-scan and extraction state."
        >
            <Form {...route} resetOnSuccess className="grid gap-4">
                {({ errors, processing, progress }) => (
                    <>
                        {props.subjectType === 'meeting' && (
                            <SearchableSelect
                                id={`dswg-purpose-${props.subjectId}`}
                                name="record_purpose"
                                label="Record purpose"
                                defaultValue={meetingPurposes[0].id}
                                options={meetingPurposes}
                            />
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor={`dswg-title-${props.subjectId}`}>
                                Record title
                            </Label>
                            <Input
                                id={`dswg-title-${props.subjectId}`}
                                name="title"
                                required
                                aria-invalid={Boolean(errors.title)}
                                aria-describedby={
                                    errors.title
                                        ? `dswg-title-error-${props.subjectId}`
                                        : undefined
                                }
                            />
                            {errors.title && (
                                <p
                                    id={`dswg-title-error-${props.subjectId}`}
                                    className="text-sm text-destructive"
                                >
                                    {errors.title}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`dswg-category-${props.subjectId}`}>
                                Category
                            </Label>
                            <Input
                                id={`dswg-category-${props.subjectId}`}
                                name="category"
                                defaultValue={
                                    props.subjectType === 'meeting'
                                        ? 'Meeting record'
                                        : 'Action evidence'
                                }
                                required
                                aria-invalid={Boolean(errors.category)}
                            />
                        </div>
                        <SearchableSelect
                            id={`dswg-source-${props.subjectId}`}
                            name="source_type"
                            label="Source type"
                            defaultValue="digital"
                            options={[
                                { id: 'digital', name: 'Born-digital' },
                                { id: 'scanned', name: 'Scanned original' },
                            ]}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor={`dswg-file-${props.subjectId}`}>
                                File
                            </Label>
                            <Input
                                id={`dswg-file-${props.subjectId}`}
                                name="document"
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                aria-invalid={Boolean(errors.document)}
                                aria-describedby={
                                    errors.document
                                        ? `dswg-file-error-${props.subjectId}`
                                        : undefined
                                }
                            />
                            {errors.document && (
                                <p
                                    id={`dswg-file-error-${props.subjectId}`}
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
