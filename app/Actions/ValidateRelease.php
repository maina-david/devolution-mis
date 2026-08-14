<?php

namespace App\Actions;

use App\Models\ReleaseRecord;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ValidateRelease
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(ReleaseRecord $release, User $actor, string $evidence): ReleaseRecord
    {
        return DB::transaction(function () use ($release, $actor, $evidence): ReleaseRecord {
            $release = ReleaseRecord::query()->lockForUpdate()->findOrFail($release->id);
            abort_unless($release->status === 'deployed', 409, __('operations.release.errors.deployed_required'));
            if ($release->deployed_by === $actor->id) {
                throw new AuthorizationException(__('operations.release.errors.independent_validator'));
            }
            $release->update(['validated_by' => $actor->id, 'validated_at' => now(), 'status' => 'validated', 'notes' => trim(($release->notes ? $release->notes."\n" : '').__('operations.release.validation_evidence', ['evidence' => $evidence]))]);
            $this->auditLogger->record($actor, $release, 'operations.release.validated', __('operations.release.audit.validated', ['version' => $release->version]), null, ['evidence' => $evidence]);

            return $release->refresh();
        });
    }
}
