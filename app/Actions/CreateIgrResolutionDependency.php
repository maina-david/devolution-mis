<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IgrResolution;
use App\Models\IgrResolutionDependency;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IgrResolutionAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateIgrResolutionDependency
{
    public function __construct(
        private AuditLogger $auditLogger,
        private IgrResolutionAccess $resolutionAccess,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(IgrResolution $dependent, IgrResolution $prerequisite, User $actor, array $attributes): IgrResolutionDependency
    {
        abort_unless($actor->can(ProgrammePermission::ManageIgrResolutions->value), 403, __('igr.errors.dependency_create_unauthorized'));
        abort_unless($this->resolutionAccess->allows($actor, $dependent) && $this->resolutionAccess->allows($actor, $prerequisite), 403, __('igr.errors.resolution_outside_scope'));
        abort_if($dependent->is($prerequisite), 422, __('igr.errors.dependency_self_reference'));
        abort_if(in_array($dependent->status, ['closure_review', 'closed'], true), 409, __('igr.errors.dependency_after_closure_review'));

        $dependency = DB::transaction(function () use ($dependent, $prerequisite, $actor, $attributes): IgrResolutionDependency {
            $locked = IgrResolution::query()->whereKey($dependent->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->dependencies()->where('prerequisite_resolution_id', $prerequisite->id)->exists(), 422, __('igr.errors.dependency_exists'));
            if ($this->createsCycle($locked, $prerequisite)) {
                throw ValidationException::withMessages(['prerequisite_resolution_id' => __('igr.errors.dependency_cycle')]);
            }

            return $locked->dependencies()->create(['prerequisite_resolution_id' => $prerequisite->id, 'dependency_type' => $attributes['dependency_type'], 'rationale' => trim((string) $attributes['rationale']), 'created_by' => $actor->id]);
        }, attempts: 3);

        $this->auditLogger->record($actor, $dependency, 'igr.resolution.dependency_created', __('igr.audit.dependency_created', ['dependent' => $dependent->resolution_number, 'prerequisite' => $prerequisite->resolution_number]), metadata: ['dependent_resolution_id' => $dependent->id, 'prerequisite_resolution_id' => $prerequisite->id, 'dependency_type' => $dependency->dependency_type]);

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
