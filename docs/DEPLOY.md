# Deploying the UAT environment (Laravel Cloud)

UAT runs on [Laravel Cloud](https://cloud.laravel.com) from the `main` branch. Code reaches `main` through a reviewed `dev` → `main` pull request, and GitHub Actions deploys it only after CI passes.

```
feature branch ─PR─▶ dev ─PR─▶ main ──CI passes──▶ deploy.yml ──deploy hook──▶ Laravel Cloud (UAT)
```

## One-time setup

1. **Create the application.** In Laravel Cloud, choose *New application* and connect GitHub. Pick `Artemisraw/stc-consignment-desk` and the branch `main`. Choose the region closest to Kenya that Cloud offers, usually one in Europe.
2. **Add a database.** Attach a **MySQL** database to the environment. Cloud sets the `DB_*` variables itself.
3. **Set environment variables** under *Settings → Environment variables*:
   | Variable | Value |
   | --- | --- |
   | `APP_NAME` | `Sky Top Carriers` |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `DEMO_PASSWORD` | a strong password shared only with UAT testers |
   | `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `database` (the defaults) |

   `APP_KEY` and `APP_URL` are set by Cloud. Never commit these values.
4. **Build and deploy commands.** Use PHP 8.4 and Node 22.
   - Build: `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`
   - Deploy: `php artisan migrate --force`
5. **Gate deploys on CI.**
   1. Turn off *push to deploy* for the environment.
   2. Copy the environment's *deploy hook* URL.
   3. In GitHub, go to *Settings → Secrets and variables → Actions* and add it as the secret `LARAVEL_CLOUD_DEPLOY_HOOK`.
6. **First deploy.** Merge `dev` into `main`. When CI passes, `deploy.yml` triggers the deploy.
7. **Load demo data once.** From the Cloud *Commands* tab, run `php artisan db:seed --force`. It refuses to run without `DEMO_PASSWORD`. Do not run it again: it would duplicate the demo data. To start over, run `php artisan migrate:fresh --seed --force`. That **deletes all UAT data.**
8. **Optional: save cost.** Turn on hibernation, so the app sleeps when nobody uses it. The first request after a quiet period takes a few seconds.

## Checks after the first deploy

- Sign in at `/login` with `owner@skytop.test` and `DEMO_PASSWORD`. Then change the owner's password under *Password*.
- Open a consignment and check that the tracking URL on the note starts with `https://`.
- Print a consignment note, a trip manifest and the cash-up, and compare them with the paper forms.
- Try the public tracker at `/` with `STC-5154`.

## Logins for testers

| Role | Email |
| --- | --- |
| Owner | `owner@skytop.test` |
| Manager | `manager@skytop.test` |
| Clerk (Nairobi) | `clerk@skytop.test` |
| Branch agent (Wajir) | `wajir@skytop.test` |

All use `DEMO_PASSWORD`. Before real data goes in, the owner should create real staff accounts and deactivate these.
