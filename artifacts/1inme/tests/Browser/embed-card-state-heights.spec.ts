import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";

/**
 * Embed-card clipping guard (task #6716, follows #6713/#6714).
 *
 * The static no-JS `<iframe>` embed snippet is sized ONCE, at copy time, from
 * Link::embedCardIframeHeight() (148px without a subtitle row, 164px with
 * one). It can never grow afterwards, so every state the card can later
 * render in — ok, gated (link turned private), unavailable (link turned
 * off) — must fit within the height the snippet was copied with, or the
 * card visibly clips on the third-party page.
 *
 * This spec reproduces the real embedding situation: a host page with a
 * fixed-height iframe pointing at /embed/link/{alias}/card, then measures
 * the rendered #embed-card height + vertical margins INSIDE the frame and
 * asserts it fits the copy-time height. No login required — the embed
 * endpoints are public/anonymous by design.
 */

const APP_URL = (process.env.APP_URL || "http://localhost:80").replace(/\/+$/, "");

// The host page is served from a fake public origin (route-fulfilled) while
// the app runs on localhost — Chromium's Local Network Access checks block a
// public-origin document from framing loopback resources, which can never
// happen in the real production topology (both sides public). Disable those
// checks for this suite only; the card's own frame-ancestors/XFO headers are
// still fully enforced.
test.use({
  launchOptions: {
    args: [
      "--disable-features=LocalNetworkAccessChecks,PrivateNetworkAccessSendPreflights,PrivateNetworkAccessRespectPreflightResults",
    ],
  },
});

// Shared-RDS safety: per-run unique aliases + stale-prefix pruning, so
// parallel task environments pointing at the same database never collide.
const PREFIX = "e2e-embch-";
const RUN =
  Date.now().toString(36) + Math.floor(Math.random() * 46656).toString(36);

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

type ScenarioKey =
  | "ok-nosub"
  | "ok-sub"
  | "gated-nosub"
  | "gated-sub"
  | "unavail-nosub"
  | "unavail-sub";

interface Scenario {
  key: ScenarioKey;
  /** State the CONTROLLER should serve now. */
  state: "ok" | "gated" | "unavailable";
  /** Substring the card's subtitle row must contain (null = no subtitle row). */
  subtitleContains: string | null;
  /** Whether the card renders an action button in this state. */
  hasButton: boolean;
}

const SUBTITLE = "A concise description for the embed card subtitle row.";

const SCENARIOS: Scenario[] = [
  { key: "ok-nosub", state: "ok", subtitleContains: null, hasButton: true },
  { key: "ok-sub", state: "ok", subtitleContains: SUBTITLE, hasButton: true },
  // Gated/unavailable ALWAYS render a one-line fallback subtitle — the
  // critical case is the no-subtitle link copied at 148px that later flips
  // state and gains that line: it must still fit 148px (task #6714).
  {
    key: "gated-nosub",
    state: "gated",
    subtitleContains: "Private link",
    hasButton: true,
  },
  {
    key: "gated-sub",
    state: "gated",
    subtitleContains: "Private link",
    hasButton: true,
  },
  {
    key: "unavail-nosub",
    state: "unavailable",
    subtitleContains: "not available",
    hasButton: false,
  },
  {
    key: "unavail-sub",
    state: "unavailable",
    subtitleContains: "not available",
    hasButton: false,
  },
];

function aliasFor(key: ScenarioKey): string {
  return `${PREFIX}${RUN}-${key}`;
}

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

/**
 * Seeds one text link per scenario and echoes, per alias, the height the
 * static iframe snippet would have been COPIED with. embedCardIframeHeight()
 * depends only on the subtitle source (seo_description / type), which the
 * later state flips (visibility, is_active) never change — so reading it
 * from the seeded row IS the copy-time height.
 */
