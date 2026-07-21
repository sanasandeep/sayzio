import { execFileSync } from "node:child_process";
import * as path from "node:path";
import { fileURLToPath } from "node:url";

import { expect, test, type Page } from "@playwright/test";

import { DEMO_LOGIN_EMAIL } from "./demo-account";
import { loginAsDemo } from "./login-as-demo";

const ARTIFACT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/**
 * Run a `php artisan tinker` seed, retrying on a transient failure. Over the
 * distant RDS the tinker process occasionally fails to connect with no PHP
 * error; a couple of quick retries absorb that blip while a genuine PHP error
 * fails every attempt and is surfaced. (Mirrors the sidebar-findbar spec.)
 */
function runTinkerSeed(php: string): string {
  let lastErr: unknown;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      return execFileSync("php", ["artisan", "tinker", "--execute=" + php], {
        cwd: ARTIFACT_ROOT,
        encoding: "utf8",
      });
    } catch (err) {
      lastErr = err;
    }
  }
  throw lastErr;
}

// Regression guard: the Live Preview iframe on /user/settings/creator once
// collapsed to the browser-default 300x150 because an Alpine STRING-syntax
// :style binding replaced the static style attribute (wiping width:390px /
// height:760px / transform-origin). The pane then showed only a tiny corner
// of the public profile (a red hero blob) inside a big white wrapper.
// The binding now uses Alpine OBJECT syntax, which merges instead.

test("creator settings live preview iframe keeps its 390x760 sizing", async ({
  page,
}) => {
  // Cold first hit against the distant RDS can take >45s; give it room.
  test.setTimeout(240_000);
  await loginAsDemo(page);
  await page.goto("/user/settings/creator", {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });

  const aside = page.locator('aside:has-text("Live preview")').first();
  await expect(aside).toBeVisible({ timeout: 30_000 });

  // The demo user may not have claimed a handle; without one there is no
  // preview iframe at all and this guard has nothing to check.
  const iframe = aside.locator("iframe");
  if ((await iframe.count()) === 0) {
    const claimMsg = aside.getByText(/Claim your handle/i);
    await expect(claimMsg).toBeVisible();
    return;
  }

  await aside.getByText("Large", { exact: true }).click();

  await expect
    .poll(
      async () =>
        iframe.evaluate((f) => {
          const r = f.getBoundingClientRect();
          return {
            w: Math.round(r.width),
            h: Math.round(r.height),
            inlineWidth: (f as HTMLElement).style.width,
            inlineHeight: (f as HTMLElement).style.height,
            origin: (f as HTMLElement).style.transformOrigin,
          };
        }),
      { timeout: 30_000 },
    )
    .toEqual(
      expect.objectContaining({
        inlineWidth: "390px",
        inlineHeight: "760px",
      }),
    );

  const box = await iframe.evaluate((f) => {
    const r = f.getBoundingClientRect();
    return { w: r.width, h: r.height };
  });
  // Broken state rendered ~226x113 (default 300x150 scaled). Fixed Large
  // mode renders roughly 390-430 wide and 700+ tall.
  expect(box.w).toBeGreaterThan(300);
  expect(box.h).toBeGreaterThan(600);
});

// ── Density + dark-mode preview toggles ─────────────────────────────────
//
// Regression guard for the owner-preview density/theme machinery:
//   - the public profile tags sections with data-cpd="m" (medium+) and
//     data-cpd="l" (large only); a cp_preview=1 visit emits CSS that hides
//     them under html.cp-d-small / html.cp-d-medium,
//   - a postMessage listener (owner preview only) toggles cp-d-small /
//     cp-d-medium and cp-pv-dark / light-mode on <html>,
//   - the settings pane's Small/Medium/Large buttons + theme toggle drive
//     that listener via pvLive().
// Any future edit to public/creator-profile.blade.php that drops the
// data-cpd attributes, the CSS block, or the message listener would
// previously have gone unnoticed — the sibling spec above only checks
// iframe sizing.

