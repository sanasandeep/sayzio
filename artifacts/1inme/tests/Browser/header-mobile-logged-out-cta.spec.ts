import { expect, test, type Page } from "@playwright/test";

// Regression guard for the LOGGED-OUT visitor call-to-action buttons inside the
// MOBILE hamburger drawer of the Sayzio public header (task: "Make sure the
// mobile menu's logged-out Login/Sign-up buttons cannot silently break").
//
// A sibling spec (header-mobile-account-menu.spec.ts) pins the LOGGED-IN branch
// of the same `@auth` block in header.blade.php (~lines 371-390). The `@else`
// branch of that block renders the visitor CTAs (Login / Register) and was still
// unguarded. Per memory note user-sidebar-dual-nav, the drawer is a
// parallel-to-desktop nav surface that tends to drift, so a markup/CSS change
// could drop or break these sign-in entry points on mobile without anyone
// noticing.
//
// The drawer's logged-out branch has TWO shapes, gated by the page's `$useModal`:
//   - useModal=true (the home page `/`): the CTAs are BUTTONS that open the
//     two-panel auth modal (Login -> login tab, Register -> register tab).
//   - useModal=false (every other marketing page, e.g. `/contact`): the CTAs are
//     plain LINKS to the login / register pages.
// This spec pins both shapes on a mobile viewport with no session.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling home/dashboard specs). `/` and `/contact` are both warmed by
// the runner.

// iPhone-ish portrait, comfortably below Tailwind's `lg` (1024px) breakpoint so
// the desktop nav is hidden and the hamburger drawer is the only nav surface.
const MOBILE_VIEWPORT = { width: 400, height: 850 };

const CONSENT_COOKIE = "1inme_cookie_consent";

/**
 * Load a header page on a mobile viewport with consent already given, so the
 * bottom-pinned cookie banner can't intercept taps on the menu or CTAs. Waits
 * for the hamburger toggle (all drawer interactions are Alpine-driven).
 */
async function gotoWithConsent(page: Page, path: string): Promise<void> {
    await page.context().addCookies([
        {
            name: CONSENT_COOKIE,
            value: encodeURIComponent(
                JSON.stringify({
                    v: 1,
                    t: Date.now(),
                    c: {
                        analytics: false,
                        marketing: false,
                        functional: false,
                    },
                }),
            ),
            domain: "localhost",
            path: "/",
        },
    ]);
    // These pages are heavy (maps, embeds); don't wait for full network idle.
    await page.goto(path, { waitUntil: "domcontentloaded", timeout: 120_000 });
    await page
        .getByRole("button", { name: "Toggle menu" })
        .waitFor({ state: "visible", timeout: 120_000 });
}

/** Open the hamburger drawer (the only nav entry point on mobile). */
async function openDrawer(page: Page): Promise<void> {
    const toggle = page.getByRole("button", { name: "Toggle menu" });
    await expect(
        toggle,
        "the mobile hamburger toggle must be visible on a small viewport",
    ).toBeVisible();
    await toggle.click();
}

/** The auth popup, scoped by its dialog aria-label. */
function modal(page: Page) {
    return page.getByRole("dialog", { name: "Sign in or create an account" });
}

test.describe("public header mobile logged-out CTAs", () => {
    // Cold marketing renders over the distant RDS can push past the default
    // budget; give the suite real headroom (mirrors sibling specs).
    test.describe.configure({ timeout: 180_000 });

    test.beforeEach(async ({ page }) => {
        await page.setViewportSize(MOBILE_VIEWPORT);
    });

    // --- useModal=false page: plain links to the login / register pages. ---
    test("on a non-modal page (/contact) the drawer shows Login/Register links to the auth pages", async ({
        page,
    }) => {
        await gotoWithConsent(page, "/contact");
        await openDrawer(page);

        // The drawer CTAs are the only visible controls with these names (the
        // desktop nav is display:none, so excluded from the a11y tree).
        const login = page.getByRole("link", { name: "Login", exact: true });
        const register = page.getByRole("link", {
            name: "Register",
            exact: true,
        });

        await expect(
            login,
            "the drawer must show a Login link when logged out",
        ).toBeVisible();
        await expect(
            register,
            "the drawer must show a Register link when logged out",
        ).toBeVisible();

        // The links point at the login / register pages (route('login.page') ->
        // /login, route('register.page') -> /register).
        await expect(
            login,
            "the Login link must point at the login page",
        ).toHaveAttribute("href", /\/login$/);
        await expect(
            register,
            "the Register link must point at the register page",
        ).toHaveAttribute("href", /\/register$/);

        // A non-modal page must NOT render the auth modal at all.
        await expect(
            modal(page),
            "a non-modal page must not carry the auth modal",
        ).toHaveCount(0);
    });

    // --- useModal=true page: buttons that open the two-panel auth modal. ---
    test("on the modal home page the drawer's Login button opens the popup on the login tab", async ({
        page,
    }) => {
        await gotoWithConsent(page, "/");
        await openDrawer(page);

        // The drawer CTA is the only visible button with this name (the modal tabs
        // are display:none until the modal opens, so excluded from the a11y tree).
        await page.getByRole("button", { name: "Login", exact: true }).click();

        const dialog = modal(page);
        await expect(
            dialog,
            "tapping Login in the drawer must open the auth modal",
        ).toBeVisible();
        // The login form (its email/identifier field) is shown for the Login tab.
        await expect(
            dialog.locator('input[name="identifier"]').first(),
            "the modal must open on the login tab",
        ).toBeVisible();
    });

    test("on the modal home page the drawer's Register button opens the popup on the register tab", async ({
        page,
    }) => {
        await gotoWithConsent(page, "/");
        await openDrawer(page);

        await page
            .getByRole("button", { name: "Register", exact: true })
            .click();

        const dialog = modal(page);
        await expect(
            dialog,
            "tapping Register in the drawer must open the auth modal",
        ).toBeVisible();
        // The register form's name field is only shown on the register tab.
        await expect(
            dialog.locator('input[name="name"]'),
            "the modal must open on the register tab",
        ).toBeVisible();
    });
});
