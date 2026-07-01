import { expect, test, type Page } from "@playwright/test";

// Regression guard for the LOGGED-OUT visitor call-to-action buttons in the
// DESKTOP Sayzio public header (task: "Make sure the desktop header's
// logged-out Login/Sign-up buttons cannot silently break").
//
// Three sibling specs already guard the other three account surfaces of the
// same header `@auth` block:
//   - header-account-menu.spec.ts        — desktop logged-IN account dropdown
//   - header-mobile-account-menu.spec.ts — mobile drawer logged-IN controls
//   - header-mobile-logged-out-cta.spec.ts — mobile drawer logged-OUT CTAs
// The one remaining unguarded surface is the DESKTOP header's logged-OUT
// Login/Register CTAs — the `@else` branch of that block (header.blade.php
// ~lines 274-281), living in the `hidden lg:flex` desktop CTA cluster. Per
// memory note user-sidebar-dual-nav, these parallel-to-mobile nav surfaces tend
// to drift, so a markup/CSS change could drop or break the desktop sign-in
// entry points without anyone noticing.
//
// The desktop logged-out branch has TWO shapes, gated by the page's `$useModal`:
//   - useModal=true (the home page `/`): the CTAs are BUTTONS that open the
//     two-panel auth modal (Login -> login tab, Register -> register tab).
//   - useModal=false (every other marketing page, e.g. `/contact`): the CTAs are
//     plain LINKS to the login / register pages.
// This spec pins both shapes on a desktop viewport with no session.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling home/dashboard specs). `/` and `/contact` are both warmed by
// the runner.

// Comfortably above Tailwind's `lg` (1024px) breakpoint so the desktop CTA
// cluster (`hidden lg:flex`) is visible and the mobile hamburger drawer is
// hidden.
const DESKTOP_VIEWPORT = { width: 1280, height: 800 };

const CONSENT_COOKIE = "1inme_cookie_consent";

/**
 * Load a header page on a desktop viewport with consent already given, so the
 * bottom-pinned cookie banner can't intercept clicks on the CTAs. Waits for the
 * theme toggle in the desktop CTA cluster (all header interactions are
 * Alpine-driven).
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
    // Anchor on the DESKTOP CTA cluster's theme toggle. On a desktop viewport the
    // mobile cluster (and its "Toggle menu" button) is `lg:hidden` (display:none)
    // and thus absent from the accessibility tree, so a role query can't see it;
    // the desktop theme toggle sits right next to the Login/Register CTAs and is
    // the only visible "Switch to …" control, so its visibility confirms the
    // desktop header cluster (and its @else branch) has painted and Alpine booted.
    await page
        .getByRole("button", { name: /Switch to (dark|light) mode/ })
        .waitFor({ state: "visible", timeout: 120_000 });
}

/** The auth popup, scoped by its dialog aria-label. */
function modal(page: Page) {
    return page.getByRole("dialog", { name: "Sign in or create an account" });
}

test.describe("public header desktop logged-out CTAs", () => {
    // Cold marketing renders over the distant RDS can push past the default
    // budget; give the suite real headroom (mirrors sibling specs).
    test.describe.configure({ timeout: 180_000 });

    test.beforeEach(async ({ page }) => {
        await page.setViewportSize(DESKTOP_VIEWPORT);
    });

    // --- useModal=false page: plain links to the login / register pages. ---
    test("on a non-modal page (/contact) the desktop header shows Login/Register links to the auth pages", async ({
        page,
    }) => {
        await gotoWithConsent(page, "/contact");

        // The desktop CTAs are the only visible controls with these names (the
        // mobile drawer is display:none, so excluded from the a11y tree).
        const login = page.getByRole("link", { name: "Login", exact: true });
        const register = page.getByRole("link", {
            name: "Register",
            exact: true,
        });

        await expect(
            login,
            "the desktop header must show a Login link when logged out",
        ).toBeVisible();
        await expect(
            register,
            "the desktop header must show a Register link when logged out",
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
    test("on the modal home page the desktop Login button opens the popup on the login tab", async ({
        page,
    }) => {
        await gotoWithConsent(page, "/");

        // The desktop CTA is the only visible button with this name (the modal
        // tabs are display:none until the modal opens, so excluded from the a11y
        // tree).
        await page.getByRole("button", { name: "Login", exact: true }).click();

        const dialog = modal(page);
        await expect(
            dialog,
            "clicking Login in the desktop header must open the auth modal",
        ).toBeVisible();
        // The login form (its email/identifier field) is shown for the Login tab.
        await expect(
            dialog.locator('input[name="identifier"]').first(),
            "the modal must open on the login tab",
        ).toBeVisible();
    });

    test("on the modal home page the desktop Register button opens the popup on the register tab", async ({
        page,
    }) => {
        await gotoWithConsent(page, "/");

        await page
            .getByRole("button", { name: "Register", exact: true })
            .click();

        const dialog = modal(page);
        await expect(
            dialog,
            "clicking Register in the desktop header must open the auth modal",
        ).toBeVisible();
        // The register form's name field is only shown on the register tab.
        await expect(
            dialog.locator('input[name="name"]'),
            "the modal must open on the register tab",
        ).toBeVisible();
    });
});