function seedFixtures(): Record<string, number> {
  const rows = SCENARIOS.map((s) => {
    const withSub = s.key.endsWith("-sub");
    const gated = s.state === "gated";
    const inactive = s.state === "unavailable";
    return `['${aliasFor(s.key)}', ${withSub ? "true" : "false"}, ${gated ? "'private'" : "'public'"}, ${inactive ? "false" : "true"}]`;
  }).join(",\n  ");

  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;

// Prune stale fixtures from earlier runs (shared RDS across task envs).
Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${PREFIX}%')
  ->where('created_at', '<', now()->subHours(6))
  ->delete();

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$ws = app(WorkspaceContext::class)->resolve($u);

$specs = [
  ${rows}
];
$out = [];
foreach ($specs as [$alias, $withSub, $visibility, $active]) {
  $l = Link::withoutGlobalScope('workspace')->where('alias', $alias)->first();
  if (!$l) {
    $l = Link::create([
      'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'text',
      'alias' => $alias, 'title' => 'E2E embed height ' . $alias,
      'is_active' => $active, 'visibility' => $visibility,
      'seo_description' => $withSub ? '${SUBTITLE}' : null,
      'settings' => ['text_content' => 'A short shared note.'],
    ]);
  }
  // Copy-time snippet height: what embedIframeSnippet() bakes into the
  // static iframe. Depends only on the subtitle source, not on state.
  $out[$alias] = $l->embedCardIframeHeight();
}
echo 'HEIGHTS=' . json_encode($out);
`.trim();

  const out = runTinker(php);
  const m = out.match(/HEIGHTS=(\{.*\})/);
  if (!m) throw new Error("Seed failed, output:\n" + out);
  return JSON.parse(m[1]) as Record<string, number>;
}

let heights: Record<string, number>;

test.beforeAll(() => {
  heights = seedFixtures();
});

for (const scenario of SCENARIOS) {
  test(`${scenario.key}: card fits the copy-time ${scenario.state} iframe height`, async ({
    page,
  }) => {
    const alias = aliasFor(scenario.key);
    const copiedHeight = heights[alias];
    expect(
      copiedHeight,
      `seed must report a snippet height for ${alias}`,
    ).toBeGreaterThan(0);
    // Sanity: the two documented variants only (guards accidental changes
    // to the height model itself — if these move, the card CSS budget and
    // this spec must be revisited together).
    expect([148, 164]).toContain(copiedHeight);

    // Real third-party embedding situation: a host page served from a
    // DIFFERENT http origin (route-fulfilled, never hits the network) with
    // the exact fixed-height iframe the snippet generator emits. NB: this
    // must be a real http origin, not page.setContent() — the card's CSP
    // `frame-ancestors *` does not match the opaque about:blank origin, so
    // Chromium blocks the frame there (ERR_BLOCKED_BY_RESPONSE).
    const hostUrl = "http://third-party.example/blog/post.html";
    await page.route(hostUrl, (route) =>
      route.fulfill({
        contentType: "text/html; charset=utf-8",
        body: `<!doctype html><html><body style="margin:24px;font-family:Georgia,serif">
          <p>Host page content above the embed.</p>
          <iframe id="embed-frame" src="${APP_URL}/embed/link/${alias}/card"
            style="width:100%;max-width:420px;height:${copiedHeight}px;border:0;display:block"
            ></iframe>
          <p>Host page content below the embed.</p>
        </body></html>`,
      }),
    );
    await page.goto(hostUrl, { waitUntil: "domcontentloaded" });

    const frameEl = await page.waitForSelector("#embed-frame");
    const frame = await frameEl.contentFrame();
    if (!frame) throw new Error("embed iframe has no content frame");
    await frame.waitForSelector("#embed-card", { state: "visible" });
    // Let the card's own late layout passes (load event, 300ms timer)
    // settle before measuring.
    await frame.waitForLoadState("load");
    await page.waitForTimeout(400);

    // The fixture really rendered the intended state.
    const subtitle = frame.locator(".subtitle");
    if (scenario.subtitleContains === null) {
      await expect(subtitle).toHaveCount(0);
    } else {
      await expect(subtitle).toContainText(scenario.subtitleContains);
    }
    await expect(frame.locator("a.btn")).toHaveCount(scenario.hasButton ? 1 : 0);
    // Badge row renders on the happy path only (task #6714).
    await expect(frame.locator(".badge")).toHaveCount(
      scenario.state === "ok" ? 1 : 0,
    );

    // Measure the card as laid out inside the fixed-height frame.
    const m = await frame.evaluate(() => {
      const el = document.getElementById("embed-card");
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return {
        total:
          rect.height +
          parseFloat(cs.marginTop || "0") +
          parseFloat(cs.marginBottom || "0"),
        scrollHeight: document.documentElement.scrollHeight,
        clientHeight: document.documentElement.clientHeight,
      };
    });
    if (!m) throw new Error("#embed-card missing at measurement time");

    // Card + its vertical margins must fit the height the static snippet
    // was copied with — otherwise the third-party page clips the card.
    expect(
      m.total,
      `card (${m.total}px incl. margins) must fit the ${copiedHeight}px copy-time iframe`,
    ).toBeLessThanOrEqual(copiedHeight);

    // And the frame document itself must not scroll (the visible symptom
    // of clipping inside a fixed, non-scrolling embed iframe).
    expect(
      m.scrollHeight,
      `frame scrollHeight ${m.scrollHeight}px must not exceed its ${m.clientHeight}px viewport`,
    ).toBeLessThanOrEqual(m.clientHeight);
  });
}
