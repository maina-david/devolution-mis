---
paths:
  - 'app/{Actions,Models,Http/Controllers,Http/Requests}/**/*Learning*.php,database/migrations/*learning_offline_package*,resources/js/pages/learning/**,tests/Feature/LearningOfflinePackageTest.php'
---

# Learning Feature

## Preserve governed offline learning artifacts
Ready offline learning packages are immutable, checksum-bound artifacts. A failed regeneration must remain evidenced but never displace the latest ready package. Never include answer keys or explanations, and never let offline package activity mutate official learner progress until an approved authenticated synchronization and reconciliation workflow exists.
