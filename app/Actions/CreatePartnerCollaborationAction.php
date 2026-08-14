<?php

namespace App\Actions;

use App\Models\County;
use App\Models\PartnerCollaborationAction;
use App\Models\PartnerCollaborationPlan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreatePartnerCollaborationAction
{
    public function __construct(
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PartnerCollaborationPlan $plan, User $actor, array $attributes): PartnerCollaborationAction
    {
        $county = County::query()->where('id', $attributes['county_id'])->firstOrFail();
        abort_unless($actor->canAccessCounty($county), 403, __('partner-coordination.lifecycle.errors.county_outside_scope'));

        return DB::transaction(function () use ($plan, $actor, $attributes, $county): PartnerCollaborationAction {
            $lockedPlan = PartnerCollaborationPlan::query()->with('partner.counties')->lockForUpdate()->findOrFail($plan->id);
            abort_unless($lockedPlan->status === 'active', 409, __('partner-coordination.lifecycle.errors.active_plan_required'));
            abort_unless($lockedPlan->partner->counties->contains('id', $county->id), 403, __('partner-coordination.lifecycle.errors.county_outside_plan'));

            $dueOn = CarbonImmutable::parse($attributes['due_on']);
            abort_unless($dueOn->betweenIncluded($lockedPlan->starts_on, $lockedPlan->ends_on), 422, __('partner-coordination.lifecycle.errors.action_due_within_plan'));

            $owner = User::query()->where('id', $attributes['accountable_user_id'])->firstOrFail();
            abort_unless($owner->canAccessCounty($county), 422, __('partner-coordination.lifecycle.errors.owner_outside_county'));

            $organizationId = is_string($attributes['accountable_organization_id'] ?? null) ? $attributes['accountable_organization_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forPartnerCollaborationAction($county->id, $organizationId, now());
            $action = $lockedPlan->actions()->create([
                ...$attributes,
                'reference_data_release_id' => $referenceDataRelease->id,
                'created_by' => $actor->id,
                'status' => 'open',
            ]);
            $this->auditLogger->record($actor, $action, 'partner.collaboration_action.created', __('partner-coordination.lifecycle.audit.action_created', ['code' => $action->code]), $county->id, [
                'accountable_user_id' => $owner->id,
                'accountable_organization_id' => $organizationId,
                'reference_data_release_id' => $referenceDataRelease->id,
                'reference_data_release_version' => $referenceDataRelease->version,
                'reference_data_release_checksum' => $referenceDataRelease->checksum,
            ]);

            return $action;
        });
    }
}
