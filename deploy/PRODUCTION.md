# LockNear — Production Deploy

## Architecture

```
locknear.com        → Cloudflare Pages  (astro-locknear)
app.locknear.com    → Vercel            (app-locknear)
api.locknear.com    → Railway service: web     ┐
ws.locknear.com     → Railway service: reverb  │  same GitHub repo
(internal)          → Railway service: worker  │  4 different start commands
(internal)          → Railway service: cron    ┘
(internal)          → Railway plugin: Redis
(internal)          → Railway service: Meilisearch (Docker)
cdn.locknear.com    → Cloudflare R2     (logos, assets)
DB                  → Neon PostgreSQL
```

---

## Railway Project Yapısı — Görsel

```
Railway Project: locknear
├── [web]         GitHub repo → "php artisan serve --host=0.0.0.0 --port=$PORT"
│                 Domain: api.locknear.com
│
├── [worker]      GitHub repo → "sh railway/run-worker.sh"
│                 No domain — internal queue processor (Horizon)
│
├── [reverb]      GitHub repo → "sh railway/run-reverb.sh"
│                 Domain: ws.locknear.com (WebSocket)
│
├── [cron]        GitHub repo → "sh railway/run-cron.sh"
│                 No domain — Laravel scheduler (every 60s)
│
├── [Redis]       Railway plugin (+ New → Database → Redis)
│                 Auto-creates REDIS_URL var → reference as ${{Redis.REDIS_URL}}
│                 DB 0: queue+session, DB 1: cache (database.php'de ayırılmış)
│
└── [meili]       Docker image: getmeili/meilisearch:v1.12
                  Env: MEILI_ENV=production, MEILI_MASTER_KEY=<random>
                  No domain — internal (MEILISEARCH_HOST=${{meili.RAILWAY_PRIVATE_DOMAIN}})
```

Hepsi aynı Railway projesi içinde. Shared variables ile env var'ları bir kez girip tüm servislere paylaşırsın.

---

## 1. Neon — PostgreSQL

1. neon.tech → New project → "locknear" → Region: us-east-1
2. Copy the **pooled connection string** (Settings → Connection Details → Pooled)
3. Set in Railway (shared variables):
   ```
   DB_CONNECTION=pgsql
   DB_URL=postgresql://user:pass@ep-xxx.us-east-2.aws.neon.tech/neondb?sslmode=require
   ```
4. Neon scales to zero on idle — free tier 0.5 GB, paid from $19/mo.

---

## 2. Redis — Railway Plugin

Railway dashboard → locknear project → **+ New → Database → Redis**

