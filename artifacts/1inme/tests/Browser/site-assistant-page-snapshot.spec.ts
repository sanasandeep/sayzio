import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
  type Route,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

// Guards the web Ask Zio widget's "include a page snapshot" (vision) flow in
// resources/views/common/partials/site-assistant.blade.php:
//   - the camera button captures the visible page (html2canvas), downscales it
//     and shows an attached-snapshot chip;
//   - the NEXT send includes a `screenshot` (JPEG data URL) in the
//     /assistant/stream POST body, and the pending snapshot is cleared;
//   - a follow-up send WITHOUT re-attaching sends no screenshot;
//   - a `vision.notice` on the terminal done frame (the plan-gate refusal for
//     free users) renders as a small note under the reply.
//
// html2canvas is stubbed via addInitScript (deterministic, no 200KB vendor
// parse per test), so the spec exercises the widget's own wiring: lazy-load
// hook, compression, chip lifecycle, request payload, and notice rendering.

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

function tinker(php: string, okToken: string): void {
  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  if (!out.includes(okToken)) {
    throw new Error(`tinker snippet failed; expected ${okToken}, output:\n` + out);
  }
}

/** Idempotently ensure the onboarded demo user exists (so the app-surface Zio launcher renders). */
function seedDemoUser(): void {
  tinker(
    `
use App\\Modules\\User\\Models\\User;
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
app(WorkspaceContext::class)->resolve($u);
echo 'SEED_OK';
`.trim(),
    "SEED_OK",
  );
}

/** Stub the chat bootstrap/session so opening the panel is deterministic and fast. */
async function stubChat(page: Page): Promise<void> {
  await page.route("**/assistant/bootstrap*", (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ greeting: "Hi, I'm Zio.", templates: [] }),
    }),
  );
  await page.route("**/assistant/session", (route: Route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        ok: true,
        visitor_token: "e2e-snap-token",
        messages: [],
        greeting: "Hi, I'm Zio.",
        starter_prompts: [],
        page_suggestions: [],
        low_balance: null,
      }),
    }),
  );
}

/** SSE body for a terminal done frame (optionally carrying a vision payload). */
function sseDone(text: string, vision?: { used: boolean; notice: string | null }): string {
  const done: Record<string, unknown> = {
    ok: true,
    assistant_message: { id: 1, role: "assistant", content: text },
    handed_off: false,
  };
  if (vision) done.vision = vision;
  return `event: done\ndata: ${JSON.stringify(done)}\n\n`;
}

async function openPanel(page: Page): Promise<void> {
  await page.locator("#sa-launcher").click();
  await expect(page.locator("#sa-panel-wrap")).toHaveClass(/sa-open/);
  await expect(
    page.locator("#sa-body .sa-msg.assistant", { hasText: "Hi, I'm Zio." }),
  ).toBeVisible({ timeout: 30_000 });
}

test.beforeAll(async ({ browser }) => {
  seedDemoUser();
  sharedContext = await browser.newContext();
  // Deterministic html2canvas stub installed before any page script runs. The
  // widget lazy-loads the vendored file only when window.html2canvas is absent,
  // so pre-defining it short-circuits the <script> injection entirely.
  await sharedContext.addInitScript(() => {
    (window as unknown as { html2canvas: unknown }).html2canvas = () => {
      const c = document.createElement("canvas");
      c.width = 320;
      c.height = 200;
      const ctx = c.getContext("2d")!;
      ctx.fillStyle = "#123456";
      ctx.fillRect(0, 0, 320, 200);
      return Promise.resolve(c);
    };
  });
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

test.describe("Ask Zio widget — page snapshot (vision) affordance", () => {
  test.describe.configure({ timeout: 180_000 });

  test("attach → chip shows; send carries screenshot once; refusal notice renders", async ({
    page,
  }) => {
    await stubChat(page);

    const streamBodies: Array<Record<string, unknown>> = [];
    await page.route("**/assistant/stream", (route: Route) => {
      streamBodies.push(route.request().postDataJSON() as Record<string, unknown>);
      const withShot = typeof streamBodies[streamBodies.length - 1].screenshot === "string";
      return route.fulfill({
        status: 200,
        contentType: "text/event-stream",
        body: withShot
          ? sseDone("Here's what I can see.", {
              used: false,
              notice:
                "Analyzing page images is available on higher plans — I answered from the page text instead.",
            })
          : sseDone("Plain text answer."),
      });
    });

    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    // The composer offers the snapshot affordance.
    const snapBtn = page.locator("#sa-panel .sa-snap");
    await expect(snapBtn).toBeVisible();

    // Attaching shows the chip + the on-state.
    await snapBtn.click();
    const chip = page.locator("#sa-snap-chip");
    await expect(chip).toBeVisible();
    await expect(chip).toContainText("Page snapshot attached");
    await expect(snapBtn).toHaveClass(/sa-snap-on/);

    // Send: the stream POST must carry the JPEG data URL; the chip clears.
    await page.locator("#sa-input").fill("What is on this page?");
    await page.locator("#sa-send").click();
    await expect(
      page.locator("#sa-body .sa-msg.assistant", { hasText: "Here's what I can see." }),
    ).toBeVisible({ timeout: 15_000 });
    expect(streamBodies.length).toBe(1);
    expect(String(streamBodies[0].screenshot)).toMatch(/^data:image\/jpeg;base64,/);
    await expect(chip).toBeHidden();
    await expect(snapBtn).not.toHaveClass(/sa-snap-on/);

    // The server-side refusal notice (free plan) renders under the reply.
    await expect(page.locator("#sa-body .sa-vision-note")).toContainText(
      "available on higher plans",
    );

    // A follow-up message WITHOUT re-attaching sends no screenshot.
    await page.locator("#sa-input").fill("And now?");
    await page.locator("#sa-send").click();
    await expect(
      page.locator("#sa-body .sa-msg.assistant", { hasText: "Plain text answer." }),
    ).toBeVisible({ timeout: 15_000 });
    expect(streamBodies.length).toBe(2);
    expect(streamBodies[1].screenshot).toBeUndefined();
  });

  test("the × on the chip discards the pending snapshot before send", async ({ page }) => {
    await stubChat(page);
    const streamBodies: Array<Record<string, unknown>> = [];
    await page.route("**/assistant/stream", (route: Route) => {
      streamBodies.push(route.request().postDataJSON() as Record<string, unknown>);
      return route.fulfill({
        status: 200,
        contentType: "text/event-stream",
        body: sseDone("Answered without an image."),
      });
    });

    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    const snapBtn = page.locator("#sa-panel .sa-snap");
    await snapBtn.click();
    const chip = page.locator("#sa-snap-chip");
    await expect(chip).toBeVisible();

    // Discard via the chip's remove button.
    await chip.locator("button").click();
    await expect(chip).toBeHidden();
    await expect(snapBtn).not.toHaveClass(/sa-snap-on/);

    await page.locator("#sa-input").fill("No image please");
    await page.locator("#sa-send").click();
    await expect(
      page.locator("#sa-body .sa-msg.assistant", { hasText: "Answered without an image." }),
    ).toBeVisible({ timeout: 15_000 });
    expect(streamBodies.length).toBe(1);
    expect(streamBodies[0].screenshot).toBeUndefined();
  });
});