/** Class + visibility snapshot of the preview document's root element. */
async function cpSnapshot(page: Page, frameUrlPart: string) {
  const frame = page
    .frames()
    .find((f) => f.url().includes(frameUrlPart));
  if (!frame) return null;
  return frame.evaluate(() => {
    // Real sections carrying data-cpd tags are content-conditional (empty
    // bio hides itself inline, tab panes are x-show'd), so their computed
    // display can be "none" for reasons unrelated to density. Inject two
    // deterministic probe divs instead: the density CSS block selects by
    // attribute (`html.cp-d-small [data-cpd="m"]{display:none!important}`),
    // so the probes reflect exactly the CSS + <html> class state. The
    // mCount/lCount assertions separately guard that the REAL markup keeps
    // its tags.
    const probe = (tier: string) => {
      const id = "cp-e2e-probe-" + tier;
      let el = document.getElementById(id);
      if (!el) {
        el = document.createElement("div");
        el.id = id;
        el.setAttribute("data-cpd", tier);
        document.body.appendChild(el);
      }
      return getComputedStyle(el).display !== "none";
    };
    const cls = document.documentElement.classList;
    return {
      small: cls.contains("cp-d-small"),
      medium: cls.contains("cp-d-medium"),
      dark: cls.contains("cp-pv-dark"),
      light: cls.contains("light-mode"),
      // Exclude the probes themselves from the real-markup tag counts.
      mCount: document.querySelectorAll(
        '[data-cpd="m"]:not([id^="cp-e2e-probe"])',
      ).length,
      lCount: document.querySelectorAll(
        '[data-cpd="l"]:not([id^="cp-e2e-probe"])',
      ).length,
      mVisible: probe("m"),
      lVisible: probe("l"),
    };
  });
}

/**
 * Snapshot of a SPECIFIC preview frame (by iframe title attribute) rather
 * than the first cp_preview frame on the page — the full-screen overlay adds
 * a second iframe with the same URL, so URL matching alone is ambiguous.
 */
async function cpSnapshotByTitle(page: Page, title: string) {
  const handle = await page.$(`iframe[title="${title}"]`);
  if (!handle) return null;
  const frame = await handle.contentFrame();
  if (!frame) return null;
  return frame.evaluate(() => {
    const cls = document.documentElement.classList;
    return {
      small: cls.contains("cp-d-small"),
      medium: cls.contains("cp-d-medium"),
      dark: cls.contains("cp-pv-dark"),
      light: cls.contains("light-mode"),
    };
  });
}

