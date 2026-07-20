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
 * End-to-end coverage for the `link` block's two Alpine-driven helpers in the
 * live editor (feature tests cover only the JSON endpoints):
 *
 *  1. "Pick from my links" — opens the picker, loads the owner's links from
 *     `links.blocks.linkPicker`, and clicking an entry writes its URL into the
 *     x-model-bound URL field (and pre-fills the empty title field). Picking
 *     also auto-runs the OG fetch, staging the result into a
 *     confirm-before-apply preview card (og-meta is mocked here because the
 *     picked link's own URL is localhost, which the SSRF guard blocks).
 *  2. "Fetch" (OG metadata) — hits `links.blocks.ogMeta` for a public URL and
 *     pre-fills at least one of title / description / thumbnail (thumbnail is
 *     injected into the nested file-upload-field Alpine component).
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

// Per-run unique aliases (parallel task envs share one RDS); stale twins with
// the same prefix are pruned at seed time.
const ALIAS_PREFIX = "e2e-lbp";
const RUN_SUFFIX = Date.now().toString(36);
const BIO_ALIAS = `${ALIAS_PREFIX}-bio-${RUN_SUFFIX}`;
const SHORT_ALIAS = `${ALIAS_PREFIX}-short-${RUN_SUFFIX}`;
const SHORT_URL = "https://example.com/picked-destination";

// A real public page the SSRF-guarded server-side fetcher is allowed to reach.
// example.com is IANA-stable: it always has a <title> ("Example Domain"), so
// the title pre-fill path is deterministic.
const OG_FETCH_URL = "https://example.com";

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
 * Seed: demo user, one biolink with TWO `link` blocks (one per scenario, both
 * with empty text/url so the pre-fill guards fire), and one short link the
 * picker can list. Prunes stale fixtures from earlier runs sharing the prefix.
 */
function seedFixtures(): {
  linkId: number;
  pickerBlockId: number;
  ogBlockId: number;
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

// Prune stale twins (created > 2h ago) sharing the alias prefix.
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}-%')
  ->where('created_at', '<', now()->subHours(2))
  ->get();
foreach ($stale as $s) {
  BiolinkBlock::where('link_id', $s->id)->delete();
  $s->delete();
}

$short = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'short',
  'alias' => '${SHORT_ALIAS}', 'title' => 'Picker Target Link',
  'url' => '${SHORT_URL}', 'is_active' => true,
]);

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${BIO_ALIAS}', 'title' => 'E2E Link Picker', 'is_active' => true,
]);
$pick = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'link', 'sort_order' => 0, 'is_active' => true, 'settings' => ['text' => '', 'url' => '']]);
$og = BiolinkBlock::create(['link_id' => $bio->id, 'type' => 'link', 'sort_order' => 1, 'is_active' => true, 'settings' => ['text' => '', 'url' => '']]);

echo 'IDS=' . json_encode(['linkId' => $bio->id, 'pickerBlockId' => $pick->id, 'ogBlockId' => $og->id]);
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

async function gotoEditor(page: Page): Promise<void> {
  // Heavy Blade render over the distant RDS; DOMContentLoaded + block-card
  // wait is the signal we actually need (see biolink-editor-inline-edit spec).
  await page.goto(`/user/links/${ids.linkId}/blocks`, {
    waitUntil: "domcontentloaded",
    timeout: 90_000,
  });
  await page.waitForSelector(".block-card", { timeout: 45_000 });
}

/** Open a block's inline settings editor and return its loaded <form>. */
async function openBlockForm(page: Page, blockId: number) {
  await page.click(`[data-block-id="${blockId}"] .edit-btn`);
  const form = page.locator(`[data-inline-editor-body="${blockId}"] form`);
  await expect(form).toBeVisible({ timeout: 20_000 });
  return form;
}

test.describe.configure({ mode: "serial" });

