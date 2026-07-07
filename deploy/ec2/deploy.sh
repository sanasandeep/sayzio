#!/usr/bin/env bash
#
# Sayzio EC2 deploy script — mirrors the Replit production build/run pipeline:
#
#   git pull
#   -> pnpm install --frozen-lockfile
#   -> php artisan view:clear          (BEFORE the asset build, so Tailwind never
#                                       scans a stale compiled-blade cache)
#   -> Vite/Tailwind asset build       (public/build/manifest.json for @vite)
#   -> composer install --no-dev --optimize-autoloader
#   -> php artisan migrate --force     with db:reconcile-migrations fallback;
#                                       KEEP SERVING on failure (loud log, no exit)
#   -> config/route/view cache
#   -> build api-server
#   -> reload/restart services
#
# Run as the deploy user (not root); it uses sudo only for service reloads:
#   sudo -u sayzio bash /var/www/sayzio/deploy/ec2/deploy.sh
#
# Works unmodified on Ubuntu 22.04/24.04 AND Amazon Linux 2023 — the PHP-FPM
# unit name and FPM runtime user are auto-detected (overridable below).
#
# Overridable via environment:
#   APP_DIR            repo root                 (default /var/www/sayzio)
#   PHP_FPM_SERVICE    php-fpm unit name         (auto: php8.4-fpm on Ubuntu,
#                                                 php-fpm on Amazon Linux 2023)
#   PHP_FPM_USER       FPM runtime user for the storage ACL grant
#                      (auto: www-data on Ubuntu, apache on Amazon Linux 2023)
#   SKIP_SERVICES=1    build only, no systemctl calls (useful for dry runs)

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/sayzio}"
LARAVEL_DIR="$APP_DIR/artifacts/1inme"

# --- PHP-FPM unit name: php8.4-fpm (Ubuntu/ondrej) vs php-fpm (AL2023) -----
if [ -z "${PHP_FPM_SERVICE:-}" ]; then
  if systemctl list-unit-files 'php8.4-fpm.service' 2>/dev/null | grep -q '^php8\.4-fpm\.service'; then
    PHP_FPM_SERVICE=php8.4-fpm
  elif systemctl list-unit-files 'php-fpm.service' 2>/dev/null | grep -q '^php-fpm\.service'; then
    PHP_FPM_SERVICE=php-fpm
  else
    PHP_FPM_SERVICE=php8.4-fpm   # historical default; override via env if needed
  fi
fi

# --- FPM runtime user: www-data (Ubuntu) vs apache (AL2023 default pool) ---
if [ -z "${PHP_FPM_USER:-}" ]; then
  if id -u www-data >/dev/null 2>&1; then
    PHP_FPM_USER=www-data
  elif id -u apache >/dev/null 2>&1; then
    PHP_FPM_USER=apache
  elif id -u nginx >/dev/null 2>&1; then
    PHP_FPM_USER=nginx
  else
    PHP_FPM_USER=www-data
  fi
fi

log() { echo "==> $*"; }

cd "$APP_DIR"

log "Pulling latest code..."
git pull --ff-only

log "Installing JS dependencies (frozen lockfile)..."
pnpm install --frozen-lockfile

# ---------------------------------------------------------------------------
# Laravel app (artifacts/1inme)
# ---------------------------------------------------------------------------
cd "$LARAVEL_DIR"

log "Clearing compiled Blade views BEFORE the asset build..."
# Tailwind's @source scan must never see a stale compiled-view cache, or
# retired utility classes leak back into the compiled CSS.
php artisan view:clear

log "Building Vite/Tailwind assets..."
pnpm --dir "$LARAVEL_DIR" run build

log "Installing PHP dependencies (production)..."
composer install --no-dev --optimize-autoloader --no-interaction

log "Running database migrations (keep-serving policy)..."
# Policy (mirrors the Replit deploy): migrate --force first; on failure fall
# back to db:reconcile-migrations --force, which heals orphaned
# (applied-but-unrecorded) migrations left by interrupted runs, then applies
# the rest — additively, never dropping tables. If that STILL fails we log a
# loud marker and CONTINUE the deploy: a possibly-incomplete schema beats full
# downtime. Drift is detected in-app (hourly db:check-pending-migrations
# alerts, admin banner) and externally via GET /up/schema (503 on drift).
set +e
php artisan migrate --force
mst=$?
if [ "$mst" -ne 0 ]; then
  echo "::1inme:: migrate --force exited $mst — attempting self-healing reconcile of orphaned migrations..." >&2
  php artisan db:reconcile-migrations --force
  mst=$?
fi
if [ "$mst" -ne 0 ]; then
  echo "::1inme:: DEPLOY MIGRATION FAILED (exit=$mst) — database schema may be incomplete (missing tables/columns); some pages may 500. Investigate immediately. Pending migrations:" >&2
  php artisan migrate:status 2>&1 | grep -i pending >&2 || true
fi
set -e

log "Caching config, routes, and views..."
# Unlike the Replit deploy (which only ran the :clear variants because config
# came from process env), on EC2 config lives in .env, so caching is safe and
# the standard production optimization.
php artisan config:cache
php artisan route:cache
php artisan view:cache

log "Fixing storage permissions (FPM user: $PHP_FPM_USER)..."
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
# Grant the PHP-FPM runtime user write access via ACLs
# (www-data on Ubuntu, apache on Amazon Linux 2023 — auto-detected above).
if command -v setfacl >/dev/null 2>&1; then
  setfacl -R -m "u:${PHP_FPM_USER}:rwX" -m "d:u:${PHP_FPM_USER}:rwX" storage bootstrap/cache || true
fi

# ---------------------------------------------------------------------------
# Express API server (artifacts/api-server)
# ---------------------------------------------------------------------------
cd "$APP_DIR"
log "Building Express API server..."
NODE_ENV=production pnpm --filter @workspace/api-server run build

# ---------------------------------------------------------------------------
# Service reloads
# ---------------------------------------------------------------------------
if [ "${SKIP_SERVICES:-0}" = "1" ]; then
  log "SKIP_SERVICES=1 — skipping service reloads."
else
  log "Reloading services..."
  sudo systemctl reload "$PHP_FPM_SERVICE"
  sudo systemctl restart sayzio-api.service
  sudo systemctl restart sayzio-queue.service
  sudo nginx -t && sudo systemctl reload nginx
fi

log "Deploy complete."
log "Smoke test: curl -fsS https://yourdomain.com/up && curl -fsS https://yourdomain.com/api/healthz"
