---
paths:
  - 'app/Http/{Controllers/AccessControlController.php,Requests/Update*PermissionsRequest.php}|resources/js/pages/access-control/**|app/Services/ProgrammeAuthorization.php'
---

# Services

## Govern role and direct permission changes separately
Only identities with user-access:manage may edit the role matrix or direct user exceptions. Keep role-inherited, direct and effective permissions visibly separate; require a business reason, audit before/after values, lock mutations, forbid self-escalation, and preserve platform-admin user-access/platform-configuration recovery permissions. Use predefined UserRole/ProgrammePermission values rather than free-typed RBAC identifiers.
