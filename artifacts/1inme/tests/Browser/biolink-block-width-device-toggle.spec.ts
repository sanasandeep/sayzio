import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type FrameLocator,
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

// Per-run unique alias — the RDS is shared across parallel task environments
// (see biolink-block-live-preview.spec.ts for the full rationale).
const ALIAS = `e2e-width-dev-${Date.now().toString(36)}${process.pid.toString(36)}`;

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

/** Seed the demo user plus a biolink with a single heading block. */
function seedFixtures(): { linkId: number; headingId: number } {
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

// Prune stale fixture links from previous runs (>2h so a concurrently
// running suite in another environment is never torn down mid-run).
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', 'e2e-width-dev-%')
  ->where('created_at', '<', now()->subHours(2))
  ->pluck('id');
if ($stale->isNotEmpty()) {
  BiolinkBlock::whereIn('link_id', $stale)->delete();
  Link::withoutGlobalScope('workspace')->whereIn('id', $stale)->delete();
}

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Width Device', 'is_active' => true,
]);
$h = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'heading', 'sort_order' => 0, 'is_active' => true, 'settings' => ['text' => 'Width Toggle H']]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'headingId' => $h->id]);
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/IDS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]);
}

let ids: ReturnType<typeof seedFixtures>;

test.beforeAll(async ({ browser }) => {
  test.setTimeout(240_000);
  ids = seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page
    .waitForLoadState("load", { timeout: 90_000 })
    .catch(() => undefined);
  // Warm the public biolink page once (cold render over the distant RDS).
  await page.goto(`/${ALIAS}`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.close();
});

test.afterAll(async () => {
  try { await sharedContext?.close(); } catch {}
});

async function gotoEditorWithPreview(
  page: Page,
): Promise<{ preview: FrameLocator }> {
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });

  const preview = page.frameLocator(".preview-iframe").first();
  await expect(
    preview.locator(`[data-block-id="${ids.headingId}"]`),
  ).toBeVisible({ timeout: 60_000 });

  // Reload detection: stamp the CURRENT iframe document. Any full reload
  // produces a fresh document without the stamp.
  await preview
    .locator("body")
    .evaluate((b) => b.setAttribute("data-e2e-live-marker", "1"));

  return { preview };
}

async function expectNoReload(preview: FrameLocator): Promise<void> {
  await expect(
    preview.locator('body[data-e2e-live-marker="1"]'),
  ).toBeVisible();
}

async function openDrawer(page: Page, blockId: number) {
  const form = page.locator(`[data-inline-editor-body="${blockId}"] form`);
  for (let attempt = 1; attempt <= 3; attempt++) {
    await page.click(`[data-block-id="${blockId}"] .edit-btn`);
    try {
      await expect(form).toBeVisible({ timeout: 20_000 });
      break;
    } catch (err) {
      if (attempt === 3) throw err;
    }
  }
  // Give _initDrawerAutoSave's 100ms baseline capture a moment to run.
  await page.waitForTimeout(400);
  return form;
}

/** Open Block Styling → Layout tab and return the width-device pieces. */
async function openLayoutTab(form: ReturnType<Page["locator"]>) {
  const styleRoot = form.locator("[data-style-root]");
  await styleRoot.locator('button:has-text("Block Styling")').click();
  await styleRoot.locator('button:has-text("Layout")').click();
  const deviceToggle = styleRoot.locator("[data-width-device-toggle]");
  await expect(deviceToggle).toBeVisible({ timeout: 15_000 });
  return { styleRoot, deviceToggle };
}

function mdChip(form: ReturnType<Page["locator"]>, value: string) {
  return form.locator(
    `label:has(input[name="style[grid_span_md]"][value="${value}"])`,
  );
}

function waitForAutosave(page: Page, blockId: number) {
  return page.waitForResponse(
    (r) =>
      r.url().includes(`/blocks/${blockId}`) &&
      r.request().method() === "POST" &&
      r.ok(),
    { timeout: 60_000 },
  );
}

test.describe.configure({ mode: "serial" });
test.setTimeout(180_000);

