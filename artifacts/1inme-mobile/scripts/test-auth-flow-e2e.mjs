#!/usr/bin/env node
/**
 * End-to-end regression check for the mobile sign-in screen, driving the
 * REAL app in a headless browser.
 *
 * test-auth-flow.mjs already pins the auth *logic* (endpoints, payloads, the
 * Sanctum bearer token, input validation, screen wiring) by replaying the
 * source against mocks. What it can NOT catch is a purely visual break: the
 * "Send code" button hidden or untappable, the email field not accepting
 * focus, or the verify screen failing to mount. This test renders the actual
 * screen and clicks through it, so those breaks surface.
 *
 * The login screen has three sign-in paths, and a visual break in any of
 * them slips past the source-replay test. This harness exercises all three:
 *   - Email OTP: type an email, "Send code", type a code, "Verify and sign in".
 *   - Demo logins: "Demo as user" / "Demo as admin" (POST /api/v1/auth/demo).
 *   - Social providers: six web-browser OAuth buttons (+ Google when built).
 *
 * This harness does NOT depend on the live proxied Expo dev domain (or any
 * RDS-backed render). Like the Google variant below, it boots its OWN
 * throwaway, self-contained Expo web dev server on a free port and intercepts
 * every backend call, so the whole suite runs deterministically and quickly
 * enough to use as a local check. (Set APP_URL to point the main flow at an
 * already-running server instead — useful for local debugging.)
 *
 * Speed/CI: Expo boot (well over a minute per server) is the dominant cost, and
 * the two flows need DIFFERENT bundles (the Google variant must be built with
 * EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID). To stay inside a constrained CI window the
 * orchestrator boots BOTH servers in PARALLEL, either server can be REUSED via
 * APP_URL / GOOGLE_APP_URL (so CI can pre-start them once), and AUTH_FLOW_ONLY
 * can run just one flow so the suite can be split into two independent jobs.
 *
 * Strategy (reuses the reachLoginScreen harness from check-icon-fonts.mjs):
 *   1. Boot a throwaway Expo web server (or use APP_URL) and launch headless
 *      Chromium against it, with all /api/** calls intercepted.
 *   2. Walk onboarding to the "Welcome back" login screen.
 *   3. Assert every visible social-provider button is tappable (rendered,
 *      visible, not disabled) — a hidden/untappable button would otherwise
 *      ship unnoticed. (Each web-browser provider is then clicked through its
 *      full mocked sign-in in step 8.)
 *   4. Click each demo button with POST **\/api/v1/auth/demo intercepted and
 *      fulfilled with a mock {data:{token,user}}; assert the request role and
 *      that the app lands in the signed-in tabs. Reset to the login screen
 *      between runs by clearing the stored session.
 *   5. Type an email, intercept POST **\/api/v1/auth/otp/send and fulfill it
 *      with a mock 200 (so no real OTP is sent and production is never hit),
 *      asserting the request URL / method / body. Then click "Send code" and
 *      assert the verify screen appears ("Check your inbox").
 *   6. Type a code, intercept **\/api/v1/auth/otp/verify returning
 *      {data:{token,user}}, click "Verify and sign in", and assert the app
 *      lands in the signed-in tabs.
 *   7. Load /oauth-callback directly to exercise the deep-link return screen
 *      both legs read by app/oauth-callback.tsx:
 *        - the browser leg (?token=…&user=… → applySession), plus its
 *          cancelled/malformed error paths; and
 *        - the native-SDK leg (?provider=…&id_token=… → POST /auth/social
 *          via AuthContext.socialLogin), asserting the forwarded request
 *          URL/method/body and that a 422 surfaces "Sign-in failed".
 *   8. Click EVERY web-browser OAuth provider button (Instagram, Facebook, X,
 *      LinkedIn, Pinterest, TikTok) through its full mocked round-trip: the
 *      backend /user/social-oauth/{provider}/login URL is intercepted and
 *      stands in for the backend + OS deep-link by returning the app to
 *      /oauth-callback?provider=…&id_token=…, which forwards { provider,
 *      id_token } to POST /auth/social. Each one asserts it opened the
 *      provider-specific login URL, POSTed the correct provider, and landed in
 *      the signed-in tabs — catching a wrong/missing provider key or handler.
 *   9. Click EVERY web-browser OAuth provider button through a FAILING
 *      round-trip too, alternating two mechanisms across the providers: a
 *      cancelled popup (the backend redirect carries ?error=access_denied) and
 *      a rejected token (the backend redirect carries provider+id_token but
 *      POST /auth/social returns 422). Each asserts the friendly "Sign-in
 *      failed" screen appears, a way back is offered, and the app does NOT sign
 *      in (no signed-in tabs, no persisted token) — so a provider that hangs or
 *      wrongly signs the user in on failure is caught.
 *
 * The test no-ops gracefully (skips, exit 0) when a throwaway Expo server
 * can't be booted (expo missing, port contention, bundling too slow) — so it
 * never fails CI just because the environment can't bring Metro up.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:auth-flow-e2e
 *
 * Environment:
 *   APP_URL   point the main flow at an already-running server instead of
 *             booting a throwaway one (handy for local debugging). When unset,
 *             the main flow boots its own self-contained Expo web server.
 */

import { spawn } from "node:child_process";
import net from "node:net";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { chromium } from "playwright";

import {
  APP_URL,
  NAV_TIMEOUT_MS,
  STEP_TIMEOUT_MS,
  reachLoginScreen,
} from "./check-icon-fonts.mjs";

// Root of the mobile artifact (one level up from scripts/). Used to spawn the
// throwaway Google-enabled Expo server for the variant below.
const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");

// A dummy Google web client id. Its value never matters — it only needs to be
// truthy so HAS_GOOGLE_NATIVE / GOOGLE_AUTH_SAFE_TO_INIT flip on and the
// Google button renders (and the real useIdTokenAuthRequest hook runs).
const GOOGLE_VARIANT_WEB_CLIENT_ID =
  "1inme-e2e-test.apps.googleusercontent.com";

// How long to wait for the throwaway Expo server to report ready before
// giving up and skipping the variant.
const EXPO_BOOT_DEADLINE_MS = 180_000;

const EMAIL = "creator@example.com";
const CODE = "123456";
const MOCK_TOKEN = "sanctum-token-e2e";
// A distinct token for the native-SDK /auth/social leg so we can prove the
// session that landed came from that forward and not the token+user leg.
const MOCK_SOCIAL_TOKEN = "sanctum-token-social-e2e";
// The fake id_token Google "returns" in the mocked round-trip. The success
// handler must forward this exact value to POST /api/v1/auth/social.
const MOCK_ID_TOKEN = "mock-google-id-token-e2e";
// The fake id_token the mocked web-browser-provider backend "returns" on its
// redirect (?provider=…&id_token=…). oauth-callback.tsx must forward this exact
// value to POST /api/v1/auth/social for every web provider.
const MOCK_WEB_ID_TOKEN = "mock-web-oauth-id-token-e2e";

// The web-browser OAuth providers always render on the login screen (see
// WEB_BROWSER_PROVIDERS in app/(auth)/index.tsx); their accessibility labels
// are the minimum set this test requires to be tappable. Google only renders
// when a Google client id is configured, so it's verified opportunistically.
const REQUIRED_SOCIAL_LABELS = [
  "Continue with Instagram",
  "Continue with Facebook",
  "Continue with X",
  "Continue with LinkedIn",
  "Continue with Pinterest",
  "Continue with TikTok",
];
const OPTIONAL_SOCIAL_LABELS = ["Continue with Google"];

// The base URL the main flow drives. Defaults to APP_URL, but the orchestrator
// boots a throwaway Expo web server and points this at it unless an explicit
// APP_URL was supplied. Every main-flow helper (oauth-callback, web-provider
// loops, the popup redirect shim) reads THIS, never the imported APP_URL, so
// the flow is self-consistent whether its server was booted or reused.
let appBaseUrl = APP_URL;

// An explicit APP_URL override means "don't boot a throwaway server, use this
// already-running one" (handy for local debugging and for letting CI pre-start
// the server once and reuse it). When unset, a throwaway server is booted.
const EXPLICIT_APP_URL = process.env.APP_URL || null;

