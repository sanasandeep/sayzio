import { expect, test, type Page } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";
import {
  ALIAS_PREFIX,
  OWNER_EMAIL,
  RUN_ID,
  runTinker,
  seedHeader,
} from "./event-features-seed";

// Edit Event location picker — pasting a Google Maps link always fills a
// readable address (Task #6718).
//
// Locks in the URL forms extractFromMapUrl (resources/js/map-pin-picker.js)
// supports so regex edits can't silently break the paste flow:
//   1. /maps/place/<Name>/@lat,lng,zoom → keeps the place name, appends the
//      reverse-geocoded street address;
//   2. ?q=lat,lng (literal comma) → reverse-geocoded address;
//   3. ?q=lat%2Clng (%2C-encoded comma) → same as literal;
//   4. plain-text pastes fall through to the browser default (handler must
//      NOT preventDefault or touch the model);
//   5. unrecognized short links (maps.app.goo.gl) keep the raw text so
//      nothing is lost.
// Plus the autocomplete path: typing shows Nominatim suggestions, choosing
// one fills the input, and the form PUT submits name=location correctly.
//
// All Nominatim traffic is stubbed with page.route so the spec is
// deterministic/offline — no real OSM requests are made.

const APP_BASE = process.env.APP_URL || "http://localhost:5000";
const ALIAS = `${ALIAS_PREFIX}-mappaste-${RUN_ID}`;

