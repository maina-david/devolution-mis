---
paths:
  - 'app/{Actions,Services,Http/Controllers/Settings,Http/Requests/Settings}/**|database/migrations/*create_users_table.php|resources/js/{pages/settings,components}/**'
---

# Settingscomponents

## Store profile photos privately with integrity metadata
Profile photos are client-cropped, server-normalized to 512x512 WebP, stored on the private local disk, and served only through the authenticated owner route after SHA-256 verification. Keep storage paths/checksums hidden from serialized User data, audit replacements/removals, and delete superseded objects only after the database transaction commits. Profile photo columns belong in the owning users-table migration under the project's consolidated migration policy.
