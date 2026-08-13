import { Form, usePage } from '@inertiajs/react';
import { Download, Eye, FileCheck2, Upload } from 'lucide-react';
import { useState } from 'react';
import { storePartnerAgreementChange } from '@/actions/App/Http/Controllers/LinkedDocumentController';
import {
    decideAgreementChange,
    storeAgreementChange,
} from '@/actions/App/Http/Controllers/PartnerCoordinationController';
import DatePickerField from '@/components/date-picker-field';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { download, preview } from '@/routes/evidence';
import { transition } from '@/routes/partners/agreements';
import { store as storeDocument } from '@/routes/partners/agreements/documents';

type AgreementDocument = {
    id: string;
    title: string;
    category: string;
    sourceType: string;
    originalName: string;
    mimeType: string;
    scanStatus: string;
    ocrStatus: string;
};
type AgreementChange = {
    id: string;
    version: number;
    type: string;
    proposedChanges: Record<string, string>;
    reason: string;
    effectiveOn: string;
    requester: string;
    requestedAt: string;
    requestChecksum: string;
    decision: {
        result: string;
        note: string;
        decider: string;
        decidedAt: string;
        checksum: string;
    } | null;
    documents: Array<{
        id: string;
        title: string;
        mimeType: string;
        scanStatus: string;
        recordStatus: string;
    }>;
    canUpload: boolean;
    canDecide: boolean;
};

export type PartnerAgreement = {
    id: string;
    partner: string;
    reference: string;
    title: string;
    type: string;
    startsOn: string;
    endsOn: string | null;
    status: string;
    workflowState: string | null;
    dueAt: string | null;
    canUpload: boolean;
    documents: AgreementDocument[];
    changeRequests: AgreementChange[];
    canRequestChange: boolean;
};

function usePartnerCopy(): Record<string, string> {
    return usePage().props.localization.partnerCoordination;
}

