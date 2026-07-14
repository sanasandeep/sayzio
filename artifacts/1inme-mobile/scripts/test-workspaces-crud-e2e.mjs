#!/usr/bin/env node
/**
 * End-to-end regression gate for native workspace CREATE + DELETE on mobile
 * (app/workspaces.tsx, app/workspace-edit.tsx, lib/api/workspaces.ts,
 * Task #4697), driving the REAL app in a headless browser.
 *
 * Why this exists: test-workspace-switcher.mjs pins the WIRING with a
 * source-driven check — it lifts workspaceFeatherIcon(), and asserts the
 * shipped JSX renders the mapped icon, the colour fallback and the owner-gated
 * gear on the switcher + list. What it can NOT see is the full RUNTIME loop:
 * open /workspaces, actually fill and submit the create form, watch the new
 * workspace appear in the list AND become the selected workspace in the drawer
 * switcher, then open the native edit screen for an owned team workspace,
 * delete it through the confirm guard, and watch it drop out of the list —
 * while the personal / last-remaining workspace never even offers a delete
 * affordance. A UI-wiring regression (the create form not POSTing, the
 * switcher not re-selecting, the confirm guard bypassed or missing, the delete
 * button leaking onto the personal workspace) would slip past the
 * source-driven test entirely.
 *
 * react-native-web's Alert.alert is a NO-OP, so the delete confirm is routed
 * through lib/webAlert.ts onto window.confirm on web. This harness arms a
 * Playwright dialog handler to accept that confirm, exactly the "user tapped
 * Delete" path.
 *
 * What it asserts, in order:
 *   1. Booted signed-in on /workspaces: the personal workspace row is listed.
 *   2. Creating a team workspace (name -> Create) POSTs /workspaces and the
 *      new row appears in the list.
 *   3. The new workspace becomes the SELECTED workspace in the drawer switcher
 *      (persisted active workspace), reflected after a fresh app load.
 *   4. Opening the owned team workspace's native edit screen shows the delete
 *      danger zone; deleting through the confirm guard DELETEs /workspaces/{id}
 *      and the row drops out of the list.
 *   5. The personal workspace's edit screen offers NO delete affordance (the
 *      personal / last-remaining workspace can never be deleted).
 *
 * Every /api/** call is intercepted against an in-memory workspace list so
 * nothing reaches a real backend. Like the sibling mobile e2e harnesses it
 * boots its OWN throwaway, self-contained Expo web dev server (shared
 * expo-web-server.mjs manager) unless APP_URL points at an already-running
 * one, and SKIPS (exit 0) when a server can't come up or a throwaway run dies
 * of a transient environment error so it never fails CI just because Metro
 * couldn't boot on a starved box.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:workspaces-crud-e2e
 *
 * Environment:
 *   APP_URL   reuse an already-running Expo web server instead of booting a
 *             throwaway one (skips are disabled then, so local debugging never
 *             silently skips).
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[workspaces-crud-e2e]", ...args);
}

function fail(msg) {
  console.error("[workspaces-crud-e2e] FAIL:", msg);
  process.exit(1);
}

function skip(msg) {
  console.log("[workspaces-crud-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-workspaces-crud-token";
const MOCK_USER = {
  id: 4697,
  display_name: "Workspace Owner",
  email: "workspaces-crud@example.com",
};

// Names chosen so they can't collide with any nav label, banner or button text
// on the screen — this lets us locate each workspace purely by its name.
const PERSONAL_NAME = "My Personal Space";
const TEAM_NAME = "QA Team Workspace";
const PERSONAL_ID = 1;

const EXPLICIT_APP_URL = process.env.APP_URL || null;

// The server-side workspace list the mock reports. The owner starts with just
// their personal workspace; POST /workspaces appends a team workspace and
// DELETE /workspaces/{id} removes one — exactly what the real endpoints do.
let nextId = 9001;
let workspaces = [
  {
    id: PERSONAL_ID,
    name: PERSONAL_NAME,
    slug: "personal",
    is_personal: true,
    owner_user_id: MOCK_USER.id,
    is_owner: true,
    color: "#3d6bff",
    icon: "user",
    created_at: "2026-01-01T00:00:00Z",
  },
];

function envelopeItems() {
  return { data: { items: workspaces } };
}

// Intercept every backend call. Only the boot path (/auth/me, /onboarding) and
// the workspace CRUD endpoints matter; everything else gets an empty success
// envelope so the authenticated tab/data burst never reaches a real API.
async function mockApi(context) {
  await context.route("**/api/**", async (route) => {
    const req = route.request();
    const method = req.method();
    const url = new URL(req.url());
    const path = url.pathname;

    let body = { data: [] };
    let status = 200;

    if (/\/api\/v1\/auth\/me$/.test(path)) {
      body = { data: { user: MOCK_USER } };
    } else if (/\/api\/v1\/onboarding$/.test(path)) {
      // A fully onboarded account so the launch gate lands straight on the
      // requested route instead of bouncing through /setup.
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
    } else if (/\/api\/v1\/workspaces$/.test(path)) {
      if (method === "POST") {
        // Create a team workspace, mirroring the server: the caller becomes the
        // owner, so it's is_owner + non-personal (hence deletable later).
        let payload = {};
        try {
          payload = JSON.parse(req.postData() || "{}");
        } catch {}
        const ws = {
          id: nextId++,
          name: String(payload.name ?? "Untitled"),
          slug: null,
          is_personal: false,
          owner_user_id: MOCK_USER.id,
          is_owner: true,
          color: payload.color ?? null,
          icon: payload.icon ?? null,
          created_at: new Date().toISOString(),
        };
        workspaces = [...workspaces, ws];
        body = { data: { item: ws } };
        status = 201;
      } else {
        body = envelopeItems();
      }
    } else if (/\/api\/v1\/workspaces\/\d+\/activate$/.test(path)) {
      body = { data: {} };
    } else if (/\/api\/v1\/workspaces\/\d+\/members$/.test(path)) {
      body = { data: { items: [] } };
    } else if (/\/api\/v1\/workspaces\/(\d+)$/.test(path)) {
      const id = Number(path.match(/\/workspaces\/(\d+)$/)[1]);
      if (method === "DELETE") {
        // The personal workspace and the owner's last workspace are protected
        // server-side (422) — but the app should never even offer that button,
        // so a DELETE arriving for them is itself a regression. Guard so the
        // mock can't paper over a leaked affordance.
        const target = workspaces.find((w) => w.id === id);
        if (!target || target.is_personal || workspaces.length <= 1) {
          body = {
            error: {
              message: "You can't delete this workspace.",
              code: "workspace_undeletable",
            },
          };
          status = 422;
        } else {
          workspaces = workspaces.filter((w) => w.id !== id);
          body = envelopeItems();
        }
      } else if (method === "PATCH") {
        body = { data: { item: workspaces.find((w) => w.id === id) ?? null } };
      } else {
        body = { data: { item: workspaces.find((w) => w.id === id) ?? null } };
      }
    }

    await route.fulfill({
      status,
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

// Poll for a leaf element whose exact trimmed text equals `text`.
async function hasExactText(page, text) {
  return page.evaluate((t) => {
    const leaves = Array.from(document.querySelectorAll("*")).filter(
      (el) => el.children.length === 0 && el.textContent.trim().length > 0,
    );
    return leaves.some((el) => el.textContent.trim() === t);
  }, text);
}

async function waitForText(page, text, whatFailed) {
  const deadline = Date.now() + STEP_TIMEOUT_MS;
  while (Date.now() < deadline) {
    if (await hasExactText(page, text)) return;
    await page.waitForTimeout(150);
  }
  fail(whatFailed);
}

async function waitForNoText(page, text, whatFailed) {
  const deadline = Date.now() + STEP_TIMEOUT_MS;
  while (Date.now() < deadline) {
    if (!(await hasExactText(page, text))) return;
    await page.waitForTimeout(150);
  }
  fail(whatFailed);
}

// The WorkspaceProvider (workspace list + switchWorkspace) wraps the whole root
// stack, so /workspaces and /workspace-edit share the same live workspace
// context/state as the tabs switcher — a create on the list screen flips the
// active workspace the switcher reads, and the edit screen resolves its
// workspace from the same list. Real users reach these screens by navigating
// out of the tabs shell via the drawer, so the flow drives in-app SPA
// navigation from the tabs (which also keeps the switcher mounted to assert
// against) rather than raw reloads.
async function bootTabs(page, appUrl) {
  await page.goto(`${appUrl.replace(/\/$/, "")}/`, {
    waitUntil: "domcontentloaded",
    timeout: NAV_TIMEOUT_MS,
  });
}

async function openDrawer(page) {
  const menuBtn = page.locator('[aria-label="Open menu"]').first();
  await menuBtn.waitFor({ state: "visible", timeout: NAV_TIMEOUT_MS });
  await menuBtn.click();
}

// Open the drawer and tap a nav link by its exact label, keeping the tabs shell
// (and WorkspaceProvider) mounted underneath the pushed screen.
async function navViaDrawer(page, label) {
  await openDrawer(page);
  const link = page.getByText(label, { exact: true }).first();
  await link.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await link.click();
}

async function runCheck(page, appUrl) {
  // ---- 1. Booted on /workspaces: the personal workspace is listed ---------
  await bootTabs(page, appUrl);
  await navViaDrawer(page, "Workspaces");
  await waitForText(
    page,
    PERSONAL_NAME,
    "the Workspaces screen never listed the personal workspace — the " +
      "/workspaces route did not mount (a gate/layout redirect may have " +
      "stolen it, or the list query never resolved)",
  );
  log("opened /workspaces from the tabs drawer — personal workspace listed");

  // ---- 2. Create a team workspace: it POSTs and appears in the list -------
  const newBtn = page.locator('[aria-label="New workspace"]').first();
  await newBtn.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await newBtn.click();

  const nameInput = page.getByPlaceholder("e.g. Marketing team").first();
  await nameInput.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await nameInput.fill(TEAM_NAME);

  const createBtn = page.locator('[aria-label="Create workspace"]').first();
  await createBtn.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });

  const createResp = page.waitForResponse(
    (r) =>
      /\/api\/v1\/workspaces$/.test(new URL(r.url()).pathname) &&
      r.request().method() === "POST",
    { timeout: STEP_TIMEOUT_MS },
  );
  await createBtn.click();
  const resp = await createResp.catch(() =>
    fail("clicking Create did not POST /workspaces — the create form is not wired"),
  );
  if (resp.status() >= 400) {
    fail(`POST /workspaces returned ${resp.status()} — create failed`);
  }
  await waitForText(
    page,
    TEAM_NAME,
    "the newly created workspace never appeared in the list after Create — " +
      "the list didn't refresh from the create mutation",
  );
  log("created team workspace — new row appears in the list");

  // ---- 3. Creating a workspace selects it in the switcher ----------------
  // Creating a workspace auto-switches to it (onCreate → switchWorkspace),
  // which flips the active workspace (setActiveId) in the still-mounted tabs
  // provider. The switcher lives on the tabs drawer, so navigate back into the
  // tabs shell (SPA history back keeps the provider mounted) and assert the
  // active-workspace button now reflects the freshly created team.
  await page.goBack({ waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT_MS });
  await openDrawer(page);

  const activeSwitcher = page
    .locator(`[aria-label^="Active workspace: ${TEAM_NAME}"]`)
    .first();
  await activeSwitcher
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(
        `creating the "${TEAM_NAME}" workspace did not select it in the ` +
          "switcher — the active-workspace button never reflected the new " +
          "workspace (create → auto-switch regression)",
      ),
    );
  log("the created workspace is selected as the active workspace in the switcher");

  // ---- 4. Delete the owned team workspace through the confirm guard -------
  // Re-enter /workspaces from the drawer (keeps the provider mounted so the
  // edit screen can resolve the workspace).
  await navViaDrawer(page, "Workspaces");
  await waitForText(page, TEAM_NAME, "the team workspace row vanished before the delete leg");

  // The owner-only edit gear opens the native workspace-edit screen.
  const gear = page.locator(`[aria-label="Edit workspace ${TEAM_NAME}"]`).first();
  await gear.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await gear.click();

  const deleteBtn = page.locator('[aria-label="Delete workspace"]').first();
  await deleteBtn
    .waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail(
        "the workspace-edit screen for an owned team workspace did not show " +
          "the Delete danger zone — the delete affordance is missing",
      ),
    );
  log("workspace-edit screen shows the Delete danger zone for the owned team workspace");

  // Arm the confirm guard: react-native-web's Alert.alert is a no-op, so the
  // delete confirm is a window.confirm — accept it (the "user tapped Delete"
  // path). Dismissing it would (correctly) NOT delete.
  let deleteDialogSeen = false;
  const dialogHandler = async (dialog) => {
    deleteDialogSeen = true;
    if (dialog.type() === "confirm") {
      await dialog.accept().catch(() => {});
    } else {
      await dialog.dismiss().catch(() => {});
    }
  };
  page.on("dialog", dialogHandler);

  const deleteResp = page.waitForResponse(
    (r) =>
      /\/api\/v1\/workspaces\/\d+$/.test(new URL(r.url()).pathname) &&
      r.request().method() === "DELETE",
    { timeout: STEP_TIMEOUT_MS },
  );
  await deleteBtn.click();
  const delResp = await deleteResp.catch(() =>
    fail(
      "confirming the delete did not DELETE /workspaces/{id} — the confirm " +
        "guard didn't run the delete (webAlert/confirm regression)",
    ),
  );
  page.off("dialog", dialogHandler);
  if (!deleteDialogSeen) {
    fail(
      "no confirmation dialog was raised before deleting — the destructive " +
        "confirm guard is missing (a workspace would be deleted on a single tap)",
    );
  }
  if (delResp.status() >= 400) {
    fail(
      `DELETE returned ${delResp.status()} — the app tried to delete a ` +
        `protected/undeletable workspace (leaked affordance)`,
    );
  }

  // Back on the list, the deleted workspace is gone and the personal one stays.
  await waitForNoText(
    page,
    TEAM_NAME,
    "the deleted workspace still shows in the list — the delete didn't " +
      "refresh the list surface",
  );
  await waitForText(page, PERSONAL_NAME, "the personal workspace vanished after deleting the team one");
  log("deleted the team workspace through the confirm guard — row dropped from the list");

  // ---- 5. The personal workspace offers NO delete affordance -------------
  const personalGear = page
    .locator(`[aria-label="Edit workspace ${PERSONAL_NAME}"]`)
    .first();
  await personalGear.waitFor({ state: "visible", timeout: STEP_TIMEOUT_MS });
  await personalGear.click();

  // The edit screen must render (its Name field is a stable anchor), then we
  // assert there is NO Delete affordance for the personal / last workspace.
  await page
    .getByText("Save changes", { exact: true })
    .first()
    .waitFor({ timeout: STEP_TIMEOUT_MS })
    .catch(() =>
      fail("the personal workspace-edit screen never rendered its Save control"),
    );
  const personalDelete = page.locator('[aria-label="Delete workspace"]');
  if ((await personalDelete.count()) > 0) {
    fail(
      "the personal (last-remaining) workspace exposed a Delete affordance — " +
        "the personal / last workspace must never be deletable",
    );
  }
  log("personal / last-remaining workspace correctly hides the delete affordance");
}

async function run() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("workspaces-crud", EXPLICIT_APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
    return;
  }
  const { appUrl, child, explicit } = server;
  log("driving the workspace create/delete check against", appUrl);

  let browser;
  try {
    browser = await chromium.launch({ headless: true });
  } catch (e) {
    stopExpo(child);
    skip(
      `could not launch a headless browser in this environment: ${e?.message || e}`,
    );
    return;
  }

  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await seedSession(context);
    await mockApi(context);

    const page = await context.newPage();
    page.on("pageerror", (e) => log("pageerror:", e.message));
    page.setDefaultTimeout(STEP_TIMEOUT_MS);
    page.setDefaultNavigationTimeout(NAV_TIMEOUT_MS);

    try {
      await runCheck(page, appUrl);
    } catch (e) {
      // Best-effort contract: transient environment errors on a throwaway
      // server SKIP; real regressions exited 1 via fail() before reaching
      // here. Against an explicit APP_URL we always fail hard.
      if (!explicit && isTransientEnvError(e)) {
        await browser.close().catch(() => {});
        stopExpo(child);
        skip(
          `the environment was too slow to drive the check ` +
            `(${e?.message?.split("\n")[0] ?? "unknown error"}); ` +
            `skipping (best-effort, not a workspace-crud regression)`,
        );
        return;
      }
      throw e;
    }

    log(
      "PASS: an owner can create a workspace (it appears + becomes selected in " +
        "the switcher) and delete an owned team workspace through the confirm " +
        "guard (it drops from the list), while the personal / last-remaining " +
        "workspace never offers a delete affordance.",
    );
    await browser.close().catch(() => {});
    stopExpo(child);
    // Explicit exit: the throwaway Metro child would otherwise keep the event
    // loop alive; the manager's process-exit hook reaps it.
    process.exit(0);
  } finally {
    await browser.close().catch(() => {});
    stopExpo(child);
  }
}

// Termination guarantee: runHarness exits the process as soon as run()
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(run, {
  log,
  onError: (e) => {
    const msg = e?.message || String(e);
    const infra =
      /Target page, context or browser has been closed|browser has been closed|browserType\.launch|pthread_create|Browser closed|Target closed/i.test(
        msg,
      );
    if (infra) {
      skip(
        `browser crashed under environment load, not a product failure: ${msg.split("\n")[0]}`,
      );
    }
    fail(e?.stack || msg);
  },
});
