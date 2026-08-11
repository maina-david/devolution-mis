<?php

namespace App\Actions;

use App\Models\LearningEnrollment;
use App\Models\LearningOfflinePackage;
use App\Models\LearningOfflineSync;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LearningOfflineSyncReconciler;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class SubmitLearningOfflineSync
{
    public function __construct(private LearningOfflineSyncReconciler $reconciler, private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $payload */
    public function handle(LearningEnrollment $enrollment, User $actor, array $payload): LearningOfflineSync
    {
        abort_unless($enrollment->user_id === $actor->id, 403);
        $package = LearningOfflinePackage::query()->findOrFail((string) $payload['package_id']);

        return DB::transaction(function () use ($enrollment, $package, $actor, $payload): LearningOfflineSync {
            $lockedEnrollment = LearningEnrollment::query()->whereKey($enrollment->id)->lockForUpdate()->sole();
            $normalized = $this->reconciler->validateAndNormalize($lockedEnrollment, $package, $payload);
            $payloadChecksum = $this->canonicalJson->checksum($normalized);
            $existing = LearningOfflineSync::query()->where('learning_enrollment_id', $lockedEnrollment->id)->where('client_sync_id', $normalized['client_sync_id'])->first();
            if ($existing instanceof LearningOfflineSync) {
                abort_unless(hash_equals($existing->payload_checksum, $payloadChecksum), 409, 'The synchronization identifier was already used with different activity.');

                return $existing;
            }

            $sync = LearningOfflineSync::query()->create([
                'learning_offline_package_id' => $package->id,
                'learning_enrollment_id' => $lockedEnrollment->id,
                'county_id' => $lockedEnrollment->county_id,
                'submitted_by' => $actor->id,
                'submitted_by_name' => $actor->name,
                'client_sync_id' => $normalized['client_sync_id'],
                'device_id' => $normalized['device_id'],
                'schema_version' => $normalized['schema'],
                'payload' => $normalized,
                'payload_checksum' => $payloadChecksum,
                'base_progress_checksum' => $this->reconciler->progressChecksum($lockedEnrollment),
                'event_count' => count($normalized['events']),
                'submitted_at' => now(),
            ]);
            $this->auditLogger->record($actor, $sync, 'learning.offline-sync.submitted', "Offline learning activity submitted for {$lockedEnrollment->course->code}.", $lockedEnrollment->county_id, ['package_id' => $package->id, 'package_version' => $package->package_version, 'payload_checksum' => $payloadChecksum, 'event_count' => $sync->event_count]);

            return $sync;
        });
    }
}
