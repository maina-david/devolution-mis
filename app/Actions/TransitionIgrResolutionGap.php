<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IgrResolution;
use App\Models\IgrResolutionGap;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IgrResolutionAccess;
use Illuminate\Support\Facades\DB;

class TransitionIgrResolutionGap
{
    public function __construct(
        private AuditLogger $auditLogger,
        private IgrResolutionAccess $resolutionAccess,
    ) {}

    public function handle(IgrResolutionGap $gap, User $actor, string $transition, string $rationale): IgrResolutionGap
    {
        $allowed = [
            'open' => ['start_mitigation' => 'mitigating', 'resolve' => 'resolved'],
            'mitigating' => ['resolve' => 'resolved'],
            'resolved' => ['accept' => 'accepted', 'reject' => 'mitigating'],
        ];
        $target = $allowed[$gap->status][$transition] ?? null;
        abort_unless($this->resolutionAccess->allows($actor, $gap->resolution), 403, __('igr.errors.resolution_outside_scope'));
        abort_unless($gap->county === null || $actor->canAccessCounty($gap->county), 403, __('igr.errors.gap_outside_scope'));
        abort_unless(is_string($target), 409, __('igr.errors.gap_transition_unavailable'));
        if (in_array($transition, ['accept', 'reject'], true)) {
            abort_unless($actor->can(ProgrammePermission::CloseIgrResolutions->value), 403);
            abort_if($actor->id === $gap->reported_by || $actor->id === $gap->resolved_by, 403, __('igr.errors.gap_independent_acceptance'));
        } else {
            abort_unless($actor->id === $gap->owner_user_id || $actor->can(ProgrammePermission::UpdateIgrResolutions->value) || $actor->can(ProgrammePermission::ManageIgrResolutions->value), 403);
        }

        $updated = DB::transaction(function () use ($gap, $actor, $transition, $rationale, $target): IgrResolutionGap {
            $locked = IgrResolutionGap::query()->whereKey($gap->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === $gap->status, 409, __('igr.errors.gap_concurrent_change'));
            $attributes = ['status' => $target];
            if ($transition === 'start_mitigation') {
                $attributes += ['mitigation_plan' => trim($rationale), 'mitigation_started_at' => now()];
            } elseif ($transition === 'resolve') {
                $attributes += ['resolution_note' => trim($rationale), 'resolved_by' => $actor->id, 'resolved_at' => now()];
            } elseif ($transition === 'accept') {
                $attributes += ['accepted_by' => $actor->id, 'accepted_at' => now()];
            } else {
                $attributes += ['mitigation_plan' => trim($rationale), 'resolved_by' => null, 'resolved_at' => null];
            }
            $locked->update($attributes);
            $resolution = IgrResolution::query()->whereKey($locked->igr_resolution_id)->lockForUpdate()->firstOrFail();
            $resolution->update(['implementation_gap' => $resolution->gaps()->whereNotIn('status', ['accepted'])->orderByDesc('severity')->value('title')]);

            return $locked->refresh();
        }, attempts: 3);
        $this->auditLogger->record($actor, $updated, 'igr.resolution.gap_transitioned', __('igr.audit.gap_transitioned', ['status' => __('igr.gap_statuses.'.$updated->status)]), $updated->county_id, ['transition' => $transition, 'from_status' => $gap->status, 'to_status' => $updated->status]);

        return $updated;
    }
}
