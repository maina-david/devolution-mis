import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    KeyRound,
    MapPin,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import CountyIdentity from '@/components/county-identity';
import type { CountyIdentityValue } from '@/components/county-identity';
import DateRangeFilter from '@/components/date-range-filter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import { DEFAULT_LOCALE } from '@/lib/reference-catalog';
import { index as usersIndex } from '@/routes/programme-users';

type TableData = { rows: WorkspaceRow[]; pagination: WorkspacePagination };
type GovernanceItem = Record<string, unknown> & { id: string };

type Props = {
    profile: {
        id: string;
        name: string;
        email: string;
        role: { value: string; label: string };
        status: string;
        homeCounty: CountyIdentityValue | null;
        assignedCounties: CountyIdentityValue[];
        permissions: string[];
        teams: Array<{ id: string; name: string }>;
        emailVerifiedAt: string | null;
        twoFactorEnabled: boolean;
        passkeyCount: number;
        accessRevokedAt: string | null;
        accessRevocationReason: string | null;
        createdAt: string | null;
        updatedAt: string | null;
    };
    summary: {
        sessionCount: number | null;
        pageViewCount: number | null;
        auditEventCount: number | null;
        lastSeenAt: string | null;
        currentPage: string | null;
    };
    sessions: TableData | null;
    pageViews: TableData | null;
    auditEvents: TableData | null;
    accessGovernance: {
        lifecycleRequests: GovernanceItem[];
        accessReviews: GovernanceItem[];
        delegations: GovernanceItem[];
    } | null;
    capabilities: {
        viewActivity: boolean;
        viewAudit: boolean;
        viewAccessGovernance: boolean;
    };
    filters: Record<string, string | undefined>;
};

function humanize(value: string): string {
    return value.replaceAll('_', ' ').replaceAll('-', ' ');
}

function dateTime(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(DEFAULT_LOCALE)
        : 'Not recorded';
}

function Detail({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="border-b pb-3 last:border-b-0">
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-sm font-medium text-foreground">
                {value}
            </dd>
        </div>
    );
}

function DataSection({
    title,
    columns,
    data,
}: {
    title: string;
    columns: string[];
    data: TableData;
}) {
    return (
        <Card>
            <CardHeader className="border-b">
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                <WorkspaceDataTable
                    columns={columns}
                    rows={data.rows}
                    pagination={data.pagination}
                />
            </CardContent>
        </Card>
    );
}

