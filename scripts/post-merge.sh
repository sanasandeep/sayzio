#!/bin/bash
set -e

pnpm install --frozen-lockfile

# Apply the api-server's drizzle schema. We use the NON-force `push` on purpose:
# drizzle.config.ts restricts drizzle-kit to the dedicated `drizzle` Postgres
# schema (schemaFilter: ["drizzle"], declared via pgSchema("drizzle")), so a push
# can only ever create/alter objects drizzle owns and NEVER emits DROP statements
# for Laravel's `public` tables. Without --force, if a diff ever did contain a
# data-loss statement, push aborts (non-interactive) instead of forcing it
# through — fail safe rather than wipe. It is made non-fatal so a drizzle change
# needing review never blocks the Laravel schema sync below.
pnpm --filter db run push || echo "post-merge: drizzle push reported changes needing review — skipped (non-fatal)"

# Keep the Laravel `1inme` schema in sync ADDITIVELY after every merge.
#
# This shares the same (live/production) Postgres database, so it must NEVER drop
# or recreate tables. `php artisan migrate --force` only CREATEs/ALTERs and is
# safe. If it trips over an orphaned migration (an interrupted run over the
# distant RDS that COMMITed `up()` but was killed before recording it), we fall
# back to `db:reconcile-migrations --force`, which applies the rest and records
# the orphan rather than dying on it. There is deliberately NO `migrate:fresh`
# here — wiping the shared database is exactly the data-loss this script must
# prevent.
if [ -d artifacts/1inme ] && command -v php >/dev/null 2>&1; then
  cd artifacts/1inme

  has_plans=$(php -r "
    require 'vendor/autoload.php';
    \$app = require 'bootstrap/app.php';
    \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo Illuminate\Support\Facades\Schema::hasTable('plans') ? '1' : '0';
  " 2>/dev/null || echo "0")

  if [ "$has_plans" != "1" ]; then
    # Core tables missing is abnormal on the shared database. Do NOT rebuild by
    # wiping — alert loudly. The additive `migrate --force` below will (re)create
    # whatever is genuinely missing without dropping anything that survives.
    echo "::1inme:: POST-MERGE WARNING: core Laravel table 'plans' is missing. NOT running migrate:fresh (it would wipe the shared/live database). Applying additive migrations only — investigate the database immediately." >&2
  fi

  # Additive schema sync with self-healing for orphaned migrations.
  php artisan migrate --force \
    || php artisan db:reconcile-migrations --force \
    || echo "::1inme:: POST-MERGE: migrations did not fully apply — schema may be incomplete. The hourly db:check-pending-migrations check will alert admins. Non-fatal; continuing." >&2

  # Reseed the curated card template library so blueprints added between merges
  # land in prod. CardTemplateSeeder is idempotent and preserves admin-edited
  # rows (see CardTemplateSeeder::SEED_VERSION + CardTemplate::wasCustomized).
  php artisan db:seed --class=Database\\Seeders\\CardTemplateSeeder --force 2>/dev/null || true

  # Onboarding page templates. The "Who are you?" persona picker reads from
  # page_templates, which starts empty in a freshly provisioned environment. The
  # three seeders below ensure every PersonaCatalog persona ends up with >= 10
  # recommended templates so the picker is never empty. They are all idempotent
  # (a re-run on a populated DB is a safe no-op) but are NOT a full `db:seed`
  # (DatabaseSeeder also creates non-idempotent roles/permissions/admin).
  #
  # The expanded library inserts ~400 rows one-by-one and is slow over the
  # distant RDS, so it is detached to the background to stay within the
  # post-merge budget. Logs -> storage/logs/post-merge-recover.log.
  echo "seeding onboarding page templates in background..."
  mkdir -p storage/logs
  nohup bash -c "
    php artisan db:seed --class=Database\\\\Seeders\\\\StarterPageTemplatesSeeder --force
    php artisan db:seed --class=Database\\\\Seeders\\\\PageTemplatePersonaSeeder --force
    php artisan db:seed --class=Database\\\\Seeders\\\\ExpandedPageTemplateLibrarySeeder --force
    echo \"[\$(date)] onboarding page-template seed finished\"
  " >> storage/logs/post-merge-recover.log 2>&1 < /dev/null &
  disown $! 2>/dev/null || true

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
