---
paths:
  - 'routes/**,resources/js/**'
---

# Js

## Generate Wayfinder form variants
This frontend uses generated route `.form()` helpers throughout. After route changes always run `php artisan wayfinder:generate --with-form --no-interaction`; generating without `--with-form` removes those helpers and breaks TypeScript project-wide.
