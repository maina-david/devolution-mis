---
paths:
  - 'app/Actions/StartWorkflow.php,app/Actions/TransitionWorkflow.php,app/Services/WorkflowRuleEvaluator.php,app/Services/WorkflowSlaMonitor.php,app/Models/WorkflowInstance.php,app/Models/WorkflowTransition.php,database/migrations/*workflow_instances*,database/migrations/*workflow_transitions*'
---

# Migrations

## Use the shared runtime engine for governed lifecycles
Module lifecycles must start from an effective published WorkflowVersion via StartWorkflow and advance via TransitionWorkflow. The engine serializes state changes, enforces configured permissions/rules/separation of duties, maintains deadlines and immutable transition evidence, and resolves state SLA escalations when leaving that state. Do not update workflow instance state directly.
