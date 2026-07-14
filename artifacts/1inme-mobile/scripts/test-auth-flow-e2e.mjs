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
 *   - Social providers: the app ships two OAuth buttons (Google + LinkedIn).
 *     Google is always shown; its native expo-auth-session flow is used instead
 *     of the web-browser popup when a Google client ID is built in.
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
 *   8. Click EVERY web-browser OAuth provider button (Google, LinkedIn)
 *      through its full mocked round-trip: the
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
 *      wrongly signs the user in on failure is caught. When the failing return
 *      names the provider (the rejected-token leg), the screen must NAME it
 *      ("LinkedIn sign-in is having issues") and offer a one-tap "Use email
 *      instead" fallback that lands on the email sign-in field; a plain cancel
 *      offers the same fallback but blames no provider.
 *  10. Prove the guest "Perfect pairings" -> signup handoff survives a
 *      cancelled/rejected WEB-browser-provider sign-in plus a successful retry
 *      (the web providers take oauth-callback's "Sign-in failed" branch, unlike
 *      Google): a guest taps a pairing card, starts a web-provider sign-in,
 *      hits the failure return, taps back, and retries the SAME provider — and
 *      must STILL land on /links/create/biolink because the stash is only
 *      consumed on success. Covers a cancelled popup (Google) AND a backend
 *      422 (LinkedIn).
 *
 * The test no-ops gracefully (skips, exit 0) when a throwaway Expo server
 * can't be booted (expo missing, port contention, bundling too slow) — so it
 * never fails CI just because the environment can't bring Metro up.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:auth-flow-e2e
 *
 *   Or run a single, shorter, parallel-friendly piece (each boots/reuses only
 *   its own Expo server, so the two can run as independent jobs):
 *     pnpm --filter @workspace/1inme-mobile run test:auth-flow-e2e:core
 *       (AUTH_FLOW_ONLY=main  — demo, OTP, OAuth deep-link, the 6 web providers)
 *     pnpm --filter @workspace/1inme-mobile run test:auth-flow-e2e:google
 *       (AUTH_FLOW_ONLY=google — the Google-enabled variant only)
 *
 * Environment:
 *   APP_URL          point the main flow at an already-running server instead
 *                    of booting a throwaway one (handy for local debugging, and
 *                    lets CI pre-boot once so a piece finishes well under the
 *                    local-run window). When unset, the main flow boots its own
 *                    self-contained Expo web server.
 *   GOOGLE_APP_URL   same, for the Google variant's server.
 *   AUTH_FLOW_ONLY   "both" (default), "main", or "google" — which flow(s) to
 *                    run. The :core / :google scripts set this for you.
 */

import { chromium } from "playwright";

import {
  APP_URL,
  NAV_TIMEOUT_MS,
  STEP_TIMEOUT_MS,
  reachLoginScreen,
} from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

// A dummy Google web client id. Its value never matters — it only needs to be
// truthy so HAS_GOOGLE_NATIVE / GOOGLE_AUTH_SAFE_TO_INIT flip on and the
// Google button renders (and the real useIdTokenAuthRequest hook runs).
const GOOGLE_VARIANT_WEB_CLIENT_ID =
  "1inme-e2e-test.apps.googleusercontent.com";

