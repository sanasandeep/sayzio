#!/usr/bin/env node
/**
 * Rendered e2e gate for the AI-builder "Upload instead" flow on mobile
 * (app/links/[id]/ai-builder.tsx), driving the REAL app in a headless
 * browser. Complements the source-driven wiring check
 * (test-ai-builder-upload-instead.mjs) by proving the RENDERED behaviour:
 *
 *   1. The AI builder screen shows the auto-sourced preview box while the
 *      creator has no uploads ("No uploads — preview the images we'd use").
 *   2. "Preview images" POSTs /ai-builder/source-preview and renders the
 *      extracted thumbnails ("Found on your links …").
 *   3. Tapping "Upload instead" opens the image picker (expo-image-picker's
 *      web file input, satisfied via Playwright's filechooser) and POSTs the
 *      picked file to /links/wizard/image (mocked upload).
 *   4. Once the upload resolves, the WHOLE preview box disappears (it is
 *      gated on images.length === 0) and the uploaded thumbnail renders in
 *      the Photos/uploads row instead.
 *   5. Removing the only upload (the "x" on the thumbnail) brings the
 *      preview box back — the "No uploads — preview the images we'd use"
 *      copy reappears with a WORKING preview action (tapping it POSTs
 *      /ai-builder/source-preview again), so creators are never stuck
 *      without image options after deleting their upload.
 *
 * Every /api/** call is intercepted against in-memory mocks so nothing
 * reaches a real backend; the upload itself is a real multipart POST from
 * the app's own uploadWizardImage. Like the sibling harnesses it boots its
 * own throwaway Expo web server unless APP_URL points at a running one, and
 * SKIPS on transient environment errors.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:ai-builder-upload-instead-e2e
 */

import { chromium } from "playwright";

import { NAV_TIMEOUT_MS, STEP_TIMEOUT_MS } from "./check-icon-fonts.mjs";
import {
  createExpoServerManager,
  runHarness,
  isTransientEnvError,
} from "./expo-web-server.mjs";

function log(...args) {
  console.log("[ai-upload-instead-e2e]", ...args);
}
function fail(msg) {
  console.error("[ai-upload-instead-e2e] FAIL:", msg);
  process.exit(1);
}
function skip(msg) {
  console.log("[ai-upload-instead-e2e] SKIP:", msg);
  process.exit(0);
}

const VIEWPORT = { width: 400, height: 720 };
const MOCK_TOKEN = "e2e-ai-upload-token";
const MOCK_USER = {
  id: 5748,
  display_name: "Upload Tester",
  email: "ai-upload@example.com",
};
const LINK_ID = 5748;
const EXPLICIT_APP_URL = process.env.APP_URL || null;

// A real 1x1 transparent PNG — used both as the picked file's bytes and as
// the body served for every mocked image URL so <Image> actually renders.
const TINY_PNG = Buffer.from(
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABh6FO1AAAAABJRU5ErkJggg==",
  "base64",
);

// Mocked server-side state. Paths on the app origin so RNW <Image> can load
// them through our route without external hosts.
const EXTRACTED_URL = "/e2e-mock-img/extracted-1.png";
// The upload mock mints a DISTINCT URL per upload (uploaded-photo-N.png) so
// the multi-upload phase can tell the two thumbnails apart; removeImage()
// filters by URL, so duplicates would vanish together and mask a bug.
const uploadedUrl = (n) => `/e2e-mock-img/uploaded-photo-${n}.png`;
const UPLOADED_URL = uploadedUrl(1);

const INTAKE = {
  ai_enabled: true,
  balance: 500,
  estimated_cost: 20,
  allowed_types: ["heading", "paragraph", "link"],
  max_links: 25,
  max_images: 25,
  max_files: 5,
  on_brand_allowed: false,
  image_search_enabled: false,
  brand_kit: null,
};

const PREVIEW = {
  extracted: [EXTRACTED_URL],
  generation: { enabled: true, cost_per_image: 5, slots: ["avatar", "cover"] },
};

let previewPosts = 0;
let uploadPosts = [];

