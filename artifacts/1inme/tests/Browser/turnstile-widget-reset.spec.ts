import { expect, test } from "@playwright/test";

// Guards the Turnstile ↔ auth-ajax interplay for Task #6704 (invisible
// Turnstile captcha on sign-up & OTP):
//
//   1. Turnstile tokens are single-use, so after an AJAX submit that does NOT
//      navigate away, auth-ajax.js must reset the widget so the next attempt
//      carries a fresh token.
//   2. Auth pages render SEVERAL forms each carrying their own .cf-turnstile
//      (email OTP, WhatsApp sign-up, …). A bare `turnstile.reset()` only
//      resets the FIRST widget on the page — the submitted form's spent token
//      would stay in place and every retry would fail until a full refresh.
//      So the reset must target the widget(s) INSIDE the submitted form.
//   3. Default state: with Turnstile unconfigured, no widget markup and no
//      Cloudflare script may appear on the auth pages.
//
// The feature stays OFF in this run (shared-RDS safe: no settings flipped).
// The reset path in auth-ajax.js is exercised by injecting a stub
// `window.turnstile` plus widget containers into two live data-ajax forms and
// driving a failing AJAX submit on the SECOND one.

const CONSENT_COOKIE = "1inme_cookie_consent";

test.beforeEach(async ({ context, baseURL }) => {
  const origin = new URL(baseURL ?? "http://127.0.0.1").origin;
  await context.addCookies([
    { name: CONSENT_COOKIE, value: "accepted", url: origin },
  ]);
});

test.describe("Turnstile widget — disabled default & per-form AJAX reset", () => {
  test("unconfigured: login page ships no widget and no Cloudflare script", async ({ page }) => {
    await page.goto("/login");
    await expect(page.locator("form").first()).toBeVisible();
    expect(await page.locator(".cf-turnstile").count()).toBe(0);
    expect(
      await page.locator('script[src*="challenges.cloudflare.com"]').count(),
    ).toBe(0);
  });

  test("failed AJAX submit resets ONLY the submitted form's widget", async ({ page }) => {
    await page.goto("/login");

    // The login page renders several data-ajax forms wired at load time
    // (password → login.submit, email OTP / WhatsApp → send-otp). Widgets are
    // injected into TWO different forms; a failing submit on the send-otp
    // form must reset only ITS widget (a bare turnstile.reset() would grab
    // the page's first widget — the password form's).
    expect(await page.locator("form[data-ajax]").count()).toBeGreaterThan(1);

    await page.evaluate(() => {
      const w = window as any;
      // Stub the Cloudflare API: record what reset() is called with.
      w.__tsResets = [];
      w.turnstile = {
        reset(arg: any) {
          w.__tsResets.push(
            arg instanceof Element ? arg.getAttribute("data-marker") : String(arg),
          );
        },
      };

      const forms = Array.from(
        document.querySelectorAll<HTMLFormElement>("form[data-ajax]"),
      );
      const otpForm = forms.find((f) => f.action.includes("send-otp"))!;
      const otherForm = forms.find((f) => f !== otpForm)!;

      const mkWidget = (marker: string) => {
        const el = document.createElement("div");
        el.className = "cf-turnstile";
        el.setAttribute("data-marker", marker);
        return el;
      };
      otherForm.appendChild(mkWidget("widget-A")); // first widget in the DOM
      otpForm.appendChild(mkWidget("widget-B"));

      // Invalid identifier ⇒ the server answers with a non-redirect JSON
      // error (ok=false) — exactly the retry path that needs a fresh token.
      // An EMPTY identifier fails `required` validation server-side with a
      // guaranteed 422 — a malformed-but-present value can still be accepted
      // by lenient identifier handling and redirect to the verify page.
      const id = otpForm.querySelector<HTMLInputElement>('input[name="identifier"]');
      if (id) { id.value = ""; }
      // The form is visually hidden (Alpine x-show), so native constraint
      // validation (required / type=email) would silently swallow
      // requestSubmit(); the SERVER's validation failure is what we want.
      otpForm.noValidate = true;
      (window as any).__otpForm = otpForm;
    });

    // Submit the OTP form (requestSubmit fires the wired submit handler even
    // while Alpine keeps the form visually hidden) and await the response.
    const [resp] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes("send-otp") && r.request().method() === "POST",
      ),
      page.evaluate(() => (window as any).__otpForm.requestSubmit()),
    ]);
    expect(resp.status()).toBe(422);

    // The reset must have hit the OTP form's widget — and ONLY that one.
    await expect
      .poll(async () => page.evaluate(() => (window as any).__tsResets))
      .toEqual(["widget-B"]);
  });
});
