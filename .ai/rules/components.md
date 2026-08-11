---
paths:
  - 'app/Jobs/ExtractDocumentText.php,app/Models/DocumentExtraction*.php,database/migrations/*document_extraction*,app/Services/ProgrammeWorkspaceData.php,resources/js/components/evidence-row-action.tsx'
---

# Components

## Retain extraction executions as immutable attempt evidence
Every document extraction execution must append a DocumentExtractionAttempt with version, ordinal, trigger source, initiating identity snapshot, engine, timing, checksum/output metrics, and error evidence. The mutable DocumentExtraction remains the current searchable aggregate; terminal attempts are PostgreSQL-immutable and must be exposed in the governed document Sheet. Automatic upload, version replacement, scheduled recovery, and manual retry must identify their provenance explicitly.
