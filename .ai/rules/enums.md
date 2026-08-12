---
paths:
  - '{resources/js/**,lang/**,app/Http/**,app/Enums/SupportedLocale.php,tests/**}'
---

# Enums

## Supported interface locales and persistent defaults
IDMIS supports English (en), Kiswahili (sw), and French (fr). Every new or changed user-facing string must add synchronized keys for all three locales in the same change. The authenticated header locale control displays the active locale's flag and native name, and changing it persists the authenticated user's default language while updating the session and document lang. Locale controls must retain explicit screen-reader names and announced state.
