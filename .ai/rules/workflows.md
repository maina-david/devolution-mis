---
paths:
  - 'app/Services/WorkflowSimulator.php,app/Http/Controllers/WorkflowDefinitionController.php,resources/js/pages/workflows/**'
---

# Workflows

## Workflow simulations are evidence-only and non-mutating
Pre-publication workflow scenarios must run through WorkflowSimulator and the permission-gated JSON endpoint. A simulation may inspect identities, permissions, rules, separation-of-duties, terminal paths, and snapshotted business-calendar SLAs, but it must never create workflow instances, transitions, audits, escalations, or notifications. Surface scenario/version checksums and per-step control evidence in the workflow Sheet.