export default function PartnerAgreementRegister({
    agreements,
    canManage,
    canApprove,
}: {
    agreements: PartnerAgreement[];
    canManage: boolean;
    canApprove: boolean;
}) {
    const copy = usePartnerCopy();
    const [previewDocument, setPreviewDocument] =
        useState<AgreementDocument | null>(null);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FileCheck2 aria-hidden="true" />
                    {copy.agreement_register}
                </CardTitle>
                <CardDescription>
                    {copy.agreement_register_description}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {agreements.length === 0 && (
                    <WorkspaceEmptyState
                        title={copy.no_agreements}
                        description={copy.no_agreements_description}
                        className="min-h-48"
                    />
                )}
                {agreements.map((agreement) => (
                    <article
                        key={agreement.id}
                        className="grid gap-4 rounded-lg border p-4"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="grid gap-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {agreement.reference}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {agreement.workflowState ??
                                            agreement.status}
                                    </Badge>
                                </div>
                                <h3 className="font-semibold">
                                    {agreement.title}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {agreement.partner} {copy.separator}{' '}
                                    {agreement.type} {copy.separator}{' '}
                                    {agreement.startsOn}
                                    {agreement.endsOn
                                        ? ` ${copy.to} ${agreement.endsOn}`
                                        : ''}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {agreement.canUpload && (
                                    <UploadAgreementDocument
                                        agreement={agreement}
                                    />
                                )}
                                {canManage &&
                                    agreement.workflowState === 'draft' && (
                                        <SubmitAgreement
                                            agreement={agreement}
                                        />
                                    )}
                                {canApprove &&
                                    agreement.workflowState ===
                                        'pending_approval' && (
                                        <AgreementDecision
                                            agreement={agreement}
                                        />
                                    )}
                                {agreement.canRequestChange && (
                                    <RequestAgreementChange
                                        agreement={agreement}
                                    />
                                )}
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <p className="text-sm font-medium">
                                {copy.repository_records}{' '}
                                {copy.open_parenthesis}
                                {agreement.documents.length}
                                {copy.close_parenthesis}
                            </p>
                            {agreement.documents.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {copy.no_repository_records}
                                </p>
                            ) : (
                                agreement.documents.map((document) => (
                                    <div
                                        key={document.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3"
                                    >
                                        <div className="grid gap-1">
                                            <p className="text-sm font-medium">
                                                {document.title}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {document.originalName}{' '}
                                                {copy.separator}{' '}
                                                {document.sourceType}{' '}
                                                {copy.separator} {copy.scan}{' '}
                                                {document.scanStatus}{' '}
                                                {copy.separator} {copy.ocr}{' '}
                                                {document.ocrStatus}
                                            </p>
                                        </div>
                                        {document.scanStatus === 'clean' && (
                                            <div className="flex gap-2">
                                                {supportsPreview(document) && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setPreviewDocument(
                                                                document,
                                                            )
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
                                                            document:
                                                                document.id,
                                                        })}
                                                    >
                                                        <Download aria-hidden="true" />
                                                        {copy.download}
                                                    </a>
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                        {agreement.changeRequests.length > 0 && (
                            <div className="grid gap-2 border-t pt-4">
                                <p className="text-sm font-medium">
                                    {copy.change_history}
                                </p>
                                {agreement.changeRequests.map((change) => (
                                    <div
                                        key={change.id}
                                        className="grid gap-3 rounded-md border p-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex gap-2">
                                                <Badge variant="outline">
                                                    {copy.version_prefix}
                                                    {change.version}
                                                </Badge>
                                                <Badge variant="secondary">
                                                    {change.type}
                                                </Badge>
                                                <Badge>
                                                    {change.decision?.result ??
                                                        'pending'}
                                                </Badge>
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                {copy.effective}{' '}
                                                {change.effectiveOn}
                                            </span>
                                        </div>
                                        <p className="text-sm">
                                            {change.reason}
                                        </p>
                                        <p className="font-mono text-[10px] break-all text-muted-foreground">
                                            {change.requestChecksum}
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {change.canUpload && (
                                                <UploadAgreementChangeEvidence
                                                    change={change}
                                                />
                                            )}
                                            {change.canDecide && (
                                                <DecideAgreementChange
                                                    change={change}
                                                />
                                            )}
                                            {change.documents.map(
                                                (document) =>
                                                    document.scanStatus ===
                                                        'clean' && (
                                                        <Button
                                                            key={document.id}
                                                            size="sm"
                                                            variant="outline"
                                                            asChild
                                                        >
                                                            <a
                                                                href={download.url(
                                                                    {
                                                                        document:
                                                                            document.id,
                                                                    },
                                                                )}
                                                            >
                                                                <Download />
                                                                {document.title}
                                                            </a>
                                                        </Button>
                                                    ),
                                            )}
                                        </div>
                                        {change.decision && (
                                            <p className="text-xs text-muted-foreground">
                                                {change.decision.decider}
                                                {copy.label_separator}{' '}
                                                {change.decision.note}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </article>
                ))}
            </CardContent>
            <Sheet
                open={previewDocument !== null}
                onOpenChange={(open) => !open && setPreviewDocument(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-4xl">
                    <SheetHeader>
                        <SheetTitle>
                            {previewDocument?.title ?? 'Agreement record'}
                        </SheetTitle>
                        <SheetDescription>
                            {copy.authorized_preview}
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
        </Card>
    );
}

function RequestAgreementChange({
    agreement,
}: {
    agreement: PartnerAgreement;
}) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title="Request agreement change"
            triggerLabel="Request change"
            description="Propose an amendment, renewal, suspension or termination without rewriting the approved agreement."
        >
            <Form
                {...storeAgreementChange.form({ agreement: agreement.id })}
                className="grid gap-4"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id={`change-type-${agreement.id}`}
                            name="change_type"
                            label="Change type"
                            options={[
                                { id: 'amendment', name: 'Amendment' },
                                { id: 'renewal', name: 'Renewal' },
                                { id: 'suspension', name: 'Suspension' },
                                { id: 'termination', name: 'Termination' },
                            ]}
                            error={errors.change_type}
                        />
                        <DatePickerField
                            name="effective_on"
                            label="Effective date"
                            required
                            min={new Date().toISOString().slice(0, 10)}
                            error={errors.effective_on}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor={`change-title-${agreement.id}`}>
                                {copy.revised_title}
                            </Label>
                            <Input
                                id={`change-title-${agreement.id}`}
                                name="title"
                                defaultValue={agreement.title}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`change-summary-${agreement.id}`}>
                                {copy.revised_summary}
                            </Label>
                            <Textarea
                                id={`change-summary-${agreement.id}`}
                                name="summary"
                            />
                        </div>
                        <DatePickerField
                            name="ends_on"
                            label="Revised end date"
                            defaultValue={agreement.endsOn ?? ''}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor={`change-value-${agreement.id}`}>
                                {copy.revised_value}
                            </Label>
                            <Input
                                id={`change-value-${agreement.id}`}
                                name="committed_value"
                                inputMode="decimal"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`change-reason-${agreement.id}`}>
                                {copy.reason}
                            </Label>
                            <Textarea
                                id={`change-reason-${agreement.id}`}
                                name="reason"
                                minLength={20}
                                required
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            {copy.submit_governed_request}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function UploadAgreementChangeEvidence({
    change,
}: {
    change: AgreementChange;
}) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title="Upload change evidence"
            triggerLabel="Upload evidence"
            description="Attach the signed amendment, renewal, suspension or termination instrument."
        >
            <Form
                {...storePartnerAgreementChange.form({
                    changeRequest: change.id,
                })}
                className="grid gap-4"
                resetOnSuccess
            >
                <Label>
                    {copy.record_title}
                    <Input name="title" required />
                </Label>
                <Label>
                    {copy.category}
                    <Input
                        name="category"
                        defaultValue="Agreement change"
                        required
                    />
                </Label>
                <input type="hidden" name="source_type" value="digital" />
                <Label>
                    {copy.document}
                    <Input name="document" type="file" required />
                </Label>
                <Button type="submit">
                    <Upload />
                    {copy.upload_securely}
                </Button>
            </Form>
        </FormSheet>
    );
}

function DecideAgreementChange({ change }: { change: AgreementChange }) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title="Decide agreement change"
            triggerLabel="Record decision"
            description="An independent approver reviews the clean evidence and records an immutable decision."
        >
            <Form
                {...decideAgreementChange.form({ changeRequest: change.id })}
                className="grid gap-4"
            >
                <SearchableSelect
                    id={`change-decision-${change.id}`}
                    name="decision"
                    label="Decision"
                    options={[
                        { id: 'approved', name: 'Approve' },
                        { id: 'rejected', name: 'Reject' },
                    ]}
                />
                <Label>
                    {copy.decision_note}
                    <Textarea name="decision_note" minLength={20} required />
                </Label>
                <Button type="submit">{copy.retain_decision}</Button>
            </Form>
        </FormSheet>
    );
}

function UploadAgreementDocument({
    agreement,
}: {
    agreement: PartnerAgreement;
}) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title="Upload agreement record"
            triggerLabel="Upload record"
            icon={Upload}
            description="Add a privately stored scanned or born-digital copy. Files are checksum-bound and security scanned."
        >
            <Form
                {...storeDocument.form({ agreement: agreement.id })}
                resetOnSuccess
                className="grid gap-4"
            >
                {({ errors, processing, progress }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor={`agreement-title-${agreement.id}`}>
                                {copy.record_title}
                            </Label>
                            <Input
                                id={`agreement-title-${agreement.id}`}
                                name="title"
                                required
                                aria-invalid={Boolean(errors.title)}
                                aria-describedby={
                                    errors.title
                                        ? `agreement-title-error-${agreement.id}`
                                        : undefined
                                }
                            />
                            {errors.title && (
                                <p
                                    id={`agreement-title-error-${agreement.id}`}
                                    className="text-sm text-destructive"
                                >
                                    {errors.title}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`agreement-category-${agreement.id}`}
                            >
                                {copy.category}
                            </Label>
                            <Input
                                id={`agreement-category-${agreement.id}`}
                                name="category"
                                defaultValue="agreement"
                                required
                                aria-invalid={Boolean(errors.category)}
                            />
                        </div>
                        <SearchableSelect
                            id={`agreement-source-${agreement.id}`}
                            name="source_type"
                            label="Source type"
                            defaultValue="digital"
                            options={[
                                { id: 'digital', name: 'Born-digital' },
                                { id: 'scanned', name: 'Scanned original' },
                            ]}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor={`agreement-file-${agreement.id}`}>
                                {copy.agreement_file}
                            </Label>
                            <Input
                                id={`agreement-file-${agreement.id}`}
                                name="document"
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                aria-invalid={Boolean(errors.document)}
                                aria-describedby={
                                    errors.document
                                        ? `agreement-file-error-${agreement.id}`
                                        : undefined
                                }
                            />
                            {errors.document && (
                                <p
                                    id={`agreement-file-error-${agreement.id}`}
                                    className="text-sm text-destructive"
                                >
                                    {errors.document}
                                </p>
                            )}
                        </div>
                        {progress && (
                            <p role="status" className="text-sm">
                                {copy.uploading}
                                {copy.label_separator} {progress.percentage}
                                {copy.percent}
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

function AgreementDecision({ agreement }: { agreement: PartnerAgreement }) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title="Review agreement"
            triggerLabel="Record decision"
            description="Approve or reject this independently submitted agreement. The submitter cannot make this decision."
        >
            <Form
                {...transition.form({ agreement: agreement.id })}
                className="grid gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <SearchableSelect
                            id={`agreement-decision-${agreement.id}`}
                            name="transition"
                            label="Decision"
                            defaultValue="approve"
                            options={[
                                { id: 'approve', name: 'Approve agreement' },
                                { id: 'reject', name: 'Reject agreement' },
                            ]}
                        />
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`agreement-comment-${agreement.id}`}
                            >
                                {copy.decision_rationale}
                            </Label>
                            <Textarea
                                id={`agreement-comment-${agreement.id}`}
                                name="comment"
                                aria-invalid={Boolean(errors.comment)}
                                aria-describedby={
                                    errors.comment
                                        ? `agreement-comment-error-${agreement.id}`
                                        : undefined
                                }
                            />
                            {errors.comment && (
                                <p
                                    id={`agreement-comment-error-${agreement.id}`}
                                    className="text-sm text-destructive"
                                >
                                    {errors.comment}
                                </p>
                            )}
                        </div>
                        <Button type="submit" disabled={processing}>
                            {copy.record_decision}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function SubmitAgreement({ agreement }: { agreement: PartnerAgreement }) {
    const copy = usePartnerCopy();
    const hasDocument = agreement.documents.length > 0;

    return (
        <FormSheet
            title="Submit agreement for approval"
            triggerLabel="Submit for approval"
            description="Confirm the repository record is complete. Submission locks further uploads and sends the agreement for an independent decision."
        >
            <Form
                {...transition.form({ agreement: agreement.id })}
                className="grid gap-4"
            >
                {({ processing }) => (
                    <>
                        <input type="hidden" name="transition" value="submit" />
                        <p className="text-sm text-muted-foreground">
                            {hasDocument
                                ? `${agreement.documents.length} repository record(s) will be bound to this submission.`
                                : 'Upload at least one agreement record before submission is available.'}
                        </p>
                        <Button
                            type="submit"
                            disabled={processing || !hasDocument}
                        >
                            {copy.confirm_submission}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function supportsPreview(document: AgreementDocument): boolean {
    return (
        document.mimeType === 'application/pdf' ||
        document.mimeType.startsWith('image/') ||
        document.mimeType === 'text/plain'
    );
}
