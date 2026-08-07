import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

// Regression guard for the Folders desk on the dashboard (task: "fold the
// standalone /user/projects Folders page into the dashboard itself").
//
// The old /user/projects page was retired: its route now 301s back to
// /user/dashboard#folders, and the dashboard gained a desktop-style "desk"
// section (#folders) where every folder (Project under the hood) renders as a
// 3D folder icon that opens the links filtered by that folder, plus a dashed
// "New Folder" icon with an inline AJAX create. This spec pins:
//   1. /user/projects redirects to the dashboard (no more standalone page),
//   2. the #folders desk renders with a seeded folder's icon + name + count,
//   3. clicking a folder lands on /user/links?project_id=...,
//   4. the inline New Folder flow (dashed icon → input → Create) actually
//      persists a folder via the AJAX store endpoint.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points
// it at the ephemeral e2e server, since localhost:80 hits the Express
// api-server — see the sibling dashboard-layout spec).

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

// Per-run unique names: fixtures live in the shared RDS, so parallel task
// environments must never collide on fixed names (memory: shared-RDS e2e
// fixture aliases). Stale desk fixtures from crashed runs are pruned in seed.
const RUN_TAG = Date.now().toString(36);
const SEED_FOLDER = `DeskFixture ${RUN_TAG}`;
const CREATED_FOLDER = `DeskCreated ${RUN_TAG}`;
const FIXTURE_PREFIX = "DeskFixture ";
const CREATED_PREFIX = "DeskCreated ";

function runTinkerSeed(php: string): string {
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
 * Idempotently prepare the demo user (active/verified/onboarded — see the
 * dashboard-layout spec) and seed one folder with a known name so the desk
 * has something deterministic to render. Also prunes folders left behind by
 * earlier runs of this spec.
 */
function seedFixtures(): void {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Plan;
use App\\Modules\\User\\Models\\Project;
use Illuminate\\Support\\Facades\\Hash;

$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
if (!$u) {
  $free = Plan::where('slug', 'free')->first();
  $u = User::create([
    'name' => 'Demo User', 'email' => '${DEMO_LOGIN_EMAIL}',
    'password' => Hash::make('password'), 'plan_id' => $free?->id,
    'status' => 'active', 'email_verified_at' => now(),
  ]);
}
$u->status = 'active';
if ($u->email_verified_at === null) { $u->email_verified_at = now(); }
if ($u->onboarded_at === null) { $u->onboarded_at = now(); }
$u->save();

Project::withoutGlobalScopes()->where('user_id', $u->id)
  ->where(function ($q) { $q->where('name', 'like', '${FIXTURE_PREFIX}%')->orWhere('name', 'like', '${CREATED_PREFIX}%'); })
  ->delete();

// Tinker has no bound current_workspace, so BelongsToWorkspace won't fill
// workspace_id — set it to the personal workspace explicitly or the folder
// is invisible to the workspace-scoped dashboard query.
// Mirror WorkspaceContext::resolve for a fresh session: the persisted
// active workspace wins, falling back to the user's first own workspace.
$ws = \\App\\Modules\\User\\Models\\Workspace::find((int) ($u->active_workspace_id ?? 0))
    ?? \\App\\Modules\\User\\Models\\Workspace::where('owner_user_id', $u->id)->orderBy('id')->first();
// workspace_id is not mass-assignable on Project — set it explicitly.
$p = $u->projects()->create(['name' => '${SEED_FOLDER}', 'color' => '#e11d48']);
$p->workspace_id = $ws?->id;
$p->save();

echo 'SEED_OK';
`.trim();

  const out = runTinkerSeed(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Folders desk seed failed, output:\n" + out);
  }
}

/** Assert (server-side) that a folder with the given name now exists. */
function folderExists(name: string): boolean {
  const php = `
use App\\Modules\\User\\Models\\User;
use App\\Modules\\User\\Models\\Project;
$u = User::where('email', '${DEMO_LOGIN_EMAIL}')->first();
echo Project::withoutGlobalScopes()->where('user_id', $u->id)->where('name', '${name}')->exists() ? 'YES' : 'NO';
`.trim();
  return runTinkerSeed(php).includes("YES");
}

async function gotoDashboard(page: Page): Promise<void> {
  await page.goto("/user/dashboard", { waitUntil: "domcontentloaded", timeout: 90_000 });
  await expect(page.locator("#folders")).toBeAttached({ timeout: 60_000 });
}

test.beforeAll(async ({ browser }) => {
  seedFixtures();
  sharedContext = await browser.newContext();
  const page = await sharedContext.newPage();
  await loginAsDemo(page);
  await page.close();
});

test.afterAll(async () => {
  await sharedContext?.close();
});

test("the old /user/projects page redirects to the dashboard desk", async ({ page }) => {
  await page.goto("/user/projects", { waitUntil: "domcontentloaded", timeout: 90_000 });
  await expect(page).toHaveURL(/\/user\/dashboard(#folders)?$/, { timeout: 60_000 });
  await expect(page.locator("#folders")).toBeAttached();
});

test("the desk renders the seeded folder as a desktop icon that opens its links", async ({ page }) => {
  await gotoDashboard(page);

  const desk = page.locator("#folders");
  // Heading + seeded folder icon with its label.
  await expect(desk.getByRole("heading", { name: "Folders" })).toBeVisible();
  const folderLink = desk.locator(`a.desk-icon-link[title="Open ${SEED_FOLDER}"]`);
  await expect(folderLink).toBeVisible();
  // The 3D icon parts exist and carry the folder's color.
  await expect(folderLink.locator(".fld-front")).toBeAttached();
  const fldStyle = await folderLink.locator(".fld").getAttribute("style");
  expect(fldStyle ?? "").toContain("#e11d48");
  // Count badge renders (0 links in the fixture folder).
  await expect(folderLink.locator(".fld-count")).toHaveText("0");

  // Clicking opens the links page filtered by this folder.
  await folderLink.click();
  await page.waitForURL(/\/user\/links\?project_id=\d+/, { timeout: 60_000 });
});

test("the dashed New Folder icon creates a folder inline via AJAX", async ({ page }) => {
  await gotoDashboard(page);

  const desk = page.locator("#folders");
  await desk.getByRole("button", { name: "New folder" }).click();

  const input = desk.getByPlaceholder("Folder name");
  await expect(input).toBeVisible();
  await input.fill(CREATED_FOLDER);
  await desk.getByRole("button", { name: "Create" }).click();

  // Success path reloads the dashboard and the new folder appears on the desk.
  await expect(
    page.locator(`#folders a.desk-icon-link[title="Open ${CREATED_FOLDER}"]`),
  ).toBeVisible({ timeout: 60_000 });
  expect(folderExists(CREATED_FOLDER)).toBe(true);
});
