#!/usr/bin/env bash
# Orchestrator for the Zio Browser sync plan-gate REAL-API e2e.
#
# One process does everything (backgrounded servers are reaped between tool
# calls in this environment):
#   1. Throwaway local Postgres cluster + full `migrate:fresh` of the 1inme app
#   2. Fixture seed via `artisan tinker` (3 plans: gated / capped / open,
#      user + real Sanctum token)
#   3. Real Laravel dev server (php -S — inherits DB_* env; artisan serve strips it)
#   4. Pre-seeds the Zio SQLite DB (node-ABI better-sqlite3)
#   5. Swaps in the Electron-ABI better-sqlite3 binary and runs the Electron
#      UI e2e under Xvfb (tests/e2e-sync-plan-gate/run.cjs)
#   6. Restores the node-ABI binary and runs raw-DB assertions (verify-db.cjs)
#
# Usage: bash artifacts/zio-browser/tests/e2e-sync-plan-gate/run.sh
set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ZIO_DIR="$(cd "$HERE/../.." && pwd)"
LARAVEL_ROOT="$(cd "$ZIO_DIR/../1inme" && pwd)"

PGPORT=55447
PGDIR="/tmp/zio-gate-e2e-pg.$$"
RUN_ID="$(date +%s)$$"
USERDATA="$(mktemp -d /tmp/zio-gate-e2e-userdata.XXXXXX)"
export ZIO_E2E_LOG="/tmp/zio-sync-plan-gate-e2e.log"

BSQ_DIR="$(cd "$ZIO_DIR" && node -p "require('path').dirname(require('fs').realpathSync(require.resolve('better-sqlite3/package.json')))" 2>/dev/null)" || { echo "FAIL: cannot resolve better-sqlite3"; exit 1; }
BSQ_BIN="$BSQ_DIR/build/Release/better_sqlite3.node"
NODE_PREBUILD="$ZIO_DIR/prebuilds/better-sqlite3-v11.10.0-abi137-linux-x64.node"
ELECTRON_PREBUILD="$ZIO_DIR/prebuilds/better-sqlite3-electron37.10.3-linux-x64.node"
[ -f "$ELECTRON_PREBUILD" ] || { echo "FAIL: missing $ELECTRON_PREBUILD (build with scripts/rebuild-native.sh semantics: node-gyp --runtime=electron)"; exit 1; }

PHP_PID=""
cleanup() {
  [ -n "$PHP_PID" ] && kill "$PHP_PID" 2>/dev/null
  pg_ctl -D "$PGDIR" stop -m immediate >/dev/null 2>&1
  rm -rf "$PGDIR"
  # Always restore the node-ABI binary for vitest runs.
  [ -f "$NODE_PREBUILD" ] && cp "$NODE_PREBUILD" "$BSQ_BIN" 2>/dev/null
}
trap cleanup EXIT

echo "== 1) throwaway Postgres on :$PGPORT"
initdb -D "$PGDIR" -U postgres --auth=trust >/dev/null 2>&1 || { echo "FAIL: initdb"; exit 1; }
pg_ctl -D "$PGDIR" -o "-p $PGPORT -k /tmp" -l "$PGDIR.log" start >/dev/null 2>&1 || { echo "FAIL: pg start"; exit 1; }
for i in $(seq 1 30); do
  psql -h 127.0.0.1 -p "$PGPORT" -U postgres -d postgres -c 'select 1' >/dev/null 2>&1 && break
  sleep 0.5
done
createdb -h 127.0.0.1 -p "$PGPORT" -U postgres zio_gate_e2e || { echo "FAIL: createdb"; exit 1; }

# Laravel env overrides — blank DB_URL/DATABASE_URL so discrete creds win.
LARAVEL_ENV=(DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=$PGPORT DB_DATABASE=zio_gate_e2e
  DB_USERNAME=postgres DB_PASSWORD= DB_SSLMODE=disable PGSSLMODE=disable DB_URL= DATABASE_URL=)

echo "== 2) migrate:fresh"
(cd "$LARAVEL_ROOT" && env "${LARAVEL_ENV[@]}" php artisan migrate:fresh --force >/tmp/zio-gate-migrate.log 2>&1) \
  || { echo "FAIL: migrate:fresh (see /tmp/zio-gate-migrate.log)"; tail -5 /tmp/zio-gate-migrate.log; exit 1; }

