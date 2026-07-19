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

// Shared logged-in context (demo-login is rate-limited at throttle:5,1).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const ALIAS = "e2e-inline-edit";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

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
 * Seed the demo user plus a biolink with two top-level blocks and a card
 * container holding one child block, returning all the ids the spec needs.
 * Idempotent: prior blocks on the fixture biolink are wiped each run.
 */
function seedFixtures(): {
  linkId: number;
  blockA: number;
  blockB: number;
  cardId: number;
  childId: number;
} {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\DB;

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

$bio = Link::withoutGlobalScope('workspace')->where('alias', '${ALIAS}')->first();
if (!$bio) {
  $bio = Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => '${ALIAS}', 'title' => 'E2E Inline Edit', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id; $bio->save();
}

BiolinkBlock::where('link_id', $bio->id)->delete();
$a = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true, 'settings' => ['text' => 'Hello A']]);
$b = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'paragraph', 'sort_order' => 1, 'is_active' => true, 'settings' => ['text' => 'Body B']]);
$card = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'card', 'sort_order' => 2, 'is_active' => true, 'settings' => ['title' => 'Card C']]);
$child = BiolinkBlock::create(['link_id' => $bio->id, 'parent_id' => $card->id, 'type' => 'link', 'sort_order' => 0, 'is_active' => true, 'settings' => ['text' => 'Child D', 'url' => 'https://example.com']]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'blockA' => $a->id, 'blockB' => $b->id, 'cardId' => $card->id, 'childId' => $child->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: ReturnType<typeof seedFixtures>;

test.beforeAll(async ({ browser }) => {
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

function wrapSel(id: number): string {
  return `[data-inline-editor="${id}"]`;
}
function bodySel(id: number): string {
  return `[data-inline-editor-body="${id}"]`;
}
function editBtnSel(id: number): string {
  return `[data-block-id="${id}"] .edit-btn`;
}

async function gotoEditor(page: Page): Promise<void> {
  // The editor is a heavy Blade render over the distant RDS; a cold hit can
  // exceed the default nav budget, and third-party subresources can hold the
  // `load` event open. DOMContentLoaded + an explicit block-card wait is the
  // signal we actually need.
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });
}

test.describe.configure({ mode: "serial" });

test("edit expands inline below the block and loads the settings form", async ({
  page,
}) => {
  test.setTimeout(180_000);
  await gotoEditor(page);

  // No inline editor open and no legacy modal markup on the page.
  await expect(page.locator(".inline-block-editor.open")).toHaveCount(0);
  await expect(page.locator(".edit-modal-overlay")).toHaveCount(0);
  await expect(page.locator("#editDrawerContent")).toHaveCount(0);

  await page.click(editBtnSel(ids.blockA));
  const wrapA = page.locator(wrapSel(ids.blockA));
  await expect(wrapA).toBeVisible();
  // The AJAX editForm fetch lands a real form (with the block's settings)
  // inside the inline body — the same form the old modal used to host.
  await expect(page.locator(`${bodySel(ids.blockA)} form`)).toBeVisible({
    timeout: 20_000,
  });
  await expect(
    page.locator(`${bodySel(ids.blockA)} form input:not([type="hidden"]), ${bodySel(ids.blockA)} form textarea`).first(),
  ).toBeVisible();
});

test("only one inline editor is open at a time", async ({ page }) => {
  test.setTimeout(180_000);
  await gotoEditor(page);

  await page.click(editBtnSel(ids.blockA));
  await expect(page.locator(`${bodySel(ids.blockA)} form`)).toBeVisible({
    timeout: 20_000,
  });

  await page.click(editBtnSel(ids.blockB));
  await expect(page.locator(`${bodySel(ids.blockB)} form`)).toBeVisible({
    timeout: 20_000,
  });
  // Block A's editor collapsed and its body was emptied.
  await expect(page.locator(wrapSel(ids.blockA))).toBeHidden();
  await expect(page.locator(`${bodySel(ids.blockA)} form`)).toHaveCount(0);
});

test("edit button toggles: second click collapses the open editor", async ({
  page,
}) => {
  test.setTimeout(180_000);
  await gotoEditor(page);

  await page.click(editBtnSel(ids.blockA));
  await expect(page.locator(`${bodySel(ids.blockA)} form`)).toBeVisible({
    timeout: 20_000,
  });

  await page.click(editBtnSel(ids.blockA));
  await expect(page.locator(wrapSel(ids.blockA))).toBeHidden();

  // The header close (X) also collapses.
  await page.click(editBtnSel(ids.blockB));
  await expect(page.locator(`${bodySel(ids.blockB)} form`)).toBeVisible({
    timeout: 20_000,
  });
  await page.click(`${wrapSel(ids.blockB)} .inline-editor-head button`);
  await expect(page.locator(wrapSel(ids.blockB))).toBeHidden();
});

test("child blocks inside a card get their own inline editor", async ({
  page,
}) => {
  test.setTimeout(180_000);
  await gotoEditor(page);

  await page.click(editBtnSel(ids.childId));
  await expect(page.locator(wrapSel(ids.childId))).toBeVisible();
  await expect(page.locator(`${bodySel(ids.childId)} form`)).toBeVisible({
    timeout: 20_000,
  });
  // It lives inside the child card (inline, not an overlay).
  await expect(
    page.locator(
      `.child-block-card[data-block-id="${ids.childId}"] ${wrapSel(ids.childId)}`,
    ),
  ).toBeVisible();
});

test("autosave from the inline form shows the inline status and persists", async ({
  page,
}) => {
  test.setTimeout(180_000);
  await gotoEditor(page);

  await page.click(editBtnSel(ids.blockA));
  const form = page.locator(`${bodySel(ids.blockA)} form`);
  await expect(form).toBeVisible({ timeout: 20_000 });

  const textInput = form
    .locator('input[name="settings[text]"], textarea[name="settings[text]"]')
    .first();
  await expect(textInput).toBeVisible();
  await textInput.fill("Hello A edited");

  // Debounced autosave submits the form via fetch as POST (Laravel `_method`
  // spoofing handles the verb) and flips the per-block inline status through
  // "Saving..." to "Saved".
  await page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${ids.blockA}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 30_000 },
  );
  await expect(
    page.locator(`${wrapSel(ids.blockA)} .inline-autosave-status`),
  ).toContainText("Saved", { timeout: 15_000 });
});
