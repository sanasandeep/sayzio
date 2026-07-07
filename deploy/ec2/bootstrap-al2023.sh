#!/usr/bin/env bash
#
# Sayzio EC2 bootstrap — Amazon Linux 2023 variant.
#
# Mirrors bootstrap.sh (Ubuntu) step-for-step using dnf:
#   - PHP 8.4 (FPM + CLI) with the required extensions
#   - Composer
#   - Node.js 24 + pnpm (via corepack)
#   - Nginx
#   - Certbot (Let's Encrypt)
#
# Run as root (or via sudo) on the EC2 instance (default SSH user: ec2-user):
#   sudo bash bootstrap-al2023.sh
#
# Idempotent: safe to re-run.
#
# AL2023 vs Ubuntu differences this script handles:
#   - Package manager: dnf (no PPAs; PHP 8.4 comes from the AL2023 repos,
#     available since AL2023 release 2023.7).
#   - PHP ini dir: /etc/php.d/ (shared by CLI and FPM — one file, not two).
#   - PHP-FPM unit name: php-fpm (not php8.4-fpm); pool config lives in
#     /etc/php-fpm.d/www.conf; socket /run/php-fpm/www.sock; runtime user
#     "apache" with "nginx" already in listen.acl_users — works with the
#     nginx configs in this kit without pool changes.
#   - Nginx site configs go in /etc/nginx/conf.d/ (no sites-available/enabled).
#   - Certbot: dnf package when available, else the official pip-venv method.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "This script must run as root (use sudo)." >&2
  exit 1
fi

if ! grep -qs 'Amazon Linux' /etc/os-release; then
  echo "WARNING: this does not look like Amazon Linux. For Ubuntu use bootstrap.sh instead." >&2
fi

echo "==> Refreshing dnf metadata and installing base packages..."
dnf -y makecache
# curl is preinstalled on AL2023 as curl-minimal; git/unzip/zip/acl/tar are what we add.
dnf install -y git unzip zip acl tar

echo "==> Installing PHP 8.4 (FPM + CLI) and required extensions..."
# PHP 8.4 ships in the AL2023 repos from release 2023.7 (early 2025) onward.
if ! rpm -q php8.4-fpm >/dev/null 2>&1 && ! dnf -q list --available php8.4-fpm >/dev/null 2>&1; then
  cat >&2 <<'MSG'
ERROR: php8.4-fpm is not available in the enabled repositories.
PHP 8.4 requires Amazon Linux 2023 release 2023.7 or newer. Update first:

  sudo dnf upgrade -y --releasever=latest
  sudo reboot

then re-run this script. (Check the current release with: cat /etc/os-release)
MSG
  exit 1
fi
# Extension rationale (same set as the Ubuntu bootstrap):
#   pgsql      — PostgreSQL (AWS RDS) via PDO
#   mbstring   — Laravel core requirement
#   xml        — Laravel core + phpword
#   curl       — HTTP clients; on AL2023 the curl extension is bundled inside
#                php8.4-common (no separate php8.4-curl package)
#   zip        — Composer, phpword, bulk QR CSV->ZIP export (installed below;
#                package name varies)
#   gd         — simple-qrcode + dompdf image rendering
#   bcmath     — money/precision arithmetic
#   intl       — locale-aware formatting
#   opcache    — production performance
#   process    — proc_open etc. (separate subpackage on AL2023; Composer needs it)
dnf install -y \
  php8.4-fpm php8.4-cli php8.4-common \
  php8.4-pgsql php8.4-mbstring php8.4-xml \
  php8.4-gd php8.4-bcmath php8.4-intl php8.4-opcache php8.4-process

# zip extension: naming differs across AL2023 point releases / Fedora heritage
# (php8.4-zip vs php8.4-pecl-zip) — try both.
if ! php -m 2>/dev/null | grep -qix 'zip'; then
  dnf install -y php8.4-zip 2>/dev/null || dnf install -y php8.4-pecl-zip
fi

# Sanity check: every required extension must actually be loaded.
for ext in pdo_pgsql mbstring xml curl zip gd bcmath intl; do
  if ! php -m | grep -qix "$ext"; then
    echo "ERROR: PHP extension '$ext' is not loaded after install." >&2
    exit 1
  fi
done
php --version

echo "==> Configuring PHP upload limits (mirrors the Replit runtime flags)..."
# /etc/php.d/ applies to BOTH CLI and FPM on AL2023 (unlike Ubuntu's split dirs).
cat > /etc/php.d/90-sayzio.ini <<'INI'
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
INI

echo "==> Installing Composer..."
if ! command -v composer >/dev/null 2>&1; then
  EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
  ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
  if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    echo "ERROR: Composer installer checksum mismatch" >&2
    rm -f /tmp/composer-setup.php
    exit 1
  fi
  php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi
composer --version

echo "==> Installing Node.js 24 (NodeSource RPM repo)..."
# AL2023's own repos top out below Node 24 — use NodeSource, same as Ubuntu.
if ! command -v node >/dev/null 2>&1 || [ "$(node -v | cut -d. -f1 | tr -d v)" -lt 24 ]; then
  curl -fsSL https://rpm.nodesource.com/setup_24.x | bash -
  dnf install -y nodejs
fi
node --version

echo "==> Enabling pnpm via corepack..."
corepack enable
corepack prepare pnpm@latest --activate || npm install -g pnpm
pnpm --version

echo "==> Installing Nginx..."
dnf install -y nginx

echo "==> Installing Certbot..."
if command -v certbot >/dev/null 2>&1; then
  : # already installed
elif dnf -q list --available certbot >/dev/null 2>&1; then
  dnf install -y certbot python3-certbot-nginx
else
  # Official Certbot fallback for AL2023 when the dnf package is absent:
  # pip inside a dedicated venv (https://certbot.eff.org — "pip" instructions).
  dnf install -y python3 python3-pip augeas-libs
  python3 -m venv /opt/certbot
  /opt/certbot/bin/pip install --upgrade pip
  /opt/certbot/bin/pip install certbot certbot-nginx
  ln -sf /opt/certbot/bin/certbot /usr/local/bin/certbot
fi
certbot --version

echo "==> Creating deploy user and directories..."
if ! id -u sayzio >/dev/null 2>&1; then
  useradd --system --create-home --shell /bin/bash sayzio
fi
mkdir -p /var/www
mkdir -p /etc/sayzio
chmod 750 /etc/sayzio
chown root:sayzio /etc/sayzio

echo "==> Enabling services..."
# AL2023 unit names: php-fpm (not php8.4-fpm) and nginx.
systemctl enable --now php-fpm nginx

cat <<'EOF'

Bootstrap complete (Amazon Linux 2023). Next steps (see deploy/ec2/README.md):
  1. Clone the FULL monorepo:  sudo -u sayzio git clone <repo-url> /var/www/sayzio
                               (never flatten it — nginx points at artifacts/1inme/public)
  2. Fill environment:         /var/www/sayzio/artifacts/1inme/.env  and  /etc/sayzio/api-server.env
  3. Install Nginx config:     deploy/ec2/nginx/sayzio.conf -> /etc/nginx/conf.d/sayzio.conf
                               (edit server_name + switch fastcgi_pass to unix:/run/php-fpm/www.sock)
  4. Install systemd units:    deploy/ec2/systemd/*.service|*.timer -> /etc/systemd/system/
  5. Run the deploy script:    sudo -u sayzio bash /var/www/sayzio/deploy/ec2/deploy.sh
  6. Issue certificates:       certbot --nginx -d yourdomain.com
EOF
