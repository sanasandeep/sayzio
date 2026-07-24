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
 * End-to-end coverage for the "Search the web for images" picker on the web
 * AI builder intake (/user/links/{id}/ai-builder). Feature tests cover the
 * JSON endpoints; this spec proves the Alpine wiring in a real browser:
 *
 *  - the collapsible section renders when Google CSE is configured
 *  - running a search hits the image-search endpoint and paints the
 *    candidate grid + rights disclaimer
 *  - multi-select toggles the checkmark and the "Add selected (n)" counter
 *  - importing hits import-images and appends the returned vault URLs as
 *    thumbnails in the intake images list (dedupe against maxImages intact)
 *
 * The search + import endpoints are stubbed with page.route so no real
 * Google CSE call or remote download happens; server-side CSE config is
 * seeded (and restored afterwards) only so the Blade section renders at all
 * (`$imageSearchEnabled` gates the markup server-side).
 */

let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

// Per-run unique alias (parallel task envs share one RDS).
const ALIAS_PREFIX = "e2e-aiimg";
const RUN_SUFFIX = Date.now().toString(36);
const BIO_ALIAS = `${ALIAS_PREFIX}-bio-${RUN_SUFFIX}`;

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

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
 * Seed: demo user + one biolink, and enable Google CSE via the admin
 * PlatformServiceSettings (only if not already configured — flags are
 * returned so afterAll only clears what THIS run set).
 */
function seedFixtures(): {
  linkId: number;
  setCseKey: boolean;
  setCseEngine: boolean;
} {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Services\\WorkspaceContext;
use App\\Services\\Integrations\\PlatformServiceSettings;
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

// Prune stale twins (created > 2h ago) sharing the alias prefix.
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}-%')
  ->where('created_at', '<', now()->subHours(2))
  ->get();
foreach ($stale as $s) {
  BiolinkBlock::where('link_id', $s->id)->delete();
  $s->delete();
}

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${BIO_ALIAS}', 'title' => 'E2E Image Search', 'is_active' => true,
]);

// Enable CSE only if not already configured, and report what we set so
// teardown restores exactly that (never clobbers real admin/env keys).
$setKey = false; $setEngine = false;
if (PlatformServiceSettings::googleCseApiKey() === null) {
  PlatformServiceSettings::setGoogleCseApiKey('e2e-test-key');
  $setKey = true;
}
if (PlatformServiceSettings::googleCseEngineId() === null) {
  PlatformServiceSettings::setGoogleCseEngineId('e2e-test-cx');
  $setEngine = true;
}

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'setCseKey' => $setKey, 'setCseEngine' => $setEngine]);
`.trim();

  const out = runTinker(php);
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
  // Restore CSE config: clear only the pieces this run set.
  if (ids && (ids.setCseKey || ids.setCseEngine)) {
    const php = `
