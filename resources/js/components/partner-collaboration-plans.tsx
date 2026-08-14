import { Form, usePage } from '@inertiajs/react';
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
import { interpolate } from '@/hooks/use-localization';
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

function usePartnerCopy(): Record<string, string> {
    return usePage().props.localization.partnerCoordination;
}

export default function PartnerCollaborationPlans({
    plans,
    partners,
    counties,
    actionUsers,
    actionOrganizations,
    catalogue,
    canManage,
    filters,
}: {
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
    const copy = usePartnerCopy();

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <ListChecks />
                        {copy.collaboration_plans}
                    </CardTitle>
                    <CardDescription>
                        {copy.collaboration_plans_description}
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
                                    { workspace: 'partner-actions', format },
                                    { query: filters },
                                )}
                            >
                                <Download />
                                {format.toUpperCase()}
                            </a>
                        </Button>
                    ))}
                    {canManage && <CreatePlan partners={partners} />}
                </div>
            </CardHeader>
            <CardContent className="grid gap-4">
                {plans.length === 0 ? (
                    <WorkspaceEmptyState
                        title={copy.no_collaboration_plans}
                        description={copy.no_collaboration_plans_description}
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
                                        {plan.partner} {copy.separator}{' '}
                                        {plan.startsOn} {copy.to} {plan.endsOn}
                                    </p>
                                    <p className="mt-2 text-sm">
                                        {plan.objective}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {plan.canSubmit && (
                                        <TransitionPlan
                                            plan={plan}
                                            transition="submit"
                                        />
                                    )}
                                    {plan.canApprove && (
                                        <>
                                            <TransitionPlan
                                                plan={plan}
                                                transition="approve"
                                            />
                                            <TransitionPlan
                                                plan={plan}
                                                transition="reject"
                                            />
                                        </>
                                    )}
                                    {plan.canAddAction && (
                                        <CreateAction
                                            plan={plan}
                                            counties={counties}
                                            users={actionUsers}
                                            organizations={actionOrganizations}
                                            catalogue={catalogue}
                                        />
                                    )}
                                    {plan.canComplete && (
                                        <TransitionPlan
                                            plan={plan}
                                            transition="complete"
                                        />
                                    )}
                                </div>
                            </div>
                            {plan.actions.length === 0 ? (
                                <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                    {copy.no_accountable_actions}
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
                                                        {action.code}{' '}
                                                        {copy.separator}{' '}
                                                        {action.title}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {action.owner}{' '}
                                                        {copy.separator}{' '}
                                                        {copy.due}{' '}
                                                        {action.dueOn}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {action.ownerOrganization ??
                                                            'No accountable organization'}{' '}
                                                        {copy.separator}{' '}
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
                                                    action={action}
                                                />
                                            )}
                                            {action.canUpdate && (
                                                <SubmitUpdate action={action} />
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
                                                                        document:
                                                                            document.id,
                                                                    },
                                                                )}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                {copy.preview}{' '}
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
                                                                        document:
                                                                            document.id,
                                                                    },
                                                                )}
                                                            >
                                                                {copy.download}
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
                                                        {update.progress}
                                                        {copy.percent}{' '}
                                                        {copy.separator}{' '}
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
                                                            {
                                                                copy.label_separator
                                                            }{' '}
                                                            {
                                                                update.decision
                                                                    .result
                                                            }{' '}
                                                            {copy.empty_value}{' '}
                                                            {
                                                                update.decision
                                                                    .note
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                {update.canVerify && (
                                                    <VerifyUpdate
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

function CreatePlan({ partners }: { partners: Option[] }) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title={copy.create_collaboration_plan}
            triggerLabel={copy.create_plan}
            description={copy.create_plan_description}
        >
            <Form {...storeCollaborationPlan.form({})} className="grid gap-4">
                <SearchableSelect
                    id="plan-partner"
                    name="partner_profile_id"
                    label={copy.partner}
                    options={partners}
                />
                <Label>
                    {copy.reference}
                    <Input name="reference" required />
                </Label>
                <Label>
                    {copy.title_label}
                    <Input name="title" required />
                </Label>
                <Label>
                    {copy.objective}
                    <Textarea name="objective" minLength={20} required />
                </Label>
                <DatePickerField
                    name="starts_on"
                    label={copy.starts_on}
                    required
                />
                <DatePickerField name="ends_on" label={copy.ends_on} required />
                <Button type="submit">{copy.create_draft}</Button>
            </Form>
        </FormSheet>
    );
}
function TransitionPlan({
    plan,
    transition,
}: {
    plan: CollaborationPlan;
    transition: string;
}) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title={interpolate(copy.transition_plan, { transition })}
            triggerLabel={transition}
            description={copy.plan_transition_description}
        >
            <Form
                {...transitionCollaborationPlan.form({ plan: plan.id })}
                className="grid gap-4"
            >
                <input type="hidden" name="transition" value={transition} />
                <Label>
                    {copy.decision_note}
                    <Textarea
                        name="decision_note"
                        required={transition !== 'submit'}
                    />
                </Label>
                <Button type="submit">
                    {copy.record} {transition}
                </Button>
            </Form>
        </FormSheet>
    );
}
function CreateAction({
    plan,
    counties,
    users,
    organizations,
    catalogue,
}: {
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
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title={copy.add_accountable_action}
            triggerLabel={copy.add_action}
            description={copy.add_action_description}
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `Using governed catalogue release v${catalogue.version}`
                    : 'No checksum-valid published reference catalogue is currently effective.'
            }
        >
            <Form
                {...storeCollaborationAction.form({ plan: plan.id })}
                className="grid gap-4"
            >
                <SearchableSelect
                    id={`action-county-${plan.id}`}
                    name="county_id"
                    label={copy.county}
                    options={counties}
                />
                <Label>
                    {copy.code}
                    <Input name="code" required />
                </Label>
                <Label>
                    {copy.title_label}
                    <Input name="title" required />
                </Label>
                <Label>
                    {copy.description_label}
                    <Textarea name="description" minLength={20} required />
                </Label>
                <SearchableSelect
                    id={`action-owner-${plan.id}`}
                    name="accountable_user_id"
                    label={copy.accountable_owner}
                    options={users}
                />
                <SearchableSelect
                    id={`action-organization-${plan.id}`}
                    name="accountable_organization_id"
                    label={copy.accountable_organization}
                    options={organizations}
                    optional
                />
                <DatePickerField
                    name="due_on"
                    label={copy.due_on}
                    required
                    min={plan.startsOn}
                />
                <Button type="submit">{copy.assign_action}</Button>
            </Form>
        </FormSheet>
    );
}
function UploadEvidence({ action }: { action: CollaborationAction }) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title={copy.upload_action_evidence}
            triggerLabel={copy.upload_evidence}
            description={copy.upload_action_evidence_description}
        >
            <Form
                {...storePartnerCollaborationAction.form({ action: action.id })}
                className="grid gap-4"
            >
                <Label>
                    {copy.title_label}
                    <Input name="title" required />
                </Label>
                <Label>
                    {copy.category}
                    <Input
                        name="category"
                        defaultValue="Action evidence"
                        required
                    />
                </Label>
                <SearchableSelect
                    id={`action-source-${action.id}`}
                    name="source_type"
                    label={copy.source_type}
                    options={[
                        { id: 'scanned', name: 'Scanned original' },
                        { id: 'digital', name: 'Born digital' },
                    ]}
                />
                <Label>
                    {copy.document}
                    <Input type="file" name="document" required />
                </Label>
                <Button type="submit">
                    <Upload />
                    {copy.upload_securely}
                </Button>
            </Form>
        </FormSheet>
    );
}
function SubmitUpdate({ action }: { action: CollaborationAction }) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title={copy.submit_action_progress}
            triggerLabel={copy.submit_progress}
            description={copy.submit_progress_description}
        >
            <Form
                {...storeCollaborationActionUpdate.form({ action: action.id })}
                className="grid gap-4"
            >
                <Label>
                    {copy.progress}
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
                    {copy.narrative}
                    <Textarea name="narrative" minLength={20} required />
                </Label>
                <Button type="submit">{copy.submit_verification}</Button>
            </Form>
        </FormSheet>
    );
}
function VerifyUpdate({ update }: { update: ActionUpdate }) {
    const copy = usePartnerCopy();

    return (
        <FormSheet
            title={copy.verify_action_progress}
            triggerLabel={copy.verify}
            description={copy.verify_progress_description}
        >
            <Form
                {...verifyCollaborationActionUpdate.form({ update: update.id })}
                className="grid gap-4"
            >
                <SearchableSelect
                    id={`verify-${update.id}`}
                    name="decision"
                    label={copy.decision}
                    options={[
                        { id: 'verified', name: 'Verified' },
                        { id: 'rejected', name: 'Rejected' },
                    ]}
                />
                <Label>
                    {copy.verification_note}
                    <Textarea
                        name="verification_note"
                        minLength={20}
                        required
                    />
                </Label>
                <Button type="submit">{copy.retain_decision}</Button>
            </Form>
        </FormSheet>
    );
}
