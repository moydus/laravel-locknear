# Local development stack

## Quick start

```bash
# Redis + Meilisearch
docker compose up -d

# Laravel (.env)
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
REDIS_HOST=127.0.0.1
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700

# Terminal 1 — API
php artisan serve

# Terminal 2 — queue + broadcasts
php artisan queue:work

# Terminal 3 — Reverb WebSockets
php artisan reverb:start

# Terminal 4 — Horizon (optional, production-like)
php artisan horizon
```

## Provider panel (app-locknear)

```env
NEXT_PUBLIC_REVERB_APP_KEY=<same as REVERB_APP_KEY>
NEXT_PUBLIC_REVERB_HOST=localhost
NEXT_PUBLIC_REVERB_PORT=8080
NEXT_PUBLIC_REVERB_SCHEME=http
```

## Customer track (astro-locknear)

```env
PUBLIC_REVERB_APP_KEY=<same as REVERB_APP_KEY>
PUBLIC_REVERB_HOST=localhost
PUBLIC_REVERB_PORT=8080
PUBLIC_REVERB_SCHEME=http
```

## Seed packages + Stripe

```bash
php artisan db:seed --class=PackageSeeder
```

Set `STRIPE_PRICE_*` in `.env` before checkout works.

## Migrations

```bash
php artisan migrate
php artisan locknear:setup-meilisearch
```