test("picking a link fills the URL and auto-fetches a confirm-before-apply preview", async ({
  page,
}) => {
  test.setTimeout(180_000);

  // Mock the picker-triggered OG fetch: the picked link's URL is localhost,
  // which the server-side SSRF guard rejects, so fulfill it locally to
  // exercise the staged preview-card path deterministically.
  const MOCK_TITLE = "Mocked OG Title";
  const MOCK_DESC = "Mocked OG description";
  const MOCK_IMAGE = "https://example.com/mock-og.png";
  await page.route("**/blocks/og-meta*", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        success: true,
        meta: {
          title: MOCK_TITLE,
          description: MOCK_DESC,
          image_url: MOCK_IMAGE,
          favicon_url: null,
        },
      }),
    });
  });

  await gotoEditor(page);

  const form = await openBlockForm(page, ids.pickerBlockId);
  const urlInput = form.locator('input[name="settings[url]"]');
  await expect(urlInput).toHaveValue("");

  // Opening the picker triggers the lazy loadPicker() fetch.
  const pickerResponse = page.waitForResponse(
    (r) => r.url().includes("/blocks/link-picker") && r.ok(),
    { timeout: 30_000 },
  );
  await form.getByRole("button", { name: /Pick from my links/i }).click();
  await pickerResponse;

  // The seeded short link appears; click it. This also auto-runs the
  // (mocked) OG fetch.
  const ogResponse = page.waitForResponse(
    (r) => r.url().includes("/blocks/og-meta"),
    { timeout: 30_000 },
  );
  const entry = form
    .locator("button", { hasText: "Picker Target Link" })
    .first();
  await expect(entry).toBeVisible({ timeout: 15_000 });
  await entry.click();

  // applyPickedLink(): URL x-model bound, empty title pre-filled, picker shut.
  // The picker hands over the link's own public URL (`url('/{alias}')`),
  // not its destination.
  await expect(urlInput).toHaveValue(new RegExp(`/${SHORT_ALIAS}$`));
  await expect(form.locator('input[name="settings[text]"]')).toHaveValue(
    "Picker Target Link",
  );
  await expect(
    form.locator('input[placeholder="Search by title or alias…"]'),
  ).toBeHidden();

  // The staged preview card appears; nothing applied yet.
  await ogResponse;
  const card = form.locator("[data-og-preview-card]");
  await expect(card).toBeVisible({ timeout: 15_000 });
  await expect(card.getByText(MOCK_TITLE)).toBeVisible();
  await expect(form.locator('input[name="settings[thumbnail]"]')).toHaveValue(
    "",
  );

  // Apply: description + thumbnail fill (title already set by the picker,
  // so the fill-only-empty guard leaves it untouched), card closes.
  await card.getByRole("button", { name: /Apply/i }).click();
  await expect(card).toBeHidden();
  await expect(form.getByText("Details pre-filled below.")).toBeVisible();
  await expect(form.locator('input[name="settings[text]"]')).toHaveValue(
    "Picker Target Link",
  );
  await expect(
    form.locator('input[name="settings[description]"]'),
  ).toHaveValue(MOCK_DESC);
  await expect(form.locator('input[name="settings[thumbnail]"]')).toHaveValue(
    MOCK_IMAGE,
  );

  await page.unroute("**/blocks/og-meta*");
});

test("Fetch pre-fills page details from a public URL", async ({ page }) => {
  test.setTimeout(180_000);
  await gotoEditor(page);

  const form = await openBlockForm(page, ids.ogBlockId);
  const urlInput = form.locator('input[name="settings[url]"]');
  await urlInput.fill(OG_FETCH_URL);

  const ogResponse = page.waitForResponse(
    (r) => r.url().includes("/blocks/og-meta"),
    { timeout: 45_000 },
  );
  await form.getByRole("button", { name: /Fetch/i }).click();
  const resp = await ogResponse;
  expect(resp.ok(), `og-meta returned HTTP ${resp.status()}`).toBeTruthy();

  // Success message + at least one of title/description/thumbnail pre-filled.
  await expect(form.getByText("Details pre-filled below.")).toBeVisible({
    timeout: 15_000,
  });
  const title = await form
    .locator('input[name="settings[text]"]')
    .inputValue();
  const thumb = await form
    .locator('input[name="settings[thumbnail]"]')
    .inputValue();
  const desc = await form
    .locator('input[name="settings[description]"]')
    .inputValue()
    .catch(() => "");
  expect(
    title.trim() !== "" || desc.trim() !== "" || thumb.trim() !== "",
    `expected a pre-filled field; got title="${title}" desc="${desc}" thumb="${thumb}"`,
  ).toBeTruthy();

  // example.com serves a <title>, so the title path specifically must fire.
  expect(title.trim()).not.toBe("");
});
