<?php

namespace App\Actions;

use App\Models\UatFinding;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UatAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionUatFinding
{
    public function __construct(private UatAccess $access, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(UatFinding $finding, User $actor, array $attributes): UatFinding
    {
        return DB::transaction(function () use ($finding, $actor, $attributes): UatFinding {
            $finding = UatFinding::query()->with('execution.scenario.campaign')->lockForUpdate()->findOrFail($finding->id);
            $this->access->authorize($actor, $finding->execution->scenario->campaign);
            $action = $attributes['action'];

            if ($action === 'resolve') {
                if (! in_array($finding->status, ['open', 'reopened'], true) || $finding->owner_id !== $actor->id || $finding->raised_by === $actor->id) {
                    throw ValidationException::withMessages(['action' => __('change-readiness.uat_errors.resolve_separation')]);
                }
                $finding->update(['status' => 'resolved', 'resolution' => $attributes['resolution'], 'resolved_by' => $actor->id, 'resolved_at' => now(), 'verified_by' => null, 'verified_at' => null]);
            } elseif ($action === 'verify') {
                if ($finding->status !== 'resolved' || in_array($actor->id, [$finding->raised_by, $finding->resolved_by], true)) {
                    throw ValidationException::withMessages(['action' => __('change-readiness.uat_errors.verify_separation')]);
                }
                $finding->update(['status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now()]);
            } else {
                if (! in_array($finding->status, ['resolved', 'verified'], true) || $finding->resolved_by === $actor->id) {
                    throw ValidationException::withMessages(['action' => __('change-readiness.uat_errors.reopen_separation')]);
                }
                $finding->update(['status' => 'reopened', 'resolution' => $attributes['resolution'], 'verified_by' => null, 'verified_at' => null]);
            }

            $this->auditLogger->record($actor, $finding, "change-readiness.uat.finding.{$action}", "UAT finding {$finding->id} transitioned to {$finding->status}.");

            return $finding;
        });
    }
}
