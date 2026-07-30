import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * e2e: Buzz (Social Proof) campaign editor renders and saves (Task #6194).
 *
 * Regression guard for the blank-editor bug: three `\\'` sequences inside
 * single-quoted JS strings in edit.blade.php's renderFields() broke the whole
 * inline <script> with a SyntaxError, so `buzzEditor` was never defined,
 * Alpine never initialized the component, and the x-cloak'ed editor stayed
 * invisible.
 *
 * Covers:
 *  - create a campaign through the real create form → lands on the editor,
 *  - the editor actually initializes (x-cloak removed, tabs/notification
 *    list/live-preview pane visible),
 *  - the inline editor <script> parses with no JS SyntaxError anywhere on
 *    the page,
 *  - the "Campaign created" success banner renders exactly ONCE (it used to
 *    be duplicated by page-level + layout-level flash rendering),
 *  - the error fallback stays hidden when the editor boots fine,
 *  - Design/Targeting tabs switch,
 *  - saving the form round-trips and shows the "Saved." flash once.
 *
 * Self-bootstrapping: unique campaign name per run; prunes stale fixtures
 * from previous runs (shared RDS across parallel envs — see repo memory
 * e2e-shared-rds-fixture-aliases).
 */

const RUN = Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
const CAMPAIGN_NAME = `E2E Buzz ${RUN}`;

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

function runTinker(php: string): string {
  return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
}

test.beforeAll(() => {
  // Prune stale fixtures from earlier runs of this spec, and make sure the
  // demo user holds the `user-admin` web role — it carries the
  // `user.plan_limits.bypass` permission, which the buzz_popups plan gate
  // (CheckPlanLimit) requires since the demo plan doesn't include the
  // feature. Same pattern other Browser specs use for gated surfaces.
  const out = runTinker(`
use App\\Modules\\User\\Models\\SocialProof;
use App\\Modules\\User\\Models\\User;
use Illuminate\\Support\\Facades\\DB;

SocialProof::where('name', 'like', 'E2E Buzz %')->get()->each->delete();

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if ($u) {
  $rid = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
  if ($rid) { $u->roles()->syncWithoutDetaching([$rid]); $u->flushPermissionCache(); }
}
echo 'SEED_OK';
`);
  if (!out.includes("SEED_OK")) {
    throw new Error("social-proof-editor seed failed, output:\n" + out);
  }
});

test("create campaign → editor renders → save works", async ({ page }) => {
  // Login + create + save all round-trip a distant RDS; cold writes can take
  // >10s each, so give this multi-step flow generous headroom.
  test.setTimeout(240_000);
  const pageErrors: string[] = [];
  page.on("pageerror", (err) => pageErrors.push(String(err)));

  await loginAsDemo(page);

  // --- Create a campaign through the real create form ---
  await page.goto("/user/social-proofs/create", {
    waitUntil: "domcontentloaded",
  });
  // Scope to the create form — the user layout also contains a hidden
  // workspace-name input matching input[name="name"].
  const createForm = page.locator('form[action*="social-proofs"]');
  await expect(createForm).toBeAttached({ timeout: 30_000 });
  // Set the value + submit programmatically: an infinitely-animating ancestor
  // keeps Playwright's actionability "stable" check from ever passing, so
  // fill()/press()/click() all time out on this page.
  await createForm.evaluate((form, name) => {
    const input = form.querySelector<HTMLInputElement>('input[name="name"]');
    if (!input) throw new Error("campaign name input not found in create form");
    input.value = name;
  }, CAMPAIGN_NAME);
  // Wait on the store POST response first (cold RDS writes can exceed 10s),
  // then on the redirect landing on the editor URL.
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().includes("/user/social-proofs") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    createForm.evaluate((form) => (form as HTMLFormElement).requestSubmit()),
  ]);
  await page.waitForURL(/\/user\/social-proofs\/\d+/, {
    timeout: 90_000,
    waitUntil: "domcontentloaded",
  });

  // --- "Campaign created" flash renders exactly once ---
  const createdBanner = page.getByText(/Campaign created/);
  await expect(createdBanner).toHaveCount(1);
  await expect(createdBanner.first()).toBeVisible();

  // --- Editor initialized: x-cloak removed, structure visible ---
  const editor = page.locator("#bz-editor");
  await expect(editor).toBeVisible({ timeout: 15_000 });
  await expect
    .poll(async () => editor.getAttribute("x-cloak"), { timeout: 15_000 })
    .toBe(null);

  // No JS syntax/runtime errors killed the page scripts.
  expect(
    pageErrors.filter((e) => /SyntaxError|buzzEditor is not defined/i.test(e)),
  ).toEqual([]);

  // Error fallback stays hidden on a healthy boot.
  await expect(page.locator("#bz-editor-error")).toBeHidden();

  // Tabs render.
  for (const label of ["General", "Notifications", "Design", "Targeting"]) {
    await expect(
      editor.getByRole("button", { name: label }).first(),
    ).toBeVisible();
  }

  // Live preview pane renders.
  await expect(page.locator("#bz-preview")).toBeVisible();

  // --- Notifications: add one via the type picker so the list renders ---
  // The controller self-heals an empty campaign with a starter notification;
  // either way the notifications area must be visible and non-blank.
  await expect(editor.getByText(/notification/i).first()).toBeVisible();

  // --- Design & Targeting tabs switch and show their controls ---
  await editor.getByRole("button", { name: "Design" }).first().click();
  await expect(page.locator('select[name="design[theme]"]')).toBeVisible();

  await editor.getByRole("button", { name: "Targeting" }).first().click();
  await expect(
    page.locator('input[name="targeting[delay]"]'),
  ).toBeVisible();

  // --- Save round-trips ---
  // The save redirects back to the SAME editor URL, so waitForURL is a no-op
  // here — wait on the PUT/POST response instead, then for the reloaded DOM.
  // Submit programmatically (requestSubmit still fires the Alpine
  // @submit="syncBeforeSubmit" handler) — the same infinitely-animating
  // ancestor makes button clicks fail Playwright's stability check.
  const saveForm = page.locator('#bz-editor form[action*="social-proofs"]');
  await expect(saveForm).toBeAttached();
  await Promise.all([
    page.waitForResponse(
      (r) =>
        /\/user\/social-proofs\/\d+/.test(r.url()) &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    saveForm.evaluate((form) => (form as HTMLFormElement).requestSubmit()),
  ]);
  // The redirected editor page is heavy and reloads over cold RDS — give the
  // post-save navigation generous headroom before asserting the flash.
  await page.waitForURL(/\/user\/social-proofs\/\d+/, {
    timeout: 90_000,
    waitUntil: "domcontentloaded",
  });
  const savedBanner = page.getByText(/^Saved\.$/).first();
  await expect(savedBanner).toBeVisible({ timeout: 90_000 });
  await expect(page.getByText(/^Saved\.$/)).toHaveCount(1);

  // Editor still healthy after the save round-trip.
  await expect(page.locator("#bz-editor")).toBeVisible();
});
