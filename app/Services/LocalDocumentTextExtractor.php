<?php

namespace App\Services;

use App\Contracts\DocumentTextExtractor;
use App\Models\DocumentVersion;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

class LocalDocumentTextExtractor implements DocumentTextExtractor
{
    /** @var list<string> */
    private const TEXT_MIME_TYPES = ['text/plain', 'text/csv', 'application/json', 'application/xml', 'text/xml'];

    /** @var list<string> */
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/tiff'];

    public function __construct(private DocumentIntegrityVerifier $integrityVerifier) {}

    public function supports(DocumentVersion $version): bool
    {
        return in_array($version->mime_type, [...self::TEXT_MIME_TYPES, 'application/pdf', ...self::IMAGE_MIME_TYPES], true);
    }

    public function extract(DocumentVersion $version): array
    {
        if (! $this->supports($version)) {
            return $this->result('failed', 'unsupported', null, 'unsupported_mime_type', "Text extraction is not configured for {$version->mime_type}.");
        }

        if ($version->scan_status !== 'clean') {
            return $this->result('failed', 'security-gate', null, 'document_not_clean', 'Only documents with a clean security scan can be extracted.');
        }

        if (! $this->integrityVerifier->matches($version->storage_disk, $version->path, $version->content_checksum)) {
            return $this->result('failed', 'sha256-integrity-gate', null, 'integrity_mismatch', 'The stored object does not match the immutable document version checksum.');
        }

        $filesystem = Storage::disk($version->storage_disk);
        if (! $filesystem->exists($version->path)) {
            return $this->result('failed', 'storage', null, 'file_missing', 'The stored document version could not be found.');
        }

        if (in_array($version->mime_type, self::TEXT_MIME_TYPES, true)) {
            return $this->extractPlainText($filesystem, $version);
        }

        return $this->withTemporaryFile($filesystem, $version, function (string $temporaryPath) use ($version): array {
            if ($version->mime_type === 'application/pdf') {
                return $this->extractPdf($temporaryPath);
            }

            return $this->extractImage($temporaryPath);
        });
    }

    /** @return array{status: 'completed'|'no_text'|'waiting_dependency'|'failed', engine: string, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>} */
    private function extractPlainText(Filesystem $filesystem, DocumentVersion $version): array
    {
        $contents = $filesystem->get($version->path);

        return $this->textResult($contents, 'native-text');
    }

    /** @return array{status: 'completed'|'no_text'|'waiting_dependency'|'failed', engine: string, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>} */
    private function extractPdf(string $path): array
    {
        $binary = (string) config('repository.extraction.pdftotext_binary');
        $resolvedBinary = $this->resolveBinary($binary);
        if ($resolvedBinary === null) {
            return $this->result('waiting_dependency', 'poppler-pdftotext', null, 'pdftotext_unavailable', 'The configured PDF extraction binary is unavailable.');
        }

        $process = Process::timeout((int) config('repository.extraction.timeout_seconds', 120))
            ->run([$resolvedBinary, '-enc', 'UTF-8', '-layout', $path, '-']);

        if (! $process->successful()) {
            return $this->result('failed', 'poppler-pdftotext', null, 'pdf_extraction_failed', Str::limit($process->errorOutput(), 1000));
        }

        if (Str::of($process->output())->replace("\0", '')->trim()->isNotEmpty()) {
            return $this->textResult($process->output(), 'poppler-pdftotext');
        }

        return $this->extractScannedPdf($path);
    }

