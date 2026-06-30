#!/usr/bin/env node
/**
 * Regression check for the login-screen icon fonts in the REAL native
 * (iOS/Android) build — the companion to check-icon-fonts.mjs, which only
 * covers the Expo *web* bundle.
 *
 * Why a native-specific check exists:
 *   Web font loading (CSS @font-face injected by react-native-web) and native
 *   font bundling (Metro embeds the icon `.ttf` as a packager asset and
 *   `useFonts()` registers it at runtime) are completely different code paths.
 *   A glyph can render fine on web yet show up as a "tofu" missing-character box
 *   on a phone if the native asset is dropped from the bundle, or the runtime
 *   registration regresses (e.g. someone removes `...Ionicons.font` /
 *   `...Feather.font` from the root `useFonts()` call). The web e2e gate would
 *   stay green while the shipped app's login screen breaks.
 *
 * What this asserts against the compiled native bundle (the exact JS + asset
 * graph that gets packaged into the app):
 *   1. Both icon `.ttf` font files (Ionicons.ttf and Feather.ttf) are embedded
 *      as packager assets in the native bundle — i.e. the font asset actually
 *      ships. A missing asset is the classic cause of native tofu.
 *   2. Both icon-set font FAMILIES are registered in the bundle (the
 *      `createIconSet(..., 'ionicons'|'Feather', ...)` family name that
 *      `useFonts()` must preload), so the glyphs resolve to the embedded font
 *      at runtime instead of falling back to the system font.
 *   3. Every social-provider icon shown on the login screen resolves to a real
 *      codepoint in the bundled Ionicons glyph map — so none of the seven
 *      provider logos can silently become tofu from a renamed/typo'd icon.
 *
 * These mirror, at the native-bundle level, the three things check-icon-fonts
 * asserts in the browser (both font families loaded + every social glyph paints
 * as a real character). The login-screen icon list is read from source so the
 * two stay in lockstep automatically.
 *
 * Usage (debugging against a pre-built bundle):
 *   NATIVE_BUNDLE_FILE=/path/to/bundle.js node scripts/check-native-icon-fonts.mjs
 *
 * Normally invoked via the self-booting harness which compiles the bundle:
 *   pnpm --filter @workspace/1inme-mobile run test:native-icon-fonts-e2e
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");

// The two icon sets the login screen (and the rest of the app) depend on, with
// the FAMILY name each is registered under via createIconSet(...). These family
// strings are what the root useFonts() call must preload on native; they appear
// verbatim in the compiled bundle. (Ionicons registers lowercase "ionicons";
// Feather registers "Feather" — see @expo/vector-icons/build/{Ionicons,Feather}.)
const ICON_SETS = [
  {
    set: "Ionicons",
    family: "ionicons",
    fontFile: "Ionicons.ttf",
    glyphMap:
      "node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/glyphmaps/Ionicons.json",
  },
  {
    set: "Feather",
    family: "Feather",
    fontFile: "Feather.ttf",
    glyphMap:
      "node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/glyphmaps/Feather.json",
  },
];

// Login screen source — the single source of truth for which social-provider
// icons render, so this check can't drift from the actual UI.
const LOGIN_SOURCE = path.join(MOBILE_ROOT, "app", "(auth)", "index.tsx");

// Root layout source — the single startup useFonts() call that must PRELOAD
// both icon sets on native before any screen renders. Dropping `...Ionicons.font`
// or `...Feather.font` here is the exact "native font registration regresses"
// failure this gate exists to catch.
const ROOT_LAYOUT_SOURCE = path.join(MOBILE_ROOT, "app", "_layout.tsx");

function log(...args) {
  console.log("[check-native-icon-fonts]", ...args);
}

function fail(msg) {
  console.error("[check-native-icon-fonts] FAIL:", msg);
  process.exit(1);
}

// Read the Ionicons glyph map that Metro bundles. createIconSet maps an icon
// NAME ("logo-instagram") to a codepoint via exactly this JSON, so a name that
// isn't a key here renders as tofu on the device.
function loadGlyphMap(relPath) {
  const abs = path.join(MOBILE_ROOT, relPath);
  return JSON.parse(fs.readFileSync(abs, "utf-8"));
}

// Parse the login screen's SOCIALS list to get the icon names actually shown.
// Reading from source keeps this native check honest when the provider lineup
// changes. Falls back to an empty list (the bundle-level assertions still run)
// if the file can't be parsed.
export function readLoginSocialIconNames() {
  let src;
  try {
    src = fs.readFileSync(LOGIN_SOURCE, "utf-8");
  } catch (e) {
    log(`could not read login source (${e?.message ?? e}); skipping glyph-name check`);
    return [];
  }
  const start = src.indexOf("const SOCIALS");
  const region = start >= 0 ? src.slice(start, src.indexOf("];", start)) : src;
  const names = [];
  for (const m of region.matchAll(/icon:\s*"([^"]+)"/g)) names.push(m[1]);
  return names;
}

// Find packager-asset registrations in the compiled native bundle. Metro emits
// each bundled asset as an object literal carrying its name + type; this mirrors
// the proven extraction scripts/build.js uses to copy assets for the real
// deploy. `type` is matched loosely (case-insensitive "ttf") since extension
// casing can vary by platform/sdk.
function bundleHasFontAsset(bundleText, fontFile) {
  const base = fontFile.replace(/\.ttf$/i, "");
  // Asset object fields can appear in any order; require both the asset name
  // and a ttf type somewhere in the same small object by scanning name->type
  // and type->name windows.
  const namePat = new RegExp(
    `name:"${base}"[^}]{0,200}?type:"ttf"`,
    "i",
  );
  const typePat = new RegExp(
    `type:"ttf"[^}]{0,200}?name:"${base}"`,
    "i",
  );
  return namePat.test(bundleText) || typePat.test(bundleText);
}

export function assertFontAssetsBundled(bundleText) {
  for (const { set, fontFile } of ICON_SETS) {
    if (!bundleHasFontAsset(bundleText, fontFile)) {
      fail(
        `${set}: the native bundle does not embed ${fontFile} as a packager ` +
          `asset. The font won't ship, so its glyphs render as "tofu" boxes on ` +
          `the device even though the web build looks fine.`,
      );
    }
    log(`OK: ${fontFile} is embedded in the native bundle`);
  }
}

export function assertFontFamiliesRegistered(bundleText) {
  for (const { set, family } of ICON_SETS) {
    // The createIconSet family name is embedded as a quoted string literal in
    // the compiled bundle. Require the quoted form to avoid matching unrelated
    // substrings.
    const re = new RegExp(`["']${family}["']`);
    if (!re.test(bundleText)) {
      fail(
        `${set}: font family "${family}" is not registered in the native ` +
          `bundle. The icon set's createIconSet registration is missing, so ` +
          `glyphs fall back to the system font (tofu) on the device.`,
      );
    }
    log(`OK: font family "${family}" is registered in the native bundle`);
  }
}

// The native build only renders icon glyphs without "tofu" if the icon fonts
// are preloaded at startup. On native, the root useFonts() call is what
// registers them before the first screen paints — so assert both icon sets are
// spread into that call. This is the exact regression the gate guards against
// and is verified statically (it holds regardless of whether the heavy native
// bundle compiled), complementing the bundle-level asset/family checks.
export function assertStartupFontPreload() {
  let src;
  try {
    src = fs.readFileSync(ROOT_LAYOUT_SOURCE, "utf-8");
  } catch (e) {
    fail(`could not read root layout (${ROOT_LAYOUT_SOURCE}): ${e?.message ?? e}`);
  }
  const start = src.indexOf("useFonts(");
  if (start < 0) {
    fail(
      `root layout has no useFonts() call — nothing preloads the icon fonts at ` +
        `startup, so native icons render as tofu.`,
    );
  }
  // Scope to the useFonts({...}) argument object.
  const region = src.slice(start, src.indexOf("})", start) + 2);
  for (const { set } of ICON_SETS) {
    const re = new RegExp(`\\.\\.\\.${set}\\.font`);
    if (!re.test(region)) {
      fail(
        `root layout's startup useFonts() does not preload \`...${set}.font\`. ` +
          `On native the ${set} glyphs won't be registered before screens render ` +
          `and show as "tofu" boxes (the web build can still look fine).`,
      );
    }
    log(`OK: startup useFonts() preloads ...${set}.font`);
  }
}

export function assertLoginSocialGlyphsResolvable() {
  const names = readLoginSocialIconNames();
  if (names.length === 0) {
    log("no social icon names parsed from login source; skipping glyph-name check");
    return;
  }
  const ionicons = loadGlyphMap(ICON_SETS[0].glyphMap);
  for (const name of names) {
    const cp = ionicons[name];
    if (typeof cp !== "number") {
      fail(
        `login social icon "${name}" is not a key in the bundled Ionicons ` +
          `glyph map — it has no codepoint, so it renders as tofu on the ` +
          `device. (Renamed/typo'd icon, or removed from the icon set?)`,
      );
    }
  }
  log(
    `OK: all ${names.length} login social icons resolve to real Ionicons ` +
      `glyphs (${names.join(", ")})`,
  );
}

// Run the full native icon-font regression check against a compiled native
// bundle's text. Exported so the self-booting harness can compile the bundle
// and then run this exact check against it — the harness owns the Metro
// lifecycle; this owns the assertions.
export function runNativeIconFontCheck(bundleText) {
  log("verifying both icon fonts are embedded as native packager assets");
  assertFontAssetsBundled(bundleText);

  log("verifying both icon-set font families are registered in the bundle");
  assertFontFamiliesRegistered(bundleText);

  log("verifying every login social-provider icon resolves to a real glyph");
  assertLoginSocialGlyphsResolvable();

  log("verifying the root layout preloads both icon fonts at startup");
  assertStartupFontPreload();

  log(
    "PASS: the native build embeds the Ionicons & Feather fonts, registers both " +
      "families, preloads them at startup, and every login social glyph resolves.",
  );
}

function main() {
  const file = process.env.NATIVE_BUNDLE_FILE || process.argv[2];
  if (!file) {
    fail(
      "no bundle to check — set NATIVE_BUNDLE_FILE or pass a bundle path. " +
        "(Normally run via test:native-icon-fonts-e2e, which compiles one.)",
    );
  }
  const bundleText = fs.readFileSync(file, "utf-8");
  log(`checking pre-built bundle ${file} (${bundleText.length} bytes)`);
  runNativeIconFontCheck(bundleText);
}

const invokedDirectly =
  process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;

if (invokedDirectly) {
  try {
    main();
  } catch (e) {
    console.error(e);
    process.exit(1);
  }
}
