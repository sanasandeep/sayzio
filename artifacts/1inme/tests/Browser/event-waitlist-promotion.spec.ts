import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";
import {
  ALIAS_PREFIX,
  OWNER_EMAIL,
  RUN_ID,
  runTinker,
  seedHeader,
} from "./event-features-seed";

// Waitlist auto-promotion — Task #6547.
//
// Seeds an ics event at capacity=1 with one confirmed ("going") guest and one
// waitlisted guest. The organizer removes the confirmed guest through the
// RSVP-management page (resources/views/user/links/rsvps.blade.php): the trash
// button POSTs to links.rsvps.destroy, whose controller frees the seat and runs
// WaitlistPromotionService::promoteForLink. This spec asserts the previously
// waitlisted guest is now shown as "Going / Confirmed" and no longer carries a
// waitlist "#N in line" position.

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const ALIAS = `${ALIAS_PREFIX}-waitlist-${RUN_ID}`;

let LINK_ID = 0;
let CONFIRMED_RSVP_ID = 0;

function seed(): void {
  const php =
    seedHeader() +
    `
use App\\Modules\\User\\Models\\IcsData;
use App\\Modules\\User\\Models\\Rsvp;
use App\\Modules\\User\\Services\\WorkspaceContext;

$u = User::where('email', '${OWNER_EMAIL}')->first();
$ws = app(WorkspaceContext::class)->resolve($u);

$link = Link::query()->withoutGlobalScope('workspace')->updateOrCreate(
  ['alias' => '${ALIAS}'],
  [
    'workspace_id' => $ws->id, 'user_id' => $u->id, 'created_by_user_id' => $u->id,
    'type' => 'ics', 'title' => 'Waitlist Test Dinner',
    'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
    // capacity = 1 so exactly one confirmed seat exists; auto-promote defaults ON.
    'settings' => [
      'event_category' => 'community', 'is_online' => false, 'rsvp_enabled' => true,
      'rsvp_settings' => ['capacity' => 1],
    ],
  ]
);
IcsData::updateOrCreate(
  ['link_id' => $link->id],
  [
    'event_name' => 'Waitlist Test Dinner',
    'description' => 'Waitlist e2e event.',
    'location' => 'Oakland, CA', 'organizer' => $u->name,
    'start_date' => \\Carbon\\Carbon::parse('+10 days'),
    'end_date' => \\Carbon\\Carbon::parse('+10 days')->addHours(2),
    'timezone' => 'UTC',
  ]
);
Rsvp::where('link_id', $link->id)->delete();
$going = Rsvp::create([
  'link_id' => $link->id, 'name' => 'Dora Seatholder',
  'email' => 'dora.${RUN_ID}@example.com',
  'response' => 'yes', 'status' => 'confirmed', 'plus_ones' => 0, 'source' => 'rsvp_form',
  'created_at' => now()->subMinutes(20),
]);
$wait = Rsvp::create([
  'link_id' => $link->id, 'name' => 'Evan Inqueue',
  'email' => 'evan.${RUN_ID}@example.com',
  'response' => 'yes', 'status' => 'waitlist', 'plus_ones' => 0, 'source' => 'rsvp_form',
  'created_at' => now()->subMinutes(10),
]);
echo 'SEED_OK link=' . $link->id . ' confirmed=' . $going->id . ' wait=' . $wait->id;
`;

  const out = runTinker(php);
  const m = out.match(/SEED_OK link=(\d+) confirmed=(\d+) wait=(\d+)/);
  if (!m) {
    throw new Error("Waitlist seed did not confirm SEED_OK:\n" + out);
  }
  LINK_ID = Number(m[1]);
  CONFIRMED_RSVP_ID = Number(m[2]);
}

test.describe("event waitlist auto-promotion", () => {
  test.beforeAll(() => {
    seed();
  });

  test("cancelling the going guest promotes the waitlisted guest to confirmed", async ({
    page,
  }) => {
    await loginAsDemo(page);
    await page.goto(`${APP_BASE}/user/links/${LINK_ID}/rsvps`, {
      waitUntil: "domcontentloaded",
      timeout: 60_000,
    });
    await expect(
      page.getByRole("heading", { name: /RSVPs/i }),
      "RSVP management page should render",
    ).toBeVisible({ timeout: 15_000 });

    // --- Baseline: the waitlisted guest shows a Waitlist status + a queue
    // position, and there is exactly one confirmed seat used of capacity 1. ---
    const waitRow = page.locator("tr", { hasText: "Evan Inqueue" }).first();
    // The status/response badge spans render their label with a CSS
    // uppercase transform, but the DOM text stays "Waitlist"/"Going" (with
    // surrounding whitespace). A string `hasText` normalizes that whitespace.
    await expect(
      waitRow.locator("span").filter({ hasText: "Waitlist" }),
      "the queued guest should show a Waitlist status badge",
    ).toBeVisible();
    await expect(waitRow.getByText(/#\d+ in line/)).toBeVisible();

    // Capacity tile: 1 of 1.
    await expect(page.getByText(/\bof 1\b/)).toBeVisible();

    // --- Remove the confirmed guest via the row's trash button (themed
    // confirm modal → OK). This is the seat-freeing action that auto-promotes.
    const removeResp = page.waitForResponse(
      (r) =>
        r.url().includes(`/rsvps/${CONFIRMED_RSVP_ID}`) &&
        r.request().method() === "POST",
      { timeout: 60_000 },
    );
    // The remove control is a submit button (icon-only, title="Remove") inside
    // the row's delete form (POST + _method=DELETE to /rsvps/{id}). Target it by
    // the form's action to avoid depending on the icon's accessible name.
    const removeButton = page
      .locator(`form[action$="/rsvps/${CONFIRMED_RSVP_ID}"] button[title="Remove"]`)
      .first();
    await removeButton.click();
    const ok = page.locator("[data-themed-confirm-ok]");
    await ok.waitFor({ state: "visible", timeout: 10_000 });
    await ok.click();
    await removeResp;
    await page.waitForLoadState("domcontentloaded");

    // --- After promotion: the removed guest is gone, and the formerly
    // waitlisted guest now renders as Going / Confirmed with no queue line. ---
    await expect(
      page.getByText(/RSVP removed\./i).first(),
      "removal success flash should render",
    ).toBeVisible({ timeout: 15_000 });

    await expect(
      page.getByText("Dora Seatholder"),
      "the removed confirmed guest should no longer be listed",
    ).toHaveCount(0);

    const promotedRow = page.locator("tr", { hasText: "Evan Inqueue" }).first();
    await expect(
      promotedRow.locator("span").filter({ hasText: "Confirmed" }),
      "the promoted guest should now show a Confirmed status badge",
    ).toBeVisible({ timeout: 10_000 });
    await expect(
      promotedRow.locator("span").filter({ hasText: "Going" }),
      "the promoted guest's response badge should read Going",
    ).toBeVisible();
    await expect(
      promotedRow.getByText(/#\d+ in line/),
      "the promoted guest should no longer show a waitlist position",
    ).toHaveCount(0);
  });
});