test("desktop width chip patches md-span on the preview wrap live, persists after save/reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.headingId);
  const { deviceToggle } = await openLayoutTab(form);

  const wrap = preview.locator(`[data-block-id="${ids.headingId}"]`);

  // The block starts with NO desktop override.
  await expect(async () => {
    const cls = (await wrap.getAttribute("class")) || "";
    expect(cls).not.toContain("md-span");
  }).toPass({ timeout: 10_000 });

  // Mobile segment is active by default; the desktop chips are hidden.
  await expect(mdChip(form, "6")).toBeHidden();

  // Arm the autosave listener BEFORE the change (debounced POST can land
  // while the live-patch assertion below is still polling).
  const savePromise = waitForAutosave(page, ids.headingId);

  // Switch to the Desktop segment and pick the ½ (6-of-12) chip.
  await deviceToggle.locator('button:has-text("Desktop")').click();
  await expect(mdChip(form, "6")).toBeVisible({ timeout: 10_000 });
  await mdChip(form, "6").click();

  // The live channel must toggle the class + var in place, no reload.
  await expect(async () => {
    const state = await wrap.evaluate((el) => ({
      cls: el.className,
      varVal: (el as HTMLElement).style.getPropertyValue("--md-span"),
    }));
    expect(state.cls).toContain("md-span");
    expect(state.varVal).toBe("6");
  }).toPass({ timeout: 10_000 });
  await expectNoReload(preview);

  await savePromise;
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);

  // Persistence: a fresh editor load must show the chip selected and the
  // server-rendered preview wrap carrying md-span + --md-span.
  const { preview: preview2 } = await gotoEditorWithPreview(page);
  const form2 = await openDrawer(page, ids.headingId);
  await openLayoutTab(form2);
  await expect(
    form2.locator('input[name="style[grid_span_md]"][value="6"]'),
  ).toBeChecked();

  const wrap2 = preview2.locator(`[data-block-id="${ids.headingId}"]`);
  await expect(async () => {
    const state = await wrap2.evaluate((el) => ({
      cls: el.className,
      style: el.getAttribute("style") || "",
    }));
    expect(state.cls).toContain("md-span");
    expect(state.style).toContain("--md-span:6");
  }).toPass({ timeout: 15_000 });
});

test("'Same' clears the desktop override live and after save/reload", async ({
  page,
}) => {
  const { preview } = await gotoEditorWithPreview(page);
  const form = await openDrawer(page, ids.headingId);
  const { deviceToggle } = await openLayoutTab(form);

  const wrap = preview.locator(`[data-block-id="${ids.headingId}"]`);

  // The override from the previous test is server-rendered on the wrap.
  await expect(async () => {
    const cls = (await wrap.getAttribute("class")) || "";
    expect(cls).toContain("md-span");
  }).toPass({ timeout: 10_000 });

  const savePromise = waitForAutosave(page, ids.headingId);

  await deviceToggle.locator('button:has-text("Desktop")').click();
  const sameChip = mdChip(form, "");
  await expect(sameChip).toBeVisible({ timeout: 10_000 });
  await sameChip.click();

  // Live patch: class + var removed in place, no reload.
  await expect(async () => {
    const state = await wrap.evaluate((el) => ({
      cls: el.className,
      varVal: (el as HTMLElement).style.getPropertyValue("--md-span"),
    }));
    expect(state.cls).not.toContain("md-span");
    expect(state.varVal).toBe("");
  }).toPass({ timeout: 10_000 });
  await expectNoReload(preview);

  await savePromise;
  await page.waitForTimeout(2_000);
  await expectNoReload(preview);

  // Persistence: fresh load shows "Same" selected and NO md-span on the wrap.
  const { preview: preview2 } = await gotoEditorWithPreview(page);
  const form2 = await openDrawer(page, ids.headingId);
  await openLayoutTab(form2);
  await expect(
    form2.locator('input[name="style[grid_span_md]"][value=""]'),
  ).toBeChecked();

  const wrap2 = preview2.locator(`[data-block-id="${ids.headingId}"]`);
  await expect(async () => {
    const state = await wrap2.evaluate((el) => ({
      cls: el.className,
      style: el.getAttribute("style") || "",
    }));
    expect(state.cls).not.toContain("md-span");
    expect(state.style).not.toContain("--md-span");
  }).toPass({ timeout: 15_000 });
});