// The Google variant needs a server whose bundle was built WITH
// EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID baked in (Expo inlines EXPO_PUBLIC_* at
// build time), so it can't share the main flow's plain server. GOOGLE_APP_URL
// lets CI/local point the variant at an already-running Google-enabled server
// instead of booting its own — the variant's analogue of APP_URL.
const EXPLICIT_GOOGLE_APP_URL = process.env.GOOGLE_APP_URL || null;

// Which flow(s) to run. "both" (default) runs the main flow then the Google
// variant in one process; "main" or "google" runs just that one. Splitting
// into "main" and "google" lets CI run the two flows as INDEPENDENT, parallel
// jobs (each booting/reusing only its own server) so neither has to wait on
// the other's Expo boot — the key to staying inside a constrained time window.
const FLOW = (process.env.AUTH_FLOW_ONLY || "both").toLowerCase();
if (!["both", "main", "google"].includes(FLOW)) {
  console.error(
    `[test-auth-flow-e2e] FAIL: AUTH_FLOW_ONLY must be "both", "main", or ` +
      `"google", got "${FLOW}"`,
  );
  process.exit(1);
}

// The web-browser OAuth providers driven end to end in step 7: their provider
// id (used in the backend /user/social-oauth/{id}/login URL and forwarded to
// /auth/social) and the button's accessibility label. Mirrors
// WEB_BROWSER_PROVIDERS in app/(auth)/index.tsx — note the "twitter" id renders
// as "Continue with X".
const WEB_OAUTH_PROVIDERS = [
  { id: "instagram", label: "Continue with Instagram" },
  { id: "facebook", label: "Continue with Facebook" },
  { id: "twitter", label: "Continue with X" },
  { id: "linkedin", label: "Continue with LinkedIn" },
  { id: "pinterest", label: "Continue with Pinterest" },
  { id: "tiktok", label: "Continue with TikTok" },
];

function log(...args) {
  console.log("[test-auth-flow-e2e]", ...args);
}

function fail(msg) {
  console.error("[test-auth-flow-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[test-auth-flow-e2e] SKIP:", msg);
  process.exit(0);
}

// Wait for the signed-in tab bar — the "Profile" tab label never appears on
// the auth screens, so it's our "we're in" signal. Mirrors the OTP step.
async function waitForSignedInTabs(page) {
  await page
    .getByText("Profile", { exact: true })
    .first()
    .waitFor({ timeout: STEP_TIMEOUT_MS });
}

// Clear the persisted Sanctum session (token + user) so a reload drops us
// back at the login screen, then walk onboarding again. Used between the
// demo-login runs (each one signs in and lands in the tabs). Keeps the
// onboarding-complete flag so we don't re-walk the carousel from scratch.
async function resetToLogin(page) {
  await page.evaluate(() => {
    try {
      window.localStorage.removeItem("1inme.auth.token");
      window.localStorage.removeItem("1inme.auth.user");
    } catch {}
  });
  await reachLoginScreen(page);
}

// Assert each visible social-provider button is tappable: present, visible,
// and not disabled. A hidden, removed, or stuck-disabled button is exactly
// the kind of visual break the source-replay test can't see.
async function assertSocialButtonsTappable(page) {
  let verified = 0;
  for (const label of [...REQUIRED_SOCIAL_LABELS, ...OPTIONAL_SOCIAL_LABELS]) {
    const optional = OPTIONAL_SOCIAL_LABELS.includes(label);
    const btn = page.locator(`[aria-label="${label}"]`).first();
    const present = (await btn.count()) > 0;
    if (!present) {
      // Optional providers (Google without a client id) may be absent.
      if (optional) continue;
      fail(`social button "${label}" is missing from the login screen`);
    }
    if (!(await btn.isVisible())) {
      fail(`social button "${label}" is present but not visible`);
    }
    // React Native Web renders a disabled Pressable with aria-disabled="true".
    // Read it directly rather than relying on role-specific isEnabled() heuristics.
    const ariaDisabled = await btn.getAttribute("aria-disabled");
    if (ariaDisabled === "true") {
      fail(`social button "${label}" is disabled and can't be tapped`);
    }
    verified++;
  }
  if (verified < REQUIRED_SOCIAL_LABELS.length) {
    fail(
      `expected at least ${REQUIRED_SOCIAL_LABELS.length} tappable social ` +
        `buttons, only verified ${verified}`,
    );
  }
  log(`all ${verified} visible social-provider buttons are tappable`);
}

// Wipe the persisted Sanctum session AND mark onboarding complete, so a
// direct load of /oauth-callback starts from a clean signed-out slate and
// the screen's own params decide the outcome (rather than a leftover token).
// Must run while we're already on the app origin (localStorage is per-origin).
async function clearSessionKeepOnboarding(page) {
  await page.evaluate(() => {
    try {
      window.localStorage.removeItem("1inme.auth.token");
      window.localStorage.removeItem("1inme.auth.user");
      window.localStorage.setItem("1inme.onboarding.complete", "1");
    } catch {}
  });
}

// Load the deep-link return screen directly with a given query string,
// exactly as the OS would when the provider redirects to
// 1inme://oauth-callback?… . On web that maps to /oauth-callback?… .
async function gotoOAuthCallback(page, query) {
  const url = `${appBaseUrl.replace(/\/$/, "")}/oauth-callback${query}`;
  await page.goto(url, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });
  // Wait for ANY in-app text so we know the screen actually mounted.
  await page.waitForFunction(
    () => document.body && document.body.innerText.trim().length > 0,
    null,
    { timeout: NAV_TIMEOUT_MS },
  );
}

// The success leg: a redirect carrying ?token=…&user=… must sign the user
// in (applySession) and land them in the signed-in tabs. This is the exact
// hand-off the social-button assertion deliberately stops short of, so it
// would otherwise never be exercised end to end.
async function runOAuthCallbackSuccess(page) {
  await clearSessionKeepOnboarding(page);
  const user = {
    id: 99,
    display_name: "OAuth User",
    email: "oauth@example.com",
  };
  const query =
    `?token=${encodeURIComponent(MOCK_TOKEN)}` +
    `&user=${encodeURIComponent(JSON.stringify(user))}` +
    `&provider=google`;
  await gotoOAuthCallback(page, query);
  await waitForSignedInTabs(page);

  // The persisted token proves applySession actually ran (not just that a
  // stale session was lying around — we cleared it first).
  const storedToken = await page.evaluate(() => {
    try {
      return window.localStorage.getItem("1inme.auth.token");
    } catch {
      return null;
    }
  });
  if (storedToken !== MOCK_TOKEN) {
    fail(
      `oauth-callback success did not persist the session token ` +
        `(expected ${MOCK_TOKEN}, got ${storedToken})`,
    );
  }
  log("oauth-callback success: token+user redirect landed in the signed-in tabs");
}

