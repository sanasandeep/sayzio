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

// The voice agent was moved out of the standalone floating Alpine mic and into
// the Zio chat-panel composer as a plain-JS port (see
// resources/views/common/partials/site-assistant.blade.php). The Alpine bridge
// is still guarded by voice-assistant-bridge.spec.ts; THIS spec guards the new
// in-panel runtime end-to-end: a mic in the composer that opens the panel and
// records → a turn that round-trips transcript + reply into the chat body →
// destructive-tool confirmation chips → the "What I can do" capabilities pane →
// the `voice-action` surface bridge + deferred navigate_to → and the plan-gate /
// anonymous mic visibility rules.
//
// No real Whisper / GPT / ElevenLabs call ever happens: getUserMedia +
// MediaRecorder are stubbed in the page (so the real mic→stop→sendTurn pipeline
// runs against a deterministic blob) and the /user/ai/voice/* endpoints are
// mocked in the browser. The chat bootstrap/session calls are stubbed too so the
// panel opens deterministically without depending on the Zio assistant content.

// Tests that log in share a single logged-in context (the demo-login route is
// rate-limited at throttle:5,1, so a login per test would trip the limit). Each
// test gets a fresh page from whichever context the describe block installs.
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
 * Run a `php artisan tinker` snippet and assert it printed OK_TOKEN. The string
 * is passed straight to `--execute=`; in a JS template literal `\\` becomes the
 * single backslash PHP namespaces need (App\Modules\...), while `$var` stays
 * literal (mirrors the sibling specs' tinker convention).
 */
function tinker(php: string, okToken: string): void {
  const out = execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
    cwd: ARTIFACT_ROOT,
    encoding: "utf8",
  });
  if (!out.includes(okToken)) {
    throw new Error(`tinker snippet failed; expected ${okToken}, output:\n` + out);
  }
}

/**
 * Idempotently ensure the demo user exists (onboarded, with a workspace and the
 * user-admin role). The site assistant is enabled for the app surface by default
 * so the launcher renders; voice is configured per-test (available vs gated).
 */
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

/**
 * Turn Voice ON for every plan (empty allow-list == all). The composer mic only
 * renders (as an available mic) when AiEngineSettings::voiceAllowedFor() is true,
 * which with an empty allow-list just needs ai.voice.enabled = true. AppSetting
 * forgets its own cache key on write, so the already-warm server re-reads it.
 */
function setVoiceAvailable(): void {
  tinker(
    `
use App\\Services\\AI\\AiEngineSettings;
AiEngineSettings::setEnabled(true);
AiEngineSettings::setVoiceEnabled(true);
AiEngineSettings::setVoiceEnabledPlans([]);
echo 'VOICE_AVAIL_OK';
`.trim(),
    "VOICE_AVAIL_OK",
  );
}

/**
 * Plan-gate the demo user: Voice enabled + the AI engine on, but an allow-list
 * that excludes the demo user's plan, so voiceAllowedFor() is false while the
 * gate condition (engine on && voice on) holds — the composer renders a
 * lock-badged mic that routes to the upgrade gate.
 */
function setVoiceGated(): void {
  tinker(
    `
use App\\Services\\AI\\AiEngineSettings;
AiEngineSettings::setEnabled(true);
AiEngineSettings::setVoiceEnabled(true);
AiEngineSettings::setVoiceEnabledPlans(['voice-only-tier']);
echo 'VOICE_GATED_OK';
`.trim(),
    "VOICE_GATED_OK",
  );
}

/**
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS and waits only for the demo-login POST response (not the redirect
 * target render), so the heavy post-login dashboard render never blocks the
 * suite. Mirrors the bridge / palette-dnd specs.
 */
/**
 * Stub the chat bootstrap/session so opening the panel is deterministic and
 * fast (no dependency on the Zio assistant content). The voice pipeline is the
 * subject under test; the chat greeting just proves the panel opened.
 */
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
        visitor_token: "e2e-voice-token",
        messages: [],
        greeting: "Hi, I'm Zio.",
        starter_prompts: [],
        page_suggestions: [],
        low_balance: null,
      }),
    }),
  );
}

/** A single voice "turn" response carrying tool results / confirmations. */
type TurnBody = {
  transcript?: string;
  reply?: string;
  audio_base64?: string;
  tool_results?: Array<{ result: Record<string, unknown> }>;
  pending_confirmations?: unknown[];
  credits?: Record<string, number>;
  balance?: number;
};

