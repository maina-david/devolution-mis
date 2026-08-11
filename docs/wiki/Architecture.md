# Architecture

## Application stack

- Laravel 13 / PHP 8.4
- Inertia.js 3 / React / TypeScript
- Tailwind CSS 4 / shadcn/ui
- PostgreSQL
- Spatie roles and permissions
- Laravel Scout search
- Database queues, notifications, cache, and sessions
- Reverb-compatible realtime notification events

## Request boundary

Authenticated routes resolve the active team, authorize a programme permission, apply county or portfolio scope, validate input through Form Requests, execute a transaction-safe action, retain audit evidence, and return an Inertia response or redirect.

Frontend visibility is an affordance only. It never replaces policies, gates, scoped queries, or action-level checks.

## Data integrity

Domain models use UUID primary keys. Retained operational records use soft deletes. Foreign keys, checks, specialized indexes, immutable-record triggers, and checksums protect relational and evidentiary integrity.

Each table owns its migration constraints. Only unavoidable circular or self-referential foreign keys are applied in the final deferred-constraint migration.

## Documents

Documents are private by default. The repository records source type, MIME type, checksum, scan status, OCR/extraction state, security classification, versions, legal holds, retention, disposition, and authorized preview/download evidence.

## External integrations

Integration systems and contracts are governed records. Sandbox adapters and automated tests are not evidence of a production IFMIS, IPPD, Treasury, OCoB, CBK, HRIS, messaging, conferencing, or county-system connection.
