# Local Development and Operations

## Setup

```bash
composer setup
```

Configure a PostgreSQL connection in `.env`. Laravel Herd serves the project at `https://devolution-mis.test`; do not start a separate PHP development server.

For an explicitly disposable local database only:

```bash
php artisan migrate:fresh --seed --no-interaction
```

## Long-running processes

```bash
npm run dev
php artisan queue:work
php artisan schedule:work
php artisan reverb:start
```

Use supervised processes outside local development. Do not use PHP CLI server worker settings as a substitute for queue, scheduler, or realtime workers.

## Diagnostics

```bash
php artisan schedule:list
php artisan queue:failed
php artisan route:list --except-vendor
php artisan config:show database.default
```

Use recent Laravel logs and browser logs for diagnosis. Treat notifications stored in the database separately from realtime delivery: a record may exist even when the websocket transport is unavailable.
