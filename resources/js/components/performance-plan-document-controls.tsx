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
import { store } from '@/routes/departmental-performance/plans/documents';
import { download, preview } from '@/routes/evidence';

type Props = {
    teamSlug: string;
    planId: string;
    status: string;
    documents: WorkspaceDocument[];
    canUpload: boolean;
    isEmployee: boolean;
};

export default function PerformancePlanDocumentControls(props: Props) {
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
                        <SheetTitle>Governed performance records</SheetTitle>
                        <SheetDescription>
                            Private, checksum-bound goal plans, self-review
                            evidence, and signed final appraisals.
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
                                                .replace('performance-', '')
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
                            {previewDocument?.title ?? 'Performance record'}
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
    const purpose =
        props.status === 'draft'
            ? { id: 'goal_plan', name: 'Signed goal plan' }
            : props.isEmployee
              ? {
                    id: 'self_review_evidence',
                    name: 'Self-review evidence',
                }
              : { id: 'final_appraisal', name: 'Signed final appraisal' };

    return (
        <FormSheet
            title={`Upload ${purpose.name.toLowerCase()}`}
            triggerLabel="Upload record"
            icon={Upload}
            description="Add a private scanned or born-digital performance record."
        >
            <Form
                {...store.form({
                    current_team: props.teamSlug,
                    performancePlan: props.planId,
                })}
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
                                Record title
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
                                Category
                            </Label>
                            <Input
                                id={`performance-category-${props.planId}`}
                                name="category"
                                defaultValue="Performance appraisal"
                                required
                            />
                        </div>
                        <SearchableSelect
                            id={`performance-source-${props.planId}`}
                            name="source_type"
                            label="Source type"
                            defaultValue="digital"
                            options={[
                                { id: 'digital', name: 'Born-digital' },
                                { id: 'scanned', name: 'Scanned original' },
                            ]}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor={`performance-file-${props.planId}`}>
                                File
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
