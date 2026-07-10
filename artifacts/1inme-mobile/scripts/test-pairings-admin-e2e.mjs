#!/usr/bin/env node
/**
 * End-to-end regression check for the mobile admin "Perfect Pairings" screen
 * (app/admin/link-type-pairings.tsx), driving the REAL app in a headless
 * browser.
 *
 * The server-side behaviour is already pinned by
 * tests/Feature/MobileLinkTypePairingsApiTest.php, and typecheck covers the
 * client wiring — but neither proves the screen actually renders and behaves
 * on a device. This harness renders the real screen and clicks through the
 * whole admin flow:
 *
 *   1. Boot a throwaway, self-contained Expo web dev server (or reuse one via
 *      APP_URL) with every /api/** call intercepted — no real backend, no RDS.
 *   2. Walk onboarding to the login screen and sign in via "Demo as admin"
 *      (POST /auth/demo mocked).
 *   3. Open the Admin dashboard (/admin) with /admin/context mocked to grant
 *      settings.manage, and tap the "Perfect Pairings" row.
 *   4. Assert the screen renders every mocked section + card as an
 *      accessible checkbox (aria-checked), all checked by default.
 *   5. Uncheck ONE card in the first section, and ALL cards in the second
 *      section — assert the "Section hidden" badge appears on the emptied
 *      section (and only there).
 *   6. Tap "Save settings" — assert the PUT hit /admin/link-type-pairings
 *      with exactly the expected {enabled} payload (the emptied section key
 *      OMITTED, per the API contract), and that the mocked stateful server
 *      records it.
 *   7. Reload the screen (fresh GET refetch) — assert the unchecked state
 *      PERSISTED: the single card stays unchecked, the emptied section still
 *      shows "Section hidden".
 *   8. Tap "Restore defaults", accept the confirm dialog — assert the POST
 *      hit /admin/link-type-pairings/restore-defaults, every checkbox is
 *      re-checked and the "Section hidden" badge is gone.
 *
 * The mocked pairings backend is STATEFUL (a mutable `serverState`), so the
 * persistence check in step 7 exercises the real save→refetch round trip
 * rather than a canned response.
 *
 * Best-effort contract (same as test-auth-flow-e2e.mjs): when a throwaway
 * Expo server can't be booted or can't stay serveable, the test SKIPs
 * (exit 0) rather than failing CI. Real assertion failures always fail.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:pairings-admin-e2e
 *
 * Environment:
 *   APP_URL   point at an already-running Expo web server instead of booting
 *             a throwaway one (handy for local debugging).
 */

import { chromium } from "playwright";

import {
  APP_URL,
  NAV_TIMEOUT_MS,
  STEP_TIMEOUT_MS,
  reachLoginScreen,
} from "./check-icon-fonts.mjs";
import { createExpoServerManager } from "./expo-web-server.mjs";

const MOCK_TOKEN = "sanctum-token-pairings-e2e";

function log(...args) {
  console.log("[pairings-admin-e2e]", ...args);
}

function fail(msg) {
  console.error("[pairings-admin-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[pairings-admin-e2e] SKIP:", msg);
  process.exit(0);
}

// ---------------------------------------------------------------------------
// The mocked pairings catalog. Two sections keep the flow readable: we
// uncheck one card in "biolink" and empty out "resume" entirely.
// ---------------------------------------------------------------------------
const CATALOG = [
  {
    key: "biolink",
    label: "Biolink pages",
    items: [
      { name: "Resume / Portfolio", type: "resume", icon: "file-text", benefit: "Show your work next to your links" },
      { name: "Reviews page", type: "reviews", icon: "star", benefit: "Build trust with social proof" },
      { name: "Event page", type: "event", icon: "calendar", benefit: "Promote your next event" },
    ],
  },
  {
    key: "resume",
    label: "Resume pages",
    items: [
      { name: "Biolink", type: "biolink", icon: "link", benefit: "One page for everything you share" },
      { name: "Digital card", type: "vcard", icon: "user", benefit: "Let people save your contact" },
    ],
  },
];

// Card we uncheck individually in the first section.
const UNCHECK_ONE = { section: "biolink", type: "resume", name: "Resume / Portfolio" };
// Section we empty out completely.
const EMPTY_SECTION = { key: "resume", label: "Resume pages" };

