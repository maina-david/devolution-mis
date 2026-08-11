---
paths:
  - 'database/migrations/**,database/seeders/**'
---

# Migrations Seeders

## Preserve the ngrok-shared development database
The local application is shared with reviewers through ngrok and its current database state must be preserved. Never run migrate:fresh, db:wipe, reset/refresh, rollback, or blanket DatabaseSeeder execution. Apply only pending forward migrations and run only the specific idempotent seeder required for a newly implemented feature; state the exact migration/seeder scope before execution.
