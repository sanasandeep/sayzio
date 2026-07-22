import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemoAdmin } from "./login-as-demo-admin";

// Browser coverage for the redesigned admin Block Defaults editor
// (/admin/block-defaults/{type}) — the collapsible-section layout and the new
// section-level "clear" buttons.
//
// Why this exists: the redesign moved every style field inside Alpine
// x-collapse sections that start CLOSED when the section has no override. The
// controller (BlockDefaultsController::update) was untouched, but two
// regressions became possible purely in the view layer:
//   1. A field inside a COLLAPSED (x-show:false) section could stop being
//      serialized on submit (e.g. if a refactor swapped x-show for x-if or
//      moved inputs out of the <form>). Save would then silently drop values.
//   2. The "clear section" button (clearSection()) only mutates the Alpine
//      styleData object; the inputs' :value bindings must re-evaluate to empty
//      so that the subsequent submit posts empty strings and update() actually
//      REMOVES the stored override. If the binding breaks, clearing looks like
//      it worked but the override survives in the AppSetting.
//
// The spec drives the real flow against a representative type ("link"):
// expand Typography → set font_size → COLLAPSE the section again → save →
// assert the override is persisted in the `block_defaults.overrides`
// AppSetting → clear the section → assert the input emptied → save → assert
// the stored override is gone.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner boots
// its own ephemeral e2e server — see run-validation.sh).

const BLOCK_TYPE = "link";
// Distinctive per-run value so a stale row from another env can never satisfy
// the assertion by accident.
const FONT_SIZE = String(20 + (Date.now() % 60));

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

/**
 * Run a `php artisan tinker` snippet, retrying transient distant-RDS
 * connection blips (mirrors the sibling admin specs).
 */
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

/**
 * Idempotently ensure the demo admin exists/is active (so /admin/demo-login
 * works) and start from a CLEAN slate for the block type under test — the
 * spec's save/clear assertions assume no pre-existing override for it.
 */
function seedFixtures(): void {
  const php = `
use App\\Modules\\Admin\\Models\\Admin;
use App\\Modules\\Admin\\Models\\Role;
use App\\Modules\\User\\Support\\BlockDefaults;
use Illuminate\\Support\\Facades\\Hash;

$role = Role::firstOrCreate(
  ['slug' => 'super-admin'],
  ['name' => 'Super Admin', 'guard' => 'admin']
);
$a = Admin::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$a) {
  $a = Admin::create([
    'name' => 'Admin', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'role_id' => $role->id,
    'status' => 'active',
  ]);
}
$a->status = 'active';
if (!$a->role_id) { $a->role_id = $role->id; }
$a->save();

BlockDefaults::resetAdminOverrideForType('${BLOCK_TYPE}');

echo 'SEED_OK';
`.trim();

  const out = runTinker(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Block-defaults seed failed, output:\n" + out);
  }
}

/**
 * Read the CURRENT stored admin override for the block type straight from the
 * AppSetting-backed store — the source of truth the task cares about.
 */
function readStoredOverride(): Record<string, unknown> {
  const php = `
use App\\Modules\\User\\Support\\BlockDefaults;
echo 'OVR<<<' . json_encode(BlockDefaults::getAdminOverrideForType('${BLOCK_TYPE}')) . '>>>OVR';
`.trim();
  const out = runTinker(php);
  const m = out.match(/OVR<<<(.*)>>>OVR/s);
  if (!m) {
    throw new Error("Could not read stored block-defaults override:\n" + out);
  }
  const parsed = JSON.parse(m[1]);
  // json_encode of an empty PHP array is `[]`
  return Array.isArray(parsed) ? {} : (parsed as Record<string, unknown>);
}

async function openEditor(page: Page): Promise<void> {
  await page.goto(`/admin/block-defaults/${BLOCK_TYPE}`, { timeout: 120_000 });
  await page
    .locator('button[type="submit"]', { hasText: "Save overrides" })
    .waitFor({ state: "visible", timeout: 120_000 });
}

/**
 * Submit the main overrides form through the browser's NATIVE submission path
 * (requestSubmit with the "Save overrides" button as submitter). This is the
 * same serialization a user click triggers, but avoids the pointer-level
 * flake: the admin layout's fixed sidebar can overlap the button's
 * scrolled-into-view point and intercept the click, and the debounced live
 * preview keeps re-rendering, tripping Playwright's stability checks.
 */
