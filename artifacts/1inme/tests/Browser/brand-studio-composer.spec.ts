import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";
import type { Route } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * e2e: Brand Studio KIT COMPOSER (Task #5578) — the "Pick exactly what to
 * create" composition UI on /user/brand-studio.
 *
 * The PHP feature tests cover the service-side composition validation and
 * repair; this spec covers the Alpine wiring the PHP tests cannot reach:
 *   - a preset button seeds the composition rows (kind/count/purpose),
 *   - the count stepper's "+" is capped by the per-kind kit cap (biolink=3)
 *     and never steps past it,
 *   - pushing the SUMMED per-kind count over the cap (via "Add asset")
 *     surfaces the amber composition error and disables "Generate plan",
 *     and removing the offending row clears it again,
 *   - the purpose input feeds the JSON payload the plan POST sends
 *     (kind/count/trimmed purpose per row),
 *   - the plan review page renders the purpose chips for proposed assets.
 *
 * Deterministic AI: both /user/brand-studio/estimate (auto-fired by the
 * Alpine watcher) and /user/brand-studio/plan (server-side OpenAI) are
 * intercepted with page.route(), following brand-studio-flow.spec.ts. The
 * plan response redirects to a proposal kit seeded via tinker whose assets
 * carry `purpose` values, so the review-page chips render from real Blade.
 *
 * Fixtures are per-run unique + stale-pruned (shared-RDS parallel envs — see
 * repo memory e2e-shared-rds-fixture-aliases).
 */

const RUN = Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
const KIT_NAME = `E2E BS Composer ${RUN}`;
const BIO_TITLE = `E2E BSC Bio ${RUN}`;
const LINK_TITLE = `E2E BSC Link ${RUN}`;
const QR_NAME = `E2E BSC QR ${RUN}`;
const BRIEF = "E2E composer run - launch pack for the summer campaign.";
const CUSTOM_PURPOSE = "E2E hero landing page";

// Purposes the seeded proposal carries — asserted as chips on plan review.
const PURPOSE_BIO = `E2E purpose hero ${RUN}`;
const PURPOSE_LINK = `E2E purpose campaign ${RUN}`;
const PURPOSE_QR = `E2E purpose poster ${RUN}`;

// Mirrors AiBrandStudioService::KIT_CAPS['biolink'].
const BIOLINK_CAP = 3;

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

let kitId: number;

function runTinker(php: string): string {
  return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
}

/**
 * Seed a proposal-status kit for the demo user whose assets all carry a
 * `purpose`, exactly the shape AiBrandStudioService persists when the model
 * echoes the requested composition back. Prunes stale fixtures first.
 */
function seedProposalKit(): number {
  // NOTE: goes straight to `tinker --execute=`; `\\` → single backslash for
  // PHP namespaces, `$var` stays literal in a JS template literal.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\BrandStudioKit;

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->firstOrFail();

BrandStudioKit::where('user_id', $u->id)
  ->where('name', 'like', 'E2E BS Composer %')
  ->where('created_at', '<', now()->subDay())->delete();

$kit = BrandStudioKit::create([
  'user_id'  => $u->id,
  'name'     => '${KIT_NAME}',
  'mode'     => BrandStudioKit::MODE_KIT,
  'status'   => BrandStudioKit::STATUS_PROPOSAL,
  'request'  => '${BRIEF}',
  'brand'    => ['name' => 'E2E Brand'],
  'proposal' => ['assets' => [
    [
      'kind' => 'biolink', 'title' => '${BIO_TITLE}',
      'purpose' => '${PURPOSE_BIO}', 'theme_color' => '#112233',
      'blocks' => [
        ['type' => 'heading',   'settings' => ['text' => 'Hello', 'size' => 'h2']],
        ['type' => 'paragraph', 'settings' => ['text' => 'E2E composer body.']],
      ],
    ],
    ['kind' => 'short_link', 'title' => '${LINK_TITLE}',
     'purpose' => '${PURPOSE_LINK}', 'url' => 'https://example.com/e2e-composer'],
    ['kind' => 'qr_code', 'name' => '${QR_NAME}',
     'purpose' => '${PURPOSE_QR}', 'url' => 'https://example.com/e2e-composer-qr'],
  ]],
  'credits_spent' => 5,
]);
echo 'KITID=' . $kit->id;
`.trim();

  const out = runTinker(php);
  const m = out.match(/KITID=(\d+)/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return Number(m[1]);
}

test.describe("Brand Studio — kit composer (presets, caps, purposes)", () => {
  // Blade renders go over a distant RDS; lift the shared 60s ceiling.
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    kitId = seedProposalKit();
  });

  test("preset → capped steppers → purpose → payload → review chips", async ({
    page,
  }) => {
    test.setTimeout(180_000);
    await loginAsDemo(page);

    // Keep the auto-estimate watcher deterministic and free: it debounces a
    // POST to /estimate on every composition change.
    await page.route("**/user/brand-studio/estimate", (route: Route) =>
      route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ estimated_credits: 6, balance: 900 }),
      }),
    );

    await page.goto("/user/brand-studio", {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    await expect(
      page.getByRole("heading", { name: "AI Brand Studio" }),
    ).toBeVisible();

    await page.locator("textarea[maxlength='4000']").fill(BRIEF);

    // ── Preset seeds the composition: Launch pack = biolink×1 (Launch
    // landing page), short_link×3 (Campaign links), qr_code×2 (Poster QR
    // codes).
    await expect(page.locator("input[placeholder^='Purpose']")).toHaveCount(0);
    await page.getByRole("button", { name: "Launch pack" }).click();
    const purposeInputs = page.locator("input[placeholder^='Purpose']");
    await expect(purposeInputs).toHaveCount(3);
    await expect(purposeInputs.nth(0)).toHaveValue("Launch landing page");
    await expect(purposeInputs.nth(1)).toHaveValue("Campaign links");
    await expect(purposeInputs.nth(2)).toHaveValue("Poster QR codes");

    // Row = select + stepper + purpose input + remove button. The stepper's
    // count span sits between the − and + buttons.
    const rows = page.locator("div.flex.items-center.gap-2.flex-wrap", {
      has: page.locator("input[placeholder^='Purpose']"),
    });
    await expect(rows).toHaveCount(3);
    const bioRow = rows.nth(0);
    const bioCount = bioRow.locator("span.tabular-nums");
    const bioPlus = bioRow.getByRole("button", { name: "+", exact: true });
    await expect(bioCount).toHaveText("1");

    // ── Stepper is capped at the biolink kit cap (3): stepping past the cap
    // is a no-op.
    await bioPlus.click();
    await expect(bioCount).toHaveText("2");
    await bioPlus.click();
    await expect(bioCount).toHaveText(String(BIOLINK_CAP));
    await bioPlus.click(); // beyond the cap — must stay put
    await expect(bioCount).toHaveText(String(BIOLINK_CAP));

    const generate = page.getByRole("button", { name: "Generate plan" });
    await expect(generate).toBeEnabled();

    // ── Summed per-kind count over the cap trips the amber error and
    // disables Generate: "Add asset" appends another biolink×1 → 4 > 3.
    await page.getByRole("button", { name: "Add asset" }).click();
    await expect(rows).toHaveCount(4);
    const capError = page.getByText(
      `Too many Link in Bio pages — max ${BIOLINK_CAP} per kit.`,
    );
    await expect(capError).toBeVisible();
    await expect(generate).toBeDisabled();

    // Removing the offending row clears the error and re-enables Generate.
    await rows.nth(3).locator("button.text-red-300").click();
    await expect(rows).toHaveCount(3);
    await expect(capError).toBeHidden();
    await expect(generate).toBeEnabled();

    // ── Purpose input feeds the payload (and is trimmed).
    await purposeInputs.nth(0).fill(`  ${CUSTOM_PURPOSE}  `);

    // ── Intercept the plan POST (the one OpenAI-backed step), capture the
    // Alpine-built payload, redirect to the seeded proposal kit.
    let planPayload: Record<string, unknown> | null = null;
    await page.route("**/user/brand-studio/plan", async (route: Route) => {
      planPayload = route.request().postDataJSON() as Record<string, unknown>;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          credits_spent: 5,
          balance: 895,
          kit_id: kitId,
          redirect: `/user/brand-studio/${kitId}`,
        }),
      });
    });

    await Promise.all([
      page.waitForURL(`**/user/brand-studio/${kitId}`, { timeout: 60_000 }),
      generate.click(),
    ]);

    expect(planPayload).not.toBeNull();
    const p = planPayload!;
    expect(p["mode"]).toBe("kit");
    expect(p["request"]).toBe(BRIEF);
    expect(p["composition"]).toEqual([
      { kind: "biolink", count: BIOLINK_CAP, purpose: CUSTOM_PURPOSE },
      { kind: "short_link", count: 3, purpose: "Campaign links" },
      { kind: "qr_code", count: 2, purpose: "Poster QR codes" },
    ]);

    // ── Plan review renders the composition: assets with their purpose
    // chips, all keep[] pre-checked.
    await expect(page.getByText("Review the plan")).toBeVisible();
    await expect(page.getByText(BIO_TITLE)).toBeVisible();
    await expect(page.getByText(LINK_TITLE)).toBeVisible();
    await expect(page.getByText(QR_NAME)).toBeVisible();
    await expect(page.getByText(PURPOSE_BIO)).toBeVisible();
    await expect(page.getByText(PURPOSE_LINK)).toBeVisible();
    await expect(page.getByText(PURPOSE_QR)).toBeVisible();
    await expect(page.locator("input[name='keep[]']")).toHaveCount(3);
  });
});