// The failure legs: a cancelled return (?error=access_denied) and a
// malformed return (no token / no provider creds) must each surface a
// visible "Sign-in failed" error instead of silently hanging on the
// spinner. We assert both the heading and that we did NOT slip into the
// tabs.
async function runOAuthCallbackErrors(page) {
  // --- Cancelled / provider-error return.
  await clearSessionKeepOnboarding(page);
  await gotoOAuthCallback(page, "?error=access_denied");
  await page
    .getByText("Sign-in failed", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  // The screen maps known codes to friendly copy rather than echoing the
  // raw "access_denied".
  await page
    .getByText("You cancelled the sign-in.", { exact: false })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  const inTabsAfterCancel = await page
    .getByText("Profile", { exact: true })
    .first()
    .isVisible()
    .catch(() => false);
  if (inTabsAfterCancel) {
    fail("a cancelled oauth return wrongly signed the user in");
  }
  // And there must be a way back rather than a dead end.
  await page
    .getByText("Back to sign in", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  log("oauth-callback cancelled return surfaced a friendly error, not a hang");

  // --- Malformed return: no token, no provider/id_token. The screen must
  // explain the session was missing instead of spinning forever.
  await clearSessionKeepOnboarding(page);
  await gotoOAuthCallback(page, "");
  await page
    .getByText("Sign-in failed", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  await page
    .getByText("Sign-in did not return a session", { exact: false })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  const inTabsAfterMalformed = await page
    .getByText("Profile", { exact: true })
    .first()
    .isVisible()
    .catch(() => false);
  if (inTabsAfterMalformed) {
    fail("a malformed oauth return wrongly signed the user in");
  }
  log("oauth-callback malformed return surfaced a missing-session error, not a hang");
}

// ---------------------------------------------------------------------------
// Google-enabled variant.
//
// On the default web build the Google button is intentionally absent — no
// EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID is set, so HAS_GOOGLE_NATIVE is false. The
// main flow above can therefore only verify it opportunistically and never
// exercises it. A break in the Google path — the guarded
// useIdTokenAuthRequest hook throwing at render (which crashes the whole
// login screen into the error boundary) or the button rendering disabled —
// would slip past unnoticed.
//
// We can't flip the button on against the already-running server because Expo
// inlines EXPO_PUBLIC_* into the web bundle at build time, so the value has to
// be present when Metro builds. This variant boots a SECOND, throwaway Expo
// web dev server with EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID set, then asserts the
// login screen still mounts and the Google button is tappable.
//
// Booting that server is best-effort: if it can't start (expo missing, port
// contention, bundling too slow) we skip the variant rather than fail CI —
// mirroring the "skip when the dev server is down" contract of the main flow.

function getFreePort() {
  return new Promise((resolve, reject) => {
    const srv = net.createServer();
    srv.once("error", reject);
    srv.listen(0, "127.0.0.1", () => {
      const { port } = srv.address();
      srv.close(() => resolve(port));
    });
  });
}

// Poll the Expo dev server's /status endpoint (it returns the literal
// "packager-status:running" once Metro is up) until ready or the deadline.
async function waitForExpoStatus(port, deadlineMs) {
  const url = `http://localhost:${port}/status`;
  const start = Date.now();
  while (Date.now() - start < deadlineMs) {
    try {
      const res = await fetch(url);
      const text = await res.text().catch(() => "");
      if (res.ok && /packager-status:running/.test(text)) return true;
    } catch {
      // not up yet
    }
    await new Promise((r) => setTimeout(r, 1500));
  }
  return false;
}

// Kill the spawned Expo server and the whole process group it leads (expo
// spawns Metro children; killing only the parent would orphan them).
function stopExpo(child) {
  if (!child || child.killed) return;
  try {
    process.kill(-child.pid, "SIGTERM");
  } catch {
    try {
      child.kill("SIGTERM");
    } catch {
      // already gone
    }
  }
}

// Boot a throwaway, self-contained Expo web dev server on a free port with the
// given extra env baked into the bundle, and wait for Metro to report ready.
// Returns { child, port } on success, or null if it couldn't start. Best
// effort: callers SKIP (exit 0) rather than fail CI when this returns null,
// mirroring the "skip when the dev server is down" contract — the env simply
// couldn't bring Metro up. Used for BOTH the main flow (no extra env) and the
// Google variant (EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID set).
async function bootThrowawayExpo(label, extraEnv = {}) {
  let port;
  try {
    port = await getFreePort();
  } catch (e) {
    log(`${label}: could not allocate a port (${e?.message ?? e})`);
    return null;
  }

  log(`${label}: booting a throwaway Expo web server on :${port}`);
  const child = spawn(
    "pnpm",
    ["exec", "expo", "start", "--localhost", "--port", String(port)],
    {
      cwd: MOBILE_ROOT,
      detached: true,
      stdio: ["ignore", "ignore", "ignore"],
      env: {
        ...process.env,
        CI: "1",
        BROWSER: "none",
        EXPO_NO_TELEMETRY: "1",
        ...extraEnv,
      },
    },
  );

  let spawnFailed = false;
  child.on("error", (e) => {
    spawnFailed = true;
    log(`${label}: expo failed to spawn (${e?.message ?? e})`);
  });

  const ready = await waitForExpoStatus(port, EXPO_BOOT_DEADLINE_MS);
  if (spawnFailed || !ready) {
    log(
      `${label}: the throwaway Expo server didn't become ready within ` +
        `${Math.round(EXPO_BOOT_DEADLINE_MS / 1000)}s`,
    );
    stopExpo(child);
    return null;
  }
  log(`${label}: Expo server is ready`);
  return { child, port };
}

// Every throwaway Expo child we boot is tracked here so it's torn down even on
// the skip()/fail() paths (which process.exit() directly from deep inside a
// flow). The "exit" handler must be synchronous — process.kill is — so a
// group-kill there reliably reaps Metro instead of orphaning it.
const bootedChildren = new Set();
function stopAllChildren() {
  for (const c of bootedChildren) stopExpo(c);
  bootedChildren.clear();
}
process.on("exit", stopAllChildren);

// Acquire a ready-to-drive server for a flow: reuse an already-running one when
// its *_APP_URL override is set (no boot), otherwise boot a throwaway and track
// it for teardown. Returns { appUrl, child, explicit } or null when a throwaway
// couldn't come up (callers SKIP, never fail, on null — the boot contract).
async function acquireServer(label, explicitUrl, extraEnv = {}) {
  if (explicitUrl) {
    return { appUrl: explicitUrl, child: null, explicit: true };
  }
  const booted = await bootThrowawayExpo(label, extraEnv);
  if (!booted) return null;
  bootedChildren.add(booted.child);
  return {
    appUrl: `http://localhost:${booted.port}/`,
    child: booted.child,
    explicit: false,
  };
}

async function assertGoogleButtonTappable(page) {
  const label = "Continue with Google";
  const btn = page.locator(`[aria-label="${label}"]`).first();
  if ((await btn.count()) === 0) {
    fail(
      `Google variant: "${label}" did not render even with a web client id ` +
        `configured — the Google sign-in button is broken or gated off`,
    );
  }
  if (!(await btn.isVisible())) {
    fail(`Google variant: "${label}" is present but not visible`);
  }
  // React Native Web renders a disabled Pressable with aria-disabled="true".
  const ariaDisabled = await btn.getAttribute("aria-disabled");
  if (ariaDisabled === "true") {
    fail(`Google variant: "${label}" is disabled and can't be tapped`);
  }
  log(`Google variant: "${label}" rendered, visible and tappable`);
}

// Drive the "Continue with Google" button end to end with the OAuth
// round-trip mocked (see the route handlers wired in runGoogleVariant).
// Clicking the button runs the REAL useIdTokenAuthRequest hook: it opens a
// popup to Google's authorization endpoint (intercepted), which bounces back
// to the app's redirect_uri carrying a mock id_token + the original state. A
// same-origin shim on the popup posts the result to the opener exactly like
// expo-web-browser's maybeCompleteAuthSession, so promptAsync resolves with a
// success result and the googleResponse effect fires. We then assert it POSTed
// { provider:"google", id_token } to /api/v1/auth/social and landed in the
// signed-in tabs — the leg the tappable-only check stops short of.
async function runGoogleSuccessPath(page, social, googleAuth) {
  social.req = null;
  googleAuth.req = null;

  const btn = page.locator('[aria-label="Continue with Google"]').first();
  await btn.waitFor({ timeout: STEP_TIMEOUT_MS });

  const [popup] = await Promise.all([
    page.waitForEvent("popup", { timeout: STEP_TIMEOUT_MS }),
    btn.click(),
  ]);
  log("Google variant: tapped the Google button; OAuth popup opened");
  void popup;

  // The signed-in tab bar is the proof the success handler completed.
  await waitForSignedInTabs(page);
  log("Google variant: the Google success path landed in the signed-in tabs");

  // The hook must have built a correct implicit id_token request to Google.
  if (!googleAuth.req) {
    fail("Google variant: the Google authorization endpoint was never hit");
  }
  if (googleAuth.req.responseType !== "id_token") {
    fail(
      "Google variant: expected response_type=id_token, got " +
        `${googleAuth.req.responseType}`,
    );
  }
  if (googleAuth.req.clientId !== GOOGLE_VARIANT_WEB_CLIENT_ID) {
    fail(
      "Google variant: the auth request used the wrong client_id " +
        `(${googleAuth.req.clientId})`,
    );
  }

  // The response handler must POST the returned id_token to /auth/social.
  if (!social.req) {
    fail(
      "Google variant: the success handler never POSTed to /api/v1/auth/social",
    );
  }
  if (social.req.method !== "POST") {
    fail(
      `Google variant: auth/social must be a POST, got ${social.req.method}`,
    );
  }
  if (!/\/api\/v1\/auth\/social$/.test(new URL(social.req.url).pathname)) {
    fail(`Google variant: auth/social hit the wrong URL: ${social.req.url}`);
  }
  let body;
  try {
    body = JSON.parse(social.req.body ?? "null");
  } catch {
    fail(
      `Google variant: auth/social body was not valid JSON: ${social.req.body}`,
    );
  }
  if (body?.provider !== "google" || body?.id_token !== MOCK_ID_TOKEN) {
    fail(
      "Google variant: auth/social body must be " +
        `{ provider:"google", id_token:"${MOCK_ID_TOKEN}" }, ` +
        `got ${JSON.stringify(body)}`,
    );
  }
  log(
    'Google variant: success handler POSTed { provider:"google", id_token } ' +
      "to /api/v1/auth/social",
  );
}

// Drive the Google-enabled variant against an already-acquired server (booted
// in parallel by the orchestrator, or reused via GOOGLE_APP_URL). The server's
// bundle must have EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID baked in for the Google
// button to render. Teardown of any throwaway child is the orchestrator's job.
async function runGoogleVariant(server) {
  const { child } = server;
  const googleBaseUrl = server.appUrl;

  try {
    log("Google variant: loading the login screen");

    const browser = await chromium.launch({ headless: true });
    try {
      const context = await browser.newContext({
        viewport: { width: 400, height: 720 },
      });
      const page = await context.newPage();
      let pageError = null;
      page.on("pageerror", (e) => {
        pageError = e.message;
        log("Google variant pageerror:", e.message);
      });
      page.setDefaultTimeout(STEP_TIMEOUT_MS);
      page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

      // Holders for the intercepted requests so the success path below can
      // assert their shape after the fact.
      const social = { req: null };
      const googleAuth = { req: null };

      // Capture & fulfill the social-login POST so the Google success handler
      // can complete without a real backend.
      await context.route("**/api/v1/auth/social", async (route) => {
        const req = route.request();
        social.req = {
          url: req.url(),
          method: req.method(),
          body: req.postData(),
        };
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            data: {
              token: MOCK_TOKEN,
              user: {
                id: 77,
                display_name: "Google User",
                email: "google@example.com",
              },
            },
          }),
        });
      });

      // This throwaway build has no API base configured; block every OTHER
      // backend call so nothing ever reaches a real server. (Playwright runs
      // route handlers most-recently-added-first, so this catch-all sees the
      // /auth/social request first and defers it to the handler above.)
      await context.route("**/api/**", (route) => {
        if (
          /\/api\/v1\/auth\/social$/.test(new URL(route.request().url()).pathname)
        ) {
          return route.fallback();
        }
        return route.abort();
      });

      // Mock Google's authorization endpoint: record the request (proving the
      // real useIdTokenAuthRequest hook built the implicit id_token URL) and
      // bounce the popup back to the app's redirect_uri carrying a mock
      // id_token + the original state, exactly as Google would. No external
      // browser or network is ever hit.
      await context.route(
        /accounts\.google\.com\/o\/oauth2\/v2\/auth/,
        async (route) => {
          const u = new URL(route.request().url());
          googleAuth.req = {
            clientId: u.searchParams.get("client_id"),
            responseType: u.searchParams.get("response_type"),
            redirectUri: u.searchParams.get("redirect_uri"),
            state: u.searchParams.get("state"),
          };
          const dest =
            `${googleAuth.req.redirectUri}` +
            `#id_token=${encodeURIComponent(MOCK_ID_TOKEN)}` +
            `&state=${encodeURIComponent(googleAuth.req.state ?? "")}`;
          await route.fulfill({
            status: 200,
            contentType: "text/html",
            body:
              `<!doctype html><meta charset="utf-8">` +
              `<script>location.replace(${JSON.stringify(dest)})</script>`,
          });
        },
      );

      // When the OAuth popup opens, serve a minimal same-origin completion
      // shim on its redirect_uri load instead of booting the whole app there.
      // It posts the result back to the opener exactly like expo-web-browser's
      // maybeCompleteAuthSession, so the opener's promptAsync resolves with a
      // success result and the real googleResponse effect fires.
      page.on("popup", (popup) => {
        popup
          .route("**/*", async (route) => {
            const req = route.request();
            const u = req.url();
            // The Google auth endpoint is handled at the context level above.
            if (/accounts\.google\.com/.test(u)) return route.fallback();
            // Only the top-level redirect_uri document matters; the shim has
            // no sub-resources.
            if (req.resourceType() !== "document") return route.abort();
            await route.fulfill({
              status: 200,
              contentType: "text/html",
              body:
                `<!doctype html><meta charset="utf-8"><script>` +
                `try{` +
                `var h=window.localStorage.getItem('ExpoWebBrowserRedirectHandle');` +
                `if(window.opener)window.opener.postMessage(` +
                `{url:window.location.href,expoSender:h},window.location.origin);` +
                `}catch(e){}` +
                `</script>`,
            });
          })
          .catch((e) =>
            log("Google variant: popup route wiring failed:", e?.message ?? e),
          );
      });

      await page.goto(googleBaseUrl, {
        waitUntil: "domcontentloaded",
        timeout: NAV_TIMEOUT_MS,
      });
      await page.waitForFunction(
        () => document.body && document.body.innerText.trim().length > 0,
        null,
        { timeout: NAV_TIMEOUT_MS },
      );

      // Reaching "Welcome back" is the proof the screen MOUNTED: if the
      // guarded Google hook had thrown at render, AuthLanding would be in the
      // error boundary and this would time out instead.
      await reachLoginScreen(page);
      log(
        "Google variant: login screen mounted with Google enabled " +
          "(no error boundary)",
      );

      await assertGoogleButtonTappable(page);

      if (pageError) {
        fail(`Google variant: the page reported a runtime error: ${pageError}`);
      }

      // Now drive the Google button for real (round-trip mocked) and assert
      // the response handler completes the sign-in, not just that the button
      // renders.
      await runGoogleSuccessPath(page, social, googleAuth);

      log(
        "Google variant PASS: the Google button renders and is tappable, the " +
          "login screen mounts, and the full Google sign-in completes through " +
          "POST /api/v1/auth/social into the signed-in tabs.",
      );
    } finally {
      await browser.close();
    }
  } finally {
    stopExpo(child);
  }
}

