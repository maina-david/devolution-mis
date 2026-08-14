<?php

namespace App\Services;

use App\Enums\AssessmentStatus;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentDocument;
use App\Models\CitizenCase;
use App\Models\County;
use App\Models\DevolutionProject;
use App\Models\EvaluationFinding;
use App\Models\ExchequerRequest;
use App\Models\PartnerOperationalAlert;
use App\Models\User;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;

class DashboardData
{
    public function __construct(private ProgrammeCountyScope $countyScope) {}

    /** @return array<string, mixed> */
    public function for(User $user, WorkspaceFilters $filters): array
    {
        $role = $user->programmeRole();
        $counties = $this->countyScope->query($user)
            ->when($filters->search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($filters->search).'%']))
            ->with([
                'assessments' => fn ($query) => $query->when($filters->cycleId, fn ($query, string $cycleId) => $query->where('assessment_cycle_id', $cycleId))->when($filters->from, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))->when($filters->to, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))->latest('assessed_at'),
                'grants' => fn ($query) => $query->when($filters->from, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))->when($filters->to, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to)),
            ])
            ->withCount(['documents' => fn ($query) => $query->when($filters->from, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))->when($filters->to, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))])
            ->orderBy('code')
            ->get();

        $completedStatuses = [AssessmentStatus::Assessed, AssessmentStatus::Approved, AssessmentStatus::Published];
        $assessedCount = $counties->filter(fn (County $county) => $county->assessments->contains(fn ($assessment) => in_array($assessment->status, $completedStatuses, true)))->count();
        $latestScores = $counties->map(fn (County $county) => $county->assessments->first()?->score)->filter();
        $countyIds = $counties->pluck('id');
        $period = function (Builder $query) use ($filters): Builder {
            return $query
                ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
                ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to));
        };
        $operationalSignals = [
            'activeProjects' => $period(DevolutionProject::query()->whereIn('lead_county_id', $countyIds)->whereNotIn('status', ['completed', 'cancelled', 'closed']))->count(),
            'overdueCitizenCases' => $period(CitizenCase::query()->whereIn('county_id', $countyIds)->whereNull('resolved_at')->where('resolution_due_at', '<', now()))->count(),
            'delayedExchequerRequests' => $period(ExchequerRequest::query()->whereIn('county_id', $countyIds)->where('status', 'open')->where('stage_due_at', '<', now()))->count(),
            'overdueEvaluationFindings' => $period(EvaluationFinding::query()->whereIn('county_id', $countyIds)->where('status', '!=', 'closed')->whereDate('due_at', '<', today()))->count(),
            'openPartnerAlerts' => $period(PartnerOperationalAlert::query()->whereIn('county_id', $countyIds)->where('status', 'open'))->count(),
            'evidenceAwaitingReview' => $period(AssessmentDocument::query()->whereIn('county_id', $countyIds)->where('record_status', 'active')->where('verification_status', 'pending'))->count(),
            'evidenceScanAttention' => $period(AssessmentDocument::query()->whereIn('county_id', $countyIds)->where('record_status', 'active')->whereIn('scan_status', ['pending', 'failed', 'infected']))->count(),
        ];
        $assessmentsByCycle = Assessment::query()
            ->whereIn('county_id', $countyIds)
            ->whereNotNull('assessment_cycle_id')
            ->when($filters->from, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters->to, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->withCount('documents')
            ->get(['id', 'assessment_cycle_id', 'county_id', 'status', 'score'])
            ->groupBy('assessment_cycle_id');
        $cycleOverview = AssessmentCycle::query()
            ->latest('period_start')
            ->get()
            ->map(function (AssessmentCycle $cycle) use ($assessmentsByCycle, $completedStatuses, $counties, $filters): array {
                $assessments = $assessmentsByCycle->get($cycle->id, collect());
                $completed = $assessments->filter(fn (Assessment $assessment) => in_array($assessment->status, $completedStatuses, true));
                $scores = $completed->pluck('score')->filter(fn ($score) => $score !== null);
                $completedCount = $completed->pluck('county_id')->unique()->count();

                return [
                    'id' => $cycle->id,
                    'code' => $cycle->code,
                    'name' => $cycle->name,
                    'status' => $cycle->status,
                    'periodStart' => $cycle->period_start->toDateString(),
                    'periodEnd' => $cycle->period_end->toDateString(),
                    'selected' => $filters->cycleId === $cycle->id,
                    'countiesAssessed' => $completedCount,
                    'countiesTotal' => $counties->count(),
                    'completionPercent' => $counties->isEmpty() ? 0 : round(($completedCount / $counties->count()) * 100, 1),
                    'averageScore' => $scores->isEmpty() ? null : round($scores->average(), 1),
                    'evidenceDocuments' => $assessments->sum('documents_count'),
                ];
            })
            ->values();

        return [
            'dashboardProfile' => [
                'role' => $role->name,
                'roleLabel' => (string) __("dashboard.profiles.{$role->value}.role_label"),
                'mapScope' => $this->mapScope($role),
                ...$this->profileCopy($role),
            ],
            'stats' => [
                'counties' => $counties->count(),
                'assessed' => $assessedCount,
                'pending' => $counties->count() - $assessedCount,
                'documents' => $counties->sum('documents_count'),
                'averageScore' => $latestScores->isEmpty() ? null : round($latestScores->average(), 1),
                'allocatedGrants' => (float) $counties->flatMap(fn (County $county) => $county->grants)->sum('allocated_amount'),
                'disbursedGrants' => (float) $counties->flatMap(fn (County $county) => $county->grants)->sum('disbursed_amount'),
            ],
            'counties' => $counties->map(function (County $county): array {
                $latest = $county->assessments->first();

                return ['id' => $county->id, 'code' => $county->code, 'name' => $county->name, 'slug' => $county->slug, 'region' => $county->region, 'logoUrl' => $county->logo_path, 'mapX' => $county->map_x, 'mapY' => $county->map_y, 'assessmentStatus' => $latest?->status->value ?? 'not_started', 'latestCycle' => $latest?->cycle, 'latestScore' => $latest?->score !== null ? (float) $latest->score : null, 'documents' => $county->documents_count, 'allocatedGrant' => (float) $county->grants->sum('allocated_amount'), 'disbursedGrant' => (float) $county->grants->sum('disbursed_amount')];
            })->values(),
            'cycleOverview' => $cycleOverview,
            'operationalSignals' => $operationalSignals,
            'roleFocus' => $this->roleFocus($role),
        ];
    }

    private function mapScope(UserRole $role): string
    {
        return match ($role) {
            UserRole::Assessor, UserRole::DevelopmentPartner, UserRole::DevolutionAdmin => 'country',
            UserRole::CountyOfficial, UserRole::CountyAdmin => 'county',
            UserRole::TopManagement => 'portfolio',
            UserRole::PlatformAdmin => 'none',
        };
    }

    /** @return array{eyebrow: string, title: string, description: string} */
    private function profileCopy(UserRole $role): array
    {
        $key = "dashboard.profiles.{$role->value}";

        return [
            'eyebrow' => (string) __("{$key}.eyebrow"),
            'title' => (string) __("{$key}.title"),
            'description' => (string) __("{$key}.description"),
        ];
    }

    /** @return list<string> */
    private function roleFocus(UserRole $role): array
    {
        $key = "dashboard.profiles.{$role->value}.focus";

        return [
            (string) __("{$key}.first"),
            (string) __("{$key}.second"),
            (string) __("{$key}.third"),
        ];
    }
}