async function mockApi(context) {
  // Serve the mocked vault/extracted image bytes.
  await context.route("**/e2e-mock-img/**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "image/png",
      body: TINY_PNG,
    });
  });

  await context.route("**/api/**", async (route) => {
    const req = route.request();
    const method = req.method();
    const path = new URL(req.url()).pathname;

    let body = { data: [] };

    if (/\/api\/v1\/auth\/me$/.test(path)) {
      body = { data: { user: MOCK_USER } };
    } else if (/\/api\/v1\/onboarding$/.test(path)) {
      body = {
        data: {
          onboarded_at: "2026-01-01T00:00:00Z",
          email_verified: true,
          has_links: true,
          has_biolink: true,
          whatsapp_pending: false,
          privacy_pending: false,
        },
      };
    } else if (
      new RegExp(`/api/v1/links/${LINK_ID}/ai-builder$`).test(path)
    ) {
      body = { data: INTAKE };
    } else if (
      new RegExp(`/api/v1/links/${LINK_ID}/ai-builder/source-preview$`).test(
        path,
      ) &&
      method === "POST"
    ) {
      previewPosts += 1;
      body = { data: PREVIEW };
    } else if (/\/api\/v1\/links\/wizard\/image$/.test(path) && method === "POST") {
      uploadPosts.push({
        contentType: req.headers()["content-type"] || "",
        postDataLength: (req.postDataBuffer() || Buffer.alloc(0)).length,
      });
      body = { data: { photo_url: uploadedUrl(uploadPosts.length) } };
    }

    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(body),
    });
  });
}

async function seedSession(context) {
  await context.addInitScript(
    ({ token, user }) => {
      try {
        window.localStorage.setItem("1inme.onboarding.complete", "1");
        window.localStorage.setItem("1inme.auth.token", token);
        window.localStorage.setItem("1inme.auth.user", JSON.stringify(user));
      } catch {}
    },
    { token: MOCK_TOKEN, user: MOCK_USER },
  );
}

// True while any rendered element shows the given image URL, either as an
// <img src> or as a RNW background-image (RNW <Image> uses either depending
// on version/props).
function urlRenderedPredicate() {
  return (url) => {
    const abs = new URL(url, window.location.origin).href;
    for (const img of document.querySelectorAll("img")) {
      if (img.src === abs || img.src.endsWith(url)) return true;
    }
    for (const el of document.querySelectorAll("[style]")) {
      const bg = el.style.backgroundImage || "";
      if (bg.includes(url)) return true;
    }
    return false;
  };
}

