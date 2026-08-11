---
paths:
  - 'app/{Actions,Models,Services,Http/Controllers,Http/Requests}/**/*ProjectSchedule*.php,database/migrations/*project_schedule_baseline*,resources/js/pages/projects/**'
  - 'app/{Actions,Models,Services,Http/Controllers,Http/Requests}/**/*Project*.php,database/migrations/*project_resource*,resources/js/pages/projects/**'
---

# Projects

## Project schedule baseline assurance
Capture only complete milestone schedules whose weights total exactly 100%. Baselines require a separate VerifyProjectUpdates actor, must reject stale live snapshots before approval, and terminal decisions remain PostgreSQL-immutable. Critical-path/float and forecast variance must be derived deterministically from milestone/dependency evidence and compared with the latest approved checksum-bound baseline.

## Project capacity and earned-value controls
Project resources inherit the project's package-validated currency. Allocation creation must lock the resource, remain within its project/milestone/availability dates, derive inclusive units and cost, and reject capacity excess on every overlapping day. Earned-value metrics derive only from the latest approved weighted schedule baseline plus BAC, physical progress and actual expenditure; disclose the CPI-only method and return unavailable values instead of inventing thresholds.
