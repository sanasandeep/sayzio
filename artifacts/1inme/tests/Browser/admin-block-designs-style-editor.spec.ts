import { test, expect } from "@playwright/test";
import { loginAsDemoAdmin } from "./login-as-demo-admin";

/**
 * Admin Block Designs — visual style editor (Task #6054).
 *
 * The raw style JSON textarea was replaced with friendly controls
 * (color pickers, sliders, dropdowns) that two-way sync with a hidden
 * "Advanced" JSON textarea, which remains the ONLY submitted field
 * (`style_json`). This spec verifies the sync actually works in a real
 * browser: control edits land in the textarea JSON, and JSON edits
 * update the live preview.
 */

test.describe("admin block-designs visual style editor", () => {
  test.describe.configure({ timeout: 240_000 });

  test("variant create form controls sync into style_json", async ({
    page,
  }) => {
    await loginAsDemoAdmin(page);
    await page.goto("/admin/block-designs/variants/create", {
      timeout: 120_000,
    });

    // Visual sections render.
    await expect(page.getByText("Live preview")).toBeVisible({
      timeout: 60_000,
    });
    const textarea = page.locator('textarea[name="style_json"]');
    await expect(textarea).toBeAttached();

    // Seeded with valid JSON (starter payload).
    const initial = JSON.parse((await textarea.inputValue()) || "{}");
    expect(typeof initial).toBe("object");

    // 1) Dropdown control → JSON.
    await page
      .locator("select")
      .filter({ has: page.locator('option[value="italic"]') })
      .selectOption("italic");
    await expect
      .poll(async () => JSON.parse(await textarea.inputValue()).font_style)
      .toBe("italic");

    // 2) Text color text input → JSON (numeric coercion untouched).
    const textColorInput = page
      .locator('input[type="text"][placeholder="none"]')
      .first();
    await textColorInput.fill("#12ab34");
    await expect
      .poll(async () => JSON.parse(await textarea.inputValue()).text_color)
      .toBe("#12ab34");

    // 3) Live preview chip reflects the color.
    const chip = page.getByText("Sample button", { exact: true });
    await expect(chip).toBeVisible();
    await expect
      .poll(() =>
        chip.evaluate((el) => getComputedStyle(el).color),
      )
      .toBe("rgb(18, 171, 52)");

    // 4) Advanced JSON edit → controls/preview (reverse direction).
    await page.getByRole("button", { name: /edit raw style JSON/i }).click();
    await expect(textarea).toBeVisible();
    const current = JSON.parse(await textarea.inputValue());
    current.bg_color = "#000000";
    current.border_radius = 33;
    await textarea.fill(JSON.stringify(current));
    await expect
      .poll(() =>
        chip.evaluate((el) => getComputedStyle(el).borderRadius),
      )
      .toBe("33px");

    // No JSON error shown for valid JSON.
    await expect(page.getByText(/Not valid JSON/i)).toBeHidden();

    // 5) Invalid JSON shows an inline error and keeps the field intact.
    await textarea.fill("{not json");
    await expect(page.getByText(/Not valid JSON/i)).toBeVisible();
  });

  test("template create form renders the visual editor", async ({ page }) => {
    await loginAsDemoAdmin(page);
    await page.goto("/admin/block-designs/templates/create", {
      timeout: 120_000,
    });
    await expect(page.getByText("Live preview")).toBeVisible({
      timeout: 60_000,
    });
    await expect(
      page.locator('textarea[name="style_json"]'),
    ).toBeAttached();
    await expect(
      page.getByText("Sample block", { exact: true }),
    ).toBeVisible();
    // Link layout dropdown offers the sanitizer's layouts.
    await expect(
      page.locator('select option[value="icon_left"]'),
    ).toBeAttached();
  });
});
