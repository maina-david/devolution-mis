# Testing and Release Workflow

## Complete local gate

```bash
composer ci:check
```

This runs ESLint, Prettier verification, TypeScript, Pint, PHPStan, and PHPUnit.

## Focused verification

Run the smallest relevant PHPUnit file while developing:

```bash
php artisan test --compact tests/Feature/RoleDashboardTest.php
```

Then run the complete gate before review. Frontend changes also require a production build:

```bash
npm run build
```

## Branches

- `staging` is the current integration and default branch.
- Feature work branches from `staging` and returns through pull request review.
- GitHub Actions runs on pushes to `staging` and `main`, and on pull requests.
- Production promotion requires the approved release, rollback, operations, and acceptance process; a green CI run alone is insufficient.
