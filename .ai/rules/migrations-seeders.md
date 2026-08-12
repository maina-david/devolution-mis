---
paths:
  - 'database/migrations/**,database/seeders/**'
---

# Migrations Seeders

## Maintain the canonical disposable development schema
The application is in development and the local database may be rebuilt with `migrate:fresh --seed`. Fold schema changes into each table's owning `create_*_table` migration, keep foreign-key dependencies ordered, and do not add transitional `add_*`, correction, or drop migrations. Rebuild and seed after canonical migration edits, then verify the complete graph and realistic seed data. Reconfirm this rule before changing strategy if a shared tunnel or preservation requirement is explicitly reintroduced.
