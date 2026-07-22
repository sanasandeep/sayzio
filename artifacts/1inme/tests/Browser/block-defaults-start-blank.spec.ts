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
import { loginAsDemo } from "./login-as-demo";
import { loginAsDemoAdmin } from "./login-as-demo-admin";

// End-to-end coverage for the admin "Start blank" block default:
//
//   1. The admin Block Defaults editor's friendly Content fields must two-way
//      sync with the JSON textarea (the textarea is the ONLY submitted content
//      field — the friendly inputs mutate an Alpine contentData object and
//      re-serialize into it, so a broken sync silently drops admin edits).
//   2. Toggling "Start blank" in the real admin UI and saving must persist the
//      flag into the block_defaults AppSetting store.
//   3. A user then adding a block of that type in the REAL biolink editor must
//      get a genuinely blank block: no seeded sample text in the edit drawer,
//      no "placeholder content" banner, and no sample-text fallback re-injected
//      on the public page render.
//
// Server-side semantics are already covered by feature tests
// (BlockDefaultsBlankContentTest); this spec exercises the browser layers that
// those cannot: the Alpine contentData<->JSON sync, the drawer render, and the
// public renderer's `?? ''` (not `?: 'Sample'`) fallbacks.
//
// Type under test: `paragraph` — its system default seeds sample text plus the
// `_placeholder` flag, so a start_blank regression is loudly visible.

const TYPE = "paragraph";
// The exact system sample text BlockDefaults seeds for a paragraph block. If
// blanking regresses, this string reappears in the drawer/public page.
const SAMPLE_TEXT =
  "Tell visitors a little about yourself or what this block is for.";
// The admin editor URL canonicalizes `paragraph` -> `paragraph_rich`
// (BlockTypeRegistry::canonical), whose single scalar content key is `html`
// with this system sample. Overrides saved there are shared with the raw
// `paragraph` type via the same canonical mapping — proven by the later tests
// (save on this page, then BlockDefaults::startBlankForType('paragraph') is
// true and a new `paragraph` block seeds blank).
const ADMIN_FIELD_KEY = "html";
const ADMIN_SAMPLE =
  "<p>Replace this with your own rich text. <strong>Bold</strong>, <em>italic</em>, and links all work.</p>";
// Per-run unique alias — fixed aliases collide across parallel task envs on
// the shared RDS (see repo memory).
const ALIAS = `e2e-bd-blank-${Date.now().toString(36)}`;

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

// Two authenticated contexts: the admin guard (block-defaults editor) and the
// web user guard (biolink editor + public page). Each test picks the page it
// needs via the fixtures below.
let adminContext: BrowserContext;
let userContext: BrowserContext;

const test = base.extend<{ adminPage: Page; userPage: Page }>({
  adminPage: async ({}, use) => {
    const page = await adminContext.newPage();
    await use(page);
    await page.close();
  },
  userPage: async ({}, use) => {
    const page = await userContext.newPage();
    await page.addInitScript(() => {
      (window as unknown as { __E2E__: boolean }).__E2E__ = true;
    });
    await use(page);
    await page.close();
  },
});

/** Run a tinker snippet, retrying transient distant-RDS connection blips. */
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
 * Idempotently ensure the demo admin + demo user exist, reset the paragraph
 * block-defaults override to a clean slate, prune stale aliases from earlier
 * runs, and create the biolink under test owned by the demo user.
 */
function seedFixtures(): number {
  const php = `
use App\\Modules\\Admin\\Models\\Admin;
use App\\Modules\\Admin\\Models\\Role;
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Services\\WorkspaceContext;
use App\\Modules\\User\\Support\\BlockDefaults;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

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

BlockDefaults::resetAdminOverrideForType('${TYPE}');

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

// Prune stale fixtures from earlier runs of this spec (per-run unique alias).
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', 'e2e-bd-blank-%')->get();
foreach ($stale as $s) {
  BiolinkBlock::withoutGlobalScope('workspace')->where('link_id', $s->id)->delete();
  $s->delete();
}

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Start Blank', 'is_active' => true,
]);

echo 'LINKID=' . $bio->id;
`.trim();

  const out = runTinker(php);
  const m = out.match(/LINKID=(\d+)/);
  if (!m) throw new Error("Start-blank seed failed, output:\n" + out);
  return Number(m[1]);
}

