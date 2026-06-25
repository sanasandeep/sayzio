import { expect, test, type Page } from "@playwright/test";

// Guards the cookie banner's `auto` theme: it must follow the SITE's own
// light/dark mode (the `html.light-mode` class + localStorage['1inme_theme']),
// not the OS `prefers-color-scheme`. Without this, a future change to the theme
// system (theme-styles.blade.php) or to the consent host
// (cookie-consent.blade.php autoIsDark/siteThemeIsDark + the live
// MutationObserver) could silently bring back the light-page/dark-banner clash.
//
// The effective consent theme is baked into the page server-side as a JSON
// config blob (`@json($cfgJson)`), so rather than mutate the admin AppSetting we
// intercept the /pricing document response and rewrite the `theme` in that blob
// — exercising the real client-side theming per mode.
//
// Invariants pinned here:
//   - theme="auto" + light page  → host has NO `.cc-is-dark`, card is light.
//   - theme="auto" + dark page    → host HAS `.cc-is-dark`, card is dark.
//   - toggling the site light/dark switch while the banner is open flips
//     `.cc-is-dark` (and the card colour) live via the MutationObserver.
//   - explicit admin theme="light"/"dark" is fixed and ignores the page mode.
//
// Runs against the Laravel app on :5000 — localhost:80 hits the Express
// api-server, not Laravel (see APP_URL override below).

const APP_BASE = process.env.APP_URL || "http://localhost:5000";

// Card background colours resolved from theme-styles / cookie-consent CSS:
//   light → var(--cc-bg) default #ffffff; dark → #111827.
const LIGHT_CARD_BG = "rgb(255, 255, 255)";
const DARK_CARD_BG = "rgb(17, 24, 39)";

// Default per-test colour scheme so the site's `prefers-color-scheme` fallback
// (used only when no site theme preference is stored) resolves to light. The
// dark-page tests below set an explicit `1inme_theme=dark` preference, which
// takes precedence over this, so this only fixes the "no preference" baseline.
test.use({ colorScheme: "light" });

/**
 * Intercept the /pricing document and rewrite the consent theme in the
 * server-rendered config blob. Must be installed before navigation. Matches the
 * page by PATH, not full origin, so it works whether the harness runs on :80
 * (dev workflow, where the browser drops the default port) or :5000 (CI).
 */