// The native-SDK return leg: a deep link carrying ?provider=…&id_token=…
// (no ready-made token) must be forwarded to POST /auth/social, and the
// returned {data:{token,user}} envelope must sign the user in. This is the
// branch in oauth-callback.tsx that the token+user success test never hits —
// a regression in the payload, endpoint, or envelope handling would slip
// through otherwise.
async function runOAuthCallbackNativeSuccess(page, social) {
  social.req = null;
  social.mode = "success";
  await clearSessionKeepOnboarding(page);
  const idToken = "google-id-token-e2e";
  const query = `?provider=google&id_token=${encodeURIComponent(idToken)}`;
  await gotoOAuthCallback(page, query);
  await waitForSignedInTabs(page);

  if (!social.req) {
    fail("the native oauth return never POSTed to /api/v1/auth/social");
  }
  if (social.req.method !== "POST") {
    fail(`auth/social must be a POST, got ${social.req.method}`);
  }
  if (!/\/api\/v1\/auth\/social$/.test(new URL(social.req.url).pathname)) {
    fail(`auth/social hit the wrong URL: ${social.req.url}`);
  }
  let body;
  try {
    body = JSON.parse(social.req.body ?? "null");
  } catch {
    fail(`auth/social body was not valid JSON: ${social.req.body}`);
  }
  if (body?.provider !== "google" || body?.id_token !== idToken) {
    fail(
      `auth/social body must forward { provider: "google", id_token }, ` +
        `got ${JSON.stringify(body)}`,
    );
  }
  log("auth/social request method, URL and body (provider + id_token) are correct");

  // The persisted token proves socialLogin → applySession actually ran with
  // the /auth/social response (we cleared the session first, and this token
  // is distinct from the token+user leg's).
  const storedToken = await page.evaluate(() => {
    try {
      return window.localStorage.getItem("1inme.auth.token");
    } catch {
      return null;
    }
  });
  if (storedToken !== MOCK_SOCIAL_TOKEN) {
    fail(
      `native oauth return did not persist the /auth/social session token ` +
        `(expected ${MOCK_SOCIAL_TOKEN}, got ${storedToken})`,
    );
  }
  log("oauth-callback native return: provider+id_token forwarded to /auth/social and landed in the signed-in tabs");
}

