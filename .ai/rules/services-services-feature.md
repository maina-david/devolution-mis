---
paths:
  - 'app/Services/DocumentSecurityScanner.php,app/Services/OperationalReadinessCheck.php,config/repository.php,tests/Feature/{DocumentRecordsGovernanceTest,OperationalReadinessTest}.php'
---

# Services Services Feature

## Fail closed on inconclusive document malware scans
Production document uploads require the configured ClamAV process contract; exit 0 is clean, exit 1 is quarantined/infected, and every other result rejects the upload without persistence. The EICAR-only signature gate is local/test development support and must make production readiness fail. Never retain temporary paths or raw scanner errors in scan evidence or the public readiness response.
