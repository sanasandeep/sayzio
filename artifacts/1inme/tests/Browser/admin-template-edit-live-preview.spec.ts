import { test, expect } from "@playwright/test";
import { loginAsDemoAdmin } from "./login-as-demo-admin";

/**
 * Admin template EDIT page — sticky live preview panel.
 *
 * Verifies the edit page renders a two-column layout with a scaled live
 * preview iframe (admin.templates.preview) on the right, that the iframe
 * actually loads the rendered template document, and that the form is still
 * present and intact alongside it.
 */

test.describe("admin template edit live preview", () => {
  test.describe.configure({ timeout: 240_000 });

  test("edit page shows scaled live preview beside the form", async ({
    page,
  }) => {
    await loginAsDemoAdmin(page);

    // Find any existing page template id via the index.
    await page.goto("/admin/templates?tab=page", { timeout: 120_000 });
    const firstCard = page.locator("[data-tpl-id]").first();
    await expect(firstCard).toBeAttached({ timeout: 60_000 });
    const tplId = await firstCard.getAttribute("data-tpl-id");
    expect(tplId).toBeTruthy();

    await page.goto(`/admin/templates/page/${tplId}/edit`, {
      timeout: 120_000,
    });

    // Preview panel present with the live iframe pointed at the preview route.
    const panelHeading = page.getByRole("heading", { name: /Live preview/i });
    await expect(panelHeading).toBeVisible({ timeout: 60_000 });

    const iframe = page.locator(
      `iframe[src*="/admin/templates/page/${tplId}/preview"]`,
    );
    await expect(iframe).toBeAttached();
    await expect(iframe).toBeVisible();

    // Scaled to fit: fixed 420px viewport width + a real transform scale.
    await expect(async () => {
      const { width, transform } = await iframe.evaluate((el) => {
        const cs = getComputedStyle(el);
        return { width: cs.width, transform: cs.transform };
      });
      expect(width).toBe("420px");
      expect(transform).toMatch(/^matrix\(/);
      const scale = parseFloat(transform.replace("matrix(", "").split(",")[0]);
      expect(scale).toBeGreaterThan(0.1);
      expect(scale).toBeLessThan(1.5);
    }).toPass({ timeout: 30_000 });

    // The iframe actually loads the preview document (lazy-loaded, so poll).
    await expect(async () => {
      const loaded = page
        .frames()
        .some((f) => new RegExp(`templates/page/${tplId}/preview`).test(f.url()));
      expect(loaded).toBe(true);
    }).toPass({ timeout: 60_000 });

    // Form still intact on the left: Basic Info card + name input + save flow.
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(
      page.getByRole("heading", { name: /Basic Info/i }),
    ).toBeVisible();

    // Design-editor banner unchanged for page templates.
    await expect(
      page.getByRole("button", { name: /Edit design here/i }),
    ).toBeVisible();
  });

  test("preview auto-reloads after a design-session save signal", async ({
    page,
  }) => {
    await loginAsDemoAdmin(page);

    await page.goto("/admin/templates?tab=page", { timeout: 120_000 });
    const firstCard = page.locator("[data-tpl-id]").first();
    await expect(firstCard).toBeAttached({ timeout: 60_000 });
    const tplId = await firstCard.getAttribute("data-tpl-id");
    expect(tplId).toBeTruthy();

    await page.goto(`/admin/templates/page/${tplId}/edit`, {
      timeout: 120_000,
    });

    // Wait for the lazy preview iframe to complete its initial load.
    await expect(async () => {
      const loaded = page
        .frames()
        .some((f) => new RegExp(`templates/page/${tplId}/preview`).test(f.url()));
      expect(loaded).toBe(true);
    }).toPass({ timeout: 60_000 });

    // 1) The inline design editor posts a save message to the parent —
    //    the preview iframe must re-request the preview route.
    const previewUrl = new RegExp(`/admin/templates/page/${tplId}/preview`);
    const reloadReq = page.waitForRequest((r) => previewUrl.test(r.url()), {
      timeout: 30_000,
    });
    await page.evaluate((id) => {
      window.postMessage(
        {
          type: "sayzio:template-design-saved",
          kind: "page",
          templateId: Number(id),
        },
        window.location.origin,
      );
    }, tplId);
    await reloadReq;

    // 2) A save stamped from another tab (full-screen editor) triggers a
    //    reload when this tab regains focus.
    const reloadReq2 = page.waitForRequest((r) => previewUrl.test(r.url()), {
      timeout: 30_000,
    });
    await page.evaluate((id) => {
      localStorage.setItem(
        "sayzio:tpl-design-saved:page:" + id,
        String(Date.now() + 5_000),
      );
      window.dispatchEvent(new Event("focus"));
    }, tplId);
    await reloadReq2;

    // Manual reload button still present as a fallback.
    await expect(page.locator('button[title="Reload preview"]')).toBeVisible();
  });
});
