#!/usr/bin/env bash
#
# Browser (Playwright) validation runner for the 1inme web artifact.
#
# Wires the tests/Browser/*.spec.ts suite into a repeatable, named validation
# step so a CSS/Alpine regression (e.g. the home-page mobile sign-in popup
# guarded by home-auth-modal-mobile.spec.ts) is caught automatically on every
# change, instead of only when someone remembers to run `pnpm test:e2e`.
#
# Prerequisites this script handles itself:
#   - Installs the Playwright chromium browser (idempotent; a no-op once the
#     browser is cached under ~/.cache/ms-playwright).
#   - Ensures the Laravel app is reachable. If APP_URL is already serving
#     (e.g. the dev workflow is up on the localhost:80 path-based proxy) the
#     suite runs against that. Otherwise it boots an ephemeral
#     `php artisan serve` on port 5000, waits for it, runs the suite against it,
#     and tears the server down on exit.
#
# Prerequisite this script does NOT handle: database migrations must already be
# applied against the (distant) RDS. Each spec is self-bootstrapping and seeds
# its own fixtures via `php artisan tinker`, but those seeds assume the schema
# exists. In a fresh environment run the migrations first (see the repo memory
# note on un-migrated 1inme DBs).
#
# Any extra args are forwarded to `playwright test`, e.g.:
#   bash tests/Browser/run-validation.sh home-auth-modal-mobile.spec.ts
set -euo pipefail

ART_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ART_DIR"

echo "==> Installing Playwright chromium (idempotent)"
pnpm exec playwright install chromium >/dev/null

# Probe the lightweight Laravel `/up` health route, NOT `/`: the home page is
# heavy (maps/embeds) and over the distant RDS a cold render can take 30-45s,
# which would make a root probe spuriously report the app as down. Boot is still
# slow here, so allow a generous per-probe timeout.
is_up() {
  curl -fsS -o /dev/null --max-time 30 "${1%/}/up" 2>/dev/null
}

SERVER_PID=""
cleanup() {
  if [ -n "$SERVER_PID" ]; then
    echo "==> Stopping ephemeral app server (pid $SERVER_PID)"
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
}
trap cleanup EXIT

APP_URL="${APP_URL:-http://localhost:80}"

if is_up "$APP_URL"; then
  echo "==> App already reachable at $APP_URL — testing against it"
else
  echo "==> $APP_URL not reachable; booting an ephemeral app server on :5000"
  # public/build is gitignored, so a fresh environment has no @vite manifest and
  # every Blade page would 500 — build it first if missing (mirrors the dev
  # workflow startup).
  [ -f public/build/manifest.json ] || pnpm run build
  PHP_CLI_SERVER_WORKERS=10 php -d upload_max_filesize=20M -d post_max_size=25M \
    -d memory_limit=256M artisan serve --host=0.0.0.0 --port=5000 --no-reload \
    >/tmp/1inme-e2e-serve.log 2>&1 &
  SERVER_PID=$!
  APP_URL="http://localhost:5000"
  echo "==> Waiting for $APP_URL (up to 60s)"
  for _ in $(seq 1 30); do
    if is_up "$APP_URL"; then break; fi
    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
      echo "Ephemeral app server exited early; log follows:" >&2
      cat /tmp/1inme-e2e-serve.log >&2
      exit 1
    fi
    sleep 2
  done
  if ! is_up "$APP_URL"; then
    echo "App did not become reachable at $APP_URL within the timeout" >&2
    cat /tmp/1inme-e2e-serve.log >&2
    exit 1
  fi
  echo "==> Ephemeral app server is up at $APP_URL"
fi

echo "==> Running Playwright Browser specs against $APP_URL"
APP_URL="$APP_URL" pnpm exec playwright test "$@"
