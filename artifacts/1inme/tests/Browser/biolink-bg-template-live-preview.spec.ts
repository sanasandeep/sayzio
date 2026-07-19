import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Frame,
  type Page,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

// Shared logged-in context (demo-login is throttled at 5/min).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const ALIAS = "e2e-bg-tpl-live-preview";

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
 * Seed the demo user + a plain color-background biolink, and report the id of
 * the first two active bg templates so the spec can click a *specific* thumb
 * and assert the preview shows exactly that template's slug.
 */
function seedFixture(): { linkId: number; tplId: number; tplSlug: string } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\Admin\\Models\\BgTemplate;
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
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
$ws = app(WorkspaceContext::class)->resolve($u);

$bio = Link::withoutGlobalScope('workspace')->where('alias', '${ALIAS}')->first();
if (!$bio) {
  $bio = Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => '${ALIAS}', 'title' => 'E2E BG Template Live Preview', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id;
}
// Reset to a plain color background so the draft change is unambiguous.
$s = $bio->settings ?? [];
$b = $s['biolink'] ?? [];
$b['background_type'] = 'color';
$b['background_color'] = '#111827';
unset($b['bg_template_id']);
$s['biolink'] = $b;
$bio->settings = $s;
$bio->save();

$tpl = BgTemplate::where('is_active', true)->orderBy('id')->first();

echo 'LINKID=' . $bio->id . ' TPLID=' . $tpl->id . ' TPLSLUG=' . $tpl->slug;
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/LINKID=(\d+) TPLID=(\d+) TPLSLUG=([a-z0-9\-]+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return { linkId: Number(m[1]), tplId: Number(m[2]), tplSlug: m[3] };
}

function findPreviewFrame(page: Page): Frame | null {
  for (const frame of page.frames()) {
    if (frame.url().includes(`/${ALIAS}?`) || frame.url().includes(`/${ALIAS}&`))
      return frame;
    if (frame.url().includes(ALIAS)) return frame;
  }
  return null;
}

test.describe.configure({ mode: "serial" });

let fixture: { linkId: number; tplId: number; tplSlug: string };

test.beforeAll(async ({ browser }) => {
  // Seeding goes through tinker over a distant RDS — keep it out of the
  // per-test timeout budget. When the fixture was pre-seeded externally the
  // ids can be injected via env to skip tinker entirely.
  if (
    process.env.E2E_BGTPL_LINKID &&
    process.env.E2E_BGTPL_TPLID &&
    process.env.E2E_BGTPL_TPLSLUG
  ) {
    fixture = {
      linkId: Number(process.env.E2E_BGTPL_LINKID),
      tplId: Number(process.env.E2E_BGTPL_TPLID),
      tplSlug: process.env.E2E_BGTPL_TPLSLUG,
    };
  } else {
    fixture = seedFixture();
  }
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  try { await sharedContext?.close(); } catch {}
});

test("clicking a background template updates the live phone preview without saving", async ({
  page,
}) => {
  // Cold first navigation to the heavy appearance editor over the distant RDS
  // can eat most of the default 60s budget before the iframe even mounts.
  test.setTimeout(180_000);
  const { linkId, tplId, tplSlug } = fixture;

  await page.goto(`/user/links/${linkId}/settings/appearance`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });

  // Wait for the phone preview iframe to load the signed public page.
  await expect
    .poll(() => findPreviewFrame(page) !== null, { timeout: 60_000 })
    .toBe(true);

  // Switch the background type to "Template".
  const templateTab = page
    .locator('button:has-text("Template")')
    .first();
  await templateTab.waitFor({ state: "visible", timeout: 30_000 });
  await templateTab.click();

  // Click the specific seeded template thumb (hidden radio inside a label).
  // The background card partial can render more than once on the page
  // (desktop + duplicated pane) — scope to the first instance.
  const radio = page
    .locator(`input[type="radio"][name="bg_template_id"][value="${tplId}"]`)
    .first();
  await radio.waitFor({ state: "attached", timeout: 30_000 });
  // Register the response listener BEFORE clicking so the debounced draft
  // push (500ms) can never race past us, then click the wrapping label
  // (the radio itself is visually hidden).
  const draftPush = page.waitForResponse(
    (r) =>
      r.url().includes("/preview-draft") && r.request().method() === "POST",
    { timeout: 30_000 },
  );
  await radio.evaluate((el) => (el.closest("label") as HTMLElement).click());
  await expect(radio).toBeChecked();

  // The draft push should fire and reload the iframe with _draft=1; the
  // reloaded public page must contain the template's bg layer.
  const resp = await draftPush;
  expect(resp.ok()).toBe(true);

  // Wait for the iframe to reload in draft mode and render the template layer.
  await expect
    .poll(
      async () => {
        const frame = findPreviewFrame(page);
        if (!frame || !frame.url().includes("_draft=1")) return false;
        try {
          return await frame.evaluate(
            (slug) => !!document.querySelector(`.bg-template-${slug}`),
            tplSlug,
          );
        } catch {
          return false; // navigating
        }
      },
      { timeout: 30_000 },
    )
    .toBe(true);

  // Unsaved-preview badge should be visible.
  await expect(page.locator("#draftPreviewBadge")).toBeVisible();
});
