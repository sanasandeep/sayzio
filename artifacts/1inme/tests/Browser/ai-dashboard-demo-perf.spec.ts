import { expect, test, type Page } from "@playwright/test";

// Long-running-session performance / a11y guard for the "describe → AI arranges
// → dashboard updates" demo partial (task: "Verify the new AI Dashboard demo
// doesn't slow down page load on low-end devices").
//
// The animated demo (`common/partials/ai-dashboard-demo.blade.php`) injects a
// scoped <style>+<script> block into BOTH the home page (compact variant) and
// the public "See how it works" AI Dashboard page (`/ai-dashboard`, rich
// variant). It runs a typewriter effect (setInterval) plus an
// IntersectionObserver-gated scene cycle (setInterval every 6.2s) that advances
// through the 5 dashboard presets. It was verified by hand to render and respect
// prefers-reduced-motion, but there was no automated check that, over a
// long-running session, it doesn't:
//   - leak timers (each cycle spawning a setInterval it never clears → jank /
//     memory growth on low-end devices),
//   - throw console/JS errors, or
//   - regress accessibility (the root carries `aria-live="off"` so the constant
//     typewriter mutation never spams assistive tech).
//
// Strategy for the "no runaway timers" check (the crux): we can't modify the
// partial, so we instrument window.setInterval/clearInterval BEFORE the page
// scripts run and key on the demo's two distinctive delays (22ms typewriter,
// 6200ms cycle). Unrelated page intervals almost never use those exact delays,
// so the counts are demo-specific and low-flake. A leak in the cycle timer would
// show as >1 simultaneously-active 6200ms interval; a leak in the typewriter as
// >1 active 22ms interval. We also assert each delay was SEEN at least once so a
// future change to those constants fails this test (prompting an update) rather
// than silently passing with a stale filter that matches nothing.
//
// Runs against the Laravel app; baseURL comes from APP_URL (the runner points it
// at the ephemeral e2e server, since localhost:80 hits the Express api-server —
// see the other home/marketing specs).

const CONSENT_COOKIE = "1inme_cookie_consent";

// The demo's two distinctive setInterval delays, mirroring the partial:
//   - typeText():   setInterval(..., 22)     — per-character typewriter
//   - start():      setInterval(..., 6200)   — scene cycle
// Kept in sync with common/partials/ai-dashboard-demo.blade.php. The
// "seen >= 1" assertions below turn a drift in these constants into a test
// failure instead of a silent false pass.
const TYPE_DELAY_MS = 22;
const CYCLE_DELAY_MS = 6200;

// The two surfaces that embed the demo, and the variant each renders.
const DEMO_PAGES = [
  { label: "home (compact)", path: "/", variant: "compact" },
  { label: "ai-dashboard (rich)", path: "/ai-dashboard", variant: "rich" },
] as const;

type TimerTrack = {
  typeSeen: number;
  cycleSeen: number;
  maxActiveType: number;
  maxActiveCycle: number;
};

/**
 * Console-error noise filter. The home page pulls in maps, fonts and social
 * embeds that routinely fail to load in a headless/offline CI browser and log
 * as console "error" (and the odd network pageerror). Those are not the demo's
 * doing, so we ignore resource/network chatter and only fail on genuine
 * script-level errors — which is what a demo regression would produce.
 */
function isNoiseError(text: string): boolean {
  return /Failed to load resource|net::ERR|ERR_|favicon|Failed to fetch|Load failed|status of (4\d\d|5\d\d)|the server responded with|preload|Content Security Policy|third-party cookie/i.test(
    text,
  );
}

/**
 * Attach console-error + uncaught-exception collectors and the timer
 * instrumentation, then navigate to a demo page, scroll the demo into view, and
 * wait for it to initialise. Returns getters the tests assert against.
 *
 * The timer instrumentation is installed via addInitScript so it wraps
 * setInterval/clearInterval BEFORE the partial's IIFE runs.
 */
