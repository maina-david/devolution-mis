'use no memo';

import { router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type {
    RowSelectionState,
    SortingFn,
    SortingState,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown, MoreHorizontal } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import CountyIdentity, {
    CountyIdentityGroup,
} from '@/components/county-identity';
import type {
    CountyIdentityGroupValue,
    CountyIdentityValue,
} from '@/components/county-identity';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { WorkspaceBulkExportActions } from '@/components/workspace-bulk-actions';

export type WorkspaceRow = {
    id: string;
    cells: Array<
        number | string | null | CountyIdentityValue | CountyIdentityGroupValue
    >;
    status?: string;
    meta?: Record<string, string | null>;
    documents?: WorkspaceDocument[];
};
export type WorkspaceDocument = {
    id: string;
    purpose: string;
    title: string;
    category: string;
    sourceType: string;
    originalName: string | null;
    mimeType: string | null;
    scanStatus: string;
    ocrStatus: string;
};
export type WorkspacePagination = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    pageName?: string;
};

function humanize(value: string) {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}

function sortableValue(value: WorkspaceRow['cells'][number]): string | number {
    if (typeof value === 'number') {
        return value;
    }

    if (isCountyIdentity(value)) {
        return value.name;
    }

    if (isCountyIdentityGroup(value)) {
        return value.items.map((county) => county.name).join(', ');
    }

    return String(value ?? '').toLocaleLowerCase();
}

const workspaceSorting: SortingFn<WorkspaceRow> = (left, right, columnId) => {
    const columnIndex = Number(columnId.replace('column-', ''));
    const leftValue = sortableValue(left.original.cells[columnIndex]);
    const rightValue = sortableValue(right.original.cells[columnIndex]);

    if (typeof leftValue === 'number' && typeof rightValue === 'number') {
        return leftValue - rightValue;
    }

    return String(leftValue).localeCompare(String(rightValue), undefined, {
        numeric: true,
        sensitivity: 'base',
    });
};

