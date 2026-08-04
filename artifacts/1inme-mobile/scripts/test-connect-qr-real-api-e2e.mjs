#!/usr/bin/env node
/**
 * REAL-API e2e for the mobile Event Connect QR surfaces (Task #6692,
 * screens from Task #6687).
 *
 * Proves against a REAL Laravel backend + real RDS:
 *   1. Host opens /events/connect-qr/{linkId}: the SVG QR renders and the
 *      connect URL (…?src=connect_qr) is shown.
 *   2. A signed-in guest opens /events/{alias}?src=connect_qr, taps
 *      "RSVP & Connect" and sees the "You're connected!" success state.
 *   3. Server-side, the Rsvp (yes/confirmed/source=connect_qr) and
 *      EventQrConnect (rsvp_id set, followed) rows exist.
 *   4. Host opens /links/{id}/visitors: the "QR Connect" section shows with
 *      the RSVP/connect counts from step 2.
 *
 * Infrastructure mirrors test-contact-jump-to-record-real-api-e2e.mjs:
 *   - Boots its own Laravel dev server (php -S + vendored framework router,
 *     cwd=public/ — artisan serve strips DB_* env).
 *   - Seeds per-run host + guest users, an ics event link and mints real
 *     Sanctum tokens via `artisan tinker --execute` (prunes stale fixtures
 *     by email/alias prefix — shared RDS across envs).
 *   - Boots a throwaway Expo web server with EXPO_PUBLIC_API_BASE_URL baked.
 *
 * SKIPs (exit 0) when the environment can't support it. ALWAYS grep the log
 * for the literal PASS line — a SKIP also exits 0.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:connect-qr-real-api-e2e
 */

import { execFileSync, spawn } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import { chromium } from "playwright";

import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
  getFreePort,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[connect-qr-e2e]", ...args);
}
function fail(msg) {
  console.error("[connect-qr-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[connect-qr-e2e] SKIP:", msg);
  process.exit(0);
}

const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");
const LARAVEL_ROOT = path.resolve(MOBILE_ROOT, "..", "1inme");
const VIEWPORT = { width: 400, height: 780 };
// Cross-region RDS makes every request slow; budget generously.
const STEP_TIMEOUT_MS = 90_000;
const NAV_TIMEOUT_MS = 120_000;

const RUN_ID = Date.now().toString(36) + process.pid.toString(36);
const EMAIL_PREFIX = "e2e-cqr-";
const HOST_EMAIL = `${EMAIL_PREFIX}host-${RUN_ID}@example.test`;
const GUEST_EMAIL = `${EMAIL_PREFIX}guest-${RUN_ID}@example.test`;
const ALIAS_PREFIX = "e2e-cqr-";
const ALIAS = ALIAS_PREFIX + RUN_ID;

// ---------------------------------------------------------------------------
// Laravel dev server
// ---------------------------------------------------------------------------

const laravelChildren = new Set();
process.on("exit", () => {
  for (const c of laravelChildren) {
    try {
      process.kill(-c.pid, "SIGTERM");
    } catch {
      try {
        c.kill("SIGTERM");
      } catch {
        /* gone */
      }
    }
  }
});

async function bootLaravel() {
  const port = await getFreePort();
  const routerPath = path.join(
    LARAVEL_ROOT,
    "vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php",
  );
  log(`booting Laravel dev server on :${port}`);
  const child = spawn("php", ["-S", `127.0.0.1:${port}`, routerPath], {
    // The framework router resolves index.php from CWD — must be public/.
    cwd: path.join(LARAVEL_ROOT, "public"),
    detached: true,
    stdio: ["ignore", "ignore", "ignore"],
    env: { ...process.env, PHP_CLI_SERVER_WORKERS: "8" },
  });
  child.unref();
  laravelChildren.add(child);

  const base = `http://127.0.0.1:${port}`;
  const deadline = Date.now() + 120_000;
  while (Date.now() < deadline) {
    try {
      const res = await fetch(`${base}/up`, {
        signal: AbortSignal.timeout(20_000),
      });
      if (res.ok) {
        log("Laravel server is up");
        return { base, port, child };
      }
    } catch {
      /* not up yet */
    }
    await new Promise((r) => setTimeout(r, 1500));
  }
  return null;
}

