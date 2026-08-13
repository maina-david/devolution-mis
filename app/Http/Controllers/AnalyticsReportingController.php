<?php

namespace App\Http\Controllers;

use App\Actions\ActivateReportSchedule;
use App\Actions\AddAnalyticsWidget;
use App\Actions\CreateAnalyticsDashboard;
use App\Actions\CreateReportSchedule;
use App\Actions\PublishAnalyticsDashboard;
use App\Actions\SaveAnalyticsFilterView;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Http\Requests\ActivateReportScheduleRequest;
use App\Http\Requests\DeleteAnalyticsFilterViewRequest;
use App\Http\Requests\PublishAnalyticsDashboardRequest;
use App\Http\Requests\StoreAnalyticsDashboardRequest;
use App\Http\Requests\StoreAnalyticsFilterViewRequest;
use App\Http\Requests\StoreAnalyticsWidgetRequest;
use App\Http\Requests\StoreReportScheduleRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Jobs\GenerateScheduledReport;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsFilterView;
use App\Models\AnalyticsWidget;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Services\AnalyticsMetricCatalogue;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\ProgrammeCountyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsReportingController extends Controller
{
    public function __construct(
        private ProgrammeCountyScope $countyScope,
        private AnalyticsMetricCatalogue $metricCatalogue,
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
        private AuditLogger $auditLogger,
    ) {}

    public function index(WorkspaceIndexRequest $request): RedirectResponse|Response
    {
        Gate::authorize(ProgrammePermission::ViewAnalytics->value);
        $user = $this->user($request);
        $filterKeys = ['from', 'to', 'search', 'status', 'county_id', 'per_page', 'dashboard_id', 'widget_id', 'visualization', 'time_grain'];
        $hasExplicitFilter = collect($filterKeys)->contains(fn (string $key): bool => $request->filled($key));
        if (! $hasExplicitFilter) {
            $defaultView = AnalyticsFilterView::query()->where('user_id', $user->id)->where('is_default', true)->first();
            if ($defaultView instanceof AnalyticsFilterView && $defaultView->filters !== []) {
                return redirect()->route('analytics.index', $defaultView->filters);
            }
        }
        $countyIds = $this->countyScope->query($user)->pluck('id');
        $mayManage = $user->can(ProgrammePermission::ManageAnalytics->value);
        $mayApproveDashboard = $user->can(ProgrammePermission::ApproveAnalytics->value);
        $mayApproveSchedule = $user->can(ProgrammePermission::ApproveReportSchedules->value);
        $referenceDataRelease = $this->referenceDataReleaseResolver->availableForSelection(now());
        $governedCountyIds = collect($referenceDataRelease?->snapshot['counties'] ?? [])->pluck('id')->filter()->all();
        $dashboards = AnalyticsDashboard::query()
            ->with(['widgets' => fn ($query) => $query->orderBy('position'), 'county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'referenceDataRelease:id,version,effective_from,checksum', 'creator:id,name', 'publisher:id,name'])
            ->where(function (Builder $query) use ($user, $mayManage, $mayApproveDashboard): void {
                $query->where(fn (Builder $published) => $published->where('status', 'published')->whereJsonContains('audience_roles', $user->programmeRole()->value));
                if ($mayManage) {
                    $query->orWhere(fn (Builder $draft) => $draft->where('status', 'draft')->where('created_by', $user->id));
                }
                if ($mayApproveDashboard) {
                    $query->orWhere('status', 'draft');
                }
            })
            ->where(fn (Builder $query) => $query->whereNull('county_id')->orWhereIn('county_id', $countyIds))
            ->when($request->filled('county_id'), fn (Builder $query) => $query->where('county_id', $request->string('county_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('dashboard_id'), fn (Builder $query) => $query->whereKey($request->string('dashboard_id')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim();
                $query->where(fn (Builder $query) => $query->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%"));
            })
            ->latest()
            ->get();
        $dashboardPayload = $dashboards->map(function (AnalyticsDashboard $dashboard) use ($user, $request): array {
            $requestFilters = array_filter(['from' => $request->input('from'), 'to' => $request->input('to'), 'county_id' => $dashboard->county_id ?? $request->input('county_id')]);

            return [
                'id' => $dashboard->id,
                'code' => $dashboard->code,
                'name' => $dashboard->name,
                'description' => $dashboard->description,
                'county' => $dashboard->county?->identityCell(),
                'audienceRoles' => $dashboard->audience_roles,
                'status' => $dashboard->status,
                'checksum' => $dashboard->checksum,
                'referenceData' => $dashboard->referenceDataRelease === null ? null : ['version' => $dashboard->referenceDataRelease->version, 'effectiveFrom' => $dashboard->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $dashboard->referenceDataRelease->checksum],
                'publishedAt' => $dashboard->published_at?->toIso8601String(),
                'creator' => $dashboard->creator->name,
                'publisher' => $dashboard->publisher?->name,
                'widgets' => $dashboard->widgets
                    ->when($request->filled('widget_id'), fn ($widgets) => $widgets->where('id', (string) $request->input('widget_id')))
                    ->map(fn (AnalyticsWidget $widget): array => [
                        'id' => $widget->id,
                        'title' => $widget->title,
                        'description' => $widget->description,
                        'metricKey' => $widget->metric_key,
                        'visualization' => $request->filled('visualization') ? (string) $request->input('visualization') : $widget->visualization,
                        'disaggregation' => $widget->disaggregation,
                        'position' => $widget->position,
                        'width' => $widget->width,
                        'measurement' => $this->metricCatalogue->evaluate($user, $widget->metric_key, [...($widget->filters ?? []), ...$requestFilters, ...array_filter(['time_grain' => $request->input('time_grain')])], $widget->disaggregation),
                    ])->values()->all(),
            ];
        })->values();

        $schedules = collect();
        $runs = null;
        if ($mayManage || $mayApproveSchedule) {
            $scheduleQuery = ReportSchedule::query()
                ->with(['county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'referenceDataRelease:id,version,effective_from,checksum', 'creator:id,name', 'approver:id,name'])
                ->where(fn (Builder $query) => $query->whereNull('county_id')->orWhereIn('county_id', $countyIds))
                ->latest();
            $schedules = (clone $scheduleQuery)->get()->map(fn (ReportSchedule $schedule): array => $this->schedulePayload($schedule))->values();
            $runs = ReportRun::query()->with('schedule:id,code,name,format,county_id')->whereHas('schedule', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('county_id')->orWhereIn('county_id', $countyIds)))->latest()->paginate($request->integer('per_page', 10), pageName: 'runs_page')->withQueryString()->through(fn (ReportRun $run): array => $this->runPayload($run));
        }

        return Inertia::render('analytics/index', [
            'dashboards' => $dashboardPayload,
            'savedFilterViews' => AnalyticsFilterView::query()->where('user_id', $user->id)->latest()->get()->map(fn (AnalyticsFilterView $view): array => ['id' => $view->id, 'name' => $view->name, 'filters' => $view->filters, 'isDefault' => $view->is_default])->values(),
            'schedules' => $schedules,
            'runs' => $runs,
            'filters' => $request->safe()->only($filterKeys),
            'options' => [
                'counties' => $this->countyScope->query($user)->whereIn('id', $governedCountyIds)->orderBy('code')->get()->map->identityCell()->values(),
                'metrics' => collect($this->metricCatalogue->options())->map(fn (string $label, string $key): array => ['id' => $key, 'name' => $label])->values(),
                'roles' => collect(UserRole::cases())->map(fn (UserRole $role): array => ['id' => $role->value, 'name' => $role->label()])->values(),
                'users' => User::query()->whereNull('access_revoked_at')->orderBy('name')->get(['id', 'name']),
                'publishedDashboards' => $dashboards->where('status', 'published')->whereNotNull('reference_data_release_id')->map(fn (AnalyticsDashboard $dashboard): array => ['id' => $dashboard->id, 'name' => "{$dashboard->code} · {$dashboard->name}"])->values(),
            ],
            'catalogue' => $referenceDataRelease === null ? ['available' => false] : ['available' => true, 'version' => $referenceDataRelease->version, 'effectiveFrom' => $referenceDataRelease->effective_from?->toDateString(), 'checksum' => $referenceDataRelease->checksum],
            'capabilities' => ['manage' => $mayManage, 'approveDashboard' => $mayApproveDashboard, 'approveSchedule' => $mayApproveSchedule],
        ]);
    }

    public function storeFilterView(StoreAnalyticsFilterViewRequest $request, SaveAnalyticsFilterView $action): RedirectResponse
    {
        $validated = $request->validated();
        $view = $action->handle($this->user($request), [
            'name' => $validated['name'],
            'filters' => $validated['filters'],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return back()->with('success', __('analytics.filter_saved', ['name' => $view->name]));
    }

    public function destroyFilterView(DeleteAnalyticsFilterViewRequest $request, AnalyticsFilterView $filterView): RedirectResponse
    {
        $actor = $this->user($request);
        $this->auditLogger->record($actor, $filterView, 'analytics.filter_view.deleted', __('analytics.audit.filter_deleted', ['name' => $filterView->name]), metadata: ['filter_keys' => array_keys($filterView->filters)]);
        $filterView->delete();

        return back()->with('success', __('analytics.filter_deleted'));
    }

    public function storeDashboard(StoreAnalyticsDashboardRequest $request, CreateAnalyticsDashboard $action): RedirectResponse
    {
        $dashboard = $action->handle($this->user($request), $request->validated());

        return back()->with('success', __('analytics.dashboard_created', ['code' => $dashboard->code]));
    }

    public function storeWidget(StoreAnalyticsWidgetRequest $request, AnalyticsDashboard $dashboard, AddAnalyticsWidget $action): RedirectResponse
    {
        $action->handle($dashboard, $this->user($request), $request->validated());

        return back()->with('success', __('analytics.widget_added'));
    }

    public function publish(PublishAnalyticsDashboardRequest $request, AnalyticsDashboard $dashboard, PublishAnalyticsDashboard $action): RedirectResponse
    {
        $action->handle($dashboard, $this->user($request));

        return back()->with('success', __('analytics.dashboard_published'));
    }

    public function storeSchedule(StoreReportScheduleRequest $request, CreateReportSchedule $action): RedirectResponse
    {
        $schedule = $action->handle($this->user($request), $request->validated());

        return back()->with('success', __('analytics.schedule_created', ['code' => $schedule->code]));
    }

    public function activate(ActivateReportScheduleRequest $request, ReportSchedule $schedule, ActivateReportSchedule $action): RedirectResponse
    {
        $action->handle($schedule, $this->user($request));

        return back()->with('success', __('analytics.schedule_activated'));
    }

    public function runNow(Request $request, ReportSchedule $schedule): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageAnalytics->value);
        abort_unless($schedule->status === 'active', 409, __('analytics.errors.active_schedule_required'));
        $user = $this->user($request);
        if ($schedule->county !== null) {
            abort_unless($user->canAccessCounty($schedule->county), 403);
        }
        $run = $schedule->runs()->create(['triggered_by' => $user->id, 'status' => 'queued', 'filter_snapshot' => $schedule->filters, 'period_from' => $schedule->filters['from'] ?? null, 'period_to' => $schedule->filters['to'] ?? null]);
        GenerateScheduledReport::dispatch($run);

        return back()->with('success', __('analytics.report_queued'));
    }

    public function download(Request $request, ReportRun $run): StreamedResponse
    {
        Gate::authorize(ProgrammePermission::ViewAnalytics->value);
        $run->load('schedule.county');
        $user = $this->user($request);
        abort_unless($run->status === 'completed' && $run->disk && $run->path && $run->sha256, 409, __('analytics.errors.artifact_not_ready'));
        if ($run->schedule->county !== null) {
            abort_unless($user->canAccessCounty($run->schedule->county), 403);
        } else {
            abort_unless($user->programmeRole()->hasNationalScope(), 403);
        }
        $contents = Storage::disk($run->disk)->get($run->path);
        abort_unless(hash_equals($run->sha256, hash('sha256', $contents)), 409, __('analytics.errors.artifact_integrity_failed'));

        return Storage::disk($run->disk)->download($run->path, str($run->schedule->code)->slug().'-'.$run->id.'.'.$run->schedule->format, ['Content-Type' => $run->mime_type]);
    }

    /** @return array<string, mixed> */
    private function schedulePayload(ReportSchedule $schedule): array
    {
        return ['id' => $schedule->id, 'code' => $schedule->code, 'name' => $schedule->name, 'county' => $schedule->county?->identityCell(), 'referenceData' => $schedule->referenceDataRelease === null ? null : ['version' => $schedule->referenceDataRelease->version, 'effectiveFrom' => $schedule->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $schedule->referenceDataRelease->checksum], 'format' => $schedule->format, 'frequency' => $schedule->frequency, 'filters' => $schedule->filters, 'recipientCount' => count($schedule->recipient_user_ids), 'status' => $schedule->status, 'nextRunAt' => $schedule->next_run_at->toIso8601String(), 'approvedAt' => $schedule->approved_at?->toIso8601String(), 'creator' => $schedule->creator->name, 'approver' => $schedule->approver?->name];
    }

    /** @return array<string, mixed> */
    private function runPayload(ReportRun $run): array
    {
        return ['id' => $run->id, 'schedule' => ['code' => $run->schedule->code, 'name' => $run->schedule->name, 'format' => $run->schedule->format], 'status' => $run->status, 'periodFrom' => $run->period_from?->toDateString(), 'periodTo' => $run->period_to?->toDateString(), 'sizeBytes' => $run->size_bytes, 'sha256' => $run->sha256, 'recordCount' => $run->record_count, 'errorDetail' => $run->error_detail, 'startedAt' => $run->started_at?->toIso8601String(), 'completedAt' => $run->completed_at?->toIso8601String()];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
