---
paths:
  - 'app/{Actions,Services,Http/Controllers,Http/Requests}/**/*{Import,Migration,Tabular}*.php,resources/js/pages/data-migrations/**,tests/Feature/*{Import,Migration}*Test.php'
---

# Data Migrations Feature

## Keep CSV and XLSX imports on one governed path
Controlled CSV and XLSX imports must use TabularImportReader and preserve the same exact-header, bounded-row, checksum, encrypted-row, reconciliation and three-person application controls. XLSX must fail closed on formulas, multiple sheets, external/executable content and archive bounds; never add an XLSX-only bypass.