async function forceTheme(page: Page, theme: string): Promise<void> {
  await page.route(/\/pricing(?:[?#]|$)/, async (route) => {
    if (route.request().resourceType() !== "document") {
      await route.continue();
      return;
    }
    const resp = await route.fetch();
    const headers = resp.headers();
    delete headers["content-length"];
    delete headers["content-encoding"];
    let body = await resp.text();
    // The consent blob serialises `"theme":"<value>","accent":"..."` adjacently
    // (see $cfgJson) — anchoring on the following "accent" key keeps the rewrite
    // from matching an unrelated "theme" elsewhere on the page.
    body = body.replace(
      /"theme":"(?:auto|light|dark)","accent"/,
      `"theme":"${theme}","accent"`,
    );
    await route.fulfill({ status: resp.status(), headers, body });
  });
}

/**
 * Force the SITE into dark mode before any page script runs, via the
 * localStorage preference the layout + consent host both read. Must be called
 * before navigation. (Absent this, the fresh context defaults to light.)
 */
async function preferDarkSite(page: Page): Promise<void> {
  await page.addInitScript(() => {
    try {
      localStorage.setItem("1inme_theme", "dark");
    } catch (e) {
      /* ignore */
    }
  });
}

type HostState = {
  hasHost: boolean;
  dataTheme: string;
  isDark: boolean;
  cardBg: string;
  htmlLight: boolean;
};

async function readHostState(page: Page): Promise<HostState> {
  return page.evaluate(() => {
    const host = document.querySelector(".cc-host");
    const card = host ? host.querySelector<HTMLElement>(".cc-card") : null;
    return {
      hasHost: !!host,
      dataTheme: host ? host.getAttribute("data-theme") || "" : "",
      isDark: host ? host.classList.contains("cc-is-dark") : false,
      cardBg: card ? getComputedStyle(card).backgroundColor : "",
      htmlLight: document.documentElement.classList.contains("light-mode"),
    };
  });
}

/** Wait until the consent host (and its card) is mounted. */
async function waitForHost(page: Page): Promise<void> {
  await page.locator(".cc-host .cc-card").waitFor({ state: "attached" });
}

test.describe("cookie consent — auto theme follows the site mode", () => {
  test('theme="auto" on a light page: banner is light (no .cc-is-dark)', async ({
    page,
  }) => {
    await forceTheme(page, "auto");
    // Fresh context (default per-test) → no consent cookie → banner shows; no
    // stored theme + colorScheme:light → site renders in light mode.
    await page.goto(`${APP_BASE}/pricing`);
    await waitForHost(page);

    const s = await readHostState(page);
    expect(s.hasHost).toBe(true);
    expect(s.dataTheme).toBe("auto");
    // Page is genuinely light…
    expect(s.htmlLight).toBe(true);
    // …so the auto banner is light: no dark class, light card.
    expect(s.isDark).toBe(false);
    expect(s.cardBg).toBe(LIGHT_CARD_BG);
  });

  test('theme="auto" on a dark page: banner is dark (.cc-is-dark)', async ({
    page,
  }) => {
    await forceTheme(page, "auto");
    await preferDarkSite(page);
    await page.goto(`${APP_BASE}/pricing`);
    await waitForHost(page);

    const s = await readHostState(page);
    expect(s.hasHost).toBe(true);
    expect(s.dataTheme).toBe("auto");
    // Page is genuinely dark (the layout did NOT add light-mode)…
    expect(s.htmlLight).toBe(false);
    // …so the auto banner is dark: dark class present, dark card.
    expect(s.isDark).toBe(true);
    expect(s.cardBg).toBe(DARK_CARD_BG);
  });

  test('theme="auto": toggling the site switch flips the banner live', async ({
    page,
  }) => {
    await forceTheme(page, "auto");
    // Start in light mode (default), banner light.
    await page.goto(`${APP_BASE}/pricing`);
    await waitForHost(page);

    let s = await readHostState(page);
    expect(s.isDark).toBe(false);
    expect(s.cardBg).toBe(LIGHT_CARD_BG);

    // Flip the real marketing light/dark switch (Alpine @click →
    // window.inmeToggleTheme, which removes the html.light-mode class). The
    // banner's MutationObserver watches that class and should re-theme live.
    await page.waitForFunction(
      () =>
        typeof (window as unknown as { inmeToggleTheme?: unknown })
          .inmeToggleTheme === "function",
    );
    const toggle = page.locator(".mkt-theme-toggle").first();
    await toggle.click();

    await page.waitForFunction(() => {
      const h = document.querySelector(".cc-host");
      return !!h && h.classList.contains("cc-is-dark");
    });
    s = await readHostState(page);
    expect(s.htmlLight).toBe(false);
    expect(s.isDark).toBe(true);
    expect(s.cardBg).toBe(DARK_CARD_BG);

    // …and flipping back returns the banner to light, also live.
    await toggle.click();
    await page.waitForFunction(() => {
      const h = document.querySelector(".cc-host");
      return !!h && !h.classList.contains("cc-is-dark");
    });
    s = await readHostState(page);
    expect(s.htmlLight).toBe(true);
    expect(s.isDark).toBe(false);
    expect(s.cardBg).toBe(LIGHT_CARD_BG);
  });
});

test.describe("cookie consent — explicit admin theme ignores the page mode", () => {
  test('theme="light" stays light even on a dark page', async ({ page }) => {
    await forceTheme(page, "light");
    await preferDarkSite(page);
    await page.goto(`${APP_BASE}/pricing`);
    await waitForHost(page);

    const s = await readHostState(page);
    expect(s.dataTheme).toBe("light");
    // Page is genuinely dark, but the fixed light banner is unaffected: the
    // auto-only .cc-is-dark class is never applied, and the card stays light.
    expect(s.htmlLight).toBe(false);
    expect(s.isDark).toBe(false);
    expect(s.cardBg).toBe(LIGHT_CARD_BG);
  });

  test('theme="dark" stays dark even on a light page', async ({ page }) => {
    await forceTheme(page, "dark");
    // Default light page.
    await page.goto(`${APP_BASE}/pricing`);
    await waitForHost(page);

    const s = await readHostState(page);
    expect(s.dataTheme).toBe("dark");
    // Page is genuinely light, but the fixed dark banner is unaffected: its dark
    // styling comes from the data-theme="dark" selector, not the auto-only
    // .cc-is-dark class (which stays off).
    expect(s.htmlLight).toBe(true);
    expect(s.isDark).toBe(false);
    expect(s.cardBg).toBe(DARK_CARD_BG);
  });
});
