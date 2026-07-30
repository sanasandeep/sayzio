import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test as base, type BrowserContext, type Page } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

/**
 * Guards the pure client-side (Alpine) low-contrast warning on the
 * Default Colors settings tab. A Blade attribute truncation or Alpine init
 * failure would silently remove the warning with no server error — this spec
 * catches that:
 *
 * - All-inherit state: neither warning badge renders.
 * - #eeeeee text on #ffffff background → the text warning badge
 *   (data-testid="contrast-warning-text") appears with a "N:1" ratio.
 * - Changing text to #000000 hides it again.
 * - The accent warning stays hidden the whole time (accent fields inherit).
 */

// Shared logged-in context (demo-login is throttled at 5/min).
let sharedContext: BrowserContext;
const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

const ALIAS = "e2e-default-colors-contrast";

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
    'alias' => '${ALIAS}', 'title' => 'E2E Default Colors Contrast', 'is_active' => true,
  ]);
}
// The Default Colors tab only renders on a template design-session draft
// (settings['_template_draft'] must be an array — the controller redirects
// to Appearance otherwise). Also re-own + reset to the all-inherit state so
// a previous run's saved colors can't leak into the "no warning at rest"
// assertion.
$bio->user_id = $u->id;
$bio->workspace_id = $ws?->id;
$settings = $bio->settings ?? [];
unset($settings['biolink']['template_default_colors']);
$settings['_template_draft'] = $settings['_template_draft'] ?? [
  'template_id' => 0,
  'template_name' => 'E2E Contrast Draft',
  'started_at' => now()->toIso8601String(),
];
$bio->settings = $settings;
$bio->save();
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

// Field rows appear in the blade @foreach order.
const FIELD_ORDER = [
  "text_color",
  "bg_color",
  "border_color",
  "accent_color",
  "accent_text_color",
] as const;

function fieldRow(page: Page, key: (typeof FIELD_ORDER)[number]) {
  return page
    .locator("form .space-y-4 > div")
    .nth(FIELD_ORDER.indexOf(key));
}

/**
 * Set a field to an explicit hex color through the real UI path: click the
 * "Inherit — click to set" button if the field is still inheriting, then set
 * the <input type=color> value and dispatch a real `input` event so the
 * Alpine `@input` binding updates state (fill() doesn't work on color inputs).
 */
async function setColor(
  page: Page,
  key: (typeof FIELD_ORDER)[number],
  hex: string,
) {
  const row = fieldRow(page, key);
  const inheritBtn = row.getByRole("button", { name: /Inherit — click to set/ });
  if (await inheritBtn.isVisible().catch(() => false)) {
    await inheritBtn.click();
  }
  const colorInput = row.locator('input[type="color"]');
  await expect(colorInput).toBeVisible({ timeout: 10_000 });
  await colorInput.evaluate((el, value) => {
    const input = el as HTMLInputElement;
    input.value = value;
    input.dispatchEvent(new Event("input", { bubbles: true }));
  }, hex);
  // The mono swatch label mirrors Alpine state — proves the binding took.
  await expect(row.locator("span.font-mono")).toHaveText(hex);
}

test("low-contrast warning appears for a bad pair and clears when fixed", async ({
  page,
}) => {
  test.setTimeout(300_000);

  await page.goto(`/user/links/${fixture.linkId}/settings/default-colors`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });

  const textWarning = page.getByTestId("contrast-warning-text");
  const accentWarning = page.getByTestId("contrast-warning-accent");

  // Sanity: the Alpine component actually initialized (preview card visible,
  // all five rows offering "Inherit — click to set").
  await expect(page.getByText("Text on background")).toBeVisible({
    timeout: 60_000,
  });
  await expect(
    page.getByRole("button", { name: /Inherit — click to set/ }),
  ).toHaveCount(5);

  // All-inherit state: no warnings at all (ratio is null → hidden).
  await expect(textWarning).toHaveCount(0);
  await expect(accentWarning).toHaveCount(0);

  // Low-contrast pair: #eeeeee text on #ffffff background (~1.2:1).
  await setColor(page, "text_color", "#eeeeee");
  await setColor(page, "bg_color", "#ffffff");

  await expect(textWarning).toBeVisible();
  await expect(textWarning).toContainText("Low contrast");
  // The badge must include a computed ratio like "1.2:1".
  await expect(textWarning).toContainText(/\d+(\.\d+)?:1/);

  // Accent fields are still all-inherit → no accent warning.
  await expect(accentWarning).toHaveCount(0);

  // Fixing the text color to #000000 (~21:1) clears the warning.
  await setColor(page, "text_color", "#000000");
  await expect(textWarning).toHaveCount(0);
  await expect(accentWarning).toHaveCount(0);
});

test("accent warning appears with inherited accent text and clears when fixed", async ({
  page,
}) => {
  test.setTimeout(300_000);

  await page.goto(`/user/links/${fixture.linkId}/settings/default-colors`, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });

  const textWarning = page.getByTestId("contrast-warning-text");
  const accentWarning = page.getByTestId("contrast-warning-accent");

  await expect(page.getByText("Button text on accent")).toBeVisible({
    timeout: 60_000,
  });

  // Fresh page load starts from the saved (all-inherit) state, so both
  // warnings are hidden — the previous test never submitted the form.
  await expect(accentWarning).toHaveCount(0);
  await expect(textWarning).toHaveCount(0);

  // Asymmetric defaults: with only accent_color set to #ffffff, the accent
  // text falls back to #ffffff (white on white, 1:1) → warning must appear.
  await setColor(page, "accent_color", "#ffffff");

  await expect(accentWarning).toBeVisible();
  await expect(accentWarning).toContainText("Low contrast");
  await expect(accentWarning).toContainText(/\d+(\.\d+)?:1/);

  // The text warning is unaffected by accent fields.
  await expect(textWarning).toHaveCount(0);

  // Setting accent_text_color to #000000 (~21:1 on white) clears it.
  await setColor(page, "accent_text_color", "#000000");
  await expect(accentWarning).toHaveCount(0);
  await expect(textWarning).toHaveCount(0);
});