// Mutable server truth: sectionKey -> Set of enabled card types. Starts with
// everything enabled (the defaults).
function defaultState() {
  const s = {};
  for (const section of CATALOG) {
    s[section.key] = new Set(section.items.map((i) => i.type));
  }
  return s;
}

function statusEnvelope(state) {
  return {
    data: {
      sections: CATALOG.map((section) => ({
        key: section.key,
        label: section.label,
        items: section.items.map((item) => ({
          ...item,
          enabled: state[section.key]?.has(item.type) ?? false,
        })),
      })),
    },
  };
}

async function main() {
  const manager = createExpoServerManager(log);
  const server = await manager.acquireServer("pairings", process.env.APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server (best-effort)");
    return;
  }
  const appBaseUrl = server.appUrl ?? APP_URL;

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 400, height: 720 },
  });
  const page = await context.newPage();
  page.on("pageerror", (e) => log("pageerror:", e.message));
  page.setDefaultTimeout(STEP_TIMEOUT_MS);
  page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

  // Catch-all: fulfill every unmatched /api/** call with a benign {data: []}
  // so the signed-in tabs settle without React Query retry churn (see
  // test-auth-flow-e2e.mjs for why fulfil beats abort). Specific handlers are
  // registered AFTER and therefore win (most-recently-added-first).
  await page.route("**/api/**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ data: [] }),
    }),
  );

  await page.route("**/api/v1/auth/demo", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        data: {
          token: MOCK_TOKEN,
          user: { id: 12, display_name: "Demo Admin", email: "demo-admin@example.com" },
        },
      }),
    }),
  );

  // Admin context: grant back-office access + settings.manage so the
  // "Perfect Pairings" row is enabled on the Admin dashboard.
  await page.route("**/api/v1/admin/context", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        data: {
          has_admin_access: true,
          admin: {
            name: "Demo Admin",
            role: { name: "Super admin", is_super_admin: true },
          },
          can: { manage_settings: true },
        },
      }),
    }),
  );

  // Stateful pairings backend.
  let serverState = defaultState();
  const hits = { get: 0, put: null, restore: 0 };

  await page.route("**/api/v1/admin/link-type-pairings", async (route) => {
    const req = route.request();
    if (req.method() === "GET") {
      hits.get += 1;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(statusEnvelope(serverState)),
      });
      return;
    }
    if (req.method() === "PUT") {
      let body;
      try {
        body = JSON.parse(req.postData() ?? "null");
      } catch {
        body = null;
      }
      hits.put = { url: req.url(), body };
      // Apply exactly like the server: anything not listed is disabled;
      // omitted page keys disable everything on that page.
      const next = {};
      for (const section of CATALOG) {
        const allowed = new Set(section.items.map((i) => i.type));
        next[section.key] = new Set(
          (body?.enabled?.[section.key] ?? []).filter((t) => allowed.has(t)),
        );
      }
      serverState = next;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify(statusEnvelope(serverState)),
      });
      return;
    }
    await route.fallback();
  });

  await page.route("**/api/v1/admin/link-type-pairings/restore-defaults", async (route) => {
    hits.restore += 1;
    serverState = defaultState();
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(statusEnvelope(serverState)),
    });
  });

  // A checkbox row locator by accessible name (RN-web renders the Pressable
  // with role=checkbox + aria-checked, named by its inner text).
  const checkbox = (name) => page.getByRole("checkbox", { name: new RegExp(name) }).first();

  // Poll until the checkbox reaches the expected aria-checked value: state
  // flips are async re-renders (React Query onSuccess → setChecks), so a
  // one-shot read right after a click/response races the re-render.
  async function assertChecked(name, expected, where) {
    const box = checkbox(name);
    await box.waitFor({ timeout: STEP_TIMEOUT_MS });
    const deadline = Date.now() + STEP_TIMEOUT_MS;
    let state;
    while (Date.now() < deadline) {
      state = await box.getAttribute("aria-checked");
      if (String(expected) === state) return;
      await new Promise((r) => setTimeout(r, 200));
    }
    fail(`${where}: expected "${name}" aria-checked=${expected}, got ${state}`);
  }

  async function waitForPairingsScreen() {
    await page.getByText("Cross-promo cards", { exact: true }).waitFor({
      timeout: STEP_TIMEOUT_MS,
    });
  }

  // The "Section hidden" badge count changes on an async re-render (a checkbox
  // toggle → React Query onSuccess → setChecks → recompute), so a one-shot
  // count() right after a toggle/response races the re-render and can observe a
  // stale count. Poll until the count settles to the expected value, then let
  // the caller keep its own tailored fail() message.
  async function badgeCountSettles(expected) {
    const badges = page.getByText("Section hidden", { exact: true });
    const deadline = Date.now() + STEP_TIMEOUT_MS;
    let count = await badges.count();
    while (count !== expected && Date.now() < deadline) {
      await new Promise((r) => setTimeout(r, 200));
      count = await badges.count();
    }
    return count;
  }

  try {
    // ---- Boot + sign in as admin.
    const TRANSIENT_NAV =
      /ERR_CONNECTION_RESET|ERR_EMPTY_RESPONSE|ERR_CONNECTION_REFUSED|ERR_CONNECTION_CLOSED|ERR_NETWORK_CHANGED|Timeout \d+ms exceeded/;
    const NAV_ATTEMPTS = 4;
    let navErr;
    for (let attempt = 1; attempt <= NAV_ATTEMPTS; attempt++) {
      try {
        await page.goto(appBaseUrl, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT_MS });
        await page.waitForFunction(
          () => document.body && document.body.innerText.trim().length > 0,
          null,
          { timeout: NAV_TIMEOUT_MS },
        );
        navErr = undefined;
        break;
      } catch (e) {
        navErr = e;
        if (TRANSIENT_NAV.test(e?.message ?? "") && attempt < NAV_ATTEMPTS) {
          log(`nav attempt ${attempt}/${NAV_ATTEMPTS} hit a transient error; retrying`);
          await new Promise((r) => setTimeout(r, 3000));
          continue;
        }
        break;
      }
    }
    if (navErr) {
      const transient = TRANSIENT_NAV.test(navErr?.message ?? "");
      if (server.explicit || transient) {
        await browser.close();
        skip(`could not reach the server at ${appBaseUrl} (${navErr?.message ?? "unknown"})`);
        return;
      }
      throw navErr;
    }

    log("app mounted; reaching the login screen");
    await reachLoginScreen(page);

    const adminBtn = page.getByText("Demo as admin", { exact: true });
    await adminBtn.waitFor({ timeout: STEP_TIMEOUT_MS });
    await adminBtn.click();
    await page.getByText("Profile", { exact: true }).first().waitFor({
      timeout: STEP_TIMEOUT_MS,
    });
    log("signed in as demo admin (tabs rendered)");

    // ---- Admin dashboard -> Perfect Pairings.
    const origin = new URL(appBaseUrl).origin;
    await page.goto(`${origin}/admin`, { waitUntil: "domcontentloaded" });
    const pairingsRow = page.getByText("Perfect Pairings", { exact: true }).first();
    await pairingsRow.waitFor({ timeout: STEP_TIMEOUT_MS });
    await pairingsRow.click();
    await waitForPairingsScreen();
    log("opened Admin dashboard → Perfect Pairings");

    // ---- Every mocked section + card renders, all checked by default.
    for (const section of CATALOG) {
      await page.getByText(section.label, { exact: true }).waitFor({ timeout: STEP_TIMEOUT_MS });
      for (const item of section.items) {
        await assertChecked(item.name, true, "initial render");
      }
    }
    if ((await badgeCountSettles(0)) !== 0) {
      fail('the "Section hidden" badge must not show while everything is enabled');
    }
    log(`all ${CATALOG.length} sections + cards rendered, everything checked`);

    // ---- Uncheck one card in the first section...
    await checkbox(UNCHECK_ONE.name).click();
    await assertChecked(UNCHECK_ONE.name, false, "after toggling one card");

    // ...and every card in the second section.
    for (const item of CATALOG.find((s) => s.key === EMPTY_SECTION.key).items) {
      await checkbox(item.name).click();
      await assertChecked(item.name, false, "after emptying the section");
    }

    // The emptied section (and only it) shows the "Section hidden" badge. The
    // badge mounts on the re-render that follows the toggles, so poll until the
    // count settles rather than a one-shot count() that races that re-render.
    const badgeCount = await badgeCountSettles(1);
    if (badgeCount !== 1) {
      fail(`exactly ONE "Section hidden" badge expected, got ${badgeCount}`);
    }
    log('unchecked cards; "Section hidden" badge shows on the emptied section only');

    // ---- Save and assert the PUT payload.
    await page.getByText("Save settings", { exact: true }).click();
    await page.waitForFunction(() => true, null, { timeout: 1000 }).catch(() => {});
    const putDeadline = Date.now() + STEP_TIMEOUT_MS;
    while (!hits.put && Date.now() < putDeadline) {
      await new Promise((r) => setTimeout(r, 250));
    }
    if (!hits.put) fail('"Save settings" never PUT /api/v1/admin/link-type-pairings');
    const enabled = hits.put.body?.enabled;
    if (!enabled || typeof enabled !== "object") {
      fail(`PUT body must be {enabled: {...}}, got ${JSON.stringify(hits.put.body)}`);
    }
    // Emptied section must be OMITTED from the payload (that's how "hide the
    // whole section" is expressed on the wire).
    if (EMPTY_SECTION.key in enabled) {
      fail(`emptied section "${EMPTY_SECTION.key}" must be omitted from the payload, got ${JSON.stringify(enabled)}`);
    }
    const biolinkEnabled = [...(enabled.biolink ?? [])].sort();
    const expectedBiolink = CATALOG.find((s) => s.key === "biolink")
      .items.map((i) => i.type)
      .filter((t) => t !== UNCHECK_ONE.type)
      .sort();
    if (JSON.stringify(biolinkEnabled) !== JSON.stringify(expectedBiolink)) {
      fail(
        `PUT enabled.biolink must be ${JSON.stringify(expectedBiolink)}, ` +
          `got ${JSON.stringify(biolinkEnabled)}`,
      );
    }
    log("save PUT the exact expected {enabled} payload");

    // ---- Reload → fresh GET → the state persisted.
    const getsBefore = hits.get;
    await page.goto(`${origin}/admin/link-type-pairings`, { waitUntil: "domcontentloaded" });
    await waitForPairingsScreen();
    if (hits.get <= getsBefore) fail("reload did not refetch the pairings status");
    await assertChecked(UNCHECK_ONE.name, false, "after reload");
    for (const item of CATALOG.find((s) => s.key === EMPTY_SECTION.key).items) {
      await assertChecked(item.name, false, "after reload (emptied section)");
    }
    await page.getByText("Section hidden", { exact: true }).first().waitFor({
      timeout: STEP_TIMEOUT_MS,
    });
    log("saved state persisted across a reload + refetch");

    // ---- Restore defaults (accept the confirm dialog).
    page.once("dialog", (dialog) => dialog.accept());
    await page.getByText("Restore defaults", { exact: true }).click();
    const restoreDeadline = Date.now() + STEP_TIMEOUT_MS;
    while (hits.restore === 0 && Date.now() < restoreDeadline) {
      await new Promise((r) => setTimeout(r, 250));
    }
    if (hits.restore === 0) {
      fail('"Restore defaults" never POSTed /admin/link-type-pairings/restore-defaults');
    }
    for (const section of CATALOG) {
      for (const item of section.items) {
        await assertChecked(item.name, true, "after restore defaults");
      }
    }
    if ((await badgeCountSettles(0)) !== 0) {
      fail('the "Section hidden" badge must disappear after restoring defaults');
    }
    log("restore defaults re-enabled every card and cleared the badge");

    console.log(
      "[pairings-admin-e2e] PASS: admin sign-in, dashboard entry, checkbox " +
        "toggling, Section hidden badge, save payload, persistence after " +
        "refetch, and restore-defaults all verified end-to-end.",
    );
  } finally {
    await browser.close().catch(() => {});
    manager.stopAllChildren();
  }
}

main().catch((e) => {
  fail(e?.stack ?? String(e));
});
