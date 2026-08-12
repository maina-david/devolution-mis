---
paths:
  - '{bootstrap/app.php,resources/js/pages/error.tsx,resources/views/errors/**,resources/views/vendor/mail/**,lang/**,tests/Feature/BrandedErrorAndMailTemplateTest.php}'
---

# Mail Feature

## Use official identity on errors and Markdown mail
Browser errors use the branded Inertia error page with localized props; server-rendered/non-Inertia fallbacks use the matching resources/views/errors shell. Transactional and authentication emails must remain Laravel Markdown mail and inherit only the minimal published mail overrides: header, message, text equivalents, and theme. Use the Republic of Kenya/Devolution emblem plus Kenyan flag; never reintroduce Laravel default branding.
