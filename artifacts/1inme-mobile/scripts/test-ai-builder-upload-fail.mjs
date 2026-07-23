// Failure-path coverage for the AI-builder vault upload
// (app/links/[id]/ai-builder.tsx) — the mobile twin of the web spec
// tests/Browser/ai-builder-upload-instead.spec.ts "failed upload" test.
//
// A failed vault upload (quota exceeded, oversized file, 422 …) must NOT
// strand the creator: the screen shows an INLINE error message next to the
// upload control (not a transient alert that can be missed), the uploading
// flag resets in `finally` so the button flips back from "Uploading…" and is
// tappable again, and a retry through the same control succeeds.
//
// This is a source-driven test in the test-import-url.mjs convention — we run
// what ships, not a re-implementation:
//
//   1. The REAL `addImage` function is lifted verbatim from the screen and
//      executed with a stubbed image picker but the REAL `uploadWizardImage`
//      (lib/api/wizard.ts, transpiled from shipped TS) over an actual HTTP
//      round-trip to a local server speaking the /api/v1/links/wizard/image
//      contract.
//   2. Attempt 1: the server rejects with 422 { success:false,
//      error:{message} } — the FileController-style envelope. Asserts the
//      server's human message lands in `uploadError`, `uploading` is reset,
//      no image was appended, and NO alert fired.
//   3. Attempt 2 (retry): the server accepts — asserts `uploadError` clears
//      at the start of the attempt, the vault URL is appended to images[],
//      and `uploading` is reset again.
//   4. Source assertions: the inline `uploadError` message renders under BOTH
//      upload controls ("Add an image" and the preview box's "Upload
//      instead"), and the error/reset wiring lives in catch/finally.
//
// Run via `node scripts/test-ai-builder-upload-fail.mjs` (package script
// `test:ai-builder-upload-fail`, chained into `test:unit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import http from "node:http";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(
  join(root, "app", "links", "[id]", "ai-builder.tsx"),
  "utf8",
);
const wizardSrc = readFileSync(join(root, "lib", "api", "wizard.ts"), "utf8");
const apiSrc = readFileSync(join(root, "lib", "api.ts"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Balanced-brace extraction of a function from source.
// ---------------------------------------------------------------------------
function extractFn(src, signature, file) {
  const at = src.indexOf(signature);
  assert.notEqual(at, -1, `${signature} not found in ${file}`);
  const open = src.indexOf("{", at);
  let depth = 0;
  for (let i = open; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}") {
      depth--;
      if (depth === 0) return src.slice(at, i + 1);
    }
  }
  assert.fail(`could not balance braces for ${signature} in ${file}`);
}

// ---------------------------------------------------------------------------
// Transpile shipped TS (no re-implementation) — same pattern as
// test-import-url.mjs.
// ---------------------------------------------------------------------------
const tsMod = await import("typescript");
const ts = tsMod.default ?? tsMod;

function loadModule(source, fileName, requireMap) {
  const js = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2020,
      esModuleInterop: true,
    },
    fileName,
  }).outputText;
  const module = { exports: {} };
  const req = (name) => {
    if (name in requireMap) return requireMap[name];
    throw new Error(`unexpected import "${name}" in ${fileName}`);
  };
  // eslint-disable-next-line no-new-func
  new Function("require", "module", "exports", "__DEV__", js)(
    req,
    module,
    module.exports,
    false,
  );
  return module.exports;
}

const TEST_TOKEN = "e2e-upload-fail-token";

const apiModule = loadModule(apiSrc, "lib/api.ts", {
  "react-native": { Platform: { OS: "ios", select: (o) => o.ios } },
  "expo-constants": { default: { expoConfig: { version: "1.0.0" } } },
  "@/lib/secure": { getToken: async () => TEST_TOKEN },
});
const wizardModule = loadModule(wizardSrc, "lib/api/wizard.ts", {
  "@/lib/api": apiModule,
  "@/lib/secure": { getToken: async () => TEST_TOKEN },
});
assert.equal(
  typeof wizardModule.uploadWizardImage,
  "function",
  "real uploadWizardImage loaded",
);

