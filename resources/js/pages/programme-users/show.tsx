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

function dateTime(
    value: string | null,
    locale: string,
    fallback: string,
): string {
    return value ? new Date(value).toLocaleString(locale) : fallback;
}

function localizedValue(value: string, copy: Record<string, string>): string {
    return copy[value] ?? copy[value.replaceAll('-', '_')] ?? humanize(value);
}

function governanceValue(
    key: string,
    value: unknown,
    locale: string,
    copy: Record<string, string>,
): string {
    if (Array.isArray(value)) {
        return value
            .map((item) =>
                typeof item === 'string'
                    ? localizedValue(item, copy)
                    : String(item),
            )
            .join(', ');
    }

    if (typeof value === 'string' && key.endsWith('At')) {
        return dateTime(value, locale, copy.not_recorded);
    }

    return typeof value === 'string'
        ? localizedValue(value, copy)
        : String(value ?? '—');
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
    const { profile, summary, capabilities } = props;
    const { localization } = usePage().props;
    const copy = localization.programmeUserProfile;
    const formatCount = (value: number): string =>
        value.toLocaleString(localization.current);
    const formatDateTime = (value: string | null): string =>
        dateTime(value, localization.current, copy.not_recorded);

    return (
        <>
            <Head title={`${profile.name} · ${copy.user_record}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <Button
                        asChild
                        variant="link"
                        className="mb-3 h-auto p-0 text-[#83d4ad]"
                    >
                        <Link href={usersIndex()}>
                            <ArrowLeft aria-hidden="true" /> {copy.user_access}
                        </Link>
                    </Button>
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div>
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                {copy.governed_identity_record}
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
                                {localizedValue(profile.status, copy)}
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
                        [copy.recorded_sessions, summary.sessionCount],
                        [copy.page_accesses, summary.pageViewCount],
                        [copy.audit_events, summary.auditEventCount],
                        [copy.last_seen, formatDateTime(summary.lastSeenAt)],
                    ].map(([label, value]) => (
                        <Card key={String(label)}>
                            <CardContent className="p-5">
                                <p className="text-sm text-muted-foreground">
                                    {label}
                                </p>
                                <p className="mt-2 text-2xl font-bold text-foreground">
                                    {typeof value === 'number'
                                        ? formatCount(value)
                                        : (value ?? copy.restricted)}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Tabs defaultValue="overview" className="gap-4">
                    <TabsList className="h-auto flex-wrap justify-start">
                        <TabsTrigger value="overview">
                            {copy.overview}
                        </TabsTrigger>
                        {capabilities.viewActivity && (
                            <TabsTrigger value="sessions">
                                {copy.sessions}
                            </TabsTrigger>
                        )}
                        {capabilities.viewActivity && (
                            <TabsTrigger value="pages">
                                {copy.page_history}
                            </TabsTrigger>
                        )}
                        {capabilities.viewAudit && (
                            <TabsTrigger value="audit">
                                {copy.audit_trail}
                            </TabsTrigger>
                        )}
                        {capabilities.viewAccessGovernance && (
                            <TabsTrigger value="governance">
                                {copy.access_governance}
                            </TabsTrigger>
                        )}
                    </TabsList>

                    <TabsContent value="overview">
                        <div className="grid gap-4 lg:grid-cols-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <UserRound aria-hidden="true" />{' '}
                                        {copy.identity}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <dl className="space-y-3">
                                        <Detail
                                            label={copy.email_verified}
                                            value={formatDateTime(
                                                profile.emailVerifiedAt,
                                            )}
                                        />
                                        <Detail
                                            label={copy.created}
                                            value={formatDateTime(
                                                profile.createdAt,
                                            )}
                                        />
                                        <Detail
                                            label={copy.updated}
                                            value={formatDateTime(
                                                profile.updatedAt,
                                            )}
                                        />
                                        <Detail
                                            label={copy.current_activity}
                                            value={
                                                summary.currentPage ??
                                                copy.offline
                                            }
                                        />
                                        {profile.accessRevokedAt && (
                                            <Detail
                                                label={copy.revocation}
                                                value={`${formatDateTime(profile.accessRevokedAt)} · ${profile.accessRevocationReason ?? copy.no_reason_recorded}`}
                                            />
                                        )}
                                    </dl>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <MapPin aria-hidden="true" />{' '}
                                        {copy.scope}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <Detail
                                        label={copy.home_county}
                                        value={
                                            profile.homeCounty ? (
                                                <CountyIdentity
                                                    county={profile.homeCounty}
                                                    compact
                                                />
                                            ) : (
                                                copy.national_portfolio
                                            )
                                        }
                                    />
                                    <Detail
                                        label={copy.assigned_counties}
                                        value={
                                            profile.assignedCounties.length
                                                ? profile.assignedCounties
                                                      .map(
                                                          (county) =>
                                                              county.name,
                                                      )
                                                      .join(', ')
                                                : copy.none
                                        }
                                    />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <KeyRound aria-hidden="true" />{' '}
                                        {copy.security}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <dl className="space-y-3">
                                        <Detail
                                            label={copy.role}
                                            value={profile.role.label}
                                        />
                                        <Detail
                                            label={
                                                copy.two_factor_authentication
                                            }
                                            value={
                                                profile.twoFactorEnabled
                                                    ? copy.enabled
                                                    : copy.not_enabled
                                            }
                                        />
                                        <Detail
                                            label={copy.registered_passkeys}
                                            value={formatCount(
                                                profile.passkeyCount,
                                            )}
                                        />
                                        <Detail
                                            label={copy.effective_permissions}
                                            value={copy.permission_count.replace(
                                                ':count',
                                                formatCount(
                                                    profile.permissions.length,
                                                ),
                                            )}
                                        />
                                    </dl>
                                </CardContent>
                            </Card>
                        </div>
                        <Card className="mt-4">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldCheck aria-hidden="true" />{' '}
                                    {copy.effective_permissions}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-2">
                                {profile.permissions.map((permission) => (
                                    <Badge key={permission} variant="outline">
                                        {localizedValue(permission, copy)}
                                    </Badge>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>
                    {props.sessions && (
                        <TabsContent value="sessions">
                            <DataSection
                                title={copy.authentication_sessions}
                                columns={[
                                    copy.current_page,
                                    copy.ip_address,
                                    copy.logged_in,
                                    copy.last_seen,
                                    copy.logged_out,
                                ]}
                                data={props.sessions}
                            />
                        </TabsContent>
                    )}
                    {props.pageViews && (
                        <TabsContent value="pages">
                            <DataSection
                                title={copy.authorized_page_history}
                                columns={[
                                    copy.page,
                                    copy.route,
                                    copy.path,
                                    copy.ip_address,
                                    copy.viewed,
                                ]}
                                data={props.pageViews}
                            />
                        </TabsContent>
                    )}
                    {props.auditEvents && (
                        <TabsContent value="audit">
                            <DataSection
                                title={copy.correlated_audit_trail}
                                columns={[
                                    copy.action,
                                    copy.description,
                                    copy.actor,
                                    copy.method,
                                    copy.ip_address,
                                    copy.recorded,
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
                                                {localizedValue(section, copy)}
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
                                                                            {localizedValue(
                                                                                key,
                                                                                copy,
                                                                            )}
                                                                            {
                                                                                copy.field_separator
                                                                            }
                                                                        </span>{' '}
                                                                        {governanceValue(
                                                                            key,
                                                                            value,
                                                                            localization.current,
                                                                            copy,
                                                                        )}
                                                                    </p>
                                                                ),
                                                            )}
                                                    </div>
                                                ))
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    {copy.no_governed_records}
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
