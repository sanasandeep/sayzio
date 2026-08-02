#!/usr/bin/env bash
# Post-package verification for the Linux release artifacts in release/.
# Catches packaging regressions before the release job can ship them
# (v0.3.8 was blocked by deb metadata that only surfaced in the real CI run).
#
# Asserts:
#   1. Exactly one .AppImage, one .deb and latest-linux.yml exist in release/
#   2. latest-linux.yml references the AppImage with matching sha512 + size
#   3. dpkg-deb -I on the .deb shows Architecture: amd64, a non-empty
#      Maintainer, a Homepage, and every expected Depends entry
#   4. better_sqlite3.node inside BOTH packages is ELF x86-64
#
# Usage: scripts/verify-linux-release.sh
# Run from artifacts/zio-browser (expects release/ next to it).
set -euo pipefail

RELEASE_DIR="release"
NATIVE_REL="app.asar.unpacked/node_modules/better-sqlite3/build/Release/better_sqlite3.node"
EXPECTED_DEPENDS=(
  libgtk-3-0
  libnotify4
  libnss3
  libxss1
  libxtst6
  xdg-utils
  libatspi2.0-0
  libuuid1
  libsecret-1-0
)

fail() {
  echo "::error::$*" >&2
  exit 1
}

[ -d "$RELEASE_DIR" ] || fail "release directory missing: $RELEASE_DIR"

# ── 1. Required artifacts exist ──────────────────────────────────────────────
shopt -s nullglob
appimages=("$RELEASE_DIR"/*.AppImage)
debs=("$RELEASE_DIR"/*.deb)
shopt -u nullglob

[ "${#appimages[@]}" -eq 1 ] || fail "expected exactly 1 .AppImage in $RELEASE_DIR/, found ${#appimages[@]}: ${appimages[*]:-none}"
[ "${#debs[@]}" -eq 1 ] || fail "expected exactly 1 .deb in $RELEASE_DIR/, found ${#debs[@]}: ${debs[*]:-none}"
[ -f "$RELEASE_DIR/latest-linux.yml" ] || fail "auto-update feed missing: $RELEASE_DIR/latest-linux.yml"

APPIMAGE="${appimages[0]}"
DEB="${debs[0]}"
YML="$RELEASE_DIR/latest-linux.yml"
echo "Found: $APPIMAGE, $DEB, $YML"

# ── 2. latest-linux.yml matches the AppImage ─────────────────────────────────
APPIMAGE_NAME="$(basename "$APPIMAGE")"
grep -q "url: $APPIMAGE_NAME" "$YML" \
  || fail "latest-linux.yml does not reference $APPIMAGE_NAME (url mismatch — the update feed would 404)"

# electron-updater yml stores sha512 as base64. The top-level `sha512:` /
# `path:` keys describe the primary artifact; the files[] entry for the
# AppImage must match too. Compare against both occurrences.
ACTUAL_SHA512="$(openssl dgst -sha512 -binary "$APPIMAGE" | base64 -w0)"
if ! grep -q "sha512: $ACTUAL_SHA512" "$YML"; then
  echo "actual AppImage sha512 (base64): $ACTUAL_SHA512" >&2
  echo "latest-linux.yml sha512 lines:" >&2
  grep "sha512:" "$YML" >&2 || true
  fail "latest-linux.yml sha512 does not match $APPIMAGE_NAME (updater would reject the download)"
fi

ACTUAL_SIZE="$(stat -c%s "$APPIMAGE")"
if ! grep -Eq "size: $ACTUAL_SIZE\$" "$YML"; then
  echo "actual AppImage size: $ACTUAL_SIZE" >&2
  grep "size:" "$YML" >&2 || true
  fail "latest-linux.yml size does not match $APPIMAGE_NAME ($ACTUAL_SIZE bytes)"
fi
echo "OK: latest-linux.yml url/sha512/size match $APPIMAGE_NAME"

# ── 3. deb control metadata ──────────────────────────────────────────────────
command -v dpkg-deb >/dev/null || fail "dpkg-deb not available on this runner"
CONTROL="$(dpkg-deb -I "$DEB")"

echo "$CONTROL" | grep -Eq '^ *Architecture: amd64$' \
  || fail ".deb Architecture is not amd64: $(echo "$CONTROL" | grep -E '^ *Architecture:' || echo '<missing>')"

echo "$CONTROL" | grep -Eq '^ *Maintainer: .+' \
  || fail ".deb Maintainer field is missing/empty (electron-builder linux.maintainer regression)"

echo "$CONTROL" | grep -Eq '^ *Homepage: .+' \
  || fail ".deb Homepage field is missing (package.json homepage regression — the field that blocked v0.3.8)"

DEPENDS_LINE="$(echo "$CONTROL" | grep -E '^ *Depends:' || true)"
[ -n "$DEPENDS_LINE" ] || fail ".deb has no Depends line at all"
for dep in "${EXPECTED_DEPENDS[@]}"; do
  # Match a whole package name token (avoid libnss3 matching libnss3-tools etc.)
  echo "$DEPENDS_LINE" | grep -Eq "[ ,]${dep}([ ,(]|\$)" \
    || fail ".deb Depends is missing '$dep' (deb deps drift; got: ${DEPENDS_LINE#*Depends: })"
done
echo "OK: deb control metadata (amd64, Maintainer, Homepage, ${#EXPECTED_DEPENDS[@]} Depends entries)"

# ── 4. better_sqlite3.node is ELF x86-64 inside BOTH packages ────────────────
check_native() {
  local bin="$1" label="$2"
  [ -f "$bin" ] || fail "$label: packaged native binary missing at $bin"
  local desc
  desc="$(file "$bin")"
  echo "$label: $desc"
  echo "$desc" | grep -q 'x86-64' \
    || fail "$label: better_sqlite3.node is not ELF x86-64"
}

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# AppImage: --appimage-extract works without FUSE.
chmod +x "$APPIMAGE"
( cd "$WORK" && "$OLDPWD/$APPIMAGE" --appimage-extract >/dev/null ) \
  || fail "failed to extract $APPIMAGE_NAME (--appimage-extract)"
check_native "$WORK/squashfs-root/resources/$NATIVE_REL" "AppImage"

# deb: unpack the data archive.
dpkg-deb -x "$DEB" "$WORK/deb" || fail "failed to unpack $(basename "$DEB")"
check_native "$WORK/deb/opt/Zio Browser/resources/$NATIVE_REL" "deb"

echo "OK: Linux release artifacts verified (AppImage + deb + latest-linux.yml)"
