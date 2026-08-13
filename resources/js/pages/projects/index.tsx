import { Head, usePage } from '@inertiajs/react';
import { BriefcaseBusiness, Download } from 'lucide-react';
import type { CountyIdentityValue } from '@/components/county-identity';
import DateRangeFilter from '@/components/date-range-filter';
import ProjectInitiationForm from '@/components/project-initiation-form';
import { Button } from '@/components/ui/button';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { interpolate } from '@/hooks/use-localization';
import { preserveDrilldownFilters } from '@/lib/preserve-drilldown-filters';
import { show } from '@/routes/projects';
import { exportMethod } from '@/routes/workspace';

type Project = {
    id: string;
    code: string;
    title: string;
    county: CountyIdentityValue;
    sector: string;
    programme: string | null;
    stage: string;
    status: string;
    progress: string;
    budget: string;
    expenditure: string;
    milestones: number;
    risks: number;
    referenceRelease: {
        version: number;
        checksum: string;
        effectiveFrom: string;
        status: string;
    } | null;
};
type Option = { id: string; name: string; code?: string };
type Props = {
    projects: {
        data: Project[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { from?: string; to?: string; search?: string };
    capabilities: { manage: boolean; submitUpdates: boolean };
    options: {
        counties: Option[];
        sectors: Option[];
        programmes: Option[];
        organizations: Option[];
        indicators: Option[];
    };
};

export default function ProjectIndex({
    projects,
    filters,
    capabilities,
    options,
}: Props) {
    const page = usePage();
    const copy = page.props.localization.projects;
    const locale = page.props.localization.current;
    const rows: WorkspaceRow[] = projects.data.map((project) => ({
        id: project.id,
        status: project.status,
        cells: [
            `${project.code} · ${project.title}`,
            project.county,
            project.sector,
            project.referenceRelease
                ? `v${project.referenceRelease.version}`
                : copy.legacy_unpinned,
            project.stage,
            `${Number(project.progress)}%`,
            `${Number(project.expenditure).toLocaleString(locale)} / ${Number(project.budget).toLocaleString(locale)}`,
            project.status,
        ],
    }));
    const pagination: WorkspacePagination = {
        currentPage: projects.current_page,
        lastPage: projects.last_page,
        perPage: projects.per_page,
        total: projects.total,
    };

    return (
        <>
            <Head title={copy.project_delivery} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                        {copy.investment_delivery_control}
                    </p>
                    <h1 className="mt-3 text-3xl font-bold">
                        {copy.project_management}
                    </h1>
                    <p className="mt-3 max-w-3xl text-[#c7d6dd]">
                        {copy.project_management_description}
                    </p>
                </section>
                {capabilities.manage && <ProjectInitiationForm {...options} />}
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                />
                <section className="overflow-hidden rounded-xl border bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                        <div className="flex items-center gap-3">
                            <BriefcaseBusiness
                                className="size-5 text-[#147a55]"
                                aria-hidden="true"
                            />
                            <div>
                                <h2 className="font-bold">
                                    {copy.authorized_portfolio}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {interpolate(
                                        copy.authorized_portfolio_count,
                                        {
                                            count: projects.total.toLocaleString(
                                                locale,
                                            ),
                                        },
                                    )}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {['csv', 'xlsx', 'pdf', 'json'].map((format) => (
                                <Button
                                    key={format}
                                    asChild
                                    size="sm"
                                    variant="outline"
                                >
                                    <a
                                        href={exportMethod.url(
                                            { workspace: 'projects', format },
                                            { query: filters },
                                        )}
                                    >
                                        <Download aria-hidden="true" />
                                        {format.toUpperCase()}
                                    </a>
                                </Button>
                            ))}
                        </div>
                    </div>
                    {rows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                copy.project,
                                copy.lead_county,
                                copy.sector,
                                copy.reference_release,
                                copy.lifecycle_stage,
                                copy.physical_progress,
                                copy.expenditure_budget,
                                copy.status,
                            ]}
                            rows={rows}
                            pagination={pagination}
                            bulkExport={{
                                workspace: 'projects',
                                filters,
                            }}
                            getRowHref={(row) =>
                                preserveDrilldownFilters(
                                    show.url({ project: row.id }),
                                    page.url,
                                )
                            }
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_matching_projects}
                            description={copy.no_matching_projects_description}
                            className="min-h-72 border-0"
                        />
                    )}
                </section>
            </div>
        </>
    );
}
