---
paths:
  - 'app/Services/LocalDocumentTextExtractor.php,config/repository.php,tests/Feature/DocumentTextExtractionTest.php'
---

# Services Feature

## Fail closed and retain scanned-document OCR provenance
Extraction must verify clean scan and immutable checksum before invoking binaries. Image-only PDFs fall back from native text to bounded Poppler rasterization plus per-page Tesseract, retaining engine/language/page/DPI and explicit dependency/failure evidence. Do not mark missing dependencies or empty renderer output as successfully searchable.
