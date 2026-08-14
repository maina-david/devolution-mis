<?php

namespace App\Actions;

use App\Models\LearningCohort;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TransitionLearningCohort
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{transition:string, rationale:string} $attributes */
    public function handle(LearningCohort $cohort, User $actor, array $attributes): LearningCohort
    {
        return DB::transaction(function () use ($cohort, $actor, $attributes): LearningCohort {
            $lockedCohort = LearningCohort::query()->whereKey($cohort->id)->lockForUpdate()->firstOrFail();
            if (! $actor->programmeRole()->hasNationalScope()) {
                $hasCountyAccess = $lockedCohort->county_id !== null
                    && ($actor->county_id === $lockedCohort->county_id || $actor->assignedCounties()->whereKey($lockedCohort->county_id)->exists());
                abort_unless($hasCountyAccess, 403);
            }

            $nextStatus = match ($attributes['transition']) {
                'open' => $lockedCohort->status === 'draft' ? 'open' : null,
                'start' => $lockedCohort->status === 'open' ? 'active' : null,
                'complete' => $lockedCohort->status === 'active' ? 'completed' : null,
                'cancel' => in_array($lockedCohort->status, ['draft', 'open', 'active'], true) ? 'cancelled' : null,
                default => null,
            };
            abort_unless($nextStatus !== null, 409, __('learning.cohort.errors.transition_not_permitted'));
            if ($nextStatus === 'active') {
                abort_unless($lockedCohort->memberships()->exists(), 409, __('learning.cohort.errors.learner_required_to_start'));
                abort_unless(now()->greaterThanOrEqualTo($lockedCohort->starts_at), 409, __('learning.cohort.errors.before_scheduled_start'));
            }
            if ($nextStatus === 'completed') {
                abort_unless(now()->greaterThanOrEqualTo($lockedCohort->ends_at), 409, __('learning.cohort.errors.before_scheduled_end'));
            }

            $lockedCohort->update(['status' => $nextStatus, 'transitioned_by' => $actor->id, 'transitioned_at' => now()]);
            $this->auditLogger->record($actor, $lockedCohort, 'learning.cohort.transitioned', __('learning.cohort.audit.transitioned', ['cohort' => $lockedCohort->code, 'status' => __("learning.cohort.statuses.{$nextStatus}")]), $lockedCohort->county_id, ['transition' => $attributes['transition'], 'rationale' => $attributes['rationale']]);

            return $lockedCohort->refresh();
        });
    }
}
