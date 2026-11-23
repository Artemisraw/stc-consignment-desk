# Agent instructions

Sky Top Carriers consignment desk: a Laravel + Vue/Inertia operations app for a Nairobi–Mandera courier. Two AI agents (Claude Code and OpenAI Codex) work on this repo in parallel, each on its own GitHub issue. These rules apply to both.

Read first: `docs/SPEC.md` (roles, modules, business rules), `docs/schema.md` (tables), and `docs/prototype/stc-consignment-desk.html` (visual reference).

## Stack and conventions

- PHP 8.2+, Laravel 12, Vue 3, Inertia 2, Vite 6. MySQL in development and production, SQLite in tests.
- Use Form Requests for non-trivial input validation and policies for model authorization.
- Store KES as integer shillings. Never introduce floating-point money fields or calculations.
- Add new lifecycle values as backed PHP enums and cast them on the model.
- Every consignment status change and its `tracking_events` row belong in one database transaction.
- Preserve the one-active-trip rule in `TripService`; never implement trip loading directly in a controller.
- Keep controllers thin and operational workflows in services (`app/Services`).
- Add feature coverage for every changed flow, especially trip loading, payments and handover.
- Never commit `.env`, credentials, production phone lists or Daraja secrets.
- Preserve the prototype's receipt-like visual language and strong mobile behaviour (check 390px and 1366px widths). Printed pages use the `print-sheet` styles in `resources/css/app.css`.

## Commands

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate   # first run only
vendor/bin/phpunit          # tests (in-memory SQLite, no Vite build needed)
npm run build               # frontend build
php artisan migrate --seed  # demo data; logins are in README.md
```

CI (`.github/workflows/ci.yml`) runs PHPUnit on PHP 8.2/8.3/8.4 with SQLite and on PHP 8.4 with MySQL 8, plus the Vite build. A PR is not done until CI is green.

## Workflow for parallel agents

- Work only on the issue you were given. Branch from the latest `dev`, named `codex/<issue-number>-<slug>` or `claude/<issue-number>-<slug>`.
- Stay inside the files the issue lists as in scope. If you need to change something outside it, say so in the PR description instead of widening the change silently.
- Put new tests in a new file named after the feature (for example `tests/Feature/StaffAccountsTest.php`). Do not add to `tests/Feature/CourierFlowsTest.php`; that file belongs to the core flows.
- Feature tests call `$this->withoutVite()` in `setUp()` so they never need a frontend build.
- New migrations get a new timestamped file; never edit an existing migration.
- Do not change `composer.json` platform settings or regenerate `composer.lock` unless the issue asks for a new package. The lock is pinned to PHP 8.2 on purpose.
- Rebase on `dev` before opening the PR, open it against `dev`, and write `Closes #<issue>` in the description, with a short list of what changed and how you tested it.
- Anything that changes money (totals, balances, payments) or consignment/trip status transitions must be called out in the PR description for line-by-line human review.
