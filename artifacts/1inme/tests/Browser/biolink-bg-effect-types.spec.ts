import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test as base, type BrowserContext, type Locator, type Page } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * Task #6211 — browser-level guard for the Task #6204 Tiles / Mesh /
 * Pattern background types plus the torn-paper tear styles & quick combos.
 *
 * The save/render paths are covered by feature tests
 * (BiolinkBackgroundTypesTest); this spec proves the Alpine editor UI works
 * end-to-end:
 *   1. the new type buttons render in the Background Type grid and switch
 *      the visible panel,
 *   2. swatch clicks fill the hidden inputs (tiles_palette / mesh_preset /
 *      pattern_preset) and toggle the selection ring,
 *   3. the torn quick-combo chips update the paper/backdrop color inputs
 *      AND flip the hidden torn_style input via the torn-style-combo
 *      window event,
 *   4. saving persists the pick (settings.biolink round-trip incl. the
 *      bg_effect_colors mobile stamp),
 *   5. the public page renders the picked background (dedicated
 *      .bg-tiles / .bg-torn-* layers, mesh/pattern CSS inlined).
 */

const RUN_ID = Date.now().toString(36);
const ALIAS_PREFIX = "e2e-bgfx-";
const ALIAS = `${ALIAS_PREFIX}${RUN_ID}`;

const ARTIFACT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

function runTinker(php: string): string {
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

let sharedContext: BrowserContext;
let linkId = 0;

const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    // Neutralize the device-preview iframe: every $dispatch('change') in the
    // appearance form triggers a draft push + iframe reload of the (heavy)
    // public page. In this constrained env that reliably crashed the shared
    // Chromium renderer ("Target crashed") mid-test. The in-editor preview
    // is not what this spec guards — the editor controls, persistence and
    // the real public page render are — so blank out sub-frame documents.
    await page.route("**/*", async (route) => {
      const req = route.request();
      let isSubframeDoc = false;
      try {
        isSubframeDoc = req.resourceType() === "document" && req.frame() !== page.mainFrame();
      } catch {
        isSubframeDoc = false;
      }
      if (!isSubframeDoc) return route.fallback();
      await route.fulfill({
        status: 200,
        contentType: "text/html",
        body: "<!doctype html><title>preview blocked</title>",
      });
    });
    await use(page);
    await page.close();
  },
});

test.describe.configure({ mode: "serial" });

