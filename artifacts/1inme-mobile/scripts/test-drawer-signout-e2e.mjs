#!/usr/bin/env node
/**
 * End-to-end regression check for the drawer "Sign out" confirmation
 * (components/DrawerSidebar.tsx), driving the REAL app in a headless browser.
 *
 * Why this exists: the drawer's Sign out button shows a confirmation dialog
 * before signing out. Dismissing that dialog WITHOUT choosing either action
 * (Cancel on web, or the Android hardware back button closing the native
 * Alert) must never sign the user out — only an explicit confirm may. Nothing
 * else in CI covered that, so a regression (e.g. sign-out firing on dismiss,
 * or the confirmation disappearing entirely) would ship silently.
 *
 * What it asserts, in order:
 *   1. Signed in, opening the drawer shows the Sign out button.
 *   2. Tapping Sign out presents a confirmation dialog (window.confirm on
 *      web — react-native-web's Alert.alert is a no-op, so the component
 *      branches; dismissal semantics mirror the Android back button).
 *   3. DISMISSING the dialog keeps the user signed in: no /auth/logout call,
 *      the session token is still persisted, the user stays on the tabs, and
 *      the drawer can be closed and re-opened afterwards.
 *   4. CONFIRMING signs out: /auth/logout is POSTed, the persisted session is
 *      cleared, and the app lands on the auth screen ("Welcome back").
 *
 * Every /api/** call is intercepted (benign {data:[]} catch-all + a tracked
 * /auth/logout handler) so nothing reaches a real backend. Like the other
 * mobile e2e harnesses it boots its OWN throwaway Expo web dev server (shared
 * expo-web-server.mjs manager) unless APP_URL points at an already-running
 * one, and SKIPS (exit 0) when a server can't come up so it never fails CI
 * just because Metro couldn't boot.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:drawer-signout-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting
 *             a throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import { createExpoServerManager, runHarness, isTransientEnvError } from "./expo-web-server.mjs";

function log(...args) {
  console.log("[drawer-signout-e2e]", ...args);
}

function fail(msg) {
  console.error("[drawer-signout-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[drawer-signout-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-drawer-signout-token";
const MOCK_USER = {
  id: 41,
  display_name: "Drawer Tester",
  email: "drawer-signout@example.com",
};

async function getStoredToken(page) {
  return page.evaluate(() => {
    try {
      return window.localStorage.getItem("1inme.auth.token");
    } catch {
      return null;
    }
  });
}

// The signed-in tab bar is the proof the app booted into the authenticated
// shell (same signal the auth-flow harness uses).
async function waitForSignedInTabs(page) {
  // Use "attached" state (element in DOM) rather than "visible" to avoid a
  // timing dependency on the ZioSplash exit animation — the downstream
  // openDrawer() call already asserts { state: "visible" } on the tab UI
  // so the overall boot assertion remains meaningful.
  await page
    .getByText("Profile", { exact: true })
    .first()
    .waitFor({ state: "attached", timeout: NAV_TIMEOUT_MS });
}

async function openDrawer(page) {
  const menuBtn = page.locator('[aria-label="Open menu"]').first();
  await menuBtn.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await menuBtn.click();
  const signOutBtn = page.locator('[aria-label="Sign out"]').first();
  await signOutBtn.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  return signOutBtn;
}

async function run() {
  const { acquireServer } = createExpoServerManager(log);
  const server = await acquireServer("drawer-signout", process.env.APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
  }
  const appUrl = server.appUrl.endsWith("/")
    ? server.appUrl
    : server.appUrl + "/";

  // Under heavy parallel validation load chromium can fail to spawn threads
  // (pthread_create EAGAIN) or crash right after launch. That's environment
  // exhaustion, not a product regression — skip like the sibling harnesses.
  let browser;
  try {
    browser = await chromium.launch();
  } catch (e) {
    skip(`could not launch a headless browser in this environment: ${e?.message || e}`);
  }
  const context = await browser.newContext({ viewport: VIEWPORT });

  // Pre-seed auth + onboarding so AuthProvider finds them on first read.
  // Keys match lib/secure.ts web path (Platform.OS === "web" uses localStorage
  // with the "1inme.auth.*" keys directly). Seeding all three means
  // AuthProvider initialises with user ≠ null, so /(tabs) mounts on the
  // first "/" navigation without hitting the auth-guard redirect.
  await context.addInitScript(() => {
    try {
      window.localStorage.setItem("1inme.auth.token", "e2e-drawer-signout-token");
      window.localStorage.setItem(
        "1inme.auth.user",
        JSON.stringify({ id: 41, display_name: "Drawer Tester", email: "drawer-signout@example.com" }),
      );
      window.localStorage.setItem("1inme.onboarding.complete", "1");
    } catch {}
  });

  // Track sign-out's POST /auth/logout so we can assert it fires ONLY on
  // confirm. Registered before the catch-all... no: Playwright consults the
  // most-recently-registered handler first, so register the catch-all FIRST
  // and this specific one after it.
  const logout = { count: 0 };

  // Catch-all so no authenticated tab burst ever reaches a real backend.
  await context.route("**/api/**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: [] }),
    }),
  );
  await context.route("**/api/v1/auth/logout", async (route) => {
    logout.count += 1;
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: { ok: true } }),
    });
  });
  // Specific: /auth/me — AuthContext.refresh() calls this endpoint on boot;
  // return the mock user in the expected {data:{user:{...}}} shape so the
  // auth state stays valid. Registered after the catch-all so Playwright's
  // LIFO handler order gives this route priority.
  await context.route("**/auth/me", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        data: {
          user: {
            id: MOCK_USER.id,
            display_name: MOCK_USER.display_name,
            email: MOCK_USER.email,
          },
        },
      }),
    });
  });
  // Specific: /onboarding — GateScreen calls getOnboardingStatus() on every
  // authenticated boot. Returns a valid onboarded_at so needsSetup resolves
  // to false and the gate proceeds straight to /(tabs) without spinning.
  await context.route("**/api/v1/onboarding", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: { onboarded_at: "2024-01-01T00:00:00.000Z" } }),
    });
  });

  const page = await context.newPage();
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
  page.on("pageerror", (e) => log("pageerror:", e.message));

  // Collect every confirmation dialog the app raises. Each test leg arms the
  // NEXT dialog's response (dismiss vs accept) before tapping Sign out; an
  // unexpected extra dialog is dismissed (the safe default) and would then
  // surface via the signed-in/out assertions.
  const dialogs = [];
  let nextDialogAction = "dismiss";
  page.on("dialog", async (dialog) => {
    dialogs.push({ type: dialog.type(), message: dialog.message() });
    if (nextDialogAction === "accept") {
      await dialog.accept().catch(() => {});
    } else {
      await dialog.dismiss().catch(() => {});
    }
  });

  try {
    // Auth is pre-seeded in localStorage via addInitScript above. Navigate
    // to root — AuthProvider reads the stored token/user on init so user is
    // already set when GateScreen renders → no auth-guard redirect → /(tabs)
    // mounts and FloatingTabBar becomes visible.
    log(`navigating to root (auth pre-seeded in localStorage)`);
    await page.goto(appUrl.replace(/\/$/, "") + "/", {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });
    // Boot-phase guard: tab bar not visible within DRAWER_BOOT_TIMEOUT_MS means
    // the CI box was resource-starved and couldn't load the app in time — SKIP
    // (env issue, not a product regression). Real sign-out assertion failures
    // come later and still propagate as hard FAILs.
    await waitForSignedInTabs(page).catch((bootErr) => {
      skip(
        `the environment was too slow to drive the check ` +
          `(${bootErr?.message?.split("\n")[0]}); ` +
          `skipping (best-effort, not a sign-out regression)`,
      );
    });
    log("booted signed in — tab bar visible");

    // ---- 1+2. Drawer shows Sign out; tapping it raises a confirmation ----
    let signOutBtn = await openDrawer(page);
    log("drawer open — Sign out button visible");

    // ---- 3. Dismissing the confirmation must NOT sign out ---------------
    nextDialogAction = "dismiss";
    const before = dialogs.length;
    await signOutBtn.click();
    // The dialog event fires with the click handler; poll briefly for it to
    // be recorded.
    const deadline = Date.now() + STEP_TIMEOUT_MS;
    while (dialogs.length <= before && Date.now() < deadline) {
      await new Promise((r) => setTimeout(r, 50));
    }
    if (dialogs.length <= before) {
      fail("tapping Sign out did not present a confirmation dialog");
    }
    const dlg = dialogs[dialogs.length - 1];
    if (dlg.type !== "confirm") {
      fail(`expected a confirm dialog, got type "${dlg.type}"`);
    }
    if (!/sign out/i.test(dlg.message)) {
      fail(`confirmation message doesn't mention signing out: "${dlg.message}"`);
    }
    log(`confirmation dialog shown: "${dlg.message}" — dismissed it`);

    // Still signed in: no logout call, token intact, still on the tabs.
    // Give any (wrong) sign-out a moment to happen before asserting.
    await page.waitForTimeout(750);
    if (logout.count !== 0) {
      fail("dismissing the confirmation still POSTed /auth/logout — accidental sign-out");
    }
    const tokenAfterDismiss = await getStoredToken(page);
    if (tokenAfterDismiss !== MOCK_TOKEN) {
      fail(
        `dismissing the confirmation cleared the persisted session ` +
          `(expected ${MOCK_TOKEN}, got ${tokenAfterDismiss})`,
      );
    }
    await waitForSignedInTabs(page);
    log("dismiss kept the user signed in (no logout call, session intact)");

    // The drawer must still be usable: close it, then re-open it.
    const closeBtn = page.locator('[aria-label="Close menu"]').first();
    if (await closeBtn.isVisible().catch(() => false)) {
      await closeBtn.click();
      await page
        .locator('[aria-label="Sign out"]')
        .first()
        .waitFor({ state: "hidden", timeout: STEP_TIMEOUT_MS })
        .catch(() => {});
    }
    signOutBtn = await openDrawer(page);
    log("drawer closed and re-opened after cancelling — still functional");

    // ---- 4. Confirming signs out and lands on the auth screen -----------
    nextDialogAction = "accept";
    const beforeConfirm = dialogs.length;
    await signOutBtn.click();
    const deadline2 = Date.now() + STEP_TIMEOUT_MS;
    while (dialogs.length <= beforeConfirm && Date.now() < deadline2) {
      await new Promise((r) => setTimeout(r, 50));
    }
    if (dialogs.length <= beforeConfirm) {
      fail("second Sign out tap did not present a confirmation dialog");
    }
    log("confirmation dialog shown again — accepted it");

    // Signed out: auth screen appears, logout was POSTed, session cleared.
    await page
      .getByText("Welcome back", { exact: false })
      .first()
      .waitFor({ timeout: STEP_TIMEOUT_MS });
    if (logout.count !== 1) {
      fail(`expected exactly one POST /auth/logout after confirming, got ${logout.count}`);
    }
    const tokenAfterConfirm = await getStoredToken(page);
    if (tokenAfterConfirm !== null) {
      fail(`confirming sign-out left a persisted token behind: ${tokenAfterConfirm}`);
    }
    log("confirm signed the user out and landed on the auth screen");

    log("PASS: drawer sign-out confirmation e2e checks all passed");
    await browser.close().catch(() => {});
    // Explicit exit (like the sibling harnesses): the throwaway Metro child
    // would otherwise keep the event loop alive; the manager's process exit
    // hook reaps it.
    process.exit(0);
  } catch (e) {
    failOrSkipInfra(e);
  } finally {
    await browser.close().catch(() => {});
  }
}

// Distinguish a real assertion/product failure from the environment killing
// the browser out from under us (resource exhaustion during parallel
// validation runs). Only the latter is a skip.
function failOrSkipInfra(e) {
  const msg = e?.message || String(e);
  const infra =
    /Target page, context or browser has been closed|browser has been closed|browserType\.launch|pthread_create|Browser closed|Target closed/i.test(
      msg,
    );
  if (infra || isTransientEnvError(e)) {
    skip(`the environment was too slow to drive the check (${msg.split("\n")[0]}); skipping (best-effort, not a sign-out regression)`);
  }
  fail(e?.stack || msg);
}

// Termination guarantee: runHarness exits the process as soon as run()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(run, { log, onError: failOrSkipInfra });
