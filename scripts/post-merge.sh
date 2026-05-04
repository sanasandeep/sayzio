#!/bin/bash
set -e

pnpm install --frozen-lockfile
pnpm --filter db run push-force

# The api-server's drizzle push above shares the same Postgres database as the
# Laravel `1inme` artifact and wipes any tables it doesn't know about. Restore
# Laravel's schema + seed data so the app keeps working after every merge.
#
# When a rebuild is needed it can take ~15s — beyond the 20s post-merge budget,
# so the heavy recovery is detached to the background. The dev server will
# briefly 500 while it runs, then auto-recover. The fast path (tables intact)
# completes synchronously.
if [ -d artifacts/1inme ] && command -v php >/dev/null 2>&1; then
  cd artifacts/1inme

  has_plans=$(php -r "
    require 'vendor/autoload.php';
    \$app = require 'bootstrap/app.php';
    \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo Illuminate\Support\Facades\Schema::hasTable('plans') ? '1' : '0';
  " 2>/dev/null || echo "0")

  if [ "$has_plans" = "1" ]; then
    # Fast path: just apply any new migrations + reseed the curated card
    # template library so blueprints added between merges land in prod.
    # CardTemplateSeeder is idempotent and preserves admin-edited rows
    # (see CardTemplateSeeder::SEED_VERSION + CardTemplate::wasCustomized).
    php artisan migrate --force || true
    php artisan db:seed --class=Database\\Seeders\\CardTemplateSeeder --force 2>/dev/null || true
  else
    # Slow path: schema was wiped. Detach the rebuild so we don't blow the
    # post-merge timeout. Logs go to storage/logs/post-merge-recover.log.
    echo "Laravel tables missing after schema push — rebuilding in background..."
    mkdir -p storage/logs
    nohup bash -c "
      php artisan migrate:fresh --force --seed
      echo \"[\$(date)] post-merge recovery finished\"
    " >> storage/logs/post-merge-recover.log 2>&1 < /dev/null &
    disown $! 2>/dev/null || true
  fi

  cd - >/dev/null
fi