// ---------------------------------------------------------------------------
// Local server speaking the /api/v1/links/wizard/image contract:
// attempt 1 → 422 FileController-style { success:false, error:{message} },
// attempt 2 → 200 { data:{ photo_url } }.
// ---------------------------------------------------------------------------
const QUOTA_ERROR =
  "Storage quota exceeded. Delete some files or upgrade your plan.";
const UPLOADED_URL = "/f/e2e-upload-fail.png";
const requests = [];
const server = http.createServer((req, res) => {
  // Drain the multipart body; we only assert on route/auth/sequence.
  req.on("data", () => {});
  req.on("end", () => {
    requests.push({
      method: req.method,
      url: req.url,
      auth: req.headers.authorization,
    });
    res.setHeader("Content-Type", "application/json");
    if (req.method !== "POST" || req.url !== "/api/v1/links/wizard/image") {
      res.statusCode = 404;
      res.end(JSON.stringify({ error: { message: "Not found" } }));
      return;
    }
    if (requests.length === 1) {
      res.statusCode = 422;
      res.end(
        JSON.stringify({
          success: false,
          error: { message: QUOTA_ERROR, code: "storage_quota_exceeded" },
        }),
      );
      return;
    }
    res.statusCode = 200;
    res.end(JSON.stringify({ data: { photo_url: UPLOADED_URL } }));
  });
});
await new Promise((r) => server.listen(0, "127.0.0.1", r));
process.env.EXPO_PUBLIC_API_BASE_URL = `http://127.0.0.1:${server.address().port}`;

// ---------------------------------------------------------------------------
// Lift the REAL addImage from the shipped screen and run it against a state
// harness (functional setState semantics) + a stubbed picker.
// ---------------------------------------------------------------------------
const addImageTs = extractFn(
  screenSrc,
  "async function addImage()",
  "ai-builder.tsx",
);
const addImageJs = ts.transpileModule(addImageTs, {
  compilerOptions: { target: ts.ScriptTarget.ES2020 },
}).outputText;

const state = {
  images: [],
  uploading: false,
  uploadError: null,
  estimate: 7, // pre-set so we can see setEstimate(null) on success
};
const alerts = [];
const setter = (key) => (v) => {
  state[key] = typeof v === "function" ? v(state[key]) : v;
};

const scope = {
  images: state.images, // rebound before each run below
  intake: { max_images: 25 },
  ImagePicker: {
    requestMediaLibraryPermissionsAsync: async () => ({ granted: true }),
    launchImageLibraryAsync: async () => ({
      canceled: false,
      assets: [
        {
          uri: "file:///tmp/e2e-upload-fail.png",
          mimeType: "image/png",
          fileName: "e2e-upload-fail.png",
        },
      ],
    }),
    MediaTypeOptions: { Images: "images" },
  },
  showAlert: (...args) => alerts.push(args),
  uploadWizardImage: wizardModule.uploadWizardImage,
  setUploading: (v) => {
    setter("uploading")(v);
    uploadingTimeline.push(state.uploading);
  },
  setUploadError: (v) => {
    setter("uploadError")(v);
    errorTimeline.push(state.uploadError);
  },
  setImages: setter("images"),
  setEstimate: setter("estimate"),
};
let uploadingTimeline = [];
let errorTimeline = [];

const runAddImage = () =>
  runExtractedStatements(addImageJs, "addImage()", scope, "addImage", {
    test: "test-ai-builder-upload-fail",
  });

