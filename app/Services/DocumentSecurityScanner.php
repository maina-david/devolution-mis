<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class DocumentSecurityScanner
{
    /** @return array{status: 'clean'|'infected', checksum: string, details: array<string, mixed>} */
    public function inspect(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException(__('document-security.errors.upload_unavailable'));
        }

        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw new RuntimeException(__('document-security.errors.checksum_failed'));
        }
        $startedAt = hrtime(true);
        $result = match ($this->driver()) {
            'clamav' => $this->inspectWithClamAv($path),
            'signature' => $this->inspectWithDevelopmentSignatureGate($path),
            default => throw new RuntimeException(__('document-security.errors.scanner_unsupported')),
        };

        return [
            'status' => $result['status'],
            'checksum' => $checksum,
            'details' => [
                'engine' => $result['engine'],
                'result' => $result['status'],
                'signature' => $result['signature'],
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
                'scanned_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function readinessDetail(): string
    {
        if ($this->driver() === 'signature') {
            if (app()->environment('production')) {
                throw new RuntimeException(__('document-security.errors.production_clamav_required'));
            }

            return __('document-security.readiness.development_gate');
        }

        if ($this->driver() !== 'clamav') {
            throw new RuntimeException(__('document-security.errors.scanner_unsupported'));
        }

        $result = Process::timeout($this->timeoutSeconds())->run([$this->clamAvBinary(), '--version']);
        if (! $result->successful() || trim($result->output()) === '') {
            throw new RuntimeException(__('document-security.errors.clamav_unavailable'));
        }

        return __('document-security.readiness.clamav_available', ['version' => mb_substr(trim(strtok($result->output(), "\r\n") ?: __('document-security.readiness.version_reported')), 0, 180)]);
    }

    /** @return array{status: 'clean'|'infected', engine: string, signature: string|null} */
    private function inspectWithClamAv(string $path): array
    {
        try {
            $result = Process::timeout($this->timeoutSeconds())->run([
                $this->clamAvBinary(),
                '--no-summary',
                '--infected',
                $path,
            ]);
        } catch (Throwable) {
            throw new RuntimeException(__('document-security.errors.scan_failed'));
        }

        if ($result->exitCode() === 0) {
            return ['status' => 'clean', 'engine' => 'clamav-clamscan', 'signature' => null];
        }

        if ($result->exitCode() === 1) {
            preg_match('/:\s*(?<signature>[^\r\n:]+)\s+FOUND\s*$/m', $result->output(), $matches);

            return [
                'status' => 'infected',
                'engine' => 'clamav-clamscan',
                'signature' => isset($matches['signature']) ? mb_substr(trim($matches['signature']), 0, 255) : 'malware-detected',
            ];
        }

        throw new RuntimeException(__('document-security.errors.scan_failed'));
    }

    /** @return array{status: 'clean'|'infected', engine: string, signature: string|null} */
    private function inspectWithDevelopmentSignatureGate(string $path): array
    {
        if (app()->environment('production')) {
            throw new RuntimeException(__('document-security.errors.development_gate_prohibited'));
        }

        $infected = $this->fileContains($path, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE');

        return [
            'status' => $infected ? 'infected' : 'clean',
            'engine' => 'idmis-development-signature-gate',
            'signature' => $infected ? 'EICAR-Test-Signature' : null,
        ];
    }

    private function fileContains(string $path, string $needle): bool
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException(__('document-security.errors.upload_open_failed'));
        }

        $overlap = '';
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 65_536);
                if ($chunk === false) {
                    throw new RuntimeException(__('document-security.errors.upload_read_failed'));
                }

                $contents = $overlap.$chunk;
                if (str_contains($contents, $needle)) {
                    return true;
                }

                $overlap = substr($contents, -max(0, strlen($needle) - 1));
            }
        } finally {
            fclose($stream);
        }

        return false;
    }

    private function driver(): string
    {
        return (string) config('repository.security.malware_scanner');
    }

    private function clamAvBinary(): string
    {
        return (string) config('repository.security.clamav_binary');
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) config('repository.security.timeout_seconds'));
    }
}
