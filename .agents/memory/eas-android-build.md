---
name: EAS Android APK builds from this workspace
description: How to run eas-cli builds for artifacts/1inme-mobile from the Replit env (auth, gotchas, polling)
---

- Auth: `EXPO_TOKEN` secret authenticates eas-cli non-interactively (robot "SAYZIO", account **eefind** — not "sayzio"). Project: `@eefind/sayzio-mobile`, projectId `7d456b75-3a4b-4f59-95b1-369f0cb1ba77` (now in app.json).
- **Why the first init failed:** a placeholder `extra.eas.projectId` makes `eas project:init` think it's already linked, then GraphQL fails on "Invalid UUID appId". Remove the field first, then `project:init --non-interactive --force`.
- **expo-share-intent duplicate ShareExtension:** the plugin injects its own iOS appExtension; ANY manual `extra.eas.build.experimental.ios.appExtensions` ShareExtension entry makes `expo config` fatal ("more than one appExtensions"). Remove the manual block entirely — deduping to one still fails.
- **Git is blocked:** eas-cli's default VCS mode touches `.git/index.lock`, which the agent sandbox forbids. Always run with `EAS_NO_VCS=1`.
- **Upload exceeds bash timeout:** project archive is ~1.0 GB (monorepo, no `.easignore`), compress+upload ≈ 90s+. `nohup`'d children get reaped when the bash call ends — run the build via a registered validation workflow (`setValidationCommand` + `restart_workflow`) with `--no-wait`, then poll `eas build:view <id> --json` (calls flakily time out; wrap in `timeout 60`, retry).
- Preview profile = APK, signed with an EAS-generated remote keystore; production profile = AAB for Play Store.
- **How to apply:** future builds are just `EAS_NO_VCS=1 npx -y eas-cli build --platform android --profile preview --non-interactive --no-wait` from artifacts/1inme-mobile. Consider adding a `.easignore` to cut the 1 GB archive.
