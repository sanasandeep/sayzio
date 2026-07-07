#!/usr/bin/env bash
#
# Sayzio — issue/install a Let's Encrypt certificate for ONE custom domain.
#
# Called automatically by the Laravel scheduler (`domains:issue-certificates`
# every 10 minutes, when SSL_AUTO_ISSUE=true) for every domain that passes
# DNS verification in the app; also safe to run by hand.
#
# What it does (idempotent):
#   1. certbot webroot issuance against the Laravel public dir (works before
#      any per-domain vhost exists: unmatched Hosts fall through to the main
#      sayzio.conf server, whose `location /` serves static files first).
#   2. Renders /etc/nginx/conf.d/sayzio-domain-<domain>.conf from
#      nginx/custom-domain.conf.template (80 → ACME + redirect, 443 → app).
#   3. nginx -t, then reload (config rolled back if the test fails).
#
# Renewals are certbot's job (systemd timer, or the pip-venv crontab from the
# README); the --deploy-hook below reloads nginx after each renewal.
#
# Install (as root; see README Step 7):
#   sudo install -m 0755 -o root -g root deploy/ec2/issue-domain-cert.sh /usr/local/sbin/sayzio-issue-cert
#   # sudoers line so the app user can invoke it without a password:
#   #   sayzio ALL=(root) NOPASSWD: /usr/local/sbin/sayzio-issue-cert
#
# Usage:
#   sayzio-issue-cert <domain> [letsencrypt-account-email]
#
# Env overrides:
#   SAYZIO_ROOT   repo root (default /var/www/sayzio)

set -euo pipefail

DOMAIN="${1:-}"
LE_EMAIL="${2:-}"

if [[ -z "$DOMAIN" ]]; then
  echo "usage: $(basename "$0") <domain> [letsencrypt-account-email]" >&2
  exit 2
fi

# Strict hostname validation — this value came from user input upstream.
if ! [[ "$DOMAIN" =~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$ ]]; then
  echo "refusing invalid domain: $DOMAIN" >&2
  exit 2
fi

if [[ "$(id -u)" -ne 0 ]]; then
  echo "must run as root (install to /usr/local/sbin and whitelist in sudoers)" >&2
  exit 3
fi

SAYZIO_ROOT="${SAYZIO_ROOT:-/var/www/sayzio}"
WEBROOT="$SAYZIO_ROOT/artifacts/1inme/public"
TEMPLATE="$SAYZIO_ROOT/deploy/ec2/nginx/custom-domain.conf.template"
CONF="/etc/nginx/conf.d/sayzio-domain-$DOMAIN.conf"

[[ -d "$WEBROOT"  ]] || { echo "webroot not found: $WEBROOT" >&2; exit 4; }
[[ -f "$TEMPLATE" ]] || { echo "template not found: $TEMPLATE" >&2; exit 4; }

CERTBOT="$(command -v certbot || echo /usr/local/bin/certbot)"
[[ -x "$CERTBOT" ]] || { echo "certbot not installed (run the bootstrap script)" >&2; exit 4; }

# Detect the PHP-FPM socket for the rendered vhost (Ubuntu vs AL2023).
if [[ -S /run/php-fpm/www.sock ]]; then
  FPM_SOCKET="unix:/run/php-fpm/www.sock"
elif [[ -S /run/php/php8.4-fpm.sock ]]; then
  FPM_SOCKET="unix:/run/php/php8.4-fpm.sock"
else
  # Fall back to any php socket present so a minor version bump doesn't break issuance.
  CANDIDATE="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"
  [[ -n "$CANDIDATE" ]] || { echo "no PHP-FPM socket found under /run" >&2; exit 4; }
  FPM_SOCKET="unix:$CANDIDATE"
fi

# --- 1. Certificate (webroot HTTP-01; idempotent via --keep-until-expiring) ---
CERTBOT_ARGS=(
  certonly --webroot -w "$WEBROOT" -d "$DOMAIN"
  --non-interactive --agree-tos --keep-until-expiring
  --deploy-hook "systemctl reload nginx"
)
if [[ -n "$LE_EMAIL" ]]; then
  CERTBOT_ARGS+=(-m "$LE_EMAIL" --no-eff-email)
fi
"$CERTBOT" "${CERTBOT_ARGS[@]}"

LIVE_DIR="/etc/letsencrypt/live/$DOMAIN"
[[ -e "$LIVE_DIR/fullchain.pem" ]] || { echo "certbot reported success but $LIVE_DIR/fullchain.pem is missing" >&2; exit 5; }

# --- 2. Per-domain nginx vhost (rendered fresh each run) ---
TMP_CONF="$(mktemp)"
sed -e "s|__DOMAIN__|$DOMAIN|g" \
    -e "s|__APP_ROOT__|$SAYZIO_ROOT|g" \
    -e "s|__FPM_SOCKET__|$FPM_SOCKET|g" \
    "$TEMPLATE" > "$TMP_CONF"

HAD_CONF=0; PREV_CONF=""
if [[ -f "$CONF" ]]; then
  HAD_CONF=1
  PREV_CONF="$(mktemp)"
  cp "$CONF" "$PREV_CONF"
fi
install -m 0644 "$TMP_CONF" "$CONF"
rm -f "$TMP_CONF"

# --- 3. Validate + reload (roll back the conf if nginx rejects it) ---
if ! nginx -t; then
  echo "nginx -t failed with the new vhost — rolling back $CONF" >&2
  if [[ "$HAD_CONF" -eq 1 ]]; then cp "$PREV_CONF" "$CONF"; else rm -f "$CONF"; fi
  [[ -n "$PREV_CONF" ]] && rm -f "$PREV_CONF"
  exit 6
fi
[[ -n "$PREV_CONF" ]] && rm -f "$PREV_CONF"

systemctl reload nginx
echo "OK: certificate + vhost installed for $DOMAIN"
