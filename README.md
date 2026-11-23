# Sky Top Carriers consignment desk

A working Laravel + Vue operations application built from the supplied STC HTML prototype. It covers staff authentication, role-aware fleet administration, consignment intake and printing, trip manifests, route advancement, public tracking, payments, handover and reports.

## Requirements

- PHP 8.2 or newer with PDO MySQL (or PDO SQLite for a quick local run)
- Composer 2
- Node.js 20 or newer and npm
- MySQL 8 / MariaDB 10.6 or newer

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the MySQL database named `sky_top_carriers`, adjust `.env` if necessary, then run:

```bash
php artisan migrate --seed
npm run build
composer run dev
```

For the quickest evaluation without MySQL, set `DB_CONNECTION=sqlite`, remove the other `DB_*` values, create `database/database.sqlite`, and run the migration command.

## Seeded accounts

All seeded accounts use the password `password`.

| Role | Email |
| --- | --- |
| Owner | `owner@skytop.test` |
| Manager | `manager@skytop.test` |
| Clerk | `clerk@skytop.test` |

The public tracker is at `/`; try `STC-5154`. The staff login is `/login`.

## Verification

```bash
vendor/bin/phpunit
npm run build
```

Product rules are documented in [`docs/SPEC.md`](docs/SPEC.md), the tables in [`docs/schema.md`](docs/schema.md), and the original visual reference is retained in [`docs/prototype/stc-consignment-desk.html`](docs/prototype/stc-consignment-desk.html).

## Production notes

Set `APP_ENV=production`, `APP_DEBUG=false`, a strong `APP_KEY`, secure database credentials, and a persistent queue/cache driver. M-Pesa in this MVP is a manually confirmed payment method; Daraja callback verification, SMS, offline/PWA support and GPS are phase-two integrations.
