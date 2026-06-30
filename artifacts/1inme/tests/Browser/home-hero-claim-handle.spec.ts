import { expect, test, type Locator, type Page } from "@playwright/test";

// Guards the home hero's "claim your link" → in-modal signup handoff (task:
// "Make sure a claimed handle survives the in-modal signup, not just the
// standalone page"). The most common claim entry point is the homepage hero:
// the visitor types a handle into the glass pill, submits it, and the SAME
// `open-auth` CustomEvent the other hero CTAs use is dispatched — now carrying
// `detail.handle`. The header's Alpine x-data reads that handle into `authHandle`
// and opens the register modal, whose hidden `<input name="desired_handle"
// :value="authHandle">` then rides along in the register POST so
// AuthController::applyClaimedHandle() can reserve it after sign-up.
//
// That browser-side wiring (hero JS → open-auth event → header Alpine → modal
// hidden field) is what an earlier feature test could NOT cover — it only pinned
// the controller end and the standalone /register page's `?handle=` field. A
// regression in the event detail, the Alpine listener, or the modal binding
// would silently drop the handle for every hero-originated signup. These tests
// pin that exact chain.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral :5000 server, since localhost:80 hits the Express api-server).

const CONSENT_COOKIE = "1inme_cookie_consent";

/**
 * Load the home page with consent already given (so the bottom-pinned cookie
 * banner can't intercept clicks) and wait for Alpine — the open-auth event
 * handling and the modal's `desired_handle` binding are both Alpine-driven.
 */
async function gotoHome(page: Page): Promise<void> {
  await page.context().addCookies([
    {
      name: CONSENT_COOKIE,
      value: encodeURIComponent(
        JSON.stringify({
          v: 1,
          t: Date.now(),
          c: { analytics: false, marketing: false, functional: false },
        }),
      ),
      domain: "localhost",
      path: "/",
    },
  ]);
  // The home page is heavy (maps, embeds); don't wait for full network idle.
  await page.goto("/", { waitUntil: "domcontentloaded" });
  await page.waitForFunction(
    () => !!(window as unknown as { Alpine?: unknown }).Alpine,
  );
  // The hero claim control lives in the copy column; make sure it's mounted.
  await page.locator("#zio-claim-input").waitFor({ state: "attached" });
}

/** The auth popup, scoped by its dialog aria-label. */
function modal(page: Page): Locator {
  return page.getByRole("dialog", { name: "Sign in or create an account" });
}

/**
 * Type a handle into the hero "claim your link" pill and submit it. Passing an
 * empty string submits with no handle (the empty-claim path).
 */
async function submitClaim(page: Page, handle: string): Promise<void> {
  const input = page.locator("#zio-claim-input");
  await input.fill(handle);
  // Submit via the pill's own button so the real `onsubmit=zioClaimSubmit`
  // handler fires (it preventDefaults + dispatches the open-auth event). The
  // button sits inside the .zio-claim form.
  await page.locator(".zio-claim-btn").click();
  await expect(modal(page)).toBeVisible();
}

/**
 * The register form's hidden desired_handle field — scoped INSIDE the modal so
 * it's never confused with the hero pill input (which also carries
 * name="desired_handle"). This is the input that actually rides along in the
 * register POST.
 */
function modalDesiredHandle(page: Page): Locator {
  return modal(page).locator('input[name="desired_handle"]');
}

test.describe("home hero claim-your-link → in-modal signup handoff", () => {
  test.beforeEach(async ({ page }) => {
    // Distant RDS makes the first paint slow; give each test headroom.
    test.setTimeout(60_000);
  });

  test("typing a handle opens the register modal and carries it into the hidden field + banner", async ({
    page,
  }) => {
    await gotoHome(page);

    await submitClaim(page, "janedoe");

    // The claim entry always lands on the register tab (detail.tab='register').
    // The register form's name field is only shown on that tab.
    await expect(modal(page).locator('input[name="name"]')).toBeVisible();

    // The hidden field that rides along in the POST holds the typed handle.
    await expect(modalDesiredHandle(page)).toHaveValue("janedoe");

    // The "Claiming @handle" banner confirms it to the visitor.
    const banner = modal(page).locator("text=Claiming");
    await expect(banner).toBeVisible();
    await expect(banner).toContainText("@janedoe");
  });

  test("the handle is normalized (lowercased, leading @ stripped, trimmed) before it reaches the modal", async ({
    page,
  }) => {
    await gotoHome(page);

    // zioClaimSubmit lowercases, strips a leading @, and trims — so an "ugly"
    // input must arrive at the modal already canonicalised.
    await submitClaim(page, "  @JaneDoe ");

    await expect(modalDesiredHandle(page)).toHaveValue("janedoe");
    await expect(modal(page).locator("text=Claiming")).toContainText(
      "@janedoe",
    );
  });

  test("an empty claim opens registration normally with no handle and no banner", async ({
    page,
  }) => {
    await gotoHome(page);

    await submitClaim(page, "");

    // Still opens the register tab…
    await expect(modal(page).locator('input[name="name"]')).toBeVisible();
    // …but with an empty desired_handle and the "Claiming" banner hidden.
    await expect(modalDesiredHandle(page)).toHaveValue("");
    await expect(modal(page).locator("text=Claiming")).toBeHidden();
  });

  test("the claimed handle is actually sent in the register POST body", async ({
    page,
  }) => {
    await gotoHome(page);

    await submitClaim(page, "creator_99");
    await expect(modalDesiredHandle(page)).toHaveValue("creator_99");

    // Fill the rest of the register form so the browser will serialize a real
    // submit, then intercept the POST and read back the form body. We abort the
    // request (status 0) so nothing is actually persisted and no navigation
    // happens — we only care that the browser put desired_handle on the wire.
    await modal(page).locator('input[name="name"]').fill("Creator Ninety-Nine");
    await modal(page)
      .locator('input[name="email"]')
      .fill("creator99@example.com");

    let postBody: string | null = null;
    await page.route("**/user/register", async (route) => {
      const req = route.request();
      if (req.method() === "POST") {
        postBody = req.postData();
        await route.abort();
        return;
      }
      await route.fallback();
    });

    await modal(page)
      .getByRole("button", { name: "Create account" })
      .click();

    await expect
      .poll(() => postBody, { timeout: 15_000 })
      .not.toBeNull();

    const params = new URLSearchParams(postBody as unknown as string);
    expect(params.get("desired_handle")).toBe("creator_99");
    // Sanity: the rest of the form is present too, so we're reading the real
    // register submission (not some other POST).
    expect(params.get("name")).toBe("Creator Ninety-Nine");
    expect(params.get("email")).toBe("creator99@example.com");
  });
});
