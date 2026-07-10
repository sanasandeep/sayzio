---
name: Expo dev server won't start — metro packages not hoisted after merges
description: "@expo/cli crashes at boot with 'Cannot find module metro-runtime/package.json' when node_modules is left half-installed after a wave of merges; fix is pnpm install --force.
---

Symptom sequence when the 1inme-mobile expo workflow is FAILED after many task merges:
1. Web bundle fails with `TypeError: dependencies is not iterable` in metro `buildSubgraph.js` — mixed metro versions (stale `metro@0.83.6` orphan dirs coexisting with the pinned `0.83.3`), and the app symlink still resolving an OLD `expo-router` because node_modules is stale.
2. After a plain `pnpm install` syncs the symlinks, it flips to `Error: Cannot find module 'metro-runtime/package.json'` (require stack ends in `@expo/cli/.../withMetroMultiPlatform`) and the dev server never starts.

Root cause: node_modules is in a corrupted/incomplete state. `@expo/cli` only declares `@expo/metro` yet calls `require.resolve('metro-runtime/package.json')`, so metro-* packages MUST be hoisted into pnpm's virtual store `node_modules/.pnpm/node_modules/`. After the merges only `metro` was symlinked there; `metro-runtime` (and siblings) were missing. A plain `pnpm install` reports "Already up to date" and does NOT repair the missing hoist links.

Fix (no code/lockfile change — env repair only):
- `pnpm install --force` — rebuilds the whole module tree from the (correct) lockfile and restores every `metro-*` symlink in `.pnpm/node_modules`. This is heavy/slow (can run 10+ min) and may get killed by the bash timeout while STILL running in the background — poll `pgrep -f "pnpm install --force"` and check `ls node_modules/.pnpm/node_modules/metro-runtime` for the fix landing, don't relaunch it.
- Then clear caches: `rm -rf artifacts/1inme-mobile/.expo /tmp/metro-* /tmp/haste-map-* node_modules/.cache` and `restart_workflow "artifacts/1inme-mobile: expo"`.

Verify: `curl -s -o /dev/null -w "%{http_code}" localhost:23680/` returns 200 (find the port via `ps aux | grep "expo start"`), and `node -e "require.resolve('metro-runtime/package.json',{paths:[<@expo/cli dir>]})"` resolves to `metro-runtime@0.83.3`.

**Why:** the metro-version overrides (pinning metro/metro-* to 0.83.3 in pnpm-workspace.yaml) plus SDK package bumps landing across separate merges leave node_modules out of sync with the lockfile; the incomplete hoist is invisible to `git status` (node_modules is gitignored) so it looks like a code bug but is purely an install-state repair.
