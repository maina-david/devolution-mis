---
paths:
  - 'app/Models/County.php,app/**/**County*,resources/js/**,database/data/county-identity.json,public/images/counties/**'
---

# Counties

## Render counties through the governed identity contract
Every county shown in a table, map, detail surface, document context or county-aware PDF/JSON export must use County::identityCell() and the shared CountyIdentity renderer, including multi-county portfolios. Identity assets come from the National Treasury registry and retain authority/source URL, official website, verification date, source SHA-256 and local derivative SHA-256; refresh provenance and tests together.
