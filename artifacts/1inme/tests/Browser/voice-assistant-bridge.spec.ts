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

$u = User::where('email', 'demo@1inme.com')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => 'demo@1inme.com',
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
 * Log in as the demo user (non-prod quick-login). Submits the demo-login form
 * via JS and waits only for the demo-login POST response (not the redirect
 * target render), so the heavy post-login dashboard render never blocks the
 * suite. Mirrors the palette-dnd spec.
 */
async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login");
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/user/demo-login") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    page.evaluate(() => {
      const form = document.querySelector<HTMLFormElement>(
        'form[action$="/user/demo-login"]',
      );
      if (!form) throw new Error("demo-login form not found");
      form.submit();
    }),
  ]);
}

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
    sharedContext = await browser.newContext();
    const page = await sharedContext.newPage();
    await loginAsDemo(page);
    await page.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test("a spoken 'select_link_type' picks the type and submits the Create form", async ({
    page,
  }) => {
    await openWithWidget(page, "/user/links/create");

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

    // `url` is the radio value behind the "Short Link" card on the Create page.
    await mockTurn(page, {
      tool_results: [
        { result: { client_action: { type: "select_link_type", link_type: "url" } } },
      ],
    });

    const submit = page.waitForRequest(
      (r) =>
        r.method() === "POST" && r.url().includes("/user/links/choose-type"),
      { timeout: 60_000 },
    );
    await speak(page);

    // The create form's @voice-action handler set `type` and called $el.submit():
    // the POST carries the spoken type.
    const req = await submit;
    expect(req.postData() || "").toContain("type=url");
  });

  test("a spoken 'wizard_advance' clicks the wizard's forward submit", async ({
    page,
  }) => {
    await openWithWidget(page, "/user/links/wizard");

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

    await mockTurn(page, {
      tool_results: [
        { result: { client_action: { type: "wizard_advance", direction: "next" } } },
      ],
    });

    const advance = page.waitForRequest(
      (r) => r.method() === "POST" && r.url().endsWith("/user/links/wizard"),
      { timeout: 60_000 },
    );
    await speak(page);

    // Step 0 forwards by submitting a persona_group choice (goNext clicks the
    // form's forward submit button), so the advancing POST carries it.
    const req = await advance;
    expect(req.postData() || "").toContain("persona_group=");
  });

  test("a 'navigate_to' with spoken audio is DEFERRED until the reply finishes", async ({
    page,
  }) => {
    await openWithWidget(page, "/user/links/create");

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
    await openWithWidget(page, "/user/links/create");

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
});
