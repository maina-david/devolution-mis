---
paths:
  - 'app/{Actions,Models,Services,Http/Controllers}/**,database/migrations/**,tests/Feature/**'
---

# Migrations Feature

## Pin governed catalogue releases at selection boundaries
When a business record selects governed county, organization, sector or programme references, resolve and checksum-verify the latest effective published ReferenceDataRelease, validate every selected UUID against its snapshot, independently enforce actor scope, and pin the release in the creation transaction. Expose version/checksum in audit, operational UI and authorized exports; leave legacy records explicitly unpinned unless an approved disposition authorizes backfill.
