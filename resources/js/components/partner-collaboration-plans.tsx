import { Form } from '@inertiajs/react';
import { Download, ListChecks, Upload } from 'lucide-react';
import { storePartnerCollaborationAction } from '@/actions/App/Http/Controllers/LinkedDocumentController';
import {
    storeCollaborationAction,
    storeCollaborationActionUpdate,
    storeCollaborationPlan,
    transitionCollaborationPlan,
    verifyCollaborationActionUpdate,
} from '@/actions/App/Http/Controllers/PartnerCoordinationController';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
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
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { download, preview } from '@/routes/evidence';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string };
type ActionUpdate = {
    id: string;
    progress: number;
    narrative: string;
    submitter: string;
    submittedAt: string;
    updateChecksum: string;
    decision: {
        result: string;
        note: string;
        verifier: string;
        checksum: string;
    } | null;
    canVerify: boolean;
};
type CollaborationAction = {
    id: string;
    code: string;
    title: string;
    description: string;
    county: CountyIdentityValue;
    owner: string;
    ownerId: string;
    ownerOrganization: string | null;
    referenceData: {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    } | null;
    dueOn: string;
    progress: number;
    status: string;
    canUpdate: boolean;
    canUpload: boolean;
    documents: Array<{ id: string; title: string; scanStatus: string }>;
    updates: ActionUpdate[];
};
export type CollaborationPlan = {
    id: string;
    partner: string;
    reference: string;
    title: string;
    objective: string;
    startsOn: string;
    endsOn: string;
    status: string;
    canSubmit: boolean;
    canApprove: boolean;
    canAddAction: boolean;
    canComplete: boolean;
    actions: CollaborationAction[];
};

