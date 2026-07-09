// Regression coverage for the "Import from URL" shortcut screen
// (app/import-url.tsx) — the deep-link target (sayzio://import-url?url=... /
// https://sayzio.app/import-url?url=...) that wires three flows (Create QR,
// Add to calendar, Shorten link) to live API endpoints. A silent drift here
// breaks the share-sheet shortcut without anyone noticing, so this test pins:
//
//   1. Deep-link param parsing — the REAL `normalizeUrl` / `hostOf` functions
//      (evaluated verbatim from the shipped source) accept/normalize/reject
//      the URL shapes a share sheet can hand us, and the screen actually
//      routes `params.url` through `normalizeUrl`.
//   2. The picker — the shipped `actions` array offers exactly the three
//      flows (qr / calendar / shorten) and each action is gated on a valid
//      URL (`disabled={!url}`).
//   3. Payload wiring — each mutation's REAL `createQrCode(...)` /
//      `createCalendarEvent(...)` / `createLink(...)` call expression is
//      lifted from source and executed against stubs, asserting the exact
//      payload contract the backend expects (type:"url" QR payload, event
//      URL-in-description + `YYYY-MM-DDTHH:MM` start_at, type:"short" link).
//   4. Shorten flow END-TO-END through the shipped client stack: the screen's
//      real `createLink(...)` expression → the REAL `createLink` helper
//      (lib/api/links.ts) → the REAL `apiFetch` (lib/api.ts, transpiled from
//      the shipped TS, with Bearer token + envelope handling) → an actual
//      HTTP round-trip to a local server speaking the `/api/v1/links`
//      contract — asserting method, path, auth header, JSON body, and that
//      the `{data:{link}}` envelope unwraps into the Link whose `short_url`
//      the screen shows. The error path (`{error:{message,code}}` → ApiError
//      with `code`, which handlePlanLockedError reads) is asserted too.
//
// Follows the source-driven convention of test-quick-contact.mjs /
// test-calendar-event-quota.mjs: we run what ships, not a re-implementation.
//
// Run via `node scripts/test-import-url.mjs` (package script
// `test:import-url`, chained into `test:unit` → the mobile-unit workflow).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import http from "node:http";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall as runExtractedCallShared } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(join(root, "app", "import-url.tsx"), "utf8");
const apiSrc = readFileSync(join(root, "lib", "api.ts"), "utf8");
const linksSrc = readFileSync(join(root, "lib", "api", "links.ts"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Helpers: balanced extraction of a function body / call arguments from source.
// ---------------------------------------------------------------------------
function extractFn(src, signature, file) {
  const at = src.indexOf(signature);
  assert.notEqual(at, -1, `${signature} not found in ${file}`);
  const open = src.indexOf("{", at);
  let depth = 0;
  let end = -1;
  for (let i = open; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}") {
      depth--;
      if (depth === 0) {
        end = i + 1;
        break;
      }
    }
  }
  assert.notEqual(end, -1, `could not balance braces for ${signature}`);
  return src.slice(at, end);
}

// Balanced-paren extraction of the argument list of `name(` starting at from.
function extractCallArgs(src, name, file, from = 0) {
  const at = src.indexOf(`${name}(`, from);
  assert.notEqual(at, -1, `${name}( not found in ${file}`);
  const open = at + name.length;
  let depth = 0;
  let end = -1;
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    if (ch === "(") depth++;
    else if (ch === ")") {
      depth--;
      if (depth === 0) {
        end = i;
        break;
      }
    }
  }
  assert.notEqual(end, -1, `unterminated ${name}(...) call in ${file}`);
  return src.slice(open + 1, end);
}

// ---------------------------------------------------------------------------
// Resilient evaluation of extracted call expressions: the shared helper in
// scripts/lib/extract.mjs (originally written here) defaults unknown screen
// variables to null and prints an actionable "new variable X — extend the
// test" message instead of a raw ReferenceError.
// ---------------------------------------------------------------------------
const runExtractedCall = (expr, scope, label) =>
  runExtractedCallShared(expr, scope, label, { test: "test-import-url" });

// ===========================================================================
// 1. Deep-link param parsing: the REAL normalizeUrl / hostOf.
// ===========================================================================
console.log("[test-import-url] normalizeUrl / hostOf (real source)");

const normalizeUrlSrc = extractFn(
  screenSrc,
  "function normalizeUrl",
  "import-url.tsx",
).replace(
  "function normalizeUrl(raw: string): string | null",
  "function normalizeUrl(raw)",
);
const hostOfSrc = extractFn(screenSrc, "function hostOf", "import-url.tsx").replace(
  "function hostOf(url: string): string",
  "function hostOf(url)",
);

