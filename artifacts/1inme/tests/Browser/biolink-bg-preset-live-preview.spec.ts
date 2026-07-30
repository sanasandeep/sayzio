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

const ALIAS = "e2e-bg-preset-live-preview";

// Task #6204 hid the "gradients" group from the Presets picker (moved to the
// Gradient tab), so target a picker-visible preset from the abstract group.
// getComputedStyle normalizes hex colors to rgb(), so match the rgb form.
const PRESET_LABEL = "Abstract 1";
const PRESET_CSS_MARKER = "rgb(255, 0, 199)";

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
 * Seed the demo user + a plain color-background biolink so the draft change
 * to a background preset is unambiguous.
 */
function seedFixture(): { linkId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;

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
    'alias' => '${ALIAS}', 'title' => 'E2E BG Preset Live Preview', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id;
}
// Reset to a plain color background so the draft change is unambiguous.
$s = $bio->settings ?? [];
$b = $s['biolink'] ?? [];
$b['background_type'] = 'color';
$b['background_color'] = '#111827';
unset($b['bg_preset_key']);
$s['biolink'] = $b;
$bio->settings = $s;
$bio->save();

echo 'LINKID=' . $bio->id;
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/LINKID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return { linkId: Number(m[1]) };
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

let fixture: { linkId: number };

test.beforeAll(async ({ browser }) => {
  if (process.env.E2E_BGPRESET_LINKID) {
    fixture = { linkId: Number(process.env.E2E_BGPRESET_LINKID) };
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

test("clicking a background preset swatch updates the live phone preview without saving", async ({
  page,
}) => {
  // Cold first navigation to the heavy appearance editor over the distant RDS
  // can eat most of the default 60s budget before the iframe even mounts.
  test.setTimeout(180_000);
  const { linkId } = fixture;

  await page.goto(`/user/links/${linkId}/settings/appearance`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });

  // Wait for the phone preview iframe to load the signed public page.
  await expect
    .poll(() => findPreviewFrame(page) !== null, { timeout: 60_000 })
    .toBe(true);

  // Switch the background type to "Presets".
  const presetsTab = page.locator('button:has-text("Presets")').first();
  await presetsTab.waitFor({ state: "visible", timeout: 30_000 });
  await presetsTab.click();

  // Click the first preset swatch (default "gradients" group). The background
  // card partial can render more than once on the page — scope to the first
  // visible instance.
  const swatch = page.locator(`button[title="${PRESET_LABEL}"]`).first();
  await swatch.waitFor({ state: "visible", timeout: 30_000 });

  // Register the response listener BEFORE clicking so the debounced draft
  // push (500ms) can never race past us.
  const draftPush = page.waitForResponse(
    (r) =>
      r.url().includes("/preview-draft") && r.request().method() === "POST",
    { timeout: 30_000 },
  );
  await swatch.click();

  const resp = await draftPush;
  expect(resp.ok()).toBe(true);

  // Wait for the iframe to reload in draft mode and render the preset
  // background on the public page's body.
  await expect
    .poll(
      async () => {
        const frame = findPreviewFrame(page);
        if (!frame || !frame.url().includes("_draft=1")) return false;
        try {
          return await frame.evaluate((marker) => {
            // Default bg_attachment is "fixed": the preset renders on the
            // dedicated .bg-page-fixed layer. Fall back to <body> for the
            // scroll mode / legacy path.
            const layer = document.querySelector(".bg-page-fixed");
            const el = layer ?? document.body;
            return getComputedStyle(el).backgroundImage.includes(marker);
          }, PRESET_CSS_MARKER);
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
