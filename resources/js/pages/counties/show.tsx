import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    BanknoteIcon,
    FileCheck2Icon,
    FilesIcon,
    ExternalLinkIcon,
    ChevronDownIcon,
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
import { interpolate } from '@/hooks/use-localization';
import {
    DEFAULT_COUNTRY_NAME,
    formatCurrency,
    formatNumber,
} from '@/lib/reference-catalog';
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
    administrativeHierarchy: {
        parentUnitCount: number;
        subCountyCount: number;
        constituencyCount: number;
        wardCount: number;
        registeredVoters: number;
        units: Array<{
            id: string;
            code: string;
            name: string;
            classification: string;
            sourceAuthority: string;
            effectiveFrom: string;
            registeredVoters: number;
            wards: Array<{
                id: string;
                code: string;
                name: string;
                registeredVoters: number;
            }>;
        }>;
    };
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
    administrativeHierarchy,
    filters,
    capabilities,
    cycles,
}: Props) {
    const { localization } = usePage().props;
    const copy = localization.countyDetail;
    const cards = [
        {
            label: copy.assessment_cycles,
            value: summary.assessments,
            icon: FileCheck2Icon,
        },
        {
            label: copy.evidence_documents,
            value: summary.documents,
            icon: FilesIcon,
        },
        {
            label: copy.verified_evidence,
            value: summary.verifiedDocuments,
            icon: FileCheck2Icon,
        },
        {
            label: copy.grant_disbursement,
            value: formatCompactCurrency(summary.disbursedGrants),
            detail: interpolate(copy.of_amount, {
                amount: formatCompactCurrency(summary.allocatedGrants),
            }),
            icon: BanknoteIcon,
        },
    ];

    return (
        <>
            <Head
                title={interpolate(copy.page_title, { county: county.name })}
            />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <Button variant="secondary" size="sm" asChild>
                        <Link href={countiesIndex()}>
                            <ArrowLeftIcon
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                            {copy.all_counties}
                        </Link>
                    </Button>
                    <div className="mt-3 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                        <div className="flex items-center gap-4">
                            <CountyIdentity county={county} />
                            <div>
                                <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                    {interpolate(copy.county_code_region, {
                                        code: String(county.code).padStart(
                                            3,
                                            '0',
                                        ),
                                        region:
                                            county.region ??
                                            DEFAULT_COUNTRY_NAME,
                                    })}
                                </p>
                                <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                    {interpolate(copy.page_title, {
                                        county: county.name,
                                    })}
                                </h1>
                                <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                    {copy.description}
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
                                        {copy.official_website}
                                        <ExternalLinkIcon aria-hidden="true" />
                                    </a>
                                </Button>
                            )}
                            <Badge className="w-fit border-white/20 bg-white/10 text-white">
                                <MapPinIcon aria-hidden="true" />
                                {copy.county_record}
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
                    aria-label={copy.county_summary}
                >
                    {cards.map(({ label, value, detail, icon: Icon }) => (
                        <Card key={label}>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-sm text-muted-foreground">
                                    {label}
                                </CardTitle>
                                <Icon
                                    className="size-5 text-[#147a55]"
                                    aria-hidden="true"
                                />
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

                <Card>
                    <CardHeader>
                        <CardTitle>{copy.administrative_hierarchy}</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {copy.administrative_hierarchy_description}
                        </p>
                    </CardHeader>
                    <CardContent>
                        {administrativeHierarchy.units.length > 0 ? (
                            <>
                                <dl className="mb-5 grid gap-3 sm:grid-cols-3">
                                    {[
                                        [
                                            copy.parent_units,
                                            administrativeHierarchy.parentUnitCount,
                                        ],
                                        [
                                            copy.wards,
                                            administrativeHierarchy.wardCount,
                                        ],
                                        [
                                            copy.registered_voters,
                                            administrativeHierarchy.registeredVoters,
                                        ],
                                    ].map(([label, value]) => (
                                        <div
                                            key={String(label)}
                                            className="rounded-lg border p-3"
                                        >
                                            <dt className="text-xs text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="mt-1 text-xl font-bold">
                                                {formatNumber(Number(value))}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <div className="grid gap-3 lg:grid-cols-2">
                                    {administrativeHierarchy.units.map(
                                        (unit) => (
                                            <details
                                                key={unit.id}
                                                className="group rounded-lg border bg-background p-4"
                                            >
                                                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                                    <span>
                                                        {unit.code}{' '}
                                                        {copy.separator}{' '}
                                                        {unit.name}
                                                    </span>
                                                    <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                                        <Badge
                                                            variant="outline"
                                                            className="font-normal"
                                                        >
                                                            {copy[
                                                                `classification_${unit.classification}`
                                                            ] ??
                                                                unit.classification.replaceAll(
                                                                    '_',
                                                                    ' ',
                                                                )}
                                                        </Badge>
                                                        {interpolate(
                                                            copy.ward_count,
                                                            {
                                                                count: formatNumber(
                                                                    unit.wards
                                                                        .length,
                                                                ),
                                                            },
                                                        )}
                                                        <ChevronDownIcon
                                                            className="size-4 transition-transform group-open:rotate-180"
                                                            aria-hidden="true"
                                                        />
                                                    </span>
                                                </summary>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {interpolate(
                                                        copy.source_lineage,
                                                        {
                                                            authority:
                                                                unit.sourceAuthority,
                                                            date: new Date(
                                                                unit.effectiveFrom,
                                                            ).toLocaleDateString(
                                                                localization.current,
                                                            ),
                                                        },
                                                    )}
                                                </p>
                                                <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                                                    {unit.wards.map((ward) => (
                                                        <li
                                                            key={ward.id}
                                                            className="rounded-md bg-muted/50 px-3 py-2 text-sm"
                                                        >
                                                            <span className="font-medium">
                                                                {ward.code}{' '}
                                                                {copy.separator}{' '}
                                                                {ward.name}
                                                            </span>
                                                            <span className="block text-xs text-muted-foreground">
                                                                {
                                                                    copy.registered_voters
                                                                }
                                                                {
                                                                    copy.label_separator
                                                                }{' '}
                                                                {formatNumber(
                                                                    ward.registeredVoters,
                                                                )}
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </details>
                                        ),
                                    )}
                                </div>
                            </>
                        ) : (
                            <WorkspaceEmptyState
                                title={copy.no_administrative_units}
                                description={
                                    copy.no_administrative_units_description
                                }
                                className="min-h-48 border-0"
                            />
                        )}
                    </CardContent>
                </Card>

                <CountyTable
                    title={copy.assessment_history}
                    data={assessments}
                    locale={localization.current}
                    copy={copy}
                    renderActions={(row) => (
                        <AssessmentRowAction
                            assessmentId={row.id}
                            status={row.status}
                            capabilities={capabilities}
                            isLegacy={row.meta?.isLegacy === 'true'}
                        />
                    )}
                />
                <CountyTable
                    title={copy.document_management}
                    data={documents}
                    locale={localization.current}
                    copy={copy}
                    renderActions={(row) => (
                        <EvidenceRowAction
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
                    title={copy.exchequer_grants}
                    data={grants}
                    locale={localization.current}
                    copy={copy}
                    renderActions={
                        capabilities.manageGrants
                            ? (row) => (
                                  <GrantRowAction
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
    locale,
    copy,
}: {
    title: string;
    data: TableData;
    renderActions?: (row: WorkspaceRow) => ReactNode;
    locale: string;
    copy: Record<string, string>;
}) {
    return (
        <section className="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div className="border-b px-5 py-4">
                <h2 className="font-bold">{title}</h2>
                <p className="text-sm text-muted-foreground">
                    {interpolate(copy.matching_records, {
                        count: data.pagination.total.toLocaleString(locale),
                    })}
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
                    title={copy.no_matching_county_records}
                    description={copy.no_matching_county_records_description}
                    className="min-h-60 border-0"
                />
            )}
        </section>
    );
}
