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

# Provision the dedicated PHPUnit test database so RefreshDatabase feature tests
# (e.g. tests/Feature/EmailOnlyLoginPolicyTest.php, which guards the email-only
# login policy) can run in this environment without the manual `createdb` step
# documented in artifacts/1inme/CONTRIBUTING.md. phpunit.xml forces
# DB_DATABASE=1inme_testing while host/port/credentials fall through to .env, so
# we create the database on exactly that connection. Postgres has no
# `CREATE DATABASE IF NOT EXISTS`, so we probe pg_database first. Idempotent and
# best-effort: a failure here never aborts the merge.
if [ -f artifacts/1inme/.env ] && command -v psql >/dev/null 2>&1; then
  TEST_DB_HOST=$(grep -E '^DB_HOST=' artifacts/1inme/.env | head -1 | cut -d= -f2-)
  TEST_DB_PORT=$(grep -E '^DB_PORT=' artifacts/1inme/.env | head -1 | cut -d= -f2-)
  TEST_DB_USER=$(grep -E '^DB_USERNAME=' artifacts/1inme/.env | head -1 | cut -d= -f2-)
  TEST_DB_PASS=$(grep -E '^DB_PASSWORD=' artifacts/1inme/.env | head -1 | cut -d= -f2-)
  if [ -n "$TEST_DB_HOST" ]; then
    if PGPASSWORD="$TEST_DB_PASS" psql -h "$TEST_DB_HOST" -p "${TEST_DB_PORT:-5432}" \
        -U "$TEST_DB_USER" -d postgres -tAc \
        "SELECT 1 FROM pg_database WHERE datname='1inme_testing'" 2>/dev/null | grep -q 1; then
      echo "post-merge: 1inme_testing test database already present"
    else
      PGPASSWORD="$TEST_DB_PASS" psql -h "$TEST_DB_HOST" -p "${TEST_DB_PORT:-5432}" \
        -U "$TEST_DB_USER" -d postgres -c 'CREATE DATABASE "1inme_testing"' >/dev/null 2>&1 \
        && echo "post-merge: created 1inme_testing test database" \
        || echo "post-merge: skipped 1inme_testing provisioning (non-fatal)"
    fi
  fi
fi
