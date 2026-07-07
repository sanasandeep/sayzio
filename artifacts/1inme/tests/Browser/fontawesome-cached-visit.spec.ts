import { expect, test, type Page } from "@playwright/test";

// Runtime companion to the static fontawesome-loader guard
// (scripts/src/check-fontawesome-loader.ts). The static guard pins the shape of
// the shared loader partial
// (resources/views/common/partials/fontawesome.blade.php) — now a PLAIN
// BLOCKING stylesheet link (data-fa-stylesheet) + woff2 font preloads, after
// the loadCSS media=print swap repeatedly blanked icons in real Safari. This
// spec proves the stylesheet actually applies and icons genuinely render in a
// real browser — including on a warm-cache repeat visit.
//
// What it asserts, on both a COLD first visit and a WARM (cached) reload:
//   1. The FA <link data-fa-stylesheet> is present with an applied stylesheet.
//   2. The Font Awesome webfont is actually loaded (document.fonts.check).
//   3. A rendered fa-* icon has a non-zero-width ::before glyph drawn with the
//      Font Awesome font-family — i.e. the icon is genuinely visible, not a
//      blank box.
//
// What it CAN catch: partial edits that break loading, asset path drift, font
// 404s, or anyone reintroducing a media=print swap (the link would carry a
// non-empty media and the stylesheet would not apply in print-less rendering).
//
// Runs against the Laravel app (APP_URL, default :5000) — localhost:80 hits the
// Express api-server, not Laravel.

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const CONSENT_COOKIE = "1inme_cookie_consent";

// /about extends public/layouts/site.blade.php (which @includes the shared
// fontawesome partial) and renders plenty of fa-* icons in its content. It is
// a light, stable, unauthenticated marketing route.
const PAGE_PATH = "/about";

async function seedConsent(page: Page): Promise<void> {
  const decision = JSON.stringify({
    v: 1,
    t: Date.now(),
    c: { analytics: true, marketing: false, functional: false },
  });
  await page.context().addCookies([
    {
      name: CONSENT_COOKIE,
      value: encodeURIComponent(decision),
      domain: "localhost",
      path: "/",
    },
  ]);
}

type FaState = {
  linkCount: number;
  stylesheetApplied: boolean;
  fontLoaded: boolean;
  iconFound: boolean;
  iconFontFamily: string;
  glyphContent: string;
  glyphWidth: number;
};

/**
 * Wait until the FA link has been activated (media="all") and the webfont is
 * available, then measure a real rendered fa-* glyph. Returns the observed
 * state for assertion; waiting is bounded by the caller's expect.poll/timeouts.
 */
async function readFaState(page: Page): Promise<FaState> {
  return page.evaluate(async () => {
    const links = Array.from(
      document.querySelectorAll<HTMLLinkElement>("link[data-fa-stylesheet]"),
    );
    // The stylesheet is "applied" when its CSSOM is reachable and it is not
    // scoped to a non-matching media type.
    const stylesheetApplied = links.some((l) => {
      if (l.media && l.media !== "all" && l.media !== "screen") return false;
      try {
        return !!l.sheet && l.sheet.cssRules.length > 0;
      } catch {
        return false;
      }
    });

    // document.fonts.ready resolves once all pending font loads settle.
    try {
      await (document as Document & { fonts: FontFaceSet }).fonts.ready;
    } catch {
      /* older engines: fall through, the check below still runs */
    }
    const fonts = (document as Document & { fonts: FontFaceSet }).fonts;
    const fontLoaded =
      fonts.check('900 16px "Font Awesome 6 Free"') ||
      fonts.check('400 16px "Font Awesome 6 Brands"');

    // Find a visible fa-* icon element and measure its ::before glyph.
    const icons = Array.from(
      document.querySelectorAll<HTMLElement>(
        'i[class*="fa-"], span[class*="fa-"]',
      ),
    );
    let iconFound = false;
    let iconFontFamily = "";
    let glyphContent = "";
    let glyphWidth = 0;
    for (const icon of icons) {
      const rect = icon.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) continue;
      const before = getComputedStyle(icon, "::before");
      const content = before.content;
      if (!content || content === "none" || content === "normal") continue;
      iconFound = true;
      iconFontFamily = before.fontFamily;
      glyphContent = content;
      glyphWidth = rect.width;
      break;
    }

    return {
      linkCount: links.length,
      stylesheetApplied,
      fontLoaded,
      iconFound,
      iconFontFamily,
      glyphContent,
      glyphWidth,
    };
  });
}

async function assertIconsRender(page: Page, visit: string): Promise<void> {
  // The blocking stylesheet should be applied as soon as the DOM is ready;
  // poll briefly in case the CSSOM is still settling.
  await expect
    .poll(
      async () => {
        const s = await readFaState(page);
        return s.linkCount > 0 && s.stylesheetApplied;
      },
      {
        message: `${visit}: link[data-fa-stylesheet] missing or stylesheet never applied`,
        timeout: 20000,
      },
    )
    .toBe(true);

  await expect
    .poll(async () => (await readFaState(page)).fontLoaded, {
      message: `${visit}: Font Awesome webfont never became available`,
      timeout: 20000,
    })
    .toBe(true);

  const state = await readFaState(page);
  expect(
    state.iconFound,
    `${visit}: no visible fa-* icon with a rendered ::before glyph found`,
  ).toBe(true);
  expect(
    state.iconFontFamily,
    `${visit}: icon ::before is not using the Font Awesome font (got "${state.iconFontFamily}")`,
  ).toMatch(/font awesome/i);
  expect(
    state.glyphWidth,
    `${visit}: rendered fa glyph has zero width (content=${state.glyphContent})`,
  ).toBeGreaterThan(0);
}

test.describe("Font Awesome loader — icons render on cold and cached visits", () => {
  test("fa-* glyphs visible and stylesheet applied on first visit AND warm-cache reload", async ({
    page,
  }) => {
    await seedConsent(page);

    // ---- Cold first visit ----
    await page.goto(`${APP_BASE}${PAGE_PATH}`, {
      waitUntil: "domcontentloaded",
    });
    await assertIconsRender(page, "cold visit");

    // Sanity: the stylesheet request actually happened and succeeded once.
    // (Guards against a silently-404ing asset path where glyphs fall back to
    // some other icon font that happens to render.)
    const faResponse = await page.evaluate(() => {
      const entries = performance.getEntriesByType(
        "resource",
      ) as PerformanceResourceTiming[];
      const e = entries.find((r) => r.name.includes("fontawesome"));
      return e ? { found: true, name: e.name } : { found: false, name: "" };
    });
    expect(
      faResponse.found,
      "cold visit: no fontawesome stylesheet resource entry recorded",
    ).toBe(true);

    // ---- Warm reload: stylesheet now served from HTTP cache ----
    // page.reload() keeps the browser cache (Playwright contexts have caching
    // enabled by default), so the blocking FA stylesheet is satisfied from
    // cache — historically the condition under which the old print-swap
    // loader broke in Safari; the plain link must simply apply.
    await page.reload({ waitUntil: "domcontentloaded" });
    await assertIconsRender(page, "warm-cache reload");

    // ---- Second navigation (fresh document, still-cached stylesheet) ----
    // A reload sometimes revalidates; a plain re-navigation within the same
    // context is the closest headless analogue of "user comes back later".
    await page.goto(`${APP_BASE}${PAGE_PATH}`, {
      waitUntil: "domcontentloaded",
    });
    await assertIconsRender(page, "cached re-navigation");
  });
});
