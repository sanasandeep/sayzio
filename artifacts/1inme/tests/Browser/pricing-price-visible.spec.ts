import { expect, test, type Page } from "@playwright/test";

// Regression guard for the pricing page "blank price" class of bug (task:
// "Prevent blank prices from reappearing when a new Alpine component is added").
//
// The public /pricing page renders every plan card's headline price through an
// Alpine `x-text` binding (plans.blade.php) that lives on a section-level
// `x-data` object with a bunch of helper methods (`money`, `perMonth`, `intro`,
// `priceSize`, ...). Alpine inlines each `x-data` object / expression onto a
// SINGLE line before evaluating it, so a `//` line comment anywhere inside that
// object silently comments out the rest of the expression — the whole component
// fails to evaluate, `money()`/`perMonth()` become undefined, and every price
// `x-text` renders the missing-price fallback ("—") or an empty string. The
// only visible symptom is a blank/dash price; there is NO server error and NO
// console error until someone opens DevTools and sees "Alpine Expression Error".
//
// A future edit that adds a new Alpine component (or a stray `//` comment) to
// this page would reintroduce the same silent breakage. This headless spec loads
// the real /pricing page and pins the invariant:
//   1. Alpine evaluated the pricing component without an expression error, and
//   2. every plan card shows a real currency amount (a "$" / "₹" figure), not a
//      blank or the "—" missing-price fallback,
//   3. on BOTH the Monthly and Annual cycle (the toggle re-runs the same helper
//      methods, so a broken component blanks annual prices too).
//
// Because the whole page's prices go through the one Alpine component, this
// single spec catches the entire "// comment killed Alpine" regression family.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the sibling home/marketing specs). /pricing is warmed by the runner.

const CONSENT_COOKIE = "1inme_cookie_consent";

// A rendered plan price must be a real currency amount: a currency symbol
// ($, ₹, €, £) immediately followed by a digit (e.g. "$9.00", "₹499.00",
// "$0.00" for the free tier). This intentionally does NOT match the em-dash
// "—" missing-price fallback nor an empty string — the exact blank-price
// signatures this test exists to catch.
const PRICE_RE = /[$₹€£]\s?\d/u;

/** The em-dash the JS `money()` helper falls back to when plan data is missing. */
const MISSING_PRICE = "—";

/**
 * Load /pricing with cookie consent already given (so the bottom-pinned consent
 * banner can't intercept the cycle toggle) and wait until Alpine has booted and
 * at least one plan-card price element exists.
 */
async function gotoPricing(page: Page): Promise<void> {
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
  await page.goto("/pricing", { waitUntil: "domcontentloaded" });
  // The plan cards live in a horizontal rail; the headline price is the single
  // `.price-num` inside each `.plan-price` block.
  await page
    .locator(".plan-card .plan-price .price-num")
    .first()
    .waitFor({ state: "attached" });
  // Alpine drives the reactive prices + the cycle toggle; wait until it's on the
  // page before we interact so the toggle click actually re-renders.
  await page.waitForFunction(() => Boolean((window as unknown as { Alpine?: unknown }).Alpine));
}

/**
 * Click the Monthly / Annual segmented toggle and wait for Alpine's reactive
 * re-render to land. The sliding knob's transform class (`translate-x-full` on
 * annual, `translate-x-0` on monthly) is bound to the same `cycle` state that
 * drives the prices, so once the knob reflects the target cycle the price
 * `x-text` bindings have re-run too.
 */
async function selectCycle(page: Page, cycle: "monthly" | "annual"): Promise<void> {
  const label = cycle === "annual" ? /Annual/ : /^Monthly$/;
  await page.getByRole("button", { name: label }).click();
  const knobClass = cycle === "annual" ? "translate-x-full" : "translate-x-0";
  await page.locator(`.cycle-knob.${knobClass}`).first().waitFor({ state: "attached" });
}

/**
 * Assert every plan-card headline price is a real, non-blank currency amount.
 * A blank or "—" here is the exact symptom of a broken pricing Alpine component.
 */
async function assertAllPricesValid(page: Page, cycle: string): Promise<void> {
  const priceEls = page.locator(".plan-card .plan-price .price-num");
  const count = await priceEls.count();
  expect(
    count,
    "expected at least one plan-card price element on /pricing",
  ).toBeGreaterThan(0);

  const texts = await priceEls.allInnerTexts();
  texts.forEach((raw, i) => {
    const text = (raw ?? "").trim();
    expect(
      text.length,
      `plan card #${i} price is blank on the ${cycle} cycle (Alpine likely failed to render)`,
    ).toBeGreaterThan(0);
    expect(
      text,
      `plan card #${i} price is the missing-price fallback "${MISSING_PRICE}" on the ${cycle} cycle`,
    ).not.toBe(MISSING_PRICE);
    expect(
      text,
      `plan card #${i} price "${text}" is not a currency amount on the ${cycle} cycle`,
    ).toMatch(PRICE_RE);
  });
}

test.describe("pricing page price rendering", () => {
  // Distant RDS makes the first paint slow even against a warmed server.
  let alpineErrors: string[];

  test.beforeEach(async ({ page }) => {
    test.setTimeout(60_000);
    alpineErrors = [];
    // Alpine reports a broken expression via `console.warn("Alpine Expression
    // Error: ...")` and then re-throws (surfacing as a pageerror). Capture both
    // signatures so the "// comment silently killed Alpine" regression fails the
    // test instead of only showing up as a blank price.
    page.on("console", (msg) => {
      const text = msg.text();
      if (/alpine expression error/i.test(text)) {
        alpineErrors.push(`[console.${msg.type()}] ${text}`);
      }
    });
    page.on("pageerror", (err) => {
      const msg = `${err.name}: ${err.message}`;
      if (/alpine|is not defined|is not a function|unexpected|syntaxerror/i.test(msg)) {
        alpineErrors.push(`[pageerror] ${msg}`);
      }
    });
    await gotoPricing(page);
  });

  test("monthly cycle: every plan card shows a real price and Alpine did not error", async ({
    page,
  }) => {
    await selectCycle(page, "monthly");
    await assertAllPricesValid(page, "monthly");
    expect(
      alpineErrors,
      `Alpine expression error(s) on /pricing (monthly):\n${alpineErrors.join("\n")}`,
    ).toEqual([]);
  });

  test("annual cycle: toggling to Annual keeps every plan card price non-blank", async ({
    page,
  }) => {
    await selectCycle(page, "annual");
    await assertAllPricesValid(page, "annual");
    expect(
      alpineErrors,
      `Alpine expression error(s) on /pricing (annual):\n${alpineErrors.join("\n")}`,
    ).toEqual([]);
  });
});
