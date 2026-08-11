---
paths:
  - 'app/Actions/PublishWorkflowVersion.php,app/Models/WorkflowVersion.php,database/migrations/*workflow_versions*,app/Http/Controllers/WorkflowDefinitionController.php'
---

# Controllers

## Published workflow versions are immutable releases
Workflow versions move draft → published → retired. Publication must use PublishWorkflowVersion so it serializes per definition, retires the prior release, records effective dates and computes the canonical SHA-256 checksum. PostgreSQL prevents released configuration mutation/deletion; only published → retired with effective_to is allowed.
