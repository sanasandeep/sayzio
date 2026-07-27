import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test as base, type BrowserContext, type Page } from "@playwright/test";

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

const ALIAS = "e2e-settings-tabs-dedupe";

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
    'alias' => '${ALIAS}', 'title' => 'E2E Settings Tabs Dedupe', 'is_active' => true,
  ]);
} else {
  $bio->user_id = $u->id; $bio->workspace_id = $ws?->id; $bio->save();
}
echo 'LINKID=' . $bio->id;
`.trim();

  const out = runTinkerSeed(php);
  const m = out.match(/LINKID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return { linkId: Number(m[1]) };
}

test.describe.configure({ mode: "serial" });

let fixture: { linkId: number };

test.beforeAll(async ({ browser }) => {
  fixture = seedFixture();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

// All seven settings sub-tab URLs relative to the link.
function tabUrls(linkId: number): Record<string, string> {
  const base = `/user/links/${linkId}`;
  return {
    appearance: `${base}/settings/appearance`,
    layout: `${base}/settings/layout`,
    "block-theme": `${base}/settings/block-theme`,
    themes: `${base}/themes`,
    advanced: `${base}/settings/advanced`,
    embed: `${base}/settings/embed`,
    splash: `${base}/splash`,
  };
}

async function expectSingleTabBar(page: Page, label: string) {
  await expect(page.locator(".settings-tabs"), `${label}: one tab bar`).toHaveCount(1);
}

test("each settings sub-tab renders exactly one tab bar on direct load", async ({ page }) => {
  test.setTimeout(600_000);
  const urls = tabUrls(fixture.linkId);
  for (const [name, url] of Object.entries(urls)) {
    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 120_000 });
    await expectSingleTabBar(page, `direct load ${name}`);
    // Tab bar must live OUTSIDE the AJAX swap container.
    await expect(
      page.locator("#settings-tab-content .settings-tabs"),
      `${name}: no tab bar inside swap container`,
    ).toHaveCount(0);
  }
});

test("AJAX switching into and out of Themes keeps a single tab bar and active state", async ({
  page,
}) => {
  test.setTimeout(600_000);
  const urls = tabUrls(fixture.linkId);
  await page.goto(urls.appearance, { waitUntil: "domcontentloaded", timeout: 120_000 });
  await expectSingleTabBar(page, "start on appearance");

  // Mark the preview iframe (present on appearance) so we can detect a reload.
  const hasIframe = (await page.locator("#devicePreviewFrame, iframe").count()) > 0;
  if (hasIframe) {
    await page.evaluate(() => {
      const f = document.querySelector("iframe");
      if (f) (f as HTMLElement).dataset.e2eMarker = "kept";
    });
  }

  const order: Array<[string, string]> = [
    ["Themes", urls.themes],
    ["Layout", urls.layout],
    ["Themes", urls.themes],
    ["Advanced", urls.advanced],
    ["Embed", urls.embed],
    ["Intro", urls.splash],
    ["Block Theme", urls["block-theme"]],
    ["Appearance", urls.appearance],
  ];

  for (const [label, url] of order) {
    const respPromise = page.waitForResponse(
      (r) => r.url().includes(url) && r.request().method() === "GET",
      { timeout: 60_000 },
    );
    await page.click(`.settings-tab[href$="${url}"]`);
    await respPromise;
    // Container swap settles quickly after the response.
    await expect(page.locator("#settings-tab-content")).not.toHaveClass(/is-loading/, {
      timeout: 30_000,
    });
    await expectSingleTabBar(page, `after switch to ${label}`);
    await expect(
      page.locator("#settings-tab-content .settings-tabs"),
      `${label}: no tab bar inside swap container`,
    ).toHaveCount(0);
    await expect(
      page.locator(`.settings-tab.is-active[href$="${url}"]`),
      `${label}: active highlight`,
    ).toHaveCount(1);
    // URL updated via pushState (no full navigation).
    expect(page.url()).toContain(url);
  }

  if (hasIframe) {
    // The device preview iframe on Appearance must be the same node
    // (never reloaded/replaced) after the round trip back to Appearance.
    const markerKept = await page.evaluate(() => {
      const f = document.querySelector("iframe");
      return f ? (f as HTMLElement).dataset.e2eMarker === "kept" : null;
    });
    expect(markerKept, "preview iframe survived AJAX tab switches").toBe(true);
  }
});
