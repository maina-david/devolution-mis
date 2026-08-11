<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\IgrResolution;
use App\Models\IgrResolutionGap;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateIgrResolutionGap
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(IgrResolution $resolution, User $actor, array $attributes): IgrResolutionGap
    {
        abort_unless($actor->can(ProgrammePermission::UpdateIgrResolutions->value) || $actor->can(ProgrammePermission::ManageIgrResolutions->value), 403);
        abort_unless(in_array($resolution->status, ['open', 'in_progress'], true), 409, 'Gaps can be reported only while implementation is active.');
        abort_unless($resolution->assignments()->where('user_id', $attributes['owner_user_id'])->exists(), 422, 'The gap owner must be a responsible party for this resolution.');
        if (! empty($attributes['county_id'])) {
            $county = County::query()->whereKey((string) $attributes['county_id'])->firstOrFail();
            abort_unless($actor->canAccessCounty($county), 403);
            abort_unless($resolution->assignments()->where('county_id', $county->id)->exists(), 422, 'The affected county must be assigned to this resolution.');
        }

        $gap = DB::transaction(function () use ($resolution, $actor, $attributes): IgrResolutionGap {
            $locked = IgrResolution::query()->whereKey($resolution->id)->lockForUpdate()->firstOrFail();
            $gap = $locked->gaps()->create([...$attributes, 'reported_by' => $actor->id]);
            $locked->update(['implementation_gap' => $gap->title]);

            return $gap;
        }, attempts: 3);
        $countyId = isset($attributes['county_id']) ? (string) $attributes['county_id'] : null;
        $this->auditLogger->record($actor, $gap, 'igr.resolution.gap_reported', "Implementation gap reported for {$resolution->resolution_number}.", $countyId, ['severity' => $gap->severity, 'due_on' => $gap->due_on->toDateString()]);
        $gap->owner->notifyNow(new ProgrammeAlert('IGR implementation gap assigned', "You own {$gap->title} for {$resolution->resolution_number}.", 'igr-resolutions'));

        return $gap->refresh();
    }
}
