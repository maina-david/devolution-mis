import { Form } from '@inertiajs/react';
import { CheckCircle2, GitBranch, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import IndicatorSupersessionSheet from '@/components/indicator-supersession-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { approve } from '@/routes/monitoring-evaluation/indicators';

export type IndicatorDefinitionItem = {
    id: string;
    code: string;
    name: string;
    resultsLevel: string;
    version: number;
    status: string;
    sector: string | null;
    createdBy: string;
    supersedesId: string | null;
    changeSummary: string | null;
    canSupersede: boolean;
    isCurrentApproved: boolean;
    hasSuccessor: boolean;
    description: string;
    unitOfMeasure: string;
    valueType: string;
    direction: string;
    frequency: string;
    dataSource: string;
    verificationMethod: string;
    referenceData: {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    } | null;
};

export default function IndicatorDefinitionRegister({
    teamSlug,
    definitions,
    currentUserId,
}: {
    teamSlug: string;
    definitions: IndicatorDefinitionItem[];
    currentUserId: string;
}) {
    const [superseding, setSuperseding] =
        useState<IndicatorDefinitionItem | null>(null);

    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
            <div className="border-b px-5 py-4">
                <h2 className="font-bold">Indicator definition register</h2>
                <p className="text-sm text-muted-foreground">
                    Drafts require approval by an administrator other than their
                    author.
                </p>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Code</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Level</TableHead>
                        <TableHead>Sector</TableHead>
                        <TableHead>Version</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Catalogue</TableHead>
                        <TableHead className="text-right">Action</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {definitions.map((item) => (
                        <TableRow key={item.id}>
                            <TableCell className="font-mono text-xs">
                                {item.code}
                            </TableCell>
                            <TableCell>{item.name}</TableCell>
                            <TableCell>{item.resultsLevel}</TableCell>
                            <TableCell>{item.sector ?? '—'}</TableCell>
                            <TableCell>v{item.version}</TableCell>
                            <TableCell>
                                <Badge variant="outline">
                                    {item.status === 'approved' &&
                                    !item.isCurrentApproved
                                        ? 'superseded'
                                        : item.status}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                                {item.referenceData
                                    ? `v${item.referenceData.version} · ${item.referenceData.checksum.slice(0, 10)}…`
                                    : 'Legacy unpinned'}
                            </TableCell>
                            <TableCell className="text-right">
                                {(item.status === 'draft' &&
                                    item.createdBy !== currentUserId) ||
                                item.canSupersede ? (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Open actions for ${item.code}`}
                                            >
                                                <MoreHorizontal aria-hidden="true" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            className="p-2"
                                        >
                                            <DropdownMenuGroup>
                                                {item.status === 'draft' &&
                                                    item.createdBy !==
                                                        currentUserId && (
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Form
                                                                {...approve.form(
                                                                    {
                                                                        current_team:
                                                                            teamSlug,
                                                                        indicator:
                                                                            item.id,
                                                                    },
                                                                )}
                                                            >
                                                                {({
                                                                    processing,
                                                                }) => (
                                                                    <button
                                                                        type="submit"
                                                                        disabled={
                                                                            processing
                                                                        }
                                                                        className="flex w-full items-center gap-2"
                                                                    >
                                                                        <CheckCircle2 aria-hidden="true" />{' '}
                                                                        Approve
                                                                    </button>
                                                                )}
                                                            </Form>
                                                        </DropdownMenuItem>
                                                    )}
                                                {item.canSupersede && (
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setSuperseding(item)
                                                        }
                                                    >
                                                        <GitBranch aria-hidden="true" />{' '}
                                                        Create successor version
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                ) : (
                                    <span className="text-xs text-muted-foreground">
                                        {item.status === 'draft'
                                            ? 'Independent approval required'
                                            : item.hasSuccessor
                                              ? 'Successor draft pending'
                                              : 'Released'}
                                    </span>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <IndicatorSupersessionSheet
                teamSlug={teamSlug}
                indicator={superseding}
                open={superseding !== null}
                onOpenChange={(open) => !open && setSuperseding(null)}
            />
        </section>
    );
}