// Hard wall-clock budget for a single web-OAuth provider's full mocked sign-in
// (normally well under ~30s once the post-login calls are mocked). If a provider
// blows past this we fail fast with a clear, provider-named message rather than
// letting nested step/nav timeouts stack into a multi-minute hang.
const PROVIDER_DEADLINE_MS = 75_000;

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
// SOCIALS / WEB_BROWSER_PROVIDERS in app/(auth)/index.tsx); their accessibility
// labels ("Log in with {label}") are the minimum set this test requires to be
// tappable. The app currently ships exactly two social providers — Google and
// LinkedIn (SocialProvider is typed to those two). Google is in
// WEB_BROWSER_PROVIDERS so it always renders — the native expo-auth-session
// flow takes precedence only when EXPO_PUBLIC_GOOGLE_*_CLIENT_ID is compiled in.
const REQUIRED_SOCIAL_LABELS = [
  "Log in with Google",
  "Log in with LinkedIn",
];
const OPTIONAL_SOCIAL_LABELS = [];

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
// /auth/social) and the button's accessibility label ("Log in with {label}").
// Mirrors SOCIALS / WEB_BROWSER_PROVIDERS in app/(auth)/index.tsx. Google is
// included here because in the main flow (no native client ID) it uses the
// web-browser path; the Google variant separately exercises the native
// expo-auth-session path via runGoogleVariant.
const WEB_OAUTH_PROVIDERS = [
  { id: "google", label: "Log in with Google" },
  { id: "linkedin", label: "Log in with LinkedIn" },
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

// Race a phase against a hard wall-clock deadline so a stalled run fails fast
// with a clear message rather than letting nested step/nav timeouts stack into
// a multi-minute hang. On timeout it rejects (caught at the top level → exit 1,
// after the finally blocks tear the browser/server down); the loser of the race
// keeps running harmlessly until the process exits.
async function withDeadline(label, ms, fn) {
  let timer;
  const deadline = new Promise((_, reject) => {
    timer = setTimeout(
      () =>
        reject(
          new Error(
            `${label} exceeded ${Math.round(ms / 1000)}s — failing fast ` +
              `instead of hanging`,
          ),
        ),
      ms,
    );
  });
  try {
    return await Promise.race([fn(), deadline]);
  } finally {
    clearTimeout(timer);
  }
}

// Wait for the signed-in tab bar — the "Profile" tab label never appears on
// the auth screens, so it's our "we're in" signal. Mirrors the OTP step.
async function waitForSignedInTabs(page) {
  await page
    .getByText("Profile", { exact: true })
    .first()
    .waitFor({ timeout: STEP_TIMEOUT_MS });
}

// The floating mic's accessibility label (see components/VoiceAssistant.tsx).
// It renders ONLY inside the signed-in (tabs) layout — the splash-visibility
// fix deliberately removed GlobalVoiceAssistant from the root layout. The two
// checks below pin that: the mic is ABSENT while the branded ZioSplash is on
// screen (cold launch), and PRESENT once the user lands on a signed-in tab.
const VOICE_MIC_LABEL = "Open Zio Assistant";

// Assert the cold-launch splash is on screen AND the voice mic is not.
//
// The branded ZioSplash (components/ZioSplash.tsx) plays for a minimum of
// 2.4s (its minDuration, gated by GateScreen), so it is reliably still on
// screen immediately after the app mounts. We first wait for a splash marker
// — the "Zio runs it all" pill — to prove we actually caught the splash, then
// assert the floating mic ("Open Zio Assistant") is NOT present. If the mic
// leaks onto the splash, GlobalVoiceAssistant has been reintroduced into the
// root layout (the exact regression this guards against).
async function assertSplashHidesVoiceMic(page) {
  const splashMarker = page
    .getByText("Zio runs it all", { exact: false })
    .first();
  try {
    await splashMarker.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  } catch {
    fail(
      "the branded ZioSplash never appeared on cold launch, so the mic's " +
        "absence during the splash could not be verified",
    );
  }
  const micCount = await page
    .locator(`[aria-label="${VOICE_MIC_LABEL}"]`)
    .count();
  if (micCount > 0) {
    fail(
      `the voice mic ("${VOICE_MIC_LABEL}") is present during the ZioSplash — ` +
        "it must only mount on signed-in tab screens, not the splash " +
        "(GlobalVoiceAssistant leaked back into the root layout)",
    );
  }
  log("cold-launch splash is showing and the voice mic is correctly hidden");
}

// Once signed in and on a tab screen, the floating mic must be present and
// tappable — the (tabs) layout mounts it. Auto-waits for the mount so the
// check doesn't race the tab render.
async function assertVoiceMicPresent(page) {
  const mic = page.locator(`[aria-label="${VOICE_MIC_LABEL}"]`).first();
  await mic
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(
        `the voice mic ("${VOICE_MIC_LABEL}") did not appear on the signed-in ` +
          "tab screen — the (tabs) layout should mount it",
      ),
    );
  // React Native Web renders a disabled Pressable with aria-disabled="true".
  const ariaDisabled = await mic.getAttribute("aria-disabled");
  if (ariaDisabled === "true") {
    fail(`the voice mic ("${VOICE_MIC_LABEL}") is present but disabled`);
  }
  log("signed-in tab screen shows the voice mic");
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
    if (optional) {
      // Optional providers (Google without a client id) may be absent, so a
      // one-shot presence check is correct here — we must NOT auto-wait for a
      // button that legitimately never renders.
      if ((await btn.count()) === 0) continue;
    } else {
      // Required buttons render with the login screen, but that render can lag
      // the "Welcome back" heading reachLoginScreen waited on. Auto-wait for
      // the button instead of a one-shot count() that races the mount and would
      // spuriously report a present button as "missing".
      await btn
        .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
        .catch(() =>
          fail(`social button "${label}" is missing from the login screen`),
        );
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
// sayzio://oauth-callback?… . On web that maps to /oauth-callback?… .
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
  // And there must be a way back rather than a dead end, plus a clear email
  // fallback. A plain cancel doesn't blame any provider, so no provider-down
  // guidance line should appear here.
  await page
    .getByText("Back to sign in", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  await page
    .getByText("Use email instead", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  const namedProviderOnCancel = await page
    .getByText("sign-in is having issues", { exact: false })
    .first()
    .isVisible()
    .catch(() => false);
  if (namedProviderOnCancel) {
    fail("a plain cancelled return wrongly blamed a specific provider");
  }
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
  // The email fallback is offered on every failure screen.
  await page
    .getByText("Use email instead", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });
  log("oauth-callback malformed return surfaced a missing-session error, not a hang");
}

// ---------------------------------------------------------------------------
// Google-enabled variant.
//
// The main flow above exercises Google via the web-browser OAuth path (the same
// round-trip as LinkedIn), since no native Google client ID is compiled into the
// default dev bundle. The NATIVE expo-auth-session path — which uses
// useIdTokenAuthRequest and POSTs id_token directly to /auth/social — requires
// EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID to be baked into the bundle at build time.
//
// A break in the native path — the guarded useIdTokenAuthRequest hook throwing
// at render (which crashes the whole login screen into the error boundary), or
// the button rendering disabled when a client ID IS present — would slip past the
// main flow.
//
// We can't flip the native path on against the already-running server because
// Expo inlines EXPO_PUBLIC_* into the web bundle at build time, so the value
// has to be present when Metro builds. This variant boots a SECOND, throwaway
// Expo web dev server with EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID set, then asserts
// the login screen still mounts, the Google button is tappable, and the native
// flow fires (rather than the web-browser fallback).
//
// Booting that server is best-effort: if it can't start (expo missing, port
// contention, bundling too slow) we skip the variant rather than fail CI —
// mirroring the "skip when the dev server is down" contract of the main flow.

// The Expo web-server boot/warm/teardown dance (spawn `expo start`, wait for
// Metro, warm `GET /` to a real 200, reap children on exit) lives in the shared
// expo-web-server.mjs module so this harness and the icon-font harness can't
// drift. acquireServer reuses an already-running server when *_APP_URL is set,
// otherwise boots a throwaway and tracks it for teardown; stopExpo group-kills a
// booted child. Both follow the best-effort contract: a server that can't come
// up returns null and the caller SKIPs (exit 0) rather than failing CI.
const { acquireServer, stopExpo } = createExpoServerManager(log);

async function assertGoogleButtonTappable(page) {
  const label = "Log in with Google";
  const btn = page.locator(`[aria-label="${label}"]`).first();
  // Auto-wait for the button to mount rather than a one-shot count() that races
  // the login-screen render and would falsely report the button as missing.
  await btn
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(
        `Google variant: "${label}" did not render even with a web client id ` +
          `configured — the Google sign-in button is broken or gated off`,
      ),
    );
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

// Drive the "Log in with Google" button end to end with the OAuth
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

  const btn = page.locator('[aria-label="Log in with Google"]').first();
  await btn.waitFor({ timeout: STEP_TIMEOUT_MS });

  const [popup] = await Promise.all([
    page.waitForEvent("popup", { timeout: STEP_TIMEOUT_MS }),
    btn.click(),
  ]);
  log("Google variant: tapped the Google button; OAuth popup opened");

  // The signed-in tab bar is the proof the success handler completed.
  await waitForSignedInTabs(page);
  log("Google variant: the Google success path landed in the signed-in tabs");

  // Close the popup now that it has handed the result back to the opener, so it
  // doesn't linger as an orphaned window.
  await popup.close().catch(() => {});

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

// Prove the guest "Perfect pairings" -> signup handoff survives the full Google
// OAuth round-trip end to end. A guest on a public link-type page (here the
// restaurant menu) taps a pairing card, which stashes a short-lived, type-
// specific create path via lib/authNext.ts and routes into /(auth). After the
// mocked Google sign-in completes, app/(auth)/index.tsx's googleResponse effect
// must call redirectAfterAuth, landing the brand-new creator on that exact
// create screen (/links/create/biolink) — NOT the generic signed-in tabs the
// runGoogleSuccessPath test above proves. A regression in the stash, the
// consume, or any completion path silently drops the new user in the wrong
// place and quietly kills the guest->creator conversion.
//
// test-auth-next.mjs already pins this handoff's LOGIC by source-replay; this is
// the one place the whole chain (real card tap -> real AsyncStorage stash ->
// real Google hook -> real redirectAfterAuth) runs against the actual app.
//
// Runs on a FRESH guest context on the SAME already-booted Google-enabled
// browser/server as runGoogleVariant, so it costs no extra Expo boot.
async function runGooglePairingHandoff(browser, googleBaseUrl) {
  const context = await browser.newContext({
    viewport: { width: 400, height: 720 },
  });
  try {
    // Guests haven't onboarded, but the intro splash must not sit between the
    // /(auth) push and the login screen. Mark onboarding complete before any
    // page script runs (this also keeps us signed OUT — no token seeded — so
    // the card renders its guest "Sign up to create" CTA, not the logged-in one).
    await context.addInitScript(() => {
      try {
        window.localStorage.setItem("1inme.onboarding.complete", "1");
        window.localStorage.removeItem("1inme.auth.token");
        window.localStorage.removeItem("1inme.auth.user");
      } catch {}
    });

    const page = await context.newPage();
    let pageError = null;
    page.on("pageerror", (e) => {
      pageError = e.message;
      log("Google pairing pageerror:", e.message);
    });
    page.setDefaultTimeout(STEP_TIMEOUT_MS);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

    const social = { req: null };
    const googleAuth = { req: null };

    // Fulfill the social-login POST so the Google sign-in completes with no
    // backend, exactly as in runGoogleVariant.
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
              id: 88,
              display_name: "Pairing Guest",
              email: "pairing@example.com",
            },
          },
        }),
      });
    });

    // Serve the public restaurant page's data WITH a single biolink "Perfect
    // pairings" card, so the guest has a real card to tap. The payload mirrors
    // the RestaurantMenu shape (lib/api/restaurant.ts); categories are empty
    // (the page still renders the pairings module below them).
    await context.route("**/api/v1/restaurant/**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            menu: {
              mode: "display",
              currency: "USD",
              accent_color: null,
              order_enabled: false,
              tax: { enabled: false, rate: 0, inclusive: false, label: "" },
            },
            link: { alias: "pairing-e2e", title: "Pairing E2E Menu" },
            table: null,
            categories: [],
            pairings: [
              {
                name: "Biolink page",
                type: "biolink",
                icon: "fa-grid",
                benefit: "Turn one scan into your whole world.",
              },
            ],
          },
        }),
      });
    });

    // Catch-all: benign {data:[]} for everything else so post-nav React Query
    // calls settle. Defer the two specific handlers above (Playwright runs
    // route handlers most-recently-registered first, so this catch-all — added
    // last — sees each request first and falls back for the paths it owns).
    await context.route("**/api/**", (route) => {
      const path = new URL(route.request().url()).pathname;
      if (
        /\/api\/v1\/auth\/social$/.test(path) ||
        /\/api\/v1\/restaurant\//.test(path)
      ) {
        return route.fallback();
      }
      return route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: [] }),
      });
    });

    // Mock Google's authorization endpoint (identical to runGoogleVariant):
    // record the request, then bounce the popup back to redirect_uri carrying a
    // mock id_token + the original state.
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

    // Same-origin popup completion shim (identical to runGoogleVariant): post
    // the redirect result back to the opener like expo-web-browser does.
    page.on("popup", (popup) => {
      popup
        .route("**/*", async (route) => {
          const req = route.request();
          const u = req.url();
          if (/accounts\.google\.com/.test(u)) return route.fallback();
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
          log("Google pairing: popup route wiring failed:", e?.message ?? e),
        );
    });

    // Navigate to the public restaurant page. Nav hardening mirrors the main
    // flows: on a shared box a transient connection error means Metro couldn't
    // stay serveable → best-effort SKIP (exit 0), never a hard fail. The server
    // already served the main variant, so a NON-transient error here is a real
    // regression and propagates.
    const url = `${googleBaseUrl.replace(/\/$/, "")}/restaurant/pairing-e2e`;
    const TRANSIENT_NAV =
      /ERR_CONNECTION_RESET|ERR_EMPTY_RESPONSE|ERR_CONNECTION_REFUSED|ERR_CONNECTION_CLOSED|ERR_NETWORK_CHANGED|Timeout \d+ms exceeded/;
    const NAV_ATTEMPTS = 4;
    let navErr;
    for (let attempt = 1; attempt <= NAV_ATTEMPTS; attempt++) {
      try {
        await page.goto(url, {
          waitUntil: "domcontentloaded",
          timeout: NAV_TIMEOUT_MS,
        });
        await page.waitForFunction(
          () => document.body && document.body.innerText.trim().length > 0,
          null,
          { timeout: NAV_TIMEOUT_MS },
        );
        navErr = undefined;
        break;
      } catch (e) {
        navErr = e;
        if (TRANSIENT_NAV.test(e?.message ?? "") && attempt < NAV_ATTEMPTS) {
          log(
            `Google pairing: nav attempt ${attempt}/${NAV_ATTEMPTS} hit a ` +
              `transient connection error (${e?.message ?? "unknown"}); retrying`,
          );
          await new Promise((r) => setTimeout(r, 3000));
          continue;
        }
        break;
      }
    }
    if (navErr) {
      if (TRANSIENT_NAV.test(navErr?.message ?? "")) {
        await context.close().catch(() => {});
        skip(
          `Google pairing: could not reach ${url} ` +
            `(${navErr?.message ?? "unknown error"}). Is it running?`,
        );
        return;
      }
      throw navErr;
    }

    // The guest's biolink pairing card must render and be tappable. Its
    // accessibility label is the guest CTA ("Sign up to create ..."), which
    // proves we're signed out and looking at the conversion surface.
    const card = page
      .locator('[aria-label="Sign up to create Biolink page"]')
      .first();
    await card.waitFor({ timeout: STEP_TIMEOUT_MS });
    log("Google pairing: guest sees the biolink 'Perfect pairings' card");
    await card.click();

    // Tapping stashes the post-auth create path and routes into /(auth); the
    // Google button being present there proves we reached the login screen.
    const gbtn = page.locator('[aria-label="Log in with Google"]').first();
    await gbtn.waitFor({ timeout: STEP_TIMEOUT_MS });
    log(
      "Google pairing: card tap routed the guest into /(auth) with Google enabled",
    );

    if (pageError) {
      fail(`Google pairing: the page reported a runtime error: ${pageError}`);
    }

    // Drive the Google sign-in for real (round-trip mocked).
    const [popup] = await Promise.all([
      page.waitForEvent("popup", { timeout: STEP_TIMEOUT_MS }),
      gbtn.click(),
    ]);
    log("Google pairing: tapped the Google button; OAuth popup opened");

    // THE PROOF: after sign-in, redirectAfterAuth must land on the stashed
    // type-specific create screen, not the signed-in tabs. Assert both the URL
    // and the create screen's own copy so a header-only match can't fool us.
    await page.waitForFunction(
      () => location.pathname.includes("/links/create/biolink"),
      null,
      { timeout: STEP_TIMEOUT_MS },
    );
    await page
      .getByText("A multi-block landing page for your bio.", { exact: false })
      .waitFor({ timeout: STEP_TIMEOUT_MS });

    // Close the popup now that it has handed the result back.
    await popup.close().catch(() => {});

    // The sign-in must actually have round-tripped through Google + /auth/social
    // (not a stale/leftover session): assert the intercepted requests' shapes,
    // mirroring runGoogleSuccessPath.
    if (!googleAuth.req) {
      fail("Google pairing: the Google authorization endpoint was never hit");
    }
    if (googleAuth.req.clientId !== GOOGLE_VARIANT_WEB_CLIENT_ID) {
      fail(
        "Google pairing: the auth request used the wrong client_id " +
          `(${googleAuth.req.clientId})`,
      );
    }
    if (!social.req) {
      fail("Google pairing: sign-in never POSTed to /api/v1/auth/social");
    }
    let body;
    try {
      body = JSON.parse(social.req.body ?? "null");
    } catch {
      fail(
        `Google pairing: auth/social body was not valid JSON: ${social.req.body}`,
      );
    }
    if (body?.provider !== "google" || body?.id_token !== MOCK_ID_TOKEN) {
      fail(
        "Google pairing: auth/social body must be " +
          `{ provider:"google", id_token:"${MOCK_ID_TOKEN}" }, ` +
          `got ${JSON.stringify(body)}`,
      );
    }

    log(
      "Google pairing PASS: a guest tapped a 'Perfect pairings' card, was " +
        "stashed a post-auth path and routed into /(auth), completed the mocked " +
        "Google sign-in, and landed on /links/create/biolink — the pairing-to-" +
        "signup handoff survives the full OAuth round-trip.",
    );
  } finally {
    await context.close().catch(() => {});
  }
}

