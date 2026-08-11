<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class DocumentSecurityScanner
{
    /** @return array{status: 'clean'|'infected', checksum: string, details: array<string, mixed>} */
    public function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw new RuntimeException('The uploaded file checksum could not be calculated.');
        }
        $contents = file_get_contents($path, false, null, 0, 8192);
        $eicarSignature = 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE';
        $infected = $contents !== false && str_contains($contents, $eicarSignature);

        return [
            'status' => $infected ? 'infected' : 'clean',
            'checksum' => $checksum,
            'details' => [
                'engine' => 'idmis-upload-signature-gate',
                'signature' => $infected ? 'EICAR-Test-Signature' : null,
                'scanned_at' => now()->toIso8601String(),
            ],
        ];
    }
}
