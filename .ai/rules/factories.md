---
paths:
  - '{app/Actions/**,routes/**,resources/js/**,database/factories/**}'
---

# Factories

## Administrator-only account provisioning
Accounts must be created only through the governed administrator GrantProgrammeAccess flow. Do not add self-registration, user invitations, team/workspace creation, switching, membership-management routes/UI, or user/workspace slugs. Authenticated routes use stable application paths such as `/dashboard`; authorization and county scope come from programme RBAC and county assignments, never a team URL parameter.
