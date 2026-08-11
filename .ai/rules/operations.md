---
paths:
  - 'app/Actions/RetryFailedQueueJob.php,app/Console/Commands/RecordOperationalMeasurement.php,app/Http/Controllers/OperationsController.php,app/Models/QueueRecoveryAttempt.php,database/migrations/*queue_recovery_attempts*,resources/js/pages/operations/**'
---

# Operational queue recovery

- Never expose retained failed-job payloads or exception bodies in Inertia props, exports or audit descriptions. Surface only job identity, queue/connection, safe exception class, timestamps and SHA-256 checksums.
- A successful recovery must atomically enqueue the retained payload, remove it from the active failed register and append an attributed immutable UUID recovery record. PostgreSQL must reject recovery-evidence update and delete.
- The built-in operator recovery path is limited to the configured transactional database queue. Redis, SQS or another provider requires an approved provider-specific idempotent adapter; fail closed instead of claiming cross-provider atomicity.
- Only `operations:manage` may retry a failed job. `operations:view` may inspect minimized failure and retained recovery evidence.
- Record queue depth, oldest pending-job age and failed-job count as separate SLI measurements. Configuration defaults are engineering thresholds, not approved production SLOs.
