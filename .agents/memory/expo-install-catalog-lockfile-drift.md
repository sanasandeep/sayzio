---
name: Expo-install adds dependencies block → frozen-lockfile drift
description: Why merged mobile expo-install tasks silently break the next deploy/merge, and the safe fix.
---

# Expo-install `dependencies` block breaks frozen-lockfile

When a task runs `expo install` / adds a native module (e.g. react-native-purchases,
react-native-webview) in `artifacts/1inme-mobile`, the expo installer appends a NEW
`dependencies` block to that package.json containing `expo`, `react`, `react-native`
with **literal** versions (e.g. `"react": "19.1.0"`) — even though those already live
in `devDependencies` as `"catalog:"`. The lockfile still records the importer with
`catalog:`, so root `pnpm install --frozen-lockfile` fails:
`ERR_PNPM_OUTDATED_LOCKFILE ... react (lockfile: catalog:, manifest: 19.1.0)`.

**Why it matters:** `frozen-lockfile` is the FIRST step of both the deploy build and
the post-merge setup script. This drift silently breaks the next publish AND every
future task merge until reconciled. It won't surface in the mobile dev workflow
(which doesn't run frozen install).

**Why it's format-only:** the workspace catalog pins `react: 19.1.0`
(pnpm-workspace.yaml), so `catalog:` and `19.1.0` resolve to the same version — the
break is purely a specifier-format mismatch, not a real version conflict.

**Fix (two steps):**
1. Set the drifted specifier back to the convention: `"react": "catalog:"` in the
   mobile package.json `dependencies` block.
2. The `dependencies` block also re-classifies expo-* packages the lockfile had
   elsewhere, so hand-editing react alone is NOT enough — regenerate:
   `pnpm install --lockfile-only`, then confirm with `pnpm install --frozen-lockfile`.

Commit BOTH package.json + pnpm-lock.yaml. Do not use `--no-frozen-lockfile` as a
"fix" — it only hides the drift from the current run; the committed lockfile stays
stale and the next deploy still fails.
