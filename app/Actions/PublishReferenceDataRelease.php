<?php

namespace App\Actions;

use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class PublishReferenceDataRelease
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    /** @param array{approval_reference: string, effective_from: string} $attributes */
    public function handle(ReferenceDataRelease $release, User $actor, array $attributes): ReferenceDataRelease
    {
        return DB::transaction(function () use ($release, $actor, $attributes): ReferenceDataRelease {
            $release = ReferenceDataRelease::query()->lockForUpdate()->findOrFail($release->id);
            abort_unless($release->status === 'submitted', 409, __('reference-data.governance.errors.release_not_submitted'));
            if ($release->submitted_by === $actor->id) {
                throw new AuthorizationException(__('reference-data.governance.errors.release_submitter_separation'));
            }
            abort_unless(hash_equals($release->checksum, $this->canonicalJson->checksum($release->snapshot)), 409, __('reference-data.governance.errors.release_checksum_failed'));
            abort_if(ReferenceDataRelease::query()->where('status', 'published')->where('effective_from', '>=', $attributes['effective_from'])->exists(), 409, __('reference-data.governance.errors.release_effective_date_order'));

            $release->update([
                'approved_by' => $actor->id,
                'status' => 'published',
                'approval_reference' => $attributes['approval_reference'],
                'effective_from' => $attributes['effective_from'],
                'published_at' => now(),
            ]);
            $this->auditLogger->record($actor, $release, 'reference.release.published', __('reference-data.governance.audit.release_published', ['version' => $release->version]), metadata: ['checksum' => $release->checksum, 'approval_reference' => $attributes['approval_reference'], 'effective_from' => $attributes['effective_from']]);

            return $release->refresh();
        });
    }
}
