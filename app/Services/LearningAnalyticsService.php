<?php

namespace App\Services;

use App\Models\County;
use App\Models\LearningAssessmentAttempt;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningQuestionBankItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LearningAnalyticsService
{
    public function __construct(private ProgrammeCountyScope $countyScope) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(User $user, array $filters): array
    {
        $authorizedCounties = $this->countyScope->query($user)->orderBy('code')->get();
        $countyIds = $this->countyIds($authorizedCounties);
        $this->authorizeSelectedCounty($filters['county_id'] ?? null, $countyIds);
        $base = $this->filteredEnrollments($countyIds, $filters);
        $summary = (clone $base)->selectRaw("COUNT(*) AS enrollments, COUNT(*) FILTER (WHERE status = 'in_progress') AS active, COUNT(*) FILTER (WHERE status = 'completed') AS completed, ROUND(AVG(best_score)::numeric, 2) AS average_score, ROUND(AVG(progress_percentage)::numeric, 2) AS average_progress")->firstOrFail();
        $certificates = (clone $base)->whereHas('certificate')->count();
        $courses = (clone $base)->join('learning_courses', 'learning_courses.id', '=', 'learning_enrollments.learning_course_id')
            ->selectRaw("learning_courses.id, learning_courses.code, learning_courses.title, learning_courses.category, COUNT(*) AS enrollments, COUNT(*) FILTER (WHERE learning_enrollments.status = 'completed') AS completed, ROUND(AVG(learning_enrollments.progress_percentage)::numeric, 2) AS average_progress, ROUND(AVG(learning_enrollments.best_score)::numeric, 2) AS average_score")
            ->groupBy('learning_courses.id', 'learning_courses.code', 'learning_courses.title', 'learning_courses.category')
            ->orderBy('learning_courses.code')->get()->map(fn (Model $row): array => $this->courseRow($row));
        $counties = (clone $base)->join('counties', 'counties.id', '=', 'learning_enrollments.county_id')
            ->selectRaw("counties.id, counties.code, counties.name, counties.logo_path, COUNT(*) AS enrollments, COUNT(*) FILTER (WHERE learning_enrollments.status = 'completed') AS completed, ROUND(AVG(learning_enrollments.progress_percentage)::numeric, 2) AS average_progress, ROUND(AVG(learning_enrollments.best_score)::numeric, 2) AS average_score")
            ->groupBy('counties.id', 'counties.code', 'counties.name', 'counties.logo_path')
            ->orderBy('counties.code')->get()->map(fn (Model $row): array => $this->countyRow($row));
        $trend = (clone $base)->selectRaw("to_char(date_trunc('month', enrolled_at), 'YYYY-MM') AS period, COUNT(*) AS enrollments, COUNT(*) FILTER (WHERE status = 'completed') AS completed")
            ->groupByRaw("date_trunc('month', enrolled_at)")->orderByRaw("date_trunc('month', enrolled_at)")->get()
            ->map(function (Model $row): array {
                $enrollments = (int) $row->getAttribute('enrollments');
                $suppressed = $this->shouldSuppress($enrollments);

                return ['period' => (string) $row->getAttribute('period'), 'suppressed' => $suppressed, 'enrollments' => $suppressed ? null : $enrollments, 'completed' => $suppressed ? null : (int) $row->getAttribute('completed')];
            })->values()->all();
        $total = (int) $summary->getAttribute('enrollments');
        $completed = (int) $summary->getAttribute('completed');
        $perPage = (int) ($filters['per_page'] ?? 10);
        $summarySuppressed = $this->shouldSuppress($total);
        $questionBank = $this->questionBankAnalysis($base, (int) ($filters['question_page'] ?? 1), $perPage);

        return [
            'privacy' => ['minimumCellSize' => $this->minimumCellSize()],
            'summary' => ['hasData' => $total > 0, 'suppressed' => $summarySuppressed, 'enrollments' => $summarySuppressed ? null : $total, 'active' => $summarySuppressed ? null : (int) $summary->getAttribute('active'), 'completed' => $summarySuppressed ? null : $completed, 'completionRate' => $summarySuppressed ? null : $this->percentage($completed, $total), 'certificates' => $summarySuppressed ? null : $certificates, 'averageScore' => $summarySuppressed || $summary->getAttribute('average_score') === null ? null : (float) $summary->getAttribute('average_score'), 'averageProgress' => $summarySuppressed ? null : (float) ($summary->getAttribute('average_progress') ?? 0)],
            'courses' => $this->paginate($courses, (int) ($filters['course_page'] ?? 1), $perPage, 'course_page'),
            'counties' => $this->paginate($counties, (int) ($filters['county_page'] ?? 1), $perPage, 'county_page'),
            'trend' => $trend,
            'questionBank' => $questionBank,
            'options' => ['counties' => $authorizedCounties->map->identityCell()->values(), 'courses' => LearningCourse::query()->where('status', 'published')->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->whereNull('county_id')->orWhereIn('county_id', $countyIds)))->orderBy('code')->get(['id', 'code', 'title'])->map(fn (LearningCourse $course): array => ['id' => $course->id, 'name' => "{$course->code} · {$course->title}"])->values()],
        ];
    }

    /**
     * @param  Builder<LearningEnrollment>  $enrollments
     * @return array{hasData: bool, attempts: int|null, suppressed: bool, lineages: int, rows: list<array<string, mixed>>, pagination: array{currentPage: int, lastPage: int, perPage: int, total: int, pageName: string}}
     */
    private function questionBankAnalysis(Builder $enrollments, int $page, int $perPage): array
    {
        /** @var array<string, array{questionId: string, responseCount: int, correctCount: int, correctScoreTotal: float, incorrectCount: int, incorrectScoreTotal: float, lineages: array<string, true>}> $aggregates */
        $aggregates = [];
        $attemptCount = 0;
        $lineages = [];
        $attempts = LearningAssessmentAttempt::query()
            ->whereIn('learning_enrollment_id', (clone $enrollments)->select('learning_enrollments.id'))
            ->select(['id', 'score', 'result_snapshot'])
            ->cursor();

        foreach ($attempts as $attempt) {
            $snapshot = $attempt->result_snapshot;
            $results = is_array($snapshot['questions'] ?? null) ? $snapshot['questions'] : $snapshot;
            $validResults = array_values(array_filter(
                $results,
                fn (mixed $result): bool => is_array($result) && is_string($result['question_id'] ?? null) && $result['question_id'] !== '',
            ));

            if ($validResults === []) {
                continue;
            }

            $attemptCount++;
            $lineage = is_string($snapshot['question_bank_checksum'] ?? null) ? $snapshot['question_bank_checksum'] : 'legacy-unversioned';
            $lineages[$lineage] = true;
            foreach ($validResults as $result) {
                $questionId = $result['question_id'];
                $aggregates[$questionId] ??= ['questionId' => $questionId, 'responseCount' => 0, 'correctCount' => 0, 'correctScoreTotal' => 0.0, 'incorrectCount' => 0, 'incorrectScoreTotal' => 0.0, 'lineages' => []];
                $aggregates[$questionId]['responseCount']++;
                $aggregates[$questionId]['lineages'][$lineage] = true;
                if (($result['correct'] ?? false) === true) {
                    $aggregates[$questionId]['correctCount']++;
                    $aggregates[$questionId]['correctScoreTotal'] += (float) $attempt->score;
                } else {
                    $aggregates[$questionId]['incorrectCount']++;
                    $aggregates[$questionId]['incorrectScoreTotal'] += (float) $attempt->score;
                }
            }
        }

        $items = LearningQuestionBankItem::query()
            ->with(['question:id,question', 'bank:id,version,checksum'])
            ->whereIn('learning_quiz_question_id', array_keys($aggregates))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('learning_quiz_question_id');
        $rows = collect($aggregates)->map(function (array $aggregate) use ($items): array {
            $lineageChecksums = array_keys($aggregate['lineages']);
            $singleLineageChecksum = count($lineageChecksums) === 1 ? $lineageChecksums[0] : null;
            $questionItems = $items->get($aggregate['questionId'], collect());
            $item = $questionItems->first(
                fn (LearningQuestionBankItem $candidate): bool => $singleLineageChecksum !== null && $candidate->bank->checksum === $singleLineageChecksum,
            ) ?? $questionItems->first();
            $suppressed = $this->shouldSuppress($aggregate['responseCount']);
            $discrimination = null;
            if (! $suppressed && $aggregate['correctCount'] > 0 && $aggregate['incorrectCount'] > 0) {
                $discrimination = round(($aggregate['correctScoreTotal'] / $aggregate['correctCount']) - ($aggregate['incorrectScoreTotal'] / $aggregate['incorrectCount']), 2);
            }
            $question = $item instanceof LearningQuestionBankItem && $item->question !== null ? $item->question->question : (string) __('learning-analytics.removed_question');
            $variantGroup = $item instanceof LearningQuestionBankItem ? $item->variant_group : (string) __('learning-analytics.legacy_group');
            $difficulty = $item instanceof LearningQuestionBankItem ? $item->difficulty : (string) __('learning-analytics.unclassified');
            $tags = $item instanceof LearningQuestionBankItem ? array_values(array_filter($item->tags, is_string(...))) : [];
            $hasMatchedVersion = $singleLineageChecksum !== null
                && $singleLineageChecksum !== 'legacy-unversioned'
                && $item instanceof LearningQuestionBankItem
                && $item->bank->checksum === $singleLineageChecksum;

            return [
                'id' => $aggregate['questionId'],
                'question' => $question,
                'variantGroup' => $variantGroup,
                'difficulty' => $difficulty,
                'tags' => $tags,
                'bankVersion' => $hasMatchedVersion ? $item->bank->version : null,
                'bankChecksum' => $hasMatchedVersion ? $singleLineageChecksum : null,
                'lineageCount' => count($lineageChecksums),
                'suppressed' => $suppressed,
                'responseCount' => $suppressed ? null : $aggregate['responseCount'],
                'correctRate' => $suppressed ? null : $this->percentage($aggregate['correctCount'], $aggregate['responseCount']),
                'discrimination' => $discrimination,
            ];
        })->sort(function (array $left, array $right): int {
            if ($left['suppressed'] !== $right['suppressed']) {
                return $left['suppressed'] <=> $right['suppressed'];
            }

            return ($left['correctRate'] ?? PHP_FLOAT_MAX) <=> ($right['correctRate'] ?? PHP_FLOAT_MAX)
                ?: strcmp($left['question'], $right['question']);
        })->values();
        $suppressed = $this->shouldSuppress($attemptCount);
        $pagination = $this->paginateQuestionRows(array_values($rows->all()), $page, $perPage);

        return ['hasData' => $attemptCount > 0, 'attempts' => $suppressed ? null : $attemptCount, 'suppressed' => $suppressed, 'lineages' => count($lineages), ...$pagination];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, pagination: array{currentPage: int, lastPage: int, perPage: int, total: int, pageName: string}}
     */
    private function paginateQuestionRows(array $rows, int $page, int $perPage): array
    {
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $lastPage);

        return ['rows' => array_slice($rows, ($currentPage - 1) * $perPage, $perPage), 'pagination' => ['currentPage' => $currentPage, 'lastPage' => $lastPage, 'perPage' => $perPage, 'total' => $total, 'pageName' => 'question_page']];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(User $user, array $filters): array
    {
        $countyIds = $this->countyIds($this->countyScope->query($user)->get(['id']));
        $this->authorizeSelectedCounty($filters['county_id'] ?? null, $countyIds);

        return array_values($this->filteredEnrollments($countyIds, $filters)
            ->join('learning_courses', 'learning_courses.id', '=', 'learning_enrollments.learning_course_id')
            ->join('counties', 'counties.id', '=', 'learning_enrollments.county_id')
            ->selectRaw("counties.id AS county_id, counties.code AS county_code, counties.name AS county_name, counties.logo_path, learning_courses.code AS course_code, learning_courses.title AS course_title, COUNT(*) AS enrollments, COUNT(*) FILTER (WHERE learning_enrollments.status = 'completed') AS completed, ROUND(AVG(learning_enrollments.progress_percentage)::numeric, 2) AS average_progress, ROUND(AVG(learning_enrollments.best_score)::numeric, 2) AS average_score")
            ->groupBy('counties.id', 'counties.code', 'counties.name', 'counties.logo_path', 'learning_courses.id', 'learning_courses.code', 'learning_courses.title')
            ->orderBy('counties.code')->orderBy('learning_courses.code')->get()
            ->map(fn (Model $row): array => $this->exportRow($row))->values()->all());
    }

    /** @return array<string, mixed> */
    private function courseRow(Model $row): array
    {
        $enrollments = (int) $row->getAttribute('enrollments');
        $completed = (int) $row->getAttribute('completed');
        $suppressed = $this->shouldSuppress($enrollments);

        return ['id' => (string) $row->getAttribute('id'), 'code' => (string) $row->getAttribute('code'), 'title' => (string) $row->getAttribute('title'), 'category' => (string) $row->getAttribute('category'), 'suppressed' => $suppressed, 'enrollments' => $suppressed ? null : $enrollments, 'completed' => $suppressed ? null : $completed, 'completionRate' => $suppressed ? null : $this->percentage($completed, $enrollments), 'averageProgress' => $suppressed ? null : (float) ($row->getAttribute('average_progress') ?? 0), 'averageScore' => $suppressed || $row->getAttribute('average_score') === null ? null : (float) $row->getAttribute('average_score')];
    }

    /** @return array<string, mixed> */
    private function countyRow(Model $row): array
    {
        $county = new County;
        $county->forceFill(['id' => $row->getAttribute('id'), 'code' => $row->getAttribute('code'), 'name' => $row->getAttribute('name'), 'logo_path' => $row->getAttribute('logo_path')]);
        $enrollments = (int) $row->getAttribute('enrollments');
        $completed = (int) $row->getAttribute('completed');
        $suppressed = $this->shouldSuppress($enrollments);

        return ['county' => $county->identityCell(), 'suppressed' => $suppressed, 'enrollments' => $suppressed ? null : $enrollments, 'completed' => $suppressed ? null : $completed, 'completionRate' => $suppressed ? null : $this->percentage($completed, $enrollments), 'averageProgress' => $suppressed ? null : (float) ($row->getAttribute('average_progress') ?? 0), 'averageScore' => $suppressed || $row->getAttribute('average_score') === null ? null : (float) $row->getAttribute('average_score')];
    }

    /** @return array<string, mixed> */
    private function exportRow(Model $row): array
    {
        $enrollments = (int) $row->getAttribute('enrollments');
        $completed = (int) $row->getAttribute('completed');
        $suppressed = $this->shouldSuppress($enrollments);

        return ['county' => ['id' => (string) $row->getAttribute('county_id'), 'code' => (string) $row->getAttribute('county_code'), 'name' => (string) $row->getAttribute('county_name'), 'logoUrl' => $row->getAttribute('logo_path')], 'course_code' => (string) $row->getAttribute('course_code'), 'course_title' => (string) $row->getAttribute('course_title'), 'suppressed' => $suppressed, 'enrollments' => $suppressed ? null : $enrollments, 'completed' => $suppressed ? null : $completed, 'completion_rate' => $suppressed ? null : $this->percentage($completed, $enrollments), 'average_progress' => $suppressed ? null : (float) ($row->getAttribute('average_progress') ?? 0), 'average_score' => $suppressed || $row->getAttribute('average_score') === null ? null : (float) $row->getAttribute('average_score')];
    }

    /**
     * @param  list<string>  $countyIds
     * @param  array<string, mixed>  $filters
     * @return Builder<LearningEnrollment>
     */
    private function filteredEnrollments(array $countyIds, array $filters): Builder
    {
        return LearningEnrollment::query()->whereIn('learning_enrollments.county_id', $countyIds)
            ->when($filters['county_id'] ?? null, fn (Builder $query, string $id) => $query->where('learning_enrollments.county_id', $id))
            ->when($filters['course_id'] ?? null, fn (Builder $query, string $id) => $query->where('learning_enrollments.learning_course_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('learning_enrollments.status', $status))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('learning_enrollments.enrolled_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('learning_enrollments.enrolled_at', '<=', $to))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('course', fn (Builder $course) => $course->where('code', 'ilike', "%{$search}%")->orWhere('title', 'ilike', "%{$search}%")));
    }

    /**
     * @param  Collection<int, County>  $counties
     * @return list<string>
     */
    private function countyIds(Collection $counties): array
    {
        return array_values($counties->pluck('id')->map(fn (mixed $id): string => (string) $id)->values()->all());
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

    private function shouldSuppress(int $cellSize): bool
    {
        return $cellSize > 0 && $cellSize < $this->minimumCellSize();
    }

    private function minimumCellSize(): int
    {
        return max(2, min(100, (int) config('analytics.minimum_aggregate_cell_size', 5)));
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

        return ['rows' => array_values($items->slice(($currentPage - 1) * $perPage, $perPage)->values()->all()), 'pagination' => ['currentPage' => $currentPage, 'lastPage' => $lastPage, 'perPage' => $perPage, 'total' => $total, 'pageName' => $pageName]];
    }
}
