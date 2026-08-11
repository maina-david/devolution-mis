---
paths:
  - 'app/Models/User.php,app/Services/ProgrammeAuthorization.php,database/migrations/**,database/seeders/**'
---

# Seeders

## Spatie programme RBAC uses UUIDs without teams
Programme roles and permissions use spatie/laravel-permission with UUIDv7 Role/Permission models and UUID morph pivots. Keep Spatie teams disabled: county portfolio scope is handled by county_id/assignedCounties and is separate from the app's existing programme-team domain. Users must not regain a role column.
