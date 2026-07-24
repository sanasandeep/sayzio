import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test as base, type BrowserContext } from "@playwright/test";

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

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

function seedDemoUser(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Plan;
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
echo 'OK';
`;
  let lastErr: unknown;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
        cwd: ARTIFACT_ROOT,
        encoding: "utf8",
      });
      return;
    } catch (err) {
      lastErr = err;
    }
  }
  throw lastErr;
}

test.beforeAll(async ({ browser }) => {
  seedDemoUser();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

test("selecting a QR template applies to preview, highlight, and export state", async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on("console", (msg) => {
    if (msg.type() === "error") consoleErrors.push(msg.text());
  });

  await page.goto("/user/qr-codes/create", {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });

  // Preview renders once the engine loads.
  const preview = page.locator("#qrTarget svg");
  await expect(preview).toBeVisible({ timeout: 60_000 });

  // Templates tab is the default; the Midnight card must have a rendered thumb.
  const midnightCard = page
    .locator(".qr-template-card", { hasText: "Midnight" })
    .first();
  await expect(midnightCard.locator("#qr-tpl-thumb-midnight svg")).toBeVisible({
    timeout: 60_000,
  });

  const svgBefore = await page.locator("#qrTarget").innerHTML();
  const designBefore = await page
    .locator('input[name="design_json"]')
    .inputValue();
  const before = JSON.parse(designBefore);
  expect(before.fg_color).not.toBe("#e2e8f0");

  await midnightCard.click();

  // 1) Active highlight on the selected card.
  await expect(midnightCard).toHaveClass(/active/, { timeout: 15_000 });

  // 2) Design state (source for PNG/SVG downloads and save) reflects the preset.
  await expect
    .poll(async () => {
      const raw = await page.locator('input[name="design_json"]').inputValue();
      const d = JSON.parse(raw);
      return {
        fg: d.fg_color,
        bg: d.bg_color,
        dot: d.dot_style,
        eye: d.corner_square_style,
      };
    })
    .toEqual({
      fg: "#e2e8f0",
      bg: "#0b1226",
      dot: "rounded",
      eye: "extra-rounded",
    });

  // 3) Live preview re-rendered with the template's colors.
  await expect
    .poll(async () => page.locator("#qrTarget").innerHTML())
    .not.toBe(svgBefore);
  await expect
    .poll(async () => page.locator("#qrTarget").innerHTML())
    .toContain("#e2e8f0");

  // 4) SVG download matches the applied template.
  const downloadPromise = page.waitForEvent("download", { timeout: 30_000 });
  await page.getByRole("button", { name: /SVG/ }).click();
  const download = await downloadPromise;
  const stream = await download.createReadStream();
  const chunks: Buffer[] = [];
  for await (const chunk of stream) chunks.push(chunk as Buffer);
  const svgContent = Buffer.concat(chunks).toString("utf8");
  expect(svgContent).toContain("<svg");
  expect(svgContent).toContain("#e2e8f0");
  expect(svgContent).toContain("#0b1226");

  // 5) Other tabs still apply: switch a dot style from the Shapes tab.
  await page.getByRole("button", { name: "Shapes", exact: true }).click();
  const dotThumb = page.locator('.qr-shape-card[title="dot"]').first();
  if (await dotThumb.count()) {
    const htmlBefore = await page.locator("#qrTarget").innerHTML();
    await dotThumb.click();
    await expect
      .poll(async () => page.locator("#qrTarget").innerHTML())
      .not.toBe(htmlBefore);
    // Shape thumbnails render with the current (template) foreground color.
    const thumbSvg = await dotThumb.innerHTML();
    expect(thumbSvg).toContain("svg");
  }

  const relevantErrors = consoleErrors.filter(
    (t) => !/favicon|net::|Failed to load resource/i.test(t),
  );
  expect(relevantErrors).toEqual([]);
});
