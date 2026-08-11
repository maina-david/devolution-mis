import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    BanknoteIcon,
    FileCheck2Icon,
    FilesIcon,
    ExternalLinkIcon,
    MapPinIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import AssessmentRowAction from '@/components/assessment-row-action';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DateRangeFilter from '@/components/date-range-filter';
import EvidenceRowAction from '@/components/evidence-row-action';
import GrantRowAction from '@/components/grant-row-action';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { DEFAULT_COUNTRY_NAME, formatCurrency } from '@/lib/reference-catalog';
import { index as countiesIndex } from '@/routes/counties';

type TableData = {
    columns: string[];
    rows: WorkspaceRow[];
    pagination: WorkspacePagination;
};
type Props = {
    county: CountyIdentityValue & {
        region: string | null;
        officialWebsiteUrl: string | null;
        logoSourceAuthority: string | null;
        logoVerifiedAt: string | null;
    };
    summary: {
        assessments: number;
        documents: number;
        verifiedDocuments: number;
        allocatedGrants: number;
        disbursedGrants: number;
    };
    assessments: TableData;
    documents: TableData;
    grants: TableData;
    filters: {
        from?: string;
        to?: string;
        search?: string;
        cycle_id?: string;
    };
    cycles: Array<{ id: string; name: string }>;
    capabilities: Record<string, boolean>;
};

const formatCompactCurrency = (value: number) =>
    formatCurrency(value, undefined, {
        notation: 'compact',
        maximumFractionDigits: 1,
    });

export default function CountyShow({
    county,
    summary,
    assessments,
    documents,
    grants,
    filters,
    capabilities,
    cycles,
}: Props) {
    const { currentTeam } = usePage().props;

    if (!currentTeam) {
        return null;
    }

    const cards = [
        {
            label: 'Assessment cycles',
            value: summary.assessments,
            icon: FileCheck2Icon,
        },
        {
            label: 'Evidence documents',
            value: summary.documents,
            icon: FilesIcon,
        },
        {
            label: 'Verified evidence',
            value: summary.verifiedDocuments,
            icon: FileCheck2Icon,
        },
        {
            label: 'Grant disbursement',
            value: formatCompactCurrency(summary.disbursedGrants),
            detail: `of ${formatCompactCurrency(summary.allocatedGrants)}`,
            icon: BanknoteIcon,
        },
    ];

    return (
        <>
            <Head title={`${county.name} county`} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <Button variant="secondary" size="sm" asChild>
                        <Link href={countiesIndex(currentTeam.slug)}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            All counties
                        </Link>
                    </Button>
                    <div className="mt-3 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                        <div className="flex items-center gap-4">
                            <CountyIdentity county={county} />
                            <div>
                                <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                    County 0{county.code} ·{' '}
                                    {county.region ?? DEFAULT_COUNTRY_NAME}
                                </p>
                                <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                    {county.name} County
                                </h1>
                                <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                    Complete assessment, evidence, verification,
                                    and exchequer record within your authorized
                                    scope.
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {county.officialWebsiteUrl && (
                                <Button variant="secondary" size="sm" asChild>
                                    <a
                                        href={county.officialWebsiteUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Official website
                                        <ExternalLinkIcon aria-hidden="true" />
                                    </a>
                                </Button>
                            )}
                            <Badge className="w-fit border-white/20 bg-white/10 text-white">
                                <MapPinIcon />
                                County record
                            </Badge>
                        </div>
                    </div>
                </section>

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    initialCycleId={filters.cycle_id}
                    cycles={cycles}
                />

                <section
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="County summary"
                >
                    {cards.map(({ label, value, detail, icon: Icon }) => (
                        <Card key={label}>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-sm text-muted-foreground">
                                    {label}
                                </CardTitle>
                                <Icon className="size-5 text-[#147a55]" />
                            </CardHeader>
                            <CardContent>
                                <p className="text-2xl font-bold">{value}</p>
                                {detail && (
                                    <p className="text-xs text-muted-foreground">
                                        {detail}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <CountyTable
                    title="Assessment history"
                    data={assessments}
                    renderActions={(row) => (
                        <AssessmentRowAction
                            assessmentId={row.id}
                            status={row.status}
                            teamSlug={currentTeam.slug}
                            capabilities={capabilities}
                        />
                    )}
                />
                <CountyTable
                    title="Document management"
                    data={documents}
                    renderActions={(row) => (
                        <EvidenceRowAction
                            teamSlug={currentTeam.slug}
                            documentId={row.id}
                            status={row.status}
                            canVerify={Boolean(capabilities.verify)}
                            canManage={Boolean(capabilities.upload)}
                            canManageRecords={false}
                            meta={row.meta}
                        />
                    )}
                />
                <CountyTable
                    title="Exchequer and grants"
                    data={grants}
                    renderActions={
                        capabilities.manageGrants
                            ? (row) => (
                                  <GrantRowAction
                                      teamSlug={currentTeam.slug}
                                      grantId={row.id}
                                      status={row.status}
                                      meta={row.meta}
                                  />
                              )
                            : undefined
                    }
                />
            </div>
        </>
    );
}

function CountyTable({
    title,
    data,
    renderActions,
}: {
    title: string;
    data: TableData;
    renderActions?: (row: WorkspaceRow) => ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div className="border-b px-5 py-4">
                <h2 className="font-bold">{title}</h2>
                <p className="text-sm text-muted-foreground">
                    {data.pagination.total.toLocaleString()} matching records
                </p>
            </div>
            {data.rows.length > 0 ? (
                <WorkspaceDataTable
                    columns={data.columns}
                    rows={data.rows}
                    pagination={data.pagination}
                    renderActions={renderActions}
                />
            ) : (
                <WorkspaceEmptyState
                    title="No matching county records"
                    description="Adjust the search or date range. Records remain limited to this county and your assigned access."
                    className="min-h-60 border-0"
                />
            )}
        </section>
    );
}