test("density buttons and theme toggle drive the preview iframe; plain visits stay untouched", async ({
  page,
}) => {
  test.setTimeout(240_000);

  // The demo user needs a claimed handle or there is no preview iframe at
  // all. Idempotently claim one (direct column write; the e2e env's demo
  // account bypasses NotBannedName anyway) and read it back for the plain
  // /@handle visit at the end.
  const out = runTinkerSeed(
    `$u = \\App\\Modules\\User\\Models\\User::where('email', '${DEMO_LOGIN_EMAIL}')->first();` +
      `if (!$u) { echo 'HANDLE:NONE'; } else {` +
      `if (empty($u->handle)) { $u->handle = 'demo_cp_' . $u->id; $u->save(); }` +
      `echo 'HANDLE:' . $u->handle; }`,
  );
  const handle = /HANDLE:([A-Za-z0-9_]+)/.exec(out)?.[1];
  expect(handle, `demo user handle (tinker said: ${out})`).toBeTruthy();

  await loginAsDemo(page);
  await page.goto("/user/settings/creator", {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });

  const aside = page.locator('aside:has-text("Live preview")').first();
  await expect(aside).toBeVisible({ timeout: 30_000 });
  const iframe = aside.locator("iframe");
  await expect(iframe).toHaveCount(1, { timeout: 30_000 });

  // Wait for the preview document (cold public-profile render over the
  // distant RDS can be slow) and for its data-cpd tagging to be present.
  await expect
    .poll(async () => cpSnapshot(page, "cp_preview=1"), { timeout: 120_000 })
    .toEqual(expect.objectContaining({ mVisible: true }));

  const snap = async () => {
    const s = await cpSnapshot(page, "cp_preview=1");
    if (!s) throw new Error("preview frame disappeared");
    return s;
  };

  // The public page must still carry the density tags at all.
  const initial = await snap();
  expect(initial.mCount).toBeGreaterThan(0);
  expect(initial.lCount).toBeGreaterThan(0);

  // ── Small: both medium+ and large-only sections hide ──────────────
  await aside.getByText("Small", { exact: true }).click();
  await expect.poll(snap, { timeout: 30_000 }).toEqual(
    expect.objectContaining({
      small: true,
      medium: false,
      mVisible: false,
      lVisible: false,
    }),
  );

  // ── Medium: medium+ shows, large-only stays hidden ─────────────────
  await aside.getByText("Medium", { exact: true }).click();
  await expect.poll(snap, { timeout: 30_000 }).toEqual(
    expect.objectContaining({
      small: false,
      medium: true,
      mVisible: true,
      lVisible: false,
    }),
  );

  // ── Large: everything shows again ──────────────────────────────────
  await aside.getByText("Large", { exact: true }).click();
  await expect.poll(snap, { timeout: 30_000 }).toEqual(
    expect.objectContaining({
      small: false,
      medium: false,
      mVisible: true,
      lVisible: true,
    }),
  );

  // ── Theme toggle flips cp-pv-dark <-> light-mode ────────────────────
  const themeBtn = aside.locator('button[title="Toggle preview dark/light"]');
  const before = await snap();
  await themeBtn.click();
  await expect.poll(snap, { timeout: 30_000 }).toEqual(
    expect.objectContaining(
      before.dark
        ? { dark: false, light: true }
        : { dark: true, light: false },
    ),
  );
  await themeBtn.click();
  await expect.poll(snap, { timeout: 30_000 }).toEqual(
    expect.objectContaining(
      before.dark
        ? { dark: true, light: false }
        : { dark: false, light: true },
    ),
  );

  // ── Plain /@handle visit: no preview machinery ──────────────────────
  await page.goto(`/@${handle}`, {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });
  const plain = await page.evaluate(() => {
    const cls = document.documentElement.classList;
    return {
      small: cls.contains("cp-d-small"),
      medium: cls.contains("cp-d-medium"),
      dark: cls.contains("cp-pv-dark"),
      mCount: document.querySelectorAll('[data-cpd="m"]').length,
    };
  });
  expect(plain.small).toBe(false);
  expect(plain.medium).toBe(false);
  expect(plain.dark).toBe(false);
  // The tags are still in the markup, just inert without cp_preview=1.
  expect(plain.mCount).toBeGreaterThan(0);

  // The message listener must NOT be installed on plain visits: post the
  // same cpLive message the editor sends and assert nothing toggles.
  await page.evaluate(() => {
    window.postMessage(
      { type: "cpLive", density: "small", theme: "dark" },
      window.location.origin,
    );
  });
  await page.waitForTimeout(1_000);
  const after = await page.evaluate(() => {
    const cls = document.documentElement.classList;
    return {
      small: cls.contains("cp-d-small"),
      dark: cls.contains("cp-pv-dark"),
    };
  });
  expect(after.small).toBe(false);
  expect(after.dark).toBe(false);
});

// ── Full-screen preview overlay ─────────────────────────────────────────
//
// The expand button switches pvMode to 'full', which mounts a SECOND iframe
// (x-ref="pvFrameFull") via a template x-if. It receives the same cpLive
// postMessage as the sidebar pane — density forced to 'large', theme from
// the toggle — but had no coverage: a regression (e.g. the x-if remount
// dropping @load="pvLive()") would leave the overlay stuck on defaults.

