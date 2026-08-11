# Integrated Devolution Management Information System (IDMIS)

IDMIS is a role-scoped management information system for Kenya's State Department for Devolution and the 47 county governments. It digitizes assessment, evidence, programme delivery, intergovernmental coordination, learning, reporting, and governance workflows defined by the IDMIS Terms of Reference.

This repository is private. It contains application source and automated tests only; credentials, runtime data, generated builds, private documents, and local evidence-generation artifacts are excluded from Git.

## Platform scope

The application covers the fourteen ToR modules:

1. Citizen Feedback Mechanism
2. E-Learning Platform
3. Partner Coordination
4. Development Sector Working Group Coordination
5. Project Management
6. State Departmental Performance
7. Monitoring and Evaluation
8. Grievance Redress Mechanism
9. Centralized Data Repository
10. Data Analytics and Reporting
11. Intergovernmental Relations Resolution Tracking
12. Devolution Performance Assessment
13. Travel Clearance
14. Knowledge Management

The authenticated navigation groups these modules into County services, Delivery coordination, Performance and insights, Knowledge and capability, and Platform governance. Contextual tabs expose the module workspaces without duplicating every page in the main sidebar.

## Core architecture

- Laravel 13 on PHP 8.4
- Inertia.js 3 with React and TypeScript
- Tailwind CSS 4 and shadcn/ui components
- PostgreSQL with UUID domain identifiers, foreign-key enforcement, soft deletes, checks, indexes, and integrity triggers
- Spatie roles and permissions with national, assigned-portfolio, and county-only data scopes
- Laravel Scout for authorized global search
- Database-backed queues, notifications, cache, and sessions
- Laravel Reverb-compatible realtime notification delivery
- Private document storage with versioning, checksums, preview/download authorization, scanning state, OCR/extraction state, retention, legal hold, and disposition workflows

## Role and geography boundaries

- County officials and county administrators see only their home county and explicitly delegated scope.
- Independent assessors and development partners see only assigned counties.
- Top management sees its authorized oversight portfolio.
- Devolution administrators have national programme scope.
- Platform administrators operate in national platform context and are not attached to a county identity.

All drilldowns, dashboard signals, maps, tables, searches, bulk actions, and exports must preserve these authorization boundaries.

## Local setup

Prerequisites:

- PHP 8.4 and Composer 2
- Node.js 22 and npm
- PostgreSQL 17 or a compatible supported PostgreSQL release
- Laravel Herd for the local `https://devolution-mis.test` site

Install the application:

```bash
composer setup
```

Configure `.env` with a PostgreSQL database and mail, queue, broadcast, storage, and integration settings appropriate to the environment. Never commit `.env`, OAuth keys, database files, or credentials.

Generate Passport signing keys once per environment before exercising OAuth client-credentials integrations:

```bash
php artisan passport:keys --no-interaction
```

In deployed environments, use approved secret custody or the `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` environment variables instead of source-controlled keys. Rotating these keys invalidates existing access tokens and must follow the approved release procedure.

For an authorized disposable development database, rebuild and seed with:

```bash
php artisan migrate:fresh --seed --no-interaction
```

Do not run a destructive database reset against a shared tunnel, review, staging, or production environment.

Start frontend development through the existing Herd site:

```bash
npm run dev
```

## Background processing

Production-like local operation requires separate long-running processes:

```bash
php artisan queue:work
php artisan schedule:work
php artisan reverb:start
```

Use a process supervisor in deployed environments. Scheduler entries use overlap protection and single-server locks where required. Queue and scheduler failures must be reviewed through the Operations workspace and application logs.

## Quality gates

Run the complete local quality pipeline:

```bash
composer ci:check
```

Useful focused commands:

```bash
php artisan test --compact tests/Feature/RoleDashboardTest.php
vendor/bin/phpstan analyse --no-progress
vendor/bin/pint --format agent
npm run types:check
npm run lint:check
npm run format:check
npm run build
```

GitHub Actions runs the same CI checks against PostgreSQL for pushes to `staging` and `main`, and for pull requests.

## Database migrations

Each table has an owning Laravel migration. Table-specific checks, specialized indexes, and triggers live immediately after that table's `Schema::create()` call. PostgreSQL extensions and reusable trigger functions live in the leading primitives migration. Only unavoidable circular or self-referential foreign keys may be deferred to the final constraint migration.

Domain models use UUID primary keys and soft deletes where the retained-record policy requires them. Foreign-key targets must be created before dependent migrations.

## Security and assurance

- Registration is disabled; administrators grant access.
- Authorization is enforced on the server and never delegated to frontend visibility alone.
- Sensitive citizen and integration payloads are encrypted or excluded from responses as appropriate.
- Audit, workflow decision, release, assurance, and document-integrity evidence is retained rather than overwritten.
- Demonstration integrations and automated tests are not evidence of production government-interface approval.
- Production acceptance still requires accountable owner approvals, representative UAT, security and accessibility assurance, performance evidence, disaster-recovery exercises, and release authorization.

## Documentation

Repository-operational documentation is maintained as version-controlled, Wiki-ready Markdown under [`docs/wiki`](docs/wiki/Home.md). GitHub's private-Wiki feature is unavailable on the repository owner's current plan, so these files remain the canonical engineering documentation unless the account is upgraded. Formal ToR traceability, implementation evidence, enterprise-readiness gates, architecture decisions, brand assets, and controlled project context are maintained in the separate IDMIS Obsidian vault.

## Contribution workflow

1. Branch from `staging`.
2. Keep changes scoped and preserve county/portfolio authorization.
3. Add or update PHPUnit tests for every behavior change.
4. Run the smallest relevant test slice, then the complete CI pipeline before review.
5. Open a pull request into `staging`; promote reviewed, accepted releases through the approved release process.
