import type { Page } from "@playwright/test";

/**
 * Authenticate a Playwright page on the ADMIN guard as the non-prod demo admin
 * (`AuthController::demoLogin` on the admin side → `/admin/demo-login`).
 *
 * This is the admin counterpart to `./login-as-demo` and exists for the same
 * reason: we do NOT depend on a `form[action$="/admin/demo-login"]` element
 * being rendered in the admin login markup. Whether that demo button renders is
 * environment/data driven, so relying on it makes the login step silently
 * brittle — in a fresh/isolated env where it isn't rendered the spec fails at
 * setup with a misleading "admin demo-login form not found", which looks like
 * the feature under test broke when it's really just admin sign-in.
 *
 * Instead we replicate exactly what the demo-login endpoint needs — a
 * same-session CSRF token POSTed to `/admin/demo-login` — by reading the
 * `_token` the admin login page's own forms already carry
 * (`input[name="_token"]`, falling back to the `csrf-token` meta tag) and
 * submitting a synthesized form. This works regardless of which login buttons
 * are rendered.
 *
 * We wait only for the demo-login POST itself: Laravel sets the authenticated
 * session cookie on its 302 response, so the context is logged in as soon as
 * that returns. We deliberately do NOT wait for the redirect target to render
 * (the admin dashboard is a heavy cold Blade render over the distant RDS, and
 * specs navigate straight to their own page anyway).
 */
export async function loginAsDemoAdmin(page: Page): Promise<void> {
  await page.goto("/admin/login", { timeout: 120_000 });
  await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().endsWith("/admin/demo-login") &&
        r.request().method() === "POST",
      { timeout: 90_000 },
    ),
    page.evaluate(() => {
      const token =
        document.querySelector<HTMLInputElement>('input[name="_token"]')
          ?.value ??
        document
          .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
          ?.getAttribute("content") ??
        "";
      if (!token) {
        throw new Error("CSRF _token not found on /admin/login");
      }
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "/admin/demo-login";
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "_token";
      input.value = token;
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }),
  ]);
}