// ---------------------------------------------------------------------------
// Fixture seed / verify / cleanup via artisan tinker
// ---------------------------------------------------------------------------

function runTinker(php) {
  let lastErr;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
        cwd: LARAVEL_ROOT,
        encoding: "utf8",
        timeout: 300_000,
      });
    } catch (err) {
      lastErr = err;
    }
  }
  throw lastErr;
}

function seedFixture() {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\Admin\\Models\\Plan;
use App\\Modules\\User\\Models\\IcsData;
use App\\Modules\\User\\Models\\Rsvp;
use App\\Modules\\User\\Models\\EventQrConnect;
use App\\Modules\\User\\Models\\Follow;
use App\\Modules\\User\\Models\\LinkClick;
use Illuminate\\Support\\Facades\\Hash;

// Prune stale fixture users from previous runs (shared RDS across envs).
$cut = now()->subHours(2);
$stale = User::where('email', 'like', '${EMAIL_PREFIX}%@example.test')
  ->where('created_at', '<', $cut)->get();
foreach ($stale as $su) {
  $links = Link::withoutGlobalScope('workspace')->where('user_id', $su->id)->get();
  foreach ($links as $sl) {
    EventQrConnect::where('link_id', $sl->id)->delete();
    Rsvp::where('link_id', $sl->id)->delete();
    LinkClick::where('link_id', $sl->id)->delete();
    IcsData::where('link_id', $sl->id)->delete();
    $sl->delete();
  }
  Follow::where('follower_id', $su->id)->orWhere('creator_id', $su->id)->delete();
  $su->tokens()->delete();
  $su->delete();
}

$free = Plan::where('slug', 'free')->first();
$mk = function (string $email, string $name) use ($free) {
  $u = User::create([
    'name' => $name, 'email' => $email,
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
  $u->onboarded_at = now(); $u->save();
  return $u;
};
$host  = $mk('${HOST_EMAIL}', 'CQR Host');
$guest = $mk('${GUEST_EMAIL}', 'CQR Guest');

$link = Link::withoutGlobalScope('workspace')->create([
  'user_id' => $host->id, 'type' => 'ics',
  'alias' => '${ALIAS}', 'title' => 'Connect QR E2E Party', 'is_active' => true,
  'settings' => ['rsvp_enabled' => true],
]);
$ws = $host->ownedWorkspaces()->first();
if ($ws) { $link->workspace_id = $ws->id; $link->saveQuietly(); }
IcsData::create([
  'link_id' => $link->id, 'event_name' => $link->title,
  'start_date' => now()->addDays(30)->setTime(18, 0),
  'end_date'   => now()->addDays(30)->setTime(20, 0),
  'timezone'   => 'UTC',
]);

echo 'SEED_JSON:' . json_encode([
  'hostId' => $host->id, 'guestId' => $guest->id, 'linkId' => $link->id,
  'alias' => $link->alias,
  'hostToken'  => $host->createToken('e2e-cqr-host')->plainTextToken,
  'guestToken' => $guest->createToken('e2e-cqr-guest')->plainTextToken,
]) . "\\n";
`;
  const out = runTinker(php);
  const m = out.match(/SEED_JSON:(\{.*\})/);
  if (!m) fail(`seed produced no SEED_JSON marker; output:\n${out}`);
  return JSON.parse(m[1]);
}

/** Server-side oracle: the guest's tap must have minted the real rows. */
function verifyServerRows(seed) {
  const php = `
use App\\Modules\\User\\Models\\Rsvp;
use App\\Modules\\User\\Models\\EventQrConnect;
use App\\Modules\\User\\Models\\Follow;
$rsvp = Rsvp::where('link_id', ${Number(seed.linkId)})->where('email', '${GUEST_EMAIL}')->first();
$cn = EventQrConnect::where('link_id', ${Number(seed.linkId)})->where('user_id', ${Number(seed.guestId)})->first();
echo 'VERIFY_JSON:' . json_encode([
  'rsvp' => $rsvp ? ['response' => $rsvp->response, 'status' => $rsvp->status, 'source' => $rsvp->source, 'id' => $rsvp->id] : null,
  'connect' => $cn ? ['rsvp_id' => $cn->rsvp_id, 'followed' => (bool) $cn->followed, 'was_new_user' => (bool) $cn->was_new_user] : null,
  'followed' => Follow::where('follower_id', ${Number(seed.guestId)})->where('creator_id', ${Number(seed.hostId)})->exists(),
]) . "\\n";
`;
  const out = runTinker(php);
  const m = out.match(/VERIFY_JSON:(\{.*\})/);
  if (!m) fail(`verify produced no VERIFY_JSON marker; output:\n${out}`);
  return JSON.parse(m[1]);
}

function cleanupFixture(seed) {
  if (!seed) return;
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\IcsData;
use App\\Modules\\User\\Models\\Rsvp;
use App\\Modules\\User\\Models\\EventQrConnect;
use App\\Modules\\User\\Models\\Follow;
use App\\Modules\\User\\Models\\LinkClick;
EventQrConnect::where('link_id', ${Number(seed.linkId)})->delete();
Rsvp::where('link_id', ${Number(seed.linkId)})->delete();
LinkClick::where('link_id', ${Number(seed.linkId)})->delete();
IcsData::where('link_id', ${Number(seed.linkId)})->delete();
Link::withoutGlobalScope('workspace')->where('id', ${Number(seed.linkId)})->delete();
foreach ([${Number(seed.hostId)}, ${Number(seed.guestId)}] as $uid) {
  Follow::where('follower_id', $uid)->orWhere('creator_id', $uid)->delete();
  $u = User::find($uid);
  if ($u) { $u->tokens()->delete(); $u->delete(); }
}
echo "CLEANED\\n";
`;
  try {
    runTinker(php);
    log("fixture cleaned up");
  } catch (e) {
    log(`cleanup failed (non-fatal): ${e?.message ?? e}`);
  }
}

// ---------------------------------------------------------------------------
// Browser flow
// ---------------------------------------------------------------------------

async function newSignedInContext(browser, token, user) {
  const context = await browser.newContext({ viewport: VIEWPORT });
  await context.addInitScript(
    ({ token, user }) => {
      try {
        window.localStorage.setItem("1inme.onboarding.complete", "1");
        window.localStorage.setItem("1inme.auth.token", token);
        window.localStorage.setItem("1inme.auth.user", JSON.stringify(user));
      } catch {}
    },
    { token, user },
  );
  return context;
}

async function run(appUrl, seed) {
  const browser = await chromium.launch();
  try {
    // ── 1. HOST: the Connect QR screen ──────────────────────────────────
    const hostCtx = await newSignedInContext(browser, seed.hostToken, {
      id: seed.hostId,
      display_name: "CQR Host",
      email: HOST_EMAIL,
    });
    const hostPage = await hostCtx.newPage();
    hostPage.setDefaultTimeout(STEP_TIMEOUT_MS);

    const qrApiPromise = hostPage.waitForResponse(
      (r) =>
        r.url().includes(`/api/v1/links/${seed.linkId}/connect-qr`) &&
        r.status() === 200,
      { timeout: NAV_TIMEOUT_MS },
    );
    log("host: opening the Connect QR screen…");
    await hostPage.goto(`${appUrl}/events/connect-qr/${seed.linkId}`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });
    const qrRes = await qrApiPromise;
    const qrData = (await qrRes.json())?.data ?? {};
    if (!qrData.connect_url?.includes("?src=connect_qr")) {
      fail(`connect_url missing ?src=connect_qr tag: ${qrData.connect_url}`);
    }
    if (!qrData.qr_svg?.includes("<svg")) {
      fail("connect-qr API returned no SVG payload");
    }
    log(`host: real API returned QR for ${qrData.connect_url}`);

    // The SvgXml QR renders as a real <svg> in the DOM with QR modules.
    await hostPage
      .locator("svg path, svg rect")
      .first()
      .waitFor({ state: "visible" });
    const svgStats = await hostPage.evaluate(() => {
      let best = 0;
      for (const svg of document.querySelectorAll("svg")) {
        best = Math.max(best, svg.querySelectorAll("path, rect").length);
      }
      return { svgCount: document.querySelectorAll("svg").length, best };
    });
    if (svgStats.best < 1) fail("no rendered SVG with QR shapes found");
    log(
      `host: SVG QR rendered (${svgStats.svgCount} svg(s), densest has ${svgStats.best} shapes)`,
    );
    // The connect URL is shown under the QR for sanity-checking/printing.
    await hostPage
      .getByText(qrData.connect_url, { exact: true })
      .waitFor({ state: "visible" });
    await hostPage
      .getByText("Connect QR E2E Party")
      .first()
      .waitFor({ state: "visible" });
    log("host: connect URL + event title are visible under the QR");
    await hostPage.close();

    // ── 2. GUEST: one-tap RSVP & Connect ────────────────────────────────
    const guestCtx = await newSignedInContext(browser, seed.guestToken, {
      id: seed.guestId,
      display_name: "CQR Guest",
      email: GUEST_EMAIL,
    });
    const guestPage = await guestCtx.newPage();
    guestPage.setDefaultTimeout(STEP_TIMEOUT_MS);

    log("guest: opening the QR-tagged event page…");
    await guestPage.goto(`${appUrl}/events/${seed.alias}?src=connect_qr`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });

    // The prompt card renders only for ?src=connect_qr.
    await guestPage
      .getByText("You scanned the host's Connect QR", { exact: false })
      .waitFor({ state: "visible" });
    const connectBtn = guestPage.getByText("RSVP & Connect", { exact: true }).last();
    await connectBtn.waitFor({ state: "visible" });
    log('guest: "RSVP & Connect" prompt is visible — tapping…');

    const connectApiPromise = guestPage.waitForResponse(
      (r) =>
        r.url().includes(`/api/v1/events/${seed.alias}/connect`) &&
        r.request().method() === "POST",
      { timeout: NAV_TIMEOUT_MS },
    );
    await connectBtn.click();
    const connectRes = await connectApiPromise;
    if (connectRes.status() !== 200) {
      fail(
        `connect endpoint returned ${connectRes.status()}: ${await connectRes.text()}`,
      );
    }
    const connectJson = await connectRes.json();
    if (connectJson?.success !== true) {
      fail(`connect endpoint success!=true: ${JSON.stringify(connectJson)}`);
    }
    await guestPage
      .getByText("You're connected!", { exact: true })
      .waitFor({ state: "visible" });
    log("guest: success state rendered — You're connected!");
    await guestPage.close();

    // ── 3. Server-side oracle: real Rsvp + EventQrConnect + Follow rows ─
    const rows = verifyServerRows(seed);
    if (!rows.rsvp || rows.rsvp.response !== "yes" || rows.rsvp.source !== "connect_qr") {
      fail(`server RSVP row wrong: ${JSON.stringify(rows.rsvp)}`);
    }
    if (!rows.connect || Number(rows.connect.rsvp_id) !== Number(rows.rsvp.id)) {
      fail(`server EventQrConnect row wrong: ${JSON.stringify(rows.connect)}`);
    }
    if (rows.connect.was_new_user !== false) {
      fail("existing guest wrongly marked as a new user");
    }
    if (!rows.connect.followed || !rows.followed) {
      fail(`follow not recorded: ${JSON.stringify(rows)}`);
    }
    log(
      `server: Rsvp #${rows.rsvp.id} (yes/${rows.rsvp.status}/connect_qr) + EventQrConnect (followed) verified on the real DB`,
    );

    // ── 4. HOST: Visitor Insights shows the QR Connect panel ────────────
    const hostCtx2 = await newSignedInContext(browser, seed.hostToken, {
      id: seed.hostId,
      display_name: "CQR Host",
      email: HOST_EMAIL,
    });
    const insightsPage = await hostCtx2.newPage();
    insightsPage.setDefaultTimeout(STEP_TIMEOUT_MS);

    const visitorsApiPromise = insightsPage.waitForResponse(
      (r) =>
        r.url().includes(`/api/v1/links/${seed.linkId}/visitors`) &&
        r.status() === 200,
      { timeout: NAV_TIMEOUT_MS },
    );
    log("host: opening Visitor Insights…");
    await insightsPage.goto(`${appUrl}/links/${seed.linkId}/visitors`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });
    const visRes = await visitorsApiPromise;
    const visQr = (await visRes.json())?.data?.qr_connect;
    if (!visQr) fail("visitors API returned no qr_connect block for the event link");
    if (visQr.connected !== 1 || visQr.rsvps !== 1 || visQr.follows !== 1) {
      fail(`qr_connect stats wrong: ${JSON.stringify(visQr)}`);
    }
    log(
      `host: visitors API qr_connect = ${JSON.stringify(visQr)} — panel data is live`,
    );

    await insightsPage
      .getByText("QR Connect", { exact: true })
      .waitFor({ state: "visible" });
    await insightsPage
      .getByText("Guests who scanned your Connect QR", { exact: false })
      .waitFor({ state: "visible" });
    // The RSVPs / New follows tiles render the real counts.
    for (const label of ["RSVPs", "New follows"]) {
      await insightsPage
        .getByText(label, { exact: true })
        .waitFor({ state: "visible" });
    }
    await insightsPage
      .getByText("Get the Connect QR", { exact: true })
      .waitFor({ state: "visible" });
    log("host: QR Connect section + stat tiles + QR shortcut are visible");
    await hostCtx2.close();

    log(
      "PASS — host QR screen, guest one-tap RSVP & Connect (with real DB rows) and the Visitor Insights QR Connect panel verified end to end against the real API.",
    );
  } finally {
    await browser.close();
  }
}