echo "== 3) seed fixtures via tinker"
SEED_PHP='
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
$mk = function (array $features, string $tag) {
  return Plan::create([
    "name" => "E2E " . ucfirst($tag), "slug" => "e2e-gate-" . $tag . "-" . Str::lower(Str::random(6)),
    "monthly_price" => 0, "annual_price" => 0, "trial_days" => 0, "grace_days" => 0,
    "refund_window_days" => 0, "status" => "active", "sort_order" => 99, "features" => $features,
  ]);
};
$gated  = $mk(["browser_sync" => false], "gated");
$capped = $mk(["browser_sync" => true, "max_browser_sync_items" => 2], "capped");
$open   = $mk(["browser_sync" => true], "open");
$u = User::create([
  "name" => "Zio Gate E2E", "email" => "e2e-zio-gate-'"$RUN_ID"'@example.test",
  "password" => Hash::make("password"), "plan_id" => $gated->id,
  "status" => "active", "email_verified_at" => now(),
]);
$u->onboarded_at = now(); $u->save();
$token = $u->createToken("zio-gate-e2e")->plainTextToken;
echo "SEED_JSON:" . json_encode([
  "userId" => $u->id, "token" => $token,
  "gated" => $gated->id, "capped" => $capped->id, "open" => $open->id,
]) . "\n";
'
SEED_OUT="$(cd "$LARAVEL_ROOT" && env "${LARAVEL_ENV[@]}" php artisan tinker --execute="$SEED_PHP" 2>&1)"
SEED_JSON="$(echo "$SEED_OUT" | grep -o 'SEED_JSON:{.*}' | head -1 | cut -d: -f2-)"
[ -n "$SEED_JSON" ] || { echo "FAIL: seed produced no SEED_JSON"; echo "$SEED_OUT" | tail -20; exit 1; }
USER_ID="$(echo "$SEED_JSON" | node -e "process.stdin.on('data',d=>console.log(JSON.parse(d).userId))")"
TOKEN="$(echo "$SEED_JSON" | node -e "process.stdin.on('data',d=>console.log(JSON.parse(d).token))")"
PLAN_CAPPED="$(echo "$SEED_JSON" | node -e "process.stdin.on('data',d=>console.log(JSON.parse(d).capped))")"
PLAN_OPEN="$(echo "$SEED_JSON" | node -e "process.stdin.on('data',d=>console.log(JSON.parse(d).open))")"
echo "seeded user=$USER_ID plans capped=$PLAN_CAPPED open=$PLAN_OPEN"

echo "== 4) boot Laravel (php -S)"
LARAVEL_PORT=8199
ROUTER="$LARAVEL_ROOT/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
(cd "$LARAVEL_ROOT/public" && env "${LARAVEL_ENV[@]}" PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:$LARAVEL_PORT "$ROUTER" >/tmp/zio-gate-laravel.log 2>&1) &
PHP_PID=$!
LARAVEL_BASE="http://127.0.0.1:$LARAVEL_PORT"
for i in $(seq 1 60); do
  curl -sf "$LARAVEL_BASE/up" >/dev/null 2>&1 && break
  sleep 1
done
curl -sf "$LARAVEL_BASE/up" >/dev/null 2>&1 || { echo "FAIL: Laravel never came up"; tail -5 /tmp/zio-gate-laravel.log; exit 1; }
echo "Laravel up at $LARAVEL_BASE"

echo "== 5) seed the Zio SQLite DB (node ABI)"
cp "$NODE_PREBUILD" "$BSQ_BIN"
env ZIO_USER_DATA="$USERDATA" LARAVEL_BASE="$LARAVEL_BASE" SANCTUM_TOKEN="$TOKEN" \
    SEED_USER_ID="$USER_ID" node "$HERE/seed-zio-db.cjs" || { echo "FAIL: seed-zio-db"; exit 1; }

echo "== 6) Electron UI e2e (Electron ABI better-sqlite3)"
cp "$ELECTRON_PREBUILD" "$BSQ_BIN"
env ZIO_USER_DATA="$USERDATA" LARAVEL_BASE="$LARAVEL_BASE" SEED_USER_ID="$USER_ID" \
    PLAN_CAPPED_ID="$PLAN_CAPPED" PLAN_OPEN_ID="$PLAN_OPEN" \
    PGHOST=127.0.0.1 PGPORT=$PGPORT PGUSER=postgres PGDATABASE=zio_gate_e2e \
    xvfb-run -a node "$HERE/run.cjs"
UI_RC=$?

echo "== 7) raw-DB assertions (node ABI restored)"
cp "$NODE_PREBUILD" "$BSQ_BIN"
env ZIO_USER_DATA="$USERDATA" node "$HERE/verify-db.cjs"
DB_RC=$?

rm -rf "$USERDATA"
if [ $UI_RC -ne 0 ] || [ $DB_RC -ne 0 ]; then
  echo "RESULT: FAILED (ui=$UI_RC db=$DB_RC) — see $ZIO_E2E_LOG"
  exit 1
fi
echo "RESULT: PASS"
