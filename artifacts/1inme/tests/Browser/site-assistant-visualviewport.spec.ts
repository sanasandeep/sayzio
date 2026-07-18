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

// Guards the Safari-iOS visible-viewport pinning added to the Ask Zio chat
// panel (saSyncPanelViewport in
// resources/views/common/partials/site-assistant.blade.php). Safari iOS animates
// its address/tab bars in and out as you scroll, shrinking the *visible*
// viewport mid-session; the mobile stylesheet fixes the panel at `100dvh - 100px`
// which clips the header/composer under the bars once they animate back in. The
// widget therefore listens on window.visualViewport and pins the OPEN panel's
// height to the true visible area on small screens, clearing the inline height
// on desktop / when closed / in browsers without visualViewport.
//
// This behaviour has no natural DOM trigger Playwright can fire (visualViewport
// height changes are not emulated by setViewportSize), so an addInitScript shim
// installs a controllable fake window.visualViewport whose height the test
// drives via window.__setVisualViewportHeight(). The specs assert:
//   - on mobile, the open panel's inline height tracks the reduced visual
//     viewport (vv.height - 100), NOT the full dvh, so the whole panel (header +
//     body + composer) fits inside the visible area; and
//   - on desktop the widget never applies an inline height (stylesheet 560px
//     sizing is left untouched), even when a visualViewport resize fires.

// The chat bootstrap/session is rate-limited via demo-login, so all tests share
// one logged-in context; each test gets a fresh page from it.
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

// Keep in lockstep with SA_VV_RESERVE in site-assistant.blade.php (the mobile
// `100dvh - 100px` reserve for the launcher gutter).
const SA_VV_RESERVE = 100;

/** Run a `php artisan tinker` snippet and assert it printed okToken. */
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

/** Log in as the demo user (non-prod quick-login) without blocking on the dashboard render. */
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
        visitor_token: "e2e-vv-token",
        messages: [],
        greeting: "Hi, I'm Zio.",
        starter_prompts: [],
        page_suggestions: [],
        low_balance: null,
      }),
    }),
  );
}

/** Open the panel via the launcher and wait for the stubbed greeting. */
async function openPanel(page: Page): Promise<void> {
  await page.locator("#sa-launcher").click();
  await expect(page.locator("#sa-panel-wrap")).toHaveClass(/sa-open/);
  await expect(
    page.locator("#sa-body .sa-msg.assistant", { hasText: "Hi, I'm Zio." }),
  ).toBeVisible({ timeout: 30_000 });
}

/** The current pixel height of the panel's inline `style.height` (0 when unset). */
async function inlineHeight(page: Page): Promise<number> {
  return page.locator("#sa-panel").evaluate((el) => {
    const h = (el as HTMLElement).style.height;
    return h ? parseFloat(h) : 0;
  });
}

