#!/usr/bin/env node
/**
 * REAL-API e2e for the Links-tab quick-shorten customization sheet:
 * long-pressing the bolt opens a sheet where the user can type a
 * destination, pick a custom back-half (alias) and choose which domain
 * the short link lives on.
 *
 * The loop it proves, every API call against a real Laravel server backed
 * by the real database:
 *   1. Long-press (mouse down → hold → up) on the bolt opens the sheet,
 *      pre-filled from the clipboard.
 *   2. Typing a back-half fires the debounced GET /links/check-alias and
 *      the "✓ available" verdict renders.
 *   3. Picking the seeded custom (admin-global) domain re-runs the alias
 *      check scoped to that domain (`domain_id` in the query string).
 *   4. "Shorten" POSTs /links/quick-shorten carrying the alias +
 *      domain_id; the 201 short_url uses the branded host, and the
 *      confirmation toast shows that same branded short URL.
 *   5. The tap-guard held: exactly ONE quick-shorten POST fired (the
 *      long-press release must not also trigger the one-tap shorten).
 *
 * Infrastructure (same pattern as test-stock-image-real-api-e2e.mjs):
 *   - Boots its own Laravel dev server (php -S + vendored framework
 *     router, cwd=public/ — artisan serve strips DB_* env).
 *   - Seeds via artisan tinker: demo user + Sanctum token, an untagged
 *     global verified domain (untagged ⇒ entitled to every plan), and
 *     temporarily lifts the user onto an unlimited-links plan so the
 *     plan link-cap gate can't 402 the create (restored in cleanup).
 *   - Boots a throwaway Expo web server with EXPO_PUBLIC_API_BASE_URL
 *     baked to the Laravel server.
 *   - Grants clipboard permissions so the sheet's clipboard pre-fill
 *     doesn't reject on headless chromium.
 *
 * SKIPs (exit 0) when the environment can't support it. Always grep the
 * log for the literal PASS line — a SKIP also exits 0.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:quick-shorten-sheet-e2e
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
  console.log("[quick-shorten-sheet-e2e]", ...args);
}
function fail(msg) {
  console.error("[quick-shorten-sheet-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[quick-shorten-sheet-e2e] SKIP:", msg);
  process.exit(0);
}

const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");
const LARAVEL_ROOT = path.resolve(MOBILE_ROOT, "..", "1inme");
const VIEWPORT = { width: 400, height: 780 };
// Cross-region RDS makes every request slow; budget generously.
const STEP_TIMEOUT_MS = 90_000;
const NAV_TIMEOUT_MS = 120_000;

const DEMO_EMAIL = "sayzioapp@gmail.com";
// Per-run unique names (shared RDS across parallel task envs).
const RUN_ID = Date.now().toString(36) + process.pid.toString(36);
const ALIAS_PREFIX = "e2e-qshort-";
const ALIAS = ALIAS_PREFIX + RUN_ID;
const DOMAIN_PREFIX = "e2e-qs-";
const DOMAIN_HOST = `${DOMAIN_PREFIX}${RUN_ID}.example.com`;
const DEST_URL = `https://example.com/quick-shorten-e2e/${RUN_ID}`;

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
// Fixture seed / cleanup via artisan tinker
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
use App\\Modules\\User\\Models\\Domain;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\Schema;

$u = User::where('email', '${DEMO_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${DEMO_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
if ($u->onboarded_at === null) { $u->onboarded_at = now(); $u->save(); }

// The quick-shorten endpoint enforces the plan link cap; the shared demo
// account usually has plenty of links, so lift it onto an unlimited-links
// plan for the run (restored in cleanup).
$originalPlanId = $u->plan_id;
$unlimited = Plan::all()->first(function ($p) {
  $f = is_array($p->features) ? $p->features : [];
  return (int) ($f['max_links'] ?? 5) === -1;
});
if ($unlimited && (int) $u->plan_id !== (int) $unlimited->id) {
  $u->plan_id = $unlimited->id;
  $u->save();
}

// Prune stale fixtures from previous runs (shared RDS across task envs).
$cut = now()->subHours(2);
Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}%')
  ->where('user_id', $u->id)->where('created_at', '<', $cut)->delete();
Domain::withoutGlobalScope('workspace')
  ->where('domain', 'like', '${DOMAIN_PREFIX}%')
  ->where('created_at', '<', $cut)->delete();

// Untagged admin-global verified domain: user_id NULL + verified + active
// with no plan/badge tags means it is open to every account.
$d = new Domain([
  'domain' => '${DOMAIN_HOST}', 'type' => 'custom',
  'is_verified' => true, 'is_active' => true, 'is_primary' => false,
  'verified_at' => now(),
]);
$d->user_id = null;
$d->save();
// A workspace-creating observer (tinker context) or global scope default
// may stamp a workspace id; global domains must have workspace_id NULL.
if (Schema::hasColumn('domains', 'workspace_id')) {
  DB::table('domains')->where('id', $d->id)->update(['workspace_id' => null, 'user_id' => null]);
}

$token = $u->createToken('e2e-quick-shorten')->plainTextToken;
echo 'SEED_JSON:' . json_encode([
  'userId' => $u->id, 'domainId' => $d->id, 'domain' => $d->domain,
  'originalPlanId' => $originalPlanId, 'token' => $token,
]) . "\\n";
`;
  const out = runTinker(php);
  const m = out.match(/SEED_JSON:(\{.*\})/);
  if (!m) fail(`seed produced no SEED_JSON marker; output:\n${out}`);
  return JSON.parse(m[1]);
}

function cleanupFixture(seed) {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Domain;
$u = User::where('email', '${DEMO_EMAIL}')->first();
if ($u) {
  Link::withoutGlobalScope('workspace')
    ->where('user_id', $u->id)->where('alias', '${ALIAS}')->delete();
  ${seed.originalPlanId != null ? `if ((int) $u->plan_id !== ${Number(seed.originalPlanId)}) { $u->plan_id = ${Number(seed.originalPlanId)}; $u->save(); }` : ""}
}
Domain::withoutGlobalScope('workspace')->where('id', ${Number(seed.domainId)})->delete();
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

// RN-web Pressable onLongPress fires after ~500ms of sustained press; hold
// the mouse down well past that before releasing.
async function longPress(page, locator) {
  const box = await locator.boundingBox();
  if (!box) fail("long-press target has no bounding box");
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  await page.mouse.down();
  await page.waitForTimeout(800);
  await page.mouse.up();
}

async function run(appUrl, seed) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({
      viewport: VIEWPORT,
      // The sheet pre-fills from the clipboard on open; without these the
      // headless readText() rejects and the sheet could fail to open.
      permissions: ["clipboard-read", "clipboard-write"],
    });
    await context.addInitScript(
      ({ token, user }) => {
        try {
          window.localStorage.setItem("1inme.onboarding.complete", "1");
          window.localStorage.setItem("1inme.auth.token", token);
          window.localStorage.setItem("1inme.auth.user", JSON.stringify(user));
        } catch {}
      },
      {
        token: seed.token,
        user: { id: seed.userId, display_name: "Demo User", email: DEMO_EMAIL },
      },
    );

    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    // Count every quick-shorten POST so we can prove the long-press
    // release didn't ALSO fire the one-tap clipboard shorten.
    let quickShortenPosts = 0;
    page.on("request", (r) => {
      if (
        r.url().includes("/api/v1/links/quick-shorten") &&
        r.method() === "POST"
      ) {
        quickShortenPosts++;
      }
    });

    log("opening the Links tab…");
    await page.goto(`${appUrl}/links`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });

    const bolt = page.getByLabel("Quick-shorten from clipboard");
    await bolt.waitFor({ state: "visible" });

    // Seed the clipboard with the destination so the sheet pre-fills it.
    await page.evaluate(
      (text) => navigator.clipboard.writeText(text),
      DEST_URL,
    );

    // 1. Long-press the bolt → the customization sheet opens.
    log("long-pressing the bolt…");
    await longPress(page, bolt);
    await page.getByText("Quick shorten", { exact: true }).waitFor({
      state: "visible",
    });
    log("sheet opened");
    if (quickShortenPosts > 0) {
      fail(
        "the long-press release also fired the one-tap quick-shorten POST (tap guard broken)",
      );
    }

    // Destination: prefer the clipboard pre-fill, but fill defensively so
    // the run doesn't hinge on headless clipboard quirks.
    const destInput = page.getByPlaceholder(
      "https://example.com/very/long/path",
    );
    await destInput.waitFor({ state: "visible" });
    const preFilled = await destInput.inputValue();
    if (preFilled === DEST_URL) {
      log("destination pre-filled from the clipboard");
    } else {
      log(
        `destination not pre-filled (clipboard read returned ${JSON.stringify(preFilled)}); typing it`,
      );
      await destInput.fill(DEST_URL);
    }

    // 2. Back-half → debounced alias check (no domain chosen yet: the
    //    seeded domain is not primary, so the default host is selected).
    const aliasInput = page.getByPlaceholder("leave blank to auto-generate");
    const firstCheckPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/links/check-alias") &&
        r.url().includes(`alias=${ALIAS}`) &&
        r.status() === 200,
    );
    await aliasInput.fill(ALIAS);
    const firstCheck = await firstCheckPromise;
    if (firstCheck.url().includes("domain_id=")) {
      fail(
        `alias check before picking a domain unexpectedly carried a domain_id: ${firstCheck.url()}`,
      );
    }
    const firstVerdict = await firstCheck.json();
    if (firstVerdict?.available !== true) {
      fail(
        `fresh per-run alias reported unavailable on the default host: ${JSON.stringify(firstVerdict)}`,
      );
    }
    await page.getByText(/^✓ /).waitFor({ state: "visible" });
    log("alias check on the default host: available ✓");

    // 3. Pick the seeded branded domain → the alias check re-runs SCOPED
    //    to that domain (domain_id in the query string).
    const domainChip = page.getByText(seed.domain, { exact: true });
    await domainChip.waitFor({ state: "visible" });
    const scopedCheckPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/links/check-alias") &&
        r.url().includes(`alias=${ALIAS}`) &&
        r.url().includes(`domain_id=${seed.domainId}`) &&
        r.status() === 200,
    );
    await domainChip.click();
    const scopedVerdict = await (await scopedCheckPromise).json();
    if (scopedVerdict?.available !== true) {
      fail(
        `alias reported unavailable on the chosen domain: ${JSON.stringify(scopedVerdict)}`,
      );
    }
    await page.getByText(/^✓ /).waitFor({ state: "visible" });
    log(`alias check re-ran scoped to domain_id=${seed.domainId}: available ✓`);

    // 4. Submit → POST carries alias + domain_id; 201 short_url uses the
    //    branded host; the toast shows the branded short URL.
    const createPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/links/quick-shorten") &&
        r.request().method() === "POST",
    );
    await page.getByLabel("Create short link").click();
    const createRes = await createPromise;
    if (createRes.status() !== 201) {
      fail(
        `quick-shorten POST returned ${createRes.status()}: ${await createRes.text().catch(() => "")}`,
      );
    }
    const createReq = JSON.parse(createRes.request().postData() || "{}");
    if (createReq.alias !== ALIAS) {
      fail(`POST body alias mismatch: ${JSON.stringify(createReq)}`);
    }
    if (Number(createReq.domain_id) !== Number(seed.domainId)) {
      fail(`POST body domain_id mismatch: ${JSON.stringify(createReq)}`);
    }
    const created = (await createRes.json())?.data;
    const expectedShortUrl = `https://${seed.domain}/${ALIAS}`;
    if (created?.short_url !== expectedShortUrl) {
      fail(
        `created short_url is not on the branded host: got ${created?.short_url}, expected ${expectedShortUrl}`,
      );
    }
    log(`created ${created.short_url} (branded host ✓)`);

    // Sheet closes and the toast confirms with the branded short URL.
    await page
      .getByText(`Short link created and copied: ${expectedShortUrl}`)
      .waitFor({ state: "visible" });
    log("toast shows the branded short URL");

    if (quickShortenPosts !== 1) {
      fail(
        `expected exactly 1 quick-shorten POST, saw ${quickShortenPosts} (tap guard / double-submit)`,
      );
    }

    await context.close();
    log(
      "PASS — long-press sheet: open → alias check (default + branded host) → domain pick → create on the branded domain → toast.",
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
  log(`seeded domain=${seed.domain} (id=${seed.domainId}) alias=${ALIAS}`);

  const { acquireServer, stopExpo } = createExpoServerManager(log);
  // Always boot a throwaway server: EXPO_PUBLIC_API_BASE_URL must be baked
  // into the bundle at boot, so a pre-warmed APP_URL server can't be reused.
  const server = await acquireServer("quick-shorten-sheet", null, {
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
      skip(`transient environment error: ${err.message}`);
    }
    throw err;
  } finally {
    cleanupFixture(seed);
    if (!server.explicit && server.child) stopExpo(server.child);
  }
  process.exit(0);
}

runHarness(main, {
  log,
  timeoutMs: 25 * 60_000,
  onError: (err) => fail(err?.stack || String(err)),
});
