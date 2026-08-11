<?php

use App\Support\ReferenceCatalogue;

return [
    'security' => [
        'malware_scanner' => env('DOCUMENT_MALWARE_SCANNER', 'signature'),
        'clamav_binary' => env('DOCUMENT_CLAMAV_BINARY', 'clamscan'),
        'timeout_seconds' => (int) env('DOCUMENT_MALWARE_SCAN_TIMEOUT', 120),
    ],
    'extraction' => [
        'pdftotext_binary' => env('DOCUMENT_PDFTOTEXT_BINARY', 'pdftotext'),
        'pdftoppm_binary' => env('DOCUMENT_PDFTOPPM_BINARY', 'pdftoppm'),
        'tesseract_binary' => env('DOCUMENT_TESSERACT_BINARY', 'tesseract'),
        'language' => env('DOCUMENT_OCR_LANGUAGE', ReferenceCatalogue::defaultOcrLanguage()),
        'ocr_pdf_dpi' => (int) env('DOCUMENT_OCR_PDF_DPI', 200),
        'ocr_pdf_max_pages' => (int) env('DOCUMENT_OCR_PDF_MAX_PAGES', 250),
        'timeout_seconds' => (int) env('DOCUMENT_EXTRACTION_TIMEOUT', 120),
        'maximum_characters' => (int) env('DOCUMENT_EXTRACTION_MAX_CHARACTERS', 2000000),
        'retry_after_minutes' => (int) env('DOCUMENT_EXTRACTION_RETRY_MINUTES', 30),
    ],
];
