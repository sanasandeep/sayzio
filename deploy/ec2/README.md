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
├── bootstrap.sh                    # Ubuntu 22.04/24.04 provisioning (PHP 8.4, Composer, Node 24, pnpm, Nginx, Certbot)
├── bootstrap-al2023.sh             # Amazon Linux 2023 provisioning (same stack via dnf)
├── deploy.sh                       # repeatable deploy (mirrors the Replit production pipeline; auto-detects distro)
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

## Supported distros at a glance

Both tracks install the exact same stack; only package/service/path names differ.

| | Ubuntu 22.04/24.04 | Amazon Linux 2023 |
|---|---|---|
| Bootstrap script | `bootstrap.sh` | `bootstrap-al2023.sh` |
| Default SSH user | `ubuntu` | `ec2-user` |
| PHP 8.4 source | ondrej/php PPA | AL2023 repos (release **2023.7+**; older → `dnf upgrade --releasever=latest`) |
| PHP-FPM unit | `php8.4-fpm` | `php-fpm` |
| PHP-FPM socket | `/run/php/php8.4-fpm.sock` | `/run/php-fpm/www.sock` |
| FPM runtime user | `www-data` | `apache` (default pool; `nginx` is already in `listen.acl_users`) |
| Custom PHP ini | `/etc/php/8.4/{fpm,cli}/conf.d/90-sayzio.ini` | `/etc/php.d/90-sayzio.ini` (shared CLI+FPM) |
| Nginx site config | `sites-available/` + symlink in `sites-enabled/` | `/etc/nginx/conf.d/*.conf` (no sites-enabled) |
| Certbot | `apt install certbot python3-certbot-nginx` | `dnf install certbot python3-certbot-nginx` (bootstrap falls back to the official pip-venv method if absent) |

`deploy.sh` needs **no changes** on either distro — it auto-detects the
PHP-FPM unit name and the FPM runtime user for the storage ACL grant
(`PHP_FPM_SERVICE` / `PHP_FPM_USER` env overrides remain available).

## Step 1 — Provision the EC2 instance

- **AMI**: Ubuntu 24.04 LTS (22.04 also works) **or Amazon Linux 2023**.
- **Size**: `t3.small` minimum; `t3.medium`+ recommended (Vite/Tailwind builds
  and Composer are memory-hungry). On instances with less than ~4 GB RAM the
  bootstrap scripts automatically create and enable a persistent 2 GB swapfile
  (skipped if swap is already active) — no manual swap setup needed.
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
# on the EC2 instance (scp the script up, or clone the repo first and run it from deploy/ec2/)
sudo bash bootstrap.sh              # Ubuntu 22.04/24.04
sudo bash bootstrap-al2023.sh       # Amazon Linux 2023
```

Installs PHP 8.4 (FPM + CLI + extensions), Composer, Node.js 24, pnpm, Nginx,
Certbot; creates the `sayzio` deploy user and `/etc/sayzio`. Idempotent.

AL2023 note: PHP 8.4 ships in the AL2023 repos from release **2023.7**
onward. On an older release the script stops and tells you to
`sudo dnf upgrade -y --releasever=latest && sudo reboot` first.

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

> **Clone the FULL monorepo — never flatten it.** Nginx and every script in
> this kit point *into* the repo at `artifacts/1inme/public`; the Laravel app
> must stay at `/var/www/sayzio/artifacts/1inme`. See
> [Recovering from a flattened clone](#recovering-from-a-flattened-clone)
> if the app's contents were ever moved to the repo root.

## Step 4 — Install Nginx + systemd units

**Ubuntu:**

```bash
cd /var/www/sayzio

# Nginx (edit server_name first; default socket line already matches Ubuntu)
sudo cp deploy/ec2/nginx/sayzio.conf /etc/nginx/sites-available/sayzio.conf
sudo ln -s /etc/nginx/sites-available/sayzio.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

**Amazon Linux 2023** (no `sites-available`/`sites-enabled` — nginx loads
`/etc/nginx/conf.d/*.conf`):

```bash
cd /var/www/sayzio

sudo cp deploy/ec2/nginx/sayzio.conf /etc/nginx/conf.d/sayzio.conf
# edit server_name AND switch the fastcgi_pass socket:
#   comment  fastcgi_pass unix:/run/php/php8.4-fpm.sock;
#   enable   fastcgi_pass unix:/run/php-fpm/www.sock;
sudo nano /etc/nginx/conf.d/sayzio.conf
# (optional) comment out the default `server { listen 80; ... }` block in
# /etc/nginx/nginx.conf so this vhost is the only one answering
sudo nginx -t && sudo systemctl reload nginx
```

