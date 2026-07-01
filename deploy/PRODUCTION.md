# LockNear Production Runbook

This document describes the current VPS deployment. Older Railway/Neon instructions
were removed because they did not match production.

## Architecture

- Host: Ubuntu VPS, application in `/var/www/laravel-locknear`
- Nginx + PHP-FPM 8.4 for `api.locknear.com`
- PostgreSQL 16 on localhost
- Redis on localhost for cache, sessions, queues and Horizon
- Supervisor programs: `locknear-horizon`, `locknear-reverb`, `locknear-scheduler`
- Astro customer app on Cloudflare and provider dashboard on Vercel

## Required environment

Production uses `APP_ENV=production`, `APP_DEBUG=false`, PostgreSQL, Redis queues,
Reverb broadcasting, Stripe, Twilio and transactional mail. Keep `.env` mode `0600`.
Do not keep plaintext `.env` backups inside the release directory.

Recommended logging:

```dotenv
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
SUPPORT_INBOX=support@locknear.com
```

`locknear:monitor-health` runs every five minutes and checks the database, failed jobs,
Horizon and disk capacity. Critical failures are logged and emailed to `SUPPORT_INBOX`
with a 30-minute alert cooldown.

## Automated deploy

Pushes to `main` run Composer security audit and the full test suite before the SSH
deploy job. The deploy uses a fast-forward merge, maintenance mode, forced migrations,
cache rebuild, supervisor restart and an HTTPS `/up` health check.

Unexpected changes outside generated Filament assets stop deployment.

## Stripe subscription checkout

Provider upgrades use Stripe Checkout. Each paid package needs recurring price IDs in
Stripe and matching env vars on the API server.

1. In Stripe Dashboard (or CLI), create recurring prices for:
   - Professional monthly ($299) and yearly ($2,990)
   - Business monthly ($699) and yearly ($6,990)
2. Set on production `.env`:

```dotenv
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_PROFESSIONAL_MONTHLY=price_...
STRIPE_PRICE_PROFESSIONAL_YEARLY=price_...
STRIPE_PRICE_BUSINESS_MONTHLY=price_...
STRIPE_PRICE_BUSINESS_YEARLY=price_...
```

3. Sync into the database (deploy runs this automatically; run manually after changing prices):

```sh
cd /var/www/laravel-locknear
php artisan db:seed --class=PackageSeeder
php artisan locknear:sync-package-prices
php artisan config:cache
```

Without `STRIPE_PRICE_*` values, `/subscription` checkout returns
`Price not configured for this plan`.

## Manual verification

```sh
cd /var/www/laravel-locknear
php artisan about --only=environment,cache,drivers
php artisan migrate:status
php artisan horizon:status
php artisan schedule:list
php artisan queue:failed
php artisan locknear:monitor-health
curl --fail https://api.locknear.com/up
```

## Rollback

1. Identify the previous known-good commit.
2. Revert the faulty commit in Git; do not reset the production worktree.
3. Push the revert to `main` and let the normal pipeline deploy it.
4. Database migrations must have a reviewed backward-compatible rollback plan before
   deployment. Never run `migrate:rollback` blindly on production.

## End-to-end release smoke test

Use test-mode payment credentials in staging:

1. Customer authorizes a card and creates a lead.
2. An online provider receives and accepts the dispatch.
3. Provider marks en-route and arrived.
4. Provider proposes a quote; customer approves it.
5. Provider starts work; customer signs.
6. Provider completes; approved total is captured and invoice is created.
7. Verify tracking session exchange, customer/provider messages and failed-job count.

The automated `SecureWorkOrderTest` covers this state sequence without charging Stripe.
