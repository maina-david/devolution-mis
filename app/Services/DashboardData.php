<?php

namespace App\Services;

use App\Enums\AssessmentStatus;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentCycle;
use App\Models\County;
use App\Models\User;
use App\Support\WorkspaceFilters;

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
                'roleLabel' => $role->label(),
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
        return match ($role) {
            UserRole::CountyOfficial => ['eyebrow' => 'County workspace', 'title' => 'Evidence and assessment readiness', 'description' => 'Prepare county evidence, monitor completeness, and follow the latest assessment outcome.'],
            UserRole::CountyAdmin => ['eyebrow' => 'County administration', 'title' => 'County performance control', 'description' => 'Coordinate officials, evidence collection, submissions, grants, and county readiness.'],
            UserRole::Assessor => ['eyebrow' => 'Independent verification', 'title' => 'Assigned county assessments', 'description' => 'Review evidence and assessment progress only for counties assigned to you.'],
            UserRole::DevelopmentPartner => ['eyebrow' => 'Partner portfolio', 'title' => 'Programme results and grants', 'description' => 'Track assessment results, evidence, and grant delivery across your partner portfolio.'],
            UserRole::TopManagement => ['eyebrow' => 'Executive oversight', 'title' => 'County performance overview', 'description' => 'Monitor the counties in your oversight portfolio and focus attention where delivery is behind.'],
            UserRole::DevolutionAdmin => ['eyebrow' => 'National coordination', 'title' => 'All-county delivery command', 'description' => 'Coordinate assessment cycles, evidence readiness, grants, and performance across all 47 counties.'],
            UserRole::PlatformAdmin => ['eyebrow' => 'Platform operations', 'title' => 'National platform control', 'description' => 'Monitor platform coverage and administer governed access across the national service.'],
        };
    }
}
