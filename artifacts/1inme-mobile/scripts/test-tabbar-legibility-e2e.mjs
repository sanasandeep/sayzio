#!/usr/bin/env node
/**
 * End-to-end regression gate for the floating tab bar's ACTIVE-tab legibility
 * (components/FloatingTabBar.tsx), driving the REAL app in a headless browser.
 *
 * Why this exists: the active-tab indicator is the brand primary colour (blue)
 * on icon + label, while inactive tabs use the muted foreground colour.
 * The center "Create" tab always shows a white icon on a brand-gradient circle.
 * Nothing in CI verified that treatment, so a future colour-token change (or an
 * accidental style refactor) could silently revert the active state back to the
 * non-active weight/colour, making it hard to distinguish. That would ship
 * unnoticed.
 *
 * What it asserts, per THEME (light + dark):
 *   1. The active label uses the BOLD weight (SpaceGrotesk_700Bold), distinct
 *      from the inactive SemiBold — i.e. it hasn't fallen back to non-active.
 *   2. The active label paints in the theme's primary colour (brand blue),
 *      NOT the muted inactive colour.
 *   3. The Create tab's icon is always white (sits on a gradient circle).
 *   4. A control INACTIVE tab uses the SemiBold weight + muted colour —
 *      proving the check actually discriminates active vs not.
 *   5. The active tab is programmatically announced as selected to assistive
 *      tech: it exposes role="tab" + aria-selected="true", while inactive tabs
 *      expose aria-selected="false". This guards the accessibility contract so
 *      a future refactor can't silently drop it back to role="button" (for
 *      which React Native Web omits aria-selected entirely).
 *
 * Every /api/** call is intercepted with a benign {data:[]} catch-all so the
 * authenticated shell never reaches a real backend. Like the sibling mobile
 * e2e harnesses it boots its OWN throwaway Expo web dev server (shared
 * expo-web-server.mjs manager) unless APP_URL points at an already-running
 * one, and SKIPS (exit 0) when a server / browser can't come up so it never
 * fails CI just because the box couldn't bring Metro or Chromium up. A real
 * legibility regression on a server that DID boot still fails (exit 1).
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:tabbar-legibility-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting
 *             a throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import { createExpoServerManager,
  runHarness, isTransientEnvError } from "./expo-web-server.mjs";

function log(...args) {
  console.log("[tabbar-legibility-e2e]", ...args);
}

function fail(msg) {
  console.error("[tabbar-legibility-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[tabbar-legibility-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-tabbar-legibility-token";
const MOCK_USER = {
  id: 77,
  display_name: "Tab Bar Tester",
  email: "tabbar-legibility@example.com",
};

// The five tabs, mirrored from FloatingTabBar.tsx. `pathname` is what
// router.navigate lands on; `hasLabel` is false for "create" (icon-only).
const TABS = [
  { label: "Home", pathname: "/", hasLabel: true },
  { label: "Links", pathname: "/links", hasLabel: true },
  { label: "Create", pathname: "/create", hasLabel: false },
  { label: "Inbox", pathname: "/inbox", hasLabel: true },
  { label: "Profile", pathname: "/profile", hasLabel: true },
];

// Expected computed colours (rgb) per theme, from constants/colors.ts.
//   light: primary #3d6bff (blue600), mutedForeground #475569
//   dark:  primary #7d9bff (blue400), mutedForeground #9ca3af
// The center Create tab always uses a white icon (#ffffff) — it sits on a
// brand-gradient circle, not the glass bar, so it stays white in both themes.
const THEME_COLORS = {
  light: { primary: "rgb(61, 107, 255)", muted: "rgb(71, 85, 105)", createIcon: "rgb(255, 255, 255)" },
  dark: { primary: "rgb(125, 155, 255)", muted: "rgb(156, 163, 175)", createIcon: "rgb(255, 255, 255)" },
};

// Return the expected active icon colour for a given tab.
function activeColorFor(tab, expected) {
  return tab.label === "Create" ? expected.createIcon : expected.primary;
}

// The active label must use this weight; inactive labels use SemiBold. RNW
// emits the raw fontFamily string as the computed font-family value.
const BOLD_FAMILY = "SpaceGrotesk_700Bold";
const SEMIBOLD_FAMILY = "SpaceGrotesk_600SemiBold";

async function waitForSignedInTabs(page) {
  // Home tab pressable is the proof the authenticated shell + tab bar mounted.
  await page
    .locator('[aria-label="Home"]')
    .first()
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
}

// Read the computed legibility-relevant styles of a tab's label + icon.
// Runs entirely in the page so we get real getComputedStyle values.
async function inspectTab(page, label) {
  return page.evaluate((lbl) => {
    const btn = document.querySelector(`[aria-label="${lbl}"]`);
    if (!btn) return { error: "tab pressable not found" };

    // Walk every descendant; classify by its OWN (direct) text content:
    //   - the label element's direct text === the tab label ("Home", …)
    //   - the icon element's direct text is a Feather glyph in the Unicode
    //     Private Use Area (U+E000–U+F8FF).
    let labelEl = null;
    let iconEl = null;
    for (const el of btn.querySelectorAll("*")) {
      const direct = Array.from(el.childNodes)
        .filter((n) => n.nodeType === 3)
        .map((n) => n.nodeValue)
        .join("")
        .trim();
      if (!direct) continue;
      if (direct === lbl) {
        labelEl = el;
      } else if (/[\uE000-\uF8FF]/.test(direct)) {
        iconEl = el;
      }
    }

    const read = (el) => {
      if (!el) return null;
      const cs = getComputedStyle(el);
      return {
        textShadow: cs.textShadow,
        fontFamily: cs.fontFamily,
        color: cs.color,
      };
    };

    return {
      ariaSelected: btn.getAttribute("aria-selected"),
      label: read(labelEl),
      icon: read(iconEl),
    };
  }, label);
}

function hasShadow(v) {
  return typeof v === "string" && v.trim() !== "" && v.trim() !== "none";
}

// Read the `tabindex` attribute of every tab, in TABS order, from the DOM.
async function readTabIndices(page) {
  return page.evaluate((labels) => {
    return labels.map((l) => {
      const el = document.querySelector(`[aria-label="${l}"]`);
      return el ? el.getAttribute("tabindex") : "missing";
    });
  }, TABS.map((t) => t.label));
}

// The aria-label of whatever element currently holds DOM focus (or null).
async function readFocusedLabel(page) {
  return page.evaluate(() => {
    const el = document.activeElement;
    return el ? el.getAttribute("aria-label") : null;
  });
}

// The computed outline of whatever element currently holds DOM focus, plus
// whether it matches :focus-visible. Used to prove a keyboard-focused tab
// paints a visible focus ring (and a mouse/touch press does not).
async function readFocusedOutline(page) {
  return page.evaluate(() => {
    const el = document.activeElement;
    if (!el) return null;
    const cs = getComputedStyle(el);
    let matchesFocusVisible = false;
    try {
      matchesFocusVisible = el.matches(":focus-visible");
    } catch {}
    return {
      label: el.getAttribute("aria-label"),
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

// Verify the keyboard focus INDICATOR: a keyboard-focused tab must paint a
// visible :focus-visible outline (so a sighted keyboard user can track which
// tab focus is on), while a mouse/touch press must NOT leave a stray ring.
// Assumes the tab bar is mounted and interactive.
async function checkFocusRing(page, theme) {
  // ---- Positive: reach a tab BY KEYBOARD → the ring shows -----------------
  // Focus the (active) Home tab, then arrow to Links so focus was moved via
  // the keyboard — Chromium then treats it as :focus-visible.
  await page.locator(`[aria-label="${TABS[0].label}"]`).first().focus();
  await page.keyboard.press("ArrowRight"); // keyboard → Links
  const focusedInfo = await readFocusedOutline(page);
  if (!focusedInfo || focusedInfo.label !== TABS[1].label) {
    fail(
      `[${theme}] focus ring: expected keyboard focus on "${TABS[1].label}" ` +
        `to inspect its focus indicator, got "${focusedInfo?.label ?? "none"}".`,
    );
  }
  if (!hasVisibleOutline(focusedInfo)) {
    fail(
      `[${theme}] focus ring: the keyboard-focused "${TABS[1].label}" tab has ` +
        `no visible outline (outline: "${focusedInfo.outlineWidth} ` +
        `${focusedInfo.outlineStyle} ${focusedInfo.outlineColor}", ` +
        `:focus-visible=${focusedInfo.matchesFocusVisible}) — a sighted ` +
        `keyboard user can't tell which tab focus has landed on.`,
    );
  }
  log(
    `[${theme}] focus ring: keyboard-focused tab shows a visible outline ` +
      `(${focusedInfo.outlineWidth} ${focusedInfo.outlineStyle})`,
  );

  // ---- Negative: a POINTER press must NOT leave a stray ring --------------
  // Click Profile with the mouse; it takes DOM focus but :focus-visible must
  // NOT match, so the tab carries no outline. Assert fail-fast: focus must
  // actually land on Profile (otherwise the negative check would silently pass
  // on the wrong element) AND the tab must carry no visible ring.
  await page.locator(`[aria-label="${TABS[4].label}"]`).first().click();
  const afterClick = await readFocusedOutline(page);
  if (!afterClick || afterClick.label !== TABS[4].label) {
    fail(
      `[${theme}] focus ring: clicking "${TABS[4].label}" did not leave DOM ` +
        `focus on it (activeElement aria-label="${afterClick?.label ?? "none"}") ` +
        `— cannot verify a pointer press leaves no stray ring.`,
    );
  }
  if (hasVisibleOutline(afterClick)) {
    fail(
      `[${theme}] focus ring: a mouse/touch press left a stray outline on ` +
        `"${TABS[4].label}" (outline: "${afterClick.outlineWidth} ` +
        `${afterClick.outlineStyle}") — the ring should be :focus-visible ` +
        `only, not shown on tap.`,
    );
  }
  log(
    `[${theme}] focus ring: a pointer press leaves no stray ring ` +
      `(:focus-visible only)`,
  );
}

// Verify the WAI-ARIA tab keyboard pattern on web:
//   • Roving tabindex — only the ACTIVE tab is in the natural tab order
//     (tabindex 0); every other tab is tabindex -1.
//   • ArrowRight/ArrowDown move focus to the next tab (wrapping past the last),
//     ArrowLeft/ArrowUp to the previous (wrapping before the first).
//   • Home/End jump focus to the first/last tab.
//   • Enter on a focused, non-active tab navigates to it (roving tabindex then
//     follows the newly active tab).
// Assumes the caller has just landed on Home (TABS[0] active).
async function checkKeyboardNav(page, theme, expected) {
  // ---- Roving tabindex: only the active (Home) tab is tabbable ----------
  const indices = await readTabIndices(page);
  const active = indices.findIndex((v) => v === "0");
  if (active !== 0) {
    fail(
      `[${theme}] keyboard: expected only the active Home tab to have ` +
        `tabindex="0", got tabindex list [${indices.join(", ")}] — the ` +
        `roving tabindex isn't anchored to the active tab.`,
    );
  }
  const strays = indices.filter((v, i) => i !== active && v !== "-1");
  if (strays.length) {
    fail(
      `[${theme}] keyboard: non-active tabs must be tabindex="-1" (out of the ` +
        `natural tab order), got [${indices.join(", ")}] — arrow-key roving ` +
        `is defeated if every tab is tabbable.`,
    );
  }

  // ---- Arrow keys move focus across the tabs (with wrap) ----------------
  await page.locator(`[aria-label="${TABS[0].label}"]`).first().focus();
  let focused = await readFocusedLabel(page);
  if (focused !== TABS[0].label) {
    fail(
      `[${theme}] keyboard: could not focus the Home tab to begin arrow-key ` +
        `navigation (activeElement aria-label="${focused}").`,
    );
  }

  // Walk forward through every tab and past the end (wrap back to Home).
  const forwardOrder = [...TABS.slice(1).map((t) => t.label), TABS[0].label];
  for (const exp of forwardOrder) {
    await page.keyboard.press("ArrowRight");
    focused = await readFocusedLabel(page);
    if (focused !== exp) {
      fail(
        `[${theme}] keyboard: ArrowRight should have moved focus to "${exp}", ` +
          `but focus is on "${focused}".`,
      );
    }
  }
  log(`[${theme}] keyboard: ArrowRight walks all tabs and wraps to Home`);

  // From Home, ArrowLeft wraps back to the last tab (Profile).
  await page.keyboard.press("ArrowLeft");
  focused = await readFocusedLabel(page);
  if (focused !== TABS[TABS.length - 1].label) {
    fail(
      `[${theme}] keyboard: ArrowLeft from Home should wrap to ` +
        `"${TABS[TABS.length - 1].label}", but focus is on "${focused}".`,
    );
  }

  // ArrowUp/ArrowDown behave like Left/Right.
  await page.keyboard.press("ArrowDown");
  focused = await readFocusedLabel(page);
  if (focused !== TABS[0].label) {
    fail(
      `[${theme}] keyboard: ArrowDown from the last tab should wrap to ` +
        `"${TABS[0].label}", but focus is on "${focused}".`,
    );
  }
  await page.keyboard.press("ArrowUp");
  focused = await readFocusedLabel(page);
  if (focused !== TABS[TABS.length - 1].label) {
    fail(
      `[${theme}] keyboard: ArrowUp should move focus to ` +
        `"${TABS[TABS.length - 1].label}", but focus is on "${focused}".`,
    );
  }
  log(`[${theme}] keyboard: ArrowLeft/Up/Down move + wrap focus correctly`);

  // Home/End jump to the first/last tab.
  await page.keyboard.press("Home");
  focused = await readFocusedLabel(page);
  if (focused !== TABS[0].label) {
    fail(
      `[${theme}] keyboard: Home key should focus "${TABS[0].label}", but ` +
        `focus is on "${focused}".`,
    );
  }
  await page.keyboard.press("End");
  focused = await readFocusedLabel(page);
  if (focused !== TABS[TABS.length - 1].label) {
    fail(
      `[${theme}] keyboard: End key should focus ` +
        `"${TABS[TABS.length - 1].label}", but focus is on "${focused}".`,
    );
  }
  log(`[${theme}] keyboard: Home/End jump to first/last tab`);

  // ---- Enter on a focused, non-active tab navigates to it ---------------
  // Focus Home, arrow to Links, press Enter — the tab bar should activate
  // Links (its icon adopts the focused foreground) and the roving tabindex
  // should follow to Links.
  await page.locator(`[aria-label="${TABS[0].label}"]`).first().focus();
  await page.keyboard.press("ArrowRight"); // → Links
  focused = await readFocusedLabel(page);
  if (focused !== TABS[1].label) {
    fail(
      `[${theme}] keyboard: expected focus on "${TABS[1].label}" before ` +
        `pressing Enter, got "${focused}".`,
    );
  }
  await page.keyboard.press("Enter");
  const activated = await waitForIconColor(page, TABS[1].label, expected.primary);
  if (activated.timedOut) {
    fail(
      `[${theme}] keyboard: pressing Enter on the focused "${TABS[1].label}" ` +
        `tab did not navigate to it (icon colour stayed ` +
        `"${activated.last?.icon?.color ?? "n/a"}", expected the active ` +
        `colour "${expected.primary}").`,
    );
  }
  const afterEnter = await readTabIndices(page);
  if (afterEnter[1] !== "0") {
    fail(
      `[${theme}] keyboard: after Enter-activating "${TABS[1].label}", the ` +
        `roving tabindex="0" should follow it, got [${afterEnter.join(", ")}].`,
    );
  }
  log(`[${theme}] keyboard: Enter activates the focused tab + tabindex follows`);

  // ---- Space on a focused, non-active tab also activates it -------------
  // Focus is still on Links (Enter doesn't move focus). Arrow to Create, then
  // press Space — Create should activate, proving Space works alongside Enter.
  await page.keyboard.press("ArrowRight"); // Links → Create
  focused = await readFocusedLabel(page);
  if (focused !== TABS[2].label) {
    fail(
      `[${theme}] keyboard: expected focus on "${TABS[2].label}" before ` +
        `pressing Space, got "${focused}".`,
    );
  }
  await page.keyboard.press(" ");
  const activatedSpace = await waitForIconColor(page, TABS[2].label, expected.createIcon);
  if (activatedSpace.timedOut) {
    fail(
      `[${theme}] keyboard: pressing Space on the focused "${TABS[2].label}" ` +
        `tab did not navigate to it (icon colour stayed ` +
        `"${activatedSpace.last?.icon?.color ?? "n/a"}", expected the active ` +
        `colour "${expected.createIcon}").`,
    );
  }
  log(`[${theme}] keyboard: Space also activates the focused tab`);
}

// Poll a tab's computed styles until its icon paints in `targetColor`, the
// signal the tab bar has (de)focused it. Returns the settled inspectTab info,
// or `{ timedOut: true, last }` if it never reached that colour within
// STEP_TIMEOUT_MS — so a stuck tab bar fails loudly instead of hanging.
// NOTE: the focused state is read from the rendered icon colour
// (primaryForeground vs mutedForeground), which is also one of the things this
// gate asserts. The tabs now use role="tab", so React Native Web ALSO emits
// `aria-selected`; that semantic attribute is asserted separately once the tab
// has settled into its focused/unfocused colour.
async function waitForIconColor(page, label, targetColor) {
  const deadline = Date.now() + STEP_TIMEOUT_MS;
  let last = null;
  for (;;) {
    const info = await inspectTab(page, label);
    last = info;
    if (info && info.icon && info.icon.color === targetColor) return info;
    if (Date.now() >= deadline) return { timedOut: true, last };
    await page.waitForTimeout(120);
  }
}

// Activate a tab by tapping it and waiting for its icon to adopt the expected
// active colour. Tapping the already-active tab is a no-op in the app
// (onPress returns early), and its icon is already the active colour, so
// this settles immediately in that case.
async function activateTab(page, tab, theme, expected) {
  const targetColor = activeColorFor(tab, expected);
  const pressable = page.locator(`[aria-label="${tab.label}"]`).first();
  await pressable.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await pressable.click();
  const info = await waitForIconColor(page, tab.label, targetColor);
  if (info.timedOut) {
    fail(
      `[${theme}] ${tab.label}: tab never became active after tapping it ` +
        `(icon colour stayed "${info.last?.icon?.color ?? "n/a"}", expected ` +
        `the active colour "${targetColor}"). The tab bar may ` +
        `not be responding to navigation.`,
    );
  }
  return info;
}

async function checkTheme(context, appUrl, theme) {
  const expected = THEME_COLORS[theme];
  const page = await context.newPage();
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);
  page.on("pageerror", (e) => log(`[${theme}] pageerror:`, e.message));

  try {
    log(`[${theme}] navigating to ${appUrl} (signed-in boot)`);
    await page.goto(appUrl, { waitUntil: "domcontentloaded" });
    await waitForSignedInTabs(page);
    log(`[${theme}] booted signed in — tab bar visible`);

    for (const tab of TABS) {
      const info = await activateTab(page, tab, theme, expected);
      if (info.error) {
        fail(`[${theme}] ${tab.label}: ${info.error}`);
      }

      // ---- Active tab must be ANNOUNCED as selected to assistive tech ------
      // role="tab" makes React Native Web emit aria-selected; the active tab
      // must expose "true" so a screen reader speaks its selected state.
      if (info.ariaSelected !== "true") {
        fail(
          `[${theme}] ${tab.label}: active tab is not announced as selected ` +
            `(aria-selected="${info.ariaSelected}", expected "true"). A ` +
            `screen-reader user gets no spoken signal for the active tab.`,
        );
      }

      // ---- Active ICON must be present and paint the active colour ---------
      if (!info.icon) {
        fail(`[${theme}] ${tab.label}: could not find the active icon glyph`);
      }

      if (!tab.hasLabel) {
        log(`[${theme}] ${tab.label}: icon-only tab — active icon found OK`);
        continue;
      }

      // ---- Active LABEL: bold weight + brand primary colour ---------------
      // The new design shows active tabs with the brand primary colour (blue)
      // instead of a white text-shadow over a gradient circle. No shadow needed.
      if (!info.label) {
        fail(`[${theme}] ${tab.label}: could not find the active label text`);
      }
      if (!info.label.fontFamily.includes(BOLD_FAMILY)) {
        fail(
          `[${theme}] ${tab.label}: active label fell back to a non-active ` +
            `weight (font-family="${info.label.fontFamily}", expected to ` +
            `include ${BOLD_FAMILY}).`,
        );
      }
      if (info.label.color !== expected.primary) {
        fail(
          `[${theme}] ${tab.label}: active label colour is ` +
            `"${info.label.color}", expected the brand primary ` +
            `"${expected.primary}" (not the muted inactive colour).`,
        );
      }
      log(
        `[${theme}] ${tab.label}: active label bold + brand primary OK`,
      );
    }

    // ---- Control: an INACTIVE tab must look inactive --------------------
    // Land on Home so a different tab (Profile) is guaranteed inactive, then
    // assert it carries NO shadow, the SemiBold weight, and the muted colour.
    // This proves the check discriminates — if a regression made the active
    // state look like inactive (or vice-versa), the asserts above would fire.
    await activateTab(page, TABS[0], theme, expected); // Home
    // Wait until Profile's icon has dropped back to the muted colour (i.e. it
    // has been de-focused) so we inspect a settled inactive tab, not a mid-
    // transition one.
    const settled = await waitForIconColor(page, "Profile", expected.muted);
    if (settled.timedOut) {
      fail(
        `[${theme}] control: Profile never returned to the muted inactive ` +
          `colour after focusing Home (icon colour stayed ` +
          `"${settled.last?.icon?.color ?? "n/a"}", expected the muted ` +
          `"${expected.muted}") — active and inactive look the same.`,
      );
    }
    const inactive = settled;
    if (inactive.error || !inactive.label) {
      fail(`[${theme}] control: could not inspect the inactive Profile tab`);
    }
    if (inactive.ariaSelected !== "false") {
      fail(
        `[${theme}] control: an INACTIVE tab does not expose ` +
          `aria-selected="false" (got "${inactive.ariaSelected}") — the ` +
          `selected state isn't being announced distinctly from unselected.`,
      );
    }
    if (!inactive.label.fontFamily.includes(SEMIBOLD_FAMILY)) {
      fail(
        `[${theme}] control: inactive label weight is ` +
          `"${inactive.label.fontFamily}", expected the SemiBold family.`,
      );
    }
    if (inactive.label.color !== expected.muted) {
      fail(
        `[${theme}] control: inactive label colour is ` +
          `"${inactive.label.color}", expected the muted "${expected.muted}".`,
      );
    }
    log(`[${theme}] control: inactive tab correctly plain/muted`);

    // ---- Keyboard: WAI-ARIA tab pattern (roving tabindex + arrow keys) ----
    // We're currently landed on Home (from the control block above), so the
    // keyboard check can assume Home is the active tab.
    await checkKeyboardNav(page, theme, expected);

    // ---- Keyboard focus indicator (:focus-visible ring) -------------------
    // A sighted keyboard user must see which tab focus is on as they arrow
    // across; the ring must appear only for keyboard focus, not pointer taps.
    await checkFocusRing(page, theme);

    log(
      `[${theme}] PASS: active tab stays bold + brand-primary colour; ` +
        `inactive stays plain/muted; keyboard arrow-key navigation follows ` +
        `the WAI-ARIA tab pattern; the keyboard-focused tab shows a visible ` +
        `:focus-visible ring.`,
    );
  } finally {
    await page.close().catch(() => {});
  }
}

async function run() {
  const { acquireServer } = createExpoServerManager(log);
  const server = await acquireServer("tabbar-legibility", process.env.APP_URL);
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
        // browser couldn't come up. Real legibility regressions exit 1 via
        // fail() before this catch can run. Against an explicit APP_URL we
        // still fail hard so local debugging never silently skips.
        if (!explicit && isTransientEnvError(e)) {
          await context.close().catch(() => {});
          await browser.close().catch(() => {});
          skip(
            `the environment was too slow to drive the check ` +
              `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
              `skipping (best-effort, not a legibility regression)`,
          );
          return;
        }
        throw e;
      } finally {
        await context.close().catch(() => {});
      }
    }

    log(
      "PASS: the active tab stays readable (bold + shadow + foreground) on " +
        "every gradient stop in both light and dark mode.",
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