export default function WorkspaceDataTable({
    columns,
    rows,
    pagination,
    renderActions,
    renderActionControl,
    getRowHref,
    renderBulkActions,
    canSelectRow,
    bulkExport,
    allowFilteredBulkSelection = false,
}: {
    columns: string[];
    rows: WorkspaceRow[];
    pagination: WorkspacePagination;
    renderActions?: (row: WorkspaceRow) => ReactNode;
    renderActionControl?: (row: WorkspaceRow) => ReactNode;
    getRowHref?: (row: WorkspaceRow) => string | undefined;
    renderBulkActions?: (
        selectedRows: WorkspaceRow[],
        clearSelection: () => void,
        selection: { mode: 'selected' | 'filtered'; count: number },
    ) => ReactNode;
    canSelectRow?: (row: WorkspaceRow) => boolean;
    bulkExport?: {
        teamSlug: string;
        workspace: string;
        filters: Record<string, string | undefined>;
    };
    allowFilteredBulkSelection?: boolean;
}) {
    const page = usePage();
    const [sorting, setSorting] = useState<SortingState>([]);
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [allFilteredSelected, setAllFilteredSelected] = useState(false);
    const helper = createColumnHelper<WorkspaceRow>();
    const hasBulkActions = Boolean(renderBulkActions || bulkExport);
    const definitions = [
        ...(hasBulkActions
            ? [
                  helper.display({
                      id: 'select',
                      enableSorting: false,
                      header: ({ table }) => (
                          <Checkbox
                              checked={
                                  table.getIsAllPageRowsSelected() ||
                                  (table.getIsSomePageRowsSelected() &&
                                      'indeterminate')
                              }
                              onCheckedChange={(checked) =>
                                  table.toggleAllPageRowsSelected(
                                      Boolean(checked),
                                  )
                              }
                              aria-label="Select all rows on this page"
                          />
                      ),
                      cell: ({ row }) => (
                          <Checkbox
                              checked={row.getIsSelected()}
                              disabled={!row.getCanSelect()}
                              onCheckedChange={(checked) =>
                                  row.toggleSelected(Boolean(checked))
                              }
                              aria-label={`Select row ${row.index + 1}`}
                          />
                      ),
                  }),
              ]
            : []),
        helper.display({
            id: 'row-number',
            header: '#',
            enableSorting: false,
            cell: ({ row }) =>
                (pagination.currentPage - 1) * pagination.perPage +
                row.index +
                1,
        }),
        ...columns.map((label, index) =>
            helper.accessor((row) => row.cells[index], {
                id: `column-${index}`,
                header: label,
                sortingFn: workspaceSorting,
                cell: ({ row, getValue }) => {
                    const value = getValue();

                    if (isCountyIdentity(value)) {
                        return <CountyIdentity county={value} compact />;
                    }

                    if (isCountyIdentityGroup(value)) {
                        return <CountyIdentityGroup counties={value.items} />;
                    }

                    return index === columns.length - 1 &&
                        row.original.status ? (
                        <Badge variant="outline">
                            {humanize(String(value))}
                        </Badge>
                    ) : (
                        String(value ?? '—')
                    );
                },
            }),
        ),
    ];

    if (renderActions || renderActionControl) {
        definitions.push(
            helper.accessor((): WorkspaceRow['cells'][number] => null, {
                id: 'actions',
                enableSorting: false,
                header: () => <span className="sr-only">Actions</span>,
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        {renderActionControl ? (
                            renderActionControl?.(row.original)
                        ) : (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Open row actions"
                                    >
                                        <MoreHorizontal aria-hidden="true" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="min-w-56 p-2"
                                >
                                    {renderActions?.(row.original)}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                ),
            }),
        );
    }

    // TanStack Table returns a stable stateful table API that React Compiler intentionally does not memoize.
    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: rows,
        columns: definitions,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        onSortingChange: setSorting,
        onRowSelectionChange: setRowSelection,
        enableRowSelection: (row) =>
            canSelectRow ? canSelectRow(row.original) : true,
        getRowId: (row) => row.id,
        state: { sorting, rowSelection },
        manualPagination: true,
        pageCount: pagination.lastPage,
    });
    const visitPage = (target: number) => {
        const url = new URL(page.url, window.location.origin);
        url.searchParams.set(pagination.pageName ?? 'page', String(target));
        router.get(
            `${url.pathname}?${url.searchParams.toString()}`,
            {},
            { preserveState: true, preserveScroll: true },
        );
    };
    const changePerPage = (value: string) => {
        const url = new URL(page.url, window.location.origin);
        url.searchParams.set('per_page', value);
        url.searchParams.set(pagination.pageName ?? 'page', '1');
        router.get(
            `${url.pathname}?${url.searchParams.toString()}`,
            {},
            { preserveState: true, preserveScroll: true },
        );
    };
    const firstRecord = pagination.total
        ? (pagination.currentPage - 1) * pagination.perPage + 1
        : 0;
    const lastRecord = Math.min(
        pagination.currentPage * pagination.perPage,
        pagination.total,
    );
    const selectedRows = table
        .getSelectedRowModel()
        .rows.map((row) => row.original);
    const selectablePageRows = table
        .getRowModel()
        .rows.filter((row) => row.getCanSelect()).length;
    const selection = {
        mode: allFilteredSelected
            ? ('filtered' as const)
            : ('selected' as const),
        count: allFilteredSelected ? pagination.total : selectedRows.length,
    };
    const clearSelection = () => {
        setRowSelection({});
        setAllFilteredSelected(false);
    };

    useEffect(() => {
        clearSelection();
    }, [rows]);

    return (
        <>
            {hasBulkActions && selectedRows.length > 0 && (
                <div
                    className="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/40 px-5 py-3"
                    role="region"
                    aria-label="Bulk actions"
                >
                    <p className="text-sm font-medium" aria-live="polite">
                        {allFilteredSelected
                            ? `${pagination.total} matching records selected`
                            : `${selectedRows.length} selected on this page`}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        {allowFilteredBulkSelection &&
                            !allFilteredSelected &&
                            selectedRows.length === selectablePageRows &&
                            pagination.total > selectedRows.length &&
                            pagination.total <= 100 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setAllFilteredSelected(true)}
                                >
                                    Select all {pagination.total} matching
                                </Button>
                            )}
                        {bulkExport && (
                            <WorkspaceBulkExportActions
                                teamSlug={bulkExport.teamSlug}
                                workspace={bulkExport.workspace}
                                rows={selectedRows}
                                filters={bulkExport.filters}
                                selectionMode={selection.mode}
                            />
                        )}
                        {renderBulkActions?.(
                            selectedRows,
                            clearSelection,
                            selection,
                        )}
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clearSelection}
                        >
                            Clear selection
                        </Button>
                    </div>
                </div>
            )}
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((group) => (
                        <TableRow key={group.id}>
                            {group.headers.map((header) => {
                                const canSort = header.column.getCanSort();
                                const direction = header.column.getIsSorted();

                                return (
                                    <TableHead key={header.id}>
                                        {canSort ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="-ml-3"
                                                onClick={header.column.getToggleSortingHandler()}
                                                aria-label={`Sort by ${String(header.column.columnDef.header)}`}
                                            >
                                                {flexRender(
                                                    header.column.columnDef
                                                        .header,
                                                    header.getContext(),
                                                )}
                                                {direction === 'asc' ? (
                                                    <ArrowUp data-icon="inline-end" />
                                                ) : direction === 'desc' ? (
                                                    <ArrowDown data-icon="inline-end" />
                                                ) : (
                                                    <ArrowUpDown data-icon="inline-end" />
                                                )}
                                            </Button>
                                        ) : (
                                            flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )
                                        )}
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {table.getRowModel().rows.map((row, visibleRowIndex) => {
                        const href = getRowHref?.(row.original);

                        return (
                            <TableRow
                                key={row.id}
                                tabIndex={href ? 0 : undefined}
                                role={href ? 'link' : undefined}
                                className={
                                    href
                                        ? 'cursor-pointer focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset'
                                        : undefined
                                }
                                onClick={(event) => {
                                    if (
                                        href &&
                                        !(event.target as HTMLElement).closest(
                                            'a, button, input, select, textarea',
                                        )
                                    ) {
                                        router.visit(href);
                                    }
                                }}
                                onKeyDown={(event) => {
                                    if (
                                        href &&
                                        (event.key === 'Enter' ||
                                            event.key === ' ')
                                    ) {
                                        event.preventDefault();
                                        router.visit(href);
                                    }
                                }}
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell key={cell.id}>
                                        {cell.column.id === 'row-number'
                                            ? (pagination.currentPage - 1) *
                                                  pagination.perPage +
                                              visibleRowIndex +
                                              1
                                            : flexRender(
                                                  cell.column.columnDef.cell,
                                                  cell.getContext(),
                                              )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
            <div className="flex flex-col justify-between gap-4 border-t px-5 py-4 lg:flex-row lg:items-center">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p className="text-sm text-muted-foreground">
                        Showing {firstRecord.toLocaleString()}–
                        {lastRecord.toLocaleString()} of{' '}
                        {pagination.total.toLocaleString()} records
                    </p>
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Rows per page
                        </span>
                        <Select
                            value={String(pagination.perPage)}
                            onValueChange={changePerPage}
                        >
                            <SelectTrigger
                                className="w-20"
                                aria-label="Rows per page"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Rows per page</SelectLabel>
                                    {Array.from(
                                        new Set([
                                            pagination.perPage,
                                            10,
                                            25,
                                            50,
                                        ]),
                                    )
                                        .sort((left, right) => left - right)
                                        .map((size) => (
                                            <SelectItem
                                                key={size}
                                                value={String(size)}
                                            >
                                                {size}
                                            </SelectItem>
                                        ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <Pagination className="w-auto">
                    <PaginationContent>
                        <PaginationItem>
                            <PaginationPrevious
                                href="#"
                                aria-disabled={pagination.currentPage === 1}
                                onClick={(event) => {
                                    event.preventDefault();

                                    if (pagination.currentPage > 1) {
                                        visitPage(pagination.currentPage - 1);
                                    }
                                }}
                            />
                        </PaginationItem>
                        <PaginationItem>
                            <PaginationNext
                                href="#"
                                aria-disabled={
                                    pagination.currentPage ===
                                    pagination.lastPage
                                }
                                onClick={(event) => {
                                    event.preventDefault();

                                    if (
                                        pagination.currentPage <
                                        pagination.lastPage
                                    ) {
                                        visitPage(pagination.currentPage + 1);
                                    }
                                }}
                            />
                        </PaginationItem>
                    </PaginationContent>
                </Pagination>
            </div>
        </>
    );
}

function isCountyIdentityGroup(
    value: unknown,
): value is CountyIdentityGroupValue {
    return (
        typeof value === 'object' &&
        value !== null &&
        'kind' in value &&
        value.kind === 'county-list' &&
        'items' in value &&
        Array.isArray(value.items)
    );
}

function isCountyIdentity(value: unknown): value is CountyIdentityValue {
    return (
        typeof value === 'object' &&
        value !== null &&
        'kind' in value &&
        value.kind === 'county'
    );
}
