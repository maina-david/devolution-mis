import { Head, Link, usePage } from '@inertiajs/react';
import {
    ChartNoAxesCombined,
    Database,
    DownloadIcon,
    FileUp,
    SearchXIcon,
    ShieldCheck,
} from 'lucide-react';
import AssessmentCreateForm from '@/components/assessment-create-form';
import AssessmentRowAction from '@/components/assessment-row-action';
import {
    AuditAssuranceRowAction,
    AuditAssuranceRunControl,
} from '@/components/audit-assurance-controls';
import type { CountyIdentityValue } from '@/components/county-identity';
import DateRangeFilter from '@/components/date-range-filter';
import EvidenceRowAction from '@/components/evidence-row-action';
import EvidenceUploadForm from '@/components/evidence-upload-form';
import GrantRowAction from '@/components/grant-row-action';
import PlatformSettingRowAction from '@/components/platform-setting-row-action';
import ProgrammeUserAccessForm from '@/components/programme-user-access-form';
import ProgrammeUserRowAction from '@/components/programme-user-row-action';
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
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    AssessmentBulkActions,
    EvidenceBulkActions,
    ProgrammeUserBulkActions,
} from '@/components/workspace-bulk-actions';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import { preserveDrilldownFilters } from '@/lib/preserve-drilldown-filters';
import { show as showAssessment } from '@/routes/assessments';
import { index as assessmentAnalytics } from '@/routes/assessments/analytics';
import { show as showCounty } from '@/routes/counties';
import { index as dataImportsIndex } from '@/routes/data-migrations';
import { show as showProgrammeUser } from '@/routes/programme-users';
import { exportMethod } from '@/routes/workspace';

type Props = {
    workspace: {
        title: string;
        description: string;
        columns: string[];
        rows: WorkspaceRow[];
        pagination: WorkspacePagination;
        assessmentOptions?: Array<{ id: string; label: string }>;
        accessOptions?: {
            roles: Array<{ value: string; label: string }>;
            counties: CountyIdentityValue[];
        };
        assessmentCreationOptions?: {
            counties: CountyIdentityValue[];
            cycles: Array<{ id: string; name: string }>;
            pairs: Array<{ countyId: string; cycleId: string }>;
        };
    };
    capabilities: Record<string, boolean>;
    workspaceType: string;
    filters: {
        from?: string;
        to?: string;
        search?: string;
        cycle_id?: string;
        status?: string;
    };
    cycles?: Array<{ id: string; name: string }>;
};

function humanize(value: string): string {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}

