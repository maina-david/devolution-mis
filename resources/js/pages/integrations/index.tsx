import { Form, Head, usePage } from '@inertiajs/react';
import {
    Download,
    Eye,
    FileJson,
    MoreHorizontal,
    Play,
    Plus,
    RefreshCcw,
    ShieldCheck,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import type { CountyIdentityValue } from '@/components/county-identity';
import DatePickerField from '@/components/date-picker-field';
import DateRangeFilter from '@/components/date-range-filter';
import FormSheet from '@/components/form-sheet';
import SearchableSelect from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
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
import WorkspaceDataTable from '@/components/workspace-data-table';
import type {
    WorkspacePagination,
    WorkspaceRow,
} from '@/components/workspace-data-table';
import WorkspaceEmptyState from '@/components/workspace-empty-state';
import { DEFAULT_LOCALE } from '@/lib/reference-catalog';
import {
    publish,
    store as storeContract,
} from '@/routes/integrations/contracts';
import { resolve } from '@/routes/integrations/exceptions';
import { dispatch, retry } from '@/routes/integrations/exchanges';
import { activate, store as storeSystem } from '@/routes/integrations/systems';
import { exportMethod } from '@/routes/workspace';

type Option = { id: string; name: string };
type Contract = {
    id: string;
    version: number;
    name: string;
    resource: string;
    method: string;
    path: string;
    requestSchema: Record<string, unknown>;
    responseSchema: Record<string, unknown> | null;
    fieldMappings: Record<string, unknown> | null;
    retryPolicy: Record<string, unknown>;
    rateLimit: number;
    status: string;
    checksum: string;
    sourceOwnerApprovalReference: string | null;
    dataSharingAgreementReference: string | null;
    submitter: string | null;
    approver: string | null;
    effectiveFrom: string | null;
    effectiveTo: string | null;
};
type System = {
    id: string;
    code: string;
    name: string;
    purpose: string;
    owner: string;
    organization: string | null;
    referenceData: null | {
        version: number;
        effectiveFrom: string | null;
        checksum: string;
    };
    environment: string;
    transport: string;
    authScheme: string;
    credentialReference: string | null;
    baseUrl: string | null;
    direction: string;
    classification: string;
    status: string;
    health: string;
    productionApprovalReference: string | null;
    contractCount: number;
    reconciliationRunCount: number;
    contracts: Contract[];
};
type Exchange = {
    id: string;
    system: string;
    contract: string;
    county: CountyIdentityValue | null;
    direction: string;
    correlationId: string;
    externalReference: string | null;
    idempotencyKey: string;
    checksum: string;
    status: string;
    httpStatus: number | null;
    attempts: number;
    nextAttemptAt: string | null;
    errorCategory: string | null;
    errorDetail: string | null;
    acceptedAt: string;
    completedAt: string | null;
    creator: string | null;
    attemptHistory: ExchangeAttempt[];
};
type ExchangeAttempt = {
    id: string;
    number: number;
    trigger: string;
    outcome: string;
    initiatedBy: string;
    httpStatus: number | null;
    retryable: boolean;
    retryAfterSeconds: number | null;
    responseChecksum: string | null;
    errorCategory: string | null;
    errorDetail: string | null;
    startedAt: string;
    completedAt: string;
    durationMs: number;
};
type Exception = {
    id: string;
    runReference: string;
    system: string;
    county: CountyIdentityValue | null;
    externalReference: string | null;
    localReference: string | null;
    type: string;
    field: string | null;
    severity: string;
    description: string;
    status: string;
    assignee: string | null;
    resolver: string | null;
    resolvedAt: string | null;
};
type PageSet<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    systems: System[];
    exchanges: PageSet<Exchange>;
    exceptions: PageSet<Exception>;
    filters: Record<string, string | undefined>;
    capabilities: { manage: boolean; resolve: boolean };
    catalogue: {
        available: boolean;
        version: number | null;
        effectiveFrom: string | null;
    };
    options: { organizations: Option[]; counties: CountyIdentityValue[] };
};