**systemd units (identical on both distros):**

```bash
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
`deploy.sh` uses (note the PHP-FPM unit name differs per distro):

```bash
# Ubuntu
sudo tee /etc/sudoers.d/sayzio-deploy <<'EOF'
sayzio ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm, /usr/bin/systemctl restart sayzio-api.service, /usr/bin/systemctl restart sayzio-queue.service, /usr/bin/systemctl reload nginx, /usr/sbin/nginx -t
EOF

# Amazon Linux 2023
sudo tee /etc/sudoers.d/sayzio-deploy <<'EOF'
sayzio ALL=(root) NOPASSWD: /usr/bin/systemctl reload php-fpm, /usr/bin/systemctl restart sayzio-api.service, /usr/bin/systemctl restart sayzio-queue.service, /usr/bin/systemctl reload nginx, /usr/sbin/nginx -t
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

Certbot install differs per distro (both handled by the bootstrap scripts):
Ubuntu uses the apt packages; AL2023 uses `dnf install certbot
python3-certbot-nginx` when available, otherwise the bootstrap falls back to
the official pip-venv method (`/opt/certbot`, symlinked to
`/usr/local/bin/certbot`). With the pip method there is no distro renewal
timer — add one: `echo "0 0,12 * * * root certbot renew -q" | sudo tee -a /etc/crontab`.

Then set `SESSION_SECURE_COOKIE=true` in `.env` and redeploy.

### Automatic HTTPS for customer custom domains

Every customer custom domain (and admin global domain) needs its own
certificate once its DNS points at this server. The kit automates this
end-to-end — the app's scheduler issues certificates for newly verified
domains via a sudoers-whitelisted root helper:

- **`deploy/ec2/issue-domain-cert.sh`** (install as
  `/usr/local/sbin/sayzio-issue-cert`): certbot **webroot** issuance against
  the Laravel public dir (works before any per-domain vhost exists — unmatched
  Hosts fall through to the main `sayzio.conf` server, which serves static
  files first), then renders
  `/etc/nginx/conf.d/sayzio-domain-<domain>.conf` from
  `nginx/custom-domain.conf.template` (80 → ACME + HTTPS redirect, 443 → the
  Laravel app; PHP-FPM socket auto-detected per distro), validates with
  `nginx -t` (auto-rollback on failure) and reloads nginx. Idempotent.
- **`php artisan domains:issue-certificates`** (scheduled every 10 minutes,
  see `routes/console.php`): finds active **verified** domains without a
  certificate — including all pre-existing ones on first enable — and runs
  the helper for each. Per-domain retry backoff (default 1h); state on the
  `domains` row (`ssl_status`, `ssl_attempts`, `ssl_last_error`, ...). The
  verify buttons (user + admin) reset that state so a freshly verified domain
  is picked up on the next tick.
- **Failures are never silent**: every failed attempt logs a loud
  `::1inme:: SSL ISSUANCE FAILED` marker to the Laravel log; after 3
  consecutive failures the ops admins (`user.ops_alerts.receive`) get an
  in-app + email alert (re-alerted at most every 24h), plus a recovery notice
  when the certificate finally lands.