// The native-SDK access_token leg: some providers/flows hand back an
// access_token instead of an id_token. The deep link then carries
// ?provider=…&access_token=… and the screen must forward exactly that to
// POST /auth/social — { provider, access_token } and crucially NO id_token,
// so a regression that drops or mislabels the access_token is caught. Mirrors
// runOAuthCallbackNativeSuccess but for the access_token branch.
async function runOAuthCallbackNativeAccessTokenSuccess(page, social) {
  social.req = null;
  social.mode = "success";
  await clearSessionKeepOnboarding(page);
  const accessToken = "google-access-token-e2e";
  const query = `?provider=google&access_token=${encodeURIComponent(accessToken)}`;
  await gotoOAuthCallback(page, query);
  await waitForSignedInTabs(page);

  if (!social.req) {
    fail("the native oauth (access_token) return never POSTed to /api/v1/auth/social");
  }
  if (social.req.method !== "POST") {
    fail(`auth/social must be a POST, got ${social.req.method}`);
  }
  if (!/\/api\/v1\/auth\/social$/.test(new URL(social.req.url).pathname)) {
    fail(`auth/social hit the wrong URL: ${social.req.url}`);
  }
  let body;
  try {
    body = JSON.parse(social.req.body ?? "null");
  } catch {
    fail(`auth/social body was not valid JSON: ${social.req.body}`);
  }
  if (body?.provider !== "google" || body?.access_token !== accessToken) {
    fail(
      `auth/social body must forward { provider: "google", access_token }, ` +
        `got ${JSON.stringify(body)}`,
    );
  }
  // The access_token leg must NOT mislabel the credential as an id_token —
  // forwarding both (or the wrong key) would let a regression slip through.
  if (body?.id_token != null) {
    fail(
      `auth/social must not forward an id_token on the access_token leg, ` +
        `got ${JSON.stringify(body)}`,
    );
  }
  log("auth/social request method, URL and body (provider + access_token, no id_token) are correct");

  // The persisted token proves socialLogin → applySession actually ran with
  // the /auth/social response (we cleared the session first, and this token
  // is distinct from the token+user leg's).
  const storedToken = await page.evaluate(() => {
    try {
      return window.localStorage.getItem("1inme.auth.token");
    } catch {
      return null;
    }
  });
  if (storedToken !== MOCK_SOCIAL_TOKEN) {
    fail(
      `native oauth (access_token) return did not persist the /auth/social ` +
        `session token (expected ${MOCK_SOCIAL_TOKEN}, got ${storedToken})`,
    );
  }
  log("oauth-callback native return: provider+access_token forwarded to /auth/social and landed in the signed-in tabs");
}

