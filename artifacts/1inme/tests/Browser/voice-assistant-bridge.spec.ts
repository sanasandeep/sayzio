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
import { loginAsDemoAdmin } from "./login-as-demo-admin";

// All tests share a single logged-in browser context (the demo-login route is
// rate-limited at throttle:5,1, so a login per test would trip the limit). Each
// test gets a fresh page from that context; the suite runs serially.
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

/**
 * Idempotently ensure the demo user exists (onboarded, with a workspace and the
 * user-admin role) and TURN ON the Voice Assistant for every plan. The floating
 * mic widget only renders when AiEngineSettings::voiceAllowedFor() is true, and
 * with an empty allow-list that just needs ai.voice.enabled = true. We never set
 * any API key — the turn endpoint is mocked in the browser, so no real Whisper /
 * GPT / ElevenLabs call ever happens.
 *
 * Done via `php artisan tinker` so the spec is self-bootstrapping on a fresh
 * runner; it only needs the Laravel app running with migrations applied.
 */
function seedVoiceFixture(): void {
  // NOTE: this string is passed straight to `tinker --execute=`. In a JS
  // template literal `\\` becomes the single backslash PHP namespaces need
  // (App\Modules\...), while `$var` stays literal.
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Services\\WorkspaceContext;
use App\\Services\\AI\\AiEngineSettings;
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
// Mark onboarded so the post-login RedirectToOnboarding soft gate doesn't bounce
// us through the onboarding wizard before the page we want (active-user state).
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }
app(WorkspaceContext::class)->resolve($u);

// Voice on for everyone: empty allow-list == all plans. AppSetting::put forgets
// its own cache key, so the already-warm server re-reads the new value.
AiEngineSettings::setVoiceEnabled(true);
AiEngineSettings::setVoiceEnabledPlans([]);

echo 'VOICE_OK';
`.trim();

  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  if (!out.includes("VOICE_OK")) {
    throw new Error("Voice fixture seed failed, output:\n" + out);
  }
}

/**
 * Idempotently ensure the demo ADMIN exists and is active so the admin
 * demo-login route (`/admin/demo-login`, non-prod only) can log the shared
 * context onto the admin guard. The admin layout is the surface that still
 * hosts the floating voice widget (the user layout suppresses it via
 * voiceFloating=false since the Zio-panel merge). Mirrors the seeding in
 * admin-sidebar-findbar.spec.ts.
 */
function seedDemoAdminFixture(): void {
  const php = `
use App\\Modules\\Admin\\Models\\Admin;
use App\\Modules\\Admin\\Models\\Role;
use Illuminate\\Support\\Facades\\Hash;

$role = Role::firstOrCreate(
  ['slug' => 'super-admin'],
  ['name' => 'Super Admin', 'guard' => 'admin']
);
$a = Admin::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$a) {
  $a = Admin::create([
    'name' => 'Admin', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'role_id' => $role->id,
    'status' => 'active',
  ]);
}
$a->status = 'active';
if (!$a->role_id) { $a->role_id = $role->id; }
$a->save();