export default function IntegrationManagement({
    systems,
    exchanges,
    exceptions,
    filters,
    capabilities,
    catalogue,
    options,
}: Props) {
    const copy = useIntegrationCopy();
    const exchangeRows: WorkspaceRow[] = exchanges.data.map((exchange) => ({
        id: exchange.id,
        status: exchange.status,
        cells: [
            exchange.system,
            exchange.contract,
            exchange.county ?? 'National',
            exchange.externalReference ?? '—',
            exchange.correlationId,
            exchange.checksum.slice(0, 12),
            exchange.attempts,
            exchange.httpStatus ?? '—',
            humanize(exchange.status),
        ],
    }));
    const exceptionRows: WorkspaceRow[] = exceptions.data.map((exception) => ({
        id: exception.id,
        status: exception.status,
        cells: [
            exception.runReference,
            exception.system,
            exception.county ?? 'National',
            humanize(exception.type),
            exception.field ?? '—',
            humanize(exception.severity),
            exception.description,
            humanize(exception.status),
        ],
    }));

    return (
        <>
            <Head title={copy.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <section className="authenticated-page-header">
                    <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                        <div className="max-w-3xl">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#83d4ad] uppercase">
                                {copy.eyebrow}
                            </p>
                            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                {copy.title}
                            </h1>
                            <p className="mt-3 max-w-2xl text-[#c7d6dd]">
                                {copy.description}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <IntegrationSystemExport filters={filters} />
                            {capabilities.manage && (
                                <>
                                    <ContractForm systems={systems} />
                                    <SystemForm
                                        organizations={options.organizations}
                                        catalogue={catalogue}
                                    />
                                </>
                            )}
                        </div>
                    </div>
                </section>
                <DateRangeFilter
                    initialFrom={filters.from}
                    initialTo={filters.to}
                    initialSearch={filters.search}
                    selectFilters={[
                        {
                            key: 'status',
                            label: 'Operational status',
                            options: [
                                'design',
                                'contract_review',
                                'published',
                                'active',
                                'succeeded',
                                'failed',
                                'retry_scheduled',
                                'dead_lettered',
                                'open',
                                'resolved',
                            ].map(option),
                            value: filters.status,
                        },
                        {
                            key: 'county_id',
                            label: 'County',
                            options: options.counties,
                            value: filters.county_id,
                        },
                    ]}
                />
                <section className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    {systems.map((system) => (
                        <SystemCard
                            key={system.id}
                            system={system}
                            capabilities={capabilities}
                            counties={options.counties}
                        />
                    ))}
                    {systems.length === 0 && (
                        <WorkspaceEmptyState
                            title={copy.no_integration_systems_registered}
                            description="Register the first source system and submit a versioned interface contract for independent approval."
                            className="min-h-56 lg:col-span-2 xl:col-span-3"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <RegisterHeader
                        title={copy.exchange_register}
                        description={`${exchanges.total.toLocaleString()} payload-safe exchange records`}
                        filters={filters}
                    />
                    {exchangeRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'System',
                                'Contract',
                                'County',
                                'External reference',
                                'Correlation ID',
                                'Checksum',
                                'Attempts',
                                'HTTP',
                                'Status',
                            ]}
                            rows={exchangeRows}
                            pagination={page(exchanges)}
                            bulkExport={{
                                workspace: 'integrations',
                                filters,
                            }}
                            renderActionControl={(row) => {
                                const exchange = exchanges.data.find(
                                    (entry) => entry.id === row.id,
                                );

                                return exchange ? (
                                    <ExchangeAction
                                        exchange={exchange}
                                        canManage={capabilities.manage}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_matching_exchanges}
                            description="Dispatch a schema-valid sandbox payload or adjust the filters."
                            className="min-h-64 border-0"
                        />
                    )}
                </section>
                <section className="overflow-hidden rounded-xl border bg-card">
                    <div className="flex items-center justify-between border-b px-5 py-4 sm:px-6">
                        <div>
                            <h2 className="font-bold">
                                {copy.reconciliation_exceptions}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {exceptions.total.toLocaleString()}{' '}
                                {copy.discrepancies_authorized_portfolio}
                            </p>
                        </div>
                        <TriangleAlert className="size-5 text-amber-600" />
                    </div>
                    {exceptionRows.length ? (
                        <WorkspaceDataTable
                            columns={[
                                'Run',
                                'System',
                                'County',
                                'Type',
                                'Field',
                                'Severity',
                                'Description',
                                'Status',
                            ]}
                            rows={exceptionRows}
                            pagination={{
                                ...page(exceptions),
                                pageName: 'exception_page',
                            }}
                            renderActionControl={(row) => {
                                const exception = exceptions.data.find(
                                    (entry) => entry.id === row.id,
                                );

                                return exception ? (
                                    <ExceptionAction
                                        exception={exception}
                                        canResolve={capabilities.resolve}
                                    />
                                ) : null;
                            }}
                        />
                    ) : (
                        <WorkspaceEmptyState
                            title={copy.no_reconciliation_exceptions}
                            description="No discrepancies match the current scope and filters."
                            className="min-h-56 border-0"
                        />
                    )}
                </section>
            </div>
        </>
    );
}

function SystemCard({
    system,
    capabilities,
    counties,
}: {
    system: System;
    capabilities: Props['capabilities'];
    counties: CountyIdentityValue[];
}) {
    const copy = useIntegrationCopy();
    const [surface, setSurface] = useState<string | null>(null);
    const published = system.contracts.find(
        (contract) => contract.status === 'published',
    );

    return (
        <>
            <Card>
                <CardHeader className="flex-row items-start justify-between">
                    <div>
                        <CardTitle>
                            {system.code} {copy.separator} {system.name}
                        </CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {system.owner}
                        </p>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label={`Actions for ${system.name}`}
                            >
                                <MoreHorizontal />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onSelect={() => setSurface('details')}
                            >
                                <Eye /> {copy.view_contracts}
                            </DropdownMenuItem>
                            {capabilities.manage && published && (
                                <DropdownMenuItem
                                    onSelect={() => setSurface('dispatch')}
                                >
                                    <Play /> {copy.dispatch_sandbox_exchange}
                                </DropdownMenuItem>
                            )}
                            {capabilities.manage &&
                                system.environment === 'production' &&
                                !system.productionApprovalReference && (
                                    <DropdownMenuItem
                                        onSelect={() => setSurface('activate')}
                                    >
                                        <ShieldCheck />{' '}
                                        {copy.record_activation_approval}
                                    </DropdownMenuItem>
                                )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <p className="line-clamp-3 text-sm leading-6 text-muted-foreground">
                        {system.purpose}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Badge>{humanize(system.status)}</Badge>
                        <Badge variant="outline">
                            {humanize(system.environment)}
                        </Badge>
                        <Badge variant="outline">
                            {humanize(system.transport)}
                        </Badge>
                        <Badge variant="outline">
                            {system.contractCount} {copy.contracts}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
            <Sheet
                open={surface !== null}
                onOpenChange={(open) => !open && setSurface(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-4xl">
                    <SheetHeader>
                        <SheetTitle>
                            {surface === 'details'
                                ? system.name
                                : humanize(surface ?? '')}
                        </SheetTitle>
                        <SheetDescription>
                            {system.code} {copy.separator} {system.environment}{' '}
                            {copy.separator} {system.direction}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-5 px-4 pt-4 pb-8">
                        {surface === 'details' ? (
                            <SystemDetails
                                system={system}
                                canManage={capabilities.manage}
                            />
                        ) : surface === 'dispatch' && published ? (
                            <DispatchForm
                                contract={published}
                                counties={counties}
                            />
                        ) : surface === 'activate' ? (
                            <ActivationForm system={system} />
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function SystemDetails({
    system,
    canManage,
}: {
    system: System;
    canManage: boolean;
}) {
    const copy = useIntegrationCopy();

    return (
        <>
            <div className="grid gap-3 rounded-xl border p-4 text-sm md:grid-cols-2">
                <Detail label={copy.owner} value={system.owner} />
                <Detail
                    label={copy.owner_organization}
                    value={system.organization ?? 'Not assigned'}
                />
                <Detail
                    label={copy.reference_catalogue}
                    value={
                        system.referenceData
                            ? `v${system.referenceData.version} · ${system.referenceData.checksum}`
                            : 'Legacy · unpinned'
                    }
                />
                <Detail
                    label={copy.classification}
                    value={humanize(system.classification)}
                />
                <Detail
                    label={copy.authentication}
                    value={humanize(system.authScheme)}
                />
                <Detail label={copy.health} value={humanize(system.health)} />
                <Detail
                    label={copy.endpoint}
                    value={system.baseUrl ?? 'Not configured'}
                />
                <Detail
                    label={copy.credential_reference}
                    value={system.credentialReference ?? 'Not configured'}
                />
            </div>
            {system.contracts.map((contract) => (
                <Card key={contract.id}>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-3">
                            <CardTitle className="text-base">
                                {copy.version_prefix}
                                {contract.version} {copy.separator}{' '}
                                {contract.name}
                            </CardTitle>
                            <Badge>{humanize(contract.status)}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm">
                        <p>
                            <strong>{contract.method}</strong> {contract.path}
                        </p>
                        <p>
                            {copy.resource_label} {contract.resource}{' '}
                            {copy.separator} {copy.rate_limit_label}{' '}
                            {contract.rateLimit}
                            {copy.per_minute}
                        </p>
                        <p className="font-mono text-xs break-all text-muted-foreground">
                            {copy.sha256} {contract.checksum}
                        </p>
                        <pre className="overflow-x-auto rounded-lg bg-muted p-3 text-xs">
                            {JSON.stringify(contract.requestSchema, null, 2)}
                        </pre>
                        <p className="text-muted-foreground">
                            {copy.submitted_by} {contract.submitter ?? '—'}{' '}
                            {copy.separator} {copy.approved_by}{' '}
                            {contract.approver ?? 'Pending'}
                        </p>
                        {canManage && contract.status === 'review' && (
                            <PublishContractForm
                                system={system}
                                contract={contract}
                            />
                        )}
                    </CardContent>
                </Card>
            ))}
        </>
    );
}

function PublishContractForm({
    system,
    contract,
}: {
    system: System;
    contract: Contract;
}) {
    const copy = useIntegrationCopy();

    return (
        <FormSheet
            title={`Publish ${contract.name} v${contract.version}`}
            description="Independently approve the immutable schema contract. Production interfaces require source-owner and data-sharing references."
            triggerLabel="Review and publish"
            icon={ShieldCheck}
        >
            <Form
                action={publish({ contract: contract.id })}
                className="grid gap-4 pt-4"
            >
                <Field
                    name="source_owner_approval_reference"
                    label={copy.source_owner_approval_reference}
                    optional={system.environment !== 'production'}
                />
                <Field
                    name="data_sharing_agreement_reference"
                    label={copy.data_sharing_agreement_reference}
                    optional={system.environment !== 'production'}
                />
                <DatePickerField
                    name="effective_from"
                    label={copy.effective_from}
                    includeTime
                    required
                />
                <DatePickerField
                    name="effective_to"
                    label={copy.effective_until}
                    includeTime
                />
                <Button type="submit">
                    <ShieldCheck /> {copy.publish_contract}
                </Button>
            </Form>
        </FormSheet>
    );
}

function SystemForm({
    organizations,
    catalogue,
}: {
    organizations: Option[];
    catalogue: Props['catalogue'];
}) {
    const copy = useIntegrationCopy();

    return (
        <FormSheet
            title={copy.register_integration_system}
            description="Register endpoint metadata and a vault reference only. Never store credentials in this form."
            triggerLabel="New system"
            icon={Plus}
            size="xl"
            triggerDisabled={!catalogue.available}
            triggerTitle={
                catalogue.available
                    ? `Using governed catalogue release v${catalogue.version}`
                    : 'No checksum-valid published reference catalogue is currently effective.'
            }
        >
            <Form action={storeSystem()} className="grid gap-5 pt-4">
                {({ processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field name="code" label={copy.system_code} />
                            <Field name="name" label={copy.system_name} />
                            <Field
                                name="system_owner"
                                label={copy.authoritative_system_owner}
                            />
                            <SearchableSelect
                                id="integration-organization"
                                name="owner_organization_id"
                                label={copy.owner_organization}
                                options={organizations}
                                optional
                            />
                            <SearchableSelect
                                id="integration-environment"
                                name="environment"
                                label={copy.environment}
                                options={['sandbox', 'test', 'production'].map(
                                    option,
                                )}
                                defaultValue="sandbox"
                            />
                            <SearchableSelect
                                id="integration-transport"
                                name="transport"
                                label={copy.transport}
                                options={['fixture', 'https_json', 'sftp'].map(
                                    option,
                                )}
                                defaultValue="fixture"
                            />
                            <SearchableSelect
                                id="integration-auth"
                                name="auth_scheme"
                                label={copy.authentication}
                                options={[
                                    'none',
                                    'oauth2_client_credentials',
                                    'mtls',
                                    'bearer_vault',
                                    'sftp_key_vault',
                                ].map(option)}
                                defaultValue="none"
                            />
                            <SearchableSelect
                                id="integration-direction"
                                name="direction"
                                label={copy.direction}
                                options={[
                                    'inbound',
                                    'outbound',
                                    'bidirectional',
                                ].map(option)}
                                defaultValue="inbound"
                            />
                            <SearchableSelect
                                id="integration-classification"
                                name="data_classification"
                                label={copy.data_classification}
                                options={[
                                    'public',
                                    'official',
                                    'confidential',
                                    'restricted',
                                ].map(option)}
                                defaultValue="official"
                            />
                            <SearchableSelect
                                id="integration-status"
                                name="status"
                                label={copy.lifecycle_status}
                                options={[
                                    'design',
                                    'contract_review',
                                    'approved',
                                    'active',
                                    'suspended',
                                ].map(option)}
                                defaultValue="design"
                            />
                            <Field
                                name="base_url"
                                label={copy.base_url}
                                optional
                            />
                            <Field
                                name="credential_reference"
                                label={copy.credential_vault_reference}
                                optional
                            />
                        </div>
                        <TextField
                            name="purpose"
                            label={copy.purpose_and_authorized_data_use}
                        />
                        <Button type="submit" disabled={processing}>
                            {copy.register_system}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function IntegrationSystemExport({ filters }: { filters: Props['filters'] }) {
    const copy = useIntegrationCopy();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    <Download /> {copy.export_systems}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a
                                href={exportMethod.url(
                                    {
                                        workspace: 'integration-systems',
                                        format,
                                    },
                                    { query: filters },
                                )}
                            >
                                {format.toUpperCase()}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function ContractForm({ systems }: { systems: System[] }) {
    const copy = useIntegrationCopy();

    return (
        <FormSheet
            title={copy.submit_interface_contract}
            description="Define the schema, mappings, idempotency and retry policy for independent publication."
            triggerLabel="New contract"
            icon={FileJson}
            size="xl"
        >
            <Form action={storeContract()} className="grid gap-5 pt-4">
                {({ processing }) => (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <SearchableSelect
                                id="contract-system"
                                name="integration_system_id"
                                label={copy.integration_system}
                                options={systems.map((system) => ({
                                    id: system.id,
                                    name: `${system.code} · ${system.name}`,
                                }))}
                            />
                            <Field name="name" label={copy.contract_name} />
                            <Field
                                name="resource_name"
                                label={copy.canonical_resource}
                            />
                            <SearchableSelect
                                id="contract-method"
                                name="http_method"
                                label={copy.http_method}
                                options={['GET', 'POST', 'PUT', 'PATCH'].map(
                                    (id) => ({ id, name: id }),
                                )}
                                defaultValue="POST"
                            />
                            <Field
                                name="path"
                                label={copy.resource_path}
                                defaultValue="/v1/resource"
                            />
                            <Field
                                name="idempotency_field"
                                label={copy.idempotency_field}
                                optional
                            />
                            <Field
                                name="rate_limit_per_minute"
                                label={copy.rate_limit_per_minute}
                                type="number"
                                defaultValue="60"
                            />
                        </div>
                        <JsonField
                            name="request_schema"
                            label={copy.request_json_schema}
                            defaultValue={
                                '{"type":"object","required":["reference"],"properties":{"reference":{"type":"string"}}}'
                            }
                        />
                        <JsonField
                            name="response_schema"
                            label={copy.response_json_schema}
                            defaultValue={
                                '{"type":"object","required":["accepted"],"properties":{"accepted":{"type":"boolean"}}}'
                            }
                        />
                        <JsonField
                            name="field_mappings"
                            label={copy.canonical_field_mappings}
                            defaultValue="{}"
                        />
                        <JsonField
                            name="required_headers"
                            label={copy.required_headers}
                            defaultValue={
                                '["X-Correlation-ID","Idempotency-Key"]'
                            }
                        />
                        <JsonField
                            name="retry_policy"
                            label={copy.retry_policy}
                            defaultValue={
                                '{"max_attempts":3,"backoff_seconds":[60,300,1800]}'
                            }
                        />
                        <Button type="submit" disabled={processing}>
                            {copy.submit_independent_review}
                        </Button>
                    </>
                )}
            </Form>
        </FormSheet>
    );
}

function DispatchForm({
    contract,
    counties,
}: {
    contract: Contract;
    counties: CountyIdentityValue[];
}) {
    const copy = useIntegrationCopy();

    return (
        <Form
            action={dispatch({ contract: contract.id })}
            className="grid gap-4"
        >
            <p className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
                {copy.production_delivery_gate}
            </p>
            <Field name="idempotency_key" label={copy.idempotency_key} />
            <Field
                name="external_reference"
                label={copy.external_reference}
                optional
            />
            <SearchableSelect
                id="exchange-county"
                name="county_id"
                label={copy.county_scope}
                options={counties}
                optional
            />
            <DatePickerField
                name="source_occurred_at"
                label={copy.source_event_time}
                includeTime
            />
            <JsonField
                name="payload"
                label={copy.contract_valid_json_payload}
                defaultValue="{}"
            />
            <Button type="submit">
                <Play /> {copy.validate_dispatch}
            </Button>
        </Form>
    );
}

function ActivationForm({ system }: { system: System }) {
    const copy = useIntegrationCopy();

    return (
        <Form action={activate({ system: system.id })} className="grid gap-4">
            <p className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm">
                {copy.activation_evidence_gate}
            </p>
            <Field
                name="production_approval_reference"
                label={copy.source_owner_activation_reference}
            />
            <DatePickerField
                name="production_approved_at"
                label={copy.approval_date_and_time}
                includeTime
                required
            />
            <Button type="submit">
                <ShieldCheck /> {copy.record_controlled_activation}
            </Button>
        </Form>
    );
}

function ExchangeAction({
    exchange,
    canManage,
}: {
    exchange: Exchange;
    canManage: boolean;
}) {
    const [open, setOpen] = useState(false);
    const copy = useIntegrationCopy();
    const canRetry =
        canManage &&
        exchange.direction === 'outbound' &&
        ['retry_scheduled', 'dead_lettered'].includes(exchange.status);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for exchange ${exchange.correlationId}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => setOpen(true)}>
                            <Eye /> {copy.view_attempt_history}
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-4xl">
                    <SheetHeader>
                        <SheetTitle>{copy.exchange_outcome}</SheetTitle>
                        <SheetDescription>
                            {exchange.system} {copy.separator}{' '}
                            {exchange.correlationId}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        <Detail
                            label={copy.contract}
                            value={exchange.contract}
                        />
                        <Detail
                            label={copy.idempotency_key}
                            value={exchange.idempotencyKey}
                        />
                        <Detail
                            label={copy.payload_checksum}
                            value={exchange.checksum}
                        />
                        <Detail
                            label={copy.status}
                            value={humanize(exchange.status)}
                        />
                        <Detail
                            label={copy.next_attempt}
                            value={
                                exchange.nextAttemptAt
                                    ? new Date(
                                          exchange.nextAttemptAt,
                                      ).toLocaleString(DEFAULT_LOCALE)
                                    : 'Not scheduled'
                            }
                        />
                        <Detail
                            label={copy.accepted}
                            value={new Date(exchange.acceptedAt).toLocaleString(
                                DEFAULT_LOCALE,
                            )}
                        />
                        {exchange.errorDetail && (
                            <Detail
                                label={humanize(
                                    exchange.errorCategory ?? 'error',
                                )}
                                value={exchange.errorDetail}
                            />
                        )}
                        {exchange.attemptHistory.length ? (
                            <div className="overflow-hidden rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>
                                                {copy.attempt}
                                            </TableHead>
                                            <TableHead>
                                                {copy.trigger}
                                            </TableHead>
                                            <TableHead>
                                                {copy.outcome}
                                            </TableHead>
                                            <TableHead>
                                                {copy.initiated_by}
                                            </TableHead>
                                            <TableHead>{copy.http}</TableHead>
                                            <TableHead>
                                                {copy.duration}
                                            </TableHead>
                                            <TableHead>
                                                {copy.evidence}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {exchange.attemptHistory.map(
                                            (attempt) => (
                                                <TableRow key={attempt.id}>
                                                    <TableCell>
                                                        {attempt.number}
                                                    </TableCell>
                                                    <TableCell>
                                                        {humanize(
                                                            attempt.trigger,
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline">
                                                            {humanize(
                                                                attempt.outcome,
                                                            )}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        {attempt.initiatedBy}
                                                    </TableCell>
                                                    <TableCell>
                                                        {attempt.httpStatus ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {attempt.durationMs}{' '}
                                                        {copy.milliseconds}
                                                    </TableCell>
                                                    <TableCell className="max-w-56">
                                                        <p className="truncate text-xs">
                                                            {attempt.errorDetail ??
                                                                attempt.responseChecksum ??
                                                                'No error'}
                                                        </p>
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <WorkspaceEmptyState
                                title={copy.no_retained_attempts}
                                description="This exchange predates the immutable attempt ledger."
                                className="min-h-40"
                            />
                        )}
                        {canRetry && (
                            <Form {...retry.form({ exchange: exchange.id })}>
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        <RefreshCcw /> {copy.retry_exchange_now}
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function ExceptionAction({
    exception,
    canResolve,
}: {
    exception: Exception;
    canResolve: boolean;
}) {
    const [open, setOpen] = useState(false);
    const copy = useIntegrationCopy();

    return (
        <>
            <Button
                variant="ghost"
                size="icon"
                onClick={() => setOpen(true)}
                aria-label={`View reconciliation exception ${exception.id}`}
            >
                <MoreHorizontal />
            </Button>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>{copy.reconciliation_exception}</SheetTitle>
                        <SheetDescription>
                            {exception.runReference} {copy.separator}{' '}
                            {exception.system}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4 pt-4 pb-8">
                        <Detail
                            label={copy.description_label}
                            value={exception.description}
                        />
                        <Detail
                            label={copy.references}
                            value={`${exception.externalReference ?? '—'} / ${exception.localReference ?? '—'}`}
                        />
                        {canResolve && exception.status === 'open' && (
                            <Form
                                action={resolve({ exception: exception.id })}
                                className="grid gap-4"
                            >
                                <TextField
                                    name="resolution"
                                    label={
                                        copy.verified_resolution_and_evidence
                                    }
                                />
                                <Button type="submit">
                                    <RefreshCcw /> {copy.resolve_exception}
                                </Button>
                            </Form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

function RegisterHeader({
    title,
    description,
    filters,
}: {
    title: string;
    description: string;
    filters: Props['filters'];
}) {
    const copy = useIntegrationCopy();

    return (
        <div className="flex items-center justify-between border-b px-5 py-4 sm:px-6">
            <div>
                <h2 className="font-bold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <Download /> {copy.export}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {['csv', 'xlsx', 'json', 'pdf'].map((format) => (
                        <DropdownMenuItem key={format} asChild>
                            <a
                                href={exportMethod.url(
                                    { workspace: 'integrations', format },
                                    { query: filters },
                                )}
                            >
                                {format.toUpperCase()}
                            </a>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
function Field({
    name,
    label,
    type = 'text',
    defaultValue,
    optional = false,
}: {
    name: string;
    label: string;
    type?: 'text' | 'number';
    defaultValue?: string;
    optional?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={defaultValue}
                required={!optional}
            />
        </div>
    );
}
function TextField({ name, label }: { name: string; label: string }) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Textarea id={name} name={name} rows={4} required />
        </div>
    );
}
function JsonField({
    name,
    label,
    defaultValue,
}: {
    name: string;
    label: string;
    defaultValue: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Textarea
                id={name}
                name={name}
                rows={5}
                defaultValue={defaultValue}
                required
                className="font-mono text-xs"
                spellCheck={false}
            />
        </div>
    );
}
function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 text-sm break-words">{value}</p>
        </div>
    );
}
function option(id: string) {
    return { id, name: humanize(id) };
}
function humanize(value: string) {
    return value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}

function useIntegrationCopy(): Record<string, string> {
    return usePage().props.localization.integrationManagement;
}
function page<T>(records: PageSet<T>): WorkspacePagination {
    return {
        currentPage: records.current_page,
        lastPage: records.last_page,
        perPage: records.per_page,
        total: records.total,
    };
}
