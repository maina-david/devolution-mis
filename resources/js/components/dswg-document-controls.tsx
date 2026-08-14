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
import { store as storeActionDocument } from '@/routes/dswg/actions/documents';
import { store as storeMeetingDocument } from '@/routes/dswg/meetings/documents';
import { download, preview } from '@/routes/evidence';

type Props = {
    subjectId: string;
    subjectType: 'meeting' | 'action';
    documents: WorkspaceDocument[];
    canUpload: boolean;
    meetingStatus?: string;
};

export default function DswgDocumentControls(props: Props) {
    const commonCopy = useCommonCopy();
    const copy = usePage().props.localization.dswg;
    const [previewDocument, setPreviewDocument] =
        useState<WorkspaceDocument | null>(null);

    return (
        <div className="flex flex-wrap gap-2">
            {props.canUpload && <UploadRecord {...props} />}
            <Sheet>
                <SheetTrigger asChild>
                    <Button type="button" size="sm" variant="outline">
                        <Files aria-hidden="true" />
                        {copy.records} {'('}
                        {props.documents.length}
                        {')'}
                    </Button>
                </SheetTrigger>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{copy.governed_records}</SheetTitle>
                        <SheetDescription>
                            {copy.private_records} {copy[props.subjectType]}
                            {'.'}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-3 px-4 pb-8">
                        {props.documents.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {copy.no_repository_records}
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
                                                copy.repository_record}{' '}
                                            {'·'}{' '}
                                            {copy[document.sourceType] ??
                                                document.sourceType}
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
                                                <Eye aria-hidden="true" />
                                                {copy.preview}
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
                                                {copy.download}
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
                            {previewDocument?.title ?? copy.dswg_record}
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

function UploadRecord(props: Props) {
    const commonCopy = useCommonCopy();
    const copy = usePage().props.localization.dswg;
    const meetingPurposes =
        props.meetingStatus === 'scheduled'
            ? [
                  { id: 'agenda', name: copy.agenda },
                  { id: 'supporting', name: copy.supporting_material },
              ]
            : [
                  { id: 'minutes', name: copy.minutes },
                  { id: 'supporting', name: copy.supporting_material },
              ];
    const route =
        props.subjectType === 'meeting'
            ? storeMeetingDocument.form({ meeting: props.subjectId })
            : storeActionDocument.form({ action: props.subjectId });

    return (
        <FormSheet
            title={interpolate(commonCopy.upload_record, {
                record: `${copy[props.subjectType]} ${copy.record}`,
            })}
            triggerLabel={copy.upload_record}
            icon={Upload}
            description={copy.upload_record_description}
        >
            <Form {...route} resetOnSuccess className="grid gap-4">
                {({ errors, processing, progress }) => (
                    <>
                        {props.subjectType === 'meeting' && (
                            <SearchableSelect
                                id={`dswg-purpose-${props.subjectId}`}
                                name="record_purpose"
                                label={copy.record_purpose}
                                defaultValue={meetingPurposes[0].id}
                                options={meetingPurposes}
                            />
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor={`dswg-title-${props.subjectId}`}>
                                {copy.record_title}
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
                                {copy.category}
                            </Label>
                            <Input
                                id={`dswg-category-${props.subjectId}`}
                                name="category"
                                defaultValue={
                                    props.subjectType === 'meeting'
                                        ? copy.meeting_record
                                        : copy.action_evidence
                                }
                                required
                                aria-invalid={Boolean(errors.category)}
                            />
                        </div>
                        <SearchableSelect
                            id={`dswg-source-${props.subjectId}`}
                            name="source_type"
                            label={copy.source_type}
                            defaultValue="digital"
                            options={[
                                { id: 'digital', name: copy.digital },
                                { id: 'scanned', name: copy.scanned },
                            ]}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor={`dswg-file-${props.subjectId}`}>
                                {copy.file}
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
                                {copy.uploading}
                                {':'} {progress.percentage}
                                {'%'}
                            </p>
                        )}
                        <Button type="submit" disabled={processing}>
                            {copy.upload_securely}
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