use App\\Services\\Integrations\\PlatformServiceSettings;
${ids.setCseKey ? "PlatformServiceSettings::setGoogleCseApiKey(null);" : ""}
${ids.setCseEngine ? "PlatformServiceSettings::setGoogleCseEngineId(null);" : ""}
echo 'CLEANED';
`.trim();
    runTinker(php);
  }
});

// Stubbed candidate results (shape mirrors GoogleImageSearchService::search).
const RESULTS = [
  {
    url: "https://images.example.com/photo-a.jpg",
    thumbnail: null,
    title: "Photo A",
    source: "images.example.com",
    width: 800,
    height: 600,
  },
  {
    url: "https://images.example.com/photo-b.png",
    thumbnail: null,
    title: "Photo B",
    source: "images.example.com",
    width: 640,
    height: 640,
  },
  {
    url: "https://images.example.com/photo-c.webp",
    thumbnail: null,
    title: "Photo C",
    source: "cdn.example.org",
    width: 1024,
    height: 768,
  },
];
const DISCLAIMER =
  "Make sure you have the rights to use any image you pick — search results may be copyrighted.";
// Vault-relative URLs, same shape importImages returns after storing.
const IMPORTED = [
  { url: "/f/e2e-imported-a.jpg", source_url: RESULTS[0].url },
  { url: "/f/e2e-imported-b.png", source_url: RESULTS[1].url },
];

test("search, select and import candidates into the images list", async ({
  page,
}) => {
  test.setTimeout(180_000);

  let searchBody: unknown = null;
  await page.route("**/ai-builder/image-search", async (route) => {
    searchBody = route.request().postDataJSON();
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ results: RESULTS, disclaimer: DISCLAIMER }),
    });
  });

  let importBody: unknown = null;
  await page.route("**/ai-builder/import-images", async (route) => {
    importBody = route.request().postDataJSON();
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ images: IMPORTED }),
    });
  });

  // The intake page is a light authenticated render, but a cold first paint
  // over the distant RDS can still be slow — generous nav budget.
  await page.goto(`/user/links/${ids.linkId}/ai-builder`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });

  // The CSE-gated section rendered (server saw the seeded config).
  const toggle = page.getByRole("button", {
    name: /Search the web for images/i,
  });
  await expect(toggle).toBeVisible({ timeout: 30_000 });

  // Collapsed by default: the query input is hidden until opened.
  const queryInput = page.getByPlaceholder("e.g. minimalist fitness logo");
  await expect(queryInput).toBeHidden();
  await toggle.click();
  await expect(queryInput).toBeVisible();

  // Run a search (Enter key path also exists; use the button).
  await queryInput.fill("minimalist fitness logo");
  const searchResponse = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/image-search"),
    { timeout: 30_000 },
  );
  // The submit button is the icon-only sibling of the input.
  await queryInput
    .locator("xpath=following-sibling::button[1]")
    .click();
  await searchResponse;
  expect(searchBody).toMatchObject({ query: "minimalist fitness logo" });

  // Candidate grid + rights disclaimer painted.
  await expect(page.getByText(DISCLAIMER)).toBeVisible({ timeout: 15_000 });
  const candidates = page.locator(
    'button[class*="aspect-square"]:has(img[src^="https://images.example.com/"], img[src^="https://cdn."])',
  );
  const candidateA = page.locator(
    `button:has(img[src="${RESULTS[0].url}"])`,
  );
  const candidateB = page.locator(
    `button:has(img[src="${RESULTS[1].url}"])`,
  );
  const candidateC = page.locator(
    `button:has(img[src="${RESULTS[2].url}"])`,
  );
  await expect(candidateA).toBeVisible();
  await expect(candidateB).toBeVisible();
  await expect(candidateC).toBeVisible();
  void candidates;

  // Import is disabled until something is selected.
  const importBtn = page.getByRole("button", { name: /Add selected/i });
  await expect(importBtn).toBeVisible();
  await expect(importBtn).toBeDisabled();

  // Multi-select two candidates; the counter tracks; toggling off works.
  await candidateA.click();
  await expect(importBtn).toHaveText(/Add selected \(1\)/);
  await candidateC.click();
  await expect(importBtn).toHaveText(/Add selected \(2\)/);
  await expect(candidateC.locator(".fa-check")).toBeVisible();
  await candidateC.click(); // deselect
  await expect(importBtn).toHaveText(/Add selected \(1\)/);
  await candidateB.click();
  await expect(importBtn).toHaveText(/Add selected \(2\)/);
  await expect(importBtn).toBeEnabled();

  // Import: hits the endpoint with the selected URLs, then the returned
  // vault URLs appear as thumbnails in the intake images grid.
  const importResponse = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/import-images"),
    { timeout: 30_000 },
  );
  await importBtn.click();
  await importResponse;
  expect(importBody).toMatchObject({
    urls: [RESULTS[0].url, RESULTS[1].url],
  });

  // Thumbnails in the images list (x-for over images[]).
  await expect(
    page.locator(`img[src="${IMPORTED[0].url}"]`),
  ).toBeAttached({ timeout: 15_000 });
  await expect(
    page.locator(`img[src="${IMPORTED[1].url}"]`),
  ).toBeAttached();

  // Selection cleared after a successful import.
  await expect(importBtn).toHaveText(/Add selected \(0\)/);
  await expect(importBtn).toBeDisabled();
});

test("search endpoint failure renders the inline error, not a broken grid", async ({
  page,
}) => {
  test.setTimeout(180_000);

  // Simulate a server-side failure (e.g. CSE quota/404/500 surfaced as a
  // JSON error) — the UI must show the inline message and paint no grid.
  await page.route("**/ai-builder/image-search", async (route) => {
    await route.fulfill({
      status: 500,
      contentType: "application/json",
      body: JSON.stringify({ message: "Image search is unavailable right now." }),
    });
  });

  await page.goto(`/user/links/${ids.linkId}/ai-builder`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });

  const toggle = page.getByRole("button", {
    name: /Search the web for images/i,
  });
  await expect(toggle).toBeVisible({ timeout: 30_000 });
  await toggle.click();

  const queryInput = page.getByPlaceholder("e.g. minimalist fitness logo");
  await queryInput.fill("anything at all");
  const failedResponse = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/image-search"),
    { timeout: 30_000 },
  );
  await queryInput.locator("xpath=following-sibling::button[1]").click();
  await failedResponse;

  // Inline error from the JSON body; no candidate grid, no import button.
  await expect(
    page.getByText("Image search is unavailable right now."),
  ).toBeVisible({ timeout: 15_000 });
  await expect(
    page.getByRole("button", { name: /Add selected/i }),
  ).toHaveCount(0);
  await expect(page.getByText(DISCLAIMER)).toHaveCount(0);

  // And a non-JSON hard failure (proxy 404 page) falls back to the generic
  // message instead of leaving the spinner stuck.
  await page.unroute("**/ai-builder/image-search");
  await page.route("**/ai-builder/image-search", async (route) => {
    await route.fulfill({
      status: 404,
      contentType: "text/html",
      body: "<html><body>Not Found</body></html>",
    });
  });
  const secondResponse = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/image-search"),
    { timeout: 30_000 },
  );
  await queryInput.locator("xpath=following-sibling::button[1]").click();
  await secondResponse;
  await expect(
    page.getByText("Search failed. Please try again."),
  ).toBeVisible({ timeout: 15_000 });
  await expect(
    page.getByRole("button", { name: /Add selected/i }),
  ).toHaveCount(0);
});

test("image_search_unavailable collapses the whole picker section mid-session", async ({
  page,
}) => {
  test.setTimeout(180_000);

  // The admin removed/disabled the Google CSE keys while the intake page was
  // open: the server answers 404 JSON with code=image_search_unavailable and
  // the client must hide the ENTIRE picker section (toggle included), not
  // just paint an inline error the creator would keep retrying against.
  await page.route("**/ai-builder/image-search", async (route) => {
    await route.fulfill({
      status: 404,
      contentType: "application/json",
      body: JSON.stringify({
        message: "Image search is not available.",
        code: "image_search_unavailable",
      }),
    });
  });

  await page.goto(`/user/links/${ids.linkId}/ai-builder`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });

  const toggle = page.getByRole("button", {
    name: /Search the web for images/i,
  });
  await expect(toggle).toBeVisible({ timeout: 30_000 });
  await toggle.click();

  const queryInput = page.getByPlaceholder("e.g. minimalist fitness logo");
  await expect(queryInput).toBeVisible();
  await queryInput.fill("anything at all");
  const failedResponse = page.waitForResponse(
    (r) => r.url().includes("/ai-builder/image-search"),
    { timeout: 30_000 },
  );
  await queryInput.locator("xpath=following-sibling::button[1]").click();
  await failedResponse;

  // The whole section collapses (x-show="searchAvailable" flips false):
  // toggle, query input, everything — not an inline error left retryable.
  await expect(toggle).toBeHidden({ timeout: 15_000 });
  await expect(queryInput).toBeHidden();
  await expect(
    page.getByText("Image search is not available."),
  ).toHaveCount(0);
  await expect(
    page.getByText("Search failed. Please try again."),
  ).toHaveCount(0);
  await expect(
    page.getByRole("button", { name: /Add selected/i }),
  ).toHaveCount(0);

  // Positive anchor: the rest of the intake page is still alive (the
  // creator can keep uploading their own images).
  await expect(
    page.getByText("Photos", { exact: false }).first(),
  ).toBeVisible();
});

// Keep this LAST: it temporarily clears the CSE config seeded in beforeAll,
// then restores it so afterAll's targeted cleanup still applies.
test("section is hidden entirely when Google CSE is not configured", async ({
  page,
}) => {
  test.setTimeout(180_000);

  // Snapshot the current admin values, clear BOTH, and verify nothing else
  // (e.g. env fallback keys) still resolves the config. Restored in finally.
  const clearPhp = `
