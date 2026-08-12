---
paths:
  - '{resources/js/**,lang/**,app/Http/**,routes/**,tests/**}'
---

# Http

## Screen-reader and localization coverage
For every new or changed user-facing feature or content, update localization coverage and screen-reader support in the same change. Use translation keys instead of introducing hard-coded UI copy; keep English, Kiswahili and French catalogs synchronized; preserve accessible names, descriptions, keyboard/focus behavior, semantic states, and live announcements where status changes dynamically. The authenticated app header must expose an accessible locale selector, and tests must cover locale persistence, authorization, translated output, and relevant accessibility contracts.