Railway otomatik `REDIS_URL` variable'ı oluşturur. Diğer servislerde reference et:
```
REDIS_URL=${{Redis.REDIS_URL}}
REDIS_CLIENT=phpredis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

DB'ye göre otomatik ayrılmış (`config/database.php`):
- DB 0 → queue + session + Reverb
- DB 1 → cache

---

## 3. Cloudflare R2 — File Storage (logos)

1. Cloudflare dashboard → R2 → Create bucket: `locknear`
2. R2 → Manage API Tokens → Create token: `Object Read & Write` on `locknear` bucket
3. Custom domain: R2 bucket → Settings → Custom Domain → `cdn.locknear.com`
4. Set in Railway:
   ```
   FILESYSTEM_DISK=r2
   FILAMENT_FILESYSTEM_DISK=r2
   AWS_ACCESS_KEY_ID=<R2 access key>
   AWS_SECRET_ACCESS_KEY=<R2 secret>
   AWS_DEFAULT_REGION=auto
   AWS_BUCKET=locknear
   AWS_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
   AWS_URL=https://cdn.locknear.com
   AWS_USE_PATH_STYLE_ENDPOINT=false
   ```

---

## 4. Resend — Transactional Email

1. resend.com → Add domain → `locknear.com` → Add DNS records in Cloudflare
2. Create API key (full access)
3. Set in Railway (web service):
   ```
   MAIL_MAILER=failover
   RESEND_API_KEY=re_xxxxxxxxxxxx
   MAIL_FROM_ADDRESS=noreply@locknear.com
   MAIL_FROM_NAME=LockNear
   ```
   Failover order: Cloudflare Email Sending → Resend → log

   For Cloudflare Email Sending (optional primary):
   ```
   CLOUDFLARE_ACCOUNT_ID=your_account_id
   CLOUDFLARE_API_TOKEN=token_with_email_sending_edit
   ```

---

## 5. Railway — Kurulum

```bash
npm install -g @railway/cli
railway login
cd laravel-locknear
railway init   # mevcut projeye bağla veya yeni oluştur
```

### Adım 1 — Redis plugin ekle
Railway dashboard → locknear project → **+ New → Database → Redis**
`REDIS_URL` otomatik oluşur. Shared variables'a ekle:
```
REDIS_URL=${{Redis.REDIS_URL}}
```

### Adım 2 — Meilisearch service ekle (Docker)
Railway dashboard → **+ New → Docker Image** → `getmeili/meilisearch:v1.12`
Env vars:
```
MEILI_ENV=production
MEILI_MASTER_KEY=<random 32 chars>
```
Internal hostname: `${{meili.RAILWAY_PRIVATE_DOMAIN}}:7700` (Railway private network)

### Adım 3 — 4 Laravel service

Her biri için: **+ New → GitHub Repo** → aynı repo → farklı start command

| Service | Start Command | Domain |
|---|---|---|
| web | `php artisan serve --host=0.0.0.0 --port=$PORT` | api.locknear.com |
| worker | `sh railway/run-worker.sh` | — |
| reverb | `sh railway/run-reverb.sh` | ws.locknear.com |
| cron | `sh railway/run-cron.sh` | — |

`web` servisine pre-deploy command ekle:
```
chmod +x ./railway/init-app.sh && sh ./railway/init-app.sh
```

### Shared env vars (Railway project → Shared Variables)
```
APP_NAME=LockNear
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.locknear.com
APP_KEY=                          # php artisan key:generate --show
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_URL=<neon pooled connection string>
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_URL=${{Redis.REDIS_URL}}
REDIS_CLIENT=phpredis
FILESYSTEM_DISK=r2
FILAMENT_FILESYSTEM_DISK=r2
BROADCAST_CONNECTION=reverb
REVERB_HOST=ws.locknear.com
REVERB_SCHEME=https
REVERB_PORT=443
REVERB_APP_ID=locknear
REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<random>
MAIL_MAILER=failover
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://${{meili.RAILWAY_PRIVATE_DOMAIN}}:7700
MEILISEARCH_KEY=<same as MEILI_MASTER_KEY>
FRONTEND_URL=https://locknear.com
APP_PROVIDER_URL=https://app.locknear.com
SANCTUM_STATEFUL_DOMAINS=locknear.com,app.locknear.com
LOCKNEAR_DISPATCH_REQUIRE_SUBSCRIPTION=true
```

---

## 6. Meilisearch — index setup

İlk deploy'dan sonra:
```bash
railway run --service=web php artisan locknear:setup-meilisearch
```

---

## 7. Astro → Cloudflare Pages

Connect GitHub repo in Cloudflare dashboard → Pages → New project.

Build settings:
- Framework: Astro
- Build command: `bun run build`
- Output dir: `dist`
- Custom domain: `locknear.com`

Cloudflare Pages env vars:
```
LARAVEL_API_URL=https://api.locknear.com
LARAVEL_API_KEY=<same as ASTRO_API_KEY in railway>
GOOGLE_PLACES_API_KEY=...
ARCJET_KEY=...
PUBLIC_TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET_KEY=...
PUBLIC_REVERB_APP_KEY=<same as REVERB_APP_KEY>
PUBLIC_REVERB_HOST=ws.locknear.com
PUBLIC_REVERB_PORT=443
PUBLIC_REVERB_SCHEME=https
PUBLIC_MAPBOX_TOKEN=...
SANITY_PROJECT_ID=...
SANITY_DATASET=production
```

---

## 8. Next.js Dashboard → Vercel

```bash
cd app-locknear
vercel --prod
```

Custom domain: `app.locknear.com` (Vercel → Settings → Domains)

Vercel env vars:
```
NEXT_PUBLIC_API_URL=https://api.locknear.com
SESSION_SECRET=<random 32+ chars>
GOOGLE_PLACES_API_KEY=...
NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
NEXT_PUBLIC_REVERB_APP_KEY=<REVERB_APP_KEY>
NEXT_PUBLIC_REVERB_HOST=ws.locknear.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

---

## 9. Post-Deploy Checklist

- [ ] Generate APP_KEY: `railway run php artisan key:generate --show`
- [ ] Run migrations: `railway run php artisan migrate --force`
- [ ] Seed packages: `railway run php artisan db:seed --class=PackageSeeder`
- [ ] Index Meilisearch: `railway run php artisan locknear:setup-meilisearch`
- [ ] Stripe webhook: `https://api.locknear.com/api/stripe/webhook` (all events)
- [ ] Google OAuth: add `https://api.locknear.com/api/customer/auth/google/callback`
- [ ] Test Reverb: `new WebSocket('wss://ws.locknear.com')` in browser console
- [ ] Send test lead → verify Twilio SMS, track page, provider dashboard dispatch
- [ ] Verify R2 logo upload works from profile page

---

## Cost Estimate (monthly)

| Service | Nerede | Tier | Tahmini Maliyet |
|---|---|---|---|
| web + worker + reverb + cron | Railway | Hobby ($5 credit) | ~$20-30 |
| Redis | Railway plugin | included in usage | ~$0-5 |
| Meilisearch | Railway (Docker service) | included in usage | ~$2-5 |
| PostgreSQL | Neon | Free (0.5GB) / Launch $19 | $0-19 |
| File storage (logolar) | Cloudflare R2 | Free 10 GB | $0 |
| Email | Resend | Free 3K/mo | $0 |
| Astro (locknear.com) | Cloudflare Pages | Free | $0 |
| Next.js (app.locknear.com) | Vercel | Free hobby | $0 |
| **Toplam** | | | **~$22-59/mo** |

> Başlangıç için Neon free tier + Railway Hobby yeterli. Traffic gelince Neon Launch'a geç.
