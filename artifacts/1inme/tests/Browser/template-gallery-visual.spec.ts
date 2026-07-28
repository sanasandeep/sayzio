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

// Shared logged-in context (demo-login is throttled at 5/min).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

// Unique per-run aliases (the dev RDS is shared across parallel task envs, so
// fixed aliases collide). Stale rows from previous runs are pruned by prefix.
const ALIAS_PREFIX = "e2e-tplgal-";
const RUN = Date.now().toString(36);
const ALIAS_STARTER = `${ALIAS_PREFIX}s-${RUN}`;
const ALIAS_PERSONA = `${ALIAS_PREFIX}p-${RUN}`;

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

type Fixture = {
  linkStarterId: number;
  linkPersonaId: number;
  starterTplId: number;
  starterTplName: string;
  personaTplId: number;
  personaTplName: string;
};

function seedFixture(): Fixture {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\Admin\\Models\\PageTemplate;
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

// Prune stale fixture links from earlier runs (shared RDS hygiene).
Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}%')->get()->each->delete();

$mk = function(string $alias, string $title) use ($u, $ws) {
  return Link::create([
    'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
    'alias' => $alias, 'title' => $title, 'is_active' => true,
  ]);
};
$l1 = $mk('${ALIAS_STARTER}', 'E2E Template Gallery Starter');
$l2 = $mk('${ALIAS_PERSONA}', 'E2E Template Gallery Persona');

// Pick one seeded starter template and one seeded persona template that the
// demo user's plan does not lock (plan_tier null is always usable).
$starter = PageTemplate::active()->where('slug','like','starter-%')->whereNull('plan_tier')->orderBy('sort_order')->first();
$persona = PageTemplate::active()->where('slug','like','persona-%')->whereNull('plan_tier')->orderBy('slug')->first();

echo json_encode([
  'linkStarterId' => $l1->id,
  'linkPersonaId' => $l2->id,
  'starterTplId' => $starter?->id,
  'starterTplName' => $starter?->name,
  'personaTplId' => $persona?->id,
  'personaTplName' => $persona?->name,
]);
`;
  const out = runTinker(php);
  const m = out.match(/\{.*\}/s);
  if (!m) throw new Error("seedFixture: no JSON in tinker output:\n" + out);
  return JSON.parse(m[0]) as Fixture;
}

let fx: Fixture;

test.beforeAll(async ({ browser }) => {
  test.setTimeout(240_000);
  fx = seedFixture();
  expect(fx.starterTplId, "a starter-* template must exist").toBeTruthy();
  expect(fx.personaTplId, "a persona-* template must exist").toBeTruthy();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
  // Best-effort cleanup of this run's fixture links.
  try {
    runTinker(
      `use App\\Modules\\User\\Models\\Link; Link::withoutGlobalScope('workspace')->where('alias','like','${ALIAS_PREFIX}%')->get()->each->delete(); echo 'ok';`,
    );
  } catch {
    // non-fatal
  }
});

/** Assert the public biolink page at /{alias} renders real, non-blank blocks. */
async function assertPublicPageRenders(page: Page, alias: string) {
  const resp = await page.goto(`/${alias}`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  expect(resp?.status(), `public page /${alias} should be 200`).toBe(200);

  const wrappers = page.locator("[data-block-id]");
  const count = await wrappers.count();
  expect(count, "applied template should render top-level blocks").toBeGreaterThan(3);

  // No blank wrappers: every block wrapper must carry real content — text or
  // a meaningful element. A wrapper whose innerHTML is empty/whitespace means
  // the type had no renderer branch and fell through.
  const blanks = await page.evaluate(() => {
    const bad: string[] = [];
    document.querySelectorAll("[data-block-id]").forEach((el) => {
      const html = (el as HTMLElement).innerHTML.trim();
      const text = (el as HTMLElement).innerText?.trim() ?? "";
      const hasMedia = !!el.querySelector(
        "img,svg,iframe,video,audio,form,input,button,a,hr,canvas",
      );
      if (html === "" || (text === "" && !hasMedia)) {
        bad.push(
          `${el.getAttribute("data-block-type")}#${el.getAttribute("data-block-id")}`,
        );
      }
    });
    return bad;
  });
  expect(blanks, `blank block wrappers on /${alias}: ${blanks.join(", ")}`).toEqual([]);

  // Every wrapper should occupy layout space (height > 0).
  const zeroHeight = await page.evaluate(() => {
    const bad: string[] = [];
    document.querySelectorAll("[data-block-id]").forEach((el) => {
      if ((el as HTMLElement).getBoundingClientRect().height <= 0) {
        bad.push(
          `${el.getAttribute("data-block-type")}#${el.getAttribute("data-block-id")}`,
        );
      }
    });
    return bad;
  });
  expect(
    zeroHeight,
    `zero-height block wrappers on /${alias}: ${zeroHeight.join(", ")}`,
  ).toEqual([]);

  // The profile/header area should end up actually visible (reveal animations
  // must not leave the page stuck at opacity 0).
  const firstVisible = await page.evaluate(() => {
    const el = document.querySelector("[data-block-id]") as HTMLElement | null;
    if (!el) return false;
    let node: HTMLElement | null = el;
    while (node) {
      const cs = getComputedStyle(node);
      if (cs.display === "none" || cs.visibility === "hidden" || parseFloat(cs.opacity) === 0) {
        return false;
      }
      node = node.parentElement;
    }
    return true;
  });
  expect(firstVisible, "first block should be effectively visible").toBe(true);
}

