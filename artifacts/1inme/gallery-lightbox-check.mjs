import { chromium } from "@playwright/test";

const BASE = "http://localhost:5000";
const browser = await chromium.launch();
const page = await browser.newPage();
try {
  // Demo admin login (synthesized POST with CSRF token)
  await page.goto(BASE + "/admin/login", { timeout: 120000 });
  await Promise.all([
    page.waitForResponse((r) => r.url().endsWith("/admin/demo-login") && r.request().method() === "POST", { timeout: 90000 }),
    page.evaluate(() => {
      const token =
        document.querySelector('input[name="_token"]')?.value ??
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
      if (!token) throw new Error("CSRF token not found");
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

  await page.goto(BASE + "/admin/platform-gallery?folder=hand-drawn", { timeout: 120000 });
  await page.waitForSelector('[role="button"][title="Click to preview full size"]', { timeout: 60000 });
  const thumbs = page.locator('[role="button"][title="Click to preview full size"]');
  console.log("thumbnails:", await thumbs.count());

  // Click the first thumbnail
  await thumbs.first().click();
  const dialog = page.locator('[role="dialog"][aria-label="Image preview"]');
  await dialog.waitFor({ state: "visible", timeout: 10000 });
  console.log("overlay opened: OK");

  // Assertions: image, label, name, key, forms
  const imgSrc = await dialog.locator("img").getAttribute("src");
  console.log("preview img src set:", !!imgSrc);
  const keyText = await dialog.locator("p:has-text('S3 key:')").innerText();
  console.log("key line:", keyText.slice(0, 80));
  if (!keyText.includes("assets/hand-drawn/")) throw new Error("S3 key missing/wrong");
  const renameVisible = await dialog.locator('form input[name="new_name"]').isVisible();
  const deleteVisible = await dialog.locator('form button:has-text("Delete")').isVisible();
  console.log("rename form visible:", renameVisible, "| delete button visible:", deleteVisible);
  const renameVal = await dialog.locator('input[name="new_name"]').inputValue();
  console.log("rename prefill (stem):", renameVal);
  if (!renameVal) throw new Error("rename stem empty");

  // SVG variant link: find a PNG+SVG card if present
  const svgCard = page.locator('div.glass:has(div:text-is("PNG + SVG"))').first();
  const hasSvgCard = (await svgCard.count()) > 0;
  if (hasSvgCard) {
    // Close current overlay via Escape
    await page.keyboard.press("Escape");
    await dialog.waitFor({ state: "hidden", timeout: 5000 });
    console.log("escape closes overlay: OK");
    await svgCard.locator('[role="button"][title="Click to preview full size"]').click();
    await dialog.waitFor({ state: "visible", timeout: 10000 });
    const svgLink = dialog.locator('a:has-text("Open SVG variant")');
    console.log("svg variant link visible:", await svgLink.isVisible());
    const href = await svgLink.getAttribute("href");
    console.log("svg href ends .svg:", (href || "").includes(".svg"));
  } else {
    console.log("no PNG+SVG card in this folder — skipping SVG link check");
    await page.keyboard.press("Escape");
    await dialog.waitFor({ state: "hidden", timeout: 5000 });
    console.log("escape closes overlay: OK");
  }

  // Close whatever is open, then test backdrop click
  if (await dialog.isVisible()) {
    await page.keyboard.press("Escape");
    await dialog.waitFor({ state: "hidden", timeout: 5000 });
  }
  await thumbs.first().click();
  await dialog.waitFor({ state: "visible", timeout: 10000 });
  await page.mouse.click(8, 8);
  await dialog.waitFor({ state: "hidden", timeout: 5000 });
  console.log("backdrop click closes overlay: OK");

  console.log("PASS");
} finally {
  await browser.close();
}
