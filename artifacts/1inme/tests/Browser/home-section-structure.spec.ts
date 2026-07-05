import { expect, test, type Page } from "@playwright/test";

// Structural guard for the marketing home page (task: "Keep the home page's
// section order from silently breaking").
//
// The home page was reorganised into thematic zones, and every AI section was
// consolidated under a single `#ai-zone` wrapper. A set of section ids is
// CONTRACTUAL: they back nav anchors, in-page jump links (the AI-zone chips
// `#ai-suite` / `#ai-marketing-strategist` / `#whatsapp-agent`, the final CTA's
// `#features` link, the hero's `#ai-suite` ghost CTAs) and external deep links.
// Because Blade has no compile-time check on ids, a future edit could silently
// drop one, duplicate one, or move an AI partial out of `#ai-zone` — and every
// jump link pointing at it would quietly break with no error.
//
// This headless spec renders the real home page and asserts the invariant:
//   1. each required section id is present EXACTLY once (not missing, not
//      duplicated — a duplicate id makes `#id` anchors ambiguous), and
//   2. the four AI partials (ai-hero, ai-suite, ai-marketing-strategist,
//      whatsapp-agent) all render INSIDE `#ai-zone`, with the AI hero appearing
//      exactly once.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the other home/marketing specs).

const CONSENT_COOKIE = "1inme_cookie_consent";

// Contractual section ids on the home page. Each must appear exactly once in the
// rendered DOM. These are the anchor targets that nav links, the AI-zone chips,
// hero/CTA jump links and deep links rely on; losing or duplicating any one
// silently breaks navigation to that zone.
const REQUIRED_SECTION_IDS: readonly string[] = [
  // Thematic zones / major sections, in document order.
  "audience",
  "how-it-works",
  "features",
  "share",
  "domains",
  "create",
  // The consolidated AI zone wrapper + its three anchored AI sections.
  "ai-zone",
  "ai-suite",
  "ai-marketing-strategist",
  "whatsapp-agent",
  "ai-dashboard",
  // Remaining major sections below the AI zone.
  "workspace-team",
  "buzz",
  "proof",
  "faq",
  "cta-final",
] as const;

// NOTE: `#everything` (the "Everything you get" overview grid), `#stats` (the
// mid-page stats strip) and `#trust` (the near-bottom security/trust strip) were
// intentionally removed when the three repeated stats/credibility strips were
// consolidated into the single near-hero credibility band
// (public.partials.marketing-trust-band). They are no longer contractual anchors.

// Section ids that are CONDITIONALLY rendered (data- or flag-gated), so they may
// legitimately be absent. They are still contractual anchors WHEN present, so we
// assert they are never duplicated — but not that they always exist. `#blog-featured`
// only renders when there are featured blog posts (`$featuredBlogPosts`).
const CONDITIONAL_SECTION_IDS: readonly string[] = ["blog-featured"] as const;

// The four AI partials that must live inside `#ai-zone`, keyed by a stable
// marker id each partial renders. The AI hero has no section-level id of its
// own; its heading `#ai-hero-h` is its single-occurrence identity marker.
const AI_ZONE_PARTIAL_MARKERS: readonly { readonly label: string; readonly id: string }[] = [
  { label: "ai-hero", id: "ai-hero-h" },
  { label: "ai-suite", id: "ai-suite" },
  { label: "ai-marketing-strategist", id: "ai-marketing-strategist" },
  { label: "whatsapp-agent", id: "whatsapp-agent" },
  { label: "ai-dashboard", id: "ai-dashboard" },
] as const;

/**
 * Load the home page with consent already given (so the bottom-pinned cookie
 * banner can't intercept anything) and wait for the DOM. The assertions are
 * purely structural, so we only need the markup — no Alpine warm-up.
 */
async function gotoHome(page: Page): Promise<void> {
  await page.context().addCookies([
    {
      name: CONSENT_COOKIE,
      value: encodeURIComponent(
        JSON.stringify({
          v: 1,
          t: Date.now(),
          c: { analytics: false, marketing: false, functional: false },
        }),
      ),
      domain: "localhost",
      path: "/",
    },
  ]);
  // The home page is heavy (maps, embeds); don't wait for full network idle.
  await page.goto("/", { waitUntil: "domcontentloaded" });
  // The final section is near the bottom of the document; waiting for it
  // ensures the whole home template has rendered before we count ids.
  await page.locator("#cta-final").waitFor({ state: "attached" });
}

/** Count how many elements carry a given id (catches duplicates, unlike `#id`). */
async function countById(page: Page, id: string): Promise<number> {
  return page.evaluate(
    (elId) => document.querySelectorAll(`[id="${elId}"]`).length,
    id,
  );
}

test.describe("home page section structure", () => {
  test.beforeEach(async ({ page }) => {
    // Distant RDS makes the first paint slow; give each test headroom.
    test.setTimeout(60_000);
  });

  test("every required section id is present exactly once", async ({ page }) => {
    await gotoHome(page);

    const counts: Record<string, number> = {};
    for (const id of REQUIRED_SECTION_IDS) {
      counts[id] = await countById(page, id);
    }

    const missing = REQUIRED_SECTION_IDS.filter((id) => counts[id] === 0);
    expect(
      missing,
      `home page is missing required section id(s): ${missing.join(", ")}`,
    ).toEqual([]);

    const duplicated = REQUIRED_SECTION_IDS.filter((id) => counts[id] > 1);
    expect(
      duplicated,
      `home page has duplicated section id(s): ${duplicated
        .map((id) => `#${id}×${counts[id]}`)
        .join(", ")}`,
    ).toEqual([]);

    // Conditionally-rendered anchors may be absent (0), but must never be
    // duplicated (>1) — a duplicate id makes the anchor ambiguous.
    for (const id of CONDITIONAL_SECTION_IDS) {
      const n = await countById(page, id);
      expect(
        n,
        `conditional section id #${id} appears ${n} times (must be 0 or 1)`,
      ).toBeLessThanOrEqual(1);
    }
  });

  test("the four AI partials render inside #ai-zone (AI hero exactly once)", async ({
    page,
  }) => {
    await gotoHome(page);

    // `#ai-zone` itself must exist exactly once to anchor the partials.
    expect(await countById(page, "ai-zone")).toBe(1);

    for (const { label, id } of AI_ZONE_PARTIAL_MARKERS) {
      const count = await countById(page, id);
      expect(count, `AI partial "${label}" marker #${id} count`).toBe(1);

      const insideZone = await page.evaluate((elId) => {
        const el = document.getElementById(elId);
        const zone = document.getElementById("ai-zone");
        return !!(el && zone && zone.contains(el));
      }, id);
      expect(
        insideZone,
        `AI partial "${label}" (#${id}) must render inside #ai-zone`,
      ).toBe(true);
    }
  });
});
