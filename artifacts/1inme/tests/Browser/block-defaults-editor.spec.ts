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
function readStoredOverride(type: string = BLOCK_TYPE): Record<string, unknown> {
  const php = `
use App\\Modules\\User\\Support\\BlockDefaults;
echo 'OVR<<<' . json_encode(BlockDefaults::getAdminOverrideForType('${type}')) . '>>>OVR';
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
        `use App\\Modules\\User\\Support\\BlockDefaults; BlockDefaults::resetAdminOverrideForType('${BLOCK_TYPE}'); BlockDefaults::resetAdminOverrideForType('list'); echo 'CLEAN_OK';`,
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

  // The Typography "Font family" field is the shared searchable font picker
  // (not a free-text input). Verify the full wiring: opening the picker,
  // searching, selecting a font mirrors into the hidden style[font_family]
  // input AND fires the bubbling change event that flips the section's
  // override badge; "clear section" resets the picker back to Inherit via the
  // font-picker-set window event. Admin surface must NOT show "My Fonts".
  test("font family uses the searchable picker wired to setStyle and clear-section", async ({
    page,
  }) => {
    await openEditor(page);

    const card = typographyCard(page);
    const header = card.locator("button.bd-section-hd");
    const picker = page.locator('.font-picker[data-picker-id="bdFontFamily"]');
    const hidden = picker.locator('input[name="style[font_family]"]');

    // Expand Typography; the picker trigger (not a free-text input) renders.
    await header.click();
    await expect(picker).toBeVisible();
    expect(
      await page.locator('input[type="text"][name="style[font_family]"]').count(),
      "the old free-text font input must be gone",
    ).toBe(0);

    // Open the picker and verify admin guard hides My Fonts entirely.
    await picker.locator("button.theme-input").click();
    await expect(picker.locator('input[placeholder="Search fonts…"]')).toBeVisible();
    await expect(picker.locator("text=My Fonts")).toHaveCount(0);

    // Search narrows the list; select a real Google Font.
    await picker.locator('input[placeholder="Search fonts…"]').fill("Lobster");
    const option = picker.locator("span", { hasText: /^Lobster$/ }).first();
    await option.click();

    // Hidden input mirrors the pick and the section badge flips on
    // (select() dispatches a bubbling change that the wrapper routes to
    // setStyle).
    await expect(hidden).toHaveValue("Lobster");
    await expect(card.locator(".bd-badge")).toBeVisible();
    const clearBtn = card.locator(".bd-clear-btn");
    await expect(clearBtn).toBeVisible();

    // The picker loaded the Google Font stylesheet for the preview.
    const fontLinks = await page
      .locator('link[href*="fonts.googleapis.com"][href*="Lobster"]')
      .count();
    expect(fontLinks, "selected Google Font stylesheet loaded").toBeGreaterThan(0);

    // Clear section resets the picker to Inherit (font-picker-set event) and
    // empties the hidden input so a save would drop the override.
    await clearBtn.click();
    await expect(hidden).toHaveValue("");
    await expect(card.locator(".bd-badge")).toBeHidden();
    await expect(
      picker.locator("button.theme-input span", { hasText: "Inherit" }).first(),
    ).toBeVisible();
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

  // Coverage for the friendly array-content row editor (Task: array-of-strings
  // and array-of-objects content keys get add/remove/reorder rows that stay in
  // two-way sync with the JSON textarea, and emptying all rows saves an
  // explicit []).
  test("list row editor two-way syncs with the JSON textarea and saving zero rows stores an explicit empty list", async ({
    page,
  }) => {
    const LIST_TYPE = "list";
    runTinker(
      `use App\\Modules\\User\\Support\\BlockDefaults; BlockDefaults::resetAdminOverrideForType('${LIST_TYPE}'); echo 'RESET_OK';`,
    );

    await page.goto(`/admin/block-defaults/${LIST_TYPE}`, { timeout: 120_000 });
    await page
      .locator('button[type="submit"]', { hasText: "Save overrides" })
      .waitFor({ state: "visible", timeout: 120_000 });

    const container = page.locator('[data-testid="content-list-items"]');
    await expect(container).toBeVisible();
    const rows = container.locator(".space-y-2 > div");
    // System default seeds three list items.
    await expect(rows).toHaveCount(3);

    const jsonBox = page.locator('textarea[name="content_json"]');

    // ── rows → JSON: editing a row must land in the JSON textarea.
    const uniqueText = `Row edit ${Date.now()}`;
    await rows.nth(0).locator('input[type="text"]').first().fill(uniqueText);
    await expect(jsonBox).toHaveValue(new RegExp(uniqueText));

    // ── JSON → rows: editing the JSON must rebuild the rows. The JSON
    // section is collapsed by default, so expand it before filling.
    await page
      .locator("button.bd-section-hd", { hasText: "Content overrides (JSON)" })
      .click();
    await expect(jsonBox).toBeVisible();
    await jsonBox.fill(
      JSON.stringify({ items: [{ text: "FromJsonSync", icon: "" }] }, null, 2),
    );
    await expect(rows).toHaveCount(1);
    await expect(rows.nth(0).locator('input[type="text"]').first()).toHaveValue(
      "FromJsonSync",
    );

    // ── add + remove rows: removing every row shows the empty hint and the
    // JSON keeps an explicit [] for the key.
    await page.locator('[data-testid="list-add-items"]').click();
    await expect(rows).toHaveCount(2);
    const removeButtons = container.locator('button[title="Remove item"]');
    while ((await removeButtons.count()) > 0) {
      await removeButtons.first().click();
    }
    await expect(rows).toHaveCount(0);
    await expect(
      container.locator("text=saving keeps this list explicitly empty"),
    ).toBeVisible();
    await expect(jsonBox).toHaveValue(/"items":\s*\[\]/);

    // ── save: the stored override must carry the explicit empty list.
    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.request().method() === "POST" &&
          r.url().includes(`/admin/block-defaults/${LIST_TYPE}`),
        { timeout: 120_000 },
      ),
      submitSaveForm(page),
    ]);
    await page.waitForLoadState("load", { timeout: 120_000 });
    await expect(page.locator("text=Block defaults saved").first()).toBeVisible({
      timeout: 60_000,
    });

    const stored = readStoredOverride(LIST_TYPE);
    const content = (stored.content ?? {}) as Record<string, unknown>;
    expect(
      content.items,
      "saving with zero rows must persist an explicit empty items list",
    ).toEqual([]);
  });

  // Coverage for drag-and-drop row reordering (Task: rows can be dragged to
  // reorder, not just moved via the arrow buttons; JSON textarea sync
  // unchanged). Native HTML5 drag events don't fire from Playwright mouse
  // moves, so the test dispatches real DragEvents with a shared DataTransfer —
  // the exact events the Alpine handlers (@dragstart/@dragover/@drop) listen
  // for.
  test("list rows reorder via drag-and-drop and the JSON textarea reflects the new order", async ({
    page,
  }) => {
    const LIST_TYPE = "list";
    runTinker(
      `use App\\Modules\\User\\Support\\BlockDefaults; BlockDefaults::resetAdminOverrideForType('${LIST_TYPE}'); echo 'RESET_OK';`,
    );

    await page.goto(`/admin/block-defaults/${LIST_TYPE}`, { timeout: 120_000 });
    await page
      .locator('button[type="submit"]', { hasText: "Save overrides" })
      .waitFor({ state: "visible", timeout: 120_000 });

    const container = page.locator('[data-testid="content-list-items"]');
    await expect(container).toBeVisible();
    const rows = container.locator(".space-y-2 > div");
    await expect(rows).toHaveCount(3);

    // Every row exposes a drag handle.
    await expect(container.locator('[data-testid="list-drag-items"]')).toHaveCount(3);

    // Capture the seeded row texts so we can assert the exact permutation.
    const readRowTexts = async (): Promise<string[]> => {
      const n = await rows.count();
      const texts: string[] = [];
      for (let i = 0; i < n; i++) {
        texts.push(
          await rows.nth(i).locator('input[type="text"]').first().inputValue(),
        );
      }
      return texts;
    };
    const before = await readRowTexts();
    expect(new Set(before).size, "seeded rows must be distinct").toBe(3);

    // Drag the LAST row's handle onto the FIRST row.
    const diag = await page.evaluate(() => {
      const container = document.querySelector(
        '[data-testid="content-list-items"]',
      )!;
      const rowEls = container.querySelectorAll(
        ":scope .space-y-2 > div",
      ) as NodeListOf<HTMLElement>;
      const handle = rowEls[2].querySelector(
        '[data-testid="list-drag-items"]',
      ) as HTMLElement;
      const dt = new DataTransfer();
      const fire = (target: Element, type: string) =>
        target.dispatchEvent(
          new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: dt }),
        );
      const alpine = (window as unknown as {
        Alpine?: { $data: (el: Element) => Record<string, unknown> };
      }).Alpine;
      const data = alpine ? alpine.$data(container) : null;
      fire(handle, "dragstart");
      const afterStart = data ? JSON.stringify(data.listDrag) : "no-alpine";
      fire(rowEls[0], "dragover");
      fire(rowEls[0], "drop");
      const afterDrop = data
        ? JSON.stringify((data.contentData as Record<string, unknown>).items)
        : "no-alpine";
      fire(handle, "dragend");
      return { afterStart, afterDrop, rowCount: rowEls.length };
    });
    // Fail loudly if dragstart never reached the Alpine handler (would
    // otherwise surface as a confusing "order unchanged" poll timeout).
    expect(diag.afterStart, "dragstart must register in listDrag").toContain(
      '"from":2',
    );

    // Rows now read [c, a, b] and the JSON textarea mirrors the same order.
    await expect
      .poll(readRowTexts, { timeout: 10_000 })
      .toEqual([before[2], before[0], before[1]]);

    const jsonBox = page.locator('textarea[name="content_json"]');
    const jsonVal = await jsonBox.inputValue();
    const parsed = JSON.parse(jsonVal) as {
      items: Array<{ text: string }>;
    };
    expect(
      parsed.items.map((i) => i.text),
      "JSON textarea must carry the dragged order",
    ).toEqual([before[2], before[0], before[1]]);
  });
});
