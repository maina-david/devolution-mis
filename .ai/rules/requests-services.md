---
paths:
  - 'app/{Actions,Models,Http/Controllers,Http/Requests,Services}/**/*VirtualClassroom*.php'
---

# Requests Services

## Govern classroom attendance as reconciled evidence
Keep attendance bound to the classroom's course enrolment and derive attended classifications from the scheduled interval rather than trusting submitted status. Provider event IDs must be idempotent, conflicting replays must fail, and every amendment must be attributed and audit the before/after checksum. Do not treat a provider-import source value as proof that a Teams, Zoom, or calendar adapter is approved or active.