test.describe("block defaults — start blank end-to-end", () => {
  // Cold authenticated renders + writes over the distant RDS are slow.
  test.describe.configure({ timeout: 240_000 });

  let linkId: number;

  test.beforeAll(async ({ browser }) => {
    linkId = seedFixtures();
    adminContext = await browser.newContext();
    userContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await loginAsDemoAdmin(adminPage);
    await adminPage.close();
    const userPage = await userContext.newPage();
    await loginAsDemo(userPage);
    await userPage.close();
  });

  test.afterAll(async () => {
    // Leave no override or fixture behind for other suites/envs.
    try {
      runTinker(
        `
use App\\Modules\\User\\Support\\BlockDefaults;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\BiolinkBlock;
BlockDefaults::resetAdminOverrideForType('${TYPE}');
$bio = Link::withoutGlobalScope('workspace')->where('alias', '${ALIAS}')->first();
if ($bio) {
  BiolinkBlock::withoutGlobalScope('workspace')->where('link_id', $bio->id)->delete();
  $bio->delete();
}
echo 'CLEAN_OK';
`.trim(),
      );
    } catch {
      /* best-effort cleanup */
    }
    try { await adminContext?.close(); } catch {}
    try { await userContext?.close(); } catch {}
  });

  async function openAdminEditor(page: Page): Promise<void> {
    await page.goto(`/admin/block-defaults/${TYPE}`, { timeout: 120_000 });
    await page
      .locator('button[type="submit"]', { hasText: "Save overrides" })
      .waitFor({ state: "visible", timeout: 120_000 });
  }

  test("friendly Content fields two-way sync with the JSON textarea", async ({
    adminPage: page,
  }) => {
    await openAdminEditor(page);

    const friendly = page.locator(
      `[data-testid="content-field-${ADMIN_FIELD_KEY}"]`,
    );
    const json = page.locator('textarea[name="content_json"]');

    // With no override, the friendly field shows the system sample and the
    // JSON textarea is empty (empty === "use system defaults").
    await expect(friendly).toHaveValue(ADMIN_SAMPLE);
    await expect(json).toHaveValue("");

    // Field → JSON: typing into the friendly input must serialize into the
    // textarea (the ONLY submitted content field).
    await friendly.fill("Synced from field");
    await expect
      .poll(async () => {
        const raw = (await json.inputValue()).trim();
        if (raw === "") return null;
        try {
          return (JSON.parse(raw) as Record<string, unknown>)[ADMIN_FIELD_KEY];
        } catch {
          return `INVALID JSON: ${raw}`;
        }
      }, { message: "friendly field edit must serialize into content_json" })
      .toBe("Synced from field");

    // JSON → field: editing the textarea directly must update the friendly
    // input via the Alpine watcher. The raw-JSON section is collapsed by
    // default, so expand it first (fill() requires visibility).
    await page
      .getByRole("button", { name: /Content overrides \(JSON\)/ })
      .click();
    await expect(json).toBeVisible();
    await json.fill(JSON.stringify({ [ADMIN_FIELD_KEY]: "Synced from JSON" }));
    await expect(
      friendly,
      "JSON textarea edit must flow back into the friendly field",
    ).toHaveValue("Synced from JSON");

    // Clearing the JSON drops the override: the friendly field falls back to
    // the system sample again.
    await json.fill("");
    await expect(friendly).toHaveValue(ADMIN_SAMPLE);
  });

  test("toggling Start blank in the admin UI persists the flag", async ({
    adminPage: page,
  }) => {
    await openAdminEditor(page);

    const startBlank = page.locator('[data-testid="checkbox-start-blank"]');
    await expect(startBlank).not.toBeChecked();
    await startBlank.check();

    // Submit via the native form-submission path (requestSubmit) — the fixed
    // admin sidebar can intercept a pointer click on the scrolled-into-view
    // save button, and the debounced live preview keeps the layout "unstable"
    // for Playwright's actionability checks. Wait for the PUT's POST response:
    // the redirect target equals the current URL, so waitForURL is a no-op.
    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.request().method() === "POST" &&
          r.url().includes(`/admin/block-defaults/${TYPE}`) &&
          !r.url().includes("/preview"),
        { timeout: 120_000 },
      ),
      page.evaluate(() => {
        const form = Array.from(document.querySelectorAll("form")).find((f) =>
          f.querySelector('input[name="_method"][value="PUT"]'),
        );
        if (!form) throw new Error("PUT overrides form not found");
        form.requestSubmit();
      }),
    ]);
    await page.waitForLoadState("load", { timeout: 120_000 });
    await expect(
      page.locator("text=Block defaults saved").first(),
      "success banner after save",
    ).toBeVisible({ timeout: 60_000 });

    // Reloaded page reflects the persisted flag…
    await expect(
      page.locator('[data-testid="checkbox-start-blank"]'),
    ).toBeChecked();

    // …and the stored state + seeded settings are genuinely blank.
    const out = runTinker(
      `
use App\\Modules\\User\\Support\\BlockDefaults;
$seed = BlockDefaults::seededSettings('${TYPE}');
echo 'STATE<<<' . json_encode([
  'start_blank' => BlockDefaults::startBlankForType('${TYPE}'),
  'text' => $seed['text'] ?? null,
  'placeholder' => !empty($seed['_placeholder']),
]) . '>>>STATE';
`.trim(),
    );
    const m = out.match(/STATE<<<(.*)>>>STATE/s);
    if (!m) throw new Error("Could not read stored state:\n" + out);
    const state = JSON.parse(m[1]) as {
      start_blank: boolean;
      text: string | null;
      placeholder: boolean;
    };
    expect(state.start_blank, "start_blank flag persisted").toBe(true);
    expect(state.text, "seeded paragraph text blanked").toBe("");
    expect(state.placeholder, "no _placeholder on blank defaults").toBe(false);
  });

  test("a new block starts blank in the editor drawer and on the public page", async ({
    userPage: page,
  }) => {
    // Enforce the precondition directly (don't depend on the previous test's
    // UI save surviving a retry): start_blank ON for the type under test.
    runTinker(
      `use App\\Modules\\User\\Support\\BlockDefaults; BlockDefaults::saveAdminOverrideForType('${TYPE}', ['start_blank' => true]); echo 'SB_OK';`,
    );

    // Open the real biolink editor with the e2e hooks armed.
    await page.goto(`/user/links/${linkId}/blocks`, { timeout: 120_000 });
    await page.waitForFunction(
      () => !!(window as unknown as { __editorTest?: unknown }).__editorTest,
      undefined,
      { timeout: 120_000 },
    );

    // Add a paragraph block through the real palette onAdd pipeline
    // (BiolinkBlockController::store applies the seeded defaults).
    await page.evaluate(() =>
      (
        window as unknown as {
          __editorTest: {
            simulatePaletteDrop: (
              type: string,
              parent: number | null,
              index: number,
            ) => boolean;
          };
        }
      ).__editorTest.simulatePaletteDrop("paragraph", null, 0),
    );
    await expect(page.locator("#blockList > .block-card-wrapper")).toHaveCount(
      1,
      { timeout: 60_000 },
    );
    await expect(page.getByText("Block added").last()).toBeVisible({
      timeout: 60_000,
    });

    // Open the block's edit drawer (inline editor).
    await page
      .locator("#blockList > .block-card-wrapper .edit-btn")
      .first()
      .click();
    const form = page.locator(".block-settings-form");
    await expect(form).toBeVisible({ timeout: 60_000 });

    // The drawer really loaded the paragraph form (guards against a vacuous
    // pass where a load failure would also show no banner)…
    const textField = form.locator('textarea[name="settings[text]"]');
    await expect(textField).toBeVisible({ timeout: 30_000 });

    // …and it is genuinely blank: no placeholder banner, no sample text.
    await expect(
      form.locator(".placeholder-banner"),
      "no placeholder banner on a start-blank block",
    ).toHaveCount(0);
    await expect(
      textField,
      "paragraph text starts empty, not seeded sample copy",
    ).toHaveValue("");
    expect(
      await form.evaluate((el) => el.classList.contains("placeholder-mode")),
      "drawer must not be in placeholder-mode styling",
    ).toBe(false);

    // Public page: the block renders without any sample-text fallback
    // (`?? ''` semantics — a `?: 'Sample'` regression would re-inject copy).
    await page.goto(`/${ALIAS}`, { timeout: 120_000 });
    await expect(page.locator("body")).toBeVisible({ timeout: 60_000 });
    const bodyText = await page.locator("body").innerText();
    expect(
      bodyText,
      "public page must not re-inject the seeded sample paragraph",
    ).not.toContain(SAMPLE_TEXT);
  });
});
