import { Form } from '@inertiajs/react';
import {
    DownloadIcon,
    FileCheck2Icon,
    ListChecksIcon,
    ShieldXIcon,
    UsersRoundIcon,
    UserXIcon,
} from 'lucide-react';
import { useState } from 'react';
import SearchableSelect from '@/components/searchable-select';
import StaticSearchableSelect from '@/components/static-searchable-select';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { WorkspaceRow } from '@/components/workspace-data-table';
import { bulkTransition } from '@/routes/assessments';
import { bulkTriage } from '@/routes/citizen-cases';
import { bulkVerification } from '@/routes/evidence';
import { bulkDestroy } from '@/routes/programme-users';
import { exportMethod } from '@/routes/workspace';

export function AssessmentBulkActions({
    rows,
    capabilities,
    clearSelection,
}: {
    rows: WorkspaceRow[];
    capabilities: Record<string, boolean>;
    clearSelection: () => void;
}) {
    const canSubmit =
        capabilities.submit &&
        rows.every((row) =>
            ['draft', 'evidence_collection'].includes(row.status ?? ''),
        );
    const canReview =
        capabilities.review && rows.every((row) => row.status === 'submitted');

    if (!canSubmit && !canReview) {
        return null;
    }

    return (
        <AssessmentTransitionSheet
            rows={rows}
            transition={canSubmit ? 'submit' : 'review'}
            clearSelection={clearSelection}
        />
    );
}