test.beforeAll(async ({ browser }) => {
  seedDemoUser();
  sharedContext = await browser.newContext();
  // Install a controllable fake window.visualViewport for every page in the
  // context, BEFORE any page script runs (the widget reads window.visualViewport
  // at parse time to attach its resize/scroll listeners). height defaults to the
  // real innerHeight until a test overrides it via __setVisualViewportHeight().
  await sharedContext.addInitScript(() => {
    const et = new EventTarget();
    let override: number | null = null;
    let offsetTopOverride = 0;
    const vv = {
      get width() {
        return window.innerWidth;
      },
      get height() {
        return override == null ? window.innerHeight : override;
      },
      // offsetTop / pageTop shift when Safari iOS scrolls the focused input into
      // view under the software keyboard; the widget must anchor above the
      // keyboard using offsetTop + height, not the (unchanged) layout viewport.
      get offsetTop() {
        return offsetTopOverride;
      },
      offsetLeft: 0,
      get pageTop() {
        return offsetTopOverride;
      },
      pageLeft: 0,
      scale: 1,
      addEventListener: (...a: unknown[]) =>
        (et.addEventListener as (...args: unknown[]) => void)(...a),
      removeEventListener: (...a: unknown[]) =>
        (et.removeEventListener as (...args: unknown[]) => void)(...a),
      dispatchEvent: (e: Event) => et.dispatchEvent(e),
    };
    Object.defineProperty(window, "visualViewport", {
      configurable: true,
      get: () => vv,
    });
    (window as unknown as { __setVisualViewportHeight: (h: number) => void }).__setVisualViewportHeight =
      (h: number) => {
        override = h;
        offsetTopOverride = 0;
        vv.dispatchEvent(new Event("resize"));
      };
    // Simulate the software keyboard: shrink the visible viewport AND shift its
    // top offset (as iOS does when it scrolls the composer into view).
    (
      window as unknown as {
        __setVisualViewport: (h: number, offsetTop: number) => void;
      }
    ).__setVisualViewport = (h: number, offsetTop: number) => {
      override = h;
      offsetTopOverride = offsetTop;
      vv.dispatchEvent(new Event("resize"));
    };
    // Simulate the user SCROLLING the page while the keyboard is already open:
    // iOS fires only a `scroll` visualViewport event and shifts offsetTop WITHOUT
    // changing height. This exercises the widget's `scroll` listener specifically
    // (the `resize` helpers above never fire it), guarding against a refactor
    // that drops the scroll listener and lets the composer slip behind the
    // keyboard on scroll without failing any resize-driven test.
    (
      window as unknown as {
        __scrollVisualViewport: (offsetTop: number) => void;
      }
    ).__scrollVisualViewport = (offsetTop: number) => {
      offsetTopOverride = offsetTop;
      vv.dispatchEvent(new Event("scroll"));
    };
  });
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

test.describe("Ask Zio panel — visualViewport pinning (mobile)", () => {
  test.describe.configure({ timeout: 180_000 });

  test("the open panel height tracks the shrinking visible viewport, keeping header + composer in view", async ({
    page,
  }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    // With the full 800px visible area the panel is pinned to 800 - 100 = 700px.
    await expect
      .poll(() => inlineHeight(page), { timeout: 10_000 })
      .toBe(800 - SA_VV_RESERVE);

    // Now the Safari address/tab bars animate IN: the visible viewport shrinks
    // to 520px while the layout viewport (innerHeight) stays 800px.
    await page.evaluate(() =>
      (window as unknown as { __setVisualViewportHeight: (h: number) => void }).__setVisualViewportHeight(
        520,
      ),
    );

    // The panel must re-pin to the REDUCED visible area (520 - 100 = 420px), not
    // stay at the full-dvh 700px that would clip the composer under the bars.
    await expect
      .poll(() => inlineHeight(page), { timeout: 10_000 })
      .toBe(520 - SA_VV_RESERVE);

    // The whole panel now fits inside the visible viewport, so both the header
    // and the composer are on-screen and rendered.
    const rendered = await page
      .locator("#sa-panel")
      .evaluate((el) => el.getBoundingClientRect().height);
    expect(rendered).toBeLessThanOrEqual(520);
    await expect(page.locator("#sa-panel .sa-header").first()).toBeVisible();
    await expect(page.locator("#sa-panel .sa-input-row").first()).toBeVisible();
  });

  test("the pinned height never collapses below the 240px floor", async ({
    page,
  }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    // A tiny visible area (e.g. keyboard + bars) must clamp at the 240px floor,
    // not go to 300 - 100 = 200 (or negative), so the panel is always usable.
    await page.evaluate(() =>
      (window as unknown as { __setVisualViewportHeight: (h: number) => void }).__setVisualViewportHeight(
        300,
      ),
    );
    await expect.poll(() => inlineHeight(page), { timeout: 10_000 }).toBe(240);
  });

  test("the panel stays anchored above the software keyboard when the visible viewport is offset", async ({
    page,
  }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    // Baseline: full visible area, no keyboard, panel pinned to 800 - 100.
    await expect
      .poll(() => inlineHeight(page), { timeout: 10_000 })
      .toBe(800 - SA_VV_RESERVE);

    // The user taps the composer: iOS opens the keyboard, which both shrinks the
    // visible viewport (height 500) AND shifts its top (offsetTop 40) as Safari
    // scrolls the focused input into view. The occluded keyboard region is
    // therefore [offsetTop + height, innerHeight] = [540, 800].
    const KB_HEIGHT = 500;
    const KB_OFFSET_TOP = 40;
    const visibleBottom = KB_OFFSET_TOP + KB_HEIGHT; // 540
    await page.evaluate(
      ([h, top]) =>
        (
          window as unknown as {
            __setVisualViewport: (h: number, offsetTop: number) => void;
          }
        ).__setVisualViewport(h, top),
      [KB_HEIGHT, KB_OFFSET_TOP],
    );

    // Height still tracks the reduced visible viewport (500 - 100 = 400).
    await expect
      .poll(() => inlineHeight(page), { timeout: 10_000 })
      .toBe(KB_HEIGHT - SA_VV_RESERVE);

    // The composer must be lifted out from behind the keyboard: its bottom edge
    // sits at or above the visible bottom, and its top stays below the visible
    // top — i.e. the whole composer row is inside [offsetTop, offsetTop+height].
    const composerRect = () =>
      page
        .locator("#sa-panel .sa-input-row")
        .first()
        .evaluate((el) => {
          const r = el.getBoundingClientRect();
          return { top: r.top, bottom: r.bottom };
        });

    await expect
      .poll(async () => (await composerRect()).bottom <= visibleBottom, {
        timeout: 10_000,
      })
      .toBe(true);

    const rect = await composerRect();
    expect(rect.bottom).toBeLessThanOrEqual(visibleBottom);
    expect(rect.top).toBeGreaterThanOrEqual(KB_OFFSET_TOP);
    await expect(page.locator("#sa-panel .sa-input-row").first()).toBeVisible();

    // When the keyboard dismisses (visible viewport restored) the lift is
    // released so the panel returns to its normal anchored position.
    await page.evaluate(
      ([h, top]) =>
        (
          window as unknown as {
            __setVisualViewport: (h: number, offsetTop: number) => void;
          }
        ).__setVisualViewport(h, top),
      [800, 0],
    );
    await expect
      .poll(
        () =>
          page
            .locator("#sa-panel-wrap")
            .evaluate((el) => (el as HTMLElement).style.transform || ""),
        { timeout: 10_000 },
      )
      .toBe("");
  });

  test("the composer re-anchors when the page scrolls with the keyboard already up", async ({
    page,
  }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    // The keyboard opens: visible viewport shrinks to 500 and shifts down 40px,
    // so the visible area is [40, 540]. This fires a `resize` event.
    const KB_HEIGHT = 500;
    const INITIAL_OFFSET_TOP = 40;
    await page.evaluate(
      ([h, top]) =>
        (
          window as unknown as {
            __setVisualViewport: (h: number, offsetTop: number) => void;
          }
        ).__setVisualViewport(h, top),
      [KB_HEIGHT, INITIAL_OFFSET_TOP],
    );

    const composerRect = () =>
      page
        .locator("#sa-panel .sa-input-row")
        .first()
        .evaluate((el) => {
          const r = el.getBoundingClientRect();
          return { top: r.top, bottom: r.bottom };
        });

    // Composer sits inside the initial visible area [40, 540].
    await expect
      .poll(async () => (await composerRect()).bottom <= INITIAL_OFFSET_TOP + KB_HEIGHT, {
        timeout: 10_000,
      })
      .toBe(true);

    // Now the user SCROLLS the page while the keyboard stays open. iOS fires ONLY
    // a `scroll` visualViewport event and shifts offsetTop further (40 -> 120)
    // WITHOUT changing height. The visible area becomes [120, 620]. If the widget
    // ignored the `scroll` event the composer would still be pinned to the old
    // [40, 540] window and slip behind the keyboard.
    const SCROLLED_OFFSET_TOP = 120;
    const scrolledBottom = SCROLLED_OFFSET_TOP + KB_HEIGHT; // 620
    await page.evaluate(
      (top) =>
        (
          window as unknown as {
            __scrollVisualViewport: (offsetTop: number) => void;
          }
        ).__scrollVisualViewport(top),
      SCROLLED_OFFSET_TOP,
    );

    // Height is unchanged by a pure scroll (still 500 - 100 = 400).
    await expect
      .poll(() => inlineHeight(page), { timeout: 10_000 })
      .toBe(KB_HEIGHT - SA_VV_RESERVE);

    // After the scroll the whole composer row must sit inside the NEW visible
    // window [offsetTop, offsetTop + height] = [120, 620].
    await expect
      .poll(async () => (await composerRect()).bottom <= scrolledBottom, {
        timeout: 10_000,
      })
      .toBe(true);

    const rect = await composerRect();
    expect(rect.bottom).toBeLessThanOrEqual(scrolledBottom);
    expect(rect.top).toBeGreaterThanOrEqual(SCROLLED_OFFSET_TOP);
    await expect(page.locator("#sa-panel .sa-input-row").first()).toBeVisible();
  });
});

test.describe("Ask Zio panel — no viewport pinning (desktop)", () => {
  test.describe.configure({ timeout: 180_000 });

  test("no inline height is applied above 480px, even when visualViewport resizes", async ({
    page,
  }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    // Desktop keeps the stylesheet's 560px sizing — no inline override on open.
    expect(await inlineHeight(page)).toBe(0);

    // A visualViewport resize on desktop must NOT start pinning the height.
    await page.evaluate(() =>
      (window as unknown as { __setVisualViewportHeight: (h: number) => void }).__setVisualViewportHeight(
        520,
      ),
    );
    await page.waitForTimeout(200);
    expect(await inlineHeight(page)).toBe(0);
  });
});