// The native-SDK failure leg: a rejected /auth/social (422) must surface the
// screen's "Sign-in failed" error instead of hanging on the spinner, and
// must NOT sign the user in.
async function runOAuthCallbackNativeError(page, social) {
  social.req = null;
  social.mode = "fail";
  await clearSessionKeepOnboarding(page);
  await gotoOAuthCallback(page, "?provider=apple&id_token=bad-token");
  await page
    .getByText("Sign-in failed", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  // The screen surfaces the backend's error-envelope message rather than a
  // silent hang on the spinner.
  await page
    .getByText("We couldn't verify that sign-in", { exact: false })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  // It must actually have attempted the forward — proving the failure came
  // from the rejected request, not a missing-creds short-circuit.
  if (!social.req) {
    fail("the failing native oauth return never POSTed to /api/v1/auth/social");
  }
  if (social.req.method !== "POST") {
    fail(`failing auth/social must be a POST, got ${social.req.method}`);
  }
  const inTabs = await page
    .getByText("Profile", { exact: true })
    .first()
    .isVisible()
    .catch(() => false);
  if (inTabs) {
    fail("a failed /auth/social return wrongly signed the user in");
  }
  // And there must be a way back rather than a dead end.
  await page
    .getByText("Back to sign in", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  log("oauth-callback native failure (422) surfaced a friendly error, not a hang");
}

// Drive one web-browser OAuth provider button end to end with the round-trip
// mocked. Unlike Google (a native-SDK id_token flow), these providers go
// through WebBrowser.openAuthSessionAsync to the backend
// /user/social-oauth/{provider}/login URL, which on success redirects back to
// 1inme://oauth-callback. On native the OS deep-links that return into the app;
// on web there's no OS, so the mocked backend (context.route in main) stands in
// for that round-trip by navigating the opener to /oauth-callback?provider=…&
// id_token=… — exactly the params the deep-link would carry. oauth-callback.tsx
// then forwards { provider, id_token } to POST /auth/social and signs in.
//
// We assert three things the tappable-only check can't: (1) the button opened
// the provider-SPECIFIC backend login URL (catches a wrong/missing provider
// key), (2) the callback forwarded the correct { provider, id_token } to
// /auth/social, and (3) the app landed in the signed-in tabs.
async function runWebProviderSuccessPath(page, social, webOauth, providerId, label) {
  social.req = null;
  social.mode = "success";
  webOauth.req = null;
  webOauth.mode = "success";
  // We may be arriving from an /oauth-callback URL (the previous step) or the
  // signed-in tabs (the previous provider). On the CURRENT page (already on the
  // app origin), mark onboarding complete AND clear the persisted session
  // BEFORE navigating: clearing first matters because if we navigated while the
  // previous provider's token was still stored, the app would boot signed-in
  // and block on authenticated calls to the real (un-mocked) backend. Then a
  // single goto APP_URL (which also escapes any leftover /oauth-callback URL)
  // lands directly on the login screen — no extra reload, which keeps this
  // 6-provider loop from piling up page loads and slowing the browser down.
  await page.evaluate(() => {
    try {
      window.localStorage.setItem("1inme.onboarding.complete", "1");
      window.localStorage.removeItem("1inme.auth.token");
      window.localStorage.removeItem("1inme.auth.user");
    } catch {}
  });
  await page.goto(appBaseUrl, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });
  try {
    await page
      .getByText("Welcome back", { exact: false })
      .waitFor({ timeout: STEP_TIMEOUT_MS });
  } catch {
    // Onboarding shortcut didn't take — fall back to the full harness walk.
    await reachLoginScreen(page);
  }

  const btn = page.locator(`[aria-label="${label}"]`).first();
  await btn.waitFor({ timeout: STEP_TIMEOUT_MS });
  if (!(await btn.isVisible())) {
    fail(`web provider "${label}" button is not visible on the login screen`);
  }

  // Pair the click with the popup event: onSocial calls
  // WebBrowser.openAuthSessionAsync, which window.open()s the backend login
  // URL. A missing/broken handler would never open a popup and this would
  // time out — exactly the silent break we're guarding against.
  const [popup] = await Promise.all([
    page.waitForEvent("popup", { timeout: STEP_TIMEOUT_MS }),
    btn.click(),
  ]);
  void popup;
  log(`${label}: tapped; OAuth popup opened to the backend login URL`);

  // The signed-in tab bar is the proof the whole round-trip completed.
  await waitForSignedInTabs(page);
  log(`${label}: the success path landed in the signed-in tabs`);

  // (1) The button must have opened the provider-specific backend login URL.
  if (!webOauth.req) {
    fail(`${label}: the backend social-oauth login URL was never opened`);
  }
  const oauthUrl = new URL(webOauth.req.url);
  if (
    !new RegExp(`/user/social-oauth/${providerId}/login$`).test(oauthUrl.pathname)
  ) {
    fail(
      `${label}: opened the wrong backend URL — expected ` +
        `/user/social-oauth/${providerId}/login, got ${oauthUrl.pathname}`,
    );
  }
  if (oauthUrl.searchParams.get("source") !== "mobile") {
    fail(
      `${label}: backend login URL missing source=mobile (${webOauth.req.url})`,
    );
  }
  if (oauthUrl.searchParams.get("return") !== "1inme://oauth-callback") {
    fail(
      `${label}: backend login URL must return to 1inme://oauth-callback, got ` +
        `${oauthUrl.searchParams.get("return")}`,
    );
  }

  // (2) The callback must have forwarded { provider, id_token } to /auth/social.
  if (!social.req) {
    fail(`${label}: the success path never POSTed to /api/v1/auth/social`);
  }
  if (social.req.method !== "POST") {
    fail(`${label}: auth/social must be a POST, got ${social.req.method}`);
  }
  if (!/\/api\/v1\/auth\/social$/.test(new URL(social.req.url).pathname)) {
    fail(`${label}: auth/social hit the wrong URL: ${social.req.url}`);
  }
  let body;
  try {
    body = JSON.parse(social.req.body ?? "null");
  } catch {
    fail(`${label}: auth/social body was not valid JSON: ${social.req.body}`);
  }
  if (body?.provider !== providerId || body?.id_token !== MOCK_WEB_ID_TOKEN) {
    fail(
      `${label}: auth/social body must be { provider:"${providerId}", ` +
        `id_token:"${MOCK_WEB_ID_TOKEN}" }, got ${JSON.stringify(body)}`,
    );
  }

  // (3) The persisted token proves socialLogin → applySession actually ran with
  // the /auth/social response (resetToLogin cleared the session first, and this
  // token is distinct from the OTP / demo / token+user legs).
  const storedToken = await page.evaluate(() => {
    try {
      return window.localStorage.getItem("1inme.auth.token");
    } catch {
      return null;
    }
  });
  if (storedToken !== MOCK_SOCIAL_TOKEN) {
    fail(
      `${label}: did not persist the /auth/social session token ` +
        `(expected ${MOCK_SOCIAL_TOKEN}, got ${storedToken})`,
    );
  }
  log(
    `${label}: opened /user/social-oauth/${providerId}/login, forwarded ` +
      `{ provider:"${providerId}", id_token } to /auth/social, and signed in`,
  );
}

// Drive one web-browser OAuth provider button through a FAILING round-trip. The
// success loop above only proves the happy path; a provider that hangs,
// dead-ends, or wrongly signs the user in when the popup is closed or the
// backend rejects the token would slip past it. Two failure mechanisms are
// covered (selected by `failureMode`), mirroring how a real failure arrives:
//
//   - "cancel": the user closes/denies the provider popup. The mocked backend
//     redirect carries ?error=access_denied (webOauth.mode="cancel"), so
//     oauth-callback.tsx maps it to "You cancelled the sign-in." WITHOUT ever
//     forwarding to /auth/social.
//   - "backend422": the popup returns a valid provider+id_token, but the
//     backend rejects it — /auth/social 422s (social.mode="fail"), so
//     oauth-callback.tsx surfaces the backend's error-envelope message.
//
// In BOTH cases we assert the friendly "Sign-in failed" screen appears, there's
// a way back, and crucially the app does NOT sign the user in — no signed-in
// tabs and no persisted session token. This is the per-web-provider analogue of
// runOAuthCallbackNativeError (which only covers the native-SDK leg).
async function runWebProviderErrorPath(
  page,
  social,
  webOauth,
  providerId,
  label,
  failureMode,
) {
  social.req = null;
  webOauth.req = null;
  if (failureMode === "cancel") {
    // The redirect carries ?error=access_denied; /auth/social is never reached.
    webOauth.mode = "cancel";
    social.mode = "success";
  } else {
    // The redirect carries a valid provider+id_token that /auth/social rejects.
    webOauth.mode = "success";
    social.mode = "fail";
  }

  // Same reset-then-land-on-login dance as the success path: clear the session
  // BEFORE navigating (a leftover token would boot us signed-in and fire
  // authenticated calls at the un-mocked backend), then a single goto lands on
  // the login screen — also escaping any leftover /oauth-callback URL.
  await page.evaluate(() => {
    try {
      window.localStorage.setItem("1inme.onboarding.complete", "1");
      window.localStorage.removeItem("1inme.auth.token");
      window.localStorage.removeItem("1inme.auth.user");
    } catch {}
  });
  await page.goto(appBaseUrl, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });
  try {
    await page
      .getByText("Welcome back", { exact: false })
      .waitFor({ timeout: STEP_TIMEOUT_MS });
  } catch {
    await reachLoginScreen(page);
  }

  const btn = page.locator(`[aria-label="${label}"]`).first();
  await btn.waitFor({ timeout: STEP_TIMEOUT_MS });
  if (!(await btn.isVisible())) {
    fail(`web provider "${label}" button is not visible on the login screen`);
  }

  // The button must still open the popup even on the failure path — that's the
  // backend login URL; a missing/broken handler would never open one.
  const [popup] = await Promise.all([
    page.waitForEvent("popup", { timeout: STEP_TIMEOUT_MS }),
    btn.click(),
  ]);
  void popup;
  log(`${label} (${failureMode}): tapped; OAuth popup opened to the backend login URL`);

  // The return screen must surface a friendly failure instead of hanging on the
  // spinner.
  await page
    .getByText("Sign-in failed", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });

  if (failureMode === "cancel") {
    // The cancel code maps to friendly copy, and the screen must NOT have
    // forwarded anything to the backend (it short-circuits on ?error=…).
    await page
      .getByText("You cancelled the sign-in.", { exact: false })
      .waitFor({ timeout: STEP_TIMEOUT_MS });
    if (social.req) {
      fail(
        `${label} (cancel): a cancelled return must NOT POST to /auth/social, ` +
          `but it did (${social.req.url})`,
      );
    }
  } else {
    // The backend's 422 error-envelope message must surface…
    await page
      .getByText("We couldn't verify that sign-in", { exact: false })
      .waitFor({ timeout: STEP_TIMEOUT_MS });
    // …and it must actually have attempted the forward with the right provider,
    // proving the failure came from the rejected request, not a short-circuit.
    if (!social.req) {
      fail(`${label} (backend422): never POSTed to /api/v1/auth/social`);
    }
    if (social.req.method !== "POST") {
      fail(
        `${label} (backend422): auth/social must be a POST, got ${social.req.method}`,
      );
    }
    let body;
    try {
      body = JSON.parse(social.req.body ?? "null");
    } catch {
      fail(
        `${label} (backend422): auth/social body was not valid JSON: ${social.req.body}`,
      );
    }
    if (body?.provider !== providerId) {
      fail(
        `${label} (backend422): auth/social must forward provider ` +
          `"${providerId}", got ${JSON.stringify(body)}`,
      );
    }
  }

  // Whichever way it failed, the app must NOT have signed the user in.
  const inTabs = await page
    .getByText("Profile", { exact: true })
    .first()
    .isVisible()
    .catch(() => false);
  if (inTabs) {
    fail(`${label} (${failureMode}): a failed web sign-in wrongly signed the user in`);
  }
  // And no session may have been persisted.
  const storedToken = await page.evaluate(() => {
    try {
      return window.localStorage.getItem("1inme.auth.token");
    } catch {
      return null;
    }
  });
  if (storedToken) {
    fail(
      `${label} (${failureMode}): a failed web sign-in wrongly persisted a ` +
        `session token (${storedToken})`,
    );
  }
  // There must be a way back rather than a dead end.
  await page
    .getByText("Back to sign in", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });

  // Restore the shared mocks to their default so later steps aren't affected.
  webOauth.mode = "success";
  social.mode = "success";

  log(
    `${label} (${failureMode}): surfaced a friendly error, stayed on the sign-in ` +
      `screen, and persisted no session token`,
  );
}

