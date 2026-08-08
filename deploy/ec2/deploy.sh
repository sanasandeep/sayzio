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
#   -> sync Nginx config (diff repo sayzio.conf vs installed; backup, copy,
#                         nginx -t, restore-on-failure; no-op if unchanged)
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
  # `systemctl cat` is the reliable existence probe (list-unit-files output
  # formatting varies and broke the old grep-based detection on AL2023).
  for _svc in php-fpm php8.4-fpm php8.3-fpm; do
    if systemctl cat "${_svc}.service" >/dev/null 2>&1; then
      PHP_FPM_SERVICE="$_svc"
      break
    fi
  done
  PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php-fpm}"  # override via env if needed
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
# CI=1: non-interactive — lets pnpm proceed past prompts (e.g. recreating
# node_modules after a pnpm version switch) instead of aborting with no TTY.
CI=1 pnpm install --frozen-lockfile

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
# Template gallery convergence (mirrors the Replit deploy, Task #5853): run the
# three idempotent template seeders after a CLEAN migrate so new/redesigned
# starter + persona templates (SEED_VERSION bumps) reach production without a
# manual artisan run. All three preserve admin-edited rows and are safe no-ops
# on a converged DB. Skipped when migrations failed (never seed a half-applied
# schema); best-effort — a seeder failure logs a marker but never fails deploy.
if [ "$mst" -eq 0 ]; then
  for s in StarterPageTemplatesSeeder PageTemplatePersonaSeeder ExpandedPageTemplateLibrarySeeder; do
    if php artisan db:seed --class="Database\\Seeders\\$s" --force; then
      echo "::1inme:: deploy template seeder $s completed" >&2
    else
      echo "::1inme:: deploy template seeder $s FAILED — template gallery may be missing new designs" >&2
    fi
  done
else
  echo "::1inme:: skipping deploy template seeders (migration step failed)" >&2
fi
set -e

log "Caching config and routes..."
# Unlike the Replit deploy (which only ran the :clear variants because config
# came from process env), on EC2 config lives in .env, so caching is safe and
# the standard production optimization.
php artisan config:cache
php artisan route:cache
# Deliberately NO `view:cache` here: it would compile every Blade view as the
# deploy user (sayzio). When PHP-FPM (user apache) later recompiles one of
# those files, BladeCompiler's touch($compiled, mtime) hits EPERM on the
# sayzio-owned file → intermittent 500s ("touch(): Utime failed"). Leaving
# views cleared (view:clear ran above) lets FPM compile lazily and OWN the
# compiled files, which is permission-safe across deploys.
php artisan view:clear

log "Fixing storage permissions (FPM user: $PHP_FPM_USER)..."
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
# Only chmod files we own — cache files created by the PHP-FPM user (apache/
# www-data) are not chmod-able by the deploy user and must not abort the deploy;
# the setfacl grant below (plus each owner's own perms) keeps both users writable.
find storage bootstrap/cache -user "$(id -un)" -exec chmod ug+rwX {} + || true
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
# Nginx config sync (before service reloads, so the reload picks up the
# new config in the same deploy pass).
# ---------------------------------------------------------------------------
if [ "${SKIP_SERVICES:-0}" = "1" ]; then
  log "SKIP_SERVICES=1 — skipping Nginx config sync."
