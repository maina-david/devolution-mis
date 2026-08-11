<?php

namespace App\Contracts;

use App\Models\DocumentVersion;

interface DocumentTextExtractor
{
    public function supports(DocumentVersion $version): bool;

    /**
     * @return array{status: 'completed'|'no_text'|'waiting_dependency'|'failed', engine: string, language: string, text: string|null, page_count: int|null, error_code: string|null, error_detail: string|null, metadata: array<string, mixed>}
     */
    public function extract(DocumentVersion $version): array;
}
