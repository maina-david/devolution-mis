<?php

namespace App\Http\Controllers;

use App\Actions\CreateTravelRequest;
use App\Actions\TransitionTravelRequest;
use App\Enums\ProgrammePermission;
use App\Http\Requests\StoreTravelRequestRequest;
use App\Http\Requests\TransitionTravelRequestRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\ProgrammeCountyScope;
use App\Services\TravelClearanceAnalytics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TravelClearanceController extends Controller
{
    public function index(WorkspaceIndexRequest $request, ProgrammeCountyScope $countyScope, TravelClearanceAnalytics $analytics): Response
    {
        Gate::authorize(ProgrammePermission::ViewTravelClearance->value);
        $user = $this->user($request);
        $visibleRequests = $this->filteredRequests($this->visibleRequests($user, $countyScope), $request);
        $query = $visibleRequests->clone()
            ->with(['requester:id,name,email', 'county:id,name,code,logo_path', 'organization:id,name', 'sector:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'itineraries', 'approvals.actor:id,name', 'documentLinks.document:id,title,original_name,mime_type,scan_status,source_type,record_status'])
            ->withCount('itineraries');
        $requests = $query->latest()->paginate($request->integer('per_page', 15))->withQueryString();

        return Inertia::render('travel-clearance/index', [
            'requests' => $requests->through(fn (TravelRequest $travelRequest): array => $this->payload($travelRequest)),
            'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'county_id', 'sector_id', 'per_page']),
            'capabilities' => [
                'submit' => $user->can(ProgrammePermission::SubmitTravelRequests->value),
                'approve' => $user->can(ProgrammePermission::ApproveTravelRequests->value),
                'financeClear' => $user->can(ProgrammePermission::FinanceClearTravel->value),
            ],
            'options' => [
                'counties' => $countyScope->query($user)->orderBy('code')->get(['id', 'name']),
                'organizations' => Organization::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
                'sectors' => Sector::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
            'analytics' => $analytics->summarize($visibleRequests),
        ]);
    }

    public function store(StoreTravelRequestRequest $request, CreateTravelRequest $createTravelRequest): RedirectResponse
    {
        $travelRequest = $createTravelRequest->handle($this->user($request), $request->validated());

        return back()->with('success', "Travel request {$travelRequest->reference} created.");
    }

    public function transition(TransitionTravelRequestRequest $request, string $currentTeam, TravelRequest $travelRequest, TransitionTravelRequest $transitionTravelRequest, ProgrammeCountyScope $countyScope): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->visibleRequests($user, $countyScope)->whereKey($travelRequest)->exists(), 403);
        $transition = $request->validated('transition');
        if (in_array($transition, ['submit', 'cancel'], true)) {
            abort_unless($travelRequest->requester_id === $user->id, 403);
        }
        $transitionTravelRequest->handle($travelRequest, $user, $request->validated());

        return back()->with('success', 'Travel clearance lifecycle updated.');
    }

    /** @return Builder<TravelRequest> */
    private function visibleRequests(User $user, ProgrammeCountyScope $countyScope): Builder
    {
        return TravelRequest::query()->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(function (Builder $query) use ($user, $countyScope): void {
            $query->where('requester_id', $user->id)->orWhereIn('county_id', $countyScope->query($user)->select('id'));
        }));
    }

    /**
     * @param  Builder<TravelRequest>  $requests
     * @return Builder<TravelRequest>
     */
    private function filteredRequests(Builder $requests, WorkspaceIndexRequest $request): Builder
    {
        return $requests->when($request->filled('from'), fn (Builder $query) => $query->whereDate('departure_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('return_date', '<=', $request->date('to')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('county_id'), fn (Builder $query) => $query->where('county_id', $request->string('county_id')))
            ->when($request->filled('sector_id'), fn (Builder $query) => $query->where('sector_id', $request->string('sector_id')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(fn (Builder $query) => $query->where('reference', 'ilike', "%{$search}%")->orWhere('purpose', 'ilike', "%{$search}%")->orWhere('destination_city', 'ilike', "%{$search}%")->orWhereHas('requester', fn (Builder $requester) => $requester->where('name', 'ilike', "%{$search}%")));
            });
    }

    /** @return array<string, mixed> */
    private function payload(TravelRequest $travelRequest): array
    {
        return [
            'id' => $travelRequest->id,
            'reference' => $travelRequest->reference,
            'requester' => $travelRequest->requester->name,
            'requesterId' => $travelRequest->requester_id,
            'county' => $travelRequest->county?->name,
            'countyIdentity' => $travelRequest->county?->identityCell(),
            'countyId' => $travelRequest->county_id,
            'organization' => $travelRequest->organization?->name,
            'sector' => $travelRequest->sector?->name,
            'travelType' => $travelRequest->travel_type,
            'purpose' => $travelRequest->purpose,
            'justification' => $travelRequest->justification,
            'destination' => "{$travelRequest->destination_city}, {$travelRequest->destination_country}",
            'departureDate' => $travelRequest->departure_date->toDateString(),
            'returnDate' => $travelRequest->return_date->toDateString(),
            'estimatedCost' => $travelRequest->estimated_cost,
            'currency' => $travelRequest->currency,
            'fundingSource' => $travelRequest->funding_source,
            'costCentre' => $travelRequest->cost_centre,
            'hrisReference' => $travelRequest->hris_employee_reference,
            'financeReference' => $travelRequest->finance_commitment_reference,
            'integrationStatus' => $travelRequest->integration_status,
            'referenceRelease' => $travelRequest->referenceDataRelease ? "v{$travelRequest->referenceDataRelease->version} · {$travelRequest->referenceDataRelease->effective_from?->toDateString()}" : 'Legacy unpinned',
            'referenceChecksum' => $travelRequest->referenceDataRelease?->checksum,
            'status' => $travelRequest->status,
            'priority' => $travelRequest->priority,
            'decisionDueAt' => $travelRequest->decision_due_at?->toIso8601String(),
            'itineraries' => $travelRequest->itineraries->map(fn ($itinerary): array => ['id' => $itinerary->id, 'origin' => $itinerary->origin, 'destination' => $itinerary->destination, 'departsAt' => $itinerary->departs_at->toIso8601String(), 'arrivesAt' => $itinerary->arrives_at->toIso8601String(), 'transportMode' => $itinerary->transport_mode, 'carrier' => $itinerary->carrier, 'estimatedCost' => $itinerary->estimated_cost])->values()->all(),
            'approvals' => $travelRequest->approvals->map(fn ($approval): array => ['id' => $approval->id, 'actor' => $approval->actor->name, 'stage' => $approval->stage, 'decision' => $approval->decision, 'rationale' => $approval->rationale, 'decidedAt' => $approval->decided_at->toIso8601String()])->values()->all(),
            'documents' => $travelRequest->documentLinks->filter(fn ($link): bool => $link->document->record_status === 'active')->map(fn ($link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'sourceType' => $link->document->source_type, 'scanStatus' => $link->document->scan_status])->values()->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
