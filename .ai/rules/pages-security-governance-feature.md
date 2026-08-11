---
paths:
  - 'app/{Actions,Models,Http/Controllers,Http/Requests}/**/*IdentityLifecycle*.php,database/migrations/*identity_lifecycle*,resources/js/pages/security-governance/**,tests/Feature/IdentityLifecycleWorkflowTest.php'
---

# Pages Security Governance Feature

## Preserve maker-checker JML evidence
Identity lifecycle changes must retain unique source provenance and current/proposed access snapshots. A requester or target may not decide the event; terminal applied/rejected evidence remains PostgreSQL-immutable. Leaver application must atomically revoke Spatie roles, county scope, sessions and remember tokens, while privileged joiner/mover access requires strong authentication.