    /** @return array{status: 'completed'|'no_text'|'waiting_dependency'|'failed', engine: string, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>} */
    private function extractScannedPdf(string $path): array
    {
        $renderer = $this->resolveBinary((string) config('repository.extraction.pdftoppm_binary'));
        if ($renderer === null) {
            return $this->result('waiting_dependency', 'poppler-pdftoppm+tesseract', null, 'pdftoppm_unavailable', 'Scanned-PDF OCR requires the configured Poppler renderer.');
        }

        $tesseract = $this->resolveBinary((string) config('repository.extraction.tesseract_binary'));
        if ($tesseract === null) {
            return $this->result('waiting_dependency', 'poppler-pdftoppm+tesseract', null, 'tesseract_unavailable', 'Scanned-PDF OCR requires the configured Tesseract binary.');
        }

        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'idmis-pdf-ocr-'.Str::uuid();
        if (! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('A secure scanned-PDF OCR directory could not be created.');
        }

        try {
            $maximumPages = max(1, (int) config('repository.extraction.ocr_pdf_max_pages', 250));
            $prefix = $temporaryDirectory.DIRECTORY_SEPARATOR.'page';
            $render = Process::timeout((int) config('repository.extraction.timeout_seconds', 120))
                ->run([$renderer, '-png', '-r', (string) max(72, (int) config('repository.extraction.ocr_pdf_dpi', 200)), '-f', '1', '-l', (string) $maximumPages, $path, $prefix]);
            if (! $render->successful()) {
                return $this->result('failed', 'poppler-pdftoppm+tesseract', null, 'pdf_rasterization_failed', Str::limit($render->errorOutput(), 1000));
            }

            $pages = glob($prefix.'-*.png') ?: [];
            sort($pages, SORT_NATURAL);
            if ($pages === []) {
                return $this->result('failed', 'poppler-pdftoppm+tesseract', null, 'pdf_rasterization_empty', 'The PDF renderer did not produce any page images.');
            }

            $language = (string) config('repository.extraction.language', ReferenceCatalogue::defaultOcrLanguage());
            $texts = [];
            foreach ($pages as $page) {
                $ocr = Process::timeout((int) config('repository.extraction.timeout_seconds', 120))
                    ->run([$tesseract, $page, 'stdout', '-l', $language]);
                if (! $ocr->successful()) {
                    return $this->result('failed', 'poppler-pdftoppm+tesseract', null, 'scanned_pdf_ocr_failed', Str::limit($ocr->errorOutput(), 1000), $language, ['page' => count($texts) + 1]);
                }
                $texts[] = $ocr->output();
            }

            $result = $this->textResult(implode("\n\n", $texts), 'poppler-pdftoppm+tesseract', $language);
            $result['page_count'] = count($pages);
            $result['metadata'] = [...$result['metadata'], 'dpi' => max(72, (int) config('repository.extraction.ocr_pdf_dpi', 200)), 'maximum_pages' => $maximumPages];

            return $result;
        } finally {
            foreach (glob($temporaryDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
            rmdir($temporaryDirectory);
        }
    }

    /** @return array{status: 'completed'|'no_text'|'waiting_dependency'|'failed', engine: string, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>} */
    private function extractImage(string $path): array
    {
        $binary = (string) config('repository.extraction.tesseract_binary');
        $resolvedBinary = $this->resolveBinary($binary);
        if ($resolvedBinary === null) {
            return $this->result('waiting_dependency', 'tesseract', null, 'tesseract_unavailable', 'Scanned-image OCR requires the configured Tesseract binary.');
        }

        $language = (string) config('repository.extraction.language', ReferenceCatalogue::defaultOcrLanguage());
        $process = Process::timeout((int) config('repository.extraction.timeout_seconds', 120))
            ->run([$resolvedBinary, $path, 'stdout', '-l', $language]);

        if (! $process->successful()) {
            return $this->result('failed', 'tesseract', null, 'image_ocr_failed', Str::limit($process->errorOutput(), 1000));
        }

        return $this->textResult($process->output(), 'tesseract', $language);
    }

    /**
     * @param  callable(string): array{status: 'completed'|'no_text'|'waiting_dependency'|'failed', engine: string, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>}  $callback
     * @return array{status: 'completed'|'no_text'|'waiting_dependency'|'failed', engine: string, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>}
     */
    private function withTemporaryFile(Filesystem $filesystem, DocumentVersion $version, callable $callback): array
    {
        $source = $filesystem->readStream($version->path);
        if (! is_resource($source)) {
            throw new RuntimeException('The stored document could not be opened for extraction.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'idmis-extract-');
        if ($temporaryPath === false) {
            fclose($source);
            throw new RuntimeException('A secure temporary extraction file could not be created.');
        }

        $target = fopen($temporaryPath, 'wb');
        if ($target === false) {
            fclose($source);
            unlink($temporaryPath);
            throw new RuntimeException('The temporary extraction file could not be opened.');
        }

        try {
            stream_copy_to_stream($source, $target);

            return $callback($temporaryPath);
        } finally {
            fclose($source);
            fclose($target);
            unlink($temporaryPath);
        }
    }

    /** @return array{status: 'completed'|'no_text'|'waiting_dependency'|'failed', engine: string, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>} */
    private function textResult(string $text, string $engine, ?string $language = null): array
    {
        $language ??= ReferenceCatalogue::defaultOcrLanguage();
        $normalized = Str::of($text)->replace("\0", '')->trim()->toString();
        if ($normalized === '') {
            return $this->result('no_text', $engine, null, 'no_text_detected', 'No machine-readable text was detected. Scanned PDFs may require the approved OCR worker.', $language);
        }

        $maximumCharacters = (int) config('repository.extraction.maximum_characters', 2_000_000);
        $truncated = Str::length($normalized) > $maximumCharacters;
        $normalized = Str::limit($normalized, $maximumCharacters, '');

        return $this->result('completed', $engine, $normalized, null, null, $language, ['truncated' => $truncated]);
    }

    private function resolveBinary(string $binary): ?string
    {
        if ($binary === '') {
            return null;
        }

        if (str_contains($binary, DIRECTORY_SEPARATOR) && is_file($binary) && is_executable($binary)) {
            return $binary;
        }

        return (new ExecutableFinder)->find($binary);
    }

    /**
     * @param  'completed'|'no_text'|'waiting_dependency'|'failed'  $status
     * @param  array<string, mixed>  $metadata
     * @return array{status: 'completed'|'no_text'|'waiting_dependency'|'failed', engine: string, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>}
     */
    private function result(string $status, string $engine, ?string $text, ?string $errorCode, ?string $errorDetail, ?string $language = null, array $metadata = []): array
    {
        $language ??= ReferenceCatalogue::defaultOcrLanguage();

        return [
            'status' => $status,
            'engine' => $engine,
            'language' => $language,
            'text' => $text,
            'page_count' => null,
            'error_code' => $errorCode,
            'error_detail' => $errorDetail,
            'metadata' => $metadata,
        ];
    }
}
