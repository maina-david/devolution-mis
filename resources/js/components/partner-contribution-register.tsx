import { Form, usePage } from '@inertiajs/react';
import {
    Download,
    Eye,
    FileCheck2,
    MoreHorizontal,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import { storePartnerContribution } from '@/actions/App/Http/Controllers/LinkedDocumentController';
import { reconcileContribution } from '@/actions/App/Http/Controllers/PartnerCoordinationController';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { download, preview } from '@/routes/evidence';

type ContributionDocument = {
    id: string;
    title: string;
    category: string;
    sourceType: string;
    originalName: string;
    mimeType: string;
    scanStatus: string;
    ocrStatus: string;
    recordStatus: string;
    checksum: string;
};
type Reconciliation = {
    id: string;
    version: number;
    decision: string;
    committedAmount: string;
    disbursedAmount: string;
    inKindValue: string;
    variance: string;
    sourceReference: string;
    reviewNote: string;
    reviewer: string;
    reviewedAt: string;
    evidenceChecksum: string;
    predecessorChecksum: string | null;
    decisionChecksum: string;
};
export type PartnerContribution = {
    id: string;
    partner: string;
    project: { id: string; code: string; title: string };
    county: CountyIdentityValue | null;
    financialYear: string;
    type: string;
    currency: string;
    committedAmount: string;
    disbursedAmount: string;
    inKindValue: string;
    status: string;
    provenance: Record<string, unknown>;
    documents: ContributionDocument[];
    reconciliations: Reconciliation[];
    canUpload: boolean;
    canReconcile: boolean;
};

export default function PartnerContributionRegister({
    contributions,
}: {
    contributions: PartnerContribution[];
}) {
    const copy = usePage().props.localization.partnerCoordination;
    const [detail, setDetail] = useState<PartnerContribution | null>(null);
    const [previewDocument, setPreviewDocument] =
        useState<ContributionDocument | null>(null);
    const money = (value: string, currency: string) =>
        `${currency} ${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FileCheck2 aria-hidden="true" />
                    {copy.contribution_register}
                </CardTitle>
                <CardDescription>
                    {copy.contribution_register_description}
                </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                {contributions.length === 0 ? (
                    <WorkspaceEmptyState
                        title={copy.no_contributions}
                        description={copy.no_contributions_description}
                        className="min-h-52 border-0"
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{copy.partner_project}</TableHead>
                                <TableHead>{copy.county}</TableHead>
                                <TableHead>{copy.reported}</TableHead>
                                <TableHead>{copy.decision}</TableHead>
                                <TableHead className="w-12">
                                    <span className="sr-only">
                                        {copy.actions}
                                    </span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {contributions.map((item) => {
                                const latest = item.reconciliations[0];

                                return (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {item.partner}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.project.code}{' '}
                                                {copy.separator}{' '}
                                                {item.financialYear}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {item.county ? (
                                                <CountyIdentity
                                                    county={item.county}
                                                    compact
                                                />
                                            ) : (
                                                'National'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <p>
                                                {money(
                                                    item.disbursedAmount,
                                                    item.currency,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {copy.of}{' '}
                                                {money(
                                                    item.committedAmount,
                                                    item.currency,
                                                )}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    latest?.decision ===
                                                    'verified'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {latest?.decision ??
                                                    'Pending evidence review'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Actions for ${item.partner}`}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setDetail(item)
                                                        }
                                                    >
                                                        <Eye />
                                                        {copy.view_record}
                                                    </DropdownMenuItem>
                                                    {item.canUpload && (
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <FormSheet
                                                                title="Upload reconciliation evidence"
                                                                triggerLabel="Upload evidence"
                                                                description="Add a scanned or born-digital source record to the governed repository."
                                                            >
                                                                <Form
                                                                    action={storePartnerContribution(
                                                                        {
                                                                            contribution:
                                                                                item.id,
                                                                        },
                                                                    )}
                                                                    className="grid gap-4"
                                                                >
                                                                    <Label>
                                                                        {
                                                                            copy.title_label
                                                                        }
                                                                        <Input
                                                                            name="title"
                                                                            required
                                                                        />
                                                                    </Label>
                                                                    <Label>
                                                                        {
                                                                            copy.category
                                                                        }
                                                                        <Input
                                                                            name="category"
                                                                            defaultValue="financial-record"
                                                                            required
                                                                        />
                                                                    </Label>
                                                                    <input
                                                                        type="hidden"
                                                                        name="source_type"
                                                                        value="uploaded"
                                                                    />
                                                                    <Label>
                                                                        {
                                                                            copy.document
                                                                        }
                                                                        <Input
                                                                            name="document"
                                                                            type="file"
                                                                            accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx"
                                                                            required
                                                                        />
                                                                    </Label>
                                                                    <Button type="submit">
                                                                        <Upload />
                                                                        {
                                                                            copy.upload_securely
                                                                        }
                                                                    </Button>
                                                                </Form>
                                                            </FormSheet>
                                                        </DropdownMenuItem>
                                                    )}
                                                    {item.canReconcile && (
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <FormSheet
                                                                title="Reconcile contribution"
                                                                triggerLabel="Record decision"
                                                                description="Independent maker-checker decision using the attached clean evidence."
                                                            >
                                                                <Form
                                                                    action={reconcileContribution(
                                                                        {
                                                                            contribution:
                                                                                item.id,
                                                                        },
                                                                    )}
                                                                    className="grid gap-4"
                                                                >
                                                                    <Label>
                                                                        {
                                                                            copy.decision
                                                                        }
                                                                        <SearchableSelect
                                                                            id="reconciliation-decision"
                                                                            name="decision"
                                                                            label="Decision"
                                                                            options={[
                                                                                {
                                                                                    id: 'verified',
                                                                                    name: 'Verified',
                                                                                },
                                                                                {
                                                                                    id: 'exception',
                                                                                    name: 'Exception',
                                                                                },
                                                                                {
                                                                                    id: 'rejected',
                                                                                    name: 'Rejected',
                                                                                },
                                                                            ]}
                                                                            defaultValue="verified"
                                                                        />
                                                                    </Label>
                                                                    <Label>
                                                                        {
                                                                            copy.verified_commitment
                                                                        }
                                                                        <Input
                                                                            name="verified_committed_amount"
                                                                            inputMode="decimal"
                                                                            defaultValue={
                                                                                item.committedAmount
                                                                            }
                                                                            required
                                                                        />
                                                                    </Label>
                                                                    <Label>
                                                                        {
                                                                            copy.verified_disbursement
                                                                        }
                                                                        <Input
                                                                            name="verified_disbursed_amount"
                                                                            inputMode="decimal"
                                                                            defaultValue={
                                                                                item.disbursedAmount
                                                                            }
                                                                            required
                                                                        />
                                                                    </Label>
                                                                    <Label>
                                                                        {
                                                                            copy.verified_in_kind
                                                                        }
                                                                        <Input
                                                                            name="verified_in_kind_value"
                                                                            inputMode="decimal"
                                                                            defaultValue={
                                                                                item.inKindValue
                                                                            }
                                                                            required
                                                                        />
                                                                    </Label>
                                                                    <Label>
                                                                        {
                                                                            copy.source_reference
                                                                        }
                                                                        <Input
                                                                            name="source_reference"
                                                                            required
                                                                        />
                                                                    </Label>
                                                                    <Label>
                                                                        {
                                                                            copy.review_note
                                                                        }
                                                                        <Textarea
                                                                            name="review_note"
                                                                            minLength={
                                                                                10
                                                                            }
                                                                            required
                                                                        />
                                                                    </Label>
                                                                    <Button type="submit">
                                                                        {
                                                                            copy.retain_decision
                                                                        }
                                                                    </Button>
                                                                </Form>
                                                            </FormSheet>
                                                        </DropdownMenuItem>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
            <Sheet
                open={detail !== null}
                onOpenChange={(open) => !open && setDetail(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{detail?.partner}</SheetTitle>
                        <SheetDescription>
                            {detail?.project.code} {copy.contribution_chain}
                        </SheetDescription>
                    </SheetHeader>
                    {detail && (
                        <div className="grid gap-6 p-4">
                            <div className="grid grid-cols-2 gap-3 rounded-lg border p-4">
                                <p className="text-sm text-muted-foreground">
                                    {copy.reported_commitment}
                                </p>
                                <p className="text-right font-medium">
                                    {money(
                                        detail.committedAmount,
                                        detail.currency,
                                    )}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {copy.reported_disbursement}
                                </p>
                                <p className="text-right font-medium">
                                    {money(
                                        detail.disbursedAmount,
                                        detail.currency,
                                    )}
                                </p>
                            </div>
                            <section>
                                <h3 className="mb-2 font-semibold">
                                    {copy.evidence}
                                </h3>
                                {detail.documents.map((document) => (
                                    <div
                                        key={document.id}
                                        className="flex items-center justify-between gap-3 border-b py-2"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {document.title}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {document.scanStatus}{' '}
                                                {copy.separator}{' '}
                                                {document.checksum.slice(0, 12)}
                                                {copy.ellipsis}
                                            </p>
                                        </div>
                                        <div className="flex">
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                onClick={() =>
                                                    setPreviewDocument(document)
                                                }
                                                aria-label={`Preview ${document.title}`}
                                            >
                                                <Eye />
                                            </Button>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                asChild
                                            >
                                                <a
                                                    href={download.url({
                                                        document: document.id,
                                                    })}
                                                    aria-label={`Download ${document.title}`}
                                                >
                                                    <Download />
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </section>
                            <section>
                                <h3 className="mb-2 font-semibold">
                                    {copy.decision_history}
                                </h3>
                                {detail.reconciliations.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {copy.no_reconciliation_decision}
                                    </p>
                                ) : (
                                    detail.reconciliations.map((item) => (
                                        <article
                                            key={item.id}
                                            className="mb-3 rounded-lg border p-3"
                                        >
                                            <div className="flex justify-between">
                                                <Badge>{item.decision}</Badge>
                                                <span className="text-xs">
                                                    {copy.version_prefix}
                                                    {item.version}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-sm">
                                                {item.reviewNote}
                                            </p>
                                            <p className="mt-2 font-mono text-[10px] break-all text-muted-foreground">
                                                {item.decisionChecksum}
                                            </p>
                                        </article>
                                    ))
                                )}
                            </section>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
            <Sheet
                open={previewDocument !== null}
                onOpenChange={(open) => !open && setPreviewDocument(null)}
            >
                <SheetContent className="w-full sm:max-w-4xl">
                    <SheetHeader>
                        <SheetTitle>{previewDocument?.title}</SheetTitle>
                        <SheetDescription>
                            {copy.authorized_preview}
                        </SheetDescription>
                    </SheetHeader>
                    {previewDocument && (
                        <iframe
                            className="h-[calc(100vh-8rem)] w-full border-0"
                            title={`Preview ${previewDocument.title}`}
                            src={preview.url({ document: previewDocument.id })}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </Card>
    );
}
