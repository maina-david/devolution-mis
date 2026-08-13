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
import { store } from '@/routes/departmental-performance/plans/documents';
import { download, preview } from '@/routes/evidence';

type Props = {
    planId: string;
    status: string;
    documents: WorkspaceDocument[];
    canUpload: boolean;
    isEmployee: boolean;
};

export default function PerformancePlanDocumentControls(props: Props) {
    const copy = usePage().props.localization.performanceDocuments;
    const [previewDocument, setPreviewDocument] =
        useState<WorkspaceDocument | null>(null);

    return (
        <div className="flex flex-wrap gap-2">
            {props.canUpload && <UploadRecord {...props} />}
            <Sheet>
                <SheetTrigger asChild>
                    <Button type="button" size="sm" variant="outline">
                        <Files aria-hidden="true" />
                        {copy.records} {'('}{props.documents.length}{')'}
                    </Button>
                </SheetTrigger>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{copy.governed_records}</SheetTitle>
                        <SheetDescription>
                            {copy.private_records}
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
                                                .replace('performance-', '')
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
                            {previewDocument?.title ?? copy.performance_record}
                        </SheetTitle>
                        <SheetDescription>
                            {copy.authorized_preview}
                        </SheetDescription>
                    </SheetHeader>
                    {previewDocument && (
                        <iframe
                            title={`${copy.preview} ${previewDocument.title}`}
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
    const copy = usePage().props.localization.performanceDocuments;
    const purpose =
        props.status === 'draft'
            ? { id: 'goal_plan', name: copy.signed_goal_plan }
            : props.isEmployee
              ? {
                    id: 'self_review_evidence',
                    name: copy.self_review_evidence,
                }
              : { id: 'final_appraisal', name: copy.signed_final_appraisal };

    return (
        <FormSheet
            title={`${copy.upload} ${purpose.name.toLocaleLowerCase()}`}
            triggerLabel={copy.upload_record}
            icon={Upload}
            description={copy.upload_record_description}
        >
            <Form
                {...store.form({ performancePlan: props.planId })}
                resetOnSuccess
                className="grid gap-4"
            >
                {({ errors, processing, progress }) => (
                    <>
                        <input
                            type="hidden"
                            name="record_purpose"
                            value={purpose.id}
                        />
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`performance-title-${props.planId}`}
                            >
                                {copy.record_title}
                            </Label>
                            <Input
                                id={`performance-title-${props.planId}`}
                                name="title"
                                required
                                aria-invalid={Boolean(errors.title)}
                            />
                            {errors.title && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {errors.title}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`performance-category-${props.planId}`}
                            >
                                {copy.category}
                            </Label>
                            <Input
                                id={`performance-category-${props.planId}`}
                                name="category"
                                defaultValue={copy.performance_appraisal}
                                required
                            />
                        </div>
                        <SearchableSelect
                            id={`performance-source-${props.planId}`}
                            name="source_type"
                            label={copy.source_type}
                            defaultValue="digital"
                            options={[
                                { id: 'digital', name: copy.digital },
                                { id: 'scanned', name: copy.scanned },
                            ]}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor={`performance-file-${props.planId}`}>
                                {copy.file}
                            </Label>
                            <Input
                                id={`performance-file-${props.planId}`}
                                name="document"
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                aria-invalid={Boolean(errors.document)}
                            />
                            {errors.document && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {errors.document}
                                </p>
                            )}
                        </div>
                        {progress && (
                            <p role="status" className="text-sm">
                                {copy.uploading}{':'} {progress.percentage}{'%'}
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
