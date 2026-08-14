---
paths:
  - 'resources/js/pages/**'
---

# Js Pages

## Use setLayoutProps for dynamic breadcrumb metadata
For localized or otherwise dynamic breadcrumbs, call Inertia v3 setLayoutProps() inside the page component and retain static `.layout = { breadcrumbs: [] }` metadata where required. Never assign a function that returns `{ breadcrumbs }` to `.layout`; React treats it as a persistent layout component and attempts to render the object.