async function openDemoPage(
  page: Page,
  path: string,
  opts: { forceReduce?: boolean } = {},
): Promise<{ scriptErrors: string[] }> {
  const scriptErrors: string[] = [];
  page.on("console", (msg) => {
    if (msg.type() === "error" && !isNoiseError(msg.text())) {
      scriptErrors.push(`console.error: ${msg.text()}`);
    }
  });
  page.on("pageerror", (err) => {
    const text = err.message || String(err);
    if (!isNoiseError(text)) scriptErrors.push(`pageerror: ${text}`);
  });

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

  // Force the reduced-motion code path deterministically. Playwright's
  // `reducedMotion: "reduce"` emulation reliably flips the CSS @media query but
  // does NOT always surface to the page's own
  // `window.matchMedia('(prefers-reduced-motion: reduce)').matches` read that
  // the demo branches on at init — so we override matchMedia for that one query
  // before any page script runs. Other queries fall through to the real impl.
  if (opts.forceReduce) {
    await page.addInitScript(() => {
      const real = window.matchMedia.bind(window);
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (window as any).matchMedia = (query: string): MediaQueryList => {
        if (/prefers-reduced-motion:\s*reduce/i.test(query)) {
          return {
            matches: true,
            media: query,
            onchange: null,
            addListener() {},
            removeListener() {},
            addEventListener() {},
            removeEventListener() {},
            dispatchEvent() {
              return false;
            },
          } as unknown as MediaQueryList;
        }
        return real(query);
      };
    });
  }

  await page.addInitScript(
    ({ typeDelay, cycleDelay }) => {
      const w = window as unknown as {
        __aiddTimerTrack?: TimerTrack;
      } & Window;
      if (w.__aiddTimerTrack) return;
      const track: TimerTrack = {
        typeSeen: 0,
        cycleSeen: 0,
        maxActiveType: 0,
        maxActiveCycle: 0,
      };
      w.__aiddTimerTrack = track;
      // id -> delay, for the intervals we care about only.
      const active = new Map<number, number>();
      const origSet = window.setInterval.bind(window);
      const origClear = window.clearInterval.bind(window);
      const recount = () => {
        let t = 0;
        let c = 0;
        active.forEach((d) => {
          if (d === typeDelay) t++;
          else if (d === cycleDelay) c++;
        });
        if (t > track.maxActiveType) track.maxActiveType = t;
        if (c > track.maxActiveCycle) track.maxActiveCycle = c;
      };
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (window as any).setInterval = function (
        handler: TimerHandler,
        delay?: number,
        ...args: unknown[]
      ) {
        const id = origSet(handler, delay as number, ...args);
        if (delay === typeDelay || delay === cycleDelay) {
          active.set(id as unknown as number, delay);
          if (delay === typeDelay) track.typeSeen++;
          if (delay === cycleDelay) track.cycleSeen++;
          recount();
        }
        return id;
      };
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      (window as any).clearInterval = function (id?: number) {
        if (id != null) active.delete(id as unknown as number);
        return origClear(id as number);
      };
    },
    { typeDelay: TYPE_DELAY_MS, cycleDelay: CYCLE_DELAY_MS },
  );

  // Heavy pages (maps/embeds): don't wait for network idle.
  await page.goto(path, { waitUntil: "domcontentloaded" });

  const root = page.locator("[data-aidd]").first();
  await root.waitFor({ state: "attached" });
  // The IntersectionObserver only starts the cycle once the demo is on screen.
  await root.scrollIntoViewIfNeeded();
  await expect(root).toBeVisible();

  return { scriptErrors };
}

/** The 0-based index of the currently-active demo scene (-1 if none). */
async function activeSceneIndex(page: Page): Promise<number> {
  return page.evaluate(() => {
    const s = document.querySelector("[data-aidd-scene].is-active");
    return s ? Number(s.getAttribute("data-i")) : -1;
  });
}

async function readTimerTrack(page: Page): Promise<TimerTrack> {
  return page.evaluate(
    () =>
      (window as unknown as { __aiddTimerTrack: TimerTrack }).__aiddTimerTrack,
  );
}

