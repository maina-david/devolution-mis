---
paths:
  - 'resources/js/components/{app-content,app-shell,input-error}.tsx,resources/js/layouts/auth/**,resources/js/pages/{welcome,help,faqs}.tsx,resources/js/pages/auth/**,tests/Feature/AccessibilityContractTest.php'
---

# Feature

## Critical journey accessibility contract
Public, authentication and authenticated shells must provide a visible-on-focus skip link to a focusable main-content target. Dynamic field errors use the shared announced InputError and every invalid control links to its error with aria-invalid/aria-describedby; success/status messages use an appropriate live status region. Source contracts are regression evidence only—browser keyboard, reflow, contrast and assistive-technology validation remain mandatory.
