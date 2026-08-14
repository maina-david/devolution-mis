<?php

namespace App\Services;

use App\Models\DevolutionProject;
use App\Models\ProjectMilestone;
use Illuminate\Validation\ValidationException;

class ProjectDependencyGraph
{
    /** @param list<string> $dependencyIds */
    public function validate(DevolutionProject $project, ?ProjectMilestone $milestone, array $dependencyIds): void
    {
        $dependencyIds = array_values(array_unique($dependencyIds));
        if ($milestone !== null && in_array($milestone->id, $dependencyIds, true)) {
            throw ValidationException::withMessages(['dependencies' => __('projects.errors.dependency_self_reference')]);
        }

        $validDependencyCount = $project->milestones()->whereKey($dependencyIds)->count();
        if ($validDependencyCount !== count($dependencyIds)) {
            throw ValidationException::withMessages(['dependencies' => __('projects.errors.dependency_outside_project')]);
        }
        if ($milestone === null) {
            return;
        }

        $graph = $project->milestones()->get(['id', 'dependencies'])->mapWithKeys(
            fn (ProjectMilestone $item): array => [$item->id => $item->id === $milestone->id ? $dependencyIds : $this->dependencyIds($item)]
        )->all();
        if ($this->containsCycle($graph)) {
            throw ValidationException::withMessages(['dependencies' => __('projects.errors.dependency_cycle')]);
        }
    }

    /** @return list<string> */
    private function dependencyIds(ProjectMilestone $milestone): array
    {
        $dependencies = $milestone->getAttribute('dependencies');
        if (! is_array($dependencies)) {
            return [];
        }

        return array_values(array_filter($dependencies, fn (mixed $id): bool => is_string($id)));
    }

    /** @param array<string, list<string>> $graph */
    private function containsCycle(array $graph): bool
    {
        $visiting = [];
        $visited = [];
        foreach (array_keys($graph) as $milestoneId) {
            if ($this->visit($milestoneId, $graph, $visiting, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  array<string, true>  $visiting
     * @param  array<string, true>  $visited
     */
    private function visit(string $milestoneId, array $graph, array &$visiting, array &$visited): bool
    {
        if (isset($visiting[$milestoneId])) {
            return true;
        }
        if (isset($visited[$milestoneId])) {
            return false;
        }

        $visiting[$milestoneId] = true;
        foreach ($graph[$milestoneId] ?? [] as $dependencyId) {
            if (isset($graph[$dependencyId]) && $this->visit($dependencyId, $graph, $visiting, $visited)) {
                return true;
            }
        }
        unset($visiting[$milestoneId]);
        $visited[$milestoneId] = true;

        return false;
    }
}