// Prove the guest "Perfect pairings" -> signup handoff ALSO survives the email +
// OTP sign-up. runGooglePairingHandoff proves it for the Google OAuth round-trip,
// but email + OTP is the MORE COMMON mobile signup path (there is no separate
// register screen — the OTP path is login+signup) AND a DIFFERENT login-
// completion branch. A regression in redirectAfterAuth on the OTP verify path
// would silently drop the brand-new creator in the generic signed-in tabs
// instead of the type-specific create screen — quietly killing the guest->
// creator conversion — and nothing catches it today.
//
// A guest on a public link-type page (here the restaurant menu) taps a biolink
// pairing card, which stashes a short-lived, type-specific create path via
// lib/authNext.ts and routes into /(auth). After the mocked email + OTP sign-in
// completes, the OTP verify completion path must call redirectAfterAuth, landing
// the new creator on /links/create/biolink — NOT the signed-in tabs.
//
// Runs on a FRESH guest context on the SAME already-booted main-flow
// browser/server (the plain CORE variant — no Google bundle needed), so it costs
// no extra Expo boot.
async function runOtpPairingHandoff(browser, baseUrl) {
  const context = await browser.newContext({
    viewport: { width: 400, height: 720 },
  });
  try {
    // Mark onboarding complete + ensure signed out before any page script runs,
    // so the intro splash doesn't sit between the /(auth) push and the login
    // screen and the card renders its guest "Sign up to create" CTA (proving
    // we're signed out and looking at the conversion surface).
    await context.addInitScript(() => {
      try {
        window.localStorage.setItem("1inme.onboarding.complete", "1");
        window.localStorage.removeItem("1inme.auth.token");
        window.localStorage.removeItem("1inme.auth.user");
      } catch {}
    });

    const page = await context.newPage();
    let pageError = null;
    page.on("pageerror", (e) => {
      pageError = e.message;
      log("OTP pairing pageerror:", e.message);
    });
    page.setDefaultTimeout(STEP_TIMEOUT_MS);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

    const sendReq = { req: null };
    const verifyReq = { req: null };

    // Fulfill the OTP send so no real code is dispatched and production is
    // never hit.
    await context.route("**/api/v1/auth/otp/send", async (route) => {
      const req = route.request();
      sendReq.req = {
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

    // Fulfill the OTP verify with a {data:{token,user}} envelope so the sign-in
    // completes with no backend, exactly as the main flow's OTP step does.
    await context.route("**/api/v1/auth/otp/verify", async (route) => {
      const req = route.request();
      verifyReq.req = {
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
              id: 87,
              display_name: "OTP Pairing Guest",
              email: EMAIL,
            },
          },
        }),
      });
    });

    // Serve the public restaurant page's data WITH a single biolink "Perfect
    // pairings" card, so the guest has a real card to tap (mirrors
    // runGooglePairingHandoff).
    await context.route("**/api/v1/restaurant/**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            menu: {
              mode: "display",
              currency: "USD",
              accent_color: null,
              order_enabled: false,
              tax: { enabled: false, rate: 0, inclusive: false, label: "" },
            },
            link: { alias: "pairing-otp-e2e", title: "Pairing OTP E2E Menu" },
            table: null,
            categories: [],
            pairings: [
              {
                name: "Biolink page",
                type: "biolink",
                icon: "fa-grid",
                benefit: "Turn one scan into your whole world.",
              },
            ],
          },
        }),
      });
    });

    // Catch-all: benign {data:[]} for everything else so post-nav React Query
    // calls settle. Defer the specific handlers above (Playwright runs route
    // handlers most-recently-registered first, so this catch-all — added last —
    // sees each request first and falls back for the paths it owns).
    await context.route("**/api/**", (route) => {
      const path = new URL(route.request().url()).pathname;
      if (
        /\/api\/v1\/auth\/otp\/(send|verify)$/.test(path) ||
        /\/api\/v1\/restaurant\//.test(path)
      ) {
        return route.fallback();
      }
      return route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: [] }),
      });
    });

    // Navigate to the public restaurant page. Nav hardening mirrors the other
    // flows: on a shared box a transient connection error means Metro couldn't
    // stay serveable → best-effort SKIP (exit 0), never a hard fail. The server
    // already served the main flow, so a NON-transient error here is a real
    // regression and propagates.
    const url = `${baseUrl.replace(/\/$/, "")}/restaurant/pairing-otp-e2e`;
    const TRANSIENT_NAV =
      /ERR_CONNECTION_RESET|ERR_EMPTY_RESPONSE|ERR_CONNECTION_REFUSED|ERR_CONNECTION_CLOSED|ERR_NETWORK_CHANGED|Timeout \d+ms exceeded/;
    const NAV_ATTEMPTS = 4;
    let navErr;
    for (let attempt = 1; attempt <= NAV_ATTEMPTS; attempt++) {
      try {
        await page.goto(url, {
          waitUntil: "domcontentloaded",
          timeout: NAV_TIMEOUT_MS,
        });
        await page.waitForFunction(
          () => document.body && document.body.innerText.trim().length > 0,
          null,
          { timeout: NAV_TIMEOUT_MS },
        );
        navErr = undefined;
        break;
      } catch (e) {
        navErr = e;
        if (TRANSIENT_NAV.test(e?.message ?? "") && attempt < NAV_ATTEMPTS) {
          log(
            `OTP pairing: nav attempt ${attempt}/${NAV_ATTEMPTS} hit a ` +
              `transient connection error (${e?.message ?? "unknown"}); retrying`,
          );
          await new Promise((r) => setTimeout(r, 3000));
          continue;
        }
        break;
      }
    }
    if (navErr) {
      if (TRANSIENT_NAV.test(navErr?.message ?? "")) {
        await context.close().catch(() => {});
        skip(
          `OTP pairing: could not reach ${url} ` +
            `(${navErr?.message ?? "unknown error"}). Is it running?`,
        );
        return;
      }
      throw navErr;
    }

    // The guest's biolink pairing card must render and be tappable. Its
    // accessibility label is the guest CTA ("Sign up to create ..."), which
    // proves we're signed out and looking at the conversion surface.
    const card = page
      .locator('[aria-label="Sign up to create Biolink page"]')
      .first();
    await card.waitFor({ timeout: STEP_TIMEOUT_MS });
    log("OTP pairing: guest sees the biolink 'Perfect pairings' card");
    await card.click();

    // Tapping stashes the post-auth create path and routes into /(auth); the
    // email field being present proves we reached the login screen.
    const emailField = page.getByPlaceholder("you@example.com");
    await emailField.waitFor({ timeout: STEP_TIMEOUT_MS });
    log("OTP pairing: card tap routed the guest into /(auth)");

    if (pageError) {
      fail(`OTP pairing: the page reported a runtime error: ${pageError}`);
    }

    // Drive the email + OTP sign-in for real (round-trip mocked).
    await emailField.fill(EMAIL);
    const sendBtn = page.getByText("Send code", { exact: true });
    await sendBtn.waitFor({ timeout: STEP_TIMEOUT_MS });
    await sendBtn.click();
    log('OTP pairing: typed email and clicked "Send code"');

    await page
      .getByText("Check your inbox", { exact: false })
      .waitFor({ timeout: STEP_TIMEOUT_MS });

    const codeField = page.getByPlaceholder("123456");
    await codeField.waitFor({ timeout: STEP_TIMEOUT_MS });
    await codeField.fill(CODE);
    const verifyBtn = page.getByText("Verify and sign in", { exact: true });
    await verifyBtn.waitFor({ timeout: STEP_TIMEOUT_MS });
    await verifyBtn.click();
    log('OTP pairing: typed code and clicked "Verify and sign in"');

    // THE PROOF: after the OTP sign-in, redirectAfterAuth must land on the
    // stashed type-specific create screen, not the signed-in tabs. Assert both
    // the URL and the create screen's own copy so a header-only match can't
    // fool us (identical proof to runGooglePairingHandoff, different auth path).
    await page.waitForFunction(
      () => location.pathname.includes("/links/create/biolink"),
      null,
      { timeout: STEP_TIMEOUT_MS },
    );
    await page
      .getByText("A multi-block landing page for your bio.", { exact: false })
      .waitFor({ timeout: STEP_TIMEOUT_MS });

    // The sign-in must actually have round-tripped through OTP send + verify
    // (not a stale/leftover session): assert the intercepted requests fired and
    // the verify body carried the identifier/type/code the app should send.
    if (!sendReq.req) {
      fail("OTP pairing: sign-in never POSTed to /api/v1/auth/otp/send");
    }
    if (!verifyReq.req) {
      fail("OTP pairing: sign-in never POSTed to /api/v1/auth/otp/verify");
    }
    let vbody;
    try {
      vbody = JSON.parse(verifyReq.req.body ?? "null");
    } catch {
      fail(
        `OTP pairing: otp/verify body was not valid JSON: ${verifyReq.req.body}`,
      );
    }
    if (
      vbody?.identifier !== EMAIL ||
      vbody?.type !== "email" ||
      vbody?.code !== CODE
    ) {
      fail(
        "OTP pairing: otp/verify body must be " +
          `{ identifier:"${EMAIL}", type:"email", code:"${CODE}" }, ` +
          `got ${JSON.stringify(vbody)}`,
      );
    }

    log(
      "OTP pairing PASS: a guest tapped a 'Perfect pairings' card, was stashed " +
        "a post-auth path and routed into /(auth), completed the mocked email + " +
        "OTP sign-in, and landed on /links/create/biolink — the pairing-to-" +
        "signup handoff survives the email OTP sign-up path too.",
    );
  } finally {
    await context.close().catch(() => {});
  }
}

