---
paths:
  - 'app/Actions/{StartWorkflow,TransitionWorkflow}.php,app/Services/BusinessTimeCalculator.php,app/Models/{WorkflowInstance,BusinessCalendar,BusinessCalendarHoliday}.php,database/migrations/*business_calendar*'
---

# Models Migrations

## Snapshot published business calendars for workflow SLAs
When a published workflow declares business_calendar_id, StartWorkflow must resolve a published/effective calendar and snapshot its UUID on the instance. All later state deadlines reuse that instance calendar; do not resolve a newer calendar mid-workflow. Published calendar and exception rows are checksum-bound and PostgreSQL-immutable; elapsed-hour fallback remains only for workflows without an approved calendar.
