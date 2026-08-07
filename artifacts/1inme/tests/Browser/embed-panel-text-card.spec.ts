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

/**
 * Embed panel whitespace fix (task #6712).
 *
 * - `text` links are card embeds: compact variant-aware snippet (148px
 *   without a subtitle, no 80vh/560px) and
 *   a preview iframe that auto-fits to the card's posted height instead of
 *   leaving a large blank area.
 * - Full-page links (biolink) keep the tall responsive preview + snippet.
 */

// Shared logged-in context (demo-login is rate-limited at throttle:5,1).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const TEXT_ALIAS = "e2e-embed-text-card";
const BIO_ALIAS = "e2e-embed-bio-page";

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

function seedFixtures(): { textId: number; bioId: number } {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
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

$mk = function (string $alias, string $type, array $extra) use ($u, $ws) {
  $l = Link::withoutGlobalScope('workspace')->where('alias', $alias)->first();
  if (!$l) {
    $l = Link::create(array_merge([
      'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => $type,
      'alias' => $alias, 'is_active' => true,
    ], $extra));
  } else {
    $l->user_id = $u->id; $l->workspace_id = $ws?->id; $l->save();
  }
  return $l;
};

$txt = $mk('${TEXT_ALIAS}', 'text', ['title' => 'E2E embed text', 'settings' => ['text_content' => 'A short shared note.']]);
$bio = $mk('${BIO_ALIAS}', 'biolink', ['title' => 'E2E embed bio']);

echo 'IDS=' . json_encode(['textId' => $txt->id, 'bioId' => $bio->id]);
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

async function gotoEdit(
  page: Page,
  linkId: number,
  mode: "light" | "dark",
): Promise<void> {
  await page.goto(`/user/links/${linkId}/edit`, {
    waitUntil: "domcontentloaded",
  });
  await page.evaluate((m) => {
    document.documentElement.classList[m === "dark" ? "remove" : "add"](
      "light-mode",
    );
  }, mode);
  await page.waitForSelector("iframe[data-embed-preview]", {
    timeout: 45_000,
  });
}

for (const mode of ["light", "dark"] as const) {
  test(`${mode}: text link gets compact card snippet + auto-fit preview`, async ({
    page,
  }) => {
    await gotoEdit(page, ids.textId, mode);

    // Card badge + compact static-iframe snippet (no tall 80vh/560px embed).
    await expect(page.locator(".embp-badge")).toContainText("Card");
    const snippet = await page
      .locator("pre code")
      .nth(1)
      .textContent();
    // No seo_description on the fixture → no subtitle row → the shorter
    // variant-aware card height (task #6713).
    expect(snippet).toContain("height:148px");
    expect(snippet).not.toContain("80vh");
    expect(snippet).not.toContain("min-height:560px");

    // Preview iframe auto-fits the card content — well under the old
    // hardcoded heights, so no large blank area inside the preview box.
    const frame = page.locator("iframe[data-embed-preview]");
    await expect
      .poll(
        async () =>
          (await frame.boundingBox().then((b) => b?.height ?? 0)) as number,
        { timeout: 20_000 },
      )
      .toBeLessThan(300);
    const h = (await frame.boundingBox())?.height ?? 0;
    expect(h).toBeGreaterThan(80);
  });
}

test("biolink keeps the tall responsive preview and snippet", async ({
  page,
}) => {
  // Biolinks have no classic edit page (it redirects to the appearance
  // settings) — the embed panel lives on the dedicated Embed settings tab.
  await page.goto(`/user/links/${ids.bioId}/settings/embed`, {
    waitUntil: "domcontentloaded",
  });
  await page.waitForSelector("iframe[data-embed-preview]", {
    timeout: 45_000,
  });

  await expect(page.locator(".embp-badge")).toContainText("Iframe");
  const snippet = await page.locator("pre code").nth(1).textContent();
  expect(snippet).toContain("80vh");
  expect(snippet).toContain("min-height:560px");

  const frame = page.locator("iframe[data-embed-preview]");
  const h = (await frame.boundingBox())?.height ?? 0;
  expect(h).toBeGreaterThanOrEqual(455);
});
