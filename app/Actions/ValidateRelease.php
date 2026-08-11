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
            abort_unless($release->status === 'deployed', 409, 'Only a deployed release can be validated.');
            if ($release->deployed_by === $actor->id) {
                throw new AuthorizationException('Separation of duties prevents the deployer from validating the release.');
            }
            $release->update(['validated_by' => $actor->id, 'validated_at' => now(), 'status' => 'validated', 'notes' => trim(($release->notes ? $release->notes."\n" : '').'Validation evidence: '.$evidence)]);
            $this->auditLogger->record($actor, $release, 'operations.release.validated', "Release {$release->version} independently validated.", null, ['evidence' => $evidence]);

            return $release->refresh();
        });
    }
}
