#!/usr/bin/env bash
# Assert the PACKAGED app ships the right-arch better_sqlite3.node in
# app.asar.unpacked. Fails the build instead of publishing a broken release
# (v0.1.10 regression guard).
#
# macOS: checks the .app bundle (Mach-O). Linux: checks the unpacked dir
# electron-builder leaves next to the AppImage/deb (ELF; same payload).
#
# Usage: scripts/verify-packaged-native.sh <x64|arm64>
set -euo pipefail

ARCH="$1"
REL_PATH="app.asar.unpacked/node_modules/better-sqlite3/build/Release/better_sqlite3.node"

case "$(uname -s)" in
  Darwin)
    case "$ARCH" in
      x64)   WANT="x86_64"; APP_DIR="release/mac" ;;
      arm64) WANT="arm64";  APP_DIR="release/mac-arm64" ;;
      *) echo "usage: $0 <x64|arm64>" >&2; exit 2 ;;
    esac
    BIN="$APP_DIR/Zio Browser.app/Contents/Resources/$REL_PATH"
    ;;
  Linux)
    case "$ARCH" in
      x64)   WANT="x86-64";  APP_DIR="release/linux-unpacked" ;;
      arm64) WANT="aarch64"; APP_DIR="release/linux-arm64-unpacked" ;;
      *) echo "usage: $0 <x64|arm64>" >&2; exit 2 ;;
    esac
    BIN="$APP_DIR/resources/$REL_PATH"
    ;;
  *) echo "unsupported host OS: $(uname -s)" >&2; exit 2 ;;
esac

if [ ! -f "$BIN" ]; then
  echo "::error::packaged native binary missing: $BIN" >&2
  exit 1
fi
file "$BIN"
if ! file "$BIN" | grep -q "$WANT"; then
  echo "::error::packaged better_sqlite3.node in $APP_DIR is not $WANT" >&2
  exit 1
fi
echo "OK: packaged $APP_DIR native binary is $WANT"
