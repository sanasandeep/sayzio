#!/usr/bin/env node
/**
 * REAL-API e2e for the contact "jump to record" flow (Task #6532).
 *
 * The mobile contact screen renders an "Activity across Sayzio" timeline and
 * deep-links each row straight to the exact record via highlight params
 * (contactActivityHref → /links/{id}/restaurant-orders?highlight={orderId}).
 * The href mapping is unit-tested (test-contact-activity-href.mjs) and the
 * backend refs are covered by ApiContactActivityRecordRefsTest, but the
 * actual scroll-and-highlight UX had never been exercised in a running app.
 *
 * The full loop this proves against a REAL Laravel backend + real RDS:
 *   1. GET /api/v1/contacts/{id}/activity returns a restaurant_orders group
 *      whose item refs carry link_id + order_id.
 *   2. Tapping the activity row navigates to
 *      /links/{linkId}/restaurant-orders?highlight={orderId}.
 *   3. The orders screen lands on the "All" filter (the highlighted order is
 *      completed, so the default "Open" filter would hide it).
 *   4. The highlighted order card gets the primary-color 2px outline while
 *      sibling cards keep the plain 1px border.
 *   5. The card is auto-scrolled into the viewport (it's seeded as the OLDEST
 *      of 8 orders, i.e. last in the id-desc list, so without the onLayout
 *      scrollTo it would sit far below the fold).
 *
 * Infrastructure mirrors test-stock-image-real-api-e2e.mjs:
 *   - Boots its own Laravel dev server (php -S + vendored framework router,
 *     cwd=public/ — artisan serve strips DB_* env).
 *   - Seeds a dedicated per-run fixture user + contact + restaurant link/menu
 *     + orders and mints a real Sanctum token via `artisan tinker --execute`
 *     (prunes stale fixtures by email/alias prefix — shared RDS across envs).
 *   - Boots a throwaway Expo web server with EXPO_PUBLIC_API_BASE_URL baked.
 *
 * SKIPs (exit 0) when the environment can't support it. ALWAYS grep the log
 * for the literal PASS line — a SKIP also exits 0.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:contact-jump-to-record-real-api-e2e
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
  console.log("[contact-jump-e2e]", ...args);
}
function fail(msg) {
  console.error("[contact-jump-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[contact-jump-e2e] SKIP:", msg);
  process.exit(0);
}

const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");
const LARAVEL_ROOT = path.resolve(MOBILE_ROOT, "..", "1inme");
const VIEWPORT = { width: 400, height: 780 };
// Cross-region RDS makes every request slow; budget generously.
const STEP_TIMEOUT_MS = 90_000;
const NAV_TIMEOUT_MS = 120_000;

const RUN_ID = Date.now().toString(36) + process.pid.toString(36);
const EMAIL_PREFIX = "e2e-jumprec-";
const EMAIL = `${EMAIL_PREFIX}${RUN_ID}@example.test`;
const ALIAS_PREFIX = "e2e-jumprec-";
const ALIAS = ALIAS_PREFIX + RUN_ID;
// Filler orders ABOVE the highlighted one (list is sorted id desc, so the
// oldest/lowest-id order — the contact's — renders last and needs scrolling).
const FILLER_ORDERS = 7;

// Theme primary (constants/colors.ts): blue600 light / blue400 dark.
const PRIMARY_RGB = new Set(["rgb(61, 107, 255)", "rgb(125, 155, 255)"]);

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
use App\\Modules\\User\\Models\\Contact;
use App\\Modules\\User\\Models\\RestaurantMenu;
use App\\Modules\\User\\Models\\RestaurantOrder;
use App\\Modules\\User\\Models\\RestaurantOrderItem;
use Illuminate\\Support\\Facades\\Hash;

// Prune stale fixture users from previous runs (shared RDS across envs).
$cut = now()->subHours(2);
$stale = User::where('email', 'like', '${EMAIL_PREFIX}%@example.test')
  ->where('created_at', '<', $cut)->get();
foreach ($stale as $su) {
  $links = Link::withoutGlobalScope('workspace')->where('user_id', $su->id)->get();
  foreach ($links as $sl) {
    $menus = RestaurantMenu::where('link_id', $sl->id)->get();
    foreach ($menus as $m) {
      $orderIds = RestaurantOrder::withoutGlobalScope('workspace')->where('menu_id', $m->id)->pluck('id');
      RestaurantOrderItem::whereIn('order_id', $orderIds)->delete();
      RestaurantOrder::withoutGlobalScope('workspace')->whereIn('id', $orderIds)->delete();
      $m->delete();
    }
    $sl->delete();
  }
  Contact::where('user_id', $su->id)->delete();
  $su->tokens()->delete();
  $su->delete();
}

$free = Plan::where('slug', 'free')->first();
$u = User::create([
  'name' => 'Jump E2E', 'email' => '${EMAIL}',
  'password' => Hash::make('password'), 'plan_id' => $free?->id,
  'status' => 'active', 'email_verified_at' => now(),
]);
$u->onboarded_at = now(); $u->save();

$contact = Contact::create(['user_id' => $u->id, 'display_name' => 'Diner Dan']);

$link = Link::withoutGlobalScope('workspace')->create([
  'user_id' => $u->id, 'type' => 'restaurant_menu',
  'alias' => '${ALIAS}', 'title' => 'E2E Jump Menu', 'is_active' => true,
]);
$menu = RestaurantMenu::create([
  'link_id' => $link->id, 'user_id' => $u->id,
  'mode' => 'order', 'currency' => 'USD',
]);

$mkOrder = function (string $status, ?string $name = null) use ($menu, $link) {
  $o = RestaurantOrder::withoutGlobalScope('workspace')->create([
    'menu_id' => $menu->id, 'link_id' => $link->id, 'status' => $status,
    'customer_name' => $name, 'subtotal' => 12.50, 'total' => 12.50,
    'currency' => 'USD',
  ]);
  RestaurantOrderItem::create([
    'order_id' => $o->id, 'item_id' => null, 'name' => 'Margherita Pizza',
    'unit_price' => 12.50, 'quantity' => 1, 'line_total' => 12.50,
  ]);
  return $o;
};

// The contact's order is created FIRST (lowest id) so it renders LAST in the
// id-desc list — far below the fold behind the fillers.
$target = $mkOrder('completed', 'Diner Dan');
$target->forceFill(['contact_id' => $contact->id])->saveQuietly();
for ($i = 0; $i < ${FILLER_ORDERS}; $i++) { $mkOrder('new', 'Filler ' . $i); }

$token = $u->createToken('e2e-jump-to-record')->plainTextToken;
echo 'SEED_JSON:' . json_encode([
  'userId' => $u->id, 'contactId' => $contact->id, 'linkId' => $link->id,
  'menuId' => $menu->id, 'orderId' => $target->id, 'token' => $token,
]) . "\\n";
`;
  const out = runTinker(php);
  const m = out.match(/SEED_JSON:(\{.*\})/);
  if (!m) fail(`seed produced no SEED_JSON marker; output:\n${out}`);
  return JSON.parse(m[1]);
}

function cleanupFixture(seed) {
  if (!seed) return;
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\Contact;
use App\\Modules\\User\\Models\\RestaurantMenu;
use App\\Modules\\User\\Models\\RestaurantOrder;
use App\\Modules\\User\\Models\\RestaurantOrderItem;
$orderIds = RestaurantOrder::withoutGlobalScope('workspace')->where('menu_id', ${Number(seed.menuId)})->pluck('id');
RestaurantOrderItem::whereIn('order_id', $orderIds)->delete();
RestaurantOrder::withoutGlobalScope('workspace')->whereIn('id', $orderIds)->delete();
RestaurantMenu::where('id', ${Number(seed.menuId)})->delete();
Link::withoutGlobalScope('workspace')->where('id', ${Number(seed.linkId)})->delete();
Contact::where('id', ${Number(seed.contactId)})->delete();
$u = User::find(${Number(seed.userId)});
if ($u) { $u->tokens()->delete(); $u->delete(); }
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

async function run(appUrl, seed) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
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
        user: { id: seed.userId, display_name: "Jump E2E", email: EMAIL },
      },
    );

    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    // 1. Contact screen: the REAL activity endpoint feeds the timeline.
    const activityPromise = page.waitForResponse(
      (r) =>
        r.url().includes(`/api/v1/contacts/${seed.contactId}/activity`) &&
        r.status() === 200,
      { timeout: NAV_TIMEOUT_MS },
    );
    log("opening the contact screen against the real API…");
    await page.goto(`${appUrl}/contacts/${seed.contactId}`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });
    const activityRes = await activityPromise;
    const groups = (await activityRes.json())?.data?.groups ?? [];
    const restGroup = groups.find((g) => g.key === "restaurant_orders");
    if (!restGroup) {
      fail(
        `activity endpoint returned no restaurant_orders group: ${JSON.stringify(groups.map((g) => g.key))}`,
      );
    }
    const refs = restGroup.items?.[0]?.refs ?? {};
    if (refs.link_id !== seed.linkId || refs.order_id !== seed.orderId) {
      fail(
        `activity refs mismatch — expected link_id=${seed.linkId} order_id=${seed.orderId}, got ${JSON.stringify(refs)}`,
      );
    }
    log("real activity timeline carries link_id + order_id refs");

    // 2. Tap the activity row (Pressable labelled "Open Order #<id>").
    const row = page.getByLabel(`Open Order #${seed.orderId}`, { exact: true });
    await row.waitFor({ state: "visible" });
    await row.scrollIntoViewIfNeeded();
    const ordersPromise = page.waitForResponse(
      (r) =>
        r.url().includes(`/api/v1/restaurant/links/${seed.linkId}/orders`) &&
        r.status() === 200,
      { timeout: NAV_TIMEOUT_MS },
    );
    await row.click();

    // 3. Deep link lands on the orders screen with the highlight param.
    await page.waitForURL(
      (u) => u.pathname.includes(`/links/${seed.linkId}/restaurant-orders`),
      { timeout: NAV_TIMEOUT_MS },
    );
    const url = new URL(page.url());
    if (url.searchParams.get("highlight") !== String(seed.orderId)) {
      fail(`destination URL missing ?highlight=${seed.orderId}: ${page.url()}`);
    }
    log(`navigated to ${url.pathname}?${url.searchParams.toString()}`);
    await ordersPromise;

    // 4. The highlighted card exists (the "All" filter must be active — the
    //    order is completed, so the default "Open" filter would hide it), it
    //    carries the 2px primary outline, and it is scrolled into view.
    const cardMarker = page.getByText(new RegExp(`Diner Dan · #${seed.orderId}$`));
    await cardMarker.waitFor({ state: "visible" });
    log('highlighted (completed) order is visible — deep link landed on "All"');

    // Poll: the scrollTo is animated, so wait until the card settles fully
    // inside the viewport with the primary 2px border.
    const deadline = Date.now() + 30_000;
    let last = null;
    for (;;) {
      last = await cardMarker.evaluate((el, orderId) => {
        // Climb to the card: the nearest ancestor with a visible border.
        let node = el;
        while (node && node !== document.body) {
          const cs = window.getComputedStyle(node);
          if (parseFloat(cs.borderTopWidth) >= 1 && cs.borderTopStyle !== "none") {
            const rect = node.getBoundingClientRect();
            return {
              found: true,
              borderWidth: cs.borderTopWidth,
              borderColor: cs.borderTopColor,
              top: rect.top,
              bottom: rect.bottom,
              viewportH: window.innerHeight,
            };
          }
          node = node.parentElement;
        }
        return { found: false };
      }, seed.orderId);
      const inView =
        last.found &&
        last.top >= 0 &&
        last.top < last.viewportH &&
        last.bottom <= last.viewportH + 4;
      const highlighted =
        last.found &&
        parseFloat(last.borderWidth) >= 2 &&
        PRIMARY_RGB.has(last.borderColor);
      if (inView && highlighted) break;
      if (Date.now() > deadline) {
        fail(
          `highlighted card never settled highlighted+in-view: ${JSON.stringify(last)}`,
        );
      }
      await new Promise((r) => setTimeout(r, 500));
    }
    log(
      `order card is outlined in the primary color (${last.borderColor}, ${last.borderWidth}) and scrolled into view (top=${Math.round(last.top)} of ${last.viewportH}px)`,
    );

    // 5. Negative control: a filler card keeps the plain 1px border (the
    //    highlight really is per-record, not screen-wide).
    const fillerBorder = await page
      .getByText(new RegExp(`Filler 6 · #`))
      .evaluate((el) => {
        let node = el;
        while (node && node !== document.body) {
          const cs = window.getComputedStyle(node);
          if (parseFloat(cs.borderTopWidth) >= 1 && cs.borderTopStyle !== "none") {
            return { borderWidth: cs.borderTopWidth, borderColor: cs.borderTopColor };
          }
          node = node.parentElement;
        }
        return null;
      });
    if (!fillerBorder || parseFloat(fillerBorder.borderWidth) >= 2) {
      fail(
        `sibling card unexpectedly highlighted too: ${JSON.stringify(fillerBorder)}`,
      );
    }
    if (PRIMARY_RGB.has(fillerBorder.borderColor)) {
      fail(
        `sibling card border took the primary color: ${JSON.stringify(fillerBorder)}`,
      );
    }
    log("sibling order card keeps the plain border — highlight is per-record");

    await context.close();
    log(
      "PASS — contact activity row → restaurant-orders deep link → scroll-and-highlight verified end to end against the real API.",
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
    `seeded user=${seed.userId} contact=${seed.contactId} link=${seed.linkId} order=${seed.orderId} (+${FILLER_ORDERS} fillers)`,
  );

  const { acquireServer, stopExpo } = createExpoServerManager(log);
  // Always boot a throwaway server: EXPO_PUBLIC_API_BASE_URL must be baked
  // into the bundle at boot, so a pre-warmed APP_URL server can't be reused.
  const server = await acquireServer("contact-jump-real-api", null, {
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
