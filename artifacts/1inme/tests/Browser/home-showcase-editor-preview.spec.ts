import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

/**
 * Guards the "What you can create" admin editor's Alpine-driven live preview
 * (admin/site-pages/partials/home-editor.blade.php, data-home-showcase-preview),
 * which mirrors SitePagesContent::splitHomeLinkTypesFeatured. An Alpine
 * regression (renamed helper, broken x-for key) would silently blank the
 * preview — this spec asserts both tiers render, that untoggling a featured
 * star moves the card into the "And plenty more" strip, and that reordering
 * rows reorders the preview.
 */

// All tests share a single logged-in admin context (demo-login is
// rate-limited, so a login per test could trip the limit).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/** Run a `php artisan tinker` seed, retrying transient RDS connection blips. */
function runTinkerSeed(php: string): string {
  let lastErr: unknown;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
        cwd: ARTIFACT_ROOT,
        encoding: "utf8",
      });
    } catch (err) {
      lastErr = err;
    }
  }
  throw lastErr;
}

/**
 * Deterministically seed the home page's showcase list to the built-in
 * defaults (explicit `featured` flags on the first 6 entries, 18 rows total).
 * This is exactly what the public home page renders when nothing is saved, so
 * on the shared RDS this seed never visibly changes the marketing site — it
 * only pins the editor's initial state so the spec's tier assertions are
 * stable regardless of what a previous run (or admin) left behind.
 *
 * NOTE: the string is passed straight to `tinker --execute=`; `\\` becomes the
 * single backslash PHP namespaces need, and bare `$var` stays literal.
 */
function seedFixtures(): void {
  const php = `
use App\\Modules\\Common\\Models\\SitePage;
use App\\Modules\\Common\\Support\\SitePagesContent;

$page = SitePage::firstOrCreate(['slug' => 'home'], ['title' => 'Home']);
$rows = SitePagesContent::homeLinkTypesDefault();
$cap = SitePagesContent::HOME_LINK_TYPES_FEATURED_CAP;
$i = 0;
foreach ($rows as &$r) { $r['featured'] = $i < $cap; $i++; }
unset($r);
$extra = is_array($page->extra) ? $page->extra : [];
$extra['link_types'] = array_values($rows);
$page->extra = $extra;
$page->save();
echo 'SEEDED=' . count($rows);
`.trim();

  const out = runTinkerSeed(php);
  if (!/SEEDED=\d+/.test(out)) {
    throw new Error("Seed failed, output:\n" + out);
  }
}

/**
 * Log in on the ADMIN guard via the non-prod demo-login form. Submits the form
 * via JS and waits only for the POST response (not the heavy admin dashboard
 * render) so the redirect target never blocks the suite.
 */
async function loginAsDemoAdmin(page: Page): Promise<void> {
  await page.goto("/admin/login");
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/admin/demo-login") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    page.evaluate(() => {
      const form = document.querySelector<HTMLFormElement>(
        'form[action$="/admin/demo-login"]',
      );
      if (!form) throw new Error("admin demo-login form not found");
      form.submit();
    }),
  ]);
}

/** The editor card that hosts the whole Alpine component (rows + preview). */
function editorRoot(page: Page) {
  return page.locator("div:has(> * [data-home-showcase-preview])");
}

/**
 * Read the preview's two tiers as ordered name lists straight from the DOM.
 * Featured big-card titles carry `text-[12px] font-bold`; strip entries carry
 * `text-[10px] font-semibold` — both inside [data-home-showcase-preview].
 */
function previewNames(page: Page) {
  return page.evaluate(() => {
    const root = document.querySelector("[data-home-showcase-preview]");
    if (!root) return null;
    const grab = (el: Element) => (el.textContent ?? "").trim();
    const featured = Array.from(root.querySelectorAll("div"))
      .filter(
        (d) =>
          d.className.includes("text-[12px]") &&
          d.className.includes("font-bold"),
      )
      .map(grab);
    const more = Array.from(root.querySelectorAll("span"))
      .filter(
        (s) =>
          s.className.includes("text-[10px]") &&
          s.className.includes("font-semibold") &&
          s.hasAttribute("x-text"),
      )
      .map(grab);
    return { featured, more };
  });
}

/** The featured-star checkbox of the editor row at index i (Alpine-bound name). */
function starCheckbox(page: Page, i: number) {
  return page.locator(
    `input[type="checkbox"][name="extra[link_types][${i}][featured]"]`,
  );
}

test.describe("home showcase editor — Alpine live preview", () => {
  // Admin Blade renders go over a distant RDS; lift the shared 60s ceiling.
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async ({ browser }) => {
    sharedContext = await browser.newContext();
    seedFixtures();
    const page = await sharedContext.newPage();
    await loginAsDemoAdmin(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test.beforeEach(async ({ page }) => {
    // Re-seed so each attempt starts from the canonical default split, then
    // open the home editor fresh (all edits below are client-side only —
    // nothing is ever saved, so the DB state stays pristine between tests).
    seedFixtures();
    await page.goto("/admin/site-pages/home");
    await expect(page.locator("[data-home-showcase-preview]")).toBeVisible({
      timeout: 90_000,
    });
  });

  test("renders both tiers mirroring the featured split", async ({ page }) => {
    const names = await previewNames(page);
    expect(names).not.toBeNull();

    // The seed stars exactly the first 6 defaults — the big-card tier shows
    // them in list order and everything else lands in the strip.
    expect(names!.featured).toEqual([
      "Short Link",
      "Link in Bio",
      "Conversational",
      "Slides",
      "AI Chatbot",
      "Restaurant Menu",
    ]);
    expect(names!.more.length).toBeGreaterThan(0);
    expect(names!.more[0]).toBe("Store Menu");
    expect(names!.more).not.toContain("Short Link");

    // The strip divider is present alongside a populated strip.
    await expect(
      page
        .locator("[data-home-showcase-preview]")
        .getByText("And plenty more"),
    ).toBeVisible();
  });

  test("untoggling a featured star moves the card into the strip", async ({
    page,
  }) => {
    // Row 0 ("Short Link") starts featured.
    const star = starCheckbox(page, 0);
    await expect(star).toBeChecked();
    await star.click();

    await expect
      .poll(async () => {
        const names = await previewNames(page);
        return names?.featured ?? [];
      })
      .not.toContain("Short Link");
    const names = await previewNames(page);
    expect(names!.more).toContain("Short Link");
    // The remaining featured cards keep list order, led by the next row.
    expect(names!.featured[0]).toBe("Link in Bio");

    // Re-star it — the card returns to the big-card tier (still under cap
    // because we just freed a slot).
    await starCheckbox(page, 0).click();
    await expect
      .poll(async () => {
        const n = await previewNames(page);
        return n?.featured ?? [];
      })
      .toContain("Short Link");
  });

  test("reordering rows reorders the preview", async ({ page }) => {
    // Click "Move down" on the first editor row — "Short Link" and
    // "Link in Bio" swap in both the rows list and the preview split.
    const root = editorRoot(page);
    await root.locator('button[title="Move down"]').first().click();

    await expect
      .poll(async () => {
        const names = await previewNames(page);
        return names?.featured.slice(0, 2) ?? [];
      })
      .toEqual(["Link in Bio", "Short Link"]);

    // The strip is untouched by a swap inside the featured tier.
    const names = await previewNames(page);
    expect(names!.more[0]).toBe("Store Menu");
  });
});
