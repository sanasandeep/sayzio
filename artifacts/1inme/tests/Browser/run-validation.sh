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

# The browser e2e gate runs its OWN dedicated, stable app server rather than
# reusing whatever happens to be on the :80 dev-workflow proxy. WHY (the dominant
# source of this suite's historical flake): the dev workflow boots its PHP server
# under `concurrently --kill-others`, tied to a 60s-cycling `vite build --watch`.
# When validation runs this suite in parallel with the heavy Expo/Metro jobs on
# one constrained box, a vite rebuild can spike/crash under the load — and
# `--kill-others` then TEARS DOWN the PHP server MID-SUITE. The signature is a
# bloated long-running test followed by 0ms worker crashes and every subsequent
# spec failing on the 30s navigation/action timeout, even on lightweight
# marketing pages. A standalone `artisan serve` started here has no vite watcher
# and no --kill-others, so it stays up for the whole run regardless of what the
# dev workflow does. A caller may still point the suite at an explicit server by
# exporting APP_URL (handy for ad-hoc local runs); only the unattended-gate
# default boots its own server.
EXPLICIT_APP_URL="${APP_URL:-}"
if [ -n "$EXPLICIT_APP_URL" ]; then
  APP_URL="$EXPLICIT_APP_URL"
  echo "==> Using caller-provided APP_URL $APP_URL"
  if ! is_up "$APP_URL"; then
    echo "Caller-provided APP_URL $APP_URL is not reachable" >&2
    exit 1
  fi
else
  # Deliberately NOT :5000 (the dev workflow's own port) so this server coexists
  # with a running dev workflow instead of colliding with it. Override with
  # VALIDATION_PORT if 5050 is ever taken.
  VALIDATION_PORT="${VALIDATION_PORT:-5050}"
  APP_URL="http://localhost:${VALIDATION_PORT}"
  echo "==> Booting a dedicated, stable e2e app server on :${VALIDATION_PORT}"
  # public/build is gitignored, so a fresh environment has no @vite manifest and
  # every Blade page would 500 — build it first if missing (mirrors the dev
  # workflow startup).
  [ -f public/build/manifest.json ] || pnpm run build
  PHP_CLI_SERVER_WORKERS=10 php -d upload_max_filesize=20M -d post_max_size=25M \
    -d memory_limit=256M artisan serve --host=0.0.0.0 --port="${VALIDATION_PORT}" --no-reload \
    >/tmp/1inme-e2e-serve.log 2>&1 &
  SERVER_PID=$!
  echo "==> Waiting for $APP_URL (up to 60s)"
  for _ in $(seq 1 30); do
    if is_up "$APP_URL"; then break; fi
    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
      echo "Dedicated e2e app server exited early; log follows:" >&2
      cat /tmp/1inme-e2e-serve.log >&2
      exit 1
    fi
    sleep 2
  done
  if ! is_up "$APP_URL"; then
    echo "Dedicated e2e app server did not become reachable at $APP_URL within the timeout" >&2
    cat /tmp/1inme-e2e-serve.log >&2
    exit 1
  fi
  echo "==> Dedicated e2e app server is up at $APP_URL"
fi

# Warm the shared app server before handing it to Playwright. The first render
# of each public route is the expensive one: over a distant RDS a cold home
# render is ~30s while a warm one is ~2s, because that first hit primes the
# file-backed config/AppSetting caches (shared across all php-cli workers) and
# the per-worker opcache. Paying that cost ONCE here — instead of once per spec
# inside a tight Playwright navigation budget — is what makes running the whole
# Browser suite as an unattended gate feasible. Best-effort: warm-up failures
# never fail the run (the specs themselves are the real assertions), and when
# APP_URL is already warm (dev workflow up) each probe just returns fast.
warm() {
  local base="${APP_URL%/}"
  echo "==> Warming app server at $base (priming shared caches; one-time cold cost)"
  local route code
  # Public routes the marketing / consent / home specs navigate to.
  for route in /up / /pricing /contact /user/login; do
    code=$(curl -fsS -o /dev/null -w "%{http_code} %{time_total}s" --max-time 90 \
      "${base}${route}" 2>/dev/null || echo "down")
    echo "    warm ${route} -> ${code}"
  done

  # Authenticated editor warm-up. The biolink block editor
  # (/user/links/{id}/blocks) is the single heaviest authenticated render and
  # the card-gallery spec opens it; its cold first paint (Blade compile + block
  # catalogs + plan gates) otherwise lands inside the spec's navigation budget
  # and flakes. Log in as the demo user via the real CSRF-protected demo-login
  # form, then GET one editor page so that compile/opcache cost is paid here
  # instead. Best-effort throughout: any failure (no demo link, CSRF drift) just
  # leaves the editor cold and the generous navigationTimeout still absorbs it.
  local jar token loginhtml
  jar="$(mktemp)"
  loginhtml=$(curl -fsS -c "$jar" -b "$jar" --max-time 60 "${base}/user/login" 2>/dev/null || true)
  # Keep this best-effort under `set -euo pipefail`: if the markup changes or no
  # token is present, `grep` exits non-zero and (with pipefail) would otherwise
  # abort the whole run. `|| true` ensures a missing token just leaves $token
  # empty and we fall through to the "skipped" branch below.
  token=$(printf '%s' "$loginhtml" \
    | grep -oE 'name="_token" value="[^"]+"' \
    | head -1 \
    | sed -E 's/.*value="([^"]+)".*/\1/' || true)
  if [ -n "$token" ]; then
    code=$(curl -fsS -c "$jar" -b "$jar" -o /dev/null -w "%{http_code}" --max-time 60 \
      -X POST "${base}/user/demo-login" -d "_token=${token}" 2>/dev/null || echo "down")
    echo "    warm demo-login -> ${code}"
    # Link id 1 is the demo user's first link in the seeded DB; even if the spec
    # later opens a different link, hitting any editor page compiles the heavy
    # shared editor view. --max-time covers a cold first render.
    code=$(curl -fsS -c "$jar" -b "$jar" -o /dev/null -w "%{http_code} %{time_total}s" --max-time 90 \
      "${base}/user/links/1/blocks" 2>/dev/null || echo "down")
    echo "    warm /user/links/1/blocks -> ${code}"
  else
    echo "    warm editor -> skipped (no CSRF token from /user/login)"
  fi
  rm -f "$jar"
}
warm

echo "==> Running Playwright Browser specs against $APP_URL"
APP_URL="$APP_URL" pnpm exec playwright test "$@"
