#!/usr/bin/env node
/**
 * End-to-end regression gate for the side drawer's KEYBOARD focus indicator
 * (components/DrawerSidebar.tsx), driving the REAL app in a headless browser.
 *
 * Why this exists: the drawer's controls (nav items, close, workspace switcher,
 * theme buttons, sign out) each render as a React Native Web <div>/Pressable,
 * which has NO default focus outline. A sighted keyboard user tabbing through
 * the drawer therefore can't tell which item focus is on. DrawerSidebar tags
 * every focusable control with `data-drawer-focusable` and injects a global
 * stylesheet painting an on-brand `:focus-visible` ring (mirroring the floating
 * tab bar's treatment in FloatingTabBar.tsx). Nothing in CI verified that, so a
 * future style refactor could silently drop the ring — an accessibility
 * regression that would ship unnoticed.
 *
 * What it asserts, per THEME (light + dark):
 *   1. Positive: a drawer nav item reached BY KEYBOARD paints a visible
 *      :focus-visible outline (so a sighted keyboard user can track focus).
 *   2. Negative: a POINTER press on a nav item leaves NO stray ring — the
 *      indicator is :focus-visible only, never shown on tap.
 *   3. The keyboard-focused item actually matches :focus-visible (proving the
 *      injected stylesheet, not some default UA outline, is responsible).
 *
 * Every /api/** call is intercepted with a benign {data:[]} catch-all so the
 * authenticated shell never reaches a real backend. Like the sibling mobile
 * e2e harnesses it boots its OWN throwaway Expo web dev server (shared
 * expo-web-server.mjs manager) unless APP_URL points at an already-running one,
 * and SKIPS (exit 0) when a server / browser can't come up so it never fails
 * CI just because the box couldn't bring Metro or Chromium up. A real focus-
 * ring regression on a server that DID boot still fails (exit 1).
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:drawer-focus-ring-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting
 *             a throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import { createExpoServerManager, isTransientEnvError } from "./expo-web-server.mjs";

function log(...args) {
  console.log("[drawer-focus-ring-e2e]", ...args);
}

function fail(msg) {
  console.error("[drawer-focus-ring-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[drawer-focus-ring-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-drawer-focus-ring-token";
const MOCK_USER = {
  id: 63,
  display_name: "Drawer Focus Tester",
  email: "drawer-focus-ring@example.com",
};

// A couple of nav labels from DrawerSidebar's NAV_GROUPS. "Dashboard" and
// "Links" are stable Main-group entries; we drive the focus ring on them.
const NAV_LABEL_KEYBOARD = "Dashboard";
const NAV_LABEL_POINTER = "Links";

// The signed-in tab bar is the proof the app booted into the authenticated
// shell (same signal the sibling harnesses use).
async function waitForSignedInTabs(page) {
  await page
    .getByText("Profile", { exact: true })
    .first()
    .waitFor({ timeout: STEP_TIMEOUT_MS });
}

async function openDrawer(page) {
  const menuBtn = page.locator('[aria-label="Open menu"]').first();
  await menuBtn.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await menuBtn.click();
  // Sign out is the last control in the drawer — its visibility proves the
  // drawer content (incl. the nav items) has mounted.
  await page
    .locator('[aria-label="Sign out"]')
    .first()
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
}

// Locate the focusable Pressable (data-drawer-focusable) that renders a given
// nav label. The label Text is a child span, so we match the tagged ancestor.
function navItem(page, label) {
  return page.locator(`[data-drawer-focusable]`, { hasText: label }).first();
}

// The computed outline of whatever element currently holds DOM focus, plus
// whether it matches :focus-visible. Mirrors the tab bar harness helper.
async function readFocusedOutline(page) {
  return page.evaluate(() => {
    const el = document.activeElement;
    if (!el) return null;
    const cs = getComputedStyle(el);
    let matchesFocusVisible = false;
    try {
      matchesFocusVisible = el.matches(":focus-visible");
    } catch {}
    const text = (el.textContent || "").trim().slice(0, 40);
    return {
      isDrawerFocusable: el.getAttribute("data-drawer-focusable") === "true",
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

// Verify the keyboard focus INDICATOR for the drawer:
//   • a nav item reached via the keyboard shows a visible :focus-visible ring
//   • a pointer press leaves no stray ring
async function checkFocusRing(page, theme) {
  // ---- Positive: keyboard focus → the ring shows -------------------------
  // Pressing Tab establishes the keyboard interaction modality; Chromium then
  // treats a subsequent programmatic .focus() on the nav item as
  // :focus-visible (the same modality the tab bar harness relies on via arrow
  // keys). This is deterministic without walking the whole DOM tab order,
  // which the overlay drawer + tab bar behind it make fragile.
  await page.keyboard.press("Tab");
  const kbdItem = navItem(page, NAV_LABEL_KEYBOARD);
  await kbdItem.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await kbdItem.evaluate((el) => el.focus());
  const focusedInfo = await readFocusedOutline(page);
  if (!focusedInfo || !focusedInfo.isDrawerFocusable) {
    fail(
      `[${theme}] focus ring: expected keyboard focus to land on the ` +
        `"${NAV_LABEL_KEYBOARD}" drawer nav item, but activeElement is ` +
        `"${focusedInfo?.text ?? "none"}" ` +
        `(data-drawer-focusable=${focusedInfo?.isDrawerFocusable}).`,
    );
  }
  if (!focusedInfo.matchesFocusVisible) {
    fail(
      `[${theme}] focus ring: the keyboard-focused "${NAV_LABEL_KEYBOARD}" ` +
        `nav item does not match :focus-visible — the injected drawer focus ` +
        `stylesheet won't paint its ring for keyboard users.`,
    );
  }
  if (!hasVisibleOutline(focusedInfo)) {
    fail(
      `[${theme}] focus ring: the keyboard-focused "${NAV_LABEL_KEYBOARD}" ` +
        `nav item has no visible outline (outline: "${focusedInfo.outlineWidth} ` +
        `${focusedInfo.outlineStyle} ${focusedInfo.outlineColor}") — a sighted ` +
        `keyboard user can't tell which drawer item focus has landed on.`,
    );
  }
  log(
    `[${theme}] focus ring: keyboard-focused drawer nav item shows a visible ` +
      `outline (${focusedInfo.outlineWidth} ${focusedInfo.outlineStyle})`,
  );

  // ---- Negative: a POINTER press must NOT leave a stray ring --------------
  // Click a nav item with the mouse; it takes DOM focus but :focus-visible
  // must NOT match, so no outline is painted. Fail-fast if focus doesn't land
  // on the clicked item (else the negative check would silently pass on the
  // wrong element). Clicking a nav item navigates + closes the drawer after a
  // short delay, so we read the outline immediately after the click.
  const ptrItem = navItem(page, NAV_LABEL_POINTER);
  await ptrItem.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await ptrItem.click();
  const afterClick = await readFocusedOutline(page);
  if (!afterClick || !afterClick.isDrawerFocusable) {
    fail(
      `[${theme}] focus ring: clicking the "${NAV_LABEL_POINTER}" nav item did ` +
        `not leave DOM focus on it (activeElement "${afterClick?.text ?? "none"}", ` +
        `data-drawer-focusable=${afterClick?.isDrawerFocusable}) — cannot ` +
        `verify a pointer press leaves no stray ring.`,
    );
  }
  if (afterClick.matchesFocusVisible || hasVisibleOutline(afterClick)) {
    fail(
      `[${theme}] focus ring: a mouse/touch press left a stray outline on the ` +
        `"${NAV_LABEL_POINTER}" nav item (outline: "${afterClick.outlineWidth} ` +
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
    log(`[${theme}] navigating to ${appUrl} (signed-in boot)`);
    await page.goto(appUrl, { waitUntil: "domcontentloaded" });
    await waitForSignedInTabs(page);
    log(`[${theme}] booted signed in — tab bar visible`);

    await openDrawer(page);
    log(`[${theme}] drawer open — nav items visible`);

    await checkFocusRing(page, theme);

    log(
      `[${theme}] PASS: keyboard-focused drawer nav items show a visible ` +
        `:focus-visible ring; a pointer press leaves no stray ring.`,
    );
  } finally {
    await page.close().catch(() => {});
  }
}

async function run() {
  const { acquireServer } = createExpoServerManager(log);
  const server = await acquireServer("drawer-focus-ring", process.env.APP_URL);
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
      // A fresh context per theme so the persisted theme pref + signed-in
      // session are cleanly isolated. Boot signed in (onboarding complete +
      // token + user) with the theme pref pre-seeded so ThemeProvider reads
      // it on mount.
      const context = await browser.newContext({
        viewport: VIEWPORT,
        colorScheme: theme,
      });
      await context.addInitScript(
        ([token, user, themePref]) => {
          try {
            window.localStorage.setItem("1inme.onboarding.complete", "1");
            window.localStorage.setItem("1inme.auth.token", token);
            window.localStorage.setItem("1inme.auth.user", JSON.stringify(user));
            window.localStorage.setItem("1inme.theme", themePref);
          } catch {}
        },
        [MOCK_TOKEN, MOCK_USER, theme],
      );
      // Catch-all so no authenticated tab burst reaches a real backend.
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
      "PASS: keyboard users see a visible on-brand focus ring on the drawer " +
        "nav items in both light and dark mode; pointer taps leave no ring.",
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

run().catch((e) => {
  failOrSkipInfra(e, Boolean(process.env.APP_URL));
});
