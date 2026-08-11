<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class DocumentIntegrityVerifier
{
    public function matches(string $disk, string $path, ?string $expectedChecksum): bool
    {
        if ($expectedChecksum === null || ! Storage::disk($disk)->exists($path)) {
            return false;
        }

        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            return false;
        }

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_equals($expectedChecksum, hash_final($hash));
        } finally {
            fclose($stream);
        }
    }
}
