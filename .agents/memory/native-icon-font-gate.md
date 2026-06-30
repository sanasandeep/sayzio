---
name: Native icon-font CI gate
description: Why the mobile login-icon check needs a native-bundle variant, and how it boots one without expo export.
---

# Native login icon-font gate (mobile)

The web icon gate (`e2e-mobile-icons` → `test:icon-fonts-e2e`) only proves icons
on the Expo **web** bundle, where fonts are CSS `@font-face`. Native bundles
embed each icon `.ttf` as a **Metro packager asset** and register it via
`useFonts({...Ionicons.font, ...Feather.font})` in `app/_layout.tsx`. So a glyph
can render on web but be **tofu** on a phone. The native gate
(`e2e-mobile-icons-native` → `test:native-icon-fonts-e2e`) closes that gap.

**Why not `expo export --platform ios`:** too slow/flaky in this env (>5min, bg
procs reaped). Instead model on the PROVEN production `scripts/build.js`: boot a
throwaway production Metro (`--no-dev --minify`), download the native `.bundle`
(`platform=ios&dev=false&minify=true`), then regex-scan it.

**How to apply / extend:**
- Asset presence regex keys off `name:"<X>"` + `type:"ttf"` packager markers —
  same literal shape `build.js` relies on (object literal, UNquoted keys, double
  quotes). Family registration matches the `createIconSet` family string
  (`ionicons` lowercase, `Feather`).
- Asset-bundled ≠ registered: a font imported by some screen still bundles its
  `.ttf` even if dropped from startup `useFonts()`. That's why the check ALSO
  statically asserts `...<Set>.font` is spread into the root `useFonts()` call —
  that static assert is the real "registration regressed" guard.
- Best-effort contract (mirrors the web harness): SKIP (exit 0) if Metro can't
  boot/compile in the wall-clock budget; FAIL (exit 1) only on a bundle that DID
  compile. Knobs: `NATIVE_BUNDLE_FILE` (reuse a prebuilt bundle),
  `NATIVE_PLATFORM` (ios default), `NATIVE_BUNDLE_DEADLINE_MS`.
- Verify via `startValidationRun` (NOT bash — 120s cap; cold native bundle is
  minutes). Metro caches the compiled bundle, so a repeat run is ~seconds.
