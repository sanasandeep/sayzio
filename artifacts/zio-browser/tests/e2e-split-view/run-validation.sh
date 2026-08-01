# Validation wrapper for the tab-mode / split-view Electron harnesses.
#
# End-to-end, self-contained:
#   1. Builds the app (renderer + main) and the preload bundle.
#   2. Ensures the Electron binary is installed (pnpm skips postinstall here).
#   3. Ensures better-sqlite3 has a Node-ABI build (source of the restore copy),
#      then swaps in an Electron-ABI build for the live-Electron run.
#   4. Runs the harnesses under xvfb:
#        run.cjs         — Website+Website split (focus/dim/divider deep checks)
#        run-modes.cjs   — every OTHER TabModeSwitcher mode (entry/exit +
#                          toolbar routing)
#        run-restore.cjs — split-tab session-restore across a full app
#                          restart (same user-data dir, two launches)
#   5. ALWAYS restores the Node-ABI better-sqlite3 binary afterwards (trap),
#      so vitest/node consumers keep working.
#
# Usage: bash artifacts/zio-browser/tests/e2e-split-view/run-validation.sh [harness.cjs ...]
#        (no args = run both harnesses)
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$APP_DIR"

echo "── Building zio-browser (renderer + main + preload) ──"
pnpm run build
pnpm run build:preload

echo "── Ensuring Electron binary ──"
if [ ! -x node_modules/electron/dist/electron ]; then
  node node_modules/electron/install.js
fi

echo "── Ensuring Node-ABI better-sqlite3 (restore source) ──"
node scripts/ensure-better-sqlite3.cjs

BSQ_DIR=$(node -p "require('path').dirname(require('fs').realpathSync(require.resolve('better-sqlite3/package.json')))")
BIN="$BSQ_DIR/build/Release/better_sqlite3.node"
NODE_ABI_BACKUP="$BIN.node-abi-backup"
ELECTRON_VERSION=$(node -p "require('electron/package.json').version")
ELECTRON_ABI_CACHE="$APP_DIR/prebuilds/better-sqlite3-electron${ELECTRON_VERSION}-$(uname -s | tr 'A-Z' 'a-z')-$(node -p process.arch).node"

cp "$BIN" "$NODE_ABI_BACKUP"
restore() {
  if [ -f "$NODE_ABI_BACKUP" ]; then
    mv -f "$NODE_ABI_BACKUP" "$BIN"
    echo "── Restored Node-ABI better-sqlite3 binary ──"
  fi
}
trap restore EXIT

echo "── Swapping better-sqlite3 to the Electron ABI (electron@$ELECTRON_VERSION) ──"
if [ -f "$ELECTRON_ABI_CACHE" ]; then
  echo "using cached Electron-ABI binary: $ELECTRON_ABI_CACHE"
  cp "$ELECTRON_ABI_CACHE" "$BIN"
else
  rm -f "$BIN"
  (cd "$BSQ_DIR" && npx --yes prebuild-install \
    --platform="$(node -p process.platform)" --arch="$(node -p process.arch)" \
    --runtime=electron --target="$ELECTRON_VERSION" --force) \
    || (cd "$BSQ_DIR" && npx --yes node-gyp rebuild --release \
          --runtime=electron --target="$ELECTRON_VERSION" \
          --dist-url=https://electronjs.org/headers) \
    || true
  if [ ! -f "$BIN" ]; then
    echo "ERROR: could not produce an Electron-ABI better-sqlite3 binary" >&2
    exit 1
  fi
  mkdir -p "$APP_DIR/prebuilds"
  cp "$BIN" "$ELECTRON_ABI_CACHE" || true
fi

HARNESSES=("$@")
if [ ${#HARNESSES[@]} -eq 0 ]; then
  HARNESSES=(run.cjs run-modes.cjs run-restore.cjs)
fi

for harness in "${HARNESSES[@]}"; do
  echo "── Running $harness under xvfb ──"
  xvfb-run -a node "tests/e2e-split-view/$harness"
done
