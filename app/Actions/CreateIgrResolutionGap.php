<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\IgrResolution;
use App\Models\IgrResolutionGap;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\IgrResolutionAccess;
use Illuminate\Support\Facades\DB;

class CreateIgrResolutionGap
{
    public function __construct(
        private AuditLogger $auditLogger,
        private IgrResolutionAccess $resolutionAccess,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(IgrResolution $resolution, User $actor, array $attributes): IgrResolutionGap
    {
        abort_unless($actor->can(ProgrammePermission::UpdateIgrResolutions->value) || $actor->can(ProgrammePermission::ManageIgrResolutions->value), 403, __('igr.errors.gap_create_unauthorized'));
        abort_unless($this->resolutionAccess->allows($actor, $resolution), 403, __('igr.errors.resolution_outside_scope'));
        abort_unless(in_array($resolution->status, ['open', 'in_progress'], true), 409, __('igr.errors.gap_implementation_inactive'));
        abort_unless($resolution->assignments()->where('user_id', $attributes['owner_user_id'])->exists(), 422, __('igr.errors.gap_owner_responsible'));
        if (! empty($attributes['county_id'])) {
            $county = County::query()->whereKey((string) $attributes['county_id'])->firstOrFail();
            abort_unless($actor->canAccessCounty($county), 403, __('igr.errors.county_outside_scope'));
            abort_unless($resolution->assignments()->where('county_id', $county->id)->exists(), 422, __('igr.errors.gap_county_assignment'));
        }

        $gap = DB::transaction(function () use ($resolution, $actor, $attributes): IgrResolutionGap {
            $locked = IgrResolution::query()->whereKey($resolution->id)->lockForUpdate()->firstOrFail();
            $gap = $locked->gaps()->create([...$attributes, 'reported_by' => $actor->id]);
            $locked->update(['implementation_gap' => $gap->title]);

            return $gap;
        }, attempts: 3);
        $countyId = isset($attributes['county_id']) ? (string) $attributes['county_id'] : null;
        $this->auditLogger->record($actor, $gap, 'igr.resolution.gap_reported', __('igr.audit.gap_reported', ['number' => $resolution->resolution_number]), $countyId, ['severity' => $gap->severity, 'due_on' => $gap->due_on->toDateString()]);
        $gap->owner->notifyNow(ProgrammeAlert::translated('igr.notifications.gap_assigned_title', 'igr.notifications.gap_assigned_message', 'igr-resolutions', messageParameters: ['title' => $gap->title, 'number' => $resolution->resolution_number]));

        return $gap->refresh();
    }
}
