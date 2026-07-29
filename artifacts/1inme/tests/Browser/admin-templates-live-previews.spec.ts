import { test, expect } from "@playwright/test";
import { loginAsDemoAdmin } from "./login-as-demo-admin";

/**
 * Admin Templates index — "Live previews" toggle.
 *
 * Verifies the grid can switch every card's thumbnail into a real scaled-down
 * live render (iframe of admin.templates.preview) and back, and that the
 * preference persists via localStorage.
 */

test.describe("admin templates live previews", () => {
  test.describe.configure({ timeout: 240_000 });

  test("toggle swaps thumbnails for scaled live iframes and persists", async ({
    page,
  }) => {
    await loginAsDemoAdmin(page);
    await page.goto("/admin/templates", { timeout: 120_000 });

    const toggle = page.getByRole("button", { name: /Live previews/i });
    await expect(toggle).toBeVisible();

    // Default = static thumbnails, no preview iframes.
    const cardImgs = page.locator('[data-tpl-id] img[alt$="preview"]');
    await expect(cardImgs.first()).toBeVisible();
    await expect(
      page.locator('[data-tpl-id] iframe[src*="templates"][src*="preview"]'),
    ).toHaveCount(0);

    // Flip to live mode.
    await toggle.click();
    const iframes = page.locator(
      '[data-tpl-id] iframe[src*="templates"][src*="preview"]',
    );
    await expect(iframes.first()).toBeAttached({ timeout: 30_000 });
    const iframeCount = await iframes.count();
    expect(iframeCount).toBeGreaterThan(1);

    // The iframe is scaled to fit its card: width fixed at 420 CSS px, with a
    // real (0 < s <= 1-ish) scale transform applied.
    const first = iframes.first();
    const { width, transform } = await first.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { width: cs.width, transform: cs.transform };
    });
    expect(width).toBe("420px");
    expect(transform).toMatch(/^matrix\(/);
    const scale = parseFloat(transform.replace("matrix(", "").split(",")[0]);
    expect(scale).toBeGreaterThan(0.1);
    expect(scale).toBeLessThan(1.5);

    // First iframe actually loads the real preview document. The iframes are
    // loading="lazy", so poll until at least one frame has navigated to the
    // preview URL instead of doing a one-shot lookup.
    await expect(async () => {
      const loaded = page
        .frames()
        .some((f) => /templates.*preview/.test(f.url()));
      expect(loaded).toBe(true);
    }).toPass({ timeout: 60_000 });

    // Persists across reload.
    await page.reload({ timeout: 120_000 });
    await expect(
      page
        .locator('[data-tpl-id] iframe[src*="templates"][src*="preview"]')
        .first(),
    ).toBeAttached({ timeout: 30_000 });

    // Flip back to thumbnails.
    await page.getByRole("button", { name: /Live previews/i }).click();
    await expect(
      page.locator('[data-tpl-id] iframe[src*="templates"][src*="preview"]'),
    ).toHaveCount(0);
    await expect(cardImgs.first()).toBeVisible();
  });
});
