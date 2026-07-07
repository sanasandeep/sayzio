# Sayzio — AWS EC2 Migration Guide

A complete, copy-paste-ready kit for moving the Sayzio application runtime
from Replit to a single AWS EC2 instance. The database is already on AWS RDS
and user content can already use S3/CloudFront, so this kit covers **only the
application runtime**: server provisioning, web server config, process
management, environment variables, and a repeatable deploy script.

Nothing in this directory changes the running Replit dev or production
setup — the `.replit-artifact/artifact.toml` files stay untouched.

## What runs where

| Component | Replit today | EC2 target |
|---|---|---|
| Laravel app (`artifacts/1inme`) | `php -S` + `server.php` static router | **Nginx + PHP-FPM 8.4** at `/` |
| Express API server (`artifacts/api-server`) | Node workflow on :8080 | systemd `sayzio-api.service`, Nginx-proxied at `/api` |
| Marketing site (`artifacts/1inme-com`) | Static serve at `/1inme-com/` | Nginx static files at `/1inme-com/` (or its own subdomain) |
| Queue worker | (not a separate process on Replit) | systemd `sayzio-queue.service` (`queue:work`, restart-on-failure) |
| Scheduler | (in-process) | systemd `sayzio-scheduler.timer` (every minute) or cron |
| Database | AWS RDS PostgreSQL | unchanged |
| User content | S3 + CloudFront | unchanged |

## Kit contents

```
deploy/ec2/
├── README.md                       # this guide
├── env-checklist.md                # complete env-var audit (Laravel + api-server)
├── bootstrap.sh                    # Ubuntu provisioning (PHP 8.4, Composer, Node 24, pnpm, Nginx, Certbot)
├── deploy.sh                       # repeatable deploy (mirrors the Replit production pipeline)
├── nginx/
│   ├── sayzio.conf                 # single-domain path-routed site config
│   └── marketing-subdomain.conf    # optional: marketing site on its own subdomain
└── systemd/
    ├── sayzio-api.service          # Express API server
    ├── sayzio-queue.service        # Laravel queue worker
    ├── sayzio-scheduler.service    # scheduler tick (oneshot)
    └── sayzio-scheduler.timer      # fires the tick every minute
```

---

## Step 1 — Provision the EC2 instance

- **AMI**: Ubuntu 24.04 LTS (22.04 also works).
- **Size**: `t3.small` minimum; `t3.medium`+ recommended (Vite/Tailwind builds
  and Composer are memory-hungry — add 2 GB swap on small instances).
- **Region/VPC**: **place the instance in the same VPC (and AZ region) as the
  RDS instance.** The current Replit setup pays a ~3s cross-region TLS/connect
  cost per fresh DB connection (mitigated by persistent PDO). Same-VPC
  placement removes that latency class entirely.
- **Security group (instance)**: inbound 22 (SSH, your IP only), 80, 443.
- **Storage**: 30 GB+ gp3 (node_modules + vendor + builds + logs).

### RDS security group / VPC access

Open the RDS security group to the EC2 instance:

1. AWS Console → RDS → your instance → VPC security groups.
2. Add an inbound rule: type *PostgreSQL* (5432), source = the **EC2
   instance's security group ID** (preferred over its IP).
3. If the EC2 box is in a different VPC, use VPC peering or make the RDS
   instance publicly accessible + IP-allowlisted (not recommended).
4. RDS enforces SSL — keep `DB_SSLMODE=require`.

## Step 2 — Bootstrap the server

```bash
# on the EC2 instance
sudo bash bootstrap.sh          # (scp it up, or clone the repo first and run deploy/ec2/bootstrap.sh)
```

Installs PHP 8.4 (FPM + CLI + extensions), Composer, Node.js 24, pnpm, Nginx,
Certbot; creates the `sayzio` deploy user and `/etc/sayzio`. Idempotent.

## Step 3 — Clone the repo and fill in environment

```bash
sudo -u sayzio git clone <your-repo-url> /var/www/sayzio
cd /var/www/sayzio

# Laravel env
sudo -u sayzio cp artifacts/1inme/.env.example artifacts/1inme/.env
sudo -u sayzio nano artifacts/1inme/.env

# api-server env
sudo nano /etc/sayzio/api-server.env
sudo chown root:sayzio /etc/sayzio/api-server.env && sudo chmod 640 /etc/sayzio/api-server.env
```

Work through **`env-checklist.md`** — it lists every variable, flags secrets,
and marks which are admin-DB-backed (mail/S3/Places/Trustpilot/Google
Contacts fall back to `app_settings`, so they can be configured in the admin
UI instead) and which are Replit-only.

> **Critical: reuse the existing `APP_KEY`** from the Replit production
> environment. It encrypts admin-stored secrets (SMTP password, platform
> service keys); generating a new key makes them unreadable.

## Step 4 — Install Nginx + systemd units

```bash
cd /var/www/sayzio

# Nginx (edit server_name + paths first)
sudo cp deploy/ec2/nginx/sayzio.conf /etc/nginx/sites-available/sayzio.conf
sudo ln -s /etc/nginx/sites-available/sayzio.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# systemd
sudo cp deploy/ec2/systemd/sayzio-*.service deploy/ec2/systemd/sayzio-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable sayzio-api.service sayzio-queue.service sayzio-scheduler.timer
```

Marketing-site options (documented in the two nginx templates):

- **Path-routed** (default, mirrors Replit): served at `/1inme-com/`; deploy
  builds with `BASE_PATH=/1inme-com/`.
- **Subdomain**: use `marketing-subdomain.conf`, remove the `/1inme-com/`
  block from `sayzio.conf`, and deploy with `MARKETING_BASE=/ bash deploy/ec2/deploy.sh`.