let LINK_ID = 0;

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
    'type' => 'ics', 'title' => 'Map Paste Test Meetup',
    'is_active' => true, 'visibility' => 'public', 'is_demo' => true,
    'settings' => ['event_category' => 'community', 'is_online' => false],
  ]
);
IcsData::updateOrCreate(
  ['link_id' => $link->id],
  [
    'event_name' => 'Map Paste Test Meetup',
    'description' => 'Map link paste e2e event.',
    'location' => '', 'organizer' => $u->name,
    'start_date' => \\Carbon\\Carbon::parse('+7 days'),
    'end_date' => \\Carbon\\Carbon::parse('+7 days')->addHours(2),
    'timezone' => 'UTC',
  ]
);
echo 'SEED_OK id=' . $link->id;
`;

  const out = runTinker(php);
  const m = out.match(/SEED_OK id=(\d+)/);
  if (!m) throw new Error("Map-paste seed did not confirm SEED_OK:\n" + out);
  LINK_ID = Number(m[1]);
}

/* ---- Nominatim stubs (deterministic/offline) --------------------------- */

const REVERSE_PARIS =
  "Eiffel Tower, 5, Avenue Anatole France, 7th Arrondissement, Paris, France";
const REVERSE_NYC =
  "350, 5th Avenue, Midtown, Manhattan, New York, NY 10118, United States";
// Autocomplete goes through the app's own cached/throttled proxy
// (/user/geo/suggest), which returns the normalized {suggestions:[...]}
// shape below — NOT direct browser calls to Nominatim.
const SUGGESTIONS = [
  {
    id: 9001,
    label: "Central Park, Manhattan, New York, NY, United States",
    lat: "40.7825547",
    lng: "-73.9655834",
  },
  {
    id: 9002,
    label: "Central Park Zoo, E 64th St, New York, NY, United States",
    lat: "40.7677",
    lng: "-73.9718",
  },
];

/** Counts so tests can assert the stub was (or wasn't) hit. */
let reverseCalls: string[] = [];
let searchCalls: string[] = [];

async function stubNominatim(page: Page): Promise<void> {
  reverseCalls = [];
  searchCalls = [];
  await page.route("https://nominatim.openstreetmap.org/**", async (route) => {
    const url = route.request().url();
    if (url.includes("/reverse")) {
      reverseCalls.push(url);
      const lat = new URL(url).searchParams.get("lat") || "";
      // Distinguish the Paris pin from the NYC pins by latitude.
      const displayName = lat.startsWith("48.") ? REVERSE_PARIS : REVERSE_NYC;
      await route.fulfill({
        contentType: "application/json",
        headers: { "access-control-allow-origin": "*" },
        body: JSON.stringify({ display_name: displayName }),
      });
      return;
    }
    await route.fulfill({
      contentType: "application/json",
      headers: { "access-control-allow-origin": "*" },
      body: "{}",
    });
  });
  // Autocomplete hits the app's own /user/geo/suggest proxy (never Nominatim
  // directly from the browser); stub it with the normalized envelope.
  await page.route("**/user/geo/suggest*", async (route) => {
    searchCalls.push(route.request().url());
    await route.fulfill({
      contentType: "application/json",
      body: JSON.stringify({ suggestions: SUGGESTIONS }),
    });
  });
  // The map tiles are only fetched if the map pane opens; block them anyway
  // so nothing external can be hit.
  await page.route("https://*.tile.openstreetmap.org/**", (route) =>
    route.abort(),
  );
}

/* ---- Paste simulation --------------------------------------------------- */

/**
 * Dispatch a synthetic ClipboardEvent("paste") carrying `text` on the
 * location input. Returns whether the handler called preventDefault()
 * (i.e. intercepted the paste). Synthetic pastes are untrusted so the
 * browser never performs the default insertion itself — for the fall-through
 * case we assert the handler left the event (and the model) alone.
 */
async function pasteIntoLocation(page: Page, text: string): Promise<boolean> {
  return page.evaluate((clip) => {
    const input = document.querySelector<HTMLInputElement>(
      'input[name="location"]',
    );
    if (!input) throw new Error("location input not found");
    const dt = new DataTransfer();
    dt.setData("text/plain", clip);
    const evt = new ClipboardEvent("paste", {
      bubbles: true,
      cancelable: true,
    });
    // Chromium ignores `clipboardData` in the ClipboardEvent constructor init
    // dict (it stays null); attach the DataTransfer explicitly.
    Object.defineProperty(evt, "clipboardData", { value: dt });
    const notCanceled = input.dispatchEvent(evt);
    return !notCanceled; // true = preventDefault() was called
  }, text);
}

async function gotoEdit(page: Page): Promise<void> {
  await page.goto(`${APP_BASE}/user/links-ics/${LINK_ID}/edit`, {
    waitUntil: "domcontentloaded",
    timeout: 60_000,
  });
  await expect(page.locator('input[name="location"]')).toBeVisible({
    timeout: 30_000,
  });
}

// The app registers a service worker that handles same-origin fetches
// (like /user/geo/suggest); SW-served requests bypass page.route entirely,
// so the autocomplete stub would never be hit and live suggestions leak in.
test.use({ serviceWorkers: "block" });

test.describe("event location map-link paste + autocomplete", () => {
  // The demo-login helper backs off up to 3×15s on 429s (throttle shared by
  // every concurrent e2e worker) and the edit page renders over the distant
  // RDS — under the full parallel validation battery the beforeEach alone can
  // blow the 60s default test budget. Give each test generous headroom.
  test.setTimeout(180_000);
  test.beforeAll(() => {
    seed();
  });

  test.beforeEach(async ({ page }) => {
    await stubNominatim(page);
    await loginAsDemo(page);
    await gotoEdit(page);
  });

  test("/maps/place/<name>/@lat,lng keeps the place name and appends the address", async ({
    page,
  }) => {
    const intercepted = await pasteIntoLocation(
      page,
      "https://www.google.com/maps/place/Eiffel+Tower/@48.8584,2.2945,17z/data=abc",
    );
    expect(intercepted).toBe(true);
    // Reverse geocode resolves; display name starts with the place name so
    // the whole readable address replaces the bare name.
    await expect(page.locator('input[name="location"]')).toHaveValue(
      REVERSE_PARIS,
      { timeout: 10_000 },
    );
    expect(reverseCalls.length).toBeGreaterThan(0);
    expect(reverseCalls[0]).toContain("lat=48.8584");
    expect(reverseCalls[0]).toContain("lon=2.2945");
  });

  test("?q=lat,lng (literal comma) fills the reverse-geocoded address", async ({
    page,
  }) => {
    const intercepted = await pasteIntoLocation(
      page,
      "https://maps.google.com/?q=40.748817,-73.985428",
    );
    expect(intercepted).toBe(true);
    await expect(page.locator('input[name="location"]')).toHaveValue(
      REVERSE_NYC,
      { timeout: 10_000 },
    );
    expect(reverseCalls[0]).toContain("lat=40.748817");
    expect(reverseCalls[0]).toContain("lon=-73.985428");
  });

  test("?q=lat%2Clng (%2C-encoded comma) parses like the literal form", async ({
    page,
  }) => {
    const intercepted = await pasteIntoLocation(
      page,
      "https://maps.google.com/?q=40.748817%2C-73.985428",
    );
    expect(intercepted).toBe(true);
    await expect(page.locator('input[name="location"]')).toHaveValue(
      REVERSE_NYC,
      { timeout: 10_000 },
    );
    expect(reverseCalls[0]).toContain("lat=40.748817");
    expect(reverseCalls[0]).toContain("lon=-73.985428");
  });

  test("plain-text pastes fall through to the browser default", async ({
    page,
  }) => {
    const input = page.locator('input[name="location"]');
    await input.fill("existing venue");
    const intercepted = await pasteIntoLocation(
      page,
      "123 Main Street, Springfield",
    );
    // The handler must NOT preventDefault (default paste behavior) and must
    // not touch the model — the value stays exactly what was typed.
    expect(intercepted).toBe(false);
    await page.waitForTimeout(500);
    await expect(input).toHaveValue("existing venue");
    expect(reverseCalls.length).toBe(0);
  });

  test("unrecognized short links keep the raw text so nothing is lost", async ({
    page,
  }) => {
    // Short links (maps.app.goo.gl) can't be expanded client-side: the
    // handler must NOT preventDefault so the browser's default paste keeps
    // the raw text, and must not clobber the model with anything else.
    const input = page.locator('input[name="location"]');
    const shortLink = "https://maps.app.goo.gl/AbCdEf123";
    const intercepted = await pasteIntoLocation(page, shortLink);
    expect(intercepted).toBe(false);
    await page.waitForTimeout(500);
    // Synthetic pastes are untrusted so the browser performs no insertion —
    // the key assertion is the handler left the event and the value alone.
    await expect(input).toHaveValue("");
    // No coordinates could be extracted, so no reverse geocode fires.
    expect(reverseCalls.length).toBe(0);
  });

  test("autocomplete: typing shows suggestions, choosing fills, form submits location", async ({
    page,
  }) => {
    const input = page.locator('input[name="location"]');
    // Type (not fill) so the @input handler's debounce fires like real typing.
    await input.click();
    await input.pressSequentially("Central Park", { delay: 40 });

    const first = page
      .locator(".mpp-suggest-item")
      .filter({ hasText: SUGGESTIONS[0].label });
    await expect(first).toBeVisible({ timeout: 10_000 });
    expect(searchCalls.length).toBeGreaterThan(0);
    expect(searchCalls[searchCalls.length - 1]).toContain(
      encodeURIComponent("Central Park"),
    );

    await first.click();
    await expect(input).toHaveValue(SUGGESTIONS[0].label);
    await expect(page.locator(".mpp-suggest-item")).toHaveCount(0);

    // Submit the whole edit form and assert the PUT carries name=location
    // with the chosen readable address (slow distant-RDS first write — see
    // repo memory e2e-editor-create-cold-rds-latency.md).
    const updateReq = page.waitForRequest(
      (r) =>
        r.url().includes(`/user/links-ics/${LINK_ID}`) &&
        r.method() === "POST",
      { timeout: 60_000 },
    );
    await page
      .getByRole("button", { name: /Save changes/i })
      .click({ noWaitAfter: true });
    const req = await updateReq;
    const body = req.postData() || "";
    const params = new URLSearchParams(body);
    expect(params.get("_method")).toBe("PUT");
    expect(params.get("location")).toBe(SUGGESTIONS[0].label);
  });
});
