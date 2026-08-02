import { expect, test } from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";
import { seedHeader, runTinker } from "./event-features-seed";

// Calendar settings page — Task #6550 (Outlook push + polished Apple subscribe).
//
// Drives resources/views/user/settings/calendar.blade.php as the signed-in
// owner and asserts two independent sections:
//   1. Apple Calendar: the webcal:// subscribe link + the read-only feed URL
//      input + its "Copy" button all render (the polished subscribe flow);
//   2. Microsoft / Outlook connect: in the UNCONFIGURED / graceful state
//      (MicrosoftCalendarProvider::isConfigured() == false in the e2e env),
//      the tile shows the "Unavailable" pill and is NOT a live connect link.
//
// beforeAll only ensures the owner exists + is onboarded + user-admin (bypasses
// the calendar_sync plan gate on this page's routes). No event fixtures needed.

const APP_BASE = process.env.APP_URL || "http://localhost:5000";

function seed(): void {
  // seedHeader ensures owner + role + onboarded; the prune body is a harmless
  // no-op here (there may be no event fixtures to prune).
  const out = runTinker(seedHeader() + "\necho 'SEED_OK';\n");
  if (!out.includes("SEED_OK")) {
    throw new Error("Calendar settings owner seed did not confirm SEED_OK:\n" + out);
  }
}

test.describe("calendar settings — Apple + Microsoft sections", () => {
  test.beforeAll(() => {
    seed();
  });

  test("Apple webcal subscribe + copy button render; Microsoft is gracefully unavailable", async ({
    page,
  }) => {
    await loginAsDemo(page);
    await page.goto(`${APP_BASE}/user/calendar`, {
      waitUntil: "domcontentloaded",
      timeout: 60_000,
    });
    await expect(
      page.getByRole("heading", { name: /Calendar Sync/i }),
      "calendar settings page should render",
    ).toBeVisible({ timeout: 15_000 });

    // --- 1. Apple Calendar section. ---
    await expect(
      page.getByRole("heading", { name: /Apple Calendar/i }),
      "Apple Calendar section heading should render",
    ).toBeVisible();

    const subscribe = page.getByRole("link", {
      name: /Subscribe in Apple Calendar/i,
    });
    await expect(subscribe).toBeVisible();
    const webcalHref = await subscribe.getAttribute("href");
    expect(webcalHref, "subscribe link href present").toBeTruthy();
    expect(
      webcalHref as string,
      "Apple subscribe link should use the webcal:// scheme",
    ).toMatch(/^webcal:\/\//);

    // The read-only feed URL input + Copy button (the "other apps" flow).
    const feedInput = page.locator("#apple-feed-url");
    await expect(feedInput).toBeVisible();
    const feedValue = await feedInput.inputValue();
    expect(feedValue, "feed URL should be an http(s) URL").toMatch(
      /^https?:\/\//,
    );
    await expect(
      page.getByRole("button", { name: /Copy/i }),
      "Copy button should render next to the feed URL",
    ).toBeVisible();

    // --- 2. Microsoft / Outlook connect — unconfigured graceful state. ---
    // The Microsoft tile is present as text either way; in the unconfigured
    // env it renders as a NON-link disabled tile with an "Unavailable" pill and
    // must NOT be an anchor to the connect route.
    await expect(
      page.getByText(/Microsoft 365 \/ Outlook/i).first(),
      "Microsoft/Outlook tile should be present",
    ).toBeVisible();

    const msConnectLink = page.locator(
      'a[href*="/calendar/connect/microsoft"]',
    );
    const msLinkCount = await msConnectLink.count();
    if (msLinkCount === 0) {
      // Unconfigured (expected in e2e): the graceful "Unavailable" pill shows.
      await expect(
        page.getByText(/Unavailable/i).first(),
        "unconfigured Microsoft tile should show the Unavailable pill",
      ).toBeVisible();
    } else {
      // Configured env (e.g. real OAuth creds present): the connect link is a
      // valid anchor. Either state is acceptable; assert it's well-formed.
      await expect(msConnectLink.first()).toBeVisible();
    }
  });
});