// Drive the main flow against an already-acquired server (booted in parallel by
// the orchestrator, or reused via APP_URL). The build has no API base
// configured, so every backend call is mocked below and nothing depends on the
// live proxied dev domain or an RDS-backed render. Teardown of any throwaway
// child is the orchestrator's job.
async function main(server) {
  const child = server.child; // null when an already-running server is reused
  appBaseUrl = server.appUrl;
  if (server.explicit) {
    log("using the already-running main-flow server at", appBaseUrl);
  }

  log("launching chromium against", appBaseUrl);
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 400, height: 720 },
  });
  const page = await context.newPage();
  page.on("pageerror", (e) => log("pageerror:", e.message));
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

  // This throwaway build has no API base configured, so on web every backend
  // call goes to `${window.location.origin}/api/...` (i.e. our own server).
  // Block every /api/** call so nothing ever reaches a real backend. The
  // specific auth mocks below are registered AFTER this catch-all, and
  // Playwright runs route handlers most-recently-added-first, so the specific
  // handlers win for the URLs they match and this only catches everything
  // else.
  await page.route("**/api/**", (route) => route.abort());

  // ---- Intercept the auth endpoints so we never touch a real backend.
  // We record the requests so we can assert their URL / method / body, and
  // fulfill them with the exact `{data:{...}}` envelopes the app expects.
  let sendReq = null;
  let verifyReq = null;
  // Mutable holder for the most recent demo request, reset before each run.
  const demo = { req: null };
  // Mutable holder for the most recent /auth/social request. `mode` flips
  // between "success" and "fail" so one run signs in and the next 422s.
  const social = { req: null, mode: "success" };

  await page.route("**/api/v1/auth/otp/send", async (route) => {
    const req = route.request();
    sendReq = {
      url: req.url(),
      method: req.method(),
      body: req.postData(),
    };
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: { sent: true } }),
    });
  });

  await page.route("**/api/v1/auth/otp/verify", async (route) => {
    const req = route.request();
    verifyReq = {
      url: req.url(),
      method: req.method(),
      body: req.postData(),
    };
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        data: {
          token: MOCK_TOKEN,
          user: {
            id: 7,
            display_name: "Creator",
            email: EMAIL,
          },
        },
      }),
    });
  });

  await page.route("**/api/v1/auth/demo", async (route) => {
    const req = route.request();
    demo.req = {
      url: req.url(),
      method: req.method(),
      body: req.postData(),
    };
    let role = "user";
    try {
      role = JSON.parse(req.postData() ?? "{}")?.role ?? "user";
    } catch {}
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        data: {
          token: MOCK_TOKEN,
          user: {
            id: role === "user" ? 11 : 12,
            display_name: role === "user" ? "Demo User" : "Demo Admin",
            email: role === "user" ? "demo-user@example.com" : "demo-admin@example.com",
          },
        },
      }),
    });
  });

  // The native-SDK OAuth return forwards the provider's id_token/access_token
  // to POST /auth/social. In "success" mode we return the standard
  // {data:{token,user}} envelope; in "fail" mode we return the 422 error
  // envelope so the screen's error path is exercised.
  await page.route("**/api/v1/auth/social", async (route) => {
    const req = route.request();
    social.req = {
      url: req.url(),
      method: req.method(),
      body: req.postData(),
    };
    if (social.mode === "fail") {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          error: {
            message: "We couldn't verify that sign-in. Please try again.",
            code: "invalid_token",
          },
        }),
      });
      return;
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        data: {
          token: MOCK_SOCIAL_TOKEN,
          user: {
            id: 42,
            display_name: "Native OAuth User",
            email: "native-oauth@example.com",
          },
        },
      }),
    });
  });

  // The web-browser OAuth providers open WebBrowser.openAuthSessionAsync to the
  // backend /user/social-oauth/{provider}/login URL (in a popup on web). This
  // context-level route (popups are separate pages, so page.route wouldn't see
  // them) records the opened URL — proving the button targeted the right
  // provider — and stands in for the backend + OS deep-link by navigating the
  // opener to /oauth-callback with the OAuth result, exactly as the native
  // round-trip would. oauth-callback.tsx then forwards it to /auth/social.
  // `mode` flips the simulated backend redirect: "success" returns a valid
  // provider+id_token (the happy round-trip), while "cancel" returns
  // ?error=access_denied — the user closing/denying the provider popup — so the
  // failure loop can exercise oauth-callback's cancel path per web provider.
  const webOauth = { req: null, mode: "success" };
  await context.route("**/user/social-oauth/**", async (route) => {
    const req = route.request();
    webOauth.req = { url: req.url(), method: req.method() };
    const m = new URL(req.url()).pathname.match(/social-oauth\/([^/]+)\/login/);
    const provider = m ? m[1] : "";
    // The opener must land on the EXPO app's origin (appBaseUrl), not this
    // popup's backend origin: getBaseUrl() points OAuth at the proxy domain
    // (via EXPO_PUBLIC_DOMAIN), but the app itself is only served from
    // appBaseUrl, so a relative redirect would strand the opener on a domain
    // without the app.
    const dest =
      webOauth.mode === "cancel"
        ? `${new URL(appBaseUrl).origin}/oauth-callback?error=access_denied`
        : `${new URL(appBaseUrl).origin}/oauth-callback` +
          `?provider=${encodeURIComponent(provider)}` +
          `&id_token=${encodeURIComponent(MOCK_WEB_ID_TOKEN)}`;
    await route.fulfill({
      status: 200,
      contentType: "text/html",
      body:
        `<!doctype html><meta charset="utf-8"><script>` +
        `try{if(window.opener){window.opener.location.href=${JSON.stringify(dest)};}}catch(e){}` +
        `</script>`,
    });
  });

  // Drives one demo button end to end: assert it's tappable, click it, mock
  // /auth/demo, and assert the app lands in the signed-in tabs with the
  // right role on the wire.
  async function runDemoFlow(buttonLabel, expectedRole) {
    demo.req = null;
    const btn = page.getByText(buttonLabel, { exact: true });
    await btn.waitFor({ timeout: STEP_TIMEOUT_MS });
    if (!(await btn.isVisible())) {
      fail(`the "${buttonLabel}" button is not visible on the login screen`);
    }
    await btn.click();
    log(`clicked "${buttonLabel}"`);

    await waitForSignedInTabs(page);
    log(`"${buttonLabel}" landed in the signed-in tabs`);

    if (!demo.req) {
      fail(`"${buttonLabel}" never POSTed to /api/v1/auth/demo`);
    }
    if (demo.req.method !== "POST") {
      fail(`auth/demo must be a POST, got ${demo.req.method}`);
    }
    if (!/\/api\/v1\/auth\/demo$/.test(new URL(demo.req.url).pathname)) {
      fail(`auth/demo hit the wrong URL: ${demo.req.url}`);
    }
    let body;
    try {
      body = JSON.parse(demo.req.body ?? "null");
    } catch {
      fail(`auth/demo body was not valid JSON: ${demo.req.body}`);
    }
    if (body?.role !== expectedRole) {
      fail(
        `"${buttonLabel}" must POST { role: "${expectedRole}" }, ` +
          `got ${JSON.stringify(body)}`,
      );
    }
    log(`auth/demo request method, URL and role ("${expectedRole}") are correct`);
  }

  try {
    try {
      await page.goto(appBaseUrl, {
        waitUntil: "domcontentloaded",
        timeout: NAV_TIMEOUT_MS,
      });
      // Wait for ANY in-app text so we know React actually mounted.
      await page.waitForFunction(
        () => document.body && document.body.innerText.trim().length > 0,
        null,
        { timeout: NAV_TIMEOUT_MS },
      );
    } catch (e) {
      // With a reused (explicit) server the server is someone else's
      // responsibility, so a connection refused = it's down → skip gracefully.
      // With our own throwaway server (already confirmed ready) a failure here
      // is a real problem, so let it propagate.
      if (server.explicit) {
        await browser.close();
        skip(
          `could not reach the server at ${appBaseUrl} ` +
            `(${e?.message ?? "unknown error"}). Is it running?`,
        );
        return;
      }
      throw e;
    }

    log("app mounted; navigating to login screen");
    await reachLoginScreen(page);

    // -----------------------------------------------------------------
    // Step 1: every visible social-provider button is tappable.
    // -----------------------------------------------------------------
    await assertSocialButtonsTappable(page);

    // -----------------------------------------------------------------
    // Step 2: both demo buttons click through to the signed-in tabs.
    // Each one signs in, so reset back to the login screen between runs
    // (and again before the OTP flow).
    // -----------------------------------------------------------------
    await runDemoFlow("Demo as user", "user");
    await resetToLogin(page);

    await runDemoFlow("Demo as admin", "super_admin");
    await resetToLogin(page);

    // -----------------------------------------------------------------
    // Step 3: the login screen renders an email field + a tappable
    // "Send code" button.
    // -----------------------------------------------------------------
    const emailField = page.getByPlaceholder("you@example.com");
    await emailField.waitFor({ timeout: STEP_TIMEOUT_MS });
    await emailField.fill(EMAIL);
    log("typed email into the login field");

    const sendBtn = page.getByText("Send code", { exact: true });
    await sendBtn.waitFor({ timeout: STEP_TIMEOUT_MS });
    if (!(await sendBtn.isVisible())) {
      fail('the "Send code" button is not visible on the login screen');
    }

    await sendBtn.click();
    log('clicked "Send code"');

    // -----------------------------------------------------------------
    // Step 4: the OTP-send request fired with the right URL/method/body,
    // and the screen advanced to the verify step.
    // -----------------------------------------------------------------
    await page
      .getByText("Check your inbox", { exact: false })
      .waitFor({ timeout: STEP_TIMEOUT_MS });
    log('verify screen appeared ("Check your inbox")');

    if (!sendReq) {
      fail("the login screen never POSTed to /api/v1/auth/otp/send");
    }
    if (sendReq.method !== "POST") {
      fail(`otp/send must be a POST, got ${sendReq.method}`);
    }
    if (!/\/api\/v1\/auth\/otp\/send$/.test(new URL(sendReq.url).pathname)) {
      fail(`otp/send hit the wrong URL: ${sendReq.url}`);
    }
    let sendBody;
    try {
      sendBody = JSON.parse(sendReq.body ?? "null");
    } catch {
      fail(`otp/send body was not valid JSON: ${sendReq.body}`);
    }
    if (sendBody?.identifier !== EMAIL || sendBody?.type !== "email") {
      fail(
        `otp/send body must be { identifier: "${EMAIL}", type: "email" }, ` +
          `got ${JSON.stringify(sendBody)}`,
      );
    }
    log("otp/send request URL, method and body are correct");

    // -----------------------------------------------------------------
    // Step 5: typing the code and verifying signs the user in (lands in
    // the signed-in tabs).
    // -----------------------------------------------------------------
    const codeField = page.getByPlaceholder("123456");
    await codeField.waitFor({ timeout: STEP_TIMEOUT_MS });
    await codeField.fill(CODE);
    log("typed the verification code");

    const verifyBtn = page.getByText("Verify and sign in", { exact: true });
    await verifyBtn.waitFor({ timeout: STEP_TIMEOUT_MS });
    await verifyBtn.click();
    log('clicked "Verify and sign in"');

    // The signed-in tab bar shows the "Profile" tab label, which never
    // appears on the auth screens. Wait for it as the "we're in" signal.
    await waitForSignedInTabs(page);
    // And the verify header should be gone.
    const stillOnVerify = await page
      .getByText("Check your inbox", { exact: false })
      .isVisible()
      .catch(() => false);
    if (stillOnVerify) {
      fail("still on the verify screen after a successful sign-in");
    }
    log("landed in the signed-in tabs");

    if (!verifyReq) {
      fail("the verify screen never POSTed to /api/v1/auth/otp/verify");
    }
    if (verifyReq.method !== "POST") {
      fail(`otp/verify must be a POST, got ${verifyReq.method}`);
    }
    if (!/\/api\/v1\/auth\/otp\/verify$/.test(new URL(verifyReq.url).pathname)) {
      fail(`otp/verify hit the wrong URL: ${verifyReq.url}`);
    }
    let verifyBody;
    try {
      verifyBody = JSON.parse(verifyReq.body ?? "null");
    } catch {
      fail(`otp/verify body was not valid JSON: ${verifyReq.body}`);
    }
    if (
      verifyBody?.identifier !== EMAIL ||
      verifyBody?.type !== "email" ||
      verifyBody?.code !== CODE
    ) {
      fail(
        `otp/verify body must be { identifier, type: "email", code }, ` +
          `got ${JSON.stringify(verifyBody)}`,
      );
    }
    log("otp/verify request URL, method and body are correct");

    // -----------------------------------------------------------------
    // Step 6: the OAuth deep-link return leg. The social buttons above
    // are only asserted tappable (clicking opens a real external round
    // trip), so the return screen that actually reads the redirect and
    // signs the user in is exercised here directly.
    // -----------------------------------------------------------------
    await runOAuthCallbackSuccess(page);
    await runOAuthCallbackErrors(page);
    await runOAuthCallbackNativeSuccess(page, social);
    await runOAuthCallbackNativeAccessTokenSuccess(page, social);
    await runOAuthCallbackNativeError(page, social);

    // -----------------------------------------------------------------
    // Step 7: drive EVERY web-browser OAuth provider button end to end.
    // The tappable-only check above can't catch a wrong/missing provider
    // key or a broken handler in a specific button; here each one is
    // clicked through the full mocked round-trip, asserting it opens the
    // provider-specific backend login URL, forwards { provider, id_token }
    // to /auth/social, and lands in the signed-in tabs.
    // -----------------------------------------------------------------
    for (const { id, label } of WEB_OAUTH_PROVIDERS) {
      await runWebProviderSuccessPath(page, social, webOauth, id, label);
    }

    // -----------------------------------------------------------------
    // Step 8: drive EVERY web-browser OAuth provider button through a
    // FAILING round-trip. The success loop only proves the happy path; a
    // provider that hangs, dead-ends, or wrongly signs the user in when
    // the popup is cancelled or the backend 422s would slip past it.
    // Alternate the two failure mechanisms across providers so both the
    // cancelled-popup and rejected-token paths are covered.
    // -----------------------------------------------------------------
    for (let i = 0; i < WEB_OAUTH_PROVIDERS.length; i++) {
      const { id, label } = WEB_OAUTH_PROVIDERS[i];
      const failureMode = i % 2 === 0 ? "cancel" : "backend422";
      await runWebProviderErrorPath(page, social, webOauth, id, label, failureMode);
    }

    log(
      "PASS: social buttons, demo logins, OTP sign-in, the OAuth deep-link " +
        "return (browser + native-SDK legs), every web-browser provider's " +
        "full sign-in, and every web-browser provider's failure path all " +
        "behave correctly.",
    );
  } finally {
    await browser.close();
    // Free this flow's throwaway server as soon as we're done with it (no-op
    // when a reused server was used, since child stays null). A backstop in the
    // process "exit" handler reaps anything left if we exit early.
    stopExpo(child);
  }
}