else
  log "Syncing Nginx config..."

  # -n = never prompt for a password (an automated deploy has no terminal).
  # If passwordless sudo isn't configured for the deploy user, fail with
  # actionable instructions instead of a hanging/opaque password prompt.
  # Probe with systemctl itself: sudoers may grant only specific commands
  # (systemctl/nginx), in which case `sudo -n true` would falsely fail.
  if ! sudo -n systemctl --version >/dev/null 2>&1; then
    echo "ERROR: the deploy user '$(id -un)' cannot use passwordless sudo, so services cannot be reloaded." >&2
    echo "Fix once on the server (as ec2-user or root) — use the sudoers block from deploy/ec2/README.md Step 4." >&2
    echo "  sudo visudo -f /etc/sudoers.d/sayzio-deploy" >&2
    exit 1
  fi

  _repo_conf="$APP_DIR/deploy/ec2/nginx/sayzio.conf"

  # Detect distro layout: Ubuntu uses sites-available + symlink in
  # sites-enabled; Amazon Linux 2023 loads /etc/nginx/conf.d/*.conf directly.
  if [ -d /etc/nginx/sites-available ]; then
    _nginx_layout="ubuntu"
    _installed_conf="/etc/nginx/sites-available/sayzio.conf"
  else
    _nginx_layout="al2023"
    _installed_conf="/etc/nginx/conf.d/sayzio.conf"
  fi

  if [ ! -f "$_installed_conf" ]; then
    log "Nginx config not found at $_installed_conf — skipping auto-sync."
    log "  Run 'Step 4 — Install Nginx' from deploy/ec2/README.md once to set up the initial config."
    log "  Subsequent deploys will sync it automatically."
  elif diff -q "$_repo_conf" "$_installed_conf" >/dev/null 2>&1; then
    log "Nginx config is up to date — no sync needed."
  elif grep -q "server_name yourdomain.com;" "$_repo_conf" && ! grep -q "server_name yourdomain.com;" "$_installed_conf"; then
    # The installed config has been customized on the server (real domains,
    # certbot SSL blocks, distro FPM socket) while the repo copy is still the
    # generic template. Overwriting it would clobber server_name + SSL and
    # take the site down with certificate errors (this happened July 2026).
    log "Nginx config on server is site-customized; repo copy is the generic template — leaving installed config untouched."
    log "  To intentionally update it, edit /etc/nginx/conf.d/sayzio.conf (or sites-available) by hand, or commit a real config to deploy/ec2/nginx/sayzio.conf."
  else
    log "Nginx config has changed — installing updated config to $_installed_conf ..."
    _backup_conf="${_installed_conf}.bak"

    # 1. Back up the current installed config.
    if ! sudo -n cp "$_installed_conf" "$_backup_conf"; then
      echo "ERROR: could not back up $_installed_conf to $_backup_conf — aborting Nginx sync." >&2
      echo "Check that the sudoers grant covers: /usr/bin/cp $_installed_conf $_backup_conf" >&2
      exit 1
    fi

    # 2. Install the new config.
    if ! sudo -n cp "$_repo_conf" "$_installed_conf"; then
      echo "ERROR: could not install new Nginx config — restoring backup." >&2
      sudo -n mv "$_backup_conf" "$_installed_conf" || true
      exit 1
    fi

    # 3. Validate the new config; restore backup and abort on failure.
    if ! sudo -n nginx -t 2>&1; then
      echo "" >&2
      echo "::1inme:: NGINX CONFIG INVALID — restoring previous config to prevent an outage." >&2
      echo "  The broken config is preserved in the repo at: $APP_DIR/deploy/ec2/nginx/sayzio.conf" >&2
      echo "  Fix the config and redeploy." >&2
      if sudo -n mv "$_backup_conf" "$_installed_conf"; then
        echo "  Previous config restored successfully." >&2
      else
        echo "  CRITICAL: could not restore backup — Nginx may be broken. Restore manually:" >&2
        echo "    sudo mv $_backup_conf $_installed_conf && sudo nginx -t && sudo systemctl reload nginx" >&2
      fi
      exit 1
    fi

    log "Nginx config synced and validated (layout: $_nginx_layout)."
    # Nginx reload is handled in the service-reloads section below.
  fi

  # ---------------------------------------------------------------------------
  # Custom-domain vhost template drift check.
  # The custom-domain.conf.template is rendered into per-domain vhosts by
  # issue-domain-cert.sh. If the template has changed since it was last used
  # to generate vhosts, the installed vhosts may be stale. We cannot
  # auto-regenerate them here (each requires a live certbot run), so we warn
  # the operator loudly when drift is detected.
  # ---------------------------------------------------------------------------
  _template="$APP_DIR/deploy/ec2/nginx/custom-domain.conf.template"
  _template_hash_file="$APP_DIR/.nginx-custom-template-sha256"
  _template_current_hash="$(sha256sum "$_template" | awk '{print $1}')"

  # Check for installed per-domain vhosts (readable without sudo on both distros).
  _has_domain_vhosts=0
  for _vhost_dir in /etc/nginx/conf.d /etc/nginx/sites-enabled; do
    if ls "${_vhost_dir}"/sayzio-domain-*.conf >/dev/null 2>&1; then
      _has_domain_vhosts=1
      break
    fi
  done

  if [ -f "$_template_hash_file" ]; then
    _template_prev_hash="$(cat "$_template_hash_file")"
    if [ "$_template_current_hash" != "$_template_prev_hash" ] && [ "$_has_domain_vhosts" -eq 1 ]; then
      echo "" >&2
      echo "::1inme:: WARNING — custom-domain Nginx template has changed since it last generated vhosts." >&2
      echo "  Installed per-domain vhosts (/etc/nginx/*/sayzio-domain-*.conf) were built" >&2
      echo "  from an older version of deploy/ec2/nginx/custom-domain.conf.template." >&2
      echo "  To update them, re-run the domain-cert helper for each customer domain:" >&2
      echo "    sudo /usr/local/sbin/sayzio-issue-cert <customerdomain.com>" >&2
      echo "  Or use the artisan command to re-issue all verified domains:" >&2
      echo "    php artisan domains:issue-certificates --force" >&2
      echo "" >&2
    fi
  fi

  # Store the current template hash for future drift comparisons.
  echo "$_template_current_hash" > "$_template_hash_file"
fi

# ---------------------------------------------------------------------------
# Service reloads
# ---------------------------------------------------------------------------
if [ "${SKIP_SERVICES:-0}" = "1" ]; then
  log "SKIP_SERVICES=1 — skipping service reloads."
else
  log "Reloading services..."
  sudo -n systemctl reload "$PHP_FPM_SERVICE"
  sudo -n systemctl restart sayzio-api.service
  sudo -n systemctl restart sayzio-queue.service
  sudo -n nginx -t && sudo -n systemctl reload nginx
fi

# ---------------------------------------------------------------------------
# Boot-time home-cache warm (mirrors the Replit production run command)
# ---------------------------------------------------------------------------
# A fresh deploy starts with cold home-page caches, so the first visitor would
# pay the full multi-second rebuild over the distant RDS before the scheduled
# home:warm-caches job (every 4 min) catches up. Warm them once here, best
# effort: a failure only logs — visitors still fall back to the lazy
# request-path rebuild, and the deploy never fails because of it.
log "Warming home-page caches (best effort)..."
cd "$APP_DIR/artifacts/1inme"
if ! php artisan home:warm-caches; then
  echo "::1inme:: boot home:warm-caches failed — first visitor may hit a cold home render (lazy rebuild still applies)" >&2
fi
cd "$APP_DIR"

log "Deploy complete."
log "Smoke test: curl -fsS https://yourdomain.com/up && curl -fsS https://yourdomain.com/api/healthz"