/** Mock the server side of a voice turn (STT + LLM + TTS). */
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

/**
 * Drive the REAL composer mic: open the panel (if needed) and click the mic to
 * start recording, then click again to stop — which fires the stubbed
 * MediaRecorder's data + stop, building a blob and calling the widget's real
 * sendTurn(). Returns once the recording UI has cleared (turn dispatched).
 */
async function speak(page: Page): Promise<void> {
  const mic = page.locator("#sa-panel .sa-mic");
  await mic.click(); // start recording
  await expect(mic).toHaveClass(/sa-mic-rec/, { timeout: 10_000 });
  await mic.click(); // stop -> onstop -> sendTurn(blob)
  await expect(mic).not.toHaveClass(/sa-mic-rec/, { timeout: 10_000 });
}

/** Open the panel via the launcher and wait for the stubbed greeting. */
async function openPanel(page: Page): Promise<void> {
  await page.locator("#sa-launcher").click();
  await expect(page.locator("#sa-panel-wrap")).toHaveClass(/sa-open/);
  await expect(
    page.locator("#sa-body .sa-msg.assistant", { hasText: "Hi, I'm Zio." }),
  ).toBeVisible({ timeout: 30_000 });
}

test.describe("voice assistant — in-panel composer (available user)", () => {
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async ({ browser }) => {
    seedDemoUser();
    setVoiceAvailable();
    sharedContext = await browser.newContext({ permissions: ["microphone"] });
    // Stub getUserMedia + MediaRecorder for every page in the context so the
    // real mic→stop→sendTurn path runs without a hardware mic. The fake
    // recorder emits one non-empty chunk on stop, mirroring a real clip.
    await sharedContext.addInitScript(() => {
      const md = (navigator.mediaDevices = navigator.mediaDevices || ({} as MediaDevices));
      md.getUserMedia = () =>
        Promise.resolve({
          getTracks: () => [{ stop() {} }],
        } as unknown as MediaStream);
      class FakeRecorder {
        mimeType: string;
        ondataavailable: ((e: { data: Blob }) => void) | null = null;
        onstop: (() => void) | null = null;
        constructor(_s: unknown, opts?: { mimeType?: string }) {
          this.mimeType = (opts && opts.mimeType) || "audio/webm";
        }
        start() {}
        stop() {
          if (this.ondataavailable)
            this.ondataavailable({
              data: new Blob(["x"], { type: this.mimeType }),
            });
          if (this.onstop) this.onstop();
        }
        static isTypeSupported() {
          return true;
        }
      }
      (window as unknown as { MediaRecorder: unknown }).MediaRecorder = FakeRecorder;
    });
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test("the composer mic (no lock badge) records once the panel is open", async ({
    page,
  }) => {
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });

    // The mic lives in the composer, which is inside the panel — hidden until
    // the launcher opens it. Open the panel first, then the mic is actionable.
    await openPanel(page);

    const mic = page.locator("#sa-panel .sa-mic");
    await expect(mic).toBeVisible();
    // No lock badge for an available user.
    await expect(mic.locator(".sa-mic-lock")).toHaveCount(0);

    // Clicking the visible mic starts the real record pipeline.
    await mic.click();
    await expect(mic).toHaveClass(/sa-mic-rec/, { timeout: 10_000 });
    await expect(page.locator("#sa-voice-status")).toHaveText("Listening…");
  });

  test("a turn round-trips: transcript + reply render in the chat body, credits update", async ({
    page,
  }) => {
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    await mockTurn(page, {
      transcript: "show my links",
      reply: "Here are your links.",
    });
    await speak(page);

    await expect(
      page.locator("#sa-body .sa-msg.user", { hasText: "show my links" }),
    ).toBeVisible({ timeout: 30_000 });
    await expect(
      page.locator("#sa-body .sa-msg.assistant", {
        hasText: "Here are your links.",
      }),
    ).toBeVisible({ timeout: 30_000 });
    // Per-turn credit meter renders.
    await expect(page.locator("#sa-voice-credits")).toContainText(
      "3 credits",
    );
  });

  test("a spoken destructive tool must be confirmed before it fires", async ({
    page,
  }) => {
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    const turnBodies: string[] = [];
    await page.route("**/user/ai/voice/turn", async (route: Route) => {
      if (route.request().method() !== "POST") return route.fallback();
      turnBodies.push(route.request().postData() || "");
      // First turn: the model wants to delete; the server gates it behind a
      // confirmation chip and runs nothing.
      if (turnBodies.length === 1) {
        return route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            transcript: "delete link 42",
            reply: "That permanently deletes link 42 — confirm?",
            pending_confirmations: [
              { tool: "delete_biolink", arguments: { link_id: 42 } },
            ],
            tool_results: [],
            credits: { stt: 1, llm: 1, tts: 1, total: 3 },
            balance: 100,
          }),
        });
      }
      // Second turn (only after the user taps Yes): the delete runs.
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

    await speak(page);

    // The destructive tool is queued behind a confirmation chip — and the
    // action has NOT run yet (only the first, gating turn was POSTed).
    const chip = page.locator("#sa-voice-confirm .sa-vc-label", {
      hasText: "delete_biolink",
    });
    await expect(chip).toBeVisible({ timeout: 30_000 });
    expect(turnBodies).toHaveLength(1);
    // The composer sends FormData; the gating turn carries an empty
    // confirmed_tools map.
    expect(turnBodies[0]).toContain('"confirmed_tools":{}');

    // Tap "Yes" → the widget replays the SAME audio with the tool confirmed.
    await page
      .locator("#sa-voice-confirm .sa-vc-yes")
      .first()
      .click();

    await expect(
      page.locator("#sa-body .sa-msg.assistant", {
        hasText: "Deleted link 42.",
      }),
    ).toBeVisible({ timeout: 30_000 });
    expect(turnBodies).toHaveLength(2);
    expect(turnBodies[1]).toContain('"delete_biolink":true');
    // The chip clears once confirmed.
    await expect(
      page.locator("#sa-voice-confirm .sa-vc-label", {
        hasText: "delete_biolink",
      }),
    ).toHaveCount(0);
  });

  test("'What I can do' loads the capabilities pane", async ({ page }) => {
    await stubChat(page);
    await page.route("**/user/ai/voice/capabilities", (route: Route) =>
      route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          tools: {
            links: [
              { name: "create_link", description: "Create a new link." },
              { name: "delete_biolink", description: "Delete a biolink.", destructive: true },
            ],
          },
          limitations: ["I can't access your billing details."],
        }),
      }),
    );

    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    await page.locator("#sa-voice-caps").click();
    const pane = page.locator("#sa-vcaps");
    await expect(pane).toHaveClass(/sa-show/, { timeout: 10_000 });
    await expect(
      pane.locator(".sa-vcaps-name", { hasText: "create_link" }),
    ).toBeVisible({ timeout: 30_000 });
    await expect(pane).toContainText("I can't access your billing details.");
  });

  test("a 'navigate_to' with spoken audio is DEFERRED until the reply finishes", async ({
    page,
  }) => {
    await stubChat(page);
    // Headless Chromium can't actually play the tiny clip, so stop the audio
    // element from auto-firing 'ended' — we end it explicitly below to prove the
    // navigation was deferred, not taken eagerly.
    await page.addInitScript(() => {
      const orig = HTMLAudioElement.prototype.play;
      HTMLAudioElement.prototype.play = function () {
        return Promise.resolve();
      };
      void orig;
    });

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

    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    await mockTurn(page, {
      reply: "Opening your links",
      audio_base64: "AAAA",
      tool_results: [{ result: { navigate_to: target } }],
    });

    const before = page.url();
    await speak(page);

    // The reply is "playing": navigation is queued, not taken.
    await expect(
      page.locator("#sa-body .sa-msg.assistant", {
        hasText: "Opening your links",
      }),
    ).toBeVisible({ timeout: 30_000 });
    expect(page.url()).toBe(before);

    // End the spoken reply → the deferred navigation now fires.
    await page.evaluate(() => {
      const audio = document.querySelector("#sa-panel audio") as HTMLAudioElement | null;
      if (audio) audio.dispatchEvent(new Event("ended"));
    });
    await page.waitForURL("**/user/links?voicenav=deferred", {
      timeout: 60_000,
    });
    await expect(page.getByText("voicenav-arrived")).toBeVisible({
      timeout: 30_000,
    });
  });

  test("a read-only client_action dispatches a 'voice-action' surface event", async ({
    page,
  }) => {
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    // Listen for the bridge event the focused surface would act on.
    await page.evaluate(() => {
      (window as unknown as { __vaSeen?: unknown[] }).__vaSeen = [];
      window.addEventListener("voice-action", (e: Event) => {
        (window as unknown as { __vaSeen: unknown[] }).__vaSeen.push(
          (e as CustomEvent).detail,
        );
      });
    });

    // Use a read-only action type that no focused surface navigates on. The My
    // Links layout's surface handler acts on `type === 'search'` with a real
    // window.location navigation, which would race speak()'s post-stop assertion
    // and wipe __vaSeen on reload. A benign type isolates the widget's job here:
    // dispatching the bridge event with the correct detail.
    await mockTurn(page, {
      reply: "Highlighting.",
      tool_results: [
        { result: { client_action: { type: "highlight", query: "coffee" } } },
      ],
    });
    await speak(page);

    await expect
      .poll(
        () =>
          page.evaluate(
            () =>
              (window as unknown as { __vaSeen: { type?: string; query?: string }[] })
                .__vaSeen,
          ),
        { timeout: 30_000 },
      )
      .toEqual([{ type: "highlight", query: "coffee" }]);
  });

  test("the voice composer renders under the site's light theme", async ({
    page,
  }) => {
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    // Follow the site theme: the panel's `html.light-mode` overrides flip it to
    // a light surface. Force the class right before measuring so the app's own
    // theme init can't race it; the CSS cascade applies synchronously.
    await page.evaluate(() =>
      document.documentElement.classList.add("light-mode"),
    );
    await expect
      .poll(
        () =>
          page
            .locator("#sa-panel")
            .evaluate((el) => getComputedStyle(el).backgroundColor),
        { timeout: 10_000 },
      )
      .toBe("rgb(255, 255, 255)");
    // The mic is still present and usable in light mode.
    await expect(page.locator("#sa-panel .sa-mic")).toBeVisible();
  });

  test("the voice composer honors prefers-reduced-motion and still records", async ({
    page,
  }) => {
    await page.emulateMedia({ reducedMotion: "reduce" });
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });
    await openPanel(page);

    // The Zio mascot peeking over the panel drops its rise/bob animation when
    // the visitor prefers reduced motion.
    await expect
      .poll(
        () =>
          page
            .locator("#sa-peek")
            .evaluate((el) => getComputedStyle(el).animationName),
        { timeout: 10_000 },
      )
      .toBe("none");

    // Voice still works end-to-end regardless of the motion preference.
    await mockTurn(page, {
      transcript: "hello",
      reply: "Hi there.",
    });
    await speak(page);
    await expect(
      page.locator("#sa-body .sa-msg.assistant", { hasText: "Hi there." }),
    ).toBeVisible({ timeout: 30_000 });
  });
});

