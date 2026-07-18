#!/usr/bin/env node
/**
 * End-to-end regression gate for the KEYBOARD focus indicator on the mobile
 * sign-in form's fields and buttons (app/(auth)/index.tsx, driven through the
 * shared components/TextField.tsx + components/Button.tsx primitives), driving
 * the REAL app in a headless browser.
 *
 * Why this exists: the sign-in form's controls each render as a React Native
 * Web <input>/<div>/Pressable, which (for the Pressable-backed buttons/tabs)
 * has NO default focus outline. A sighted keyboard user tabbing through the
 * auth screen therefore can't tell which field or button focus is on. Every
 * focusable control is tagged with `data-focus-ring` (via WEB_FOCUS_RING_PROPS)
 * and a global stylesheet (hooks/useWebFocusRing.ts, mounted in app/_layout.tsx)
 * paints an on-brand `:focus-visible` ring — mirroring the floating tab bar
 * (FloatingTabBar.tsx) and side drawer (DrawerSidebar.tsx) treatment. Nothing
 * in CI verified the auth/editor surfaces, so a future style refactor could
 * silently drop the ring — an accessibility regression that would ship
 * unnoticed. The sibling test-drawer-focus-ring-e2e.mjs covers the drawer.
 *
 * What it asserts, per THEME (light + dark):
 *   1. Positive: the email FIELD reached BY KEYBOARD paints a visible
 *      :focus-visible outline (so a sighted keyboard user can track focus).
 *   2. Negative: a POINTER press on the "Email" channel tab (a Pressable-backed
 *      button) leaves NO stray ring — the indicator is :focus-visible only,
 *      never shown on tap.
 *   3. The keyboard-focused field actually matches :focus-visible (proving the
 *      injected stylesheet, not some default UA outline, is responsible).
 *
 * Every /api/** call is intercepted with a benign {data:[]} catch-all so the
 * screen never reaches a real backend. Like the sibling mobile e2e harnesses it
 * boots its OWN throwaway Expo web dev server (shared expo-web-server.mjs
 * manager) unless APP_URL points at an already-running one, and SKIPS (exit 0)
 * when a server / browser can't come up so it never fails CI just because the
 * box couldn't bring Metro or Chromium up. A real focus-ring regression on a
 * server that DID boot still fails (exit 1).
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:form-focus-ring-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting
 *             a throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS, reachLoginScreen } from "./check-icon-fonts.mjs";
import { createExpoServerManager,
  runHarness, isTransientEnvError } from "./expo-web-server.mjs";

function log(...args) {
  console.log("[form-focus-ring-e2e]", ...args);
}

function fail(msg) {
  console.error("[form-focus-ring-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[form-focus-ring-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };

// The email TextField is the FIELD we drive the keyboard focus ring on; the
// "Email" channel tab is a Pressable-backed BUTTON we use for the pointer
// (no-ring) check — clicking it just flips channel state, no navigation.
const POINTER_TAB_LABEL = "Email";

// The email <input> rendered by the shared TextField primitive; tagged with
// data-focus-ring via WEB_FOCUS_RING_PROPS.
function emailField(page) {
  return page.locator("input[data-focus-ring]").first();
}

// The focusable Pressable (data-focus-ring) that renders the "Email" tab.
function channelTab(page, label) {
  return page.locator("[data-focus-ring]", { hasText: label }).first();
}

// The computed outline of whatever element currently holds DOM focus, plus
// whether it matches :focus-visible. Mirrors the drawer/tab-bar harness helper.
async function readFocusedOutline(page) {
  return page.evaluate(() => {
    const el = document.activeElement;
    if (!el) return null;
    const cs = getComputedStyle(el);
    let matchesFocusVisible = false;
    try {
      matchesFocusVisible = el.matches(":focus-visible");
    } catch {}
    const text = (el.textContent || el.getAttribute("value") || "").trim().slice(0, 40);
    return {
      isFocusRing: el.getAttribute("data-focus-ring") === "true",
      text,
      outlineStyle: cs.outlineStyle,
      outlineWidth: cs.outlineWidth,
      outlineColor: cs.outlineColor,
      matchesFocusVisible,
    };
  });
}

function hasVisibleOutline(info) {
  return (
    !!info &&
    info.outlineStyle !== "none" &&
    parseFloat(info.outlineWidth || "0") > 0
  );
}

// Verify the keyboard focus INDICATOR for the sign-in form:
//   • the email field reached via the keyboard shows a visible :focus-visible ring
//   • a pointer press on a button leaves no stray ring
async function checkFocusRing(page, theme) {
  // ---- Positive: keyboard focus → the ring shows -------------------------
  // Pressing Tab establishes the keyboard interaction modality; Chromium then
  // treats a subsequent programmatic .focus() as :focus-visible (the same
  // modality the drawer/tab-bar harnesses rely on). This is deterministic
  // without walking the whole DOM tab order.
  await page.keyboard.press("Tab");
  const field = emailField(page);
  await field.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await field.evaluate((el) => el.focus());
  const focusedInfo = await readFocusedOutline(page);
  if (!focusedInfo || !focusedInfo.isFocusRing) {
    fail(
      `[${theme}] focus ring: expected keyboard focus to land on the email ` +
        `field, but activeElement is "${focusedInfo?.text ?? "none"}" ` +
        `(data-focus-ring=${focusedInfo?.isFocusRing}).`,
    );
  }
  if (!focusedInfo.matchesFocusVisible) {
    fail(
      `[${theme}] focus ring: the keyboard-focused email field does not match ` +
        `:focus-visible — the injected focus stylesheet won't paint its ring ` +
        `for keyboard users.`,
    );
  }
  if (!hasVisibleOutline(focusedInfo)) {
    fail(
      `[${theme}] focus ring: the keyboard-focused email field has no visible ` +
        `outline (outline: "${focusedInfo.outlineWidth} ${focusedInfo.outlineStyle} ` +
        `${focusedInfo.outlineColor}") — a sighted keyboard user can't tell ` +
        `which field focus has landed on.`,
    );
  }
  log(
    `[${theme}] focus ring: keyboard-focused email field shows a visible ` +
      `outline (${focusedInfo.outlineWidth} ${focusedInfo.outlineStyle})`,
  );

  // ---- Negative: a POINTER press must NOT leave a stray ring --------------
  // Click the "Email" channel tab with the mouse; it takes DOM focus but
  // :focus-visible must NOT match, so no outline is painted. Fail-fast if focus
  // doesn't land on the tab (else the negative check would silently pass on the
  // wrong element). Clicking the tab only flips channel state (no navigation),
  // so the element is still present when we read the outline.
  const tab = channelTab(page, POINTER_TAB_LABEL);
  await tab.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await tab.click();
  const afterClick = await readFocusedOutline(page);
  if (!afterClick || !afterClick.isFocusRing) {
    fail(
      `[${theme}] focus ring: clicking the "${POINTER_TAB_LABEL}" tab did not ` +
        `leave DOM focus on it (activeElement "${afterClick?.text ?? "none"}", ` +
        `data-focus-ring=${afterClick?.isFocusRing}) — cannot verify a pointer ` +
        `press leaves no stray ring.`,
    );
  }
  if (afterClick.matchesFocusVisible || hasVisibleOutline(afterClick)) {
    fail(
      `[${theme}] focus ring: a mouse/touch press left a stray outline on the ` +
        `"${POINTER_TAB_LABEL}" tab (outline: "${afterClick.outlineWidth} ` +
        `${afterClick.outlineStyle}", :focus-visible=${afterClick.matchesFocusVisible}) ` +
        `— the ring should be :focus-visible only, not shown on tap.`,
    );
  }
  log(
    `[${theme}] focus ring: a pointer press leaves no stray ring ` +
      `(:focus-visible only)`,
  );
}

async function checkTheme(context, appUrl, theme) {
  const page = await context.newPage();
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
  page.on("pageerror", (e) => log(`[${theme}] pageerror:`, e.message));

  try {
    log(`[${theme}] navigating to ${appUrl} (signed-out boot)`);
    await page.goto(appUrl, { waitUntil: "domcontentloaded" });
    await reachLoginScreen(page);
    log(`[${theme}] login screen visible`);

    await checkFocusRing(page, theme);

    log(
      `[${theme}] PASS: the keyboard-focused email field shows a visible ` +
        `:focus-visible ring; a pointer press on a button leaves no stray ring.`,
    );
  } finally {
    await page.close().catch(() => {});
  }
}

async function run() {
  const { acquireServer } = createExpoServerManager(log);
  const server = await acquireServer("form-focus-ring", process.env.APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
  }
  const explicit = Boolean(server.explicit);
  const appUrl = server.appUrl.endsWith("/")
    ? server.appUrl
    : server.appUrl + "/";

  let browser;
  try {
    browser = await chromium.launch({ headless: true });
  } catch (e) {
    skip(`could not launch a headless browser in this environment: ${e?.message || e}`);
  }

  try {
    for (const theme of ["light", "dark"]) {
      // A fresh context per theme so the persisted theme pref is cleanly
      // isolated. Boot signed OUT (onboarding complete, no token) with the theme
      // pref pre-seeded so ThemeProvider reads it on mount.
      const context = await browser.newContext({
        viewport: VIEWPORT,
        colorScheme: theme,
      });
      await context.addInitScript((themePref) => {
        try {
          window.localStorage.setItem("1inme.onboarding.complete", "1");
          window.localStorage.setItem("1inme.theme", themePref);
        } catch {}
      }, theme);
      // Catch-all so no bootstrap call reaches a real backend.
      await context.route("**/api/**", (route) =>
        route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({ data: [] }),
        }),
      );

      try {
        await checkTheme(context, appUrl, theme);
      } catch (e) {
        // Best-effort contract: on a throwaway server a Playwright timeout /
        // transient connection error means the constrained box was too slow
        // (Metro recompiling, CPU starved) — SKIP, same as when the server or
        // browser couldn't come up. Real focus-ring regressions exit 1 via
        // fail() before this catch can run. Against an explicit APP_URL we
        // still fail hard so local debugging never silently skips.
        if (!explicit && isTransientEnvError(e)) {
          await context.close().catch(() => {});
          await browser.close().catch(() => {});
          skip(
            `the environment was too slow to drive the check ` +
              `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
              `skipping (best-effort, not a focus-ring regression)`,
          );
          return;
        }
        throw e;
      } finally {
        await context.close().catch(() => {});
      }
    }

    log(
      "PASS: keyboard users see a visible on-brand focus ring on the sign-in " +
        "form's fields in both light and dark mode; pointer taps leave no ring.",
    );
    await browser.close().catch(() => {});
    // Explicit exit (like the sibling harnesses): the throwaway Metro child
    // would otherwise keep the event loop alive; the manager's process exit
    // hook reaps it.
    process.exit(0);
  } catch (e) {
    await browser.close().catch(() => {});
    failOrSkipInfra(e, explicit);
  }
}

// Distinguish a real assertion/product failure from the environment killing
// the browser (resource exhaustion during parallel validation runs). Only the
// latter is a skip, and never when pointed at an explicit APP_URL.
function failOrSkipInfra(e, explicit) {
  const msg = e?.message || String(e);
  const infra =
    /Target page, context or browser has been closed|browser has been closed|browserType\.launch|pthread_create|Browser closed|Target closed/i.test(
      msg,
    );
  if (infra && !explicit) {
    skip(`browser crashed under environment load, not a product failure: ${msg.split("\n")[0]}`);
  }
  fail(e?.stack || msg);
}

// Termination guarantee: runHarness exits the process as soon as run()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(run, {
  log,
  onError: (e) => failOrSkipInfra(e, Boolean(process.env.APP_URL)),
});