async function submitSaveForm(page: Page): Promise<void> {
  // Tag the CURRENT document, submit, then wait for a document WITHOUT the
  // tag. The PUT redirects back to the same URL, so waitForURL resolves
  // immediately, and waitForResponse is ambiguous (the debounced live-preview
  // also POSTs under /admin/block-defaults/{type}/preview). A fresh document
  // is the only unambiguous "save round-trip finished" signal.
  await page.evaluate(() => {
    (window as unknown as { __bdPreSubmit?: boolean }).__bdPreSubmit = true;
  });
  await page.evaluate(() => {
    const forms = Array.from(document.querySelectorAll("form"));
    const form = forms.find((f) =>
      f.querySelector('input[name="_method"][value="PUT"]'),
    );
    if (!form) {
      throw new Error(
        `PUT overrides form not found; forms on page: ${forms
          .map((f) => f.getAttribute("action") || "(no action)")
          .join(", ")}`,
      );
    }
    // Sanity: the typography field must serialize with this form, otherwise
    // the DOM got restructured (e.g. by invalid markup) and a real user's
    // save would silently drop fields.
    const fontSize = document.querySelector<HTMLInputElement>(
      'input[name="style[font_size]"]',
    );
    if (!fontSize) throw new Error("style[font_size] input not found");
    if (fontSize.form !== form) {
      const chain: string[] = [];
      let el: Element | null = fontSize;
      while (el && chain.length < 12) {
        chain.push(el.tagName + (el.className ? `.${el.className}` : ""));
        el = el.parentElement;
      }
      throw new Error(
        `style[font_size] is NOT associated with the PUT form — DOM was ` +
          `restructured by the parser. Ancestor chain: ${chain.join(" > ")}`,
      );
    }
    form.requestSubmit();
  });
  // Cold first write over the distant RDS can be slow.
  await page.waitForFunction(
    () => !(window as unknown as { __bdPreSubmit?: boolean }).__bdPreSubmit,
    undefined,
    { timeout: 120_000 },
  );
  await page.waitForLoadState("load", { timeout: 120_000 });
}

/** The Typography section card (the .bd-card whose header carries the title). */
function typographyCard(page: Page) {
  return page
    .locator(".bd-card", {
      has: page.locator(".bd-section-title", { hasText: "Typography" }),
    })
    .first();
}

