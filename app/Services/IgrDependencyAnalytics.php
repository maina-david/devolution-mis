<?php

namespace App\Services;

use App\Models\IgrResolution;
use App\Models\IgrResolutionDependency;
use Illuminate\Database\Eloquent\Collection;

class IgrDependencyAnalytics
{
    /**
     * @param  Collection<int, IgrResolution>  $resolutions
     * @return array{summary: array{totalLinks: int, blockingLinks: int, unresolvedBlockingLinks: int, blockedResolutions: int, longestPathDepth: int}, criticalPaths: array<int, array{depth: int, blocked: bool, nodes: array<int, array{id: string, number: string, title: string, status: string, dueOn: string}>}>, bottlenecks: array<int, array{id: string, number: string, title: string, status: string, dependentCount: int}>}
     */
    public function report(Collection $resolutions): array
    {
        $byId = $resolutions->keyBy('id');
        $dependencies = $resolutions->flatMap(fn (IgrResolution $resolution) => $resolution->dependencies)
            ->filter(fn (IgrResolutionDependency $dependency): bool => $byId->has($dependency->dependent_resolution_id) && $byId->has($dependency->prerequisite_resolution_id))
            ->values();
        $prerequisites = [];
        foreach ($dependencies as $dependency) {
            $prerequisites[$dependency->dependent_resolution_id][] = $dependency;
        }
        $blockedIds = $dependencies
            ->filter(fn (IgrResolutionDependency $dependency): bool => $dependency->dependency_type === 'blocks' && $byId->get($dependency->prerequisite_resolution_id)?->status !== 'closed')
            ->pluck('dependent_resolution_id')->unique()->values();
        $paths = [];
        foreach ($resolutions as $resolution) {
            $nodeIds = $this->longestPrerequisitePath($resolution->id, $prerequisites, []);
            if (count($nodeIds) <= 1) {
                continue;
            }
            $nodes = [];
            foreach ($nodeIds as $id) {
                $node = $byId->get($id);
                $nodes[] = ['id' => $node->id, 'number' => $node->resolution_number, 'title' => $node->title, 'status' => $node->status, 'dueOn' => $node->due_on->toDateString()];
            }
            $paths[] = [
                'depth' => max(0, count($nodeIds) - 1),
                'blocked' => $blockedIds->contains($resolution->id),
                'nodes' => $nodes,
            ];
        }
        usort($paths, fn (array $left, array $right): int => $right['depth'] <=> $left['depth']);
        $paths = array_slice($paths, 0, 10);
        $dependentCounts = $dependencies->countBy('prerequisite_resolution_id');
        $bottlenecks = $dependentCounts->sortDesc()->take(10)->map(function (int $count, string $id) use ($byId): array {
            $resolution = $byId->get($id);

            return ['id' => $resolution->id, 'number' => $resolution->resolution_number, 'title' => $resolution->title, 'status' => $resolution->status, 'dependentCount' => $count];
        })->values();

        return [
            'summary' => [
                'totalLinks' => $dependencies->count(),
                'blockingLinks' => $dependencies->where('dependency_type', 'blocks')->count(),
                'unresolvedBlockingLinks' => $dependencies->filter(fn (IgrResolutionDependency $dependency): bool => $dependency->dependency_type === 'blocks' && $byId->get($dependency->prerequisite_resolution_id)?->status !== 'closed')->count(),
                'blockedResolutions' => $blockedIds->count(),
                'longestPathDepth' => (int) (collect($paths)->max('depth') ?? 0),
            ],
            'criticalPaths' => $paths,
            'bottlenecks' => $bottlenecks->values()->all(),
        ];
    }

    /**
     * @param  array<string, list<IgrResolutionDependency>>  $prerequisites
     * @param  list<string>  $visiting
     * @return list<string>
     */
    private function longestPrerequisitePath(string $resolutionId, array $prerequisites, array $visiting): array
    {
        if (in_array($resolutionId, $visiting, true)) {
            return [$resolutionId];
        }

        $incoming = $prerequisites[$resolutionId] ?? [];
        if ($incoming === []) {
            return [$resolutionId];
        }
        $paths = array_map(fn (IgrResolutionDependency $dependency): array => [...$this->longestPrerequisitePath($dependency->prerequisite_resolution_id, $prerequisites, [...$visiting, $resolutionId]), $resolutionId], $incoming);
        usort($paths, fn (array $left, array $right): int => count($right) <=> count($left));

        return $paths[0];
    }
}