test("full-screen preview honors the theme toggle and closes back to the small pane", async ({
  page,
}) => {
  test.setTimeout(240_000);

  // Ensure the demo user has a handle (same idempotent seed as above);
  // without one there is no preview machinery at all.
  const out = runTinkerSeed(
    `$u = \\App\\Modules\\User\\Models\\User::where('email', '${DEMO_LOGIN_EMAIL}')->first();` +
      `if (!$u) { echo 'HANDLE:NONE'; } else {` +
      `if (empty($u->handle)) { $u->handle = 'demo_cp_' . $u->id; $u->save(); }` +
      `echo 'HANDLE:' . $u->handle; }`,
  );
  expect(/HANDLE:([A-Za-z0-9_]+)/.test(out), `tinker said: ${out}`).toBe(true);

  await loginAsDemo(page);
  await page.goto("/user/settings/creator", {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });

  const aside = page.locator('aside:has-text("Live preview")').first();
  await expect(aside).toBeVisible({ timeout: 30_000 });
  await expect(aside.locator("iframe")).toHaveCount(1, { timeout: 30_000 });

  const SMALL_TITLE = "Profile preview";
  const FULL_TITLE = "Profile preview (full)";

  // Wait for the sidebar preview document to be live before expanding.
  await expect
    .poll(async () => cpSnapshotByTitle(page, SMALL_TITLE), {
      timeout: 120_000,
    })
    .not.toBeNull();

  // ── Expand to full screen ──────────────────────────────────────────
  await aside.locator('button[title="Full preview"]').click();
  const overlay = page.locator("div.fixed.inset-0", {
    hasText: "Profile preview —",
  });
  await expect(overlay).toBeVisible({ timeout: 15_000 });

  const fullSnap = async () => {
    const s = await cpSnapshotByTitle(page, FULL_TITLE);
    if (!s) throw new Error("full preview frame not ready");
    return s;
  };

  // The full frame must have received cpLive: density is forced to 'large'
  // (neither cp-d-small nor cp-d-medium), and one of the theme classes is
  // applied. Cold second public-profile render over the distant RDS can be
  // slow, so poll generously.
  await expect.poll(fullSnap, { timeout: 120_000 }).toEqual(
    expect.objectContaining({ small: false, medium: false }),
  );
  // Note: before any toggle the frame may carry NEITHER theme class (the
  // listener only applies cp-pv-dark / light-mode once a theme value is
  // acted on), so only assert the flips below, mirroring the sibling test.
  const before = await fullSnap();

  // ── Theme toggle flips cp-pv-dark <-> light-mode inside pvFrameFull ─
  const themeBtn = overlay.locator("button", {
    has: page.locator("i.fa-moon, i.fa-sun"),
  });
  await themeBtn.click();
  await expect.poll(fullSnap, { timeout: 30_000 }).toEqual(
    expect.objectContaining(
      before.dark
        ? { dark: false, light: true }
        : { dark: true, light: false },
    ),
  );
  await themeBtn.click();
  await expect.poll(fullSnap, { timeout: 30_000 }).toEqual(
    expect.objectContaining(
      before.dark
        ? { dark: true, light: false }
        : { dark: false, light: true },
    ),
  );

  // ── Escape returns to the small pane ────────────────────────────────
  await page.keyboard.press("Escape");
  await expect(overlay).toBeHidden({ timeout: 15_000 });
  // The template x-if unmounts the full iframe entirely.
  await expect(page.locator(`iframe[title="${FULL_TITLE}"]`)).toHaveCount(0, {
    timeout: 15_000,
  });
  // Small pane still present and its frame still answers with cp-d-small
  // (Escape sets mode 'small').
  await expect(aside.locator("iframe")).toHaveCount(1);
  await expect
    .poll(async () => cpSnapshotByTitle(page, SMALL_TITLE), {
      timeout: 30_000,
    })
    .toEqual(expect.objectContaining({ small: true }));

  // ── Close button path: expand again and close via the button ────────
  await aside.locator('button[title="Full preview"]').click();
  await expect(overlay).toBeVisible({ timeout: 15_000 });
  await overlay.locator("button", { hasText: "Close" }).click();
  await expect(overlay).toBeHidden({ timeout: 15_000 });
});

// ── Accent color: pick then clear ───────────────────────────────────────
//
// The cpLive listener sets --cp-accent/--cp-accent-soft/--cp-accent-mid as
// inline vars on <html> when a #RRGGBB color arrives. Clearing the field
// ("Reset to default") sends color: '' — the listener must REMOVE those
// inline vars so the preview falls back to the server-rendered state
// (default gradient), matching what would actually be saved. Before the
// fix, the empty string was silently ignored and the preview kept the last
// picked color until reload.

