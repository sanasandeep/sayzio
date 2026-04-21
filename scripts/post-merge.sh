#!/bin/bash
set -e

pnpm install --frozen-lockfile
pnpm --filter db run push-force

# The api-server's drizzle push above shares the same Postgres database as the
# Laravel `1inme` artifact and wipes any tables it doesn't know about. Restore
# Laravel's schema + seed data so the app keeps working after every merge.
if [ -d artifacts/1inme ] && command -v php >/dev/null 2>&1; then
  pushd artifacts/1inme >/dev/null

  # If a known Laravel table is missing, rebuild from scratch + seed.
  # Otherwise just apply any pending migrations.
  missing=$(php -r "
    require 'vendor/autoload.php';
    \$app = require 'bootstrap/app.php';
    \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo Illuminate\Support\Facades\Schema::hasTable('plans') ? '0' : '1';
  " 2>/dev/null || echo "1")

  if [ "$missing" = "1" ]; then
    echo "Laravel tables missing after schema push — rebuilding..."
    php artisan migrate:fresh --force --seed
  else
    php artisan migrate --force
  fi

  popd >/dev/null
fi
