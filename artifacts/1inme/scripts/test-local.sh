#!/usr/bin/env bash
#
# test-local.sh — run the 1inme PHPUnit suite locally against a throwaway,
# ephemeral PostgreSQL instance that is created, migrated fresh, and destroyed
# by this script. Nothing ever touches the shared dev/live RDS or the (possibly
# schema-stale) `helium` test database, so there is no wipe risk and, crucially,
# NO SCHEMA DRIFT: the database is built by replaying every migration from an
# empty schema (RefreshDatabase's migrate:fresh), so columns like
# link_clicks.created_at always match what the app queries.
#
# Why this exists
# ---------------
# The two historical "local test DB" recipes each had a sharp edge:
#   * The shared dev/live RDS is cross-region and slow, and a stray
#     DB_DATABASE=postgres + RefreshDatabase would DROP it.
#   * The `helium` 1inme_testing DB drifts behind the migration set, so a test
#     touching a newly-added column fails with a confusing "transaction is
#     aborted" cascade until someone remembers to migrate helium up.
# An ephemeral cluster sidesteps both: it is private to this run, always exactly
# matches the migration files, and is thrown away at the end.
#
# Usage (from artifacts/1inme):
#   scripts/test-local.sh                       # full suite via composer test:sharded
#   scripts/test-local.sh --filter=PaidPageTest # forward args to the runner
#   TEST_LOCAL_SHARDS=6 scripts/test-local.sh   # override shard count
#   TEST_LOCAL_MODE=artisan scripts/test-local.sh --filter=SomeTest
#                                               # use `php artisan test` instead
#                                               # of the sharded runner (best for
#                                               # a single class)
#
# Env knobs:
#   TEST_LOCAL_MODE=sharded|artisan   default: sharded
#   TEST_LOCAL_SHARDS=<n>             default: 4 (only in sharded mode)
#   TEST_LOCAL_PORT=<port>            default: 55432
#   TEST_LOCAL_KEEP=1                 keep the cluster running after the run
#                                     (prints connection info; you clean up)
#
set -euo pipefail

cd "$(dirname "$0")/.."   # artifacts/1inme

PGPORT="${TEST_LOCAL_PORT:-55432}"
PGDATA="$(mktemp -d "/tmp/1inme-testpg.XXXXXX")"
PGLOG="${PGDATA}.log"
MODE="${TEST_LOCAL_MODE:-sharded}"
SHARDS="${TEST_LOCAL_SHARDS:-4}"
KEEP="${TEST_LOCAL_KEEP:-0}"
STARTED=0

log()  { printf '\033[1;36m[test-local]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[test-local]\033[0m %s\n' "$*" >&2; }

cleanup() {
    local code=$?
    if [[ "${KEEP}" == "1" && "${STARTED}" == "1" ]]; then
        warn "TEST_LOCAL_KEEP=1 — leaving cluster running."
        warn "  Data dir : ${PGDATA}"
        warn "  Connect  : psql -h 127.0.0.1 -p ${PGPORT} -U postgres -d 1inme_testing"
        warn "  Stop with: pg_ctl -D '${PGDATA}' stop && rm -rf '${PGDATA}' '${PGLOG}'"
        return $code
    fi
    if [[ "${STARTED}" == "1" ]]; then
        log "Stopping ephemeral PostgreSQL…"
        pg_ctl -D "${PGDATA}" -m immediate stop >/dev/null 2>&1 || true
    fi
    rm -rf "${PGDATA}" "${PGLOG}" 2>/dev/null || true
}
trap cleanup EXIT

for bin in initdb pg_ctl psql createdb; do
    command -v "$bin" >/dev/null 2>&1 || { warn "Required binary '$bin' not on PATH."; exit 1; }
done

if [[ ! -x vendor/bin/phpunit ]]; then
    warn "vendor/bin/phpunit missing — run 'composer install' first."
    exit 1
fi

log "Initializing ephemeral PostgreSQL cluster in ${PGDATA}…"
initdb -D "${PGDATA}" -U postgres --auth=trust >/dev/null

# -k /tmp: put the unix socket lock file somewhere writable; the default
# /run/postgresql does not exist in this container and initdb/pg_ctl would fail.
log "Starting PostgreSQL on 127.0.0.1:${PGPORT}…"
pg_ctl -D "${PGDATA}" -o "-p ${PGPORT} -k /tmp -c listen_addresses='127.0.0.1'" -l "${PGLOG}" start >/dev/null
STARTED=1

log "Waiting for PostgreSQL to accept connections…"
for _ in $(seq 1 30); do
    if psql -h 127.0.0.1 -p "${PGPORT}" -U postgres -d postgres -c 'SELECT 1' >/dev/null 2>&1; then
        break
    fi
    sleep 0.5
done
if ! psql -h 127.0.0.1 -p "${PGPORT}" -U postgres -d postgres -c 'SELECT 1' >/dev/null 2>&1; then
    warn "PostgreSQL never became ready. Log:"; cat "${PGLOG}" >&2 || true
    exit 1
fi

log "Creating test database '1inme_testing'…"
createdb -h 127.0.0.1 -p "${PGPORT}" -U postgres 1inme_testing

# Explicitly override every DB_* the app reads so the process env wins over
# .env and RDS secrets. DB_DATABASE=1inme_testing satisfies the fail-closed
# non-test-database guard in tests/TestCase.php. DB_URL/DATABASE_URL are blanked
# so no connection-string shortcut re-points us at RDS/helium.
export DB_CONNECTION=pgsql
export DB_HOST=127.0.0.1
export DB_PORT="${PGPORT}"
export DB_DATABASE=1inme_testing
export DB_USERNAME=postgres
export DB_PASSWORD=""
export DB_SSLMODE=disable
export PGSSLMODE=disable
export DB_URL=""
export DATABASE_URL=""

log "Clearing cached config so overrides take effect…"
php artisan config:clear --ansi >/dev/null 2>&1 || true

if [[ "${MODE}" == "artisan" ]]; then
    log "Running: php artisan test $* (fresh migrate on first boot)"
    php artisan test "$@"
else
    log "Running: composer test:sharded (--shards=${SHARDS}) $*"
    php scripts/run-sharded-tests.php --shards="${SHARDS}" "$@"
fi
