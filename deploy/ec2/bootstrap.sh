#!/usr/bin/env bash
#
# Sayzio EC2 bootstrap — provisions a fresh Ubuntu (22.04/24.04) server with
# everything the platform needs:
#   - PHP 8.4 (FPM + CLI) with the required extensions
#   - Composer
#   - Node.js 24 + pnpm (via corepack)
#   - Nginx
#   - Certbot (Let's Encrypt)
#
# Run as root (or via sudo) on the EC2 instance:
#   sudo bash bootstrap.sh
#
# Idempotent: safe to re-run.

set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "This script must run as root (use sudo)." >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

echo "==> Updating apt and installing base packages..."
apt-get update -y
apt-get install -y --no-install-recommends \
  ca-certificates curl gnupg git unzip zip acl software-properties-common lsb-release

echo "==> Adding ondrej/php PPA (PHP 8.4 packages for Ubuntu)..."
add-apt-repository -y ppa:ondrej/php
apt-get update -y

echo "==> Installing PHP 8.4 (FPM + CLI) and required extensions..."
# Extension rationale:
#   pgsql      — PostgreSQL (AWS RDS) via PDO
#   mbstring   — Laravel core requirement
#   xml        — Laravel core + phpword
#   curl       — HTTP clients (OpenAI, Places, Trustpilot, WhatsApp, ...)
#   zip        — Composer, phpword, bulk QR CSV->ZIP export
#   gd         — simple-qrcode + dompdf image rendering
#   bcmath     — money/precision arithmetic
#   intl       — locale-aware formatting
#   opcache    — production performance
apt-get install -y \
  php8.4-fpm php8.4-cli \
  php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
  php8.4-gd php8.4-bcmath php8.4-intl php8.4-opcache php8.4-readline

echo "==> Configuring PHP upload limits (mirrors the Replit runtime flags)..."
for ini_dir in /etc/php/8.4/fpm/conf.d /etc/php/8.4/cli/conf.d; do
  cat > "${ini_dir}/90-sayzio.ini" <<'INI'
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
INI
done

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

echo "==> Installing Node.js 24 (NodeSource)..."
if ! command -v node >/dev/null 2>&1 || [ "$(node -v | cut -d. -f1 | tr -d v)" -lt 24 ]; then
  curl -fsSL https://deb.nodesource.com/setup_24.x | bash -
  apt-get install -y nodejs
fi
node --version

echo "==> Enabling pnpm via corepack..."
corepack enable
corepack prepare pnpm@latest --activate || npm install -g pnpm
pnpm --version

echo "==> Installing Nginx + Certbot..."
apt-get install -y nginx certbot python3-certbot-nginx

echo "==> Creating deploy user and directories..."
if ! id -u sayzio >/dev/null 2>&1; then
  useradd --system --create-home --shell /bin/bash sayzio
fi
mkdir -p /var/www
mkdir -p /etc/sayzio
chmod 750 /etc/sayzio
chown root:sayzio /etc/sayzio

echo "==> Enabling services..."
systemctl enable --now php8.4-fpm nginx

cat <<'EOF'

Bootstrap complete. Next steps (see deploy/ec2/README.md):
  1. Clone the repo:        sudo -u sayzio git clone <repo-url> /var/www/sayzio
  2. Fill environment:      /var/www/sayzio/artifacts/1inme/.env  and  /etc/sayzio/api-server.env
  3. Install Nginx config:  deploy/ec2/nginx/sayzio.conf -> /etc/nginx/sites-available/
  4. Install systemd units: deploy/ec2/systemd/*.service|*.timer -> /etc/systemd/system/
  5. Run the deploy script: sudo -u sayzio bash /var/www/sayzio/deploy/ec2/deploy.sh
  6. Issue certificates:    certbot --nginx -d yourdomain.com
EOF
