---
paths:
  - 'app/{Actions,Console/Commands}/**/*IdentityLifecycle*.php'
---

# Commands

## Future-effective JML application boundary
Approved future-effective joiner/mover/leaver events may only be applied by the configured active identity with ManageSecurityGovernance. Keep application lock-protected and idempotent; compare live access with the approved snapshot before mutation, and record retryable application_exception evidence for drift or missing privileged strong authentication.
