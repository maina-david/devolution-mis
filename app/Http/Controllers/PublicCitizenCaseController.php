<?php

namespace App\Http\Controllers;

use App\Actions\CreateCitizenCase;
use App\Http\Requests\RateCitizenCaseRequest;
use App\Http\Requests\StorePublicCitizenCaseRequest;
use App\Http\Requests\TrackCitizenCaseRequest;
use App\Models\CitizenCase;
use App\Models\CitizenCaseMessage;
use App\Models\County;
use App\Models\Sector;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicCitizenCaseController extends Controller
{
    public function index(EffectiveReferenceDataReleaseResolver $referenceDataResolver): Response
    {
        $release = $referenceDataResolver->availableForCitizenIntake(now());
        $countyIds = $this->snapshotIds($release?->snapshot['counties'] ?? []);
        $sectorIds = $this->snapshotIds($release?->snapshot['sectors'] ?? []);

        return Inertia::render('citizen-engagement/index', [
            'counties' => County::query()->whereIn('id', $countyIds)->orderBy('code')->get()->map->identityCell()->values(),
            'sectors' => Sector::query()->whereIn('id', $sectorIds)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'catalogue' => ['available' => $release !== null, 'version' => $release?->version, 'effectiveFrom' => $release?->effective_from?->toIso8601String()],
            'dashboard' => $this->dashboardData(),
        ]);
    }

    public function store(StorePublicCitizenCaseRequest $request, CreateCitizenCase $createCase): RedirectResponse
    {
        $result = $createCase->handle($request->validated(), $request->file('attachment'));

        return redirect()->route('citizen-engagement.receipt')->with('case_receipt', ['reference' => $result['case']->reference, 'trackingToken' => $result['tracking_token']]);
    }

    public function receipt(Request $request): Response|RedirectResponse
    {
        $receipt = $request->session()->get('case_receipt');
        if (! is_array($receipt)) {
            return redirect()->route('citizen-engagement.index');
        }

        return Inertia::render('citizen-engagement/receipt', ['receipt' => $receipt]);
    }

    public function track(TrackCitizenCaseRequest $request): RedirectResponse
    {
        $case = CitizenCase::query()->where('reference', $request->validated('reference'))->where('tracking_token_hash', hash('sha256', $request->validated('tracking_token')))->first();
        if (! $case) {
            return back()->withErrors(['reference' => __('citizen.tracking_mismatch')]);
        }
        $request->session()->put('tracked_case_id', $case->id);

        return redirect()->route('citizen-engagement.tracking');
    }

    public function tracking(Request $request): Response|RedirectResponse
    {
        $caseId = $request->session()->get('tracked_case_id');
        if (! is_string($caseId)) {
            return redirect()->route('citizen-engagement.index');
        }
        $case = CitizenCase::query()->with(['county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'messages' => fn ($query) => $query->where('visibility', 'public')->oldest('posted_at')])->findOrFail($caseId);

        return Inertia::render('citizen-engagement/tracking', ['case' => ['reference' => $case->reference, 'type' => $case->case_type, 'category' => $case->category, 'subject' => $case->subject, 'county' => $case->county->identityCell(), 'status' => $case->status, 'submittedAt' => $case->created_at?->toIso8601String(), 'resolutionDueAt' => $case->resolution_due_at->toIso8601String(), 'resolutionSummary' => $case->resolution_summary, 'satisfactionRating' => $case->satisfaction_rating, 'messages' => $case->messages->map(fn (CitizenCaseMessage $message): array => ['id' => $message->id, 'direction' => $message->direction, 'body' => $message->body, 'postedAt' => $message->posted_at->toIso8601String()])->values()->all()]]);
    }

    public function rate(RateCitizenCaseRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $caseId = $request->session()->get('tracked_case_id');
        abort_unless(is_string($caseId), 403);
        $case = CitizenCase::query()->findOrFail($caseId);
        abort_unless(in_array($case->status, ['resolved', 'closed'], true), 409, __('citizen.rating_after_resolution'));
        abort_if($case->satisfaction_recorded_at !== null, 409, __('citizen.rating_already_recorded'));
        $case->update([...$request->validated(), 'satisfaction_recorded_at' => now()]);
        $auditLogger->record(null, $case, 'citizen_case.satisfaction_recorded', __('citizen.rating_audit', ['reference' => $case->reference]), $case->county_id, ['rating' => $case->satisfaction_rating]);

        return back()->with('success', __('citizen.rating_recorded'));
    }

    /** @return array<string, mixed> */
    private function dashboardData(): array
    {
        return [
            'total' => CitizenCase::query()->count(),
            'resolved' => CitizenCase::query()->whereIn('status', ['resolved', 'closed'])->count(),
            'pending' => CitizenCase::query()->whereNotIn('status', ['resolved', 'closed'])->count(),
            'satisfaction' => CitizenCase::query()->whereNotNull('satisfaction_rating')->avg('satisfaction_rating'),
            'recurringIssues' => collect(['complaint', 'grievance'])->map(fn (string $category): array => ['category' => $category, 'total' => CitizenCase::query()->where('category', $category)->count()])->sortByDesc('total')->values()->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<string>
     */
    private function snapshotIds(array $records): array
    {
        return array_values(collect($records)->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all());
    }
}
