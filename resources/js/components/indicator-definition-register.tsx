import { Form, usePage } from '@inertiajs/react';
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
import WorkspaceEmptyState from '@/components/workspace-empty-state';
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
    definitions,
    currentUserId,
}: {
    definitions: IndicatorDefinitionItem[];
    currentUserId: string;
}) {
    const copy = usePage().props.localization.indicatorDefinitions;
    const [superseding, setSuperseding] =
        useState<IndicatorDefinitionItem | null>(null);

    return (
        <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
            <div className="border-b px-5 py-4">
                <h2 className="font-bold">{copy.title}</h2>
                <p className="text-sm text-muted-foreground">
                    {copy.description}
                </p>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{copy.code}</TableHead>
                        <TableHead>{copy.name}</TableHead>
                        <TableHead>{copy.level}</TableHead>
                        <TableHead>{copy.sector}</TableHead>
                        <TableHead>{copy.version}</TableHead>
                        <TableHead>{copy.status}</TableHead>
                        <TableHead>{copy.catalogue}</TableHead>
                        <TableHead className="text-right">
                            {copy.action}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {definitions.map((item) => (
                        <TableRow key={item.id}>
                            <TableCell className="font-mono text-xs">
                                {item.code}
                            </TableCell>
                            <TableCell>{item.name}</TableCell>
                            <TableCell>
                                {copy[item.resultsLevel] ?? item.resultsLevel}
                            </TableCell>
                            <TableCell>{item.sector ?? '—'}</TableCell>
                            <TableCell>
                                {copy.version_abbreviation}
                                {item.version}
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">
                                    {item.status === 'approved' &&
                                    !item.isCurrentApproved
                                        ? copy.superseded
                                        : (copy[item.status] ?? item.status)}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                                {item.referenceData
                                    ? `${copy.version_abbreviation}${item.referenceData.version} · ${item.referenceData.checksum.slice(0, 10)}…`
                                    : copy.legacy_unpinned}
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
                                                aria-label={copy.open_actions.replace(
                                                    ':code',
                                                    item.code,
                                                )}
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
                                                                        {
                                                                            copy.approve
                                                                        }
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
                                                        {
                                                            copy.create_successor_version
                                                        }
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                ) : (
                                    <span className="text-xs text-muted-foreground">
                                        {item.status === 'draft'
                                            ? copy.independent_approval_required
                                            : item.hasSuccessor
                                              ? copy.successor_draft_pending
                                              : copy.released}
                                    </span>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                    {definitions.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={8} className="p-0">
                                <WorkspaceEmptyState
                                    title={copy.empty_title}
                                    description={copy.empty_description}
                                />
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
            <IndicatorSupersessionSheet
                indicator={superseding}
                open={superseding !== null}
                onOpenChange={(open) => !open && setSuperseding(null)}
            />
        </section>
    );
}
