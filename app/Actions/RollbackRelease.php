<?php

namespace App\Actions;

use App\Models\ReleaseRecord;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class RollbackRelease
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{rollback_to_version:string,reason:string} $attributes */
    public function handle(ReleaseRecord $release, User $actor, array $attributes): ReleaseRecord
    {
        return DB::transaction(function () use ($release, $actor, $attributes): ReleaseRecord {
            $release = ReleaseRecord::query()->lockForUpdate()->findOrFail($release->id);
            abort_unless(in_array($release->status, ['deployed', 'validated'], true), 409, 'Only an active release can be rolled back.');
            abort_unless(ReleaseRecord::query()->where('environment', $release->environment)->where('version', $attributes['rollback_to_version'])->where('status', 'validated')->exists(), 409, 'Rollback target must be a previously validated release in the same environment.');
            $release->update(['rolled_back_by' => $actor->id, 'rolled_back_at' => now(), 'rollback_to_version' => $attributes['rollback_to_version'], 'status' => 'rolled_back', 'notes' => trim(($release->notes ? $release->notes."\n" : '').'Rollback reason: '.$attributes['reason'])]);
            $this->auditLogger->record($actor, $release, 'operations.release.rolled_back', "Release {$release->version} rolled back to {$attributes['rollback_to_version']}.", null, ['reason' => $attributes['reason']]);

            return $release->refresh();
        });
    }
}