// ---------------------------------------------------------------------------

async function main() {
  const laravel = await bootLaravel();
  if (!laravel) skip("could not boot a Laravel dev server in this environment");

  let seed;
  try {
    seed = seedFixture();
  } catch (e) {
    skip(`could not seed the fixture over the shared RDS: ${e?.message ?? e}`);
  }
  log(
    `seeded host=${seed.hostId} guest=${seed.guestId} link=${seed.linkId} alias=${seed.alias}`,
  );

  const { acquireServer, stopExpo } = createExpoServerManager(log);
  // Always boot a throwaway server: EXPO_PUBLIC_API_BASE_URL must be baked
  // into the bundle at boot, so a pre-warmed APP_URL server can't be reused.
  const server = await acquireServer("connect-qr-real-api", null, {
    EXPO_PUBLIC_API_BASE_URL: laravel.base,
  });
  if (!server) {
    cleanupFixture(seed);
    skip("could not boot a throwaway Expo web server in this environment");
  }
  const appUrl = server.appUrl.replace(/\/$/, "");
  try {
    await run(appUrl, seed);
  } catch (err) {
    if (isTransientEnvError(err)) {
      cleanupFixture(seed);
      skip(`transient environment error: ${err.message}`);
    }
    throw err;
  } finally {
    if (!server.explicit && server.child) stopExpo(server.child);
    cleanupFixture(seed);
  }
  process.exit(0);
}

runHarness(main, {
  log,
  timeoutMs: 25 * 60_000,
  onError: (err) => fail(err?.stack || String(err)),
});
