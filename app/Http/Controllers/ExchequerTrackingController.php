<?php

namespace App\Http\Controllers;

use App\Actions\CreateExchequerRequest;
use App\Actions\RecordExchequerEvent;
use App\Enums\ProgrammePermission;
use App\Http\Requests\RecordExchequerEventRequest;
use App\Http\Requests\StoreExchequerRequestRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\CountyGrant;
use App\Models\ExchequerEvent;
use App\Models\ExchequerRequest;
use App\Models\IntegrationExchange;
use App\Models\User;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\ProgrammeCountyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExchequerTrackingController extends Controller
{
    public function __construct(private ProgrammeCountyScope $countyScope, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    public function index(WorkspaceIndexRequest $request): Response
    {
        Gate::authorize(ProgrammePermission::ViewGrants->value);
        $user = $this->user($request);
        $release = $this->referenceDataReleaseResolver->availableForSelection(now());
        $governedCountyIds = collect($release?->snapshot['counties'] ?? [])->pluck('id')->filter()->all();
        if ($request->filled('county_id')) {
            abort_unless($this->countyScope->query($user)->whereKey($request->string('county_id'))->exists(), 403);
        }
        $countyIds = $this->countyScope->query($user)->select('id');
        $query = ExchequerRequest::query()->whereIn('county_id', $countyIds)
            ->with(['county:id,name,code,logo_path', 'referenceDataRelease:id,version,effective_from,checksum', 'grant:id,programme,financial_year,allocated_amount,disbursed_amount', 'creator:id,name', 'events.recorder:id,name', 'events.exchange:id,correlation_id,payload_checksum'])
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('county_id'), fn (Builder $query) => $query->where('county_id', $request->string('county_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('stage'), fn (Builder $query) => $query->where('current_stage', $request->string('stage')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(fn (Builder $query) => $query->where('request_reference', 'ilike', "%{$search}%")->orWhere('tranche_reference', 'ilike', "%{$search}%")->orWhereHas('grant', fn (Builder $grant) => $grant->where('programme', 'ilike', "%{$search}%")));
            });
        $summary = (clone $query)->toBase()->selectRaw("count(*) as total, count(*) filter (where status = 'completed') as completed, count(*) filter (where status = 'open' and stage_due_at < now()) as overdue, coalesce(sum(amount) filter (where status = 'completed'), 0) as credited_amount")->first();
        $averageMinutes = ExchequerEvent::query()->whereIn('exchequer_request_id', (clone $query)->select('id'))->where('event_type', 'cbk_credited')->avg('elapsed_total_minutes');
        $requests = $query->latest()->paginate($request->integer('per_page', 10))->withQueryString();

        return Inertia::render('exchequer/index', [
            'requests' => $requests->through(fn (ExchequerRequest $item): array => $this->payload($item)),
            'summary' => ['total' => (int) $summary->total, 'completed' => (int) $summary->completed, 'overdue' => (int) $summary->overdue, 'creditedAmount' => (float) $summary->credited_amount, 'averageTurnaroundHours' => $averageMinutes === null ? null : round((float) $averageMinutes / 60, 1)],
            'filters' => $request->safe()->only(['from', 'to', 'search', 'county_id', 'status', 'stage', 'per_page']),
            'capabilities' => ['create' => $user->can(ProgrammePermission::ManageGrants->value), 'recordEvents' => $user->can(ProgrammePermission::ManageIntegrations->value)],
            'options' => ['counties' => $this->countyScope->query($user)->whereIn('id', $governedCountyIds)->orderBy('name')->get()->map->identityCell()->values(), 'grants' => CountyGrant::query()->whereIn('county_id', $this->countyScope->query($user)->whereIn('id', $governedCountyIds)->select('id'))->with('county:id,name,code')->orderByDesc('financial_year')->get()->map(fn (CountyGrant $grant): array => ['id' => $grant->id, 'name' => "{$grant->county->name} · {$grant->programme} · {$grant->financial_year}", 'countyId' => $grant->county_id]), 'exchanges' => IntegrationExchange::query()->where('status', 'succeeded')->where(fn (Builder $query) => $query->whereNull('county_id')->orWhereIn('county_id', $this->countyScope->query($user)->select('id')))->with('contract.system:id,code')->latest('accepted_at')->limit(200)->get()->map(fn (IntegrationExchange $exchange): array => ['id' => $exchange->id, 'name' => "{$exchange->contract->system->code} · {$exchange->external_reference}"])],
            'catalogue' => $release === null ? ['available' => false] : ['available' => true, 'version' => $release->version, 'effectiveFrom' => $release->effective_from?->toDateString(), 'checksum' => $release->checksum],
        ]);
    }

    public function store(StoreExchequerRequestRequest $request, CreateExchequerRequest $action): RedirectResponse
    {
        $item = $action->handle($this->user($request), $request->validated());

        return back()->with('success', "Exchequer request {$item->request_reference} created.");
    }

    public function recordEvent(RecordExchequerEventRequest $request, string $currentTeam, ExchequerRequest $exchequerRequest, RecordExchequerEvent $action): RedirectResponse
    {
        $action->handle($exchequerRequest, $this->user($request), $request->validated());

        return back()->with('success', 'Exchequer lifecycle event recorded.');
    }

    /** @return array<string, mixed> */
    private function payload(ExchequerRequest $request): array
    {
        return ['id' => $request->id, 'reference' => $request->request_reference, 'referenceData' => $request->referenceDataRelease === null ? null : ['version' => $request->referenceDataRelease->version, 'effectiveFrom' => $request->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $request->referenceDataRelease->checksum], 'trancheReference' => $request->tranche_reference, 'county' => $request->county->identityCell(), 'grant' => "{$request->grant->programme} · {$request->financial_year}", 'amount' => (float) $request->amount, 'currency' => $request->currency, 'stage' => $request->current_stage, 'status' => $request->status, 'stageDueAt' => $request->stage_due_at?->toIso8601String(), 'overdue' => $request->status === 'open' && $request->stage_due_at?->isPast() === true, 'creator' => $request->creator->name, 'createdAt' => $request->created_at->toIso8601String(), 'creditedAt' => $request->credited_at?->toIso8601String(), 'events' => $request->events->map(fn (ExchequerEvent $event): array => ['id' => $event->id, 'type' => $event->event_type, 'source' => $event->source_system, 'sourceReference' => $event->source_event_reference, 'occurredAt' => $event->occurred_at->toIso8601String(), 'receivedAt' => $event->received_at->toIso8601String(), 'elapsedStageMinutes' => $event->elapsed_from_previous_minutes, 'elapsedTotalMinutes' => $event->elapsed_total_minutes, 'notes' => $event->notes, 'checksum' => $event->evidence_checksum, 'recorder' => $event->recorder->name, 'exchange' => $event->exchange ? ['id' => $event->exchange->id, 'correlationId' => $event->exchange->correlation_id, 'checksum' => $event->exchange->payload_checksum] : null])->values()];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