// Orchestrate the run. The expensive part of this suite is Expo's boot (well
// over a minute per server), and the two flows need DIFFERENT bundles — the
// Google variant must be built with EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID. Booting
// them back-to-back pushed the whole run past a 2-minute CI window. So:
//   - Acquire both servers in PARALLEL (Promise.all), overlapping the two
//     boots into roughly one boot's wall-clock instead of two.
//   - Either flow's server can be REUSED instead of booted (APP_URL /
//     GOOGLE_APP_URL), so CI can pre-start servers once and skip the boot.
//   - AUTH_FLOW_ONLY can run just one flow, so CI can split the suite into two
//     independent, parallel jobs that each boot only their own server.
// A throwaway that can't come up makes its flow SKIP (exit 0), never fail —
// preserving the "skip when the env can't bring Metro up" contract.
async function run() {
  const runMain = FLOW === "both" || FLOW === "main";
  const runGoogle = FLOW === "both" || FLOW === "google";

  const [mainServer, googleServer] = await Promise.all([
    runMain
      ? acquireServer("main flow", EXPLICIT_APP_URL)
      : Promise.resolve(null),
    runGoogle
      ? acquireServer("Google variant", EXPLICIT_GOOGLE_APP_URL, {
          EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID: GOOGLE_VARIANT_WEB_CLIENT_ID,
        })
      : Promise.resolve(null),
  ]);

  if (runMain) {
    if (!mainServer) {
      // No main server → skip the main flow. The "exit" handler reaps any
      // Google child that did boot in parallel.
      skip("the throwaway Expo server could not start; skipping the main flow");
      return;
    }
    await main(mainServer);
  }

  if (runGoogle) {
    if (!googleServer) {
      log("Google variant SKIP: the throwaway Expo server could not start");
      return;
    }
    await runGoogleVariant(googleServer);
  }
}

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
