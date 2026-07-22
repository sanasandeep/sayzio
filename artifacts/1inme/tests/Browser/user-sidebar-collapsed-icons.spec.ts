import {
  expect,
  test as base,
  type BrowserContext,
  type Page,
} from "@playwright/test";

import { loginAsDemo } from "./login-as-demo";

// Regression guard for the "missing sidebar items when collapsed" bug
// (user dashboard sidebar, icons mode).
//
// Bug: the collapsed-mode CSS in user/layouts/app.blade.php used
//   .sidebar-v2.collapsed nav > * { display:flex; justify-content:center }
// which turned each collapsible group's wrapper <div> (Links & Pages,
// Monetization, Audience, Tools, ...) into a HORIZONTAL flex row. The grouped
// links were laid out side-by-side inside the 72px-wide sidebar and clipped by
// the scroll container's overflow-x-hidden — they effectively vanished. Only
// ungrouped top-level links (Dashboard, Links, Notifications, ...) survived as
// icons.
//
// Fix: collapsed-mode wrappers now stack their children vertically
// (flex-direction: column on nav > *, display:block on the group wrapper divs
// and their x-show containers), so every permitted nav item renders as a
// centered icon on the 72px rail.
//
// This spec asserts, on the Settings page (where the bug was reported):
//   1. In collapsed (icons) mode EVERY .sidebar-link present in the DOM is
//      visible (nonzero size) and horizontally inside the sidebar box —
//      grouped items included. The visible-in-collapsed count must equal the
//      total DOM count (permission gating is server-side, so every rendered
//      link must show).
//   2. Icons are centered on the rail (link box centered within ±2px).
//   3. Full mode is unchanged: sidebar is 260px and group toggles still
//      expand/collapse their links.

let sharedContext: BrowserContext;

const test = base.extend({
  page: async ({}, use) => {
    const page = await sharedContext.newPage();
    await use(page);
    await page.close();
  },
});

async function openSettings(page: Page): Promise<void> {
  await page.goto("/user/settings/profile", { timeout: 120_000 });
  // Wait for the desktop sidebar's Dashboard link to be attached (Alpine
  // mounted, sidebar in final state).
  await page
    .locator("aside.sidebar-v2 .nav-label", { hasText: /^Dashboard$/ })
    .first()
    .waitFor({ state: "attached", timeout: 120_000 });
  await page.waitForTimeout(450); // let the width transition settle
}

type SidebarAudit = {
  sidebarWidth: number;
  totalLinks: number;
  visibleLinks: number;
  hiddenLabels: string[]; // links NOT visible / outside the sidebar box
  offCenterLabels: string[]; // visible links not centered on the rail
};

/**
 * Audit every .sidebar-link inside the desktop sidebar:
 * a link counts as "visible" when it has a nonzero rect that lies horizontally
 * within the sidebar box (1px tolerance) and no ancestor hides it
 * (display:none / visibility:hidden / opacity:0).
 */
async function auditSidebar(page: Page): Promise<SidebarAudit> {
  return page.evaluate(() => {
    const aside = document.querySelector<HTMLElement>("aside.sidebar-v2");
    if (!aside) {
      return {
        sidebarWidth: 0,
        totalLinks: 0,
        visibleLinks: 0,
        hiddenLabels: ["<no aside.sidebar-v2 found>"],
        offCenterLabels: [],
      };
    }
    const asideRect = aside.getBoundingClientRect();
    const links = Array.from(
      aside.querySelectorAll<HTMLElement>(".sidebar-link"),
    );

    const nameOf = (el: HTMLElement): string =>
      el.querySelector(".nav-label")?.textContent?.trim() ||
      el.getAttribute("href") ||
      "<unnamed>";

    const isDisplayed = (el: HTMLElement): boolean => {
      let node: HTMLElement | null = el;
      while (node && node !== aside) {
        const cs = getComputedStyle(node);
        if (
          cs.display === "none" ||
          cs.visibility === "hidden" ||
          cs.opacity === "0"
        )
          return false;
        node = node.parentElement;
      }
      return true;
    };

    const hiddenLabels: string[] = [];
    const offCenterLabels: string[] = [];
    let visibleLinks = 0;

    for (const link of links) {
      const rect = link.getBoundingClientRect();
      const withinBox =
        rect.width > 0 &&
        rect.height > 0 &&
        rect.left >= asideRect.left - 1 &&
        rect.right <= asideRect.right + 1;
      if (!withinBox || !isDisplayed(link)) {
        hiddenLabels.push(nameOf(link));
        continue;
      }
      visibleLinks++;
      // Centering check: link box centered on the scrollable rail's CLIENT
      // area (which excludes the vertical scrollbar — with all icons visible
      // the rail scrolls, and the scrollbar shifts content ~5px left of the
      // aside's geometric center; that is correct visual centering).
      const scroller =
        aside.querySelector<HTMLElement>(".sidebar-nav-scroll") ?? aside;
      const scrollerRect = scroller.getBoundingClientRect();
      const linkCenter = rect.left + rect.width / 2;
      const railCenter = scrollerRect.left + scroller.clientWidth / 2;
      if (Math.abs(linkCenter - railCenter) > 2) {
        offCenterLabels.push(
          `${nameOf(link)} (off by ${Math.round(linkCenter - railCenter)}px)`,
        );
      }
    }

    return {
      sidebarWidth: Math.round(asideRect.width),
      totalLinks: links.length,
      visibleLinks,
      hiddenLabels,
      offCenterLabels,
    };
  });
}