export default function PartnerCollaborationPlans({
    teamSlug,
    plans,
    partners,
    counties,
    actionUsers,
    actionOrganizations,
    catalogue,
    canManage,
    filters,
}: {
    teamSlug: string;
    plans: CollaborationPlan[];
    partners: Option[];
    counties: Option[];
    actionUsers: Option[];
    actionOrganizations: Option[];
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
    canManage: boolean;
    filters: {
        from?: string;
        to?: string;
        search?: string;
        county_id?: string;
        sector_id?: string;
        status?: string;
    };
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <ListChecks />
                        Collaboration plans and actions
                    </CardTitle>
                    <CardDescription>
                        Approved partner/county plans with accountable
                        evidence-backed actions and independent progress
                        verification.
                    </CardDescription>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <Button
                            key={format}
                            size="sm"
                            variant="outline"
                            asChild
                        >
                            <a
                                href={exportMethod.url(
                                    {
                                        current_team: teamSlug,
                                        workspace: 'partner-actions',
                                        format,
                                    },
                                    { query: filters },
                                )}
                            >
                                <Download />
                                {format.toUpperCase()}
                            </a>
                        </Button>
                    ))}
                    {canManage && (
                        <CreatePlan teamSlug={teamSlug} partners={partners} />
                    )}
                </div>
            </CardHeader>
            <CardContent className="grid gap-4">
                {plans.length === 0 ? (
                    <WorkspaceEmptyState
                        title="No collaboration plans"
                        description="Create a governed plan to coordinate partner and county delivery actions."
                        className="min-h-48"
                    />
                ) : (
                    plans.map((plan) => (
                        <article
                            key={plan.id}
                            className="grid gap-4 rounded-lg border p-4"
                        >
                            <div className="flex flex-wrap justify-between gap-3">
                                <div>
                                    <div className="flex gap-2">
                                        <Badge variant="outline">
                                            {plan.reference}
                                        </Badge>
                                        <Badge>{plan.status}</Badge>
                                    </div>
                                    <h3 className="mt-2 font-semibold">
                                        {plan.title}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {plan.partner} · {plan.startsOn} to{' '}
                                        {plan.endsOn}
                                    </p>
                                    <p className="mt-2 text-sm">
                                        {plan.objective}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {plan.canSubmit && (
                                        <TransitionPlan
                                            teamSlug={teamSlug}
                                            plan={plan}
                                            transition="submit"
                                        />
                                    )}
                                    {plan.canApprove && (
                                        <>
                                            <TransitionPlan
                                                teamSlug={teamSlug}
                                                plan={plan}
                                                transition="approve"
                                            />
                                            <TransitionPlan
                                                teamSlug={teamSlug}
                                                plan={plan}
                                                transition="reject"
                                            />
                                        </>
                                    )}
                                    {plan.canAddAction && (
                                        <CreateAction
                                            teamSlug={teamSlug}
                                            plan={plan}
                                            counties={counties}
                                            users={actionUsers}
                                            organizations={actionOrganizations}
                                            catalogue={catalogue}
                                        />
                                    )}
                                    {plan.canComplete && (
                                        <TransitionPlan
                                            teamSlug={teamSlug}
                                            plan={plan}
                                            transition="complete"
                                        />
                                    )}
                                </div>
                            </div>
                            {plan.actions.length === 0 ? (
                                <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                    No accountable actions assigned.
                                </p>
                            ) : (
                                plan.actions.map((action) => (
                                    <section
                                        key={action.id}
                                        className="grid gap-3 rounded-lg bg-muted/35 p-4"
                                    >
                                        <div className="flex flex-wrap justify-between gap-3">
                                            <div className="flex gap-3">
                                                <CountyIdentity
                                                    county={action.county}
                                                    compact
                                                />
                                                <div>
                                                    <p className="font-medium">
                                                        {action.code} ·{' '}
                                                        {action.title}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {action.owner} · due{' '}
                                                        {action.dueOn}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {action.ownerOrganization ??
                                                            'No accountable organization'}{' '}
                                                        ·{' '}
                                                        {action.referenceData
                                                            ? `Catalogue v${action.referenceData.version}`
                                                            : 'Legacy · unpinned'}
                                                    </p>
                                                    {action.referenceData && (
                                                        <p className="font-mono text-[10px] text-muted-foreground">
                                                            {
                                                                action
                                                                    .referenceData
                                                                    .checksum
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <Badge variant="outline">
                                                {action.status}
                                            </Badge>
                                        </div>
                                        <Progress value={action.progress} />
                                        <p className="text-sm">
                                            {action.description}
                                        </p>
                                        <div className="flex gap-2">
                                            {action.canUpload && (
                                                <UploadEvidence
                                                    teamSlug={teamSlug}
                                                    action={action}
                                                />
                                            )}
                                            {action.canUpdate && (
                                                <SubmitUpdate
                                                    teamSlug={teamSlug}
                                                    action={action}
                                                />
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {action.documents
                                                .filter(
                                                    (document) =>
                                                        document.scanStatus ===
                                                        'clean',
                                                )
                                                .map((document) => (
                                                    <span
                                                        key={document.id}
                                                        className="flex gap-1"
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            asChild
                                                        >
                                                            <a
                                                                href={preview.url(
                                                                    {
                                                                        current_team:
                                                                            teamSlug,
                                                                        document:
                                                                            document.id,
                                                                    },
                                                                )}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                Preview{' '}
                                                                {document.title}
                                                            </a>
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
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
                                                                Download
                                                            </a>
                                                        </Button>
                                                    </span>
                                                ))}
                                        </div>
                                        {action.updates.map((update) => (
                                            <div
                                                key={update.id}
                                                className="flex flex-wrap justify-between gap-3 border-t pt-3"
                                            >
                                                <div>
                                                    <p className="text-sm">
                                                        {update.progress}% ·{' '}
                                                        {update.narrative}
                                                    </p>
                                                    <p className="font-mono text-[10px] text-muted-foreground">
                                                        {update.updateChecksum}
                                                    </p>
                                                    {update.decision && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                update.decision
                                                                    .verifier
                                                            }
                                                            :{' '}
                                                            {
                                                                update.decision
                                                                    .result
                                                            }{' '}
                                                            —{' '}
                                                            {
                                                                update.decision
                                                                    .note
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                {update.canVerify && (
                                                    <VerifyUpdate
                                                        teamSlug={teamSlug}
                                                        update={update}
                                                    />
                                                )}
                                            </div>
                                        ))}
                                    </section>
                                ))
                            )}
                        </article>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function CreatePlan({
    teamSlug,
    partners,
}: {
    teamSlug: string;
    partners: Option[];
}) {
    return (
        <FormSheet
            title="Create collaboration plan"
            triggerLabel="Create plan"
            description="Create a time-bound plan for independent approval."
        >
            <Form
                {...storeCollaborationPlan.form({ current_team: teamSlug })}
                className="grid gap-4"
            >
                <SearchableSelect
                    id="plan-partner"
                    name="partner_profile_id"
                    label="Partner"
                    options={partners}
                />
                <Label>
                    Reference
                    <Input name="reference" required />
                </Label>
                <Label>
                    Title
                    <Input name="title" required />
                </Label>
                <Label>
                    Objective
                    <Textarea name="objective" minLength={20} required />
                </Label>
                <DatePickerField name="starts_on" label="Starts on" required />
                <DatePickerField name="ends_on" label="Ends on" required />
                <Button type="submit">Create draft</Button>
            </Form>
        </FormSheet>
    );
}
function TransitionPlan({
    teamSlug,
    plan,
    transition,
}: {
    teamSlug: string;
    plan: CollaborationPlan;
    transition: string;
}) {
    return (
        <FormSheet
            title={`${transition} plan`}
            triggerLabel={transition}
            description="Record an attributable plan lifecycle decision."
        >
            <Form
                {...transitionCollaborationPlan.form({
                    current_team: teamSlug,
                    plan: plan.id,
                })}
                className="grid gap-4"
            >
                <input type="hidden" name="transition" value={transition} />
                <Label>
                    Decision note
                    <Textarea
                        name="decision_note"
                        required={transition !== 'submit'}
                    />
                </Label>
                <Button type="submit">Record {transition}</Button>
            </Form>
        </FormSheet>
    );
}
function CreateAction({
    teamSlug,
    plan,
    counties,
    users,
    organizations,
    catalogue,
}: {
    teamSlug: string;
    plan: CollaborationPlan;
    counties: Option[];
    users: Option[];
    organizations: Option[];
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
}) {
    return (
        <FormSheet
            title="Add accountable action"
            triggerLabel="Add action"
            description="Assign a measurable county action within the approved period."
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `Using governed catalogue release v${catalogue.version}`
                    : 'No checksum-valid published reference catalogue is currently effective.'
            }
        >
            <Form
                {...storeCollaborationAction.form({
                    current_team: teamSlug,
                    plan: plan.id,
                })}
                className="grid gap-4"
            >
                <SearchableSelect
                    id={`action-county-${plan.id}`}
                    name="county_id"
                    label="County"
                    options={counties}
                />
                <Label>
                    Code
                    <Input name="code" required />
                </Label>
                <Label>
                    Title
                    <Input name="title" required />
                </Label>
                <Label>
                    Description
                    <Textarea name="description" minLength={20} required />
                </Label>
                <SearchableSelect
                    id={`action-owner-${plan.id}`}
                    name="accountable_user_id"
                    label="Accountable owner"
                    options={users}
                />
                <SearchableSelect
                    id={`action-organization-${plan.id}`}
                    name="accountable_organization_id"
                    label="Accountable organization"
                    options={organizations}
                    optional
                />
                <DatePickerField
                    name="due_on"
                    label="Due on"
                    required
                    min={plan.startsOn}
                />
                <Button type="submit">Assign action</Button>
            </Form>
        </FormSheet>
    );
}
function UploadEvidence({
    teamSlug,
    action,
}: {
    teamSlug: string;
    action: CollaborationAction;
}) {
    return (
        <FormSheet
            title="Upload action evidence"
            triggerLabel="Upload evidence"
            description="Attach a scanned or born-digital implementation record."
        >
            <Form
                {...storePartnerCollaborationAction.form({
                    current_team: teamSlug,
                    action: action.id,
                })}
                className="grid gap-4"
            >
                <Label>
                    Title
                    <Input name="title" required />
                </Label>
                <Label>
                    Category
                    <Input
                        name="category"
                        defaultValue="Action evidence"
                        required
                    />
                </Label>
                <SearchableSelect
                    id={`action-source-${action.id}`}
                    name="source_type"
                    label="Source type"
                    options={[
                        { id: 'scanned', name: 'Scanned original' },
                        { id: 'digital', name: 'Born digital' },
                    ]}
                />
                <Label>
                    Document
                    <Input type="file" name="document" required />
                </Label>
                <Button type="submit">
                    <Upload />
                    Upload securely
                </Button>
            </Form>
        </FormSheet>
    );
}
function SubmitUpdate({
    teamSlug,
    action,
}: {
    teamSlug: string;
    action: CollaborationAction;
}) {
    return (
        <FormSheet
            title="Submit action progress"
            triggerLabel="Submit progress"
            description="Completion requires clean evidence and independent verification."
        >
            <Form
                {...storeCollaborationActionUpdate.form({
                    current_team: teamSlug,
                    action: action.id,
                })}
                className="grid gap-4"
            >
                <Label>
                    Progress
                    <Input
                        name="progress_percentage"
                        type="number"
                        min={action.progress}
                        max="100"
                        step="0.01"
                        required
                    />
                </Label>
                <Label>
                    Narrative
                    <Textarea name="narrative" minLength={20} required />
                </Label>
                <Button type="submit">Submit for verification</Button>
            </Form>
        </FormSheet>
    );
}
function VerifyUpdate({
    teamSlug,
    update,
}: {
    teamSlug: string;
    update: ActionUpdate;
}) {
    return (
        <FormSheet
            title="Verify action progress"
            triggerLabel="Verify"
            description="Record an independent immutable verification decision."
        >
            <Form
                {...verifyCollaborationActionUpdate.form({
                    current_team: teamSlug,
                    update: update.id,
                })}
                className="grid gap-4"
            >
                <SearchableSelect
                    id={`verify-${update.id}`}
                    name="decision"
                    label="Decision"
                    options={[
                        { id: 'verified', name: 'Verified' },
                        { id: 'rejected', name: 'Rejected' },
                    ]}
                />
                <Label>
                    Verification note
                    <Textarea
                        name="verification_note"
                        minLength={20}
                        required
                    />
                </Label>
                <Button type="submit">Retain decision</Button>
            </Form>
        </FormSheet>
    );
}
