---
paths:
  - 'app/Actions/RunSupplyChainScan.php,app/Models/SupplyChainScan.php,database/migrations/*supply_chain_scans*,app/Http/Controllers/SecurityGovernanceController.php,resources/js/pages/security-governance/**'
---

# Security Governance

## Retain verifiable supply-chain evidence
Generate CycloneDX from authoritative lockfiles, retain pass/warn/fail scans immutably, and keep artifacts private. High/critical npm findings, Composer advisories, invalid audits/locks, or storage failure must fail; ambiguous lockfile/source state must warn. Verify artifact SHA-256 before every authorized download and audit the access.
