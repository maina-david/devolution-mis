---
paths:
  - 'app/{Actions,Models,Services,Http/Controllers,Http/Requests,Console/Commands}/**/*Audit*.php,database/migrations/*audit_assurance*,resources/js/**/*audit-assurance*,tests/Feature/{AuditTrailTest,AuditAssuranceTest}.php'
---

# Js Feature

## Preserve verifiable audit boundaries
New audit events use canonical hash version 2; legacy events must produce a warning and must never be reported as payload-verified. Assurance runs are immutable and explicitly bounded by the last covered event. Sign artifacts with a named retained key and verify artifact/signature on download. External anchoring and authority acceptance must not be claimed without actual evidence.