try {
  // -------------------------------------------------------------------------
  // Attempt 1: the vault rejects with 422 success:false.
  // -------------------------------------------------------------------------
  console.log("[test-ai-builder-upload-fail] attempt 1 — 422 rejection");
  scope.images = state.images;
  await runAddImage();

  assert.equal(requests.length, 1, "one upload POST fired");
  assert.equal(requests[0].url, "/api/v1/links/wizard/image");
  assert.equal(requests[0].auth, `Bearer ${TEST_TOKEN}`);
  ok("upload hits POST /api/v1/links/wizard/image with the bearer token");

  // The server's human message lands in the INLINE error state.
  assert.equal(
    state.uploadError,
    QUOTA_ERROR,
    "the server's error message must land in uploadError",
  );
  ok("inline uploadError carries the server's 422 message");

  // Nothing was appended — the creator's images list is untouched.
  assert.deepEqual(state.images, [], "failed upload must not append an image");
  ok("no image lands in images[] on failure");

  // The uploading flag reset in `finally` — the button re-enables.
  assert.equal(state.uploading, false, "uploading must reset in finally");
  assert.deepEqual(
    uploadingTimeline,
    [true, false],
    "uploading toggled on, then back off",
  );
  ok("uploading resets in finally — the control re-enables for a retry");

  // The failure is inline, not a missable alert.
  assert.equal(alerts.length, 0, "upload failure must NOT go through showAlert");
  ok("no alert fired — the error is inline");

  // -------------------------------------------------------------------------
  // Attempt 2: retry through the same flow succeeds.
  // -------------------------------------------------------------------------
  console.log("[test-ai-builder-upload-fail] attempt 2 — retry succeeds");
  errorTimeline = [];
  uploadingTimeline = [];
  scope.images = state.images;
  await runAddImage();

  assert.equal(requests.length, 2, "retry fired a second upload POST");
  // The stale error clears at the START of the retry (first transition null).
  assert.equal(
    errorTimeline[0],
    null,
    "uploadError must clear at the start of the retry",
  );
  assert.equal(state.uploadError, null, "no error after a successful retry");
  ok("retry clears the stale inline error up front");

  assert.deepEqual(
    state.images,
    [UPLOADED_URL],
    "the vault URL from the retry lands in images[]",
  );
  assert.equal(state.estimate, null, "estimate invalidated after the upload");
  assert.equal(state.uploading, false, "uploading reset after the retry too");
  ok("retry appends the uploaded vault URL and resets state");
} finally {
  server.close();
}

// ---------------------------------------------------------------------------
// Source assertions: the inline error actually RENDERS, under both controls.
// ---------------------------------------------------------------------------
console.log("[test-ai-builder-upload-fail] inline rendering wiring");

const inlineErrorRenders =
  screenSrc.split("{uploadError ? (").length - 1;
assert.ok(
  inlineErrorRenders >= 2,
  "uploadError must render under BOTH upload controls " +
    "(Add an image + the preview box's Upload instead)",
);
assert.match(
  screenSrc,
  /\{uploadError \? \(\s*<Text style=\{\{ fontSize: 12, color: colors\.destructive \}\}>\s*\{uploadError\}/,
  "uploadError renders as destructive-colored inline text",
);
ok("inline uploadError text renders under both upload controls");

// The wiring lives in the shipped addImage: clear up front, set in catch,
// reset uploading in finally.
assert.match(
  addImageTs,
  /setUploading\(true\);\s*setUploadError\(null\);/,
  "each attempt clears the previous inline error",
);
assert.match(
  addImageTs,
  /catch[\s\S]*setUploadError\(/,
  "the catch branch sets the inline error",
);
assert.match(
  addImageTs,
  /finally\s*\{\s*setUploading\(false\);/,
  "finally resets the uploading flag",
);
ok("addImage wires clear-on-attempt / set-on-catch / reset-in-finally");

// Both upload controls share addImage and the uploading state on the label.
assert.match(
  screenSrc,
  /label=\{uploading \? "Uploading…" : "Add an image"\}/,
  "Add an image button shows the uploading state",
);
assert.match(
  screenSrc,
  /label=\{uploading \? "Uploading…" : "Upload instead"\}/,
  "Upload instead button shows the uploading state",
);
ok("both upload controls share the uploading label state");

console.log(`\n[test-ai-builder-upload-fail] all assertions passed (${passed} checks)`);
process.exit(0);