echo 'ADMIN_OK';
`.trim();

  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  if (!out.includes("ADMIN_OK")) {
    throw new Error("Demo admin fixture seed failed, output:\n" + out);
  }
}

/**
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS and waits only for the demo-login POST response (not the redirect
 * target render), so the heavy post-login dashboard render never blocks the
 * suite. Mirrors the palette-dnd spec.
 */
/**
 * Open an authenticated page and wait until the floating Voice Assistant Alpine
 * component is mounted and ready to drive. These pages are cold Blade renders
 * over the distant RDS, so give navigation real headroom (mirrors palette-dnd).
 */
async function openWithWidget(page: Page, urlPath: string): Promise<void> {
  await page.goto(urlPath, { timeout: 120_000 });
  await page.waitForFunction(
    () => {
      const el = document.querySelector('[x-data^="voiceAssistant"]');
      const A = (window as unknown as { Alpine?: { $data: (e: Element) => unknown } }).Alpine;
      if (!el || !A) return false;
      const comp = A.$data(el) as { sendTurn?: unknown } | undefined;
      return !!(comp && typeof comp.sendTurn === "function");
    },
    undefined,
    { timeout: 120_000 },
  );
}

/**
 * Open an authenticated USER page and wait for the shared VoiceRuntime bridge
 * (included by the Zio panel — common/partials/voice-runtime). Since the
 * Zio-panel merge the floating widget no longer mounts on /user/* pages, so
 * page-surface tests drive the REAL bridge directly: applyToolResults()
 * dispatches the same `voice-action` window events both voice shells emit.
 */
async function openWithBridge(page: Page, urlPath: string): Promise<void> {
  await page.goto(urlPath, { timeout: 120_000 });
  await page.waitForFunction(
    () => {
      const rt = (window as unknown as {
        VoiceRuntime?: { applyToolResults?: unknown };
      }).VoiceRuntime;
      return !!(rt && typeof rt.applyToolResults === "function");
    },
    undefined,
    { timeout: 120_000 },
  );
}

/**
 * Push tool results through the real shared bridge on the page — the exact
 * code path both the admin floating widget and the Zio-panel mic use after a
 * turn (VoiceRuntime.applyToolResults → `voice-action` CustomEvent dispatch).
 */
async function applyToolResults(
  page: Page,
  results: Array<{ result: Record<string, unknown> }>,
): Promise<void> {
  await page.evaluate((toolResults) => {
    const rt = (window as unknown as {
      VoiceRuntime: {
        applyToolResults: (r: Array<{ result: Record<string, unknown> }>) => string | null;
      };
    }).VoiceRuntime;
    rt.applyToolResults(toolResults);
  }, results);
}

/** A single voice "turn" response carrying one tool result. */
type TurnBody = {
  transcript?: string;
  reply?: string;
  audio_base64?: string;
  tool_results?: Array<{ result: Record<string, unknown> }>;
  pending_confirmations?: unknown[];
  credits?: Record<string, number>;
  balance?: number;
};

/**
 * Mock the server side of a voice turn (STT + LLM + TTS) so the real widget
 * pipeline — fetch → parse → applyToolResults → dispatch `voice-action` /
 * pendingNav — runs against a deterministic response without any API call.
 */
async function mockTurn(page: Page, body: TurnBody): Promise<void> {
  await page.route("**/user/ai/voice/turn", async (route: Route) => {
    if (route.request().method() !== "POST") return route.fallback();
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        transcript: "test command",
        reply: "ok",
        pending_confirmations: [],
        credits: { stt: 1, llm: 1, tts: 1, total: 3 },
        balance: 100,
        ...body,
      }),
    });
  });
}

/** Trigger the real component's sendTurn() with a dummy blob (fire-and-forget). */
async function speak(page: Page): Promise<void> {
  await page.evaluate(() => {
    const el = document.querySelector('[x-data^="voiceAssistant"]')!;
    const A = (window as unknown as { Alpine: { $data: (e: Element) => { sendTurn: (b: Blob) => void } } }).Alpine;
    A.$data(el).sendTurn(new Blob(["x"], { type: "audio/webm" }));
  });
}

test.describe("voice assistant — client_action / voice-action bridge", () => {
  // Cold authenticated renders over the distant RDS push these past the default
  // 60s budget; give the suite real headroom (mirrors the editor specs).
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async ({ browser }) => {
    seedVoiceFixture();
    // The floating widget is ADMIN-ONLY since the Zio-panel merge, so the
    // shared context logs in on BOTH guards up front: the web session (plan
    // gate + user-page bridge tests) and the admin session (widget tests
    // open /admin). One login each keeps us under the demo-login throttle.
    seedDemoAdminFixture();
    sharedContext = await browser.newContext();
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await loginAsDemoAdmin(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test("a spoken 'select_link_type' picks the type and submits the Create form", async ({
    page,
  }) => {
    await openWithBridge(page, "/user/links/create");

    // Stub the form's destination so the native submit doesn't run the heavy
    // next page — we only care that the bridge selected the type and submitted.
    await page.route("**/user/links/choose-type", async (route: Route) => {
      if (route.request().method() !== "POST") return route.fallback();
      await route.fulfill({
        status: 200,
        contentType: "text/html",
        body: "<!doctype html><title>chose-type</title>chose-type-ok",
      });
    });

    const submit = page.waitForRequest(
      (r) =>
        r.method() === "POST" && r.url().includes("/user/links/choose-type"),
      { timeout: 60_000 },
    );
    // `url` is the radio value behind the "Short Link" card on the Create page.
    // Drive the REAL shared bridge (the same call both voice shells make after
    // a turn) — it dispatches the `voice-action` event the create form handles.
    await applyToolResults(page, [
      { result: { client_action: { type: "select_link_type", link_type: "url" } } },
    ]);

    // The create form's @voice-action handler set `type` and called $el.submit():
    // the POST carries the spoken type.
    const req = await submit;
    expect(req.postData() || "").toContain("type=url");
  });

  test("a spoken 'wizard_advance' clicks the wizard's forward submit", async ({
    page,
  }) => {
    await openWithBridge(page, "/user/links/wizard");

    // Stub the wizard step POST so goNext()'s native form submit doesn't run the
    // heavy save controller — we assert the forward submission fired.
    await page.route("**/user/links/wizard", async (route: Route) => {
      if (route.request().method() !== "POST") return route.fallback();
      await route.fulfill({
        status: 200,
        contentType: "text/html",
        body: "<!doctype html><title>wizard-advanced</title>wizard-advanced-ok",
      });
    });

    const advance = page.waitForRequest(
      (r) => r.method() === "POST" && r.url().endsWith("/user/links/wizard"),
      { timeout: 60_000 },
    );
    // Drive the REAL shared bridge — dispatches the `voice-action` event the
    // wizard's page-level listener handles (goNext clicks the forward submit).
    await applyToolResults(page, [
      { result: { client_action: { type: "wizard_advance", direction: "next" } } },
    ]);

    // Step 0 forwards by submitting a persona_group choice (goNext clicks the
    // form's forward submit button), so the advancing POST carries it.
    const req = await advance;
    expect(req.postData() || "").toContain("persona_group=");
  });

  test("a 'navigate_to' with spoken audio is DEFERRED until the reply finishes", async ({
    page,
  }) => {
    // The floating widget only mounts on the ADMIN layout now; the deferral
    // logic under test is widget-shell behavior, so drive it from /admin.
    await openWithWidget(page, "/admin");

    const target = "/user/links?voicenav=deferred";
    await page.route("**/user/links*", async (route: Route) => {
      const u = new URL(route.request().url());
      if (u.searchParams.get("voicenav") !== "deferred") return route.fallback();
      await route.fulfill({
        status: 200,
        contentType: "text/html",
        body: "<!doctype html><title>voicenav</title>voicenav-arrived",
      });
    });

    // A tiny but non-empty base64 payload so the widget takes the "has TTS audio"
    // branch (set src + play, defer nav) rather than the no-audio fast path.
    await mockTurn(page, {
      reply: "Opening your links",
      audio_base64: "AAAA",
      tool_results: [{ result: { navigate_to: target } }],
    });

    const before = page.url();
    const state = await page.evaluate(async (deferTarget) => {
      const el = document.querySelector('[x-data^="voiceAssistant"]')!;
      const A = (window as unknown as {
        Alpine: { $data: (e: Element) => { sendTurn: (b: Blob) => Promise<void>; pendingNav: string | null } };
      }).Alpine;
      const comp = A.$data(el);
      // Headless Chromium can't decode/play the tiny clip, and a rejected play()
      // would fall straight through to afterReply() — exactly the deferral we are
      // testing. Stub play() to resolve WITHOUT firing 'ended' so the nav stays
      // pending until we explicitly end the audio below.
      const audio = el.querySelector("audio") as HTMLAudioElement;
      audio.play = () => Promise.resolve();
      await comp.sendTurn(new Blob(["x"], { type: "audio/webm" }));
      return { pendingNav: comp.pendingNav, href: window.location.href };
    }, target);

    // Reply is "playing": the navigation is queued, not taken.
    expect(state.pendingNav).toContain("voicenav=deferred");
    expect(state.href).toBe(before);
    expect(page.url()).toBe(before);

    // End the spoken reply → the deferred navigation now fires.
    await page.evaluate(() => {
      const el = document.querySelector('[x-data^="voiceAssistant"]')!;
      const audio = el.querySelector("audio") as HTMLAudioElement;
      audio.dispatchEvent(new Event("ended"));
    });

    await page.waitForURL("**/user/links?voicenav=deferred", { timeout: 60_000 });
    await expect(page.getByText("voicenav-arrived")).toBeVisible({ timeout: 30_000 });
  });

  test("a 'navigate_to' with no spoken audio navigates immediately", async ({
    page,
  }) => {
    // Widget-shell behavior — the floating widget is admin-only now.
    await openWithWidget(page, "/admin");

    const target = "/user/links?voicenav=instant";
    await page.route("**/user/links*", async (route: Route) => {
      const u = new URL(route.request().url());
      if (u.searchParams.get("voicenav") !== "instant") return route.fallback();
      await route.fulfill({
        status: 200,
        contentType: "text/html",
        body: "<!doctype html><title>voicenav</title>voicenav-instant",
      });
    });

    // No audio_base64 → the widget runs the post-reply step at once (no waiting
    // on an 'ended' event), so navigation happens right away.
    await mockTurn(page, {
      reply: "Opening your links",
      tool_results: [{ result: { navigate_to: target } }],
    });

    await speak(page);

    await page.waitForURL("**/user/links?voicenav=instant", { timeout: 60_000 });
    await expect(page.getByText("voicenav-instant")).toBeVisible({ timeout: 30_000 });
  });

  test("a spoken destructive 'delete_biolink' must be confirmed before it fires", async ({
    page,
  }) => {
    // Confirmation chips are widget-shell behavior — admin-only surface now.
    await openWithWidget(page, "/admin");

    // Record every turn POST so we can prove the destructive call does NOT
    // reach the server until the user explicitly confirms.
    const turnBodies: string[] = [];
    await page.route("**/user/ai/voice/turn", async (route: Route) => {
      if (route.request().method() !== "POST") return route.fallback();
      turnBodies.push(route.request().postData() || "");
      // First turn: the model wants to delete, but the server gates it behind
      // a confirmation chip and runs NOTHING (no executed tool_results).
      if (turnBodies.length === 1) {
        return route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            transcript: "delete link 42",
            reply: "That permanently deletes link 42 — confirm?",
            pending_confirmations: [
              {
                tool: "delete_biolink",
                arguments: { link_id: 42 },
                summary: "Confirm before I run 'delete_biolink'.",
              },
            ],
            tool_results: [],
            credits: { stt: 1, llm: 1, tts: 1, total: 3 },
            balance: 100,
          }),
        });
      }
      // Second turn (only after the user taps Yes): now the delete runs and
      // reports back. We never reach here unless confirmation replayed.
      return route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          transcript: "yes",
          reply: "Deleted link 42.",
          pending_confirmations: [],
          tool_results: [{ result: { summary: "Deleted link #42." } }],
          credits: { stt: 1, llm: 1, tts: 1, total: 3 },
          balance: 100,
        }),
      });
    });

    // Drive the first turn through the REAL widget. We set `lastAudio` (the
    // clip confirmTool replays) and open the panel so the confirm chip is
    // visible — exactly the state the mic-stop path leaves the widget in.
    const firstTurn = page.waitForResponse(
      (r) =>
        r.url().endsWith("/user/ai/voice/turn") &&
        r.request().method() === "POST",
      { timeout: 60_000 },
    );
    await page.evaluate(() => {
      const el = document.querySelector('[x-data^="voiceAssistant"]')!;
      const A = (window as unknown as {
        Alpine: {
          $data: (e: Element) => {
            sendTurn: (b: Blob) => void;
            lastAudio: Blob | null;
            panelOpen: boolean;
          };
        };
      }).Alpine;
      const comp = A.$data(el);
      comp.panelOpen = true;
      comp.lastAudio = new Blob(["x"], { type: "audio/webm" });
      comp.sendTurn(comp.lastAudio);
    });
    await firstTurn;

    // The destructive tool is queued behind a confirmation chip — and crucially
    // the action has NOT run yet (only the first, gating turn has been POSTed,
    // and it carried no confirmation).
    await expect(
      page.locator("code", { hasText: "delete_biolink" }),
    ).toBeVisible({ timeout: 30_000 });
    expect(turnBodies).toHaveLength(1);
    expect(turnBodies[0]).toContain('"confirmed_tools":{}');

    // Tap "Yes" → the widget replays the SAME audio with the tool confirmed.
    const confirmReq = page.waitForRequest(
      (r) =>
        r.url().endsWith("/user/ai/voice/turn") && r.method() === "POST",
      { timeout: 60_000 },
    );
    await page.getByRole("button", { name: "Yes", exact: true }).click();
    const req = await confirmReq;

    // The confirming turn carries confirmed_tools[delete_biolink]=true (the
    // exact gate the server re-checks), the chip clears, and the success reply
    // appears — proving the destructive action only fires post-confirmation.
    expect(req.postData() || "").toContain('"delete_biolink":true');
    expect(turnBodies).toHaveLength(2);
    await expect(page.getByText("Deleted link 42.")).toBeVisible({
      timeout: 30_000,
    });
    await expect(
      page.locator("code", { hasText: "delete_biolink" }),
    ).toHaveCount(0);
  });

  // NOTE: the old "search_app drives the header search surface" test was
  // removed: no `voice-action` listener for the `search` client_action exists
  // on any surface since the Zio-panel merge (the header search voice hook is
  // gone). The generic bridge dispatch for read-only client_actions is still
  // covered by voice-assistant-panel.spec.ts ("a read-only client_action
  // dispatches a 'voice-action' surface event").

  test("the floating panel renders with a light surface in light mode", async ({
    page,
  }) => {
    // The floating mic/panel is suppressed on user pages since the Zio-panel
    // merge (the user layout includes the partial with voiceFloating=false);
    // the ADMIN layout is the surface that still hosts the floating widget.
    // The shared context is already logged in on BOTH guards (beforeAll), so
    // we just open an admin page.
    await page.goto("/admin", { timeout: 120_000 });
    await page.waitForFunction(
      () => {
        const el = document.querySelector('[x-data^="voiceAssistant"]');
        const w = window as unknown as { Alpine?: unknown };
        return Boolean(el && w.Alpine);
      },
      undefined,
      { timeout: 120_000 },
    );

    // Open the panel via the Alpine component so the .va-panel div is visible.
    await page.evaluate(() => {
      const el = document.querySelector('[x-data^="voiceAssistant"]')!;
      const A = (window as unknown as {
        Alpine: { $data: (e: Element) => { panelOpen: boolean } };
      }).Alpine;
      A.$data(el).panelOpen = true;
    });
    await expect(page.locator(".va-panel")).toBeVisible({ timeout: 10_000 });

    // Engage light mode — the same toggle the site's theme system uses.
    await page.evaluate(() =>
      document.documentElement.classList.add("light-mode"),
    );

    // The .va-panel html.light-mode override sets background:#ffffff.
    await expect
      .poll(
        () =>
          page
            .locator(".va-panel")
            .evaluate((el) => getComputedStyle(el).backgroundColor),
        { timeout: 10_000 },
      )
      .toBe("rgb(255, 255, 255)");

    // The mic button is still present and the panel is still open in light mode.
    await expect(
      page.locator(".va-panel").locator(".va-tab-btn").first(),
    ).toBeVisible();

    // ---- Representative TEXT colours must be readable on the white panel ----
    // The panel's dark-mode text colours are Tailwind utilities (text-white/*)
    // that would render white-on-white in light mode without the .va-panel
    // html.light-mode overrides. Assert each representative element's computed
    // color is dark (not white/near-white) so a future dark-hardcoded text
    // colour inside the panel can't silently regress. "Readable" = the text's
    // relative luminance is well below white's (a saturated dark blue like
    // rgb(29,78,216) passes; white/near-white greys fail).
    const readableColor = async (selector: string): Promise<void> => {
      await expect
        .poll(
          () =>
            page
              .locator(selector)
              .first()
              .evaluate((el) => getComputedStyle(el).color),
          { timeout: 10_000, message: `${selector} color in light mode` },
        )
        .toMatch(/^rgba?\((\d+), (\d+), (\d+)/);
      const color = await page
        .locator(selector)
        .first()
        .evaluate((el) => getComputedStyle(el).color);
      const m = /^rgba?\((\d+), (\d+), (\d+)/.exec(color);
      expect(m, `${selector} computed color parses: ${color}`).not.toBeNull();
      const lum = [Number(m![1]), Number(m![2]), Number(m![3])]
        .map((c) => {
          const s = c / 255;
          return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
        })
        .reduce((acc, ch, i) => acc + ch * [0.2126, 0.7152, 0.0722][i]!, 0);
      expect(
        lum < 0.45,
        `${selector} must be readable (dark, luminance < 0.45) on the white light-mode panel, got ${color} (luminance ${lum.toFixed(3)})`,
      ).toBe(true);
    };

    // The empty-state hint is visible while there are no messages/status.
    await expect(page.locator(".va-hint")).toBeVisible();
    await readableColor(".va-hint");

    // Seed a transcript + status through the Alpine component (the same state
    // a real turn produces) so the bubble and status elements render.
    await page.evaluate(() => {
      const el = document.querySelector('[x-data^="voiceAssistant"]')!;
      const A = (window as unknown as {
        Alpine: {
          $data: (e: Element) => {
            messages: { role: string; content: string }[];
            status: string;
            tab: string;
            caps: unknown;
          };
        };
      }).Alpine;
      const d = A.$data(el);
      d.messages = [
        { role: "user", content: "how many clicks today?" },
        { role: "assistant", content: "You had 42 clicks today." },
      ];
      d.status = "Thinking…";
    });
    await expect(page.locator(".va-bubble-user")).toBeVisible();
    await expect(page.locator(".va-bubble-ai")).toBeVisible();
    await expect(page.locator(".va-status")).toBeVisible();
    await readableColor(".va-bubble-user");
    await readableColor(".va-bubble-ai");
    await readableColor(".va-status");

    // Capabilities tab: seed caps directly (no network) and check a group
    // heading is readable too.
    await page.evaluate(() => {
      const el = document.querySelector('[x-data^="voiceAssistant"]')!;
      const A = (window as unknown as {
        Alpine: { $data: (e: Element) => { tab: string; caps: unknown } };
      }).Alpine;
      const d = A.$data(el);
      d.caps = {
        tools: {
          links: [
            { name: "list_links", description: "List your links.", destructive: false },
          ],
        },
        limitations: ["I can't change billing details."],
      };
      d.tab = "caps";
    });
    await expect(page.locator(".va-caps-group")).toBeVisible();
    await readableColor(".va-caps-group");
  });
});
