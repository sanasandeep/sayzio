import type { Page } from "@playwright/test";

/**
 * Authenticate a Playwright page as the non-prod demo account
 * (`AuthController::demoLogin` → the `DEMO_LOGIN_EMAIL` user).
 *
 * We do NOT depend on a `form[action$="/user/demo-login"]` element being present
 * in the login markup: whether that demo button renders is environment/data
 * driven, so relying on it makes the login step silently brittle — in an env
 * where it isn't rendered every spec sharing this helper fails at setup with a
 * misleading "demo-login form not found", which looks like the feature under
 * test broke when it's really just sign-in.
 *
 * Instead we replicate exactly what the demo-login endpoint needs — a
 * same-session CSRF token POSTed to `/user/demo-login` — by reading the `_token`
 * the login page's own forms already carry (`input[name="_token"]`) and
 * submitting a synthesized form. This is the identical mechanism
 * run-validation.sh's warm step uses to authenticate, so it works regardless of
 * which login buttons are rendered.
 *
 * We wait only for the demo-login POST itself: Laravel sets the authenticated
 * session cookie on its 302 response, so the context is logged in as soon as
 * that returns. We deliberately do NOT wait for the redirect target to render
 * (the dashboard is a heavy ~20-30s cold Blade render over the distant RDS, and
 * most specs navigate straight to their own page anyway).
 */
export async function loginAsDemo(page: Page): Promise<void> {
  await page.goto("/user/login");
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/user/demo-login") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    page.evaluate(() => {
      const tokenInput = document.querySelector<HTMLInputElement>(
        'input[name="_token"]',
      );
      if (!tokenInput || !tokenInput.value) {
        throw new Error("CSRF _token not found on /user/login");
      }
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "/user/demo-login";
      const token = document.createElement("input");
      token.type = "hidden";
      token.name = "_token";
      token.value = tokenInput.value;
      form.appendChild(token);
      document.body.appendChild(form);
      form.submit();
    }),
  ]);
}
