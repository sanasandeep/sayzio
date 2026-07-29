#!/usr/bin/env node
/**
 * REAL-API e2e for the mobile stock-image pickers (Task #6021 — verifying
 * the Task #6016 wiring end-to-end against the actual Laravel backend, not
 * an in-memory mock like the sibling harnesses).
 *
 * The full loop it proves, with every API call hitting a real Laravel
 * server backed by the real database and the real S3 platform-asset
 * catalog:
 *   1. GET /api/v1/platform-assets/grid-images returns the curated photo
 *      catalog and the "Stock images" picker renders its tiles.
 *   2. Picking a tile sets the image block's URL field.
 *   3. GET /api/v1/platform-assets/hand-drawn feeds the "Stock stickers"
 *      picker; picking a tile imports the asset into the vault via a REAL
 *      POST /api/v1/me/files/import-platform-asset (201, owned UserFile —
 *      server-side S3 read, Task #6028) and appends a sticker row.
 *   4. "Save block" PATCHes /api/v1/links/{id}/blocks/{blockId} — the
 *      server sanitizer accepts the owned sticker file_id (200).
 *   5. The GALLERY block (image_grid) has its own stock picker
 *      (testIDPrefix "gallery-stock-gallery") that appends picked URLs to
 *      the gallery rows; picking a tile + "Save block" PATCHes the block
 *      with settings.images carrying the picked URL (Task #6027).
 *   6. The PUBLIC page (GET /{alias}, no auth) renders the picked stock
 *      image URL, the imported sticker's vault url_path, AND the gallery
 *      block's picked stock URL.
 *   7. GET /api/v1/me/files?type=image lists the imported file as owned.
 *
 * Infrastructure:
 *   - Boots its own Laravel dev server (php -S + the vendored framework
 *     router, cwd=public/ — see memory notes: artisan serve strips env).
 *   - Seeds the demo user + a per-run-unique biolink/image-block fixture
 *     and mints a Sanctum bearer token via `artisan tinker --execute`
 *     (prunes stale fixtures by alias prefix — shared RDS across envs).
 *   - Boots a throwaway Expo web server with EXPO_PUBLIC_API_BASE_URL
 *     baked to the Laravel server so apiFetch talks to it directly
 *     (Laravel CORS is wildcard on api/*).
 *   - NO network interception at all (Task #6028): the sticker import is
 *     server-side by asset key, so the browser never fetches the CDN blob
 *     and no CORS shim is needed — this run proves the real mobile-web
 *     flow works against the CDN exactly as shipped.
 *
 * SKIPs (exit 0) when the environment can't support it: Expo won't boot,
 * Laravel won't boot, or the S3 catalog is unreachable/empty.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:stock-image-real-api-e2e
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
  console.log("[stock-image-real-api-e2e]", ...args);
}
function fail(msg) {
  console.error("[stock-image-real-api-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[stock-image-real-api-e2e] SKIP:", msg);
  process.exit(0);
}

const MOBILE_ROOT = path.resolve(fileURLToPath(import.meta.url), "..", "..");
const LARAVEL_ROOT = path.resolve(MOBILE_ROOT, "..", "1inme");
const VIEWPORT = { width: 400, height: 780 };
// Cross-region RDS makes every request slow; budget generously.
const STEP_TIMEOUT_MS = 90_000;
const NAV_TIMEOUT_MS = 120_000;

const DEMO_EMAIL = "sayzioapp@gmail.com";
const ALIAS_PREFIX = "e2e-stkpick-";
const ALIAS = ALIAS_PREFIX + Date.now().toString(36) + process.pid.toString(36);

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
  const child = spawn(
    "php",
    ["-S", `127.0.0.1:${port}`, routerPath],
    {
      // The framework router resolves index.php from CWD — must be public/.
      cwd: path.join(LARAVEL_ROOT, "public"),
      detached: true,
      stdio: ["ignore", "ignore", "ignore"],
      env: { ...process.env, PHP_CLI_SERVER_WORKERS: "8" },
    },
  );
  child.unref();
  laravelChildren.add(child);

  // Warm on the fast /up probe until it answers.
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
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Models\\UserFile;
use App\\Modules\\User\\Services\\WorkspaceContext;
use Illuminate\\Support\\Facades\\Hash;

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
$ws = app(WorkspaceContext::class)->resolve($u);

// Prune stale fixtures from previous runs (shared RDS across task envs).
$cut = now()->subHours(2);
$stale = Link::withoutGlobalScope('workspace')
  ->where('alias', 'like', '${ALIAS_PREFIX}%')
  ->where('user_id', $u->id)->where('created_at', '<', $cut)->get();
foreach ($stale as $sl) { BiolinkBlock::where('link_id', $sl->id)->delete(); $sl->delete(); }
UserFile::where('user_id', $u->id)
  ->where('original_name', 'like', 'e2e-stkpick-%')
  ->where('created_at', '<', $cut)->delete();

$bio = Link::create([
  'user_id' => $u->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
  'alias' => '${ALIAS}', 'title' => 'E2E Stock Pick', 'is_active' => true,
]);
$blk = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'image', 'sort_order' => 0, 'is_active' => true,
  'settings' => ['url' => 'https://placehold.co/400x300.png', 'alt' => 'Hero'],
]);
$gal = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'image_grid', 'sort_order' => 1, 'is_active' => true,
  'settings' => ['columns' => 3, 'gap' => 2, 'images' => []],
]);
$sld = BiolinkBlock::create([
  'link_id' => $bio->id, 'type' => 'image_slider', 'sort_order' => 2, 'is_active' => true,
  'settings' => ['images' => []],
]);
$token = $u->createToken('e2e-stock-pick')->plainTextToken;
echo 'SEED_JSON:' . json_encode([
  'userId' => $u->id, 'linkId' => $bio->id, 'blockId' => $blk->id,
  'galleryBlockId' => $gal->id, 'sliderBlockId' => $sld->id, 'token' => $token,
]) . "\\n";
`;
  const out = runTinker(php);
  const m = out.match(/SEED_JSON:(\{.*\})/);
  if (!m) fail(`seed produced no SEED_JSON marker; output:\n${out}`);
  return JSON.parse(m[1]);
}

function cleanupFixture(linkId, importedFileIds) {
  const ids = importedFileIds.filter((n) => Number.isInteger(n)).join(",");
  const php = `
use App\\Modules\\User\\Models\\Link;
use App\\Modules\\User\\Models\\BiolinkBlock;
use App\\Modules\\User\\Models\\UserFile;
BiolinkBlock::where('link_id', ${Number(linkId)})->delete();
Link::withoutGlobalScope('workspace')->where('id', ${Number(linkId)})->delete();
${ids ? `UserFile::whereIn('id', [${ids}])->delete();` : ""}
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

async function run(appUrl, apiBase, seed) {
  const importedFileIds = [];
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
        user: { id: seed.userId, display_name: "Demo User", email: DEMO_EMAIL },
      },
    );

    // Task #6028: no CDN CORS shim anymore — the sticker import happens
    // server-side (POST /me/files/import-platform-asset with the asset
    // key), so the browser never cross-origin-fetches the CDN blob. This
    // run therefore exercises the REAL mobile-web flow unmodified.

    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    const editorUrl = `${appUrl}/links/${seed.linkId}/blocks/${seed.blockId}`;
    log("opening the image block editor against the real API…");
    const gridAssetsPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/platform-assets/grid-images") &&
        r.status() === 200,
      { timeout: NAV_TIMEOUT_MS },
    );
    await page.goto(editorUrl, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });

    // 1. Stock images picker: open and wait for the REAL catalog.
    const stockToggle = page.getByTestId("image-stock-gallery-toggle");
    await stockToggle.waitFor({ state: "visible" });
    await stockToggle.click();
    const gridAssetsRes = await gridAssetsPromise;
    const gridAssets = (await gridAssetsRes.json())?.data?.assets ?? [];
    if (gridAssets.length === 0) {
      skip("platform-assets/grid-images returned an empty catalog (S3 unavailable)");
    }
    const pickedPhoto = gridAssets[0];
    log(
      `real catalog: ${gridAssets.length} grid-images; picking "${pickedPhoto.label}"`,
    );
    await page.getByTestId("image-stock-gallery-grid").waitFor({ state: "visible" });

    // 2. Pick the first tile → the image URL field takes the asset URL.
    // Tiles are plain Pressables (accessibilityLabel, no explicit role) —
    // select by label, not role.
    await page
      .getByTestId("image-stock-gallery-grid")
      .getByLabel(pickedPhoto.label, { exact: true })
      .first()
      .click();
    await page
      .locator(`input[value="${pickedPhoto.url}"]`)
      .first()
      .waitFor({ state: "visible" });
    log("picking a stock photo set the image URL field");

    // 3. Stock stickers picker → REAL vault import.
    const stickerToggle = page.getByTestId("sticker-stock-gallery-toggle");
    await stickerToggle.scrollIntoViewIfNeeded();
    const handDrawnPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/platform-assets/hand-drawn") &&
        r.status() === 200,
    );
    await stickerToggle.click();
    const handDrawnRes = await handDrawnPromise;
    const handDrawn = (await handDrawnRes.json())?.data?.assets ?? [];
    if (handDrawn.length === 0) {
      skip("platform-assets/hand-drawn returned an empty catalog (S3 unavailable)");
    }
    const pickedSticker = handDrawn[0];
    log(
      `real catalog: ${handDrawn.length} hand-drawn; picking "${pickedSticker.label}"`,
    );

    const uploadPromise = page.waitForResponse(
      (r) =>
        r.url().includes("/api/v1/me/files/import-platform-asset") &&
        r.request().method() === "POST",
    );
    await page
      .getByTestId("sticker-stock-gallery-grid")
      .getByLabel(pickedSticker.label, { exact: true })
      .first()
      .click();
    const uploadRes = await uploadPromise;
    if (uploadRes.status() !== 201) {
      fail(
        `vault import POST /me/files/import-platform-asset returned ${uploadRes.status()}: ${await uploadRes.text().catch(() => "")}`,
      );
    }
    const importedFile = (await uploadRes.json())?.data?.file;
    if (!importedFile?.id || importedFile.type !== "image") {
      fail(`vault import returned an unexpected file: ${JSON.stringify(importedFile)}`);
    }
    importedFileIds.push(importedFile.id);
    log(
      `stock sticker imported as owned vault file id=${importedFile.id} (${importedFile.original_name})`,
    );

    // Sticker row appears with the server defaults.
    await page.getByText(/^Top right/).waitFor({ state: "visible" });
    await page
      .getByLabel("Drag to reposition sticker")
      .waitFor({ state: "visible" });
    log("sticker row + drag stage appeared");

    // 4. Save block → REAL PATCH accepted by the server sanitizer.
    const patchPromise = page.waitForResponse(
      (r) =>
        r.url().includes(`/api/v1/links/${seed.linkId}/blocks/${seed.blockId}`) &&
        r.request().method() === "PATCH",
    );
    await page.getByText("Save block", { exact: true }).click();
    const patchRes = await patchPromise;
    if (patchRes.status() !== 200) {
      fail(
        `block PATCH returned ${patchRes.status()}: ${await patchRes.text().catch(() => "")}`,
      );
    }
    const patchReq = JSON.parse(patchRes.request().postData() || "{}");
    const sentStickers = patchReq?.settings?._style?._photo_stickers;
    if (patchReq?.settings?.url !== pickedPhoto.url) {
      fail(
        `PATCH settings.url was not the picked stock URL: ${patchReq?.settings?.url}`,
      );
    }
    if (
      !Array.isArray(sentStickers) ||
      sentStickers.length !== 1 ||
      sentStickers[0].file_id !== importedFile.id
    ) {
      fail(
        `PATCH did not carry the imported sticker file_id: ${JSON.stringify(sentStickers)}`,
      );
    }
    const savedBlock = (await patchRes.json())?.data?.block;
    const savedStickers = savedBlock?.settings?._style?._photo_stickers;
    if (
      !Array.isArray(savedStickers) ||
      savedStickers.length !== 1 ||
      savedStickers[0].file_id !== importedFile.id
    ) {
      fail(
        `server-sanitized block did not keep the sticker: ${JSON.stringify(savedBlock?.settings?._style)}`,
      );
    }
    if (savedBlock?.settings?.url !== pickedPhoto.url) {
      fail(`server-saved block URL mismatch: ${savedBlock?.settings?.url}`);
    }
    log("Save block: real PATCH accepted (stock URL + owned sticker persisted)");

    // Shared leg for the gallery-family editors (image_grid + the two
    // slider variants): they all use the same "gallery-stock-gallery"
    // picker; picking appends a row and Save persists settings.images.
    async function pickStockIntoGalleryFamilyBlock(blockId, label, avoidUrls) {
      const editorUrl2 = `${appUrl}/links/${seed.linkId}/blocks/${blockId}`;
      log(`opening the ${label} block editor against the real API…`);
      const assetsPromise = page.waitForResponse(
        (r) =>
          r.url().includes("/api/v1/platform-assets/grid-images") &&
          r.status() === 200,
        { timeout: NAV_TIMEOUT_MS },
      );
      await page.goto(editorUrl2, {
        waitUntil: "domcontentloaded",
        timeout: NAV_TIMEOUT_MS,
      });
      const toggle = page.getByTestId("gallery-stock-gallery-toggle");
      await toggle.waitFor({ state: "visible" });
      await toggle.scrollIntoViewIfNeeded();
      await toggle.click();
      const assetsRes = await assetsPromise;
      const assets = (await assetsRes.json())?.data?.assets ?? [];
      if (assets.length === 0) {
        skip("platform-assets/grid-images returned an empty catalog (S3 unavailable)");
      }
      // Prefer an asset DIFFERENT from earlier picks so the public-page
      // assertion can't pass on another block's URL alone.
      const pick =
        assets.find((a) => !avoidUrls.includes(a.url)) ?? assets[0];
      log(`${label} picker: picking "${pick.label}"`);
      await page
        .getByTestId("gallery-stock-gallery-grid")
        .waitFor({ state: "visible" });
      await page
        .getByTestId("gallery-stock-gallery-grid")
        .getByLabel(pick.label, { exact: true })
        .first()
        .click();
      // The pick fills/appends a row whose URL field takes the asset URL.
      await page
        .locator(`input[value="${pick.url}"]`)
        .first()
        .waitFor({ state: "visible" });
      log(`picking a stock photo appended a ${label} row with the asset URL`);

      const patchPromise2 = page.waitForResponse(
        (r) =>
          r.url().includes(`/api/v1/links/${seed.linkId}/blocks/${blockId}`) &&
          r.request().method() === "PATCH",
      );
      await page.getByText("Save block", { exact: true }).click();
      const patchRes2 = await patchPromise2;
      if (patchRes2.status() !== 200) {
        fail(
          `${label} block PATCH returned ${patchRes2.status()}: ${await patchRes2.text().catch(() => "")}`,
        );
      }
      const patchReq2 = JSON.parse(patchRes2.request().postData() || "{}");
      const sentImages = patchReq2?.settings?.images;
      if (
        !Array.isArray(sentImages) ||
        !sentImages.some((i) => (typeof i === "string" ? i : i?.url) === pick.url)
      ) {
        fail(
          `${label} PATCH settings.images did not carry the picked URL: ${JSON.stringify(sentImages)}`,
        );
      }
      const savedBlock2 = (await patchRes2.json())?.data?.block;
      const savedImages = savedBlock2?.settings?.images;
      if (
        !Array.isArray(savedImages) ||
        !savedImages.some(
          (i) => (typeof i === "string" ? i : i?.url) === pick.url,
        )
      ) {
        fail(
          `server-saved ${label} block did not keep the picked image: ${JSON.stringify(savedImages)}`,
        );
      }
      log(
        `${label} Save block: real PATCH accepted (picked stock URL persisted in settings.images)`,
      );
      return pick;
    }

    // 5. Gallery block (image_grid): its own stock picker.
    const galleryPick = await pickStockIntoGalleryFamilyBlock(
      seed.galleryBlockId,
      "gallery",
      [pickedPhoto.url],
    );

    // 5b. Slider block (image_slider): same mobile picker, but a DIFFERENT
    // public Blade renderer branch (Alpine x-data slider) — prove it too
    // (Task #6029).
    const sliderPick = await pickStockIntoGalleryFamilyBlock(
      seed.sliderBlockId,
      "slider",
      [pickedPhoto.url, galleryPick.url],
    );

    await context.close();

    // 5. Public render (no auth): the LIVE page shows both.
    log("fetching the public page…");
    let publicHtml = null;
    const deadline = Date.now() + 180_000;
    while (Date.now() < deadline) {
      try {
        const res = await fetch(`${apiBase}/${ALIAS}`, {
          signal: AbortSignal.timeout(120_000),
        });
        const text = await res.text();
        if (res.ok && text.length > 5000) {
          publicHtml = text;
          break;
        }
        log(`public page not ready yet (status ${res.status}, ${text.length} bytes)`);
      } catch (e) {
        log(`public page fetch retry: ${e?.message ?? e}`);
      }
      await new Promise((r) => setTimeout(r, 3000));
    }
    if (!publicHtml) fail("public page never rendered");
    if (!publicHtml.includes(pickedPhoto.url)) {
      fail(`public page does not render the picked stock image URL ${pickedPhoto.url}`);
    }
    if (!importedFile.url_path || !publicHtml.includes(importedFile.url_path)) {
      fail(
        `public page does not render the imported sticker url_path ${importedFile.url_path}`,
      );
    }
    if (!publicHtml.includes(galleryPick.url)) {
      fail(
        `public page does not render the gallery block's picked stock URL ${galleryPick.url}`,
      );
    }
    // The slider renderer embeds its images as json_encode'd Alpine x-data,
    // so the URL appears JSON-escaped (https:\/\/...) in the HTML — accept
    // either form.
    const sliderUrlEscaped = sliderPick.url.replaceAll("/", "\\/");
    if (
      !publicHtml.includes(sliderPick.url) &&
      !publicHtml.includes(sliderUrlEscaped)
    ) {
      fail(
        `public page does not render the slider block's picked stock URL ${sliderPick.url}`,
      );
    }
    log(
      "public page renders the stock image, the imported sticker, the gallery pick AND the slider pick",
    );

    // 6. Vault ownership: the imported file is listed under the account.
    const filesRes = await fetch(`${apiBase}/api/v1/me/files?type=image&per_page=60`, {
      headers: { Authorization: `Bearer ${seed.token}`, Accept: "application/json" },
      signal: AbortSignal.timeout(60_000),
    });
    const filesBody = await filesRes.json().catch(() => null);
    const owned = (filesBody?.data?.files ?? []).some((f) => f.id === importedFile.id);
    if (!filesRes.ok || !owned) {
      fail(
        `imported sticker file id=${importedFile.id} is not listed in the owner's vault (status ${filesRes.status})`,
      );
    }
    log("imported sticker is listed as an owned vault file");

    log(
      "PASS — real-API stock pick: catalog → URL field → vault import → block save → public render.",
    );
  } finally {
    await browser.close();
    cleanupFixture(seed.linkId, importedFileIds);
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
  log(`seeded link=${seed.linkId} block=${seed.blockId} alias=${ALIAS}`);

  const { acquireServer, stopExpo } = createExpoServerManager(log);
  // Always boot a throwaway server: EXPO_PUBLIC_API_BASE_URL must be baked
  // into the bundle at boot, so a pre-warmed APP_URL server can't be reused.
  const server = await acquireServer("stock-image-real-api", null, {
    EXPO_PUBLIC_API_BASE_URL: laravel.base,
  });
  if (!server) {
    cleanupFixture(seed.linkId, []);
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
