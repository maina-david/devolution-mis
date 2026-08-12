---
paths:
  - 'app/Models/{Role,Permission}.php,database/migrations/*{roles,permissions}*,config/permission.php'
---

# App Models Migrations

## Spatie UUID models must retain string key semantics
Role and Permission use UUID primary keys. Custom Spatie models must keep protected $keyType = 'string' and public $incrementing = false; otherwise Eloquent casts UUIDs to integers and permission-to-role relationships query invalid UUID values.
