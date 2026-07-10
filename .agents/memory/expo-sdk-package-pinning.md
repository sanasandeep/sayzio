---
name: Expo SDK package pinning & metro override
description: How mobile Expo package versions and the metro override must be kept consistent so the web preview doesn't blank out.
---

# Expo SDK 54 package pinning (1inme-mobile)

`pnpm exec expo install --check` (run inside `artifacts/1inme-mobile`) is the
authoritative source for the exact patch versions each package must use for the
installed Expo SDK. Bump `package.json` to the "expected version" it prints, then
`pnpm install`, then re-run `--check` until it says "Dependencies are up to date".

**Why:** transitive dep upgrades silently nudge Expo package versions and can
introduce a Metro version conflict that blanks the mobile web preview. There is
(as of now) no CI guard for this class of drift.

**How to apply:**
- Use tilde (`~`) ranges for Expo unimodule packages, not caret (`^`). Some
  unimodules (expo-clipboard, expo-file-system, expo-local-authentication) have
  their OWN independent major versions that do NOT track the SDK major — a caret
  range there (e.g. `^55.x`) resolves to a wrong, SDK-incompatible major. The
  SDK-correct majors can look surprisingly low (clipboard ~8, file-system ~19,
  local-authentication ~17 under SDK 54).
- `pnpm-workspace.yaml` pins every `metro`/`metro-*` package to a single version
  via `overrides` (0.83.3 for SDK 54). After bumping Expo packages, confirm the
  lockfile still has ONE version of each real metro-* bundler package. Note the
  `@expo/metro`, `@expo/metro-config`, `@expo/metro-runtime` packages have their
  own versions (54.x / 6.x) and are NOT the metro bundler — don't confuse them
  for duplicate metro entries when grepping.