test("clearing the accent color resets the preview's inline accent vars", async ({
  page,
}) => {
  test.setTimeout(240_000);

  // Ensure the demo user has a handle (no handle → no preview iframe) and
  // starts with NO saved accent color, so the server renders no inline
  // --cp-accent and the cleared state is distinguishable.
  runTinkerSeed(
    `$u = \\App\\Modules\\User\\Models\\User::where('email', '${DEMO_LOGIN_EMAIL}')->first();` +
      `if ($u) { if (empty($u->handle)) { $u->handle = 'demo_cp_' . $u->id; }` +
      `$u->profile_theme_color = null; $u->save(); echo 'OK'; }`,
  );

  await loginAsDemo(page);
  await page.goto("/user/settings/creator", {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });

  const aside = page.locator('aside:has-text("Live preview")').first();
  await expect(aside).toBeVisible({ timeout: 30_000 });
  const iframe = aside.locator("iframe");
  await expect(iframe).toHaveCount(1, { timeout: 30_000 });

  const accentVars = async () => {
    const frame = page.frames().find((f) => f.url().includes("cp_preview=1"));
    if (!frame) return null;
    return frame.evaluate(() => {
      const s = document.documentElement.style;
      return {
        accent: s.getPropertyValue("--cp-accent").trim(),
        soft: s.getPropertyValue("--cp-accent-soft").trim(),
        mid: s.getPropertyValue("--cp-accent-mid").trim(),
      };
    });
  };

  // Wait for the preview frame to exist (cold render over distant RDS).
  await expect
    .poll(accentVars, { timeout: 120_000 })
    .toEqual(expect.objectContaining({ accent: "" }));

  // Pick a preset swatch (buttons are titled with their hex).
  const swatchHex = "#e11d48";
  await page.locator(`button[title="${swatchHex}"]`).click();
  await expect.poll(accentVars, { timeout: 30_000 }).toEqual({
    accent: swatchHex,
    soft: swatchHex + "33",
    mid: swatchHex + "88",
  });

  // Clear it — the inline vars must be removed, not left stale.
  await page.getByRole("button", { name: "Reset to default" }).click();
  await expect.poll(accentVars, { timeout: 30_000 }).toEqual({
    accent: "",
    soft: "",
    mid: "",
  });
});

// ── Text fields: clear then save + reload ───────────────────────────────
//
// The cpLive listener hides the tagline / location / bio sections live when
// their editor inputs are emptied (display:none via [data-cp=...] hooks).
// What was NOT covered: that the live-cleared state matches what a visitor
// would actually see after saving — i.e. that a preview iframe reload with
// the SAVED (emptied) values renders the same hidden sections, rather than
// the live preview promising something the server render doesn't honor.

/** Visibility + text snapshot of the preview frame's cp text sections. */
async function cpTextState(page: Page) {
  const frame = page.frames().find((f) => f.url().includes("cp_preview=1"));
  if (!frame) return null;
  // The frame can detach mid-evaluate while the parent page navigates
  // (e.g. right after the save redirect); return null so the caller's
  // expect.poll simply retries against the fresh frame.
  return frame
    .evaluate(() => {
      const vis = (sel: string) => {
        const el = document.querySelector<HTMLElement>(sel);
        if (!el) return null;
        return getComputedStyle(el).display !== "none";
      };
      const text = (sel: string) =>
        document.querySelector(sel)?.textContent?.trim() ?? null;
      return {
        taglineVisible: vis('[data-cp="tagline"]'),
        taglineText: text('[data-cp="tagline"]'),
        locationVisible: vis('[data-cp="location-wrap"]'),
        locationText: text('[data-cp="location"]'),
        bioVisible: vis('[data-cp="bio-section"]'),
        bioText: text('[data-cp="bio"]'),
      };
    })
    .catch(() => null);
}

