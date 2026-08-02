import { expect, test } from "@playwright/test";

import {
  ALIAS_PREFIX,
  OWNER_EMAIL,
  RUN_ID,
  runTinker,
  seedHeader,
} from "./event-features-seed";

// Public event page + add-to-calendar — Task #6548.
//
// Drives resources/views/common/event-page.blade.php as an anonymous visitor
// whose browser timezone (pinned to UTC by the Playwright context below)
// differs from the event's own timezone (America/New_York). Asserts:
//   1. the "in your timezone" chip (#ev-local-time) is revealed by the inline
//      Intl JS and reads "Your time: …";
//   2. the Google Calendar add-to-calendar link href is well-formed
//      (calendar.google.com/render?action=TEMPLATE&dates=…&ctz=America/New_York);
//   3. the ?ics=1 download returns a valid VCALENDAR containing this event.
//
// Selectors are scoped tightly to the add-to-calendar chips + the timezone
// chip so this stays green even if a concurrent "cancel event" task adds a
// cancelled banner elsewhere on the page.

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const ALIAS = `${ALIAS_PREFIX}-public-${RUN_ID}`;
const CONSENT_COOKIE = "1inme_cookie_consent";
const EVENT_TZ = "America/New_York";
const EVENT_TITLE = `Public Page Gala ${RUN_ID}`;

function seed(): void {
  const php =
    seedHeader() +
    `
use App\\Modules\\User\\Models\\IcsData;
use App\\Modules\\User\\Services\\WorkspaceContext;

$u = User::where('email', '${OWNER_EMAIL}')->first();
$ws = app(WorkspaceContext::class)->resolve($u);

$link = Link::query()->withoutGlobalScope('workspace')->updateOrCreate(
  ['alias' => '${ALIAS}'],
  [
    'workspace_id' => $ws->id, 'user_id' => $u->id, 'created_by_user_id' => $u->id,
    'type' => 'ics', 'title' => '${EVENT_TITLE}',
    'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
    'settings' => ['event_category' => 'community', 'is_online' => false, 'rsvp_enabled' => true],
  ]
);
$link->eventTicketTiers()->delete();
IcsData::updateOrCreate(
  ['link_id' => $link->id],
  [
    'event_name' => '${EVENT_TITLE}',
    'description' => 'Public event page e2e.',
    'location' => 'New York, NY', 'organizer' => $u->name,
    // A fixed near-future start, in the event's own (non-UTC) timezone.
    'start_date' => \\Carbon\\Carbon::parse('+30 days 18:00', '${EVENT_TZ}'),
    'end_date' => \\Carbon\\Carbon::parse('+30 days 20:00', '${EVENT_TZ}'),
    'timezone' => '${EVENT_TZ}',
    'cover_image_url' => null, 'gallery' => [],
  ]
);
echo 'SEED_OK';
`;
  const out = runTinker(php);
  if (!out.includes("SEED_OK")) {
    throw new Error("Public event seed did not confirm SEED_OK:\n" + out);
  }
}

test.describe("public event page — timezone chip + add-to-calendar", () => {
  test.beforeAll(() => {
    seed();
  });

  // Pin the viewer timezone to UTC so it differs from the event's
  // America/New_York timezone, which is exactly what reveals the "in your
  // timezone" chip.
  test.use({ timezoneId: "UTC" });

  test("timezone chip renders and Google Calendar link is well-formed", async ({
    page,
  }) => {
    // Pre-dismiss cookie consent so its banner doesn't overlay the page.
    const decision = JSON.stringify({
      v: 1,
      t: Date.now(),
      c: { analytics: true, marketing: false, functional: false },
    });
    await page.context().addCookies([
      {
        name: CONSENT_COOKIE,
        value: encodeURIComponent(decision),
        domain: "localhost",
        path: "/",
      },
    ]);

    await page.goto(`${APP_BASE}/${ALIAS}`, {
      waitUntil: "domcontentloaded",
      timeout: 60_000,
    });

    // --- 1. "in your timezone" chip revealed by the inline Intl JS. ---
    const tzChip = page.locator("#ev-local-time");
    await expect(
      tzChip,
      "the guest-local time chip should be revealed when viewer tz != event tz",
    ).toBeVisible({ timeout: 15_000 });
    await expect(tzChip.locator("[data-local-label]")).toHaveText(
      /^Your time: .+/,
    );

    // --- 2. Google Calendar add-to-calendar link is well-formed. ---
    const gcal = page.getByRole("link", { name: /Google Calendar/i });
    await expect(gcal).toBeVisible();
    const href = await gcal.getAttribute("href");
    expect(href, "google calendar href present").toBeTruthy();
    const url = new URL(href as string);
    expect(url.hostname).toBe("calendar.google.com");
    expect(url.pathname).toContain("/calendar/render");
    expect(url.searchParams.get("action")).toBe("TEMPLATE");
    expect(url.searchParams.get("text")).toBe(EVENT_TITLE);
    expect(url.searchParams.get("ctz")).toBe(EVENT_TZ);
    // dates must be the compact UTC "Ymd\THis\Z/Ymd\THis\Z" form.
    const dates = url.searchParams.get("dates") || "";
    expect(dates).toMatch(
      /^\d{8}T\d{6}Z\/\d{8}T\d{6}Z$/,
    );
  });

  test(".ics download (?ics=1) returns a valid VCALENDAR with the event", async ({
    page,
  }) => {
    // Fetch the ICS via the page context (shares the app origin/session) and
    // assert the calendar payload rather than triggering a file download.
    const res = await page.request.get(`${APP_BASE}/${ALIAS}?ics=1`);
    expect(res.status(), "ics endpoint should return 200").toBe(200);
    expect(
      (res.headers()["content-type"] || "").toLowerCase(),
    ).toContain("text/calendar");

    const body = await res.text();
    expect(body).toContain("BEGIN:VCALENDAR");
    expect(body).toContain("END:VCALENDAR");
    expect(body).toContain("VERSION:2.0");
    expect(body).toContain("BEGIN:VEVENT");
    expect(body).toContain("END:VEVENT");
    // The VEVENT carries this event's summary and a tz-anchored DTSTART.
    expect(body).toContain(`SUMMARY:${EVENT_TITLE}`);
    expect(body).toContain(`DTSTART;TZID=${EVENT_TZ}:`);
  });
});
