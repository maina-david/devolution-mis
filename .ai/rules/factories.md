---
paths:
  - '{app/Actions/**,routes/**,resources/js/**,database/factories/**}'
---

# Factories

## Administrator-only account provisioning
Accounts must be created only through the governed administrator GrantProgrammeAccess flow. Do not add self-registration, user invitations, team/workspace creation, switching, or membership-management routes/UI. Team and team_members remain internal one-workspace-per-user URL/tenant boundaries; provision that workspace via ProvisionUserWorkspace.
