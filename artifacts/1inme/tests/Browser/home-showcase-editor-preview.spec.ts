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

// Pin the Features page to its built-in default sections too. The home
// editor's "Pull from Features" button reads the Features "Link types"
// category as its sync source, so the pull tests need a deterministic
// source list. Seeding the defaults matches what the page renders when
// nothing is saved, so this never visibly changes the marketing site.
$features = SitePage::firstOrCreate(['slug' => 'features'], ['title' => 'Features']);
$features->sections = SitePagesContent::featuresCategoriesDefault();
$features->save();
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

/**
 * Read the editor's Alpine `featuresSource` array (the Features "Link types"
 * sync list the "Pull from Features" button copies in). Read from the live
 * component so the expectations always mirror exactly what the pull uses.
 */
function readFeaturesSource(
  page: Page,
): Promise<Array<{ name: string; icon: string; desc: string }> | null> {
  return page.evaluate(() => {
    const root = document
      .querySelector("[data-home-showcase-preview]")
      ?.closest("[x-data]");
    const alpine = (window as unknown as { Alpine?: { $data: (el: Element) => unknown } })
      .Alpine;
    if (!root || !alpine) return null;
    const data = alpine.$data(root) as {
      featuresSource?: Array<{ name: string; icon: string; desc: string }>;
    };
    return data.featuresSource
      ? data.featuresSource.map((f) => ({ ...f }))
      : null;
  });
}

/** Ordered names currently in the editor's row inputs. */
function editorRowNames(page: Page): Promise<string[]> {
  return page.evaluate(() =>
    Array.from(
      document.querySelectorAll<HTMLInputElement>(
        'input[name^="extra[link_types]["][name$="][name]"]',
      ),
    ).map((i) => i.value),
  );
}

/** The name input of the editor row at index i (Alpine-bound name). */
function nameInput(page: Page, i: number) {
  return page.locator(`input[name="extra[link_types][${i}][name]"]`);
}

/** The colour input of the editor row at index i. */
function colorInput(page: Page, i: number) {
  return page.locator(
    `input[type="color"][name="extra[link_types][${i}][color]"]`,
  );
}

/** The "New badge" checkbox of the editor row at index i. */
function newCheckbox(page: Page, i: number) {
  return page.locator(
    `input[type="checkbox"][name="extra[link_types][${i}][new]"]`,
  );
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

  test("'Pull from Features' asks first, and cancelling leaves the rows untouched", async ({
    page,
  }) => {
    const before = await editorRowNames(page);
    expect(before.length).toBeGreaterThan(0);

    await page.getByRole("button", { name: "Pull from Features" }).click();

    // The themedConfirm dialog appears — nothing happens without consent.
    await expect(
      page.locator("[data-themed-confirm-title]"),
    ).toHaveText("Pull from Features link types?");
    await expect(page.locator("[data-themed-confirm-ok]")).toBeVisible();

    await page.locator("[data-themed-confirm-cancel]").click();
    await expect(page.locator("[data-themed-confirm-title]")).toHaveCount(0);

    // Rows are exactly as they were.
    expect(await editorRowNames(page)).toEqual(before);
  });

  test("confirming replaces rows with the Features list; matching rows keep colour/new/featured", async ({
    page,
  }) => {
    const source = await readFeaturesSource(page);
    expect(source).not.toBeNull();
    expect(source!.length).toBeGreaterThan(0);

    // Customise row 0 ("Short Link", which name-matches the Features list):
    // distinctive accent colour + "New" badge, and it is already featured.
    await colorInput(page, 0).fill("#ff6600");
    await newCheckbox(page, 0).check();
    await expect(starCheckbox(page, 0)).toBeChecked();

    // Rename row 1 ("Link in Bio", also featured) so it no longer matches
    // any Features name — its styling/flags must NOT carry over.
    await nameInput(page, 1).fill("ZZZ Custom Row");

    await page.getByRole("button", { name: "Pull from Features" }).click();
    await page.locator("[data-themed-confirm-ok]").click();

    // The rows become exactly the Features list — never blank, never partial.
    await expect
      .poll(async () => await editorRowNames(page))
      .toEqual(source!.map((f) => f.name));
    expect(source!.length).toBeGreaterThan(1);

    // Row 0 still name-matches ("Short Link") → keeps colour, "New" and
    // featured state.
    await expect(colorInput(page, 0)).toHaveValue("#ff6600");
    await expect(newCheckbox(page, 0)).toBeChecked();
    await expect(starCheckbox(page, 0)).toBeChecked();

    // The renamed row didn't survive; the pulled "Link in Bio" that replaced
    // it has no matching previous row, so it falls back to defaults.
    const namesAfter = await editorRowNames(page);
    expect(namesAfter).not.toContain("ZZZ Custom Row");
    const libIndex = namesAfter.indexOf("Link in Bio");
    expect(libIndex).toBeGreaterThanOrEqual(0);
    await expect(colorInput(page, libIndex)).toHaveValue("#3d6bff");
    await expect(newCheckbox(page, libIndex)).not.toBeChecked();
    await expect(starCheckbox(page, libIndex)).not.toBeChecked();

    // The preview keeps rendering from the pulled rows (still-matching
    // featured rows populate the big-card tier — no silent wipe).
    const preview = await previewNames(page);
    expect(preview!.featured).toContain("Short Link");
    expect(preview!.featured).not.toContain("Link in Bio");
  });
});
