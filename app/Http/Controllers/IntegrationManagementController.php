<?php

namespace App\Http\Controllers;

use App\Actions\ActivateIntegrationSystem;
use App\Actions\AttemptIntegrationExchangeDelivery;
use App\Actions\CreateIntegrationSystem;
use App\Actions\DispatchIntegrationExchange;
use App\Actions\PublishIntegrationContract;
use App\Actions\ResolveReconciliationException;
use App\Enums\ProgrammePermission;
use App\Http\Requests\ActivateIntegrationSystemRequest;
use App\Http\Requests\DispatchIntegrationExchangeRequest;
use App\Http\Requests\PublishIntegrationContractRequest;
use App\Http\Requests\ResolveReconciliationExceptionRequest;
use App\Http\Requests\RetryIntegrationExchangeRequest;
use App\Http\Requests\StoreIntegrationContractRequest;
use App\Http\Requests\StoreIntegrationSystemRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use App\Models\IntegrationSystem;
use App\Models\Organization;
use App\Models\ReconciliationException;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\ProgrammeCountyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationManagementController extends Controller
{
    public function __construct(private ProgrammeCountyScope $countyScope, private AuditLogger $auditLogger) {}

    public function index(WorkspaceIndexRequest $request, EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver): Response
    {
        Gate::authorize(ProgrammePermission::ViewIntegrations->value);
        $user = $this->user($request);
        $referenceDataRelease = $referenceDataReleaseResolver->availableForSelection(now());
        $governedOrganizationIds = collect($referenceDataRelease?->snapshot['organizations'] ?? [])->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->values()->all();
        $systems = IntegrationSystem::query()->with(['ownerOrganization:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'contracts' => fn ($query) => $query->with(['submitter:id,name', 'approver:id,name'])->latest('version')])->withCount(['contracts', 'reconciliationRuns'])->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))->when($request->filled('search'), function (Builder $query) use ($request): void {
            $search = $request->string('search')->trim()->toString();
            $query->where(fn (Builder $searchQuery) => $searchQuery->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")->orWhere('system_owner', 'ilike', "%{$search}%"));
        })->orderBy('code')->get();

        $exchanges = IntegrationExchange::query()->with(['contract:id,integration_system_id,name,version', 'contract.system:id,code,name', 'county:id,name,code,logo_path', 'creator:id,name', 'attempts'])->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $this->countyScope->query($user)->select('id')))->when($request->filled('from'), fn (Builder $query) => $query->whereDate('accepted_at', '>=', $request->date('from')))->when($request->filled('to'), fn (Builder $query) => $query->whereDate('accepted_at', '<=', $request->date('to')))->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))->when($request->filled('county_id'), fn (Builder $query) => $query->where('county_id', $request->string('county_id')))->when($request->filled('search'), function (Builder $query) use ($request): void {
            $search = $request->string('search')->trim()->toString();
            $query->where(fn (Builder $searchQuery) => $searchQuery->whereRaw('correlation_id::text ilike ?', ["%{$search}%"])->orWhere('external_reference', 'ilike', "%{$search}%")->orWhere('idempotency_key', 'ilike', "%{$search}%"));
        })->latest('accepted_at')->paginate($request->integer('per_page', 10))->withQueryString();

        $exceptions = ReconciliationException::query()->with(['run:id,reference,integration_system_id', 'run.system:id,code,name', 'county:id,name,code,logo_path', 'assignee:id,name', 'resolver:id,name'])->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $this->countyScope->query($user)->select('id')))->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))->latest()->paginate(10, ['*'], 'exception_page')->withQueryString();

        return Inertia::render('integrations/index', [
            'systems' => $systems->map(fn (IntegrationSystem $system): array => $this->systemPayload($system)),
            'exchanges' => $exchanges->through(fn (IntegrationExchange $exchange): array => ['id' => $exchange->id, 'system' => $exchange->contract->system->code, 'contract' => "{$exchange->contract->name} v{$exchange->contract->version}", 'county' => $exchange->county?->identityCell(), 'direction' => $exchange->direction, 'correlationId' => $exchange->correlation_id, 'externalReference' => $exchange->external_reference, 'idempotencyKey' => $exchange->idempotency_key, 'checksum' => $exchange->payload_checksum, 'status' => $exchange->status, 'httpStatus' => $exchange->http_status, 'attempts' => $exchange->attempt_count, 'nextAttemptAt' => $exchange->next_attempt_at?->toIso8601String(), 'errorCategory' => $exchange->error_category, 'errorDetail' => $exchange->error_detail, 'acceptedAt' => $exchange->accepted_at->toIso8601String(), 'completedAt' => $exchange->completed_at?->toIso8601String(), 'creator' => $exchange->creator?->name, 'attemptHistory' => $exchange->attempts->map(fn ($attempt): array => ['id' => $attempt->id, 'number' => $attempt->attempt_number, 'trigger' => $attempt->trigger_source, 'outcome' => $attempt->outcome, 'initiatedBy' => $attempt->initiated_by_name ?? 'OAuth client', 'httpStatus' => $attempt->http_status, 'retryable' => $attempt->retryable, 'retryAfterSeconds' => $attempt->retry_after_seconds, 'responseChecksum' => $attempt->response_checksum, 'errorCategory' => $attempt->error_category, 'errorDetail' => $attempt->error_detail, 'startedAt' => $attempt->started_at->toIso8601String(), 'completedAt' => $attempt->completed_at->toIso8601String(), 'durationMs' => $attempt->duration_ms])->values()->all()]),
            'exceptions' => $exceptions->through(fn (ReconciliationException $exception): array => ['id' => $exception->id, 'runReference' => $exception->run->reference, 'system' => $exception->run->system->code, 'county' => $exception->county?->identityCell(), 'externalReference' => $exception->external_reference, 'localReference' => $exception->local_reference, 'type' => $exception->exception_type, 'field' => $exception->field_name, 'severity' => $exception->severity, 'description' => $exception->description, 'status' => $exception->status, 'assignee' => $exception->assignee?->name, 'resolver' => $exception->resolver?->name, 'resolvedAt' => $exception->resolved_at?->toIso8601String()]),
            'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'county_id', 'per_page']),
            'capabilities' => ['manage' => $user->can(ProgrammePermission::ManageIntegrations->value), 'resolve' => $user->can(ProgrammePermission::ResolveIntegrationExceptions->value)],
            'catalogue' => ['available' => $referenceDataRelease !== null, 'version' => $referenceDataRelease?->version, 'effectiveFrom' => $referenceDataRelease?->effective_from?->toIso8601String()],
            'options' => ['organizations' => Organization::query()->where('status', 'active')->whereIn('id', $governedOrganizationIds)->orderBy('name')->get(['id', 'name']), 'counties' => $this->countyScope->query($user)->orderBy('name')->get()->map->identityCell()->values()],
        ]);
    }

    public function storeSystem(StoreIntegrationSystemRequest $request, CreateIntegrationSystem $createIntegrationSystem): RedirectResponse
    {
        $user = $this->user($request);
        $system = $createIntegrationSystem->handle($user, $request->validated());

        return back()->with('success', "Integration system {$system->code} registered.");
    }

    public function activate(ActivateIntegrationSystemRequest $request, string $currentTeam, IntegrationSystem $system, ActivateIntegrationSystem $action): RedirectResponse
    {
        $action->handle($system, $this->user($request), $request->validated());

        return back()->with('success', 'Production integration activation recorded.');
    }

    public function storeContract(StoreIntegrationContractRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $attributes = $request->validated();
        $version = IntegrationContract::query()->where('integration_system_id', $attributes['integration_system_id'])->max('version') + 1;
        $checksumSource = Arr::except($attributes, ['integration_system_id']);
        $contract = IntegrationContract::create([...$attributes, 'submitted_by' => $user->id, 'version' => $version, 'status' => 'review', 'content_checksum' => hash('sha256', json_encode($checksumSource, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
        $this->auditLogger->record($user, $contract, 'integration.contract.submitted', "Interface contract {$contract->name} v{$version} submitted for independent review.");

        return back()->with('success', "Interface contract v{$version} submitted for review.");
    }

    public function publish(PublishIntegrationContractRequest $request, string $currentTeam, IntegrationContract $contract, PublishIntegrationContract $action): RedirectResponse
    {
        $action->handle($contract, $this->user($request), $request->validated());

        return back()->with('success', 'Interface contract independently published.');
    }

    public function dispatch(DispatchIntegrationExchangeRequest $request, string $currentTeam, IntegrationContract $contract, DispatchIntegrationExchange $action): RedirectResponse
    {
        $exchange = $action->handle($contract, $this->user($request), $request->validated());

        return back()->with('success', "Exchange {$exchange->correlation_id} recorded as {$exchange->status}.");
    }

    public function retry(RetryIntegrationExchangeRequest $request, string $currentTeam, IntegrationExchange $exchange, AttemptIntegrationExchangeDelivery $action): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($exchange->county_id === null || $user->programmeRole()->hasNationalScope() || $this->countyScope->query($user)->whereKey($exchange->county_id)->exists(), 403);
        $exchange = $action->handle($exchange, $user, 'manual_retry');

        return back()->with('success', "Exchange {$exchange->correlation_id} retry completed as {$exchange->status}.");
    }

    public function resolve(ResolveReconciliationExceptionRequest $request, string $currentTeam, ReconciliationException $exception, ResolveReconciliationException $action): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($exception->county_id === null || $user->programmeRole()->hasNationalScope() || $this->countyScope->query($user)->whereKey($exception->county_id)->exists(), 403);
        $action->handle($exception, $user, $request->validated('resolution'));

        return back()->with('success', 'Reconciliation exception resolved with an auditable decision.');
    }

    /** @return array<string, mixed> */
    private function systemPayload(IntegrationSystem $system): array
    {
        return ['id' => $system->id, 'code' => $system->code, 'name' => $system->name, 'purpose' => $system->purpose, 'owner' => $system->system_owner, 'organization' => $system->ownerOrganization?->name, 'referenceData' => $system->referenceDataRelease ? ['version' => $system->referenceDataRelease->version, 'effectiveFrom' => $system->referenceDataRelease->effective_from?->toIso8601String(), 'checksum' => $system->referenceDataRelease->checksum] : null, 'environment' => $system->environment, 'transport' => $system->transport, 'authScheme' => $system->auth_scheme, 'credentialReference' => $system->credential_reference, 'baseUrl' => $system->base_url, 'direction' => $system->direction, 'classification' => $system->data_classification, 'status' => $system->status, 'health' => $system->health_status, 'productionApprovalReference' => $system->production_approval_reference, 'contractCount' => $system->contracts_count, 'reconciliationRunCount' => $system->reconciliation_runs_count, 'contracts' => $system->contracts->map(fn (IntegrationContract $contract): array => ['id' => $contract->id, 'version' => $contract->version, 'name' => $contract->name, 'resource' => $contract->resource_name, 'method' => $contract->http_method, 'path' => $contract->path, 'requestSchema' => $contract->request_schema, 'responseSchema' => $contract->response_schema, 'fieldMappings' => $contract->field_mappings, 'retryPolicy' => $contract->retry_policy, 'rateLimit' => $contract->rate_limit_per_minute, 'status' => $contract->status, 'checksum' => $contract->content_checksum, 'sourceOwnerApprovalReference' => $contract->source_owner_approval_reference, 'dataSharingAgreementReference' => $contract->data_sharing_agreement_reference, 'submitter' => $contract->submitter?->name, 'approver' => $contract->approver?->name, 'effectiveFrom' => $contract->effective_from?->toIso8601String(), 'effectiveTo' => $contract->effective_to?->toIso8601String()])->values()];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
