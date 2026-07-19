import type { Page } from "@playwright/test";

/**
 * Authenticate a Playwright page as the non-prod demo account
 * (`AuthController::demoLogin` → the `DEMO_LOGIN_EMAIL` user).
 *
 * Uses browser-side fetch with redirect:'manual' so the session cookie from
 * the 302 response is captured without following the full redirect chain:
 *
 *   /user/demo-login  →302→  /user/dashboard  →302→  /user/onboarding/whatsapp
 *
 * ctx.request.post (even with maxRedirects:0) either times out (30 s) because
 * /user/onboarding/whatsapp makes external WhatsApp API calls in the test env,
 * OR throws "Max redirect count exceeded" (Playwright ≥ 1.40 treats maxRedirects:0
 * as "throw on the first 302" rather than "return the 302 response").
 *
 * Browser-native fetch with redirect:'manual' avoids both problems: the 302
 * is received, the Set-Cookie header is honoured by the browser, and the
 * session is live in the context's cookie jar — all without following the
 * redirect chain further.
 *
 * Rate-limit note: demo-login is throttled at 5 req/min (shared across all
 * concurrent e2e workers on the same host). The function retries up to 3 times
 * with 15-second back-off when it receives a 429, which lets the rate-limit
 * window slide before the next attempt.
 */
export async function loginAsDemo(page: Page): Promise<void> {
  // Navigate to the login page so the browser context has a valid origin and
  // the CSRF token meta tag is available in the DOM.
  await page.goto("/user/login", { waitUntil: "domcontentloaded", timeout: 30_000 });

  let status = -2;
  for (let attempt = 0; attempt < 4; attempt++) {
    if (attempt > 0) {
      // Back off before retrying so the 1-minute rate-limit window can slide.
      await page.waitForTimeout(15_000);
    }

    status = await page.evaluate(async (): Promise<number> => {
      const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
      if (!meta) return -1;
      const res = await fetch("/user/demo-login", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ _token: meta.content }).toString(),
        redirect: "manual",
      });
      // With redirect:'manual' the browser returns an opaque-redirect (status 0).
      // The Set-Cookie header from the 302 is still honoured: the session is live
      // in the context's cookie jar even though the visible status is 0.
      return res.status;
    });

    if (status === -1) {
      throw new Error(
        "loginAsDemo: CSRF meta tag not found on /user/login — server may not have started yet",
      );
    }
    // 0 = opaque redirect (success). Anything else that isn't 429: break and
    // let the test proceed (unexpected status is not worth looping over).
    if (status !== 429) break;
  }

  if (status === 429) {
    throw new Error(
      "loginAsDemo: demo-login is rate-limited (429) after multiple retries; " +
        "too many concurrent test environments are hitting the shared throttle.",
    );
  }
}