Give the deploy user passwordless rights for exactly the service commands
`deploy.sh` uses:

```bash
sudo tee /etc/sudoers.d/sayzio-deploy <<'EOF'
sayzio ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm, /usr/bin/systemctl restart sayzio-api.service, /usr/bin/systemctl restart sayzio-queue.service, /usr/bin/systemctl reload nginx, /usr/sbin/nginx -t
EOF
```

## Step 5 — First deploy

```bash
sudo -u sayzio bash /var/www/sayzio/deploy/ec2/deploy.sh
sudo systemctl start sayzio-api.service sayzio-queue.service sayzio-scheduler.timer
```

`deploy.sh` mirrors the Replit production pipeline exactly:
`git pull` → `pnpm install --frozen-lockfile` → `view:clear` (BEFORE the asset
build, so Tailwind never scans stale compiled views) → Vite build →
`composer install --no-dev --optimize-autoloader` → `migrate --force` with the
`db:reconcile-migrations` self-healing fallback → config/route/view cache →
api-server + marketing builds → service reloads.

**Keep-serving migration policy is preserved**: if migrations fail even after
the reconcile fallback, the deploy logs a loud `::1inme:: DEPLOY MIGRATION
FAILED` marker and continues — a possibly-incomplete schema beats downtime.
Drift is surfaced by the hourly `db:check-pending-migrations` admin alert, the
admin-dashboard banner, and `GET /up/schema` (503 on drift).

## Step 6 — DNS

- Point your apex/`www` A records at the EC2 public IP (allocate an
  **Elastic IP** first so the address survives instance stop/start).
- **Custom user domains**: every customer domain (and any admin-provided
  global domain) currently pointing at the Replit deployment must be re-pointed
  at the EC2 IP (A record) or at a CNAME target you control. The app verifies
  DNS per domain; the `DOMAIN_DRIFT_GRACE_HOURS` window (default 168h) gives
  customers time to update.

## Step 7 — SSL (Certbot)

```bash
sudo certbot --nginx -d yourdomain.com          # add -d www.yourdomain.com etc.
sudo certbot renew --dry-run                    # verify auto-renewal
```

For customer custom domains you'll need per-domain certs
(`certbot --nginx -d customerdomain.com` per domain, or automate issuance).
Then set `SESSION_SECURE_COOKIE=true` in `.env` and redeploy.

## Step 8 — Smoke-test checklist

```bash
curl -fsS https://yourdomain.com/up               # instant 200, no DB (LB health check)
curl -fsS https://yourdomain.com/up/schema        # 200 = schema in sync (503 = drift)
curl -fsS https://yourdomain.com/api/healthz      # Express API server alive
curl -fsSI https://yourdomain.com/                # home page renders (200)
curl -fsSI https://yourdomain.com/@somehandle     # a seeded/known biolink page
curl -fsSI https://yourdomain.com/1inme-com/      # marketing site (if path-routed)
curl -fsSI https://yourdomain.com/build/          # 403/404, but /build/assets/*.css from page source = 200
systemctl status sayzio-api sayzio-queue          # both active (running)
systemctl list-timers | grep sayzio               # scheduler timer armed
tail -f /var/www/sayzio/artifacts/1inme/storage/logs/laravel.log
```

Also verify in-app: log in, upload a file (S3), send a test email
(Admin → Mail settings → test), and check Admin dashboard for the schema
banner (should be absent).

---

## Differences from the Replit setup

- **No `php -S` / `server.php` static router.** On Replit, production runs
  PHP's built-in server pointed at `server.php` so static assets aren't
  swallowed by Laravel's front controller. On EC2, **Nginx serves statics
  directly** from `public/` and only hands non-file requests to PHP-FPM —
  `server.php` is simply unused (leave it in place; it's harmless).
- **PHP-FPM instead of the built-in server.** Multi-worker process manager,
  no `PHP_CLI_SERVER_WORKERS`, and no `artisan serve` env-stripping concerns.
  The PHP ini limits (`upload_max_filesize=20M`, `post_max_size=25M`,
  `memory_limit=256M`) that Replit passed as `-d` flags are installed as
  `90-sayzio.ini` by `bootstrap.sh`.
- **Health checks**: use **`/up`** for any load balancer / uptime probe — it's
  Laravel's built-in instant-200 route with no DB or schema dependency. Never
  probe `/` (3–5s cold Blade render) or `/up/schema` (503s on drift by design).
- **Config caching**: on EC2 config comes from `.env`, so `deploy.sh` runs
  `config:cache`/`route:cache`/`view:cache` (the Replit deploy only cleared
  them because config came from process env).
- **Queue + scheduler are real processes** (`sayzio-queue.service`,
  `sayzio-scheduler.timer`) instead of Replit-managed behavior.
- **Custom user domains need DNS re-pointing** at the EC2 IP (see Step 6) and
  per-domain certificates.
- **Env delivery**: Laravel reads `artifacts/1inme/.env`; the api-server reads
  `/etc/sayzio/api-server.env` via systemd. No Replit Secrets pane.

## Routine operations

```bash
# Deploy a new version
sudo -u sayzio bash /var/www/sayzio/deploy/ec2/deploy.sh

# Logs
journalctl -u sayzio-api -f
journalctl -u sayzio-queue -f
tail -f /var/www/sayzio/artifacts/1inme/storage/logs/laravel.log
sudo tail -f /var/log/nginx/error.log

# After editing .env
cd /var/www/sayzio/artifacts/1inme && php artisan config:cache && sudo systemctl reload php8.4-fpm && sudo systemctl restart sayzio-queue
```
