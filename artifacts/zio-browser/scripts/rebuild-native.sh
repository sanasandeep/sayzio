#!/usr/bin/env bash
# Force better-sqlite3's native binary to the exact Electron ABI + CPU arch.
#
# Why this exists: `electron-builder install-app-deps --arch <arch>` silently
# no-ops in this pnpm workspace when a build/Release binary already exists —
# v0.1.10 shipped an x86_64 better_sqlite3.node inside the arm64 mac app
# (dlopen "incompatible architecture" at runtime). prebuild-install with
# --force is deterministic, and the arch assertion below fails the build
# instead of shipping a broken package.
#
# Usage: scripts/rebuild-native.sh <x64|arm64>
set -euo pipefail

ARCH="$1"
case "$ARCH" in
  x64) WANT="x86_64" ;;
  arm64) WANT="arm64" ;;
  *) echo "usage: $0 <x64|arm64>" >&2; exit 2 ;;
esac

ELECTRON_VERSION=$(node -p "require('electron/package.json').version")
BSQ_DIR=$(node -p "require('path').dirname(require('fs').realpathSync(require.resolve('better-sqlite3/package.json')))")
BIN="$BSQ_DIR/build/Release/better_sqlite3.node"

echo "Rebuilding better-sqlite3 for electron@$ELECTRON_VERSION $ARCH in $BSQ_DIR"
rm -f "$BIN"
if ! (cd "$BSQ_DIR" && npx --yes prebuild-install \
    --platform=darwin --arch="$ARCH" \
    --runtime=electron --target="$ELECTRON_VERSION" --force --verbose); then
  echo "prebuild-install failed; falling back to install-app-deps --arch $ARCH"
  pnpm exec electron-builder install-app-deps --arch "$ARCH"
fi

file "$BIN"
if ! file "$BIN" | grep -q "$WANT"; then
  echo "::error::better_sqlite3.node is not $WANT after rebuild for $ARCH" >&2
  exit 1
fi
echo "OK: better_sqlite3.node is $WANT"
