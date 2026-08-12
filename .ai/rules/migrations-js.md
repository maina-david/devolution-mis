---
paths:
  - '{routes/**,app/Http/Responses/**,app/Models/User.php,database/migrations/**,resources/js/**,tests/**}'
---

# Migrations Js

## Authenticated routes have no workspace tenant slug
Teams and workspaces are fully removed. Authenticated routes use stable paths such as /dashboard with auth/verified middleware; never add {current_team}, users.current_team_id, teams/team_members tables, team URL defaults, or personal workspace provisioning. Programme RBAC and county assignments provide authorization scope.