test.describe("user sidebar collapsed (icons) mode shows every nav item", () => {
  test.describe.configure({ timeout: 240_000 });

  test.beforeAll(async ({ browser }) => {
    sharedContext = await browser.newContext({
      viewport: { width: 1366, height: 900 },
    });
    const loginPage = await sharedContext.newPage();
    await loginAsDemo(loginPage);
    await loginPage.close();
  });

  test.afterAll(async () => {
    await sharedContext?.close();
  });

  test("collapsed mode: every sidebar link (grouped items included) is a visible centered icon", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      localStorage.setItem("1inme_sidebar", "icons");
    });
    await openSettings(page);

    const audit = await auditSidebar(page);

    expect(
      audit.sidebarWidth,
      `sidebar must be 72px in icons mode, got ${audit.sidebarWidth}px`,
    ).toBe(72);
    expect(
      audit.totalLinks,
      "sanity: the sidebar must render a substantial number of nav links",
    ).toBeGreaterThan(10);
    expect(
      audit.hiddenLabels,
      `every nav link must be visible inside the collapsed sidebar; missing/clipped: ${audit.hiddenLabels.join(", ")}`,
    ).toHaveLength(0);
    expect(
      audit.visibleLinks,
      "visible icon count in collapsed mode must equal the total rendered link count",
    ).toBe(audit.totalLinks);
    expect(
      audit.offCenterLabels,
      `every icon must be centered on the rail; off-center: ${audit.offCenterLabels.join(", ")}`,
    ).toHaveLength(0);
  });

  test("collapsed vs full: visible collapsed icons match the full-mode link count", async ({
    page,
  }) => {
    // Full mode first: capture the total number of rendered links.
    await page.addInitScript(() => {
      localStorage.setItem("1inme_sidebar", "full");
    });
    await openSettings(page);
    const full = await auditSidebar(page);
    expect(
      full.sidebarWidth,
      `sidebar must be 260px in full mode, got ${full.sidebarWidth}px`,
    ).toBe(260);

    // Collapse via the edge toggle button (same session, no reload).
    await page
      .locator("aside.sidebar-v2 button[aria-label='Toggle sidebar']")
      .click();
    await page.waitForTimeout(450);

    const collapsed = await auditSidebar(page);
    expect(
      collapsed.sidebarWidth,
      `after collapsing the sidebar must be 72px, got ${collapsed.sidebarWidth}px`,
    ).toBe(72);
    expect(
      collapsed.totalLinks,
      "collapsing must not change the number of rendered links",
    ).toBe(full.totalLinks);
    expect(
      collapsed.visibleLinks,
      `every link must stay visible as an icon after collapsing; missing: ${collapsed.hiddenLabels.join(", ")}`,
    ).toBe(full.totalLinks);
  });

  test("full mode: group toggle still expands/collapses its links", async ({
    page,
  }) => {
    await page.addInitScript(() => {
      localStorage.setItem("1inme_sidebar", "full");
    });
    await openSettings(page);

    // "Links & Pages" group: not route-active on the Settings page, so it
    // starts closed; its first link (QR Codes) must be hidden, then shown
    // after toggling, then hidden again.
    const groupBtn = page.locator("aside.sidebar-v2 .sidebar-group-toggle", {
      hasText: "Links & Pages",
    });
    await expect(groupBtn).toBeVisible();

    const qrLink = page.locator("aside.sidebar-v2 .sidebar-link", {
      has: page.locator(".nav-label", { hasText: /^QR Codes$/ }),
    });

    await expect(qrLink).toBeHidden();
    await groupBtn.click();
    await expect(qrLink).toBeVisible();
    await groupBtn.click();
    await expect(qrLink).toBeHidden();
  });
});