use App\\Services\\Integrations\\PlatformServiceSettings;
$prevKey = PlatformServiceSettings::googleCseApiKey();
$prevEngine = PlatformServiceSettings::googleCseEngineId();
PlatformServiceSettings::setGoogleCseApiKey(null);
PlatformServiceSettings::setGoogleCseEngineId(null);
// applyRuntimeConfig() at THIS process's boot copied the (then-present)
// admin values into config('services.google_cse.*'); clear that stale
// runtime copy so 'configured' reflects what a fresh request would see.
config(['services.google_cse.api_key' => null, 'services.google_cse.engine_id' => null]);
echo 'SNAP=' . json_encode([
  'key' => $prevKey,
  'engine' => $prevEngine,
  'configured' => PlatformServiceSettings::googleCseConfigured(),
]);
`.trim();

  const out = runTinker(clearPhp);
  const snapMatch = out.match(/SNAP=(\{.*\})/);
  if (!snapMatch) throw new Error("CSE snapshot failed, output:\n" + out);
  const snap: {
    key: string | null;
    engine: string | null;
    configured: boolean;
  } = JSON.parse(snapMatch[1]);

  const restore = () => {
    const restorePhp = `
use App\\Services\\Integrations\\PlatformServiceSettings;
PlatformServiceSettings::setGoogleCseApiKey(${JSON.stringify(snap.key)});
PlatformServiceSettings::setGoogleCseEngineId(${JSON.stringify(snap.engine)});
echo 'RESTORED';
`.trim();
    runTinker(restorePhp);
  };

  // If the config still resolves after clearing admin values, an env
  // fallback exists that we cannot unset — restore and skip.
  if (snap.configured) {
    restore();
    test.skip(
      true,
      "CSE still configured via env fallback; cannot simulate preview mode.",
    );
  }

  try {
    await page.goto(`/user/links/${ids.linkId}/ai-builder`, {
      waitUntil: "domcontentloaded",
      timeout: 90_000,
    });

    // Positive anchor first — the intake page itself rendered — so the
    // absence assertions below can't pass on a blank/error page.
    await expect(page.getByText("Photos", { exact: false }).first()).toBeVisible(
      { timeout: 30_000 },
    );
    await expect(
      page.getByPlaceholder("e.g. minimalist fitness logo"),
    ).toHaveCount(0);
    await expect(
      page.getByRole("button", { name: /Search the web for images/i }),
    ).toHaveCount(0);
    await expect(page.getByText(/Search the web for images/i)).toHaveCount(0);
  } finally {
    // Restore the exact snapshot so afterAll cleanup stays correct.
    restore();
  }
});