// eslint-disable-next-line no-new-func
const { normalizeUrl, hostOf } = new Function(
  `${normalizeUrlSrc}\n${hostOfSrc}\nreturn { normalizeUrl, hostOf };`,
)();

{
  // Plain https URL passes through (URL#toString adds the trailing slash).
  assert.equal(normalizeUrl("https://example.com"), "https://example.com/");
  // Path + query survive.
  assert.equal(
    normalizeUrl("https://example.com/a/b?x=1&y=2"),
    "https://example.com/a/b?x=1&y=2",
  );
  // Bare domain gets https:// prepended (the share-sheet/paste case).
  assert.equal(normalizeUrl("example.com/page"), "https://example.com/page");
  // http is kept, not upgraded.
  assert.equal(normalizeUrl("http://example.com"), "http://example.com/");
  // Whitespace is trimmed.
  assert.equal(normalizeUrl("  example.com  "), "https://example.com/");
  ok("valid URLs normalize (scheme added, path/query kept)");

  // Rejections: empty, no dot in hostname, garbage.
  assert.equal(normalizeUrl(""), null);
  assert.equal(normalizeUrl("   "), null);
  assert.equal(normalizeUrl("not a url"), null);
  assert.equal(normalizeUrl("localhost"), null, "dotless hostname rejected");
  assert.equal(normalizeUrl("https://localhost/x"), null);
  ok("invalid / dotless URLs are rejected (null)");

  assert.equal(hostOf("https://www.example.com/page"), "example.com");
  assert.equal(hostOf("https://sub.example.com"), "sub.example.com");
  assert.equal(hostOf("garbage"), "");
  ok("hostOf strips www. and fails soft");
}

// The screen must actually route the deep-link param through normalizeUrl.
assert.match(
  screenSrc,
  /normalizeUrl\(typeof params\.url === "string" \? params\.url : ""\)/,
  "sharedUrl must be derived by normalizeUrl(params.url)",
);
assert.match(
  screenSrc,
  /useLocalSearchParams<\{ url\?: string; title\?: string \}>/,
  "screen must read url/title deep-link params",
);
// Manual-paste fallback also goes through normalizeUrl.
assert.match(
  screenSrc,
  /const url = sharedUrl \?\? normalizeUrl\(manualUrl\);/,
  "manual URL fallback must normalize too",
);
ok("deep-link param + manual paste both flow through normalizeUrl");

// ===========================================================================
// 2. The picker: exactly the three flows, each gated on a valid URL.
// ===========================================================================
console.log("[test-import-url] action picker");

{
  const at = screenSrc.indexOf("const actions");
  assert.notEqual(at, -1, "actions array not found");
  const eq = screenSrc.indexOf("= [", at);
  assert.notEqual(eq, -1, "actions assignment not found");
  const open = screenSrc.indexOf("[", eq);
  let depth = 0;
  let end = -1;
  for (let i = open; i < screenSrc.length; i++) {
    if (screenSrc[i] === "[") depth++;
    else if (screenSrc[i] === "]") {
      depth--;
      if (depth === 0) {
        end = i;
        break;
      }
    }
  }
  assert.notEqual(end, -1, "unterminated actions literal");
  // eslint-disable-next-line no-new-func
  const actions = new Function(
    `return ${screenSrc.slice(open, end + 1)};`,
  )();

  assert.equal(actions.length, 3, "picker must offer exactly three actions");
  assert.deepEqual(
    actions.map((a) => a.key),
    ["qr", "calendar", "shorten"],
    "picker keys must be qr / calendar / shorten",
  );
  for (const a of actions) {
    assert.ok(a.label && a.hint && a.icon, `action ${a.key} needs label/hint/icon`);
  }
  ok("picker offers exactly qr / calendar / shorten");

  assert.match(
    screenSrc,
    /disabled=\{!url\}/,
    "actions must be disabled until a valid URL exists",
  );
  ok("actions are gated on a valid URL");
}

// ===========================================================================
// 3. Payload wiring — the REAL call expressions, run against stubs.
// ===========================================================================
console.log("[test-import-url] mutation payload contracts");

// Strip TS non-null assertions (`foo!`) from any identifier, so a new `bar!`
// in the screen doesn't leak invalid syntax into the evaluated expression.
// The negative lookahead keeps `!=` / `!==` comparisons intact.
const stripBang = (s) => s.replace(/([A-Za-z_$][\w$]*)!(?!=)/g, "$1");

