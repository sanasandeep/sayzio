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
 * Strategy (reuses the reachLoginScreen harness from check-icon-fonts.mjs):
 *   1. Launch headless Chromium against the running Expo web build.
 *   2. Walk onboarding to the "Welcome back" login screen.
 *   3. Assert every visible social-provider button is tappable (rendered,
 *      visible, not disabled) — a hidden/untappable button would otherwise
 *      ship unnoticed. We don't click them: that opens a real OAuth
 *      round-trip in an external browser.
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
 *
 * The test no-ops gracefully (skips, exit 0) when the Expo dev server isn't
 * running, matching the check-icon-fonts pattern — so it never fails CI just
 * because the preview happens to be down.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:auth-flow-e2e
 *
 * Environment:
 *   APP_URL   override the URL under test (defaults to the Expo dev domain
 *             on Replit, or http://localhost:8081 locally).
 */

import { chromium } from "playwright";

import {
  APP_URL,
  NAV_TIMEOUT_MS,
  STEP_TIMEOUT_MS,
  reachLoginScreen,
} from "./check-icon-fonts.mjs";

const EMAIL = "creator@example.com";
const CODE = "123456";
const MOCK_TOKEN = "sanctum-token-e2e";

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

async function main() {
  log("launching chromium against", APP_URL);
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 400, height: 720 },
  });
  const page = await context.newPage();
  page.on("pageerror", (e) => log("pageerror:", e.message));
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

  // ---- Intercept the auth endpoints so we never touch a real backend.
  // We record the requests so we can assert their URL / method / body, and
  // fulfill them with the exact `{data:{...}}` envelopes the app expects.
  let sendReq = null;
  let verifyReq = null;
  // Mutable holder for the most recent demo request, reset before each run.
  const demo = { req: null };

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
      await page.goto(APP_URL, {
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
      // Connection refused / nothing served = the dev server is down. Skip
      // gracefully rather than failing CI when the preview isn't running.
      await browser.close();
      skip(
        `could not reach the Expo dev server at ${APP_URL} ` +
          `(${e?.message ?? "unknown error"}). Is it running?`,
      );
      return;
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

    log("PASS: social buttons, demo logins and OTP sign-in all click through.");
  } finally {
    await browser.close();
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
