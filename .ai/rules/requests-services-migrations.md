---
paths:
  - 'app/{Actions,Models,Http/Controllers,Http/Requests,Services}/**/*Assessment*.php,database/migrations/*assessment*.php'
---

# Requests Services Migrations

## Publish assessment results as immutable snapshots
Governed assessments cannot accept a free-form aggregate score. Approval requires calculated verified criteria, complete evidence and active county attestation. Publication additionally requires all findings resolved and appeals decided, then writes an immutable AssessmentResultPublication containing scorecard checksum, criterion calculation inputs, attestation checksums, function profile and canonical checksum. Rankings must derive from these published snapshots, never mutable assessments.