export default function ProgrammeUserProfile(props: Props) {
    const { currentTeam } = usePage().props;
    const { profile, summary, capabilities } = props;

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title={`${profile.name} · User record`} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <Button
                        asChild
                        variant="link"
                        className="mb-3 h-auto p-0 text-[#83d4ad]"
                    >
                        <Link href={usersIndex(currentTeam.slug)}>
                            <ArrowLeft aria-hidden="true" /> User access
                        </Link>
                    </Button>
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                Governed identity record
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {profile.name}
                            </h1>
                            <p className="mt-2 text-[#c7d6dd]">
                                {profile.email}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge className="border-white/20 bg-white/10 text-white">
                                {profile.role.label}
                            </Badge>
                            <Badge className="border-white/20 bg-white/10 text-white">
                                {humanize(profile.status)}
                            </Badge>
                        </div>
                    </div>
                </section>

                <DateRangeFilter
                    initialFrom={props.filters.from}
                    initialTo={props.filters.to}
                    initialSearch={props.filters.search}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        ['Recorded sessions', summary.sessionCount],
                        ['Page accesses', summary.pageViewCount],
                        ['Audit events', summary.auditEventCount],
                        ['Last seen', dateTime(summary.lastSeenAt)],
                    ].map(([label, value]) => (
                        <Card key={String(label)}>
                            <CardContent className="p-5">
                                <p className="text-sm text-muted-foreground">
                                    {label}
                                </p>
                                <p className="mt-2 text-2xl font-bold text-foreground">
                                    {value ?? 'Restricted'}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Tabs defaultValue="overview" className="gap-4">
                    <TabsList className="h-auto flex-wrap justify-start">
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        {capabilities.viewActivity && (
                            <TabsTrigger value="sessions">Sessions</TabsTrigger>
                        )}
                        {capabilities.viewActivity && (
                            <TabsTrigger value="pages">
                                Page history
                            </TabsTrigger>
                        )}
                        {capabilities.viewAudit && (
                            <TabsTrigger value="audit">Audit trail</TabsTrigger>
                        )}
                        {capabilities.viewAccessGovernance && (
                            <TabsTrigger value="governance">
                                Access governance
                            </TabsTrigger>
                        )}
                    </TabsList>

                    <TabsContent value="overview">
                        <div className="grid gap-4 lg:grid-cols-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <UserRound /> Identity
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <dl className="space-y-3">
                                        <Detail
                                            label="Email verified"
                                            value={dateTime(
                                                profile.emailVerifiedAt,
                                            )}
                                        />
                                        <Detail
                                            label="Created"
                                            value={dateTime(profile.createdAt)}
                                        />
                                        <Detail
                                            label="Updated"
                                            value={dateTime(profile.updatedAt)}
                                        />
                                        <Detail
                                            label="Current activity"
                                            value={
                                                summary.currentPage ?? 'Offline'
                                            }
                                        />
                                        {profile.accessRevokedAt && (
                                            <Detail
                                                label="Revocation"
                                                value={`${dateTime(profile.accessRevokedAt)} · ${profile.accessRevocationReason ?? 'No reason recorded'}`}
                                            />
                                        )}
                                    </dl>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <MapPin /> Scope
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <Detail
                                        label="Home county"
                                        value={
                                            profile.homeCounty ? (
                                                <CountyIdentity
                                                    county={profile.homeCounty}
                                                    compact
                                                />
                                            ) : (
                                                'National / portfolio'
                                            )
                                        }
                                    />
                                    <Detail
                                        label="Assigned counties"
                                        value={
                                            profile.assignedCounties.length
                                                ? profile.assignedCounties
                                                      .map(
                                                          (county) =>
                                                              county.name,
                                                      )
                                                      .join(', ')
                                                : 'None'
                                        }
                                    />
                                    <Detail
                                        label="Teams"
                                        value={
                                            profile.teams
                                                .map((team) => team.name)
                                                .join(', ') || 'None'
                                        }
                                    />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <KeyRound /> Security
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <dl className="space-y-3">
                                        <Detail
                                            label="Role"
                                            value={profile.role.label}
                                        />
                                        <Detail
                                            label="Two-factor authentication"
                                            value={
                                                profile.twoFactorEnabled
                                                    ? 'Enabled'
                                                    : 'Not enabled'
                                            }
                                        />
                                        <Detail
                                            label="Registered passkeys"
                                            value={profile.passkeyCount.toLocaleString()}
                                        />
                                        <Detail
                                            label="Effective permissions"
                                            value={`${profile.permissions.length} permissions`}
                                        />
                                    </dl>
                                </CardContent>
                            </Card>
                        </div>
                        <Card className="mt-4">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldCheck /> Effective permissions
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-2">
                                {profile.permissions.map((permission) => (
                                    <Badge key={permission} variant="outline">
                                        {humanize(permission)}
                                    </Badge>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>
                    {props.sessions && (
                        <TabsContent value="sessions">
                            <DataSection
                                title="Authentication sessions"
                                columns={[
                                    'Current page',
                                    'IP address',
                                    'Logged in',
                                    'Last seen',
                                    'Logged out',
                                ]}
                                data={props.sessions}
                            />
                        </TabsContent>
                    )}
                    {props.pageViews && (
                        <TabsContent value="pages">
                            <DataSection
                                title="Authorized page history"
                                columns={[
                                    'Page',
                                    'Route',
                                    'Path',
                                    'IP address',
                                    'Viewed',
                                ]}
                                data={props.pageViews}
                            />
                        </TabsContent>
                    )}
                    {props.auditEvents && (
                        <TabsContent value="audit">
                            <DataSection
                                title="Correlated audit trail"
                                columns={[
                                    'Action',
                                    'Description',
                                    'Actor',
                                    'Method',
                                    'IP address',
                                    'Recorded',
                                ]}
                                data={props.auditEvents}
                            />
                        </TabsContent>
                    )}
                    {props.accessGovernance && (
                        <TabsContent
                            value="governance"
                            className="grid gap-4 lg:grid-cols-3"
                        >
                            {Object.entries(props.accessGovernance).map(
                                ([section, items]) => (
                                    <Card key={section}>
                                        <CardHeader>
                                            <CardTitle>
                                                {humanize(section)}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            {items.length ? (
                                                items.map((item) => (
                                                    <div
                                                        key={item.id}
                                                        className="rounded-lg border p-3 text-sm"
                                                    >
                                                        {Object.entries(item)
                                                            .filter(
                                                                ([key]) =>
                                                                    key !==
                                                                    'id',
                                                            )
                                                            .map(
                                                                ([
                                                                    key,
                                                                    value,
                                                                ]) => (
                                                                    <p
                                                                        key={
                                                                            key
                                                                        }
                                                                        className="break-words"
                                                                    >
                                                                        <span className="font-semibold">
                                                                            {humanize(
                                                                                key,
                                                                            )}
                                                                            :
                                                                        </span>{' '}
                                                                        {Array.isArray(
                                                                            value,
                                                                        )
                                                                            ? value.join(
                                                                                  ', ',
                                                                              )
                                                                            : String(
                                                                                  value ??
                                                                                      '—',
                                                                              )}
                                                                    </p>
                                                                ),
                                                            )}
                                                    </div>
                                                ))
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    No governed records.
                                                </p>
                                            )}
                                        </CardContent>
                                    </Card>
                                ),
                            )}
                        </TabsContent>
                    )}
                </Tabs>
            </div>
        </>
    );
}
