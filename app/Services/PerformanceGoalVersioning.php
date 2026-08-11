<?php

namespace App\Services;

use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalVersion;
use App\Models\User;
use App\Support\CanonicalJson;
use Carbon\CarbonInterface;

class PerformanceGoalVersioning
{
    public function __construct(private CanonicalJson $canonicalJson) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function normalize(array $attributes): array
    {
        return [
            'code' => trim((string) $attributes['code']),
            'title' => trim((string) $attributes['title']),
            'description' => trim((string) $attributes['description']),
            'kpi' => trim((string) $attributes['kpi']),
            'unit_of_measure' => trim((string) $attributes['unit_of_measure']),
            'baseline_value' => $attributes['baseline_value'] === null || $attributes['baseline_value'] === '' ? null : number_format((float) $attributes['baseline_value'], 4, '.', ''),
            'target_value' => number_format((float) $attributes['target_value'], 4, '.', ''),
            'weight' => number_format((float) $attributes['weight'], 2, '.', ''),
        ];
    }

    /** @return array<string, mixed> */
    public function currentSnapshot(PerformanceGoal $goal): array
    {
        return $this->normalize($goal->only(['code', 'title', 'description', 'kpi', 'unit_of_measure', 'baseline_value', 'target_value', 'weight']));
    }

    /** @param array<string, mixed> $snapshot */
    public function create(PerformanceGoal $goal, User $actor, array $snapshot, ?CarbonInterface $effectiveAt = null): PerformanceGoalVersion
    {
        $normalized = $this->normalize($snapshot);
        $latest = $goal->versions()->first();
        $version = $latest === null ? 1 : $latest->version + 1;
        $effectiveAt ??= now();
        $payload = ['performance_goal_id' => $goal->id, 'version' => $version, 'definition_snapshot' => $normalized, 'predecessor_checksum' => $latest?->version_checksum, 'created_by' => $actor->id, 'effective_at' => $effectiveAt->toIso8601String()];

        return $goal->versions()->create([...$payload, 'effective_at' => $effectiveAt, 'version_checksum' => $this->canonicalJson->checksum($payload)]);
    }
}
