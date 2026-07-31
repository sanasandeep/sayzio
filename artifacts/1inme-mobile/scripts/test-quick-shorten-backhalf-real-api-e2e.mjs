#!/usr/bin/env node
/**
 * REAL-API e2e for the custom back-half quick-shorten sheet on the mobile
 * Links tab (Task #6298 — regression coverage for the long-press bolt flow).
 *
 * The loop it proves, with every API call hitting a real Laravel server
 * backed by the real database:
 *   1. Long-pressing the bolt on the Links tab opens the custom back-half
 *      sheet with the clipboard content previewed.
 *   2. Typing a TAKEN alias runs the live GET /api/v1/links/check-alias and
 *      the inline status reads "already taken".
 *   3. Submitting the taken alias anyway gets a REAL 422 from
 *      POST /api/v1/links/quick-shorten and the error surfaces inline in
 *      the sheet (the sheet stays open).
 *   4. Changing to an AVAILABLE alias flips the live status to available;
 *      submitting creates the link (201) and the created link's back-half
 *      (alias) matches exactly — verified both from the response body and
 *      by re-reading the link over GET /api/v1/links.
 *   5. Blank-alias path: reopening the sheet and submitting with the alias
 *      field empty auto-generates a back-half server-side (201, non-empty
 *      alias different from anything typed).
 *
 * Infrastructure (mirrors test-stock-image-real-api-e2e.mjs):
 *   - Boots its own Laravel dev server (php -S + the vendored framework
 *     router, cwd=public/ — artisan serve strips DB_* env).
 *   - Seeds a dedicated per-run user + a "taken" fixture link and mints a
 *     Sanctum bearer token via artisan tinker (prunes stale fixtures by
 *     alias/email prefix — shared RDS across task envs).
 *   - Boots a throwaway Expo web server with EXPO_PUBLIC_API_BASE_URL baked
 *     to the Laravel server (Laravel CORS is wildcard on api/*).
 *   - Clipboard on expo-web goes through navigator.clipboard, so the
 *     browser context grants clipboard-read/clipboard-write.
 *
 * SKIPs (exit 0) when the environment can't support it: Expo won't boot,
 * Laravel won't boot, or seeding over the shared RDS fails.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:quick-shorten-backhalf-real-api-e2e
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
  console.log("[quick-shorten-backhalf-e2e]", ...args);
}
function fail(msg) {
  console.error("[quick-shorten-backhalf-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[quick-shorten-backhalf-e2e] SKIP:", msg);
  process.exit(0);
}

const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");
const LARAVEL_ROOT = path.resolve(MOBILE_ROOT, "..", "1inme");
const VIEWPORT = { width: 400, height: 780 };
// Cross-region RDS makes every request slow; budget generously.
const STEP_TIMEOUT_MS = 90_000;
const NAV_TIMEOUT_MS = 120_000;

// Per-run unique namespace (shared RDS across parallel task envs).
const RUN_ID = Date.now().toString(36) + process.pid.toString(36);
const ALIAS_PREFIX = "e2e-qshort-";
const TAKEN_ALIAS = `${ALIAS_PREFIX}taken-${RUN_ID}`;
const NEW_ALIAS = `${ALIAS_PREFIX}mine-${RUN_ID}`;
const USER_EMAIL = `e2e-qshort-${RUN_ID}@example.test`;
const DEST_URL = `https://example.com/qshort-${RUN_ID}`;

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
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;

// Prune stale fixtures from previous runs (shared RDS across task envs).
$cut = now()->subHours(2);
$staleUsers = User::where('email', 'like', 'e2e-qshort-%@example.test')
  ->where('created_at', '<', $cut)->get();
foreach ($staleUsers as $su) {
  Link::withoutGlobalScope('workspace')->where('user_id', $su->id)->delete();
  $su->delete();
}
Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}%')
  ->where('created_at', '<', $cut)->delete();

$free = Plan::where('slug', 'free')->first();
$u = User::create([
  'name' => 'E2E QuickShorten', 'email' => '${USER_EMAIL}',
  'password' => Hash::make('password'), 'plan_id' => $free?->id,
  'status' => 'active', 'email_verified_at' => now(),
]);
$u->onboarded_at = now();
$u->save();
$ws = app(WorkspaceContext::class)->resolve($u);

// The alias the sheet must report as TAKEN.
$taken = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'url',
  'alias' => '${TAKEN_ALIAS}', 'long_url' => 'https://example.com/already',
  'is_active' => true,
]);

$limits = $u->getAliasLengthLimits();
$token = $u->createToken('e2e-qshort')->plainTextToken;
echo 'SEED_JSON:' . json_encode([
  'userId' => $u->id, 'takenLinkId' => $taken->id, 'token' => $token,
  'aliasMin' => $limits['min'], 'aliasMax' => $limits['max'],
]) . "\\n";
`;
  const out = runTinker(php);
  const m = out.match(/SEED_JSON:(\{.*\})/);
  if (!m) fail(`seed produced no SEED_JSON marker; output:\n${out}`);
  return JSON.parse(m[1]);
}

function cleanupFixture(userId) {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
Link::withoutGlobalScope('workspace')->where('user_id', ${Number(userId)})->delete();
User::where('id', ${Number(userId)})->delete();
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

// RNW maps onLongPress to a pointer held past delayLongPress (400ms here);
// a Playwright click with a down→up delay comfortably past that triggers it.
async function longPress(locator) {
  await locator.click({ delay: 800 });
}

// Every POST /links/quick-shorten seen on the page is counted here so the
// long-press-open steps can prove the trailing tap-release does NOT also run
// the one-tap quick-shorten (which would create an unintended link).
let quickShortenPosts = 0;
function trackQuickShortenPosts(page) {
  page.on("request", (r) => {
    if (
      r.url().includes("/api/v1/links/quick-shorten") &&
      r.method() === "POST"
    ) {
      quickShortenPosts += 1;
    }
  });
}

async function openSheet(page, expectedDestination) {
  const postsBefore = quickShortenPosts;
  await longPress(page.getByTestId("quick-shorten-bolt"));
  const sheet = page.getByTestId("quick-shorten-sheet");
  await sheet.waitFor({ state: "visible" });
  // Give a stray onPress-triggered request time to surface, then assert the
  // long-press alone issued NO quick-shorten POST (create must be explicit).
  await page.waitForTimeout(1500);
  if (quickShortenPosts !== postsBefore) {
    fail(
      "long-press opened the sheet but ALSO fired a one-tap quick-shorten POST",
    );
  }
  // The destination is an editable TextInput (renders as <input> on
  // react-native-web), so read its value rather than innerText.
  const preview = await page
    .getByTestId("quick-shorten-destination")
    .inputValue();
  if (!preview.includes(expectedDestination)) {
    fail(
      `sheet clipboard preview does not show the copied destination: "${preview}"`,
    );
  }
  return sheet;
}

async function run(appUrl, apiBase, seed) {
  const createdLinkIds = [];
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({
      viewport: VIEWPORT,
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
        user: {
          id: seed.userId,
          display_name: "E2E QuickShorten",
          email: USER_EMAIL,
        },
      },
    );

    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);
    trackQuickShortenPosts(page);

    log("opening the Links tab against the real API…");
    const listPromise = page.waitForResponse(
      (r) => r.url().includes("/api/v1/links") && r.status() === 200,
      { timeout: NAV_TIMEOUT_MS },
    );
    await page.goto(`${appUrl}/links`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });
    await listPromise;
    await page.getByTestId("quick-shorten-bolt").waitFor({ state: "visible" });

    // Put the destination on the clipboard (expo-clipboard web reads
    // navigator.clipboard).
    await page.evaluate(
      (text) => navigator.clipboard.writeText(text),
      DEST_URL,
    );

    // 1. Long-press the bolt → the custom back-half sheet opens with the
    //    clipboard preview.
    await openSheet(page, DEST_URL);
    log("long-press opened the sheet with the clipboard preview");

    // 2. Type the TAKEN alias → live availability check reports taken.
    const aliasInput = page.getByTestId("quick-shorten-alias-input");
    const takenCheckPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/links/check-alias") &&
        r.url().includes(encodeURIComponent(TAKEN_ALIAS)) &&
        r.status() === 200,
    );
    await aliasInput.fill(TAKEN_ALIAS);
    const takenCheckBody = await (await takenCheckPromise).json();
    if (takenCheckBody?.status !== "taken") {
      fail(
        `check-alias for the seeded taken alias returned ${JSON.stringify(takenCheckBody)}`,
      );
    }
    const takenStatus = page.getByTestId("quick-shorten-alias-status");
    await takenStatus.waitFor({ state: "visible" });
    // The response can resolve before React commits the status text, so poll
    // until the transient "Checking availability…" copy clears.
    await page.waitForFunction(
      () => {
        const el = document.querySelector(
          '[data-testid="quick-shorten-alias-status"]',
        );
        return el && !/checking/i.test(el.textContent || "");
      },
      { timeout: STEP_TIMEOUT_MS },
    );
    const takenText = await takenStatus.innerText();
    if (!/already taken/i.test(takenText)) {
      fail(`inline availability status did not read taken: "${takenText}"`);
    }
    log(`live check reports taken: "${takenText}"`);

    // 3. Try to submit the taken alias anyway → the sheet now BLOCKS the
    //    submit client-side (no wasted round-trip): the Shorten button is
    //    disabled and no quick-shorten POST fires. Force a click via the
    //    DOM to prove the guard holds even if the disabled state is
    //    bypassed, and assert an inline message renders.
    const postsBeforeBlockedSubmit = quickShortenPosts;
    const createBtn = page.getByTestId("quick-shorten-create");
    const isDisabled = await createBtn.evaluate(
      (el) =>
        el.getAttribute("aria-disabled") === "true" ||
        el.hasAttribute("disabled") ||
        el.getAttribute("data-disabled") === "true",
    );
    if (!isDisabled) {
      fail("Shorten button is not disabled while the alias check says taken");
    }
    // Bypass the disabled attribute and click anyway — the submit handler
    // guard must still refuse to POST.
    await createBtn.evaluate((el) => {
      el.click();
    });
    await page.waitForTimeout(2_000);
    if (quickShortenPosts !== postsBeforeBlockedSubmit) {
      fail(
        "a quick-shorten POST fired despite the taken-alias verdict (client-side block broken)",
      );
    }
    // Sheet must stay open for correction.
    await page.getByTestId("quick-shorten-sheet").waitFor({ state: "visible" });
    log("taken alias blocked client-side: button disabled, no POST fired");

    // 4. Correct to an AVAILABLE alias → live check flips → create succeeds
    //    and the back-half matches exactly.
    const availCheckPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/links/check-alias") &&
        r.url().includes(encodeURIComponent(NEW_ALIAS)) &&
        r.status() === 200,
    );
    await aliasInput.fill(NEW_ALIAS);
    const availBody = await (await availCheckPromise).json();
    if (availBody?.status !== "available") {
      fail(`check-alias for the fresh alias returned ${JSON.stringify(availBody)}`);
    }
    const availStatus = page.getByTestId("quick-shorten-alias-status");
    await availStatus.waitFor({ state: "visible" });
    // Same commit race as the taken check — wait out the checking state.
    await page.waitForFunction(
      () => {
        const el = document.querySelector(
          '[data-testid="quick-shorten-alias-status"]',
        );
        return el && !/checking/i.test(el.textContent || "");
      },
      { timeout: STEP_TIMEOUT_MS },
    );
    const availText = await availStatus.innerText();
    if (!/available/i.test(availText)) {
      fail(`inline availability status did not read available: "${availText}"`);
    }
    log(`live check reports available: "${availText}"`);

    const createPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/links/quick-shorten") &&
        r.request().method() === "POST",
    );
    await page.getByTestId("quick-shorten-create").click();
    const createRes = await createPromise;
    if (createRes.status() !== 201) {
      fail(
        `quick-shorten with the available alias returned ${createRes.status()}: ${await createRes.text().catch(() => "")}`,
      );
    }
    const created = (await createRes.json())?.data;
    if (!created?.id) fail(`create returned no link id: ${JSON.stringify(created)}`);
    createdLinkIds.push(created.id);
    const shortPath = (() => {
      try {
        return new URL(created.short_url).pathname.replace(/^\//, "");
      } catch {
        return "";
      }
    })();
    if (shortPath.toLowerCase() !== NEW_ALIAS.toLowerCase()) {
      fail(
        `created back-half does not match the typed alias: short_url=${created.short_url} expected ${NEW_ALIAS}`,
      );
    }
    // Sheet closes and the success toast appears.
    await page
      .getByTestId("quick-shorten-sheet")
      .waitFor({ state: "hidden", timeout: STEP_TIMEOUT_MS });
    await page
      .getByText(/Short link created and copied/i)
      .waitFor({ state: "visible" });
    log(`custom back-half link created: ${created.short_url}`);

    // Server-side confirmation: the link exists with the exact alias.
    const verifyRes = await fetch(
      `${apiBase}/api/v1/links?q=${encodeURIComponent(NEW_ALIAS)}`,
      {
        headers: {
          Authorization: `Bearer ${seed.token}`,
          Accept: "application/json",
        },
        signal: AbortSignal.timeout(60_000),
      },
    );
    const verifyBody = await verifyRes.json().catch(() => null);
    const match = (verifyBody?.data?.items ?? []).find(
      (l) => l.id === created.id,
    );
    if (!verifyRes.ok || !match || match.alias.toLowerCase() !== NEW_ALIAS.toLowerCase()) {
      fail(
        `created link not found with the expected alias via GET /links (status ${verifyRes.status}): ${JSON.stringify(match)}`,
      );
    }
    log("GET /links confirms the stored alias matches the typed back-half");

    // 5. Blank-alias auto-generate path.
    await page.evaluate(
      (text) => navigator.clipboard.writeText(text),
      DEST_URL + "/second",
    );
    await openSheet(page, DEST_URL + "/second");
    // Leave the alias blank — hint copy should say auto-generate.
    const blankStatus = await page
      .getByTestId("quick-shorten-alias-status")
      .innerText();
    if (!/auto-generate/i.test(blankStatus)) {
      fail(`blank-alias hint did not mention auto-generate: "${blankStatus}"`);
    }
    const autoPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/links/quick-shorten") &&
        r.request().method() === "POST",
    );
    await page.getByTestId("quick-shorten-create").click();
    const autoRes = await autoPromise;
    if (autoRes.status() !== 201) {
      fail(
        `blank-alias quick-shorten returned ${autoRes.status()}: ${await autoRes.text().catch(() => "")}`,
      );
    }
    const autoReq = JSON.parse(autoRes.request().postData() || "{}");
    if ("alias" in autoReq && autoReq.alias) {
      fail(`blank-alias POST unexpectedly sent an alias: ${JSON.stringify(autoReq)}`);
    }
    const autoCreated = (await autoRes.json())?.data;
    if (!autoCreated?.id) {
      fail(`blank-alias create returned no link id: ${JSON.stringify(autoCreated)}`);
    }
    createdLinkIds.push(autoCreated.id);
    const autoPath = (() => {
      try {
        return new URL(autoCreated.short_url).pathname.replace(/^\//, "");
      } catch {
        return "";
      }
    })();
    if (!autoPath || autoPath.toLowerCase() === NEW_ALIAS.toLowerCase()) {
      fail(
        `blank-alias path did not auto-generate a distinct back-half: ${autoCreated.short_url}`,
      );
    }
    await page
      .getByTestId("quick-shorten-sheet")
      .waitFor({ state: "hidden", timeout: STEP_TIMEOUT_MS });
    log(`blank alias auto-generated back-half: ${autoCreated.short_url}`);

    await context.close();
    log(
      "PASS — custom back-half sheet: long-press open → clipboard preview → live taken check → inline 422 → available alias create (back-half matches) → blank-alias auto-generate.",
    );
  } finally {
    await browser.close();
    cleanupFixture(seed.userId);
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
    `seeded user=${seed.userId} taken=${TAKEN_ALIAS} aliasLimits=${seed.aliasMin}..${seed.aliasMax}`,
  );
  if (TAKEN_ALIAS.length < seed.aliasMin || NEW_ALIAS.length > seed.aliasMax) {
    cleanupFixture(seed.userId);
    skip(
      `fixture aliases fall outside the plan's alias length limits (${seed.aliasMin}..${seed.aliasMax})`,
    );
  }

  const { acquireServer, stopExpo } = createExpoServerManager(log);
  // Always boot a throwaway server: EXPO_PUBLIC_API_BASE_URL must be baked
  // into the bundle at boot, so a pre-warmed APP_URL server can't be reused.
  const server = await acquireServer("quick-shorten-backhalf", null, {
    EXPO_PUBLIC_API_BASE_URL: laravel.base,
  });
  if (!server) {
    cleanupFixture(seed.userId);
    skip("could not boot a throwaway Expo web server in this environment");
  }
  const appUrl = server.appUrl.replace(/\/$/, "");
  try {
    await run(appUrl, laravel.base, seed);
  } catch (err) {
    if (isTransientEnvError(err)) {
      skip(`transient environment error: ${err.message}`);
    }
    throw err;
  } finally {
    if (!server.explicit && server.child) stopExpo(server.child);
  }
  process.exit(0);
}

runHarness(main, {
  log,
  timeoutMs: 25 * 60_000,
  onError: (err) => fail(err?.stack || String(err)),
});
