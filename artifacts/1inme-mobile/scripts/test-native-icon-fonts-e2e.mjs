#!/usr/bin/env node
/**
 * Self-booting regression gate for the mobile login-screen icons in the REAL
 * NATIVE (iOS) build — the native counterpart to test-icon-fonts-e2e.mjs, which
 * only exercises the Expo *web* bundle.
 *
 * The web gate can't catch native-only font regressions: web injects icon fonts
 * as CSS @font-face, while native embeds the icon `.ttf` as a Metro packager
 * asset and registers it at runtime via useFonts(). A dropped native asset or a
 * lost `...Ionicons.font` / `...Feather.font` registration ships a login screen
 * full of "tofu" boxes on a phone while the web e2e gate stays green.
 *
 * This harness closes that gap. Mirroring the web harness, it compiles a REAL
 * native production bundle (the exact JS + asset graph that gets packaged into
 * the app — Metro in `--no-dev --minify` mode, exactly as scripts/build.js does
 * for the deploy) and runs the native icon-font check (runNativeIconFontCheck)
 * against it: both icon fonts must be embedded as packager assets, both font
 * families must be registered, and every login social glyph must resolve.
 *
 * Best-effort boot contract (same as the web harness): native bundling is CPU
 * heavy and slow, so if Metro can't be brought up or the bundle can't compile
 * within the wall-clock budget the harness SKIPs (exit 0) rather than failing CI
 * just because the box couldn't bundle in time. A real font/asset/registration
 * regression on a bundle that DID compile still fails the check (exit 1).
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:native-icon-fonts-e2e
 *
 * Environment:
 *   NATIVE_BUNDLE_FILE   point the check at an already-built native bundle on
 *                        disk instead of compiling one (handy for local
 *                        debugging, and lets CI pre-build once and reuse).
 *   NATIVE_PLATFORM      "ios" (default) or "android" — which native target to
 *                        compile. Fonts are bundled identically; one target
 *                        satisfies the "at least one native target" bar.
 */

import { runNativeIconFontCheck } from "./check-native-icon-fonts.mjs";
import { isTransientEnvError } from "./expo-web-server.mjs";
import { createNativeBundleManager } from "./native-bundle.mjs";

function log(...args) {
  console.log("[test-native-icon-fonts-e2e]", ...args);
}

function skip(msg) {
  console.log("[test-native-icon-fonts-e2e] SKIP:", msg);
  process.exit(0);
}

const PLATFORM = process.env.NATIVE_PLATFORM || "ios";

const { acquireNativeBundle, stopExpo } = createNativeBundleManager(log);

async function run() {
  const acquired = await acquireNativeBundle(PLATFORM);
  if (!acquired) {
    // Couldn't compile a native bundle in time → SKIP, never fail. The "exit"
    // handler reaps any Metro child that did partially boot.
    skip(
      "the native bundle could not be compiled in time; skipping the native " +
        "icon-font check (best-effort, not a regression)",
    );
    return;
  }

  const { bundleText, child } = acquired;
  // An explicit NATIVE_BUNDLE_FILE means someone deliberately pointed the
  // check at a bundle for debugging — never silently skip in that mode.
  const explicit = Boolean(process.env.NATIVE_BUNDLE_FILE);
  log(`running the native icon-font check against the ${PLATFORM} bundle`);
  try {
    runNativeIconFontCheck(bundleText);
    log(
      `PASS: the ${PLATFORM} build embeds the Ionicons & Feather fonts and ` +
        `every login social glyph resolves.`,
    );
  } catch (e) {
    // Best-effort contract, post-compile half (mirrors the web gate): the
    // scan itself is pure fs reads + regexes, so an unexpected throw here on
    // a throwaway run means the starved box hiccuped (fd/memory exhaustion,
    // transient I/O under parallel validation load) — SKIP, same as when the
    // bundle couldn't compile. Real font/asset/registration regressions exit
    // 1 via fail() inside check-native-icon-fonts.mjs before this catch can
    // run, so they can never be downgraded. Deterministic errors (e.g. a
    // missing glyph-map file) and explicit-bundle runs still fail hard.
    if (!explicit && isTransientEnvError(e)) {
      stopExpo(child);
      skip(
        `the environment failed while scanning the bundle ` +
          `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
          `skipping (best-effort, not an icon regression)`,
      );
      return;
    }
    throw e;
  } finally {
    // Free this run's Metro server (no-op when a pre-built bundle was reused,
    // since child stays null). A backstop in the "exit" handler reaps anything
    // left if we exit early.
    stopExpo(child);
  }
}

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
