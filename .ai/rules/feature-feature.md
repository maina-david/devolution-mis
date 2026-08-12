---
paths:
  - '{app/Enums/UserRole.php,app/Http/Controllers/ProgrammeWorkspaceController.php,app/Http/Controllers/WorkspaceExportController.php,tests/Feature/Audit*Test.php,tests/Feature/ProgrammeRbacTest.php}'
---

# Feature Feature

## Restrict audit trails to national administrators
Only Devolution Administrator and Platform Administrator roles may view or export audit trails or audit assurance data. Enforce the role allowlist at the HTTP boundary in addition to the audit-trail:view permission so direct permission assignments cannot bypass the restriction.