function AssessmentTransitionSheet({
    rows,
    transition,
    clearSelection,
}: {
    rows: WorkspaceRow[];
    transition: 'submit' | 'review';
    clearSelection: () => void;
}) {
    const [open, setOpen] = useState(false);
    const submitting = transition === 'submit';

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button type="button" size="sm">
                    <ListChecksIcon data-icon="inline-start" />
                    {submitting ? 'Submit selected' : 'Start selected reviews'}
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {submitting ? 'Submit' : 'Start review for'}{' '}
                        {rows.length} assessments
                    </SheetTitle>
                    <SheetDescription>
                        The complete selection is checked for lifecycle state,
                        permission and county portfolio before any assessment
                        changes. A failed check leaves every record unchanged.
                    </SheetDescription>
                </SheetHeader>
                <Form
                    {...bulkTransition.form()}
                    className="flex flex-col gap-4 px-4 pb-6"
                    onSuccess={() => {
                        setOpen(false);
                        clearSelection();
                    }}
                >
                    {({ processing }) => (
                        <>
                            {rows.map((row) => (
                                <input
                                    key={row.id}
                                    type="hidden"
                                    name="ids[]"
                                    value={row.id}
                                />
                            ))}
                            <input
                                type="hidden"
                                name="transition"
                                value={transition}
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                Confirm {submitting ? 'submission' : 'review'}
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

export function WorkspaceBulkExportActions({
    workspace,
    rows,
    filters,
    selectionMode = 'selected',
}: {
    workspace: string;
    rows: WorkspaceRow[];
    filters: Record<string, string | undefined>;
    selectionMode?: 'selected' | 'filtered';
}) {
    const query = {
        ...filters,
        ids:
            selectionMode === 'selected'
                ? rows.map((row) => row.id)
                : undefined,
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    <DownloadIcon />
                    Export selected
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    {['csv', 'xlsx', 'pdf', 'json'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a
                                href={exportMethod.url(
                                    { workspace, format },
                                    { query },
                                )}
                            >
                                {format.toUpperCase()}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function EvidenceBulkActions({
    rows,
    clearSelection,
}: {
    rows: WorkspaceRow[];
    clearSelection: () => void;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            <EvidenceDecisionSheet
                rows={rows}
                status="verified"
                clearSelection={clearSelection}
            />
            <EvidenceDecisionSheet
                rows={rows}
                status="rejected"
                clearSelection={clearSelection}
            />
        </div>
    );
}

function EvidenceDecisionSheet({
    rows,
    status,
    clearSelection,
}: {
    rows: WorkspaceRow[];
    status: 'verified' | 'rejected';
    clearSelection: () => void;
}) {
    const [open, setOpen] = useState(false);
    const approving = status === 'verified';

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button
                    type="button"
                    size="sm"
                    variant={approving ? 'outline' : 'destructive'}
                >
                    {approving ? <FileCheck2Icon /> : <ShieldXIcon />}
                    {approving ? 'Verify selected' : 'Reject selected'}
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {approving ? 'Verify' : 'Reject'} {rows.length} evidence
                        records
                    </SheetTitle>
                    <SheetDescription>
                        This decision is applied atomically. If any record is
                        quarantined, missing, or outside your county portfolio,
                        no selected record will change.
                    </SheetDescription>
                </SheetHeader>
                <Form
                    {...bulkVerification.form()}
                    className="flex flex-col gap-4 px-4 pb-6"
                    onSuccess={() => {
                        setOpen(false);
                        clearSelection();
                    }}
                >
                    {({ processing }) => (
                        <>
                            {rows.map((row) => (
                                <input
                                    key={row.id}
                                    type="hidden"
                                    name="ids[]"
                                    value={row.id}
                                />
                            ))}
                            <input type="hidden" name="status" value={status} />
                            <Button
                                type="submit"
                                variant={approving ? 'default' : 'destructive'}
                                disabled={processing}
                                aria-busy={processing}
                            >
                                {approving
                                    ? 'Confirm verification'
                                    : 'Confirm rejection'}
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

export function ProgrammeUserBulkActions({
    rows,
    clearSelection,
}: {
    rows: WorkspaceRow[];
    clearSelection: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button type="button" size="sm" variant="destructive">
                    <UserXIcon />
                    Deactivate selected
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        Deactivate {rows.length} user accounts
                    </SheetTitle>
                    <SheetDescription>
                        Access will be removed atomically and each account will
                        retain an attributed audit event. Your own account and
                        identities outside your management scope cannot be
                        processed.
                    </SheetDescription>
                </SheetHeader>
                <Form
                    {...bulkDestroy.form()}
                    className="flex flex-col gap-4 px-4 pb-6"
                    onSuccess={() => {
                        setOpen(false);
                        clearSelection();
                    }}
                >
                    {({ processing }) => (
                        <>
                            {rows.map((row) => (
                                <input
                                    key={row.id}
                                    type="hidden"
                                    name="ids[]"
                                    value={row.id}
                                />
                            ))}
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                Confirm bulk deactivation
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}

export function CitizenCaseBulkTriageActions({
    rows,
    users,
    organizations,
    sectors,
    filters,
    selection,
    clearSelection,
}: {
    rows: WorkspaceRow[];
    users: Array<{ id: string; name: string }>;
    organizations: Array<{ id: string; name: string }>;
    sectors: Array<{ id: string; name: string }>;
    filters: { from?: string; to?: string; search?: string };
    selection: { mode: 'selected' | 'filtered'; count: number };
    clearSelection: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button type="button" size="sm">
                    <UsersRoundIcon />
                    Triage selected
                </Button>
            </SheetTrigger>
            <SheetContent className="overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        Triage and assign {selection.count} citizen cases
                    </SheetTitle>
                    <SheetDescription>
                        The complete selection is locked and checked before any
                        case changes. Every case must be newly received, within
                        your county scope, and accessible to the chosen handler.
                        One failed check leaves all selected cases unchanged.
                    </SheetDescription>
                </SheetHeader>
                <Form
                    {...bulkTriage.form()}
                    className="flex flex-col gap-4 px-4 pb-6"
                    onSuccess={() => {
                        setOpen(false);
                        clearSelection();
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="selection_mode"
                                value={selection.mode}
                            />
                            {selection.mode === 'selected' &&
                                rows.map((row) => (
                                    <input
                                        key={row.id}
                                        type="hidden"
                                        name="ids[]"
                                        value={row.id}
                                    />
                                ))}
                            {selection.mode === 'filtered' &&
                                Object.entries(filters).map(([name, value]) =>
                                    value ? (
                                        <input
                                            key={name}
                                            type="hidden"
                                            name={name}
                                            value={value}
                                        />
                                    ) : null,
                                )}
                            <SearchableSelect
                                id="bulk-citizen-case-assignee"
                                name="assigned_to"
                                label="Case handler"
                                options={users}
                                error={errors.assigned_to}
                            />
                            <SearchableSelect
                                id="bulk-citizen-case-organization"
                                name="assigned_organization_id"
                                label="Assigned organization"
                                options={organizations}
                                optional
                                error={errors.assigned_organization_id}
                            />
                            <SearchableSelect
                                id="bulk-citizen-case-sector"
                                name="sector_id"
                                label="Sector"
                                options={sectors}
                                optional
                                error={errors.sector_id}
                            />
                            <StaticSearchableSelect
                                id="bulk-citizen-case-priority"
                                name="priority"
                                label="Priority"
                                values={['low', 'medium', 'high', 'critical']}
                                error={errors.priority}
                            />
                            <SearchableSelect
                                id="bulk-citizen-case-sensitivity"
                                name="is_sensitive"
                                label="Sensitivity"
                                options={[
                                    { id: '0', name: 'Standard case' },
                                    { id: '1', name: 'Sensitive case' },
                                ]}
                                error={errors.is_sensitive}
                            />
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="bulk-citizen-case-note">
                                    Triage rationale
                                </Label>
                                <Textarea
                                    id="bulk-citizen-case-note"
                                    name="triage_note"
                                    minLength={10}
                                    required
                                    aria-invalid={Boolean(errors.triage_note)}
                                />
                                {errors.triage_note && (
                                    <p className="text-xs text-destructive">
                                        {errors.triage_note}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                aria-busy={processing}
                            >
                                Confirm atomic triage
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
