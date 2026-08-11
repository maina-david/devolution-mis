<?php

namespace App\Actions;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProfilePhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UpdateProfilePhoto
{
    public function __construct(private ProfilePhotoProcessor $processor, private AuditLogger $auditLogger) {}

    public function store(User $user, UploadedFile $photo): void
    {
        $processed = $this->processor->process($photo);
        $path = "profile-photos/{$user->id}/".Str::uuid().'.webp';

        if (! Storage::disk('local')->put($path, $processed['content'])) {
            throw new RuntimeException('The profile photo could not be stored.');
        }

        $storedContent = Storage::disk('local')->get($path);
        if (! hash_equals($processed['checksum'], hash('sha256', $storedContent))) {
            Storage::disk('local')->delete($path);
            throw new RuntimeException('The stored profile photo failed integrity verification.');
        }

        $previousPath = $user->profile_photo_path;
        $previousChecksum = $user->profile_photo_checksum;

        try {
            DB::transaction(function () use ($user, $path, $processed, $previousChecksum): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $lockedUser->update([
                    'profile_photo_disk' => 'local',
                    'profile_photo_path' => $path,
                    'profile_photo_mime_type' => $processed['mimeType'],
                    'profile_photo_size_bytes' => $processed['sizeBytes'],
                    'profile_photo_checksum' => $processed['checksum'],
                    'profile_photo_updated_at' => now(),
                ]);

                $this->auditLogger->record($lockedUser, $lockedUser, 'profile.photo.updated', 'Profile photo updated.', $lockedUser->county_id, [
                    'previous_checksum' => $previousChecksum,
                    'checksum' => $processed['checksum'],
                    'size_bytes' => $processed['sizeBytes'],
                    'mime_type' => $processed['mimeType'],
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        if (is_string($previousPath) && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }
    }

    public function remove(User $user): void
    {
        $previousPath = $user->profile_photo_path;
        $previousChecksum = $user->profile_photo_checksum;

        DB::transaction(function () use ($user, $previousChecksum): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->update([
                'profile_photo_disk' => null,
                'profile_photo_path' => null,
                'profile_photo_mime_type' => null,
                'profile_photo_size_bytes' => null,
                'profile_photo_checksum' => null,
                'profile_photo_updated_at' => null,
            ]);

            $this->auditLogger->record($lockedUser, $lockedUser, 'profile.photo.removed', 'Profile photo removed.', $lockedUser->county_id, ['previous_checksum' => $previousChecksum]);
        });

        if (is_string($previousPath)) {
            Storage::disk('local')->delete($previousPath);
        }
    }
}