// ---------------------------------------------------------------------------
// Motion (default) — the demo animates: it must cycle through scenes, keep its
// timers bounded across cycles, and stay error-free over a multi-cycle session.
// ---------------------------------------------------------------------------
test.describe("AI dashboard demo — animated session stays bounded", () => {
  // Explicitly opt out of reduced motion so the animated code path runs.
  test.use({ reducedMotion: "no-preference" });

  for (const { label, path, variant } of DEMO_PAGES) {
    test(`${label}: cycles without leaking timers or logging errors`, async ({
      page,
    }) => {
      // Distant RDS cold render + a deliberate multi-cycle observation window.
      test.setTimeout(120_000);

      const { scriptErrors } = await openDemoPage(page, path);

      // A11y: the live-updating console must NOT be an aria-live region, or the
      // typewriter would spam assistive tech every keystroke.
      await expect(page.locator("[data-aidd]").first()).toHaveAttribute(
        "aria-live",
        "off",
      );

      // Sample the active scene across at least two full cycles (2 × 6.2s) so a
      // real advance is observed and any per-cycle timer leak has room to show.
      const seenIndices = new Set<number>();
      const deadline = Date.now() + 15_000;
      while (Date.now() < deadline) {
        seenIndices.add(await activeSceneIndex(page));
        await page.waitForTimeout(500);
      }

      // The demo actually advanced through more than one preset scene (proves
      // the IntersectionObserver-gated cycle really ran for this variant).
      expect(
        seenIndices.size,
        `${variant} demo did not cycle scenes (indices seen: ${[
          ...seenIndices,
        ].join(", ")})`,
      ).toBeGreaterThan(1);

      const track = await readTimerTrack(page);

      // Instrumentation actually matched the demo's timers (guards against the
      // delay constants drifting and this test silently measuring nothing).
      expect(
        track.cycleSeen,
        "cycle timer (6200ms) was never created — demo did not start cycling or the delay changed",
      ).toBeGreaterThanOrEqual(1);
      expect(
        track.typeSeen,
        "typewriter timer (22ms) was never created — demo did not type or the delay changed",
      ).toBeGreaterThanOrEqual(1);

      // The crux: no runaway timers. Across every cycle there is only ever ONE
      // live cycle interval and ONE live typewriter interval — the old one is
      // cleared before the next starts. A leak would push these above 1 and
      // climb over a long session.
      expect(
        track.maxActiveCycle,
        "more than one cycle interval alive at once — cycle timer is leaking",
      ).toBeLessThanOrEqual(1);
      expect(
        track.maxActiveType,
        "more than one typewriter interval alive at once — type timer is leaking",
      ).toBeLessThanOrEqual(1);

      // No genuine script errors over the whole multi-cycle session.
      expect(
        scriptErrors,
        `unexpected script errors during demo session:\n${scriptErrors.join(
          "\n",
        )}`,
      ).toEqual([]);
    });
  }
});

// ---------------------------------------------------------------------------
// Reduced motion — the demo must fall back to a static, fully-assembled board
// with NO JS timers at all (nothing to leak, nothing to jank), and no errors.
// ---------------------------------------------------------------------------
test.describe("AI dashboard demo — reduced-motion static fallback", () => {
  test.use({ reducedMotion: "reduce" });

  for (const { label, path } of DEMO_PAGES) {
    test(`${label}: renders static, spins up no timers, logs no errors`, async ({
      page,
    }) => {
      test.setTimeout(90_000);

      const { scriptErrors } = await openDemoPage(page, path, {
        forceReduce: true,
      });
      const root = page.locator("[data-aidd]").first();
      await expect(root).toHaveAttribute("aria-live", "off");

      // Give the (motion) cycle the same wall-clock it would have had to start,
      // then prove it did NOT: scene never advances off the first preset.
      const before = await activeSceneIndex(page);
      expect(before).toBe(0);
      await page.waitForTimeout(8_000);
      expect(
        await activeSceneIndex(page),
        "reduced-motion demo advanced scenes — the cycle should be disabled",
      ).toBe(0);

      // The static fallback is fully assembled: every tile in the visible
      // (active) scene is at full opacity, and the caret is hidden. Only the
      // active scene is checked — the demo pre-renders all 5 preset scenes into
      // the DOM and hides the inactive ones (`display:none`, base `opacity:0`),
      // so a bare `[data-aidd-tile]` selector would wrongly pick up those hidden
      // tiles.
      const tileOpacities = await root
        .locator("[data-aidd-scene].is-active [data-aidd-tile]")
        .evaluateAll((els) =>
          els.map((el) =>
            parseFloat(getComputedStyle(el as HTMLElement).opacity),
          ),
        );
      expect(tileOpacities.length).toBeGreaterThan(0);
      for (const o of tileOpacities) {
        expect(o, "reduced-motion tile is not fully visible").toBeGreaterThan(
          0.99,
        );
      }
      await expect(root.locator("[data-aidd-caret]")).toBeHidden();

      // No JS timers at all under reduced motion — the leak/jank surface is
      // entirely absent, not merely bounded.
      const track = await readTimerTrack(page);
      expect(
        track.cycleSeen,
        "reduced-motion demo created a cycle timer — it should not run any",
      ).toBe(0);
      expect(
        track.typeSeen,
        "reduced-motion demo created a typewriter timer — it should not run any",
      ).toBe(0);

      expect(
        scriptErrors,
        `unexpected script errors in reduced-motion demo:\n${scriptErrors.join(
          "\n",
        )}`,
      ).toEqual([]);
    });
  }
});
