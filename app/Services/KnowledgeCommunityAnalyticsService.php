<?php

namespace App\Services;

use App\Models\County;
use App\Models\KnowledgeCommunityReport;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeDiscussionSubscription;
use App\Models\KnowledgePost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class KnowledgeCommunityAnalyticsService
{
    public function __construct(private ProgrammeCountyScope $countyScope) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(User $user, array $filters): array
    {
        $counties = $this->countyScope->query($user)->orderBy('code')->get();
        $countyIds = $this->countyIds($counties);
        $this->authorizeSelectedCounty($filters['county_id'] ?? null, $countyIds);
        $metrics = $this->metricsQuery($user, $countyIds, $filters);
        $allRows = (clone $metrics)->get();
        $contributors = $this->contributors($allRows, $filters);
        $summary = $this->summary($allRows, $contributors);
        $countyRows = collect($this->countyRows($allRows, $contributors, $counties));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $discussions = (clone $metrics)->orderByDesc('last_posted_at')->orderBy('title')->paginate($perPage)->withQueryString();

        return [
            'summary' => $summary,
            'trend' => $this->trend($user, $countyIds, $filters),
            'counties' => $this->paginate($countyRows, (int) ($filters['county_page'] ?? 1), $perPage, 'county_page'),
            'discussions' => ['rows' => $discussions->getCollection()->map(fn (KnowledgeDiscussion $discussion): array => $this->discussionRow($discussion))->values()->all(), 'pagination' => ['currentPage' => $discussions->currentPage(), 'lastPage' => $discussions->lastPage(), 'perPage' => $discussions->perPage(), 'total' => $discussions->total(), 'pageName' => 'page']],
            'options' => ['counties' => $counties->map->identityCell()->values()],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(User $user, array $filters): array
    {
        $countyIds = $this->countyIds($this->countyScope->query($user)->get(['id']));
        $this->authorizeSelectedCounty($filters['county_id'] ?? null, $countyIds);

        return array_values($this->metricsQuery($user, $countyIds, $filters)->orderBy('title')->get()
            ->map(fn (KnowledgeDiscussion $discussion): array => $this->discussionRow($discussion))->values()->all());
    }

    /**
     * @param  list<string>  $countyIds
     * @param  array<string, mixed>  $filters
     * @return Builder<KnowledgeDiscussion>
     */
    private function metricsQuery(User $user, array $countyIds, array $filters): Builder
    {
        $posts = fn (): Builder => KnowledgePost::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('knowledge_discussion_id', 'knowledge_discussions.id')
            ->where('moderation_status', 'visible')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('posted_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('posted_at', '<=', $to));
        $contributors = fn (): Builder => KnowledgePost::query()
            ->selectRaw('COUNT(DISTINCT author_id)')
            ->whereColumn('knowledge_discussion_id', 'knowledge_discussions.id')
            ->where('moderation_status', 'visible')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('posted_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('posted_at', '<=', $to));
        $subscriptions = fn (): Builder => KnowledgeDiscussionSubscription::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('knowledge_discussion_id', 'knowledge_discussions.id')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('subscribed_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('subscribed_at', '<=', $to));
        $reports = fn (?string $status = null): Builder => KnowledgeCommunityReport::query()
            ->selectRaw('COUNT(*)')
            ->whereIn('knowledge_post_id', KnowledgePost::query()->select('id')->whereColumn('knowledge_discussion_id', 'knowledge_discussions.id'))
            ->when($status === 'resolved', fn (Builder $query) => $query->whereIn('status', ['resolved', 'dismissed']))
            ->when($status === 'open', fn (Builder $query) => $query->whereIn('status', ['reported', 'investigating']))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to));

        return KnowledgeDiscussion::query()
            ->select(['knowledge_discussions.id', 'knowledge_discussions.county_id', 'knowledge_discussions.title', 'knowledge_discussions.status', 'knowledge_discussions.visibility', 'knowledge_discussions.last_posted_at', 'knowledge_discussions.created_at'])
            ->addSelect(['contributions_count' => $posts(), 'contributors_count' => $contributors(), 'subscriptions_count' => $subscriptions(), 'reports_count' => $reports(), 'resolved_reports_count' => $reports('resolved'), 'open_reports_count' => $reports('open')])
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->whereNull('county_id')->orWhereIn('county_id', $countyIds)))
            ->when($filters['county_id'] ?? null, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $searchQuery) => $searchQuery->where('title', 'ilike', "%{$search}%")->orWhere('prompt', 'ilike', "%{$search}%")))
            ->with('county:id,name,code,logo_path');
    }

    /**
     * @param  Collection<int, KnowledgeDiscussion>  $rows
     * @param  Collection<int, KnowledgePost>  $contributors
     * @return array<string, int|float>
     */
    private function summary(Collection $rows, Collection $contributors): array
    {
        $reports = (int) $rows->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('reports_count'));
        $resolved = (int) $rows->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('resolved_reports_count'));

        return [
            'discussions' => $rows->count(),
            'openDiscussions' => $rows->where('status', 'open')->count(),
            'contributions' => (int) $rows->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('contributions_count')),
            'contributors' => $contributors->pluck('author_id')->unique()->count(),
            'subscriptions' => (int) $rows->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('subscriptions_count')),
            'reports' => $reports,
            'openReports' => (int) $rows->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('open_reports_count')),
            'resolutionRate' => $this->percentage($resolved, $reports),
        ];
    }

    /**
     * @param  Collection<int, KnowledgeDiscussion>  $rows
     * @param  Collection<int, KnowledgePost>  $contributors
     * @param  Collection<int, County>  $authorizedCounties
     * @return list<array<string, mixed>>
     */
    private function countyRows(Collection $rows, Collection $contributors, Collection $authorizedCounties): array
    {
        $countyIds = $rows->pluck('county_id')->filter()->unique()->values();
        $counties = $authorizedCounties->whereIn('id', $countyIds)->keyBy('id');

        return array_values($rows->whereNotNull('county_id')->groupBy('county_id')->map(function (Collection $countyDiscussions, string $countyId) use ($counties, $contributors): array {
            /** @var County $county */
            $county = $counties->get($countyId);
            $reports = (int) $countyDiscussions->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('reports_count'));
            $resolved = (int) $countyDiscussions->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('resolved_reports_count'));

            $discussionIds = $countyDiscussions->pluck('id');

            return ['county' => $county->identityCell(), 'discussions' => $countyDiscussions->count(), 'contributions' => (int) $countyDiscussions->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('contributions_count')), 'contributors' => $contributors->whereIn('knowledge_discussion_id', $discussionIds)->pluck('author_id')->unique()->count(), 'subscriptions' => (int) $countyDiscussions->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('subscriptions_count')), 'reports' => $reports, 'openReports' => (int) $countyDiscussions->sum(fn (KnowledgeDiscussion $row): int => (int) $row->getAttribute('open_reports_count')), 'resolutionRate' => $this->percentage($resolved, $reports)];
        })->sortBy(fn (array $row): mixed => $row['county']['code'])->values()->all());
    }

    /**
     * @param  Collection<int, KnowledgeDiscussion>  $discussions
     * @param  array<string, mixed>  $filters
     * @return Collection<int, KnowledgePost>
     */
    private function contributors(Collection $discussions, array $filters): Collection
    {
        if ($discussions->isEmpty()) {
            return collect();
        }

        return KnowledgePost::query()->select(['knowledge_discussion_id', 'author_id'])
            ->whereIn('knowledge_discussion_id', $discussions->pluck('id'))
            ->where('moderation_status', 'visible')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('posted_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('posted_at', '<=', $to))
            ->distinct()->get();
    }

    /**
     * @param  list<string>  $countyIds
     * @param  array<string, mixed>  $filters
     * @return list<array{period: string, contributions: int}>
     */
    private function trend(User $user, array $countyIds, array $filters): array
    {
        return array_values(KnowledgePost::query()->join('knowledge_discussions', 'knowledge_discussions.id', '=', 'knowledge_posts.knowledge_discussion_id')
            ->where('knowledge_posts.moderation_status', 'visible')
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->whereNull('knowledge_discussions.county_id')->orWhereIn('knowledge_discussions.county_id', $countyIds)))
            ->when($filters['county_id'] ?? null, fn (Builder $query, string $countyId) => $query->where('knowledge_discussions.county_id', $countyId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('knowledge_discussions.status', $status))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('knowledge_posts.posted_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('knowledge_posts.posted_at', '<=', $to))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $searchQuery) => $searchQuery->where('knowledge_discussions.title', 'ilike', "%{$search}%")->orWhere('knowledge_discussions.prompt', 'ilike', "%{$search}%")))
            ->selectRaw("to_char(date_trunc('month', knowledge_posts.posted_at), 'YYYY-MM') AS period, COUNT(*) AS contributions")
            ->groupByRaw("date_trunc('month', knowledge_posts.posted_at)")->orderByRaw("date_trunc('month', knowledge_posts.posted_at)")->get()
            ->map(fn (Model $row): array => ['period' => (string) $row->getAttribute('period'), 'contributions' => (int) $row->getAttribute('contributions')])->values()->all());
    }

    /** @return array<string, mixed> */
    private function discussionRow(KnowledgeDiscussion $discussion): array
    {
        $reports = (int) $discussion->getAttribute('reports_count');
        $resolved = (int) $discussion->getAttribute('resolved_reports_count');

        return ['id' => $discussion->id, 'title' => $discussion->title, 'county' => $discussion->county?->identityCell(), 'visibility' => $discussion->visibility, 'status' => $discussion->status, 'contributions' => (int) $discussion->getAttribute('contributions_count'), 'contributors' => (int) $discussion->getAttribute('contributors_count'), 'subscriptions' => (int) $discussion->getAttribute('subscriptions_count'), 'reports' => $reports, 'openReports' => (int) $discussion->getAttribute('open_reports_count'), 'resolutionRate' => $this->percentage($resolved, $reports), 'lastActivityAt' => $discussion->last_posted_at?->toIso8601String()];
    }

    /**
     * @param  Collection<int, County>  $counties
     * @return list<string>
     */
    private function countyIds(Collection $counties): array
    {
        return array_values($counties->pluck('id')->map(fn (mixed $id): string => (string) $id)->all());
    }

    /** @param list<string> $countyIds */
    private function authorizeSelectedCounty(mixed $selectedCountyId, array $countyIds): void
    {
        abort_if(is_string($selectedCountyId) && ! in_array($selectedCountyId, $countyIds, true), 403);
    }

    private function percentage(int $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0 : round(($numerator / $denominator) * 100, 2);
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  Collection<int, T>  $items
     * @return array{rows: list<T>, pagination: array{currentPage: int, lastPage: int, perPage: int, total: int, pageName: string}}
     */
    private function paginate(Collection $items, int $page, int $perPage, string $pageName): array
    {
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $lastPage);

        return ['rows' => array_values($items->slice(($currentPage - 1) * $perPage, $perPage)->all()), 'pagination' => ['currentPage' => $currentPage, 'lastPage' => $lastPage, 'perPage' => $perPage, 'total' => $total, 'pageName' => $pageName]];
    }
}