export default function ProgrammeWorkspace({
    workspace,
    capabilities,
    workspaceType,
    filters,
    cycles = [],
}: Props) {
    const page = usePage();
    const { auth } = page.props;
    const enabledCapabilities = Object.entries(capabilities).filter(
        ([, enabled]) => enabled,
    );
    const hasActions =
        [
            'assessments',
            'audit-assurance',
            'evidence',
            'grants',
            'users',
            'platform',
        ].includes(workspaceType) &&
        (workspaceType === 'assessments' ||
            Boolean(
                capabilities.download ||
                capabilities.verify ||
                capabilities.manage ||
                capabilities.configure,
            ));
    const renderActions = hasActions
        ? (row: WorkspaceRow) => {
              if (workspaceType === 'assessments') {
                  return (
                      <AssessmentRowAction
                          assessmentId={row.id}
                          status={row.status}
                          capabilities={capabilities}
                      />
                  );
              }

              if (workspaceType === 'evidence' && capabilities.download) {
                  return (
                      <EvidenceRowAction
                          documentId={row.id}
                          status={row.status}
                          canVerify={Boolean(capabilities.verify)}
                          canManage={Boolean(capabilities.upload)}
                          canManageRecords={Boolean(capabilities.manageRecords)}
                          meta={row.meta}
                      />
                  );
              }

              if (
                  workspaceType === 'audit-assurance' &&
                  capabilities.download
              ) {
                  return (
                      <AuditAssuranceRowAction
                          runId={row.id}
                          status={row.status}
                          meta={row.meta}
                      />
                  );
              }

              if (workspaceType === 'grants' && capabilities.manage) {
                  return (
                      <GrantRowAction
                          grantId={row.id}
                          meta={row.meta}
                          status={row.status}
                      />
                  );
              }

              if (workspaceType === 'users' && capabilities.manage) {
                  return (
                      <ProgrammeUserRowAction
                          userId={row.id}
                          isCurrentUser={row.id === auth.user.id}
                      />
                  );
              }

              if (workspaceType === 'platform' && capabilities.configure) {
                  return (
                      <PlatformSettingRowAction
                          settingId={row.id}
                          value={row.meta?.value}
                      />
                  );
              }

              return null;
          }
        : undefined;

    return (
        <>
            <Head title={workspace.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Integrated devolution operations
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {workspace.title}
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-[#c7d6dd] sm:text-base">
                                {workspace.description}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {enabledCapabilities.map(([capability]) => (
                                <Badge
                                    key={capability}
                                    className="border-white/20 bg-white/10 text-white"
                                >
                                    <ShieldCheck aria-hidden="true" />
                                    Can {humanize(capability)}
                                </Badge>
                            ))}
                        </div>
                    </div>
                </section>

                {workspaceType === 'evidence' && capabilities.upload && (
                    <EvidenceUploadForm
                        assessments={workspace.assessmentOptions ?? []}
                    />
                )}
                {workspaceType === 'users' &&
                    capabilities.manage &&
                    workspace.accessOptions && (
                        <div className="flex flex-wrap items-center gap-2">
                            <ProgrammeUserAccessForm
                                roles={workspace.accessOptions.roles}
                                counties={workspace.accessOptions.counties}
                            />
                            {capabilities.bulkImport && (
                                <Button variant="outline" asChild>
                                    <Link href={dataImportsIndex()}>
                                        <FileUp data-icon="inline-start" />
                                        Bulk upload users
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                {workspaceType === 'audit-assurance' && capabilities.run && (
                    <AuditAssuranceRunControl />
                )}

                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    initialCycleId={filters.cycle_id}
                    cycles={cycles.length > 0 ? cycles : undefined}
                    selectFilters={
                        workspaceType === 'audit-assurance'
                            ? [
                                  {
                                      key: 'status',
                                      label: 'Outcome',
                                      value: filters.status,
                                      options: ['pass', 'warn', 'fail'].map(
                                          (value) => ({
                                              id: value,
                                              name: humanize(value),
                                          }),
                                      ),
                                  },
                              ]
                            : []
                    }
                />

                <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold text-foreground">
                                Authorized records
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {workspace.pagination.total.toLocaleString()}{' '}
                                records in your current access scope
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {workspaceType === 'assessments' && (
                                <>
                                    {capabilities.create &&
                                        workspace.assessmentCreationOptions && (
                                            <AssessmentCreateForm
                                                options={
                                                    workspace.assessmentCreationOptions
                                                }
                                            />
                                        )}
                                    <Button variant="outline" asChild>
                                        <Link href={assessmentAnalytics.url()}>
                                            <ChartNoAxesCombined data-icon="inline-start" />
                                            Compare results
                                        </Link>
                                    </Button>
                                </>
                            )}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <DownloadIcon data-icon="inline-start" />
                                        Export
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuGroup>
                                        {['csv', 'xlsx', 'pdf', 'json'].map(
                                            (format) => (
                                                <DropdownMenuItem
                                                    key={format}
                                                    asChild
                                                >
                                                    <a
                                                        href={exportMethod.url(
                                                            {
                                                                workspace:
                                                                    workspaceType,
                                                                format,
                                                            },
                                                            {
                                                                query: filters,
                                                            },
                                                        )}
                                                    >
                                                        {format.toUpperCase()}
                                                    </a>
                                                </DropdownMenuItem>
                                            ),
                                        )}
                                    </DropdownMenuGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Database
                                className="size-5 text-[#147a55]"
                                aria-hidden="true"
                            />
                        </div>
                    </div>

                    {workspace.rows.length > 0 ? (
                        <WorkspaceDataTable
                            columns={workspace.columns}
                            rows={workspace.rows}
                            pagination={workspace.pagination}
                            bulkExport={{
                                workspace: workspaceType,
                                filters,
                            }}
                            renderActions={
                                ['evidence', 'audit-assurance'].includes(
                                    workspaceType,
                                )
                                    ? undefined
                                    : renderActions
                            }
                            renderActionControl={
                                [
                                    'evidence',
                                    'audit-assurance',
                                    'grants',
                                ].includes(workspaceType)
                                    ? renderActions
                                    : undefined
                            }
                            renderBulkActions={(
                                selectedRows,
                                clearSelection,
                            ) => (
                                <>
                                    {workspaceType === 'assessments' && (
                                        <AssessmentBulkActions
                                            rows={selectedRows}
                                            capabilities={capabilities}
                                            clearSelection={clearSelection}
                                        />
                                    )}
                                    {workspaceType === 'evidence' &&
                                        capabilities.verify && (
                                            <EvidenceBulkActions
                                                rows={selectedRows}
                                                clearSelection={clearSelection}
                                            />
                                        )}
                                    {workspaceType === 'users' &&
                                        capabilities.manage && (
                                            <ProgrammeUserBulkActions
                                                rows={selectedRows}
                                                clearSelection={clearSelection}
                                            />
                                        )}
                                </>
                            )}
                            canSelectRow={
                                workspaceType === 'users'
                                    ? (row) => row.id !== auth.user.id
                                    : undefined
                            }
                            getRowHref={(row) => {
                                if (workspaceType === 'assessments') {
                                    return preserveDrilldownFilters(
                                        showAssessment.url({
                                            assessment: row.id,
                                        }),
                                        page.url,
                                    );
                                }

                                if (
                                    workspaceType === 'counties' &&
                                    row.meta?.countyId
                                ) {
                                    return preserveDrilldownFilters(
                                        showCounty.url({
                                            county: row.meta.countyId,
                                        }),
                                        page.url,
                                    );
                                }

                                if (workspaceType === 'users') {
                                    return preserveDrilldownFilters(
                                        showProgrammeUser.url({
                                            programmeUser: row.id,
                                        }),
                                        page.url,
                                    );
                                }

                                return undefined;
                            }}
                        />
                    ) : (
                        <Empty className="min-h-72 border-0">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <SearchXIcon aria-hidden="true" />
                                </EmptyMedia>
                                <EmptyTitle>No matching records</EmptyTitle>
                                <EmptyDescription>
                                    Adjust the search, date, or assessment
                                    cycle. If the result remains empty, confirm
                                    that your county or programme access is
                                    assigned.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    )}
                </section>
            </div>
        </>
    );
}