test("emptied tagline/location/bio hide in the live preview and stay hidden after save + iframe reload", async ({
  page,
}) => {
  test.setTimeout(300_000);

  // Seed: handle claimed + all three text fields set to known values, so
  // the initial preview shows every section and the cleared state is a
  // real transition (not vacuously hidden from the start).
  const TAG = "E2E cp tagline";
  const LOC = "E2E City";
  const BIO = "E2E cp bio body";
  const out = runTinkerSeed(
    `$u = \\App\\Modules\\User\\Models\\User::where('email', '${DEMO_LOGIN_EMAIL}')->first();` +
      `if (!$u) { echo 'SEED:NONE'; } else {` +
      `if (empty($u->handle)) { $u->handle = 'demo_cp_' . $u->id; }` +
      `$u->tagline = '${TAG}'; $u->location = '${LOC}'; $u->bio = '${BIO}';` +
      `$u->save(); echo 'SEED:OK'; }`,
  );
  expect(out, `tinker said: ${out}`).toContain("SEED:OK");

  await loginAsDemo(page);
  await page.goto("/user/settings/creator", {
    waitUntil: "domcontentloaded",
    timeout: 180_000,
  });

  const aside = page.locator('aside:has-text("Live preview")').first();
  await expect(aside).toBeVisible({ timeout: 30_000 });
  await expect(aside.locator("iframe")).toHaveCount(1, { timeout: 30_000 });

  // Initial server render: every section visible with the seeded text.
  await expect.poll(() => cpTextState(page), { timeout: 120_000 }).toEqual({
    taglineVisible: true,
    taglineText: TAG,
    locationVisible: true,
    locationText: LOC,
    bioVisible: true,
    bioText: BIO,
  });

  // ── Empty all three fields in the editor ───────────────────────────
  // fill('') fires input events; the container's @input.debounce.300ms
  // pvLive() then posts the cpLive message with empty strings.
  await page.locator('input[name="tagline"]').fill("");
  await page.locator('input[name="location"]').fill("");
  await page.locator('textarea[name="bio"]').fill("");

  // Live preview must hide the sections (display:none, cleared text).
  const CLEARED = {
    taglineVisible: false,
    taglineText: "",
    locationVisible: false,
    locationText: "",
    bioVisible: false,
    bioText: "",
  };
  await expect
    .poll(() => cpTextState(page), { timeout: 30_000 })
    .toEqual(CLEARED);

  // ── Save, then confirm the reloaded preview matches ─────────────────
  // The first cold write POST over the distant RDS can exceed 10s; wait
  // for the update response explicitly before asserting anything.
  const [resp] = await Promise.all([
    page.waitForResponse(
      (r) =>
        r.url().includes("/user/settings/creator") &&
        r.request().method() === "POST",
      { timeout: 120_000 },
    ),
    // noWaitAfter: a plain click blocks 30s on "waiting for scheduled
    // navigations" against the slow authenticated re-render; the sibling
    // waitForResponse + waitForURL below own the navigation instead.
    page
      .getByRole("button", { name: /Save profile/i })
      .click({ noWaitAfter: true }),
  ]);
  expect(resp.status()).toBeLessThan(400);

  // The redirect back to the settings page mounts a FRESH preview iframe
  // rendering the SAVED values — it must match the live-cleared state:
  // sections still hidden (server-rendered style="display:none" under
  // cp_preview, empty text), proving the live preview told the truth.
  await page.waitForURL(/\/user\/settings\/creator/, { timeout: 120_000 });
  await expect
    .poll(() => cpTextState(page), { timeout: 120_000 })
    .toEqual(CLEARED);

  // And the actually-saved rows agree (belt & braces against a form
  // regression silently not persisting the cleared values).
  const saved = runTinkerSeed(
    `$u = \\App\\Modules\\User\\Models\\User::where('email', '${DEMO_LOGIN_EMAIL}')->first();` +
      `echo 'SAVED:' . json_encode([$u->tagline, $u->location, $u->bio]);`,
  );
  const m = /SAVED:(\[.*\])/.exec(saved);
  expect(m, `tinker said: ${saved}`).toBeTruthy();
  expect(JSON.parse(m![1])).toEqual([null, null, null]);
});