{
  // Quick QR: createQrCode({ name, type: "url", payload: { url } })
  const qrArgs = stripBang(extractCallArgs(screenSrc, "createQrCode", "import-url.tsx"));
  const runQr = (createQrCode, qrName, defaultName, url) =>
    runExtractedCall(
      `createQrCode(${qrArgs})`,
      { createQrCode, qrName, defaultName, url },
      "createQrCode",
    );
  let got = null;
  runQr((p) => (got = p), null, "example.com", "https://example.com/");
  assert.deepEqual(got, {
    name: "example.com",
    type: "url",
    payload: { url: "https://example.com/" },
  });
  // Empty name falls back to "Shared link".
  runQr((p) => (got = p), null, "", "https://example.com/");
  assert.equal(got.name, "Shared link");
  ok("QR flow posts type:'url' with the shared URL as payload");
}

{
  // Calendar: createCalendarEvent(id, { title, description: url, start_at })
  const evArgs = stripBang(
    extractCallArgs(screenSrc, "createCalendarEvent", "import-url.tsx"),
  );
  const runEv = (
    createCalendarEvent,
    selectedCalendarId,
    evTitle,
    sharedTitle,
    host,
    url,
    evDate,
    evTime,
    evLocation,
  ) =>
    runExtractedCall(
      `createCalendarEvent(${evArgs})`,
      {
        createCalendarEvent,
        selectedCalendarId,
        evTitle,
        sharedTitle,
        host,
        url,
        evDate,
        evTime,
        evLocation,
      },
      "createCalendarEvent",
    );
  let gotId = null;
  let gotBody = null;
  runEv(
    (id, body) => ((gotId = id), (gotBody = body)),
    42,
    null,
    "My article",
    "example.com",
    "https://example.com/post",
    "2026-07-09",
    "09:30",
    "  Cafe Central  ",
  );
  assert.equal(gotId, 42, "event goes to the selected calendar");
  assert.equal(gotBody.title, "My article", "shared title wins as event title");
  assert.equal(gotBody.location, "Cafe Central", "location is trimmed onto the event");
  assert.equal(
    gotBody.description,
    "https://example.com/post",
    "the shared URL must travel in the event description",
  );
  assert.equal(gotBody.start_at, "2026-07-09T09:30", "start_at is DATE T TIME");
  assert.equal(
    gotBody.location,
    "Cafe Central",
    "detected/typed location is trimmed into the event payload",
  );
  // No title → host fallback; no location → null.
  runEv((id, body) => (gotBody = body), 42, null, "", "example.com", "https://example.com/", "2026-07-09", "09:30", null);
  assert.equal(gotBody.title, "example.com");
  assert.equal(gotBody.location, null, "no location → null, never ''");
  ok("calendar flow posts URL-in-description with YYYY-MM-DDTHH:MM start");

  // And the screen's own validators guard the formats it sends.
  const dateRe = new RegExp(
    (screenSrc.match(/const DATE_RE = (\/.*\/);/) || [])[1].slice(1, -1),
  );
  const timeRe = new RegExp(
    (screenSrc.match(/const TIME_RE = (\/.*\/);/) || [])[1].slice(1, -1),
  );
  assert.ok(dateRe.test("2026-07-09") && !dateRe.test("07/09/2026"));
  assert.ok(timeRe.test("23:59") && !timeRe.test("9:00") && !timeRe.test("24:00"));
  ok("date/time validators match the wire format");
}

// The screen's shorten call expression (reused for the e2e leg below).
const shortenArgs = stripBang(
  extractCallArgs(screenSrc, "createLink", "import-url.tsx"),
);
const runShorten = (createLink, url, sharedTitle) =>
  runExtractedCall(
    `createLink(${shortenArgs})`,
    { createLink, url, sharedTitle },
    "createLink",
  );

{
  let got = null;
  runShorten((p) => (got = p), "https://example.com/long", "A title");
  assert.deepEqual(got, {
    type: "short",
    long_url: "https://example.com/long",
    title: "A title",
  });
  runShorten((p) => (got = p), "https://example.com/long", "");
  assert.equal(got.title, null, "missing title must be null, not ''");
  ok("shorten flow posts type:'short' with long_url");
}

// ===========================================================================
// 4. Shorten flow END-TO-END: real screen expression → real createLink →
//    real apiFetch → actual HTTP round-trip against the /api/v1/links
//    contract (standard {data:{link}} / {error:{...}} envelopes).
// ===========================================================================
console.log("[test-import-url] shorten flow end-to-end (real client stack over HTTP)");

// Transpile the shipped TS modules (no re-implementation) to CJS and load
// them with the native-only imports stubbed out.
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

