<?php

namespace App\Services;

use App\Models\AssessmentResultPublication;

class AssessmentBenchmarkService
{
    /**
     * @param  list<string>|null  $countyIds
     * @return list<array<string, mixed>>
     */
    public function rankings(string $cycleId, ?array $countyIds = null): array
    {
        $results = AssessmentResultPublication::query()
            ->where('assessment_cycle_id', $cycleId)
            ->when($countyIds !== null, fn ($query) => $query->whereIn('county_id', $countyIds))
            ->with('county:id,name,code,logo_path')
            ->orderByDesc('score')
            ->orderBy('published_at')
            ->get();
        $count = $results->count();
        $previousScore = null;
        $rank = 0;

        return array_values($results->values()->map(function (AssessmentResultPublication $publication, int $index) use ($count, &$previousScore, &$rank): array {
            if ($previousScore !== $publication->score) {
                $rank = $index + 1;
                $previousScore = $publication->score;
            }

            return ['publicationId' => $publication->id, 'assessmentId' => $publication->assessment_id, 'countyId' => $publication->county_id, 'county' => $publication->county->name, 'countyIdentity' => $publication->county->identityCell(), 'countyCode' => $publication->county->code, 'score' => $publication->score, 'performanceBand' => $publication->performance_band, 'rank' => $rank, 'percentile' => $count <= 1 ? 100.0 : round((($count - $rank) / ($count - 1)) * 100, 2), 'functionProfile' => $publication->function_profile, 'checksum' => $publication->checksum];
        })->all());
    }
}
