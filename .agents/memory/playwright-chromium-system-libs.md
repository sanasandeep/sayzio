---
name: Playwright Chromium missing system libs in fresh envs
description: Fresh isolated envs lack the shared libraries Chromium needs; installSystemDependencies fixes mass e2e failures.
---
# Playwright Chromium system libs

Fresh/isolated task envs can mass-fail EVERY browser spec at launch ("error
while loading shared libraries"). Fix via installSystemDependencies with:
glib, nss, nspr, atk, at-spi2-atk, dbus, cups, expat, libdrm, mesa,
libxkbcommon, pango, cairo, alsa-lib, the xorg.* libs, plus `libgbm` and
`systemdLibs` (libudev). Verify with `ldd` on the chrome binary — clean output
means launches work. Don't confuse this infra failure with real test failures.
