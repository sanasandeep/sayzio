# APK size: before / after (Task: shrink preview APK)

## Before (measured, build finished 2026-08-05)
Universal preview APK, no R8, no resource shrinking: **141.8 MB** (141,838,702 bytes).

Breakdown (uncompressed, `unzip -l`):

| Section | Size |
|---|---|
| lib/x86_64 | 26.3 MB |
| lib/x86 | 26.2 MB |
| lib/arm64-v8a | 23.9 MB |
| lib/armeabi-v7a | 16.3 MB |
| classes*.dex | 32.6 MB |
| res/ | 22.7 MB |
| assets/ | 7.3 MB |

## Changes
1. **Preview profile is arm64-only** — `eas.json` preview sets
   `ORG_GRADLE_PROJECT_reactNativeArchitectures=arm64-v8a`, dropping the x86,
   x86_64 and armeabi-v7a native libs (**−68.8 MB** right away). A
   `preview-universal` profile keeps the old all-ABI behaviour when needed
   (e.g. testing on an x86_64 emulator).
2. **R8 minification + resource shrinking** enabled for all release builds
   (preview APK and production AAB) via `expo-build-properties`
   (`enableProguardInReleaseBuilds`, `enableShrinkResourcesInReleaseBuilds`),
   with keep rules for reflection-heavy modules: expo-notifications,
   react-native-nfc-manager, expo-share-intent, expo-local-authentication.
3. **Dependency hygiene** — all runtime modules moved from devDependencies to
   dependencies; removed unused packages: `expo-glass-effect`, `expo-symbols`,
   `expo-status-bar`, `@expo-google-fonts/inter`,
   `@stardazed/streams-text-encoding`, `@ungap/structured-clone`
   (verified: zero imports anywhere in app/, components/, lib/, hooks/,
   contexts/, scripts/, server/). Duplicate `expo`/`react`/`react-native`
   entries across the two sections collapsed.

## Expected after
- arm64-only ABI: 141.8 − 68.8 ≈ **73 MB** before shrinking.
- R8 typically cuts dex 30–50 % (32.6 → ~18–23 MB) and resource shrinking
  trims part of the 22.7 MB res/ → expected preview APK **~60–70 MB**.
- Play Store install size for end users is smaller still: production ships an
  AAB, so the store serves per-device ABI + density splits.

## Verification status
- `npx expo config` resolves cleanly with the new plugin config; typecheck,
  expo-project-link + eas-lockfile-registry guards, and the expo web bundle
  all pass. EAS accepted the build (upload + fingerprint OK) but the Expo
  free-plan Android build quota is exhausted until **2026-09-01**, so the
  actual "after" APK could not be produced yet. First build after the reset:
  `EAS_NO_VCS=1 npx -y eas-cli build --platform android --profile preview --non-interactive --no-wait`
  then compare the artifact size against 141.8 MB.
- After the first R8 build, smoke-test the reflection-dependent features on a
  device: push notifications, NFC tag write, share-intent receive, biometric
  unlock. If anything breaks, add keep rules to the `extraProguardRules`
  block in app.json.

## Further size levers (not done)
- Hermes bytecode is already the RN 0.81 default (JS ships as bytecode).
- Asset audit: assets/ is only 7.3 MB; biggest remaining lever is res/ after
  shrinking (check `icon-appstore.png` reuse as splash at full size).
- A `.easignore` already keeps the upload archive at ~40 MB.
