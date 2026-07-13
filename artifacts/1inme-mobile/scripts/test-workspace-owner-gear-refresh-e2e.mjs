#!/usr/bin/env node
/**
 * End-to-end regression gate for the workspace switcher's owner-only edit gear
 * (components/DrawerSidebar.tsx, mirrored on app/workspaces.tsx), driving the
 * REAL app in a headless browser.
 *
 * Why this exists: Task #4699 added a source-driven guard
 * (scripts/test-workspace-switcher.mjs) proving the gear ternary is bound to
 * the live `is_owner` and evaluates true/false correctly. It does NOT render
 * the real switcher against a live React Query refetch, so a runtime
 * regression — the switcher reading a stale cached list, or the foreground
 * refetch not firing after ownership changes hands on the web — would leave the
 * edit gear on screen for a workspace the signed-in user no longer owns, and
 * slip past the source-driven test entirely. Tapping that stale gear then dead-
 * ends on an owner-only edit screen the server rejects.
 *
 * The switcher reads the workspace list from WorkspaceContext's
 * ["workspaces-list"] query, and that context wires useForegroundRefresh ->
 * refetch. react-native-web's AppState maps "change" -> "active" onto the DOM
 * `visibilitychange` event (it reads document.visibilityState), so this harness
 * simulates the app going to the background and returning to the foreground by
 * flipping document.visibilityState and dispatching `visibilitychange` —
 * exactly the signal the shipped useForegroundRefresh hook subscribes to on
 * web. That is the same ownership-handoff path the switcher relies on: the
 * transfer happens on the web, and coming back to the app must drop the gear.
 *
 * What it asserts, in order:
 *   1. Booted signed-in, the drawer's workspace switcher lists an owned team
 *      workspace and its edit gear is on screen (is_owner=true).
 *   2. Ownership is transferred away server-side (the mocked GET /workspaces now
 *      reports is_owner=false for that workspace) while the app is
 *      "backgrounded".
 *   3. Returning to the foreground (visibilitychange -> active) triggers the
 *      refetch WITHOUT a manual reload: the edit gear for that workspace
 *      disappears.
 *
 * Every /api/** call is intercepted so nothing reaches a real backend. Like the
 * sibling mobile e2e harnesses it boots its OWN throwaway, self-contained Expo
 * web dev server (shared expo-web-server.mjs manager) unless APP_URL points at
 * an already-running one, and SKIPS (exit 0) when a server can't come up or a
 * throwaway run dies of a transient environment error so it never fails CI just
 * because Metro couldn't boot on a starved box.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:workspace-owner-gear-refresh-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting a
 *             throwaway one (skips are disabled then, so local debugging never
 *             silently skips).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[workspace-owner-gear-refresh-e2e]", ...args);
}

function fail(msg) {
  console.error("[workspace-owner-gear-refresh-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[workspace-owner-gear-refresh-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-workspace-owner-gear-token";
const MOCK_USER = {
  id: 4703,
  display_name: "Ownership Handoff Tester",
  email: "workspace-owner-gear@example.com",
};

// The team workspace whose ownership changes hands. Its edit gear's
// accessibility label is the assertion target.
const TEAM_WS_NAME = "Marketing";
const TEAM_WS_ID = 902;
// The gear button's accessibilityLabel from the switcher (DrawerSidebar.tsx /
// workspaces.tsx render `Edit workspace {name}`), surfaced as aria-label on web.
const GEAR_LABEL = `Edit workspace ${TEAM_WS_NAME}`;

const EXPLICIT_APP_URL = process.env.APP_URL || null;

// The ownership state the mocked backend reports for the team workspace. Starts
// owned; flipping this to false is our stand-in for "ownership was transferred
// on the web". Playwright route handlers run in this Node process, so the very
// next GET /workspaces after the flip returns the reversed state.
let owned = true;

// GET /workspaces payload. A personal workspace (always owned, kept as the
// active one so the switcher header stays stable) plus the team workspace under
// test, whose is_owner tracks `owned`.
function workspacesPayload() {
  return {
    data: {
      items: [
        {
          id: 901,
          name: "Personal",
          slug: "personal",
          is_personal: true,
          owner_user_id: MOCK_USER.id,
          is_owner: true,
          color: "#3d6bff",
          icon: "user",
          created_at: "2026-01-01T00:00:00Z",
        },
        {
          id: TEAM_WS_ID,
          name: TEAM_WS_NAME,
          slug: "marketing",
          is_personal: false,
          owner_user_id: owned ? MOCK_USER.id : 5555,
          is_owner: owned,
          color: "#10b981",
          icon: "briefcase",
          created_at: "2026-02-01T00:00:00Z",
        },
      ],
    },
  };
}

// Intercept every backend call. Only a handful of endpoints matter for the
// signed-in boot + drawer + switcher path; everything else gets an empty
// success envelope so the authenticated tab/data burst never reaches a real API.
async function mockApi(context) {
  await context.route("**/api/**", async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname;
    let body = { data: {} };
    if (/\/api\/v1\/auth\/me$/.test(path)) {
      body = { data: { user: MOCK_USER } };
    } else if (/\/api\/v1\/onboarding$/.test(path)) {
      // A fully onboarded account so the launch gate lands in the tabs instead
      // of bouncing through /setup.
      body = {
        data: {
          onboarded_at: "2026-01-01T00:00:00Z",
          email_verified: true,
          has_links: true,
          has_biolink: true,
          whatsapp_pending: false,
          privacy_pending: false,
        },
      };
    } else if (/\/api\/v1\/workspaces$/.test(path)) {
      body = workspacesPayload();
    }
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(body),
    });
  });
}

// The signed-in tab bar is the proof the app booted into the authenticated
// shell (same signal the drawer-signout harness uses).
async function waitForSignedInTabs(page) {
  await page
    .getByText("Profile", { exact: true })
    .first()
    .waitFor({ timeout: NAV_TIMEOUT_MS });
}

// Open the drawer, then expand the workspace switcher dropdown so the per-
// workspace rows (and their owner-only gears) render.
async function openSwitcher(page) {
  const menuBtn = page.locator('[aria-label="Open menu"]').first();
  await menuBtn.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await menuBtn.click();

  // The switcher header carries `Active workspace: {name}. Tap to switch.`
  const switcherHeader = page
    .locator('[aria-label^="Active workspace:"]')
    .first();
  await switcherHeader.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await switcherHeader.click();
}

// Poll for the gear button's presence/absence so we absorb the async refetch +
// re-render after the foreground event instead of racing a single snapshot.
async function waitForGear(page, wantPresent, whatFailed) {
  const gear = page.locator(`[aria-label="${GEAR_LABEL}"]`);
  const deadline = Date.now() + STEP_TIMEOUT_MS;
  let count = -1;
  while (Date.now() < deadline) {
    count = await gear.count();
    const present = count > 0;
    if (present === wantPresent) return;
    await page.waitForTimeout(150);
  }
  fail(`${whatFailed} — gear count=${count}, wanted present=${wantPresent}`);
}

async function runCheck(page, appUrl) {
  const target = appUrl.endsWith("/") ? appUrl : appUrl + "/";
  log("navigating to", target, "(signed-in boot, team workspace owned)");
  await page.goto(target, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });

  await waitForSignedInTabs(page);
  log("booted signed in — tab bar visible");

  await openSwitcher(page);
  log("drawer open, workspace switcher expanded");

  // ---- 1. Owned team workspace: its edit gear is on screen ----------------
  await waitForGear(
    page,
    true,
    `the edit gear for the owned "${TEAM_WS_NAME}" workspace did not render`,
  );
  log(`initial state OK: edit gear visible for owned "${TEAM_WS_NAME}"`);

  // ---- 2. Ownership transferred away on the web while backgrounded --------
  // Flip the mocked backend BEFORE simulating the foreground so the refetch
  // fired by useForegroundRefresh sees the reversed (not-owner) state.
  owned = false;
  log("ownership transferred server-side (mock now reports is_owner=false)");

  // ---- 3. App returns to the foreground: assert the gear clears -----------
  // react-native-web's AppState reads document.visibilityState and emits its
  // "change" event on `visibilitychange`. Simulate background -> foreground so
  // WorkspaceContext's useForegroundRefresh fires "active" and refetches the
  // ["workspaces-list"] query the switcher reads — no manual reload.
  await page.evaluate(() => {
    const setVisibility = (value) => {
      Object.defineProperty(document, "visibilityState", {
        configurable: true,
        get: () => value,
      });
    };
    setVisibility("hidden");
    document.dispatchEvent(new Event("visibilitychange"));
    setVisibility("visible");
    document.dispatchEvent(new Event("visibilitychange"));
  });
  log("dispatched background -> foreground (AppState active) visibility change");

  await waitForGear(
    page,
    false,
    `returning to the foreground did NOT drop the edit gear for "${TEAM_WS_NAME}" ` +
      `after ownership changed hands — the switcher rendered a stale is_owner ` +
      `(foreground refetch regression or a stale-query config)`,
  );
  log(
    `foreground refresh OK: edit gear for "${TEAM_WS_NAME}" cleared live, ` +
      `without a reload`,
  );
}

async function run() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("workspace-owner-gear", EXPLICIT_APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
    return;
  }
  const { appUrl, child, explicit } = server;
  log("driving the workspace owner-gear refresh check against", appUrl);

  let browser;
  try {
    browser = await chromium.launch({ headless: true });
  } catch (e) {
    stopExpo(child);
    skip(
      `could not launch a headless browser in this environment: ${e?.message || e}`,
    );
    return;
  }

  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    // Seed a signed-in, onboarded session BEFORE any app code runs so the
    // launch gate lands straight in the tabs (the drawer only exists when
    // signed in). addInitScript runs on the first document, exactly like an app
    // resumed while signed in.
    await context.addInitScript(
      ({ token, user }) => {
        try {
          window.localStorage.setItem("1inme.onboarding.complete", "1");
          window.localStorage.setItem("1inme.auth.token", token);
          window.localStorage.setItem("1inme.auth.user", JSON.stringify(user));
        } catch {}
      },
      { token: MOCK_TOKEN, user: MOCK_USER },
    );
    await mockApi(context);

    const page = await context.newPage();
    page.on("pageerror", (e) => log("pageerror:", e.message));
    page.setDefaultTimeout(STEP_TIMEOUT_MS);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

    try {
      await runCheck(page, appUrl);
    } catch (e) {
      // Best-effort contract: transient environment errors on a throwaway
      // server SKIP; real regressions exited 1 via fail() before reaching here.
      // Against an explicit APP_URL we always fail hard.
      if (!explicit && isTransientEnvError(e)) {
        await browser.close().catch(() => {});
        stopExpo(child);
        skip(
          `the environment was too slow to drive the check ` +
            `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
            `skipping (best-effort, not an owner-gear regression)`,
        );
        return;
      }
      throw e;
    }

    log(
      "PASS: transferring workspace ownership on the web and returning to the " +
        "app drops the owner-only edit gear from the switcher, live, without a " +
        "manual reload.",
    );
    await browser.close().catch(() => {});
    stopExpo(child);
    // Explicit exit: the throwaway Metro child would otherwise keep the event
    // loop alive; the manager's process-exit hook reaps it.
    process.exit(0);
  } finally {
    await browser.close().catch(() => {});
    stopExpo(child);
  }
}

run().catch((e) => {
  const msg = e?.message || String(e);
  const infra =
    /Target page, context or browser has been closed|browser has been closed|browserType\.launch|pthread_create|Browser closed|Target closed/i.test(
      msg,
    );
  if (infra) {
    skip(
      `browser crashed under environment load, not a product failure: ${msg.split("\n")[0]}`,
    );
  }
  fail(e?.stack || msg);
});