Setup (once, after the main-domain certbot run above — that also creates the
Let's Encrypt account the webroot flow reuses):

```bash
# 1. Install the root helper
sudo install -m 0755 -o root -g root /var/www/sayzio/deploy/ec2/issue-domain-cert.sh /usr/local/sbin/sayzio-issue-cert

# 2. Let the app user invoke it without a password (append to the Step 4 sudoers file)
echo 'sayzio ALL=(root) NOPASSWD: /usr/local/sbin/sayzio-issue-cert' | sudo tee -a /etc/sudoers.d/sayzio-deploy

# 3. Enable in artifacts/1inme/.env, then refresh the config cache
#      SSL_AUTO_ISSUE=true
#      SSL_CERTBOT_EMAIL=ops@yourdomain.com   # only if no certbot account exists yet
cd /var/www/sayzio/artifacts/1inme && php artisan config:cache

# 4. Sanity-check one domain by hand (also usable for ad-hoc issuance)
sudo /usr/local/sbin/sayzio-issue-cert customerdomain.com
# or through the app path (bypasses the enable flag):
sudo -u sayzio php artisan domains:issue-certificates --force --domain=<id>
```

**Renewals** are certbot's job and cover these per-domain certs
automatically: the packaged installs ship a systemd timer
(`systemctl list-timers | grep certbot`); with the pip-venv fallback add the
crontab line above. The helper registers a `--deploy-hook "systemctl reload
nginx"` on each certificate so renewals go live without a manual reload.

`SSL_AUTO_ISSUE` stays **off** everywhere except EC2 — on Replit the platform
proxy terminates TLS and there is no certbot/nginx, so the scheduled command
is a no-op there.

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

# After editing .env  (AL2023: use `php-fpm` instead of `php8.4-fpm`)
cd /var/www/sayzio/artifacts/1inme && php artisan config:cache && sudo systemctl reload php8.4-fpm && sudo systemctl restart sayzio-queue
```

---

## Amazon Linux 2023 quickstart (existing instance, e.g. sayzio.app)

The condensed end-to-end sequence for an AL2023 instance that already has DNS
pointing at it (nginx returning 502 just means nothing is provisioned yet):

```bash
# 0. SSH in (AL2023 default user is ec2-user)
ssh ec2-user@<instance>

# 1. Clean slate if a previous attempt left a flattened/partial clone
sudo rm -rf /var/www/sayzio        # see "Recovering from a flattened clone" below

# 2. Bootstrap (idempotent; installs PHP 8.4/Composer/Node 24/pnpm/nginx/certbot)
sudo bash bootstrap-al2023.sh      # scp it up, or run from a temp clone's deploy/ec2/

# 3. Clone the FULL monorepo as the deploy user
sudo -u sayzio git clone <your-repo-url> /var/www/sayzio

# 4. Environment (work through env-checklist.md; REUSE the existing APP_KEY)
sudo -u sayzio cp /var/www/sayzio/artifacts/1inme/.env.example /var/www/sayzio/artifacts/1inme/.env
sudo -u sayzio nano /var/www/sayzio/artifacts/1inme/.env
sudo nano /etc/sayzio/api-server.env
sudo chown root:sayzio /etc/sayzio/api-server.env && sudo chmod 640 /etc/sayzio/api-server.env

# 5. Nginx (conf.d — AL2023 has no sites-available/sites-enabled)
sudo cp /var/www/sayzio/deploy/ec2/nginx/sayzio.conf /etc/nginx/conf.d/sayzio.conf
sudo nano /etc/nginx/conf.d/sayzio.conf   # server_name sayzio.app;
                                          # fastcgi_pass unix:/run/php-fpm/www.sock;
sudo nginx -t && sudo systemctl reload nginx

# 6. systemd units + sudoers (use the AL2023 sudoers block from Step 4 above)
cd /var/www/sayzio
sudo cp deploy/ec2/systemd/sayzio-*.service deploy/ec2/systemd/sayzio-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable sayzio-api.service sayzio-queue.service sayzio-scheduler.timer

# 7. First deploy + start services (deploy.sh auto-detects php-fpm/apache)
sudo -u sayzio bash /var/www/sayzio/deploy/ec2/deploy.sh
sudo systemctl start sayzio-api.service sayzio-queue.service sayzio-scheduler.timer

# 8. TLS for the domain
sudo certbot --nginx -d sayzio.app
# then set SESSION_SECURE_COOKIE=true in .env and redeploy

# 9. Smoke test (see Step 8 checklist above)
curl -fsS https://sayzio.app/up && curl -fsS https://sayzio.app/api/healthz
```

## Recovering from a flattened clone

If a previous setup attempt moved the contents of `artifacts/1inme` to the
repo root (a "flattened" layout), nothing in this kit will line up: nginx's
`root` points at `/var/www/sayzio/artifacts/1inme/public`, `deploy.sh` cds
into `artifacts/1inme`, and the systemd units use it as `WorkingDirectory`.

Fix it by re-cloning the full monorepo — do **not** try to move files back:

```bash
# preserve the env file if one was already filled in
cp /var/www/sayzio/.env /tmp/sayzio.env.bak 2>/dev/null || \
  cp /var/www/sayzio/artifacts/1inme/.env /tmp/sayzio.env.bak 2>/dev/null || true

sudo rm -rf /var/www/sayzio
sudo -u sayzio git clone <your-repo-url> /var/www/sayzio
sudo -u sayzio cp /tmp/sayzio.env.bak /var/www/sayzio/artifacts/1inme/.env 2>/dev/null || true
```

Never move `artifacts/1inme`'s contents to the repo root: the pnpm workspace
(`pnpm-workspace.yaml`), the Vite/Tailwind build, the api-server build, and
every path in this kit assume the monorepo layout.
