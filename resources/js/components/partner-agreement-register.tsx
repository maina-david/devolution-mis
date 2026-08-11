import { Form } from '@inertiajs/react';
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

export default function PartnerAgreementRegister({
    teamSlug,
    agreements,
    canManage,
    canApprove,
}: {
    teamSlug: string;
    agreements: PartnerAgreement[];
    canManage: boolean;
    canApprove: boolean;
}) {
    const [previewDocument, setPreviewDocument] =
        useState<AgreementDocument | null>(null);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FileCheck2 aria-hidden="true" />
                    Agreement approval register
                </CardTitle>
                <CardDescription>
                    Document-backed MoUs and cooperation instruments move from
                    draft submission to independent approval with immutable
                    workflow history.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {agreements.length === 0 && (
                    <WorkspaceEmptyState
                        title="No agreements registered"
                        description="Register an agreement to begin its document and approval lifecycle."
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
                                    {agreement.partner} · {agreement.type} ·{' '}
                                    {agreement.startsOn}
                                    {agreement.endsOn
                                        ? ` to ${agreement.endsOn}`
                                        : ''}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {agreement.canUpload && (
                                    <UploadAgreementDocument
                                        teamSlug={teamSlug}
                                        agreement={agreement}
                                    />
                                )}
                                {canManage &&
                                    agreement.workflowState === 'draft' && (
                                        <SubmitAgreement
                                            teamSlug={teamSlug}
                                            agreement={agreement}
                                        />
                                    )}
                                {canApprove &&
                                    agreement.workflowState ===
                                        'pending_approval' && (
                                        <AgreementDecision
                                            teamSlug={teamSlug}
                                            agreement={agreement}
                                        />
                                    )}
                                {agreement.canRequestChange && (
                                    <RequestAgreementChange
                                        teamSlug={teamSlug}
                                        agreement={agreement}
                                    />
                                )}
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <p className="text-sm font-medium">
                                Repository records ({agreement.documents.length}
                                )
                            </p>
                            {agreement.documents.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No scanned or born-digital agreement record
                                    has been uploaded.
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
                                                {document.originalName} ·{' '}
                                                {document.sourceType} · scan{' '}
                                                {document.scanStatus} · OCR{' '}
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
                                                                teamSlug,
                                                            document:
                                                                document.id,
                                                        })}
                                                    >
                                                        <Download aria-hidden="true" />
                                                        Download
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
                                    Post-approval change history
                                </p>
                                {agreement.changeRequests.map((change) => (
                                    <div
                                        key={change.id}
                                        className="grid gap-3 rounded-md border p-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex gap-2">
                                                <Badge variant="outline">
                                                    v{change.version}
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
                                                Effective {change.effectiveOn}
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
                                                    teamSlug={teamSlug}
                                                    change={change}
                                                />
                                            )}
                                            {change.canDecide && (
                                                <DecideAgreementChange
                                                    teamSlug={teamSlug}
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
                                                                        current_team:
                                                                            teamSlug,
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
                                                {change.decision.decider}:{' '}
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
                            Authorized preview of the private repository copy.
                        </SheetDescription>
                    </SheetHeader>
                    {previewDocument && (
                        <iframe
                            title={`Preview ${previewDocument.title}`}
                            src={preview.url({
                                current_team: teamSlug,
                                document: previewDocument.id,
                            })}
                            className="h-[75vh] w-full border-0 px-4 pb-4"
                        />
                    )}
                </SheetContent>
            </Sheet>
        </Card>
    );
}

function RequestAgreementChange({
    teamSlug,
    agreement,
}: {
    teamSlug: string;
    agreement: PartnerAgreement;
}) {
    return (
        <FormSheet
            title="Request agreement change"
            triggerLabel="Request change"
            description="Propose an amendment, renewal, suspension or termination without rewriting the approved agreement."
        >
            <Form
                {...storeAgreementChange.form({
                    current_team: teamSlug,
                    agreement: agreement.id,
                })}
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
                                Revised title (amendments)
                            </Label>
                            <Input
                                id={`change-title-${agreement.id}`}
                                name="title"
                                defaultValue={agreement.title}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`change-summary-${agreement.id}`}>
                                Revised summary (amendments)
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
                                Revised committed value
                            </Label>
                            <Input
                                id={`change-value-${agreement.id}`}
                                name="committed_value"
                                inputMode="decimal"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`change-reason-${agreement.id}`}>
                                Reason
                            </Label>
                            <Textarea
                                id={`change-reason-${agreement.id}`}
                                name="reason"
                                minLength={20}
                                required
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            Submit governed request
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function UploadAgreementChangeEvidence({
    teamSlug,
    change,
}: {
    teamSlug: string;
    change: AgreementChange;
}) {
    return (
        <FormSheet
            title="Upload change evidence"
            triggerLabel="Upload evidence"
            description="Attach the signed amendment, renewal, suspension or termination instrument."
        >
            <Form
                {...storePartnerAgreementChange.form({
                    current_team: teamSlug,
                    changeRequest: change.id,
                })}
                className="grid gap-4"
                resetOnSuccess
            >
                <Label>
                    Record title
                    <Input name="title" required />
                </Label>
                <Label>
                    Category
                    <Input
                        name="category"
                        defaultValue="Agreement change"
                        required
                    />
                </Label>
                <input type="hidden" name="source_type" value="digital" />
                <Label>
                    Document
                    <Input name="document" type="file" required />
                </Label>
                <Button type="submit">
                    <Upload />
                    Upload securely
                </Button>
            </Form>
        </FormSheet>
    );
}

function DecideAgreementChange({
    teamSlug,
    change,
}: {
    teamSlug: string;
    change: AgreementChange;
}) {
    return (
        <FormSheet
            title="Decide agreement change"
            triggerLabel="Record decision"
            description="An independent approver reviews the clean evidence and records an immutable decision."
        >
            <Form
                {...decideAgreementChange.form({
                    current_team: teamSlug,
                    changeRequest: change.id,
                })}
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
                    Decision note
                    <Textarea name="decision_note" minLength={20} required />
                </Label>
                <Button type="submit">Retain decision</Button>
            </Form>
        </FormSheet>
    );
}

function UploadAgreementDocument({
    teamSlug,
    agreement,
}: {
    teamSlug: string;
    agreement: PartnerAgreement;
}) {
    return (
        <FormSheet
            title="Upload agreement record"
            triggerLabel="Upload record"
            icon={Upload}
            description="Add a privately stored scanned or born-digital copy. Files are checksum-bound and security scanned."
        >
            <Form
                {...storeDocument.form({
                    current_team: teamSlug,
                    agreement: agreement.id,
                })}
                resetOnSuccess
                className="grid gap-4"
            >
                {({ errors, processing, progress }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor={`agreement-title-${agreement.id}`}>
                                Record title
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
                                Category
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
                                Agreement file
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

function AgreementDecision({
    teamSlug,
    agreement,
}: {
    teamSlug: string;
    agreement: PartnerAgreement;
}) {
    return (
        <FormSheet
            title="Review agreement"
            triggerLabel="Record decision"
            description="Approve or reject this independently submitted agreement. The submitter cannot make this decision."
        >
            <Form
                {...transition.form({
                    current_team: teamSlug,
                    agreement: agreement.id,
                })}
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
                                Decision rationale
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
                            Record decision
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function SubmitAgreement({
    teamSlug,
    agreement,
}: {
    teamSlug: string;
    agreement: PartnerAgreement;
}) {
    const hasDocument = agreement.documents.length > 0;

    return (
        <FormSheet
            title="Submit agreement for approval"
            triggerLabel="Submit for approval"
            description="Confirm the repository record is complete. Submission locks further uploads and sends the agreement for an independent decision."
        >
            <Form
                {...transition.form({
                    current_team: teamSlug,
                    agreement: agreement.id,
                })}
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
                            Confirm submission
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
