// Regression test for the mobile login screen's email-only default.
//
// The login screen (app/(auth)/index.tsx) fetches the public login-method
// policy from `GET /api/v1/auth/config` on mount (via getAuthConfig in
// lib/api/authConfig.ts) and decides which channel tabs to render:
//
//   - mobile_login_enabled = false  →  email-only (no WhatsApp tab)
//   - mobile_login_enabled = true   →  Email + WhatsApp tabs, and the
//                                       allowed country codes are surfaced
//                                       when an out-of-list number is tried.
//
// The REST API already has server-side coverage (EmailOnlyLoginPolicyTest /
// WebEmailOnlyLoginPolicyTest), but nothing verified the *mobile UI* honours
// the config. A regression here (the WhatsApp tab always rendering, the
// country-code hint vanishing, or the screen no longer defaulting to
// email-only) could ship unnoticed.
//
// This is a source-driven test (NOT a headless browser click-through),
// following the convention in test-block-cache.mjs / test-citation-href.mjs.
// It exercises the REAL logic:
//   1. getAuthConfig() run against a mocked apiFetch (success + failure).
//   2. The actual tab-list expression lifted out of the screen source,
//      evaluated for both policy states.
//   3. isAllowedCountryCode() — the real gate behind the country-code hint.
//   4. Source-wiring guards so the screen can't drift away from the logic
//      these checks pin down — including a guard that the Google auth hook
//      stays guarded against the web crash (see below).
//
// Note: the screen used to call expo-auth-session's
// `Google.useIdTokenAuthRequest` unconditionally at the top level, which
// throws on web unless a Google *web* client id is configured, crashing the
// whole login screen. That call is now guarded (useGuardedGoogleAuth) so the
// screen renders on web without credentials; the headless check-icon-fonts
// test now drives the rendered screen. Check (5) below pins that guard down.
//
// Run via `node scripts/test-login-auth-config.mjs` (package script
// `test:login-auth-config`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const authConfigSrc = readFileSync(
  join(root, "lib", "api", "authConfig.ts"),
  "utf8",
);
const screenSrc = readFileSync(
  join(root, "app", "(auth)", "index.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Load the real getAuthConfig + isAllowedCountryCode from authConfig.ts.
//
// We strip the (simple) TS annotations and inject a mock `apiFetch` so the
// functions run as plain JS — this exercises the actual source, not a
// re-implementation.
// ---------------------------------------------------------------------------
function extractFn(src, name) {
  const re = new RegExp(`export (?:async )?function ${name}\\b[\\s\\S]*?\\n\\}`, "m");
  const m = src.match(re);
  if (!m) throw new Error(`could not find ${name} in authConfig.ts`);
  return m[0];
}

const js = [
  extractFn(authConfigSrc, "getAuthConfig"),
  extractFn(authConfigSrc, "isAllowedCountryCode"),
]
  .join("\n\n")
  // Drop the generic type arg on apiFetch<{...}>(...) so the call is valid JS.
  .replace(/apiFetch<[\s\S]*?>\(/, "apiFetch(")
  // Drop the remaining simple annotations.
  .replace(/:\s*Promise<AuthConfig>/g, "")
  .replace(/:\s*string\[\]/g, "")
  .replace(/:\s*string\b/g, "")
  .replace(/:\s*boolean\b/g, "")
  .replace(/export async function/g, "async function")
  .replace(/export function/g, "function");

function loadAuthConfig(apiFetch) {
  // eslint-disable-next-line no-new-func
  return new Function(
    "apiFetch",
    `${js}; return { getAuthConfig, isAllowedCountryCode };`,
  )(apiFetch);
}

// ===========================================================================
// 1. getAuthConfig() maps the endpoint payload (and fails safe to email-only)
// ===========================================================================
console.log("[test-login-auth-config] getAuthConfig mapping");

{
  // Disabled policy → email-only.
  const { getAuthConfig } = loadAuthConfig(async () => ({
    data: { mobile_login_enabled: false, allowed_country_codes: [] },
  }));
  const cfg = await getAuthConfig();
  assert.deepEqual(cfg, { mobileLoginEnabled: false, allowedCountryCodes: [] });
}
{
  // Enabled policy → carries the allowed dialling codes through (as strings).
  const { getAuthConfig } = loadAuthConfig(async () => ({
    data: { mobile_login_enabled: true, allowed_country_codes: [1, "44"] },
  }));
  const cfg = await getAuthConfig();
  assert.deepEqual(cfg, {
    mobileLoginEnabled: true,
    allowedCountryCodes: ["1", "44"],
  });
}
{
  // Network/parse failure → safe fallback to email-only.
  const { getAuthConfig } = loadAuthConfig(async () => {
    throw new Error("boom");
  });
  const cfg = await getAuthConfig();
  assert.deepEqual(cfg, { mobileLoginEnabled: false, allowedCountryCodes: [] });
}
ok("getAuthConfig maps the policy and falls back to email-only on failure");

// ===========================================================================
// 2. The screen's tab-list expression renders the right channels
//
// We lift the EXACT ternary out of the screen source and evaluate it for both
// policy states. If someone changes it to always include the mobile tab, the
// disabled-state assertion below fails.
// ===========================================================================
console.log("[test-login-auth-config] tab list reflects the policy");

const tabExprMatch = screenSrc.match(
  /mobileLoginEnabled \? (\[[^\]]*\]) : (\[[^\]]*\])/,
);
assert.ok(
  tabExprMatch,
  "could not find the `mobileLoginEnabled ? [...] : [...]` tab list in the screen",
);

// eslint-disable-next-line no-new-func
const tabsFor = new Function(
  "mobileLoginEnabled",
  `return (${tabExprMatch[0]});`,
);

assert.deepEqual(
  tabsFor(false),
  ["email"],
  "mobile login disabled must render ONLY the email tab",
);
assert.deepEqual(
  tabsFor(true),
  ["email", "mobile"],
  "mobile login enabled must render the email AND mobile (WhatsApp) tabs",
);
ok("disabled → [email] only; enabled → [email, mobile] (real screen expression)");

// The tab labels map "mobile" → the visible "WhatsApp" string.
assert.ok(
  /c === "email" \? "Email" : "WhatsApp"/.test(screenSrc),
  "the mobile channel tab must be labelled WhatsApp",
);
ok("the mobile channel surfaces as a 'WhatsApp' tab label");

// ===========================================================================
// 3. isAllowedCountryCode() — the gate behind the country-code hint
// ===========================================================================
console.log("[test-login-auth-config] allowed-country-code gate");

{
  const { isAllowedCountryCode } = loadAuthConfig(async () => ({}));
  const ALLOWED = ["1", "44"];
  // In-list numbers pass (no hint shown).
  assert.equal(isAllowedCountryCode("+1 555 123 4567", ALLOWED), true);
  assert.equal(isAllowedCountryCode("+44 7700 900123", ALLOWED), true);
  // Out-of-list number is rejected → the screen shows the supported codes.
  assert.equal(isAllowedCountryCode("+9 999 123 4567", ALLOWED), false);
  // Empty / junk never silently passes.
  assert.equal(isAllowedCountryCode("", ALLOWED), false);
  assert.equal(isAllowedCountryCode("+1 555", []), false);
}
ok("isAllowedCountryCode admits listed dialling codes and rejects the rest");

// ===========================================================================
// 4. Screen-wiring guards: keep the screen tied to the logic above
// ===========================================================================
console.log("[test-login-auth-config] screen wiring");

// Defaults to email-only until the config loads / if it fails.
assert.ok(
  /const \[mobileLoginEnabled, setMobileLoginEnabled\] = useState\(false\)/.test(
    screenSrc,
  ),
  "the screen must default mobileLoginEnabled to false (email-only)",
);
// On config load, a disabled policy forces the email channel.
assert.ok(
  /if \(!cfg\.mobileLoginEnabled\) setChannel\("email"\)/.test(screenSrc),
  "a disabled policy must force the channel back to email",
);
// The send flow gates the mobile number through the allowed-country-code list
// and surfaces the supported codes when it doesn't match.
assert.ok(
  /!isAllowedCountryCode\(id, allowedCountryCodes\)/.test(screenSrc),
  "the send flow must gate the number via isAllowedCountryCode",
);
assert.ok(
  /Supported country codes: \$\{allowedCountryCodes\.join\(", "\)\}\./.test(
    screenSrc,
  ),
  "an out-of-list number must surface the allowed country codes",
);
ok("screen defaults to email-only, forces email when disabled, and shows codes when gated");

// ===========================================================================
// 5. Google auth hook stays guarded (regression guard for the web crash)
//
// expo-auth-session's useIdTokenAuthRequest throws on web when no webClientId
// is configured, crashing the whole login screen. The screen must NOT call it
// unconditionally at the top level — it must go through the guarded wrapper
// that skips the hook on web without a webClientId.
// ===========================================================================
console.log("[test-login-auth-config] Google auth hook is guarded against the web crash");

// The raw hook must not be invoked directly inside the component render.
assert.ok(
  !/=\s*Google\.useIdTokenAuthRequest\(/.test(screenSrc),
  "the screen must NOT call Google.useIdTokenAuthRequest directly — it crashes on web without a webClientId; use the guarded wrapper",
);
// The component must obtain the request/response/prompt via the guarded wrapper.
assert.ok(
  /useGuardedGoogleAuth\(\)/.test(screenSrc),
  "the screen must obtain Google auth via useGuardedGoogleAuth()",
);
// The wrapper must skip the hook on web when no webClientId is configured.
assert.ok(
  /Platform\.OS !== "web" \|\| !!process\.env\.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID/.test(
    screenSrc,
  ),
  "the guard must skip the hook on web unless EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID is set",
);
ok("Google auth hook is wrapped so a missing webClientId can't crash the web login screen");

console.log(`\n[test-login-auth-config] all ${passed} checks passed`);
