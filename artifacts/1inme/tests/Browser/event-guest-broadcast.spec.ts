import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";
import {
  ALIAS_PREFIX,
  OWNER_EMAIL,
  RUN_ID,
  runTinker,
  seedHeader,
} from "./event-features-seed";

// Guest-broadcast ("Message guests") organizer flow — Task #6546.
//
// Drives resources/views/user/links/broadcast.blade.php as the event organizer:
//   1. the live per-audience recipient counts render (from the x-data `counts`
//      map the controller precomputes);
//   2. sending a broadcast succeeds and appears in the "Past broadcasts" history;
//   3. an immediate re-send is refused by the 60s per-link cooldown
//      (EventBroadcastService::COOLDOWN_SECONDS) and surfaces its error message.
//
// Self-bootstrapping: seeds one ics event owned by the demo account with a
// handful of confirmed + waitlisted RSVPs (so the audience counts are non-zero
// and the send resolves real recipients) via `php artisan tinker`.

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const ALIAS = `${ALIAS_PREFIX}-broadcast-${RUN_ID}`;

// Populated by seed(): the numeric Link id (the broadcast/RSVP routes bind
// `{link}` by primary key, not alias).
let LINK_ID = 0;

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
    'type' => 'ics', 'title' => 'Broadcast Test Meetup',
    'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
    'settings' => ['event_category' => 'community', 'is_online' => false, 'rsvp_enabled' => true],
  ]
);
IcsData::updateOrCreate(
  ['link_id' => $link->id],
  [
    'event_name' => 'Broadcast Test Meetup',
    'description' => 'Broadcast e2e event.',
    'location' => 'San Francisco, CA', 'organizer' => $u->name,
    'start_date' => \\Carbon\\Carbon::parse('+7 days'),
    'end_date' => \\Carbon\\Carbon::parse('+7 days')->addHours(2),
    'timezone' => 'UTC',
  ]
);
// Reset RSVPs + broadcast history so counts/cooldown are deterministic.
Rsvp::where('link_id', $link->id)->delete();
DB::table('event_broadcasts')->where('link_id', $link->id)->delete();
$make = function (string $name, string $status, string $response) use ($link) {
  Rsvp::create([
    'link_id' => $link->id, 'name' => $name,
    'email' => strtolower(str_replace(' ', '.', $name)) . '.${RUN_ID}@example.com',
    'response' => $response, 'status' => $status, 'plus_ones' => 0,
    'source' => 'rsvp_form',
  ]);
};
$make('Going Ann', 'confirmed', 'yes');
$make('Going Bob', 'confirmed', 'yes');
$make('Waitlist Cara', 'waitlist', 'yes');
echo 'SEED_OK id=' . $link->id;
`;

  const out = runTinker(php);
  const m = out.match(/SEED_OK id=(\d+)/);
  if (!m) {
    throw new Error("Broadcast seed did not confirm SEED_OK:\n" + out);
  }
  LINK_ID = Number(m[1]);
}

/**
 * Fill + submit the compose form via its real themed-confirm modal (the form's
 * `onsubmit` opens window.themedConfirm; clicking its OK button re-submits with
 * the confirmed flag). Waits on the send POST (cold-RDS first write can exceed
 * the 10s default — see repo memory e2e-editor-create-cold-rds-latency.md).
 */
async function sendBroadcast(page: Page, subject: string, message: string) {
  await page.fill('input[name="subject"]', subject);
  await page.fill('textarea[name="message"]', message);

  const sendResp = page.waitForResponse(
    (r) =>
      r.url().includes(`/broadcast`) &&
      r.request().method() === "POST",
    { timeout: 60_000 },
  );
  await page.getByRole("button", { name: /Send to .* guest/i }).click();
  // Themed confirm modal: accept it.
  const ok = page.locator("[data-themed-confirm-ok]");
  await ok.waitFor({ state: "visible", timeout: 10_000 });
  await ok.click();
  await sendResp;
  await page.waitForLoadState("domcontentloaded");
}

test.describe("event guest broadcast", () => {
  test.beforeAll(() => {
    seed();
  });

  test("counts render, send succeeds + logs history, re-send hits cooldown", async ({
    page,
  }) => {
    await loginAsDemo(page);
    // `{link}` binds by primary key, so navigate by the seeded numeric id.
    await page.goto(`${APP_BASE}/user/links-ics/${LINK_ID}/broadcast`, {
      waitUntil: "domcontentloaded",
      timeout: 60_000,
    });
    await expect(
      page.getByRole("heading", { name: /Message guests/i }),
      "broadcast compose page should render for the seeded event",
    ).toBeVisible({ timeout: 15_000 });

    // --- 1. Live audience counts render. all_rsvps = 3 (2 going + 1 waitlist),
    // the default selected audience; the count line reflects it. ---
    await expect(
      page.getByText(/recipient\(s\) will receive this message/i),
    ).toBeVisible();
    const countText = page.locator("span[x-text='count']").first();
    await expect(countText).toHaveText("3", { timeout: 10_000 });

    // Switching the audience updates the live count (going = 2).
    await page.selectOption('select[name="audience"]', "going");
    await expect(countText).toHaveText("2", { timeout: 10_000 });
    // Back to all for the actual send.
    await page.selectOption('select[name="audience"]', "all_rsvps");
    await expect(countText).toHaveText("3", { timeout: 10_000 });

    // --- 2. Send a broadcast → success banner + history entry. ---
    const subject = `E2E venue update ${RUN_ID}`;
    await sendBroadcast(page, subject, "The venue has moved to the main hall.");

    await expect(
      page.getByText(/Message sent to \d+ guest\(s\)\./i).first(),
      "success flash should render after a send",
    ).toBeVisible({ timeout: 15_000 });

    await expect(
      page.getByText("Past broadcasts"),
      "history section should render",
    ).toBeVisible();
    await expect(
      page.locator(".card-premium").filter({ hasText: subject }),
      "the just-sent broadcast should appear in history",
    ).toBeVisible({ timeout: 10_000 });

    // --- 3. Immediate re-send → 60s cooldown error. ---
    await sendBroadcast(page, `E2E second ${RUN_ID}`, "Second message.");
    await expect(
      page.getByText(/Please wait \d+s before sending another message/i).first(),
      "an immediate re-send should be refused by the 60s cooldown",
    ).toBeVisible({ timeout: 15_000 });
  });
});