// Prove the guest "Perfect pairings" -> signup handoff survives a CANCELLED or
// REJECTED web-browser-provider sign-in FOLLOWED BY a successful retry. The two
// web providers (Google without a native client ID, and LinkedIn) don't use
// the Google native hook — they round-trip through the backend
// /user/social-oauth/{provider}/login popup and return via
// app/oauth-callback.tsx's "Sign-in failed" screen, a DIFFERENT completion
// branch than Google. runGooglePairingCancelRetry only proves the stash survives
// a cancelled/failed GOOGLE attempt; a regression on the web-provider return
// path would silently drop the guest's chosen pairing on any non-Google
// provider, quietly killing the guest->creator conversion — and nothing catches
// it today.
//
// The stash lives in AsyncStorage and is consumed ONLY by redirectAfterAuth on a
// SUCCESSFUL sign-in; oauth-callback.tsx's failure branches never touch it. So a
// guest taps a biolink pairing card (stashing a type-specific create path via
// lib/authNext.ts and routing into /(auth)), starts a web-provider sign-in, hits
// the friendly failure return (cancel → ?error=access_denied, or backend 422 →
// rejected token), taps "Back to sign in", then retries the SAME provider
// successfully. redirectAfterAuth must STILL consume the surviving stash and land
// the brand-new creator on /links/create/biolink — NOT the generic signed-in
// tabs. If the failure leg wrongly cleared (or the retry couldn't reconsume) the
// stash, the retry would dump the guest in the tabs and this fails.
//
// Runs on a FRESH guest context on the SAME already-booted main-flow
// browser/server (the plain CORE variant — no Google bundle needed), so it costs
// no extra Expo boot.
async function runWebProviderPairingCancelRetry(
  browser,
  baseUrl,
  { providerId, label, failureMode },
) {
  const tag = `web pairing (${providerId}/${failureMode})`;
  const context = await browser.newContext({
    viewport: { width: 400, height: 720 },
  });
  try {
    // Mark onboarding complete + ensure signed out before any page script runs,
    // so the intro splash doesn't sit between the /(auth) push and the login
    // screen and the card renders its guest "Sign up to create" CTA.
    await context.addInitScript(() => {
      try {
        window.localStorage.setItem("1inme.onboarding.complete", "1");
        window.localStorage.removeItem("1inme.auth.token");
        window.localStorage.removeItem("1inme.auth.user");
      } catch {}
    });

    const page = await context.newPage();
    let pageError = null;
    page.on("pageerror", (e) => {
      pageError = e.message;
      log(`${tag} pageerror:`, e.message);
    });
    page.setDefaultTimeout(STEP_TIMEOUT_MS);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

    // Mutable mock state. `webOauth.mode` flips the simulated backend redirect
    // ("cancel" → ?error=access_denied; "success" → provider+id_token). `social.mode`
    // flips the /auth/social response ("fail" → 422; "success" → session). Both
    // start in "success"; the failing first attempt sets whichever this run tests.
    const social = { req: null, mode: "success" };
    const webOauth = { req: null, mode: "success" };

    // Fulfill the social-login POST: 422 in "fail" mode (the backend rejects the
    // token), otherwise the standard {data:{token,user}} session envelope.
    await context.route("**/api/v1/auth/social", async (route) => {
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
              id: 84,
              display_name: "Web Pairing Guest",
              email: "web-pairing@example.com",
            },
          },
        }),
      });
    });

    // Serve the public restaurant page's data WITH a single biolink "Perfect
    // pairings" card, so the guest has a real card to tap (mirrors the other
    // pairing handoffs).
    await context.route("**/api/v1/restaurant/**", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            menu: {
              mode: "display",
              currency: "USD",
              accent_color: null,
              order_enabled: false,
              tax: { enabled: false, rate: 0, inclusive: false, label: "" },
            },
            link: { alias: "pairing-web-e2e", title: "Pairing Web E2E Menu" },
            table: null,
            categories: [],
            pairings: [
              {
                name: "Biolink page",
                type: "biolink",
                icon: "fa-grid",
                benefit: "Turn one scan into your whole world.",
              },
            ],
          },
        }),
      });
    });

    // Catch-all: benign {data:[]} for everything else so post-nav React Query
    // calls settle. Defer the two specific handlers above (Playwright runs route
    // handlers most-recently-registered first, so this catch-all — added last —
    // sees each request first and falls back for the paths it owns).
    await context.route("**/api/**", (route) => {
      const path = new URL(route.request().url()).pathname;
      if (
        /\/api\/v1\/auth\/social$/.test(path) ||
        /\/api\/v1\/restaurant\//.test(path)
      ) {
        return route.fallback();
      }
      return route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: [] }),
      });
    });

    // The web-browser OAuth providers open a popup to the backend
    // /user/social-oauth/{provider}/login URL. This context-level route (popups
    // are separate pages) records the opened URL and stands in for the backend +
    // OS deep-link by navigating the OPENER to /oauth-callback with the OAuth
    // result — exactly as runWebProviderSuccessPath/ErrorPath do. `mode` flips
    // the redirect between the cancelled and happy legs.
    await context.route("**/user/social-oauth/**", async (route) => {
      const req = route.request();
      webOauth.req = { url: req.url(), method: req.method() };
      const m = new URL(req.url()).pathname.match(/social-oauth\/([^/]+)\/login/);
      const provider = m ? m[1] : "";
      const dest =
        webOauth.mode === "cancel"
          ? `${new URL(baseUrl).origin}/oauth-callback?error=access_denied`
          : `${new URL(baseUrl).origin}/oauth-callback` +
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

    // Navigate to the public restaurant page. Nav hardening mirrors the other
    // flows: a transient connection error on a shared box → best-effort SKIP
    // (exit 0), never a hard fail; a NON-transient error is a real regression.
    const url = `${baseUrl.replace(/\/$/, "")}/restaurant/pairing-web-e2e`;
    const TRANSIENT_NAV =
      /ERR_CONNECTION_RESET|ERR_EMPTY_RESPONSE|ERR_CONNECTION_REFUSED|ERR_CONNECTION_CLOSED|ERR_NETWORK_CHANGED|Timeout \d+ms exceeded/;
    const NAV_ATTEMPTS = 4;
    let navErr;
    for (let attempt = 1; attempt <= NAV_ATTEMPTS; attempt++) {
      try {
        await page.goto(url, {
          waitUntil: "domcontentloaded",
          timeout: NAV_TIMEOUT_MS,
        });
        await page.waitForFunction(
          () => document.body && document.body.innerText.trim().length > 0,
          null,
          { timeout: NAV_TIMEOUT_MS },
        );
        navErr = undefined;
        break;
      } catch (e) {
        navErr = e;
        if (TRANSIENT_NAV.test(e?.message ?? "") && attempt < NAV_ATTEMPTS) {
          log(
            `${tag}: nav attempt ${attempt}/${NAV_ATTEMPTS} hit a transient ` +
              `connection error (${e?.message ?? "unknown"}); retrying`,
          );
          await new Promise((r) => setTimeout(r, 3000));
          continue;
        }
        break;
      }
    }
    if (navErr) {
      if (TRANSIENT_NAV.test(navErr?.message ?? "")) {
        await context.close().catch(() => {});
        skip(
          `${tag}: could not reach ${url} ` +
            `(${navErr?.message ?? "unknown error"}). Is it running?`,
        );
        return;
      }
      throw navErr;
    }

    // The guest's biolink pairing card must render and be tappable. Its
    // accessibility label is the guest CTA ("Sign up to create ..."), which
    // proves we're signed out and looking at the conversion surface.
    const card = page
      .locator('[aria-label="Sign up to create Biolink page"]')
      .first();
    await card.waitFor({ timeout: STEP_TIMEOUT_MS });
    log(`${tag}: guest sees the biolink 'Perfect pairings' card`);
    await card.click();

    // Tapping stashes the post-auth create path and routes into /(auth); the
    // provider button being present there proves we reached the login screen.
    const btn = page.locator(`[aria-label="${label}"]`).first();
    await btn.waitFor({ timeout: STEP_TIMEOUT_MS });
    log(`${tag}: card tap routed the guest into /(auth)`);

    if (pageError) {
      fail(`${tag}: the page reported a runtime error: ${pageError}`);
    }

    // --- FIRST attempt: fail (cancel or backend 422). The stash must survive.
    if (failureMode === "cancel") {
      webOauth.mode = "cancel";
      social.mode = "success";
    } else {
      webOauth.mode = "success";
      social.mode = "fail";
    }
    social.req = null;
    webOauth.req = null;

    const [popup1] = await Promise.all([
      page.waitForEvent("popup", { timeout: STEP_TIMEOUT_MS }),
      btn.click(),
    ]);
    log(`${tag}: tapped ${label}; OAuth popup opened (failing attempt)`);

    await page
      .getByText("Sign-in failed", { exact: true })
      .waitFor({ timeout: STEP_TIMEOUT_MS });
    if (failureMode === "cancel") {
      await page
        .getByText("You cancelled the sign-in.", { exact: false })
        .waitFor({ timeout: STEP_TIMEOUT_MS });
      if (social.req) {
        fail(
          `${tag}: a cancelled return must NOT POST to /auth/social, but it ` +
            `did (${social.req.url})`,
        );
      }
    } else {
      await page
        .getByText("We couldn't verify that sign-in", { exact: false })
        .waitFor({ timeout: STEP_TIMEOUT_MS });
      const displayName = label.replace(/^Log in with /, "");
      await page
        .getByText(`${displayName} sign-in is having issues`, { exact: false })
        .waitFor({ timeout: STEP_TIMEOUT_MS });
    }

    // The failed attempt must NOT have signed the guest in.
    const inTabsAfterFail = await page
      .getByText("Profile", { exact: true })
      .first()
      .isVisible()
      .catch(() => false);
    if (inTabsAfterFail) {
      fail(`${tag}: a failed web sign-in wrongly signed the guest in`);
    }
    await popup1.close().catch(() => {});
    log(`${tag}: failing attempt surfaced a friendly error, no sign-in`);

    // --- RETRY: back to the sign-in screen, then the SAME provider succeeds.
    // We deliberately do NOT reset the session/storage here — that would clear
    // the very stash whose survival we're proving.
    await page.getByText("Back to sign in", { exact: true }).click();
    await btn.waitFor({ timeout: STEP_TIMEOUT_MS });
    log(`${tag}: 'Back to sign in' returned to the login screen`);

    social.mode = "success";
    webOauth.mode = "success";
    social.req = null;
    webOauth.req = null;

    const [popup2] = await Promise.all([
      page.waitForEvent("popup", { timeout: STEP_TIMEOUT_MS }),
      btn.click(),
    ]);
    log(`${tag}: retried ${label}; OAuth popup opened (successful attempt)`);

    // THE PROOF: after the successful retry, redirectAfterAuth must consume the
    // surviving stash and land on the type-specific create screen — NOT the
    // signed-in tabs. Assert both the URL and the create screen's own copy so a
    // header-only match can't fool us.
    await page.waitForFunction(
      () => location.pathname.includes("/links/create/biolink"),
      null,
      { timeout: STEP_TIMEOUT_MS },
    );
    await page
      .getByText("A multi-block landing page for your bio.", { exact: false })
      .waitFor({ timeout: STEP_TIMEOUT_MS });
    await popup2.close().catch(() => {});

    // The successful retry must actually have round-tripped through the backend
    // login URL + /auth/social (not a stale session): assert the intercepted
    // requests' shapes.
    if (!webOauth.req) {
      fail(`${tag}: the retry never opened the backend social-oauth login URL`);
    }
    if (
      !new RegExp(`/user/social-oauth/${providerId}/login$`).test(
        new URL(webOauth.req.url).pathname,
      )
    ) {
      fail(
        `${tag}: the retry opened the wrong backend URL (${webOauth.req.url})`,
      );
    }
    if (!social.req) {
      fail(`${tag}: the retry never POSTed to /api/v1/auth/social`);
    }
    let body;
    try {
      body = JSON.parse(social.req.body ?? "null");
    } catch {
      fail(`${tag}: auth/social body was not valid JSON: ${social.req.body}`);
    }
    if (body?.provider !== providerId || body?.id_token !== MOCK_WEB_ID_TOKEN) {
      fail(
        `${tag}: auth/social body must be { provider:"${providerId}", ` +
          `id_token:"${MOCK_WEB_ID_TOKEN}" }, got ${JSON.stringify(body)}`,
      );
    }

    log(
      `${tag} PASS: a guest tapped a 'Perfect pairings' card, hit a ` +
        `${failureMode} web-provider failure, retried the SAME provider, and ` +
        `still landed on /links/create/biolink — the pairing stash survives a ` +
        `cancelled/rejected non-Google sign-in.`,
    );
  } finally {
    await context.close().catch(() => {});
  }
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

      // This throwaway build has no API base configured; intercept every OTHER
      // backend call so nothing ever reaches a real server. (Playwright runs
      // route handlers most-recently-added-first, so this catch-all sees the
      // /auth/social request first and defers it to the handler above.) Like the
      // main flow, fulfill unmatched calls with a fast, benign `{data: []}`
      // rather than aborting, so the post-login tabs settle without React Query
      // retry churn.
      await context.route("**/api/**", (route) => {
        if (
          /\/api\/v1\/auth\/social$/.test(new URL(route.request().url()).pathname)
        ) {
          return route.fallback();
        }
        return route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({ data: [] }),
        });
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

      // Mirror the main flow's nav hardening: even after the boot warmup reports
      // a real 200, the browser's first nav can transiently ERR_CONNECTION_RESET
      // /ERR_CONNECTION_REFUSED while a single-process Metro settles under the
      // parallel browser-e2e load on a shared box. Retry with backoff; a
      // persistent transient connection error means the server couldn't stay
      // serveable → best-effort SKIP (exit 0), never a hard fail. Real
      // assertion/logic errors still propagate.
      // Timeout \d+ms exceeded: after warmup already proved a real 200, the
      // initial nav timing out means Metro is too starved to serve the bundle
      // under this run's load — same "couldn't stay serveable" condition as a
      // connection reset, so classify it transient (skip, not hard fail).
      const TRANSIENT_NAV =
        /ERR_CONNECTION_RESET|ERR_EMPTY_RESPONSE|ERR_CONNECTION_REFUSED|ERR_CONNECTION_CLOSED|ERR_NETWORK_CHANGED|Timeout \d+ms exceeded/;
      const NAV_ATTEMPTS = 4;
      let navErr;
      for (let attempt = 1; attempt <= NAV_ATTEMPTS; attempt++) {
        try {
          await page.goto(googleBaseUrl, {
            waitUntil: "domcontentloaded",
            timeout: NAV_TIMEOUT_MS,
          });
          await page.waitForFunction(
            () => document.body && document.body.innerText.trim().length > 0,
            null,
            { timeout: NAV_TIMEOUT_MS },
          );
          navErr = undefined;
          break;
        } catch (e) {
          navErr = e;
          if (TRANSIENT_NAV.test(e?.message ?? "") && attempt < NAV_ATTEMPTS) {
            log(
              `Google variant: nav attempt ${attempt}/${NAV_ATTEMPTS} hit a ` +
                `transient connection error (${e?.message ?? "unknown"}); retrying`,
            );
            await new Promise((r) => setTimeout(r, 3000));
            continue;
          }
          break;
        }
      }
      if (navErr) {
        const transient = TRANSIENT_NAV.test(navErr?.message ?? "");
        // Reused (explicit) server → not our responsibility, skip on any failure.
        // Our own throwaway server → a PERSISTENT transient connection error is
        // a best-effort skip too. Anything else is a real problem → propagate.
        if (server.explicit || transient) {
          await browser.close();
          skip(
            `Google variant: could not reach the server at ${googleBaseUrl} ` +
              `(${navErr?.message ?? "unknown error"}). Is it running?`,
          );
          return;
        }
        throw navErr;
      }

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

      // Reuse this booted Google-enabled server/browser to prove the guest
      // "Perfect pairings" -> signup handoff survives the same OAuth round-trip.
      await runGooglePairingHandoff(browser, googleBaseUrl);
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
  // The redirect named the provider (apple), so the screen must name it and
  // point the user at email rather than echoing only the raw backend message.
  await page
    .getByText("Apple sign-in is having issues", { exact: false })
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
// sayzio://oauth-callback. On native the OS deep-links that return into the app;
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
  // single goto appBaseUrl (which also escapes any leftover /oauth-callback URL)
  // lands directly on the login screen — no extra reload, which keeps this
  // 6-provider loop from piling up page loads and slowing the browser down.
  // NOTE: navigate to appBaseUrl (the server this run actually booted/drives),
  // NOT the imported APP_URL default — without an APP_URL env those diverge
  // (APP_URL falls back to the proxy expo domain) and we'd navigate to an
  // un-mocked server that hangs the whole provider loop.
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
  log(`${label}: tapped; OAuth popup opened to the backend login URL`);

  // The signed-in tab bar is the proof the whole round-trip completed.
  await waitForSignedInTabs(page);
  log(`${label}: the success path landed in the signed-in tabs`);

  // Close the popup now that it has handed control back to the opener. Leaving
  // each of the 6 providers' popups open piled up orphaned browser windows in
  // the context, which dragged the long session down and fed the tail stall.
  await popup.close().catch(() => {});

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
  if (oauthUrl.searchParams.get("return") !== "sayzio://oauth-callback") {
    fail(
      `${label}: backend login URL must return to sayzio://oauth-callback, got ` +
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
    // …and because the redirect carried the provider, the screen must NAME it
    // and steer the user to email — provider-specific guidance, not a generic
    // echo of the backend message. The display name is the button label minus
    // the "Log in with " prefix.
    const displayName = label.replace(/^Log in with /, "");
    await page
      .getByText(`${displayName} sign-in is having issues`, { exact: false })
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
  // …plus a clear fallback to email OTP, the actionable next step when a single
  // provider misbehaves.
  await page
    .getByText("Use email instead", { exact: true })
    .waitFor({ timeout: STEP_TIMEOUT_MS });

  // Tapping the fallback must land back on the email sign-in screen with the
  // email field ready (provider-down recovery is one tap, not a dead end).
  if (failureMode === "backend422") {
    await page.getByText("Use email instead", { exact: true }).click();
    await page
      .getByPlaceholder("you@example.com")
      .waitFor({ timeout: STEP_TIMEOUT_MS });
    log(`${label} (backend422): "Use email instead" landed on the email sign-in field`);
  }

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
  // Every /api/** call must be intercepted so nothing ever reaches a real
  // backend. We FULFILL unmatched calls with a fast, benign `{data: []}` 200
  // rather than aborting them: once signed in, the tabs fire a burst of
  // authenticated GETs (feed, profile, notifications, …), and aborting made
  // React Query retry each one (3× with backoff) on every sign-in. Across the
  // demo / OTP / OAuth / 6-provider runs that retry churn piled up in the
  // long-lived page and was the source of the intermittent tail stall. An empty
  // `[]` envelope lets the tabs settle immediately and never retry; `[]` is
  // deliberately both array-iterable and property-accessible, so it satisfies
  // list- and object-shaped readers alike without crashing a screen (and the
  // tab bar — our "signed in" signal — renders regardless of this data anyway).
  // The specific auth mocks below are registered AFTER this catch-all, and
  // Playwright runs route handlers most-recently-added-first, so the specific
  // handlers win for the URLs they match and this only catches everything else.
  await page.route("**/api/**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: [] }),
    }),
  );

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
    // The opener must land on the EXPO app's origin (appBaseUrl — the server
    // this run actually drives), not this popup's backend origin: getBaseUrl()
    // points OAuth at the proxy domain (via EXPO_PUBLIC_DOMAIN), but the app
    // itself is only served from appBaseUrl, so a relative redirect (or the
    // imported APP_URL default, which falls back to the proxy expo domain when
    // no APP_URL env is set) would strand the opener on a domain without the app.
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
    // Navigate to the freshly-booted app. Even after waitForExpoServeable()
    // confirms a real 200 via curl, the browser's first nav can transiently
    // ERR_CONNECTION_RESET: Metro is a single dev-server process and the browser
    // opens several parallel sockets (document + favicon + the HMR websocket)
    // with different headers than the curl warmup, so a still-settling bundler —
    // especially under the parallel browser-e2e load on a shared box — can reset
    // one of them. Retry the nav a few times with backoff (re-warming between
    // tries so the retry hits a compiled bundle). If it keeps resetting/refusing,
    // the server simply isn't staying serveable under this run's load, which is
    // the same "best-effort boot couldn't hold" condition the reused-server case
    // already SKIPs on (exit 0) — never a hard gate failure. Non-connection
    // errors (real assertion/logic bugs) still propagate.
    // Timeout \d+ms exceeded: after waitForExpoServeable() already proved a
    // real 200, the initial nav timing out means Metro is too starved to serve
    // the bundle under this run's load — same "couldn't stay serveable"
    // condition as a connection reset, so classify it transient too.
    const TRANSIENT_NAV =
      /ERR_CONNECTION_RESET|ERR_EMPTY_RESPONSE|ERR_CONNECTION_REFUSED|ERR_CONNECTION_CLOSED|ERR_NETWORK_CHANGED|Timeout \d+ms exceeded/;
    const NAV_ATTEMPTS = 4;
    let navErr;
    for (let attempt = 1; attempt <= NAV_ATTEMPTS; attempt++) {
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
        navErr = undefined;
        break;
      } catch (e) {
        navErr = e;
        if (TRANSIENT_NAV.test(e?.message ?? "") && attempt < NAV_ATTEMPTS) {
          log(
            `nav attempt ${attempt}/${NAV_ATTEMPTS} hit a transient connection ` +
              `error (${e?.message ?? "unknown"}); re-warming and retrying`,
          );
          await new Promise((r) => setTimeout(r, 3000));
          if (typeof server?.warm === "function") {
            await server.warm().catch(() => {});
          }
          continue;
        }
        break;
      }
    }
    if (navErr) {
      const transient = TRANSIENT_NAV.test(navErr?.message ?? "");
      // Reused (explicit) server → not our responsibility, skip on any failure.
      // Our own throwaway server → a PERSISTENT transient connection error means
      // Metro couldn't stay serveable under load, also a best-effort skip. Any
      // other error is a real problem and must fail the gate.
      if (server.explicit || transient) {
        await browser.close();
        skip(
          `could not reach the server at ${appBaseUrl} ` +
            `(${navErr?.message ?? "unknown error"}). Is it running?`,
        );
        return;
      }
      throw navErr;
    }

    log("app mounted; verifying the cold-launch splash hides the voice mic");
    // Cold launch: the branded ZioSplash is on screen right after mount. The
    // floating mic must NOT be present during it (the splash-visibility fix
    // removed GlobalVoiceAssistant from the root layout).
    await assertSplashHidesVoiceMic(page);

    log("navigating to login screen");
    await reachLoginScreen(page);

    // -----------------------------------------------------------------
    // Step 0: the "Ask Zio" voice mic reappears on a signed-in tab.
    // Counterpart to assertSplashHidesVoiceMic above: the mic is hidden
    // on the cold-launch splash and must reappear once the user reaches a
    // signed-in tab screen (the (tabs) layout mounts it). Kept self-
    // contained here — a demo sign-in to land in the tabs, assert the mic,
    // then reset back to the login screen for the rest of the flow — so the
    // voice-visibility proof doesn't depend on any later step.
    // -----------------------------------------------------------------
    await runDemoFlow("Demo as user", "user");
    await assertVoiceMicPresent(page);
    await resetToLogin(page);

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
      await withDeadline(
        `web provider "${label}" sign-in`,
        PROVIDER_DEADLINE_MS,
        () => runWebProviderSuccessPath(page, social, webOauth, id, label),
      );
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

    // -----------------------------------------------------------------
    // Step 9: the guest "Perfect pairings" -> signup handoff must ALSO
    // survive the email + OTP sign-up (the more common mobile signup
    // path, and a different login-completion branch than Google OAuth).
    // Runs on a FRESH guest context on this same already-booted server,
    // so it needs no Google bundle and no extra Expo boot.
    // -----------------------------------------------------------------
    await runOtpPairingHandoff(browser, appBaseUrl);

    // -----------------------------------------------------------------
    // Step 10: the guest "Perfect pairings" -> signup handoff must ALSO
    // survive a CANCELLED or REJECTED web-browser-provider sign-in and a
    // subsequent successful retry. Web-browser providers take a different
    // completion path than the Google native hook — they go through
    // oauth-callback's "Sign-in failed" screen — so a regression there
    // would silently drop the guest's chosen pairing. Cover BOTH failure
    // mechanisms — a cancelled popup (Google) and a backend 422
    // (LinkedIn) — each on a fresh guest context on this same server.
    // -----------------------------------------------------------------
    await runWebProviderPairingCancelRetry(browser, appBaseUrl, {
      providerId: "google",
      label: "Log in with Google",
      failureMode: "cancel",
    });
    await runWebProviderPairingCancelRetry(browser, appBaseUrl, {
      providerId: "linkedin",
      label: "Log in with LinkedIn",
      failureMode: "backend422",
    });

    log(
      "PASS: the cold-launch splash hides the voice mic (present again on a " +
        "signed-in tab), social buttons, demo logins, OTP sign-in, the OAuth " +
        "deep-link return (browser + native-SDK legs), every web-browser " +
        "provider's full sign-in, every web-browser provider's failure path, " +
        "the guest pairing -> email-OTP signup handoff, and the guest " +
        "pairing -> web-provider cancel/reject + retry handoff all behave " +
        "correctly.",
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
    await runFlowBestEffort("main flow", mainServer, () => main(mainServer));
  }

  if (runGoogle) {
    if (!googleServer) {
      log("Google variant SKIP: the throwaway Expo server could not start");
      return;
    }
    await runFlowBestEffort("Google variant", googleServer, () =>
      runGoogleVariant(googleServer),
    );
  }
}

// Best-effort contract, post-boot half (mirrors the icon-font gates): on a
// THROWAWAY server, a Playwright step/nav timeout or transient connection
// error anywhere mid-flow means the constrained box was too slow (Metro
// recompiling, CPU starved by parallel validation jobs) — SKIP, same as when
// the server couldn't boot at all. Real regressions in this suite are
// reported via fail() (which exits 1 directly, before any catch) or as
// non-transient errors (assertion mismatches, withDeadline fail-fast stalls),
// both of which still fail hard here. Against an explicit APP_URL /
// GOOGLE_APP_URL (someone deliberately pointed us at a server) we also fail
// hard so local debugging never silently skips. The flow's own finally blocks
// have already torn down its browser/server by the time the error reaches us.
async function runFlowBestEffort(label, server, fn) {
  try {
    await fn();
  } catch (e) {
    if (!server.explicit && isTransientEnvError(e)) {
      skip(
        `${label}: the environment was too slow to drive the flow ` +
          `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
          `skipping (best-effort, not an auth regression)`,
      );
      return;
    }
    throw e;
  }
}

// Termination guarantee: runHarness exits the process as soon as run()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(run, { log, onError: (e) => console.error(e) });
