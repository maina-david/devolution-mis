---
paths:
  - 'app/{Actions,Models,Http/Controllers,Http/Requests}/**/*Assessment*.php,database/migrations/*assessment_scorecard*.php'
  - 'app/{Actions,Models,Http/Controllers,Http/Requests}/**/*Assessment*.php,database/migrations/*assessment*.php'
---

# Requests Migrations

## Pin and freeze released assessment scorecards
Assessment cycles must pin an explicitly published or retired AssessmentScorecardVersion. Publish only through PublishAssessmentScorecardVersion so the 14 devolved functions, hierarchy weights, mandatory evidence, checksum, attribution and effective dates are validated. Published/retired versions and their hierarchy are database-immutable; create a new draft version for every change.

## Reproduce assessment results from released criteria
Runtime assessments must pin both AssessmentCycle and its released AssessmentScorecardVersion. Calculate scores only from independently verified criterion results and verified mandatory evidence, persist the weight/input snapshot, bind county attestation to a checksum, and require attributed reasons for overrides. Never accept a free-form aggregate score for governed assessments.