test.describe("voice assistant — in-panel composer (plan-gated user)", () => {
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(async ({ browser }) => {
    seedDemoUser();
    setVoiceGated();
    sharedContext = await browser.newContext();
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
    // Restore voice-available state so a later run / re-seed isn't left gated.
    setVoiceAvailable();
  });

  test("a gated user gets a lock-badged mic that routes to the voice gate", async ({
    page,
  }) => {
    await stubChat(page);
    await page.goto("/user/links", { timeout: 120_000 });

    // Mic lives inside the panel; open it first so the lock-badged mic shows.
    await openPanel(page);

    const mic = page.locator("#sa-panel .sa-mic");
    await expect(mic).toBeVisible();
    await expect(mic.locator(".sa-mic-lock")).toHaveCount(1);

    // Clicking it routes to the upgrade/voice gate page rather than recording.
    await mic.click();
    await page.waitForURL("**/user/ai/voice*", { timeout: 60_000 });
  });
});

test.describe("voice assistant — no mic on anonymous / marketing surfaces", () => {
  test.describe.configure({ timeout: 120_000 });

  test.beforeAll(async ({ browser }) => {
    sharedContext = await browser.newContext();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test("an anonymous visitor sees the Zio launcher but no composer mic", async ({
    page,
  }) => {
    await page.goto("/pricing", { timeout: 120_000 });
    // The assistant root renders on marketing too, but with no voice mic.
    await expect(page.locator("#sa-launcher")).toBeVisible({ timeout: 30_000 });
    await expect(page.locator("#sa-panel .sa-mic")).toHaveCount(0);
  });
});