test.describe("admin block-defaults editor save/clear", () => {
  // Cold authenticated renders + form POSTs over the distant RDS are slow.
  test.describe.configure({ timeout: 240_000 });

  test.beforeAll(async ({ browser }) => {
    seedFixtures();
    sharedContext = await browser.newContext();
    const page = await sharedContext.newPage();
    await loginAsDemoAdmin(page);
    await page.close();
  });

  test.afterAll(async () => {
    // Leave no override behind for other suites/envs.
    try {
      runTinker(
        `use App\\Modules\\User\\Support\\BlockDefaults; BlockDefaults::resetAdminOverrideForType('${BLOCK_TYPE}'); echo 'CLEAN_OK';`,
      );
    } catch {
      /* best-effort cleanup */
    }
    try { await sharedContext?.close(); } catch {}
  });

  test("save persists a field from a collapsed section, clear-section empties it, and re-save removes the stored override", async ({
    page,
  }) => {
    await openEditor(page);

    const card = typographyCard(page);
    const header = card.locator("button.bd-section-hd");
    const fontSize = page.locator('input[name="style[font_size]"]');

    // ── 1. With no override, Typography starts COLLAPSED: field not visible.
    await expect(fontSize).toBeHidden();

    // Expand the section and set a value.
    await header.click();
    await expect(fontSize).toBeVisible();
    await fontSize.fill(FONT_SIZE);

    // The section-level "overrides" badge and clear button appear live.
    await expect(card.locator(".bd-badge")).toBeVisible();
    await expect(card.locator(".bd-clear-btn")).toBeVisible();

    // ── 2. Collapse the section AGAIN before submitting — the redesign's key
    // risk: a hidden (x-show:false) input must still serialize on submit.
    await header.click();
    await expect(fontSize).toBeHidden();

    // Submit via the native form submission path (requestSubmit with the save
    // button as submitter) rather than a pointer click: the admin layout's
    // fixed sidebar can overlap the button's scrolled-into-view position and
    // intercept pointer events, and the live-preview updates keep the layout
    // "unstable" for Playwright's actionability checks. requestSubmit()
    // exercises the exact same browser form serialization the click would.
    // NOTE: the redirect target equals the CURRENT url, so waitForURL would
    // resolve immediately and race the actual write. Wait for the PUT's POST
    // response (cold first write over the distant RDS can be slow) and the
    // follow-up page load instead.
    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.request().method() === "POST" &&
          r.url().includes(`/admin/block-defaults/${BLOCK_TYPE}`),
        { timeout: 120_000 },
      ),
      submitSaveForm(page),
    ]);
    await page.waitForLoadState("load", { timeout: 120_000 });
    await expect(
      page.locator("text=Block defaults saved").first(),
      "success banner after save",
    ).toBeVisible({ timeout: 60_000 });

    // The override actually landed in the AppSetting store.
    const afterSave = readStoredOverride();
    const savedStyle = (afterSave.style ?? {}) as Record<string, unknown>;
    expect(
      savedStyle.font_size,
      "style.font_size override must be persisted even though its section was collapsed at submit time",
    ).toBe(FONT_SIZE);

    // ── 3. After reload the section auto-opens (it now has an override) and
    // shows the saved value + clear affordance.
    const card2 = typographyCard(page);
    await expect(fontSize).toBeVisible();
    await expect(fontSize).toHaveValue(FONT_SIZE);
    const clearBtn = card2.locator(".bd-clear-btn");
    await expect(clearBtn).toBeVisible();

    // "Clear section" must empty the bound input (Alpine clearSection() →
    // :value re-evaluation) and drop the badge/clear affordances.
    await clearBtn.click();
    await expect(
      fontSize,
      "font_size input must empty when its section is cleared",
    ).toHaveValue("");
    await expect(card2.locator(".bd-badge")).toBeHidden();
    await expect(card2.locator(".bd-clear-btn")).toBeHidden();

    // ── 4. Submit the cleared form: the stored override must be REMOVED.
    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.request().method() === "POST" &&
          r.url().includes(`/admin/block-defaults/${BLOCK_TYPE}`),
        { timeout: 120_000 },
      ),
      submitSaveForm(page),
    ]);
    await page.waitForLoadState("load", { timeout: 120_000 });
    await expect(page.locator("text=Block defaults saved").first()).toBeVisible({
      timeout: 60_000,
    });

    const afterClear = readStoredOverride();
    const clearedStyle = (afterClear.style ?? {}) as Record<string, unknown>;
    expect(
      clearedStyle.font_size,
      "clearing the section then saving must remove the stored font_size override",
    ).toBeUndefined();
  });

  // Regression: the section headers used to nest the "clear" <button> inside
  // the header <button>. Nested buttons are invalid HTML — the parser
  // force-closes the outer button, misnesting the whole tree so every card
  // after "Layout & Display" gets ejected from the form column ("floating"
  // Border panel). Separately, the `.bd-select` rules used the `background:`
  // shorthand, which resets background-repeat while the admin layout's
  // higher-specificity chevron background-image survives → a strip of tiled
  // chevrons on every select (worst in light mode on a white background).
  test("layout stays intact and selects render a single caret in both themes", async ({
    page,
  }) => {
    await openEditor(page);

    // No <button> may end up nested inside another button in the LIVE DOM.
    const nestedButtons = await page
      .locator("button button")
      .count();
    expect(nestedButtons, "no nested <button> elements").toBe(0);

    const auditLayout = async () =>
      page.evaluate(() => {
        const formCol = document.querySelector(".bd-form-col");
        if (!formCol) return { escaped: ["<no .bd-form-col>"], repeat: "" };
        const escaped = Array.from(document.querySelectorAll(".bd-card"))
          .filter((c) => !formCol.contains(c))
          .map(
            (c) =>
              c.querySelector(".bd-section-title")?.textContent?.trim() ??
              "(untitled card)",
          );
        const sel = document.querySelector<HTMLSelectElement>(
          'select[name="style[display_mode]"]',
        );
        const cs = sel ? getComputedStyle(sel) : null;
        return {
          escaped,
          repeat: cs?.backgroundRepeat ?? "",
          images: cs ? cs.backgroundImage.split("url(").length - 1 : -1,
        };
      });

    for (const theme of ["dark", "light"] as const) {
      await page.evaluate((t) => {
        document.documentElement.classList.toggle("light-mode", t === "light");
      }, theme);
      const audit = await auditLayout();
      expect(
        audit.escaped,
        `every section card must live inside .bd-form-col (${theme} mode)`,
      ).toEqual([]);
      expect(
        audit.repeat,
        `display-mode select chevron must not tile (${theme} mode)`,
      ).toBe("no-repeat");
      expect(
        audit.images,
        `display-mode select must carry exactly one background image (${theme} mode)`,
      ).toBe(1);
    }
  });
});