/** Apply a template by id from the picker of the given link. */
async function applyTemplate(page: Page, linkId: number, tplId: number) {
  await page.goto(`/user/links/${linkId}/templates`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  const form = page.locator(
    `form:has(input[name="template_id"][value="${tplId}"])`,
  );
  await expect(form, `template ${tplId} should have an apply form`).toHaveCount(1);
  // Bring the card into view (cards are x-cloak'd until Alpine boots).
  await form.scrollIntoViewIfNeeded();
  // The apply POST + redirect chain over the distant dev RDS can take well
  // over the 30s click auto-wait, so don't let click() wait for navigation;
  // wait for the URL commit ourselves with a generous budget.
  await Promise.all([
    page.waitForURL(/\/blocks(\/editor)?/, {
      timeout: 240_000,
      waitUntil: "commit",
    }),
    form.locator('button[type="submit"]').click({ noWaitAfter: true }),
  ]);
}

test("picker grid renders chips, thumbnails and working search", async ({ page }) => {
  test.setTimeout(180_000);
  await page.goto(`/user/links/${fx.linkStarterId}/templates`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });

  await expect(
    page.getByRole("heading", { name: "Choose a starting template" }),
  ).toBeVisible();

  // Category chips: "All" plus at least a few persona categories.
  const chipBar = page.locator("div.overflow-x-auto").first();
  await expect(chipBar.getByRole("button", { name: "All", exact: true })).toBeVisible();
  const chipCount = await chipBar.locator("button").count();
  expect(chipCount, "should render several category chips").toBeGreaterThan(5);

  // The picker now server-renders only the first chunk and streams the rest
  // in the background; wait for the grid to report the full library loaded
  // before counting cards.
  await expect(page.locator("div.grid[data-all-loaded]")).toHaveCount(1, {
    timeout: 120_000,
  });

  // Cards: many render, each with a name and a thumbnail/blueprint area.
  const cards = page.locator("div.grid > div.glass");
  const cardCount = await cards.count();
  expect(cardCount, "picker should render the seeded library").toBeGreaterThan(100);

  const firstCard = cards.first();
  await expect(firstCard.locator("h3").first()).not.toHaveText("");
  // Thumbnail area: an <img> (thumbnail_url / placeholder) or the generated
  // mini blueprint partial.
  const thumbArea = firstCard.locator("div.aspect-\\[4\\/3\\]");
  await expect(thumbArea).toHaveCount(1);
  expect(await thumbArea.locator("img, [class*='tpl-bp'], div").count()).toBeGreaterThan(0);

  // Starter card is present with its name + thumbnail.
  const starterCard = page.locator(
    `div.glass:has(input[name="template_id"][value="${fx.starterTplId}"])`,
  );
  await expect(starterCard).toHaveCount(1);
  await starterCard.scrollIntoViewIfNeeded();
  await expect(starterCard.locator("h3").first()).toContainText(
    fx.starterTplName.slice(0, 12),
  );

  // Search filters the grid: searching the starter template's name shrinks
  // the visible set and keeps the matching card visible.
  const search = page.getByPlaceholder("Search templates…");
  await search.fill(fx.starterTplName);
  await expect(starterCard).toBeVisible();
  // The live counter appears once a filter is active and shows a reduced count.
  const counter = page.locator("p", { hasText: "Showing" }).first();
  await expect(counter).toBeVisible();
  const shown = await counter.locator("span").first().innerText();
  expect(Number(shown)).toBeGreaterThan(0);
  expect(Number(shown)).toBeLessThan(cardCount);

  // Nonsense search → zero state with a working "Clear filters".
  await search.fill("zzz-no-such-template-xyz");
  await expect(
    page.getByRole("heading", { name: "No templates match your filters" }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Clear filters" }).click();
  await expect(starterCard).toBeVisible();

  // Category chip filtering: click a non-All chip and confirm the counter
  // shows a subset.
  const secondChip = chipBar.locator("button").nth(1);
  await secondChip.click();
  await expect(counter).toBeVisible();
  const catShown = Number(await counter.locator("span").first().innerText());
  expect(catShown).toBeGreaterThan(0);
  expect(catShown).toBeLessThan(cardCount);
});

test("applying a starter template renders a full public page (dark + light)", async ({ page }) => {
  test.setTimeout(300_000);
  await applyTemplate(page, fx.linkStarterId, fx.starterTplId);

  await page.emulateMedia({ colorScheme: "dark" });
  await assertPublicPageRenders(page, ALIAS_STARTER);

  await page.emulateMedia({ colorScheme: "light" });
  await assertPublicPageRenders(page, ALIAS_STARTER);
});

test("applying a persona template renders a full public page (dark + light)", async ({ page }) => {
  test.setTimeout(300_000);
  await applyTemplate(page, fx.linkPersonaId, fx.personaTplId);

  await page.emulateMedia({ colorScheme: "dark" });
  await assertPublicPageRenders(page, ALIAS_PERSONA);

  await page.emulateMedia({ colorScheme: "light" });
  await assertPublicPageRenders(page, ALIAS_PERSONA);
});
