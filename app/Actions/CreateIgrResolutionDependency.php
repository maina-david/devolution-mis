<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IgrResolution;
use App\Models\IgrResolutionDependency;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateIgrResolutionDependency
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(IgrResolution $dependent, IgrResolution $prerequisite, User $actor, array $attributes): IgrResolutionDependency
    {
        abort_unless($actor->can(ProgrammePermission::ManageIgrResolutions->value), 403);
        abort_if($dependent->is($prerequisite), 422, 'A resolution cannot depend on itself.');
        abort_if(in_array($dependent->status, ['closure_review', 'closed'], true), 409, 'Dependencies cannot be added after closure review starts.');

        $dependency = DB::transaction(function () use ($dependent, $prerequisite, $actor, $attributes): IgrResolutionDependency {
            $locked = IgrResolution::query()->whereKey($dependent->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->dependencies()->where('prerequisite_resolution_id', $prerequisite->id)->exists(), 422, 'This dependency already exists.');
            if ($this->createsCycle($locked, $prerequisite)) {
                throw ValidationException::withMessages(['prerequisite_resolution_id' => 'This dependency would create a circular resolution chain.']);
            }

            return $locked->dependencies()->create(['prerequisite_resolution_id' => $prerequisite->id, 'dependency_type' => $attributes['dependency_type'], 'rationale' => trim((string) $attributes['rationale']), 'created_by' => $actor->id]);
        }, attempts: 3);

        $this->auditLogger->record($actor, $dependency, 'igr.resolution.dependency_created', "{$dependent->resolution_number} linked to prerequisite {$prerequisite->resolution_number}.", metadata: ['dependent_resolution_id' => $dependent->id, 'prerequisite_resolution_id' => $prerequisite->id, 'dependency_type' => $dependency->dependency_type]);

        return $dependency;
    }

    private function createsCycle(IgrResolution $dependent, IgrResolution $prerequisite): bool
    {
        $frontier = [$prerequisite->id];
        $visited = [];
        while ($frontier !== []) {
            $current = array_pop($frontier);
            if ($current === $dependent->id) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            array_push($frontier, ...IgrResolutionDependency::query()->where('dependent_resolution_id', $current)->pluck('prerequisite_resolution_id')->all());
        }

        return false;
    }
}