const TEST_TOKEN = "e2e-import-url-token";

const apiModule = loadModule(apiSrc, "lib/api.ts", {
  "react-native": { Platform: { OS: "ios", select: (o) => o.ios } },
  "expo-constants": { default: { expoConfig: { version: "1.0.0" } } },
  "@/lib/secure": { getToken: async () => TEST_TOKEN },
});
const linksModule = loadModule(linksSrc, "lib/api/links.ts", {
  "react-native": { Platform: { OS: "ios", select: (o) => o.ios } },
  "@/lib/api": apiModule,
  "@/lib/secure": { getToken: async () => TEST_TOKEN },
});

assert.equal(typeof linksModule.createLink, "function", "real createLink loaded");

// A local server speaking the documented /api/v1/links contract.
const requests = [];
const server = http.createServer((req, res) => {
  let body = "";
  req.on("data", (c) => (body += c));
  req.on("end", () => {
    const parsed = body ? JSON.parse(body) : null;
    requests.push({
      method: req.method,
      url: req.url,
      auth: req.headers.authorization,
      contentType: req.headers["content-type"],
      body: parsed,
    });
    res.setHeader("Content-Type", "application/json");
    if (req.method === "POST" && req.url === "/api/v1/links") {
      if (parsed?.long_url === "https://blocked.example.com/") {
        // Plan-gated rejection in the standard error envelope.
        res.statusCode = 403;
        res.end(
          JSON.stringify({
            error: {
              message: "Upgrade your plan to create more links.",
              code: "plan_limit_reached",
              details: { feature: "max_links" },
            },
          }),
        );
        return;
      }
      res.statusCode = 201;
      res.end(
        JSON.stringify({
          data: {
            link: {
              id: 777,
              type: parsed.type,
              alias: "e2eabc",
              title: parsed.title,
              long_url: parsed.long_url,
              short_url: "https://1in.me/e2eabc",
              visibility: "public",
              is_active: true,
            },
          },
        }),
      );
      return;
    }
    res.statusCode = 404;
    res.end(JSON.stringify({ error: { message: "Not found" } }));
  });
});
await new Promise((r) => server.listen(0, "127.0.0.1", r));
const port = server.address().port;
process.env.EXPO_PUBLIC_API_BASE_URL = `http://127.0.0.1:${port}`;

try {
  // Happy path: deep-link URL → normalizeUrl → screen's createLink expression
  // → real helper → HTTP → envelope unwrap → Link with short_url.
  const sharedRaw = "example.com/some/article?ref=share"; // as a share sheet might pass it
  const url = normalizeUrl(sharedRaw);
  assert.equal(url, "https://example.com/some/article?ref=share");

  const link = await runShorten(linksModule.createLink, url, "Some article");
  assert.equal(link.id, 777);
  assert.equal(
    link.short_url,
    "https://1in.me/e2eabc",
    "screen shows the short_url the API returned",
  );

  assert.equal(requests.length, 1);
  const r = requests[0];
  assert.equal(r.method, "POST");
  assert.equal(r.url, "/api/v1/links", "must hit POST /api/v1/links");
  assert.equal(r.auth, `Bearer ${TEST_TOKEN}`, "must send the bearer token");
  assert.match(r.contentType, /application\/json/);
  assert.deepEqual(r.body, {
    type: "short",
    long_url: "https://example.com/some/article?ref=share",
    title: "Some article",
  });
  ok("POST /api/v1/links round-trip: auth + payload + envelope unwrap");

  // Error path: the {error:{message,code}} envelope becomes an ApiError with
  // the code handlePlanLockedError keys on.
  let thrown = null;
  try {
    await runShorten(linksModule.createLink, "https://blocked.example.com/", "");
  } catch (e) {
    thrown = e;
  }
  assert.ok(thrown, "plan-gated rejection must throw");
  assert.equal(thrown.status, 403);
  assert.equal(thrown.code, "plan_limit_reached");
  assert.equal(thrown.message, "Upgrade your plan to create more links.");
  assert.deepEqual(thrown.details, { feature: "max_links" });
  ok("error envelope surfaces ApiError {status, code, details} for the upgrade prompt");

  // The screen must route plan-locked errors through handlePlanLockedError.
  assert.match(
    screenSrc,
    /handlePlanLockedError\(e\)/,
    "mutations must offer the upgrade prompt on plan-gated errors",
  );
  ok("screen wires plan-locked errors to the upgrade prompt");
} finally {
  server.close();
}

console.log(`\n[test-import-url] all assertions passed (${passed} groups)`);