async function run(appUrl) {
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: VIEWPORT });
    await seedSession(context);
    await mockApi(context);
    const page = await context.newPage();
    page.setDefaultTimeout(STEP_TIMEOUT_MS);

    log("opening the AI builder screen…");
    await page.goto(`${appUrl}/links/${LINK_ID}/ai-builder`, {
      waitUntil: "domcontentloaded",
      timeout: NAV_TIMEOUT_MS,
    });

    // 1. With no uploads, the auto-sourced preview box is shown.
    const previewCopy = page.getByText(
      /No uploads — preview the images we'd use/,
    );
    await previewCopy.waitFor({ state: "visible" });
    const uploadInsteadBtn = page.getByText("Upload instead", { exact: true });
    await uploadInsteadBtn.waitFor({ state: "visible" });
    log("preview box + 'Upload instead' visible while images is empty");

    // 2. Running the preview renders the extracted thumbnails.
    await page.getByText("Preview images", { exact: true }).click();
    await page
      .getByText(/Found on your links — tap to keep or remove/)
      .waitFor({ state: "visible" });
    if (previewPosts === 0) {
      fail("'Preview images' never POSTed /ai-builder/source-preview");
    }
    await page.waitForFunction(urlRenderedPredicate(), EXTRACTED_URL);
    log("preview ran and rendered the extracted thumbnail");

    // 3. "Upload instead" opens the image picker; feed it a file via the
    //    web file input (expo-image-picker's web implementation).
    const chooserPromise = page.waitForEvent("filechooser", {
      timeout: STEP_TIMEOUT_MS,
    });
    await uploadInsteadBtn.click();
    const chooser = await chooserPromise;
    await chooser.setFiles({
      name: "my-photo.png",
      mimeType: "image/png",
      buffer: TINY_PNG,
    });
    log("image picker satisfied with a 1x1 PNG");

    // 4. The upload hits the wizard vault endpoint…
    const deadline = Date.now() + STEP_TIMEOUT_MS;
    while (uploadPosts.length === 0 && Date.now() < deadline) {
      await new Promise((r) => setTimeout(r, 100));
    }
    if (uploadPosts.length === 0) {
      fail("picking a file never POSTed /links/wizard/image");
    }
    const upload = uploadPosts[0];
    if (!/multipart\/form-data/.test(upload.contentType)) {
      fail(`upload was not multipart: ${upload.contentType}`);
    }
    if (upload.postDataLength === 0) {
      fail("upload POST carried an empty body");
    }
    log("upload POSTed multipart form data to /links/wizard/image");

    // …the preview box disappears outright (gated on images.length === 0)…
    await previewCopy.waitFor({ state: "hidden" });
    if (await page.getByText("Upload instead", { exact: true }).count()) {
      fail("'Upload instead' button still rendered after a successful upload");
    }
    if (
      await page
        .getByText(/Found on your links — tap to keep or remove/)
        .count()
    ) {
      fail("extracted-thumbnails section still rendered after the upload");
    }
    log("preview box (copy, button, thumbnails) is gone after the upload");

    // …and the uploaded thumbnail joins the Photos/uploads row.
    await page.waitForFunction(urlRenderedPredicate(), UPLOADED_URL);
    log("uploaded vault image renders in the uploads row");

    // 5. Removing the only upload (the "x" on the thumbnail) brings the
    //    preview box back so the creator can return to extracted/generated
    //    images.
    await page.getByTestId("ai-builder-remove-upload-0").click();
    await previewCopy.waitFor({ state: "visible" });
    await page
      .getByText("Upload instead", { exact: true })
      .waitFor({ state: "visible" });
    // The uploaded thumbnail is gone from the uploads row.
    await page.waitForFunction((url) => {
      const abs = new URL(url, window.location.origin).href;
      for (const img of document.querySelectorAll("img")) {
        if (img.src === abs || img.src.endsWith(url)) return false;
      }
      for (const el of document.querySelectorAll("[style]")) {
        if ((el.style.backgroundImage || "").includes(url)) return false;
      }
      return true;
    }, UPLOADED_URL);
    log("preview box reappeared after removing the only upload");

    // The preview action still works: tapping it POSTs source-preview again
    // and renders the extracted thumbnails.
    const previewPostsBefore = previewPosts;
    await page
      .getByText(/^(Preview images|Refresh preview)$/)
      .first()
      .click();
    const previewDeadline = Date.now() + STEP_TIMEOUT_MS;
    while (previewPosts === previewPostsBefore && Date.now() < previewDeadline) {
      await new Promise((r) => setTimeout(r, 100));
    }
    if (previewPosts === previewPostsBefore) {
      fail(
        "preview action after removing the upload never POSTed /ai-builder/source-preview",
      );
    }
    await page
      .getByText(/Found on your links — tap to keep or remove/)
      .waitFor({ state: "visible" });
    await page.waitForFunction(urlRenderedPredicate(), EXTRACTED_URL);
    log("preview action works again and re-renders the extracted thumbnail");

    // 6. Multi-upload: with TWO uploads, removing ONE must NOT re-show the
    //    preview box (it is gated on images.length === 0) and the remaining
    //    thumbnail must stay; only removing the LAST upload restores the
    //    preview flow. A regression here would confusingly show the
    //    auto-source box while the creator still has a photo attached.
    const pickFile = async (buttonLocator, name) => {
      const chooser = page.waitForEvent("filechooser", {
        timeout: STEP_TIMEOUT_MS,
      });
      await buttonLocator.click();
      await (await chooser).setFiles({
        name,
        mimeType: "image/png",
        buffer: TINY_PNG,
      });
    };
    const waitForUploadCount = async (n, what) => {
      const dl = Date.now() + STEP_TIMEOUT_MS;
      while (uploadPosts.length < n && Date.now() < dl) {
        await new Promise((r) => setTimeout(r, 100));
      }
      if (uploadPosts.length < n) fail(`${what} never POSTed /links/wizard/image`);
    };

    // First upload of this phase goes through "Upload instead" (box visible),
    // the second through the always-present "Add an image" button.
    await pickFile(
      page.getByText("Upload instead", { exact: true }),
      "multi-photo-a.png",
    );
    await waitForUploadCount(2, "second upload (multi phase)");
    const SECOND_URL = uploadedUrl(2);
    await page.waitForFunction(urlRenderedPredicate(), SECOND_URL);
    await previewCopy.waitFor({ state: "hidden" });
    await pickFile(
      page.getByText("Add an image", { exact: true }),
      "multi-photo-b.png",
    );
    await waitForUploadCount(3, "third upload (multi phase)");
    const THIRD_URL = uploadedUrl(3);
    await page.waitForFunction(urlRenderedPredicate(), THIRD_URL);
    const removeButtons = page.locator(
      '[data-testid^="ai-builder-remove-upload-"]',
    );
    if ((await removeButtons.count()) !== 2) {
      fail(
        `expected 2 upload thumbnails in the multi phase, got ${await removeButtons.count()}`,
      );
    }
    log("two uploads attached — both thumbnails render");

    // Remove the SECOND thumbnail specifically (index 1 = THIRD_URL, since
    // uploads render in insertion order). This proves removeImage targets the
    // tapped item: a regression that always drops the first/any item would
    // keep the count right but delete the WRONG photo.
    await page.getByTestId("ai-builder-remove-upload-1").click();
    // …exactly that photo's thumbnail leaves…
    await page.waitForFunction((url) => {
      const abs = new URL(url, window.location.origin).href;
      for (const img of document.querySelectorAll("img")) {
        if (img.src === abs || img.src.endsWith(url)) return false;
      }
      for (const el of document.querySelectorAll("[style]")) {
        if ((el.style.backgroundImage || "").includes(url)) return false;
      }
      return true;
    }, THIRD_URL);
    // …the FIRST thumbnail stays…
    if (!(await page.evaluate(urlRenderedPredicate(), SECOND_URL))) {
      fail(
        "tapping the SECOND thumbnail's x removed the FIRST photo (wrong item deleted)",
      );
    }
    if ((await removeButtons.count()) !== 1) {
      fail("expected exactly 1 remaining upload thumbnail after removing one");
    }
    // …and the preview box must STAY hidden while an upload remains.
    if (await previewCopy.count()) {
      fail(
        "preview box re-appeared after removing ONE of TWO uploads (should stay hidden while images remain)",
      );
    }
    if (await page.getByText("Upload instead", { exact: true }).count()) {
      fail("'Upload instead' re-appeared while an upload is still attached");
    }
    log(
      "removing one of two uploads keeps the preview box hidden and the other thumbnail intact",
    );

    // Removing the LAST upload restores the preview flow.
    await removeButtons.first().click();
    await previewCopy.waitFor({ state: "visible" });
    await page
      .getByText("Upload instead", { exact: true })
      .waitFor({ state: "visible" });
    log("removing the last upload brings the preview box back");

    await context.close();
    log(
      "PASS — 'Upload instead' hides the auto-sourced preview flow, removing one of several uploads keeps it hidden, and removing the last upload brings it back.",
    );
  } finally {
    await browser.close();
  }
}

async function main() {
  const { acquireServer, stopExpo } = createExpoServerManager(log);
  const server = await acquireServer("ai-upload-instead", EXPLICIT_APP_URL);
  if (!server) {
    skip("could not boot a throwaway Expo web server in this environment");
    return;
  }
  const appUrl = server.appUrl.replace(/\/$/, "");
  try {
    await run(appUrl);
  } catch (err) {
    if (isTransientEnvError(err)) {
      skip(`transient environment error: ${err.message}`);
    }
    throw err;
  } finally {
    if (!server.explicit && server.child) stopExpo(server.child);
  }
  // Explicit exit: the detached throwaway Expo child keeps the event loop
  // alive otherwise (siblings do the same); the manager's process "exit"
  // handler reaps it.
  process.exit(0);
}

// Termination guarantee: runHarness exits the process as soon as main
// settles and arms a watchdog, so a leaked handle can never stall the run.
runHarness(main, { log, onError: (err) => fail(err?.stack || String(err)) });
