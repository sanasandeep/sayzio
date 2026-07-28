#!/usr/bin/env node
/**
 * E2E gate for the mobile image-block "add sticker" flow (Task #5956 —
 * app/links/[id]/blocks/[blockId].tsx + lib/api/files.ts), driving the
 * REAL app in a headless browser on a device-like viewport.
 *
 * What it asserts, in order:
 *   1. The image-block editor shows the "Photo stickers" section with the
 *      "Add sticker" (device upload) and "From my files" (vault) buttons.
 *   2. Tapping "From my files" GETs /me/files?type=image and renders the
 *      vault thumbnail grid.
 *   3. Tapping a thumbnail appends a sticker with the server defaults
 *      (pos top_right, size 48): the drag stage + sticker row (position
 *      label, Remove, Size/Rotate fields) appear and the picker closes.
 *   4. "Save block" PATCHes /links/{id}/blocks/{blockId} with
 *      settings._style._photo_stickers=[{file_id,url,pos,size,rotate,dx,dy}].
 *   5. A fresh navigation re-hydrates the sticker row from the saved
 *      _style._photo_stickers (the reload path through
 *      normalizePhotoStickers).
 *
 * Every /api/** call is intercepted against an in-memory link/block/vault
 * fixture so nothing reaches a real backend (RN-web quirk: Alert.alert is
 * a no-op on web — the flow under test never depends on it). Like the
 * sibling harnesses it boots its own throwaway Expo web server unless
 * APP_URL points at a running one, and SKIPS on transient env errors.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:image-sticker-add-e2e
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[image-sticker-add-e2e]", ...args);
}
function fail(msg) {
  console.error("[image-sticker-add-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[image-sticker-add-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-image-sticker-token";
const MOCK_USER = {
  id: 5968,
  display_name: "Sticker Tester",
  email: "image-sticker@example.com",
};
const LINK_ID = 5968;
const BLOCK_ID = 77;
// A 1x1 transparent PNG served from the mocked API origin keeps <Image>
// loads deterministic (no external network).
const PNG_1PX = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==",
  "base64",
);
const EXPLICIT_APP_URL = process.env.APP_URL || null;

const VAULT_FILE = {
  id: 4242,
  type: "image",
  original_name: "flame.png",
  mime_type: "image/png",
  url: "/e2e-assets/flame.png",
  url_path: "/e2e-assets/flame.png",
  size_human: "1 KB",
  created_at: "2026-07-01T00:00:00Z",
};

// The server-side records the mock reports. PATCH replaces the block's
// settings wholesale — exactly like Api BiolinkBlockController::update —
// so the reload leg proves the client re-merged _style itself.
const link = {
  id: LINK_ID,
  type: "biolink",
  alias: "sticker-tester",
  title: "Sticker Tester",
  short_url: "https://1in.me/sticker-tester",
  long_url: null,
  visibility: "public",
  is_active: true,
  design_locked: false,
  settings: { biolink: {} },
};
let block = {
  id: BLOCK_ID,
  link_id: LINK_ID,
  type: "image",
  sort_order: 0,
  parent_id: null,
  is_active: true,
  settings: { url: "/e2e-assets/photo.png" },
  start_date: null,
  end_date: null,
  max_clicks: null,
  click_count: 0,
  created_at: "2026-07-01T00:00:00Z",
  updated_at: "2026-07-01T00:00:00Z",
};

let filesListCalls = 0;
let filesListTypeParam = null;
const patchBodies = [];

async function mockApi(context) {
  // Image bytes for the mocked photo + vault sticker.
  await context.route("**/e2e-assets/**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "image/png",
      body: PNG_1PX,
    });
  });
  await context.route("**/api/**", async (route) => {
    const req = route.request();
    const method = req.method();
    const path = new URL(req.url()).pathname;

    let body = { data: [] };

    if (/\/api\/v1\/auth\/me$/.test(path)) {
      body = { data: { user: MOCK_USER } };
    } else if (/\/api\/v1\/onboarding$/.test(path)) {
      body = {
        data: {
          onboarded_at: "2026-01-01T00:00:00Z",
          email_verified: true,
          has_links: true,
          has_biolink: true,
          whatsapp_pending: false,
          privacy_pending: false,
        },
      };
    } else if (/\/api\/v1\/me\/files$/.test(path)) {
      filesListCalls += 1;
      filesListTypeParam = new URL(req.url()).searchParams.get("type");
      body = {
        data: {
          files: [VAULT_FILE],
          pagination: { current_page: 1, last_page: 1, total: 1 },
        },
      };
    } else if (
      new RegExp(`/api/v1/links/${LINK_ID}/blocks/${BLOCK_ID}$`).test(path)
    ) {
      if (method === "PATCH") {
        let payload = {};
        try {
          payload = JSON.parse(req.postData() || "{}");
        } catch {}
        patchBodies.push(payload);
        if (payload.settings) {
          block = { ...block, settings: payload.settings };
        }
      }
      body = { data: { block } };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}/blocks$`).test(path)) {
      body = { data: { items: [block] } };
    } else if (new RegExp(`/api/v1/links/${LINK_ID}$`).test(path)) {
      body = { data: { link } };
    } else if (/\/api\/v1\/block-catalog$/.test(path)) {
      body = { data: { items: [] } };
    }

    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(body),
    });
  });
}

async function seedSession(context) {
  await context.addInitScript(
    ({ token, user }) => {
      try {
        window.localStorage.setItem("1inme.onboarding.complete", "1");
        window.localStorage.setItem("1inme.auth.token", token);
        window.localStorage.setItem("1inme.auth.user", JSON.stringify(user));
      } catch {}
    },
    { token: MOCK_TOKEN, user: MOCK_USER },
  );
}

async function waitFor(predicate, what) {
  const deadline = Date.now() + STEP_TIMEOUT_MS;
  while (!predicate() && Date.now() < deadline) {
    await new Promise((r) => setTimeout(r, 100));
  }
  if (!predicate()) fail(`timed out waiting for ${what}`);
}

async function run(appUrl) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await seedSession(context);
    await mockApi(context);
    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    const editorUrl = `${appUrl}/links/${LINK_ID}/blocks/${BLOCK_ID}`;
    log("opening image block editor…");
    await page.goto(editorUrl, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });

    // 1. Photo stickers section + both add buttons.
    await page.getByText("Photo stickers", { exact: true }).waitFor({ state: "visible" });
    await page
      .getByRole("button", { name: "Add sticker from device" })
      .waitFor({ state: "visible" });
    const fromFiles = page.getByRole("button", {
      name: "Pick sticker from my files",
    });
    await fromFiles.waitFor({ state: "visible" });
    // No sticker yet → no row, no drag stage.
    if (await page.getByText("Top right", { exact: false }).count()) {
      fail("a sticker row rendered before any sticker was added");
    }
    log("Photo stickers section + Add sticker / From my files visible");

    // 2. Vault picker grid loads from GET /me/files.
    await fromFiles.click();
    const thumb = page.getByRole("button", {
      name: `Use ${VAULT_FILE.original_name} as sticker`,
    });
    await thumb.waitFor({ state: "visible" });
    if (filesListCalls === 0) fail("picker opened without GETting /me/files");
    if (filesListTypeParam !== "image") {
      fail(`/me/files was not scoped to images (type=${filesListTypeParam})`);
    }
    log("vault picker grid rendered the image file");

    // 3. Picking a thumbnail appends the sticker with server defaults.
    await thumb.click();
    await page.getByText(/^Top right/).waitFor({ state: "visible" });
    await page.getByText("Remove", { exact: true }).waitFor({ state: "visible" });
    await page.getByText("Size (24–160)").waitFor({ state: "visible" });
    await page
      .getByLabel("Drag to reposition sticker")
      .waitFor({ state: "visible" });
    if (await thumb.isVisible().catch(() => false)) {
      fail("vault picker stayed open after picking a sticker");
    }
    log("sticker row + drag stage appeared (pos top_right, picker closed)");

    // 4. Save block PATCHes _style._photo_stickers.
    await page.getByText("Save block", { exact: true }).click();
    await waitFor(() => patchBodies.length > 0, "the block PATCH");
    const sentStyle = patchBodies[patchBodies.length - 1]?.settings?._style;
    const stickers = sentStyle?._photo_stickers;
    if (!Array.isArray(stickers) || stickers.length !== 1) {
      fail(
        `PATCH did not carry exactly one _style._photo_stickers entry: ${JSON.stringify(sentStyle)}`,
      );
    }
    const s = stickers[0];
    if (
      s.file_id !== VAULT_FILE.id ||
      s.url !== VAULT_FILE.url_path ||
      s.pos !== "top_right" ||
      s.size !== 48 ||
      s.rotate !== 0 ||
      s.dx !== 0 ||
      s.dy !== 0
    ) {
      fail(`saved sticker has the wrong shape: ${JSON.stringify(s)}`);
    }
    log("Save block PATCHed _style._photo_stickers with the server defaults");

    // 5. A fresh load re-hydrates the sticker row from the saved settings.
    await page.goto(editorUrl, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });
    await page.getByText("Photo stickers", { exact: true }).waitFor({ state: "visible" });
    await page.getByText(/^Top right/).waitFor({ state: "visible" });
    await page
      .getByLabel("Drag to reposition sticker")
      .waitFor({ state: "visible" });
    log("reload re-hydrated the sticker from _style._photo_stickers");

    await context.close();
    log(
      "PASS — add-sticker flow: files picker → sticker row → save → reload.",
    );
  } finally {
    await browser.close();
  }
}

async function main() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("image-sticker-add", EXPLICIT_APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
    return;
  }
  const appUrl = server.appUrl.replace(/\/$/, "");
  try {
    await run(appUrl);
  } catch (err) {
    if (isTransientEnvError(err)) {
      skip(`transient environment error: ${err.message}`);
    }
    throw err;
  } finally {
    if (!server.explicit && server.child) stopExpo(server.child);
  }
  // Explicit exit: the detached throwaway Expo child keeps the event loop
  // alive otherwise (siblings do the same).
  process.exit(0);
}

runHarness(main, { log, onError: (err) => fail(err?.stack || String(err)) });