test.beforeAll(async ({ browser }) => {
  // Seed the fixture biolink (owned by the demo-login account, or the owner
  // guard 403s the editor) and prune stale fixtures from earlier runs.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Services\\WorkspaceContext;

Link::withoutGlobalScope('workspace')
    ->where('alias', 'like', '${ALIAS_PREFIX}%')
    ->where('created_at', '<', now()->subHours(2))
    ->delete();

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);
$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E BG Effects', 'is_active' => true,
  'settings' => ['biolink' => ['background_type' => 'color', 'background_color' => '#0a0612']],
]);
echo 'LINK_ID=' . $bio->id . '=END';
`.trim();
  const out = runTinker(php);
  const m = out.match(/LINK_ID=(\d+)=END/);
  if (!m) throw new Error("Seed failed, tinker output:\n" + out);
  linkId = Number(m[1]);

  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
  if (linkId) {
    runTinker(
      `App\\Modules\\User\\Models\\Link::withoutGlobalScope('workspace')->where('id', ${linkId})->delete();`,
    );
  }
});

async function gotoAppearance(page: Page) {
  await page.goto(`/user/links/${linkId}/settings/appearance`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  await expect(page.locator('form[action*="page-settings"]')).toBeVisible({ timeout: 30_000 });
  // Alpine boots on DOMContentLoaded (deferred vendored script). Wait for it
  // explicitly — the type buttons are x-for-rendered and don't exist pre-boot.
  await page.waitForFunction(() => (window as unknown as { Alpine?: unknown }).Alpine !== undefined, undefined, {
    timeout: 60_000,
  });
}

/** Click the x-for-rendered Background Type button (label lives in a child span). */
async function pickType(form: Locator, label: string) {
  await form.locator(`button:has(span:text-is("${label}"))`).click();
}

/**
 * The per-type panel div (parent of that type's hidden input). Preset labels
 * repeat across panels (e.g. "Aurora" exists in more than one catalog), so
 * every swatch/chip locator must be scoped to its own panel.
 */
function typePanel(form: Locator, hiddenInputName: string): Locator {
  return form.locator(`input[name="${hiddenInputName}"]`).locator("xpath=..");
}

/** Save the appearance form and wait out the POST → 302 → GET reload cycle. */
async function saveAppearance(page: Page, form: Locator) {
  // Cold RDS POSTs can be slow — arm waitForResponse before clicking.
  const saved = page.waitForResponse(
    (r) => r.url().includes(`/page-settings`) && r.request().method() === "POST",
    { timeout: 60_000 },
  );
  // The POST 302s back to the appearance page; wait for that follow-up GET
  // too, or a later goto gets interrupted by the redirect navigation
  // (waitForURL is a no-op — the URL never changes).
  const redirected = page.waitForResponse(
    (r) => r.url().includes("/settings/appearance") && r.request().method() === "GET",
    { timeout: 120_000 },
  );
  await form.locator("#saveAppearanceBtn").click({ noWaitAfter: true });
  const resp = await saved;
  expect(resp.status(), "page-settings POST should redirect back").toBeLessThan(400);
  await redirected;
  await page.waitForLoadState("load", { timeout: 60_000 }).catch(() => {});
}

/** Read the persisted settings.biolink map via tinker. */
function readBiolinkSettings(): Record<string, unknown> {
  const out = runTinker(
    `$l = App\\Modules\\User\\Models\\Link::withoutGlobalScope('workspace')->findOrFail(${linkId});` +
      `echo 'STATE=' . json_encode($l->settings['biolink'] ?? []) . '=END';`,
  );
  const m = out.match(/STATE=(\{.*?\})=END/s);
  expect(m, "tinker state output:\n" + out).toBeTruthy();
  return JSON.parse(m![1]) as Record<string, unknown>;
}

/** goto the public page, retrying if a lingering redirect navigation races it. */
async function gotoPublic(page: Page): Promise<string> {
  for (let attempt = 1; ; attempt++) {
    try {
      await page.goto(`/${ALIAS}`, { waitUntil: "domcontentloaded", timeout: 120_000 });
      break;
    } catch (err) {
      if (attempt >= 5 || !String(err).includes("interrupted by another navigation")) throw err;
      await page.waitForLoadState("load", { timeout: 30_000 }).catch(() => {});
      await page.waitForTimeout(1000);
    }
  }
  return page.content();
}

test("tiles: palette swatch selects, saves and renders the tile layer", async ({ page }) => {
  test.setTimeout(300_000);
  await gotoAppearance(page);
  const form = page.locator('form[action*="page-settings"]');

  await pickType(form, "Tiles");
  await expect(form.locator('input[name="background_type"]')).toHaveValue("tiles");

  const panel = typePanel(form, "tiles_palette");
  const hidden = form.locator('input[name="tiles_palette"]');
  const midnight = panel.locator('button[title="Midnight"]');
  const ocean = panel.locator('button[title="Ocean"]');
  await expect(midnight).toBeVisible();

  // Pick → hidden input carries the catalog key + ring shows; picking
  // another palette swaps the value.
  await midnight.click();
  await expect(hidden).toHaveValue("tiles_midnight");
  await expect(midnight).toHaveClass(/ring-2/);
  await ocean.click();
  await expect(hidden).toHaveValue("tiles_ocean");
  await expect(midnight).not.toHaveClass(/ring-2/);

  await form.locator('select[name="tiles_layout"]').selectOption("metro");
  await form.locator('select[name="tiles_animate"]').selectOption("1");

  await saveAppearance(page, form);

  const bio = readBiolinkSettings();
  expect(bio.background_type).toBe("tiles");
  expect(bio.tiles_palette).toBe("tiles_ocean");
  expect(bio.tiles_layout).toBe("metro");
  expect(String(bio.tiles_animate)).toBe("1");
  // Mobile-fallback stamp (Ocean colors).
  expect(bio.bg_effect_colors).toEqual(["#0891b2", "#0e7490", "#164e63"]);

  // Public page renders the dedicated tile layer.
  const html = await gotoPublic(page);
  expect(html).toContain("bg-tiles");
  expect(html).toContain("#0891b2");
});

test("mesh: preset swatch selects/deselects, saves and renders inline CSS", async ({ page }) => {
  test.setTimeout(300_000);
  await gotoAppearance(page);
  const form = page.locator('form[action*="page-settings"]');

  await pickType(form, "Mesh");
  await expect(form.locator('input[name="background_type"]')).toHaveValue("mesh");

  const hidden = form.locator('input[name="mesh_preset"]');
  const aurora = typePanel(form, "mesh_preset").locator('button[title="Aurora"]');
  await expect(aurora).toBeVisible();

  // Click selects; clicking again deselects; re-select for the save.
  await aurora.click();
  await expect(hidden).toHaveValue("mesh_aurora");
  await expect(aurora).toHaveClass(/ring-2/);
  await aurora.click();
  await expect(hidden).toHaveValue("");
  await aurora.click();
  await expect(hidden).toHaveValue("mesh_aurora");

  await saveAppearance(page, form);

  const bio = readBiolinkSettings();
  expect(bio.background_type).toBe("mesh");
  expect(bio.mesh_preset).toBe("mesh_aurora");
  expect(Array.isArray(bio.bg_effect_colors) && (bio.bg_effect_colors as string[]).length).toBeTruthy();

  // Public page inlines the mesh CSS (Aurora base color).
  const html = await gotoPublic(page);
  expect(html).toContain("#0b1026");
});

test("pattern: preset swatch selects, saves and renders inline CSS", async ({ page }) => {
  test.setTimeout(300_000);
  await gotoAppearance(page);
  const form = page.locator('form[action*="page-settings"]');

  await pickType(form, "Pattern");
  await expect(form.locator('input[name="background_type"]')).toHaveValue("pattern");

  const hidden = form.locator('input[name="pattern_preset"]');
  const dotsDark = typePanel(form, "pattern_preset").locator('button[title="Dots Dark"]');
  await expect(dotsDark).toBeVisible();

  await dotsDark.click();
  await expect(hidden).toHaveValue("pattern_dots_dark");
  await expect(dotsDark).toHaveClass(/ring-2/);

  await saveAppearance(page, form);

  const bio = readBiolinkSettings();
  expect(bio.background_type).toBe("pattern");
  expect(bio.pattern_preset).toBe("pattern_dots_dark");
  expect(bio.bg_effect_colors).toEqual(["#111827", "#374151"]);

  // Public page inlines the pattern CSS (Dots Dark base color).
  const html = await gotoPublic(page);
  expect(html).toContain("#111827");
});

test("torn: quick combo sets colors + tear style via the window event, saves and renders", async ({ page }) => {
  test.setTimeout(300_000);
  await gotoAppearance(page);
  const form = page.locator('form[action*="page-settings"]');

  await pickType(form, "Torn Paper");
  await expect(form.locator('input[name="background_type"]')).toHaveValue("torn");

  // Preset labels repeat across panels — scope chips/combos to the torn panel.
  const panel = form.locator("div[x-show=\"bgType === 'torn'\"]");
  const styleHidden = form.locator('input[name="torn_style"]');
  // Fresh biolink → the default diagonal tear.
  await expect(styleHidden).toHaveValue("diagonal");

  // The "Mint" quick combo carries paper #e2f3e8, backdrop #69a888/#2f5d48
  // and tear style "deckled" — the style rides the torn-style-combo window
  // CustomEvent into the sibling Alpine scope that owns the hidden input.
  await panel.locator('button[title="Mint"]').click();
  await expect(styleHidden).toHaveValue("deckled");
  await expect(form.locator('input[name="torn_paper_color"]')).toHaveValue("#e2f3e8");
  await expect(form.locator('input[name="torn_backdrop_color"]')).toHaveValue("#69a888");
  await expect(form.locator('input[name="torn_backdrop_color2"]')).toHaveValue("#2f5d48");

  // A tear-style chip click also updates the hidden input directly; switch
  // to "Layered stack" then back to prove the chips work, ending on deckled.
  await panel.locator('button:text-is("Layered stack")').click();
  await expect(styleHidden).toHaveValue("stack");
  await panel.locator('button:text-is("Deckled frame")').click();
  await expect(styleHidden).toHaveValue("deckled");

  await saveAppearance(page, form);

  const bio = readBiolinkSettings();
  expect(bio.background_type).toBe("torn");
  expect(bio.torn_style).toBe("deckled");
  expect(bio.torn_paper_color).toBe("#e2f3e8");
  expect(bio.torn_backdrop_color).toBe("#69a888");
  expect(bio.torn_backdrop_color2).toBe("#2f5d48");

  // Public page renders the torn layers with the combo's backdrop gradient.
  const html = await gotoPublic(page);
  expect(html).toContain("bg-torn-paper");
  expect(html).toContain("bg-torn-backdrop");
  expect(html).toContain("#69a888");
});
