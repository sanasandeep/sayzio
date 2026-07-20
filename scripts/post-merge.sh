#!/bin/bash
set -e

pnpm install --frozen-lockfile

# Blade/Alpine attribute guards — enforced on EVERY merge, not just when someone
# remembers to run the manual validation workflow.
#
# These are fast, static, read-only scans over artifacts/1inme/resources/views.
# They catch a class of bug that compiles and typechecks fine but silently breaks
# whole Alpine components at runtime (dashboard, template picker, etc.):
#   - alpine-line-comments: `//` line comments inside a double-quoted Alpine
#     attribute expression (x-data/x-init/x-*/@*/:*). The browser flattens the
#     attribute to one line, so `//` swallows the rest — including closing ) / } —
#     throwing "Alpine Expression Error: Unexpected token" and killing the whole
#     component's bindings.
#   - blade-json-in-attr: `@json(` inside a double-quoted attribute (emits literal
#     quotes that truncate x-data/@click and silently kill Alpine).
#   - blade-comment-echo: live {{ }} / {!! !!} echoes inside plain HTML/CSS comments.
#
# They run BEFORE the slow RDS schema sync so a broken merge fails fast, and they
# are deliberately FATAL (no `|| echo`): the whole point is that they can never be
# skipped. If one trips, post-merge fails loudly, the offender is fixed, and the
# idempotent steps below re-run cleanly on the next attempt.
# Run a guard, distinguishing a REAL violation from a transient crash.
#
# These tsx guards are deliberately fatal on a genuine violation (exit 1), but
# when several merges land back-to-back each post-merge run spawns pnpm install +
# tsx guards + an RDS schema sync concurrently, and a Node process can get killed
# under the memory pressure — surfacing as a signal-level exit (>=128, e.g.
# SIGABRT=134 / SIGKILL=137 / SIGSEGV=139), NOT as a guard finding. Retrying the
# scan once clears that transient crash; a real violation (exit 1) still fails
# fast on the first run and is never retried away.
run_guard() {
  # Call sites use the form `run_guard run <script>` (mirroring the underlying
  # `pnpm run <script>`) so post-merge.sh keeps the literal `run check:...`
  # wiring that check-view-guards.test.ts pins in lockstep with the combined
  # check:view-guards runner and the pre-push hook.
  if [ "$1" = "run" ]; then shift; fi
  local script="$1"
  local code=0
  # `|| code=$?` keeps the failing command "tested" so `set -e` does not abort
  # the function before we can inspect the exit status.
  pnpm --filter @workspace/scripts run "$script" || code=$?
  if [ "$code" -ge 128 ]; then
    echo "post-merge: '$script' was killed by a signal (exit $code) — likely transient memory pressure under concurrent merges; retrying once..." >&2
    code=0
    pnpm --filter @workspace/scripts run "$script" || code=$?
  fi
  # A genuine violation (exit 1) reaches here unretried; returning non-zero at
  # the top-level call site trips `set -e` and fails the merge, as intended.
  return "$code"
}

echo "running blade/alpine attribute guards..."
run_guard run check:alpine-line-comments
run_guard run check:blade-json-in-attr
run_guard run check:blade-comment-echo

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

  # Additive schema sync with self-healing for orphaned migrations, serialized
  # across concurrent merges. `migrate:guarded` holds a cross-process Postgres
  # advisory lock for the duration of the run so two merges that land close
  # together cannot migrate the shared RDS database simultaneously (that race
  # caused intermittent "cached plan must not change result type" and partial
  # backlog drains). It runs `migrate --force` and, on failure, falls back to
  # `db:reconcile-migrations --force` INSIDE the same lock. The lock auto-releases
  # if the run dies (session-scoped), so there is no deadlock risk.
  php artisan migrate:guarded \
    || echo "::1inme:: POST-MERGE: migrations did not fully apply — schema may be incomplete. The hourly db:check-pending-migrations check will alert admins. Non-fatal; continuing." >&2

  # Catalog + onboarding seeders, ALL detached to the background.
  #
  # Why background: every one of these is idempotent and best-effort (a re-run on
  # a populated DB is a safe no-op), and none is required for the app to serve —
  # so none belongs on the post-merge critical path. Over the distant RDS each
  # pass is slow, and a long foreground run widens the window in which the merge
  # orchestrator can externally cancel this job (the `river CANCEL` failures).
  # Detaching them keeps the gating run short and also keeps their stack traces
  # (e.g. CardTemplateSeeder when a concurrent merge has the schema mid-apply)
  # OUT of the gating stdout — they go to storage/logs/post-merge-recover.log.
  #
  # Contents:
  # - CardTemplateSeeder: curated card-template library; idempotent, preserves
  #   admin-edited rows (CardTemplateSeeder::SEED_VERSION + CardTemplate::wasCustomized).
  # - The "Who are you?" persona picker reads from page_templates, which starts
  #   empty in a freshly provisioned environment; the three template seeders
  #   ensure every PersonaCatalog persona ends up with >= 10 recommended
  #   templates so the picker is never empty. These are NOT a full `db:seed`
  #   (DatabaseSeeder also creates non-idempotent roles/permissions/admin).
  echo "seeding card templates + plan/addon catalog + onboarding page templates in background..."
  mkdir -p storage/logs
  nohup bash -c "
    php artisan db:seed --class=Database\\\\Seeders\\\\CardTemplateSeeder --force
    php artisan db:seed --class=Database\\\\Seeders\\\\PlansAndAddonsSeeder --force
    php artisan db:seed --class=Database\\\\Seeders\\\\StarterPageTemplatesSeeder --force
    php artisan db:seed --class=Database\\\\Seeders\\\\PageTemplatePersonaSeeder --force
    php artisan db:seed --class=Database\\\\Seeders\\\\ExpandedPageTemplateLibrarySeeder --force
    php artisan db:seed --class=Database\\\\Seeders\\\\LinkTypeExplainerSeeder --force
    # Biolink background template library (Appearance -> Page background ->
    # Template picker). All three are idempotent updateOrCreate-by-slug upserts
    # (additive-only, never destructive), so a re-run on a populated DB is a
    # safe refresh. Without these, bg_templates is empty on freshly provisioned
    # environments and the picker shows \"No templates available yet\".
    php artisan db:seed --class=Database\\\\Seeders\\\\BgTemplateSeeder --force
    php artisan db:seed --class=Database\\\\Seeders\\\\BgPatternTemplatesSeeder --force
    php artisan db:seed --class=Database\\\\Seeders\\\\LightBgTemplatesSeeder --force
    # Default profile-verification tick types (Official/Government/NGO/Company/
    # Creator). The create-table migration only inserts these when IT creates
    # the table, so a deploy where the table exists empty would leave the user
    # request form and the admin tick-type page blank. Idempotent
    # firstOrCreate-by-slug — never clobbers admin edits.
    php artisan db:seed --class=Database\\\\Seeders\\\\VerificationTickTypeSeeder --force
    echo \"[\$(date)] card-template + plan/addon + onboarding page-template + link-type explainer + bg-template seed finished\"
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
