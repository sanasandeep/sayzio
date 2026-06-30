// Regression guard for the mobile "AI Digital Performer Specialist"
// (the AI Marketing Strategist) live-AI surfaces.
//
// When the AI engine is ON and the `marketing_strategist` capability is
// granted, three mobile paths talk to a *live* AI account and would fail
// SILENTLY if they ever drifted from the backend contract:
//
//   1. Chat-refine streams tokens over Server-Sent Events. The backend
//      (App\Modules\Api\Controllers\MarketingStrategistController::chatStream)
//      emits `event: open|token|done|error` frames, each a line
//      `data: {json}` terminated by a blank line. The mobile parser in
//      lib/api/marketingStrategist.ts must: dispatch each event, read the
//      `delta` string on token, require BOTH a message object AND a numeric
//      balance on done, surface code+message on error, normalize CRLF, and
//      re-assemble frames split across network read chunks. A break here
//      means the chat bubble just stops streaming with no error.
//   2. Applying a suggestion performs a real state-changing action, so the
//      backend 409s `confirmation_required` unless the request carries
//      `{ confirm: true }`. If the client ever stops sending it, every
//      one-tap apply would silently 409 and nothing would happen.
//   3. The export endpoint sits behind auth:sanctum, so the download MUST
//      carry the Bearer token (a plain open would 404). If the header is
//      dropped, "Download" silently fails.
//
// This is a source-driven test (NOT a headless browser run): it lifts the
// REAL chatStream parser and the REAL apply/dismiss request builders out of
// lib/api/marketingStrategist.ts and exercises them, matching the convention
// in test-auth-flow.mjs / test-wizard-flow.mjs. Run via
// `node scripts/test-strategy-stream.mjs` (package script `test:strategy-stream`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const src = readFileSync(
  join(root, "lib", "api", "marketingStrategist.ts"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Lift the REAL `chatStream` out of marketingStrategist.ts. It is an async
// arrow stored as an object property; we brace-match from its declaration to
// the matching close so we capture the whole function verbatim, strip the
// (simple) TS annotations so it runs as plain JS, then evaluate it with
// injected mocks for expoFetch / getToken / getBaseUrl. This runs the actual
// shipped parser, not a re-implementation.
// ---------------------------------------------------------------------------
function extractArrowMethod(source, marker) {
  const start = source.indexOf(marker);
  assert.ok(start !== -1, `could not find ${marker} in marketingStrategist.ts`);
  const open = source.indexOf("=> {", start) + 3; // index of the body `{`
  let depth = 0;
  let i = open;
  for (; i < source.length; i++) {
    const c = source[i];
    if (c === "{") depth += 1;
    else if (c === "}") {
      depth -= 1;
      if (depth === 0) {
        i += 1;
        break;
      }
    }
  }
  return source.slice(start, i);
}

function loadChatStream({ token, baseUrl, expoFetch }) {
  let js = extractArrowMethod(src, "chatStream: async (");
  js = js
    // Replace the typed param list (incl. the inline `handlers` object type)
    // up to the real arrow with a plain, untyped signature.
    .replace(
      /^chatStream: async \([\s\S]*?=> \{/,
      "const chatStream = async (id, message, handlers) => {",
    )
    // Strip the remaining inline TS annotations.
    .replace(/const headers: Record<string, string> = \{/, "const headers = {")
    .replace(
      /const isRecord = \(v: unknown\): v is Record<string, unknown> =>/,
      "const isRecord = (v) =>",
    )
    .replace(/let parsed: unknown;/, "let parsed;")
    .replace(/const flushFrame = \(frame: string\) =>/, "const flushFrame = (frame) =>")
    .replace(/m as unknown as StrategyChatMessage/, "m");

  // eslint-disable-next-line no-new-func
  const make = new Function(
    "expoFetch",
    "getToken",
    "getBaseUrl",
    "MOBILE_USER_AGENT",
    "TextDecoder",
    `${js}\n return chatStream;`,
  );
  return make(
    expoFetch,
    async () => token,
    () => baseUrl,
    "1INMEMobileApp/test (web; expo)",
    TextDecoder,
  );
}

// A fake `expo/fetch` Response whose body streams the given byte chunks back
// through the WHATWG reader interface the parser consumes.
function streamResponse(chunks, { ok = true, status = 200 } = {}) {
  const enc = new TextEncoder();
  const queue = chunks.map((c) =>
    typeof c === "string" ? enc.encode(c) : c,
  );
  let captured;
  const res = {
    ok,
    status,
    body: {
      getReader() {
        let idx = 0;
        return {
          async read() {
            if (idx >= queue.length) return { value: undefined, done: true };
            return { value: queue[idx++], done: false };
          },
        };
      },
    },
    async text() {
      return "stream body";
    },
  };
  const expoFetch = async (url, init) => {
    captured = { url, init };
    return res;
  };
  return { expoFetch, calls: () => captured };
}

function collector() {
  const tokens = [];
  let done = null;
  let error = null;
  return {
    handlers: {
      onToken: (d) => tokens.push(d),
      onDone: (d) => {
        done = d;
      },
      onError: (e) => {
        error = e;
      },
    },
    tokens,
    get done() {
      return done;
    },
    get error() {
      return error;
    },
  };
}

// ===========================================================================
// 1. Happy path: open is ignored, every token delta arrives in order, and
//    done fires once with the persisted assistant message + numeric balance.
// ===========================================================================
console.log("[test-strategy-stream] SSE happy path");
{
  const frames =
    'event: open\ndata: {"ok":true}\n\n' +
    'event: token\ndata: {"delta":"Here "}\n\n' +
    'event: token\ndata: {"delta":"is "}\n\n' +
    'event: token\ndata: {"delta":"your plan."}\n\n' +
    'event: done\ndata: {"message":{"id":42,"role":"assistant","content":"Here is your plan.","meta":{"credits_spent":4,"streamed":true}},"balance":97}\n\n';
  const { expoFetch, calls } = streamResponse([frames]);
  const c = collector();
  const chatStream = loadChatStream({
    token: "bearer-xyz",
    baseUrl: "https://api.example.test",
    expoFetch,
  });

  await chatStream(7, "Make it punchier", c.handlers);

  assert.deepEqual(
    c.tokens,
    ["Here ", "is ", "your plan."],
    "every token delta must be delivered, in order",
  );
  assert.ok(c.done, "done must fire on the done frame");
  assert.equal(c.done.message.id, 42, "done must carry the persisted message");
  assert.equal(
    c.done.message.content,
    "Here is your plan.",
    "done message content must match the assistant turn",
  );
  assert.equal(c.done.balance, 97, "done must carry the numeric balance");
  assert.equal(c.error, null, "no error on a clean stream");

  // The streamed request must authenticate as the live AI account.
  const req = calls();
  assert.equal(
    req.url,
    "https://api.example.test/api/v1/ai/marketing-strategist/7/chat",
    "chatStream must POST to the versioned strategist chat endpoint",
  );
  assert.equal(req.init.method, "POST", "chatStream must POST");
  assert.equal(
    req.init.headers.Authorization,
    "Bearer bearer-xyz",
    "the stream request must carry the Sanctum bearer token",
  );
  assert.equal(
    req.init.headers.Accept,
    "text/event-stream",
    "the stream request must ask for SSE so the backend branches to streaming",
  );
  assert.deepEqual(
    JSON.parse(req.init.body),
    { message: "Make it punchier" },
    "chatStream must send the refine message",
  );
}
ok("open ignored, tokens delivered in order, done carries message + balance, request is authed SSE");

// ===========================================================================
// 2. Frames split across network read chunks (incl. mid-data-line) must be
//    re-assembled — the real failure mode of a naive parser.
// ===========================================================================
console.log("[test-strategy-stream] chunk-split reassembly");
{
  const full =
    'event: token\ndata: {"delta":"Hello"}\n\n' +
    'event: done\ndata: {"message":{"id":1,"role":"assistant","content":"Hello"},"balance":5}\n\n';
  // Split at awkward points: mid-frame and mid-JSON.
  const a = full.slice(0, 20);
  const b = full.slice(20, 45);
  const d = full.slice(45);
  const { expoFetch } = streamResponse([a, b, d]);
  const c = collector();
  const chatStream = loadChatStream({
    token: "t",
    baseUrl: "https://x.test",
    expoFetch,
  });

  await chatStream(1, "hi", c.handlers);

  assert.deepEqual(c.tokens, ["Hello"], "a chunk-split token frame must still parse");
  assert.ok(c.done, "a chunk-split done frame must still complete the stream");
  assert.equal(c.done.balance, 5);
}
ok("frames split across reader chunks are buffered and re-assembled");

// ===========================================================================
// 3. CRLF line endings (some proxies rewrite \n to \r\n) must normalize.
// ===========================================================================
console.log("[test-strategy-stream] CRLF normalization");
{
  const frames =
    'event: token\r\ndata: {"delta":"X"}\r\n\r\n' +
    'event: done\r\ndata: {"message":{"id":2,"role":"assistant","content":"X"},"balance":1}\r\n\r\n';
  const { expoFetch } = streamResponse([frames]);
  const c = collector();
  const chatStream = loadChatStream({
    token: "t",
    baseUrl: "https://x.test",
    expoFetch,
  });

  await chatStream(2, "hi", c.handlers);

  assert.deepEqual(c.tokens, ["X"], "CRLF token frame must parse");
  assert.ok(c.done, "CRLF done frame must complete");
}
ok("CRLF (\\r\\n) frames are normalized and parsed");

// ===========================================================================
// 4. An error frame surfaces code + message and must NOT fake a completion.
// ===========================================================================
console.log("[test-strategy-stream] error frame");
{
  const frames =
    'event: open\ndata: {"ok":true}\n\n' +
    'event: error\ndata: {"code":"insufficient_credits","message":"Not enough coins."}\n\n';
  const { expoFetch } = streamResponse([frames]);
  const c = collector();
  const chatStream = loadChatStream({
    token: "t",
    baseUrl: "https://x.test",
    expoFetch,
  });

  await chatStream(3, "hi", c.handlers);

  assert.ok(c.error, "an error frame must call onError");
  assert.equal(c.error.code, "insufficient_credits", "onError must surface the backend code");
  assert.equal(c.error.message, "Not enough coins.", "onError must surface the message");
  assert.equal(c.done, null, "an errored stream must NOT report done");
}
ok("error frame surfaces { code, message } and does not fake a done");

// ===========================================================================
// 5. A malformed done frame (balance not a number) must NOT fake a
//    completion — the guard that stops a half-streamed bubble from being
//    treated as final.
// ===========================================================================
console.log("[test-strategy-stream] malformed done is rejected");
{
  const frames =
    'event: token\ndata: {"delta":"partial"}\n\n' +
    'event: done\ndata: {"message":{"id":9,"role":"assistant","content":"partial"},"balance":null}\n\n';
  const { expoFetch } = streamResponse([frames]);
  const c = collector();
  const chatStream = loadChatStream({
    token: "t",
    baseUrl: "https://x.test",
    expoFetch,
  });

  await chatStream(9, "hi", c.handlers);

  assert.deepEqual(c.tokens, ["partial"], "the token still streams");
  assert.equal(c.done, null, "a done frame without a numeric balance must be ignored");
}
ok("a done frame missing a numeric balance is not treated as a completion");

// ===========================================================================
// 6. A non-OK HTTP response (engine off / plan gate / throttle) is reported
//    via onError with the status and body, instead of hanging on a reader.
// ===========================================================================
console.log("[test-strategy-stream] HTTP failure path");
{
  const { expoFetch } = streamResponse([], { ok: false, status: 403 });
  const c = collector();
  const chatStream = loadChatStream({
    token: "t",
    baseUrl: "https://x.test",
    expoFetch,
  });

  await chatStream(4, "hi", c.handlers);

  assert.ok(c.error, "a non-OK response must call onError");
  assert.equal(c.error.code, "403", "onError must carry the HTTP status");
  assert.equal(c.done, null, "a failed request must not report done");
  assert.deepEqual(c.tokens, [], "no tokens on a failed request");
}
ok("a non-OK HTTP response is surfaced via onError, not a silent hang");

// ===========================================================================
// 7. apply / dismiss request builders: apply MUST send { confirm: true }
//    (or the backend 409s confirmation_required); dismiss must not.
// ===========================================================================
console.log("[test-strategy-stream] apply-confirm + dismiss request shape");
function loadObjectMethod(name) {
  // Each is `name: (id: number) => apiFetch<{...}>( PATH, { ...init } ).then((r) => r.data),`.
  const start = src.indexOf(`${name}: (id: number) =>`);
  assert.ok(start !== -1, `could not find ${name} in marketingStrategist.ts`);
  const end = src.indexOf(".then((r) => r.data),", start);
  assert.ok(end !== -1, `could not find the .then for ${name}`);
  let body = src.slice(start, end + ".then((r) => r.data)".length);
  body = body
    .replace(new RegExp(`^${name}: \\(id: number\\) =>`), `const ${name} = (id) =>`)
    .replace(/apiFetch<[\s\S]*?>\(/, "apiFetch(");
  // eslint-disable-next-line no-new-func
  const make = new Function("apiFetch", `${body}\n return ${name};`);
  const calls = [];
  const apiFetch = async (path, init) => {
    calls.push({ path, init });
    return { data: { status: "applied" } };
  };
  return { fn: make(apiFetch), calls };
}
{
  const { fn: applySuggestion, calls } = loadObjectMethod("applySuggestion");
  await applySuggestion(55);
  assert.equal(
    calls[0].path,
    "/ai/marketing-strategist/suggestions/55/apply",
    "applySuggestion must hit the apply endpoint",
  );
  assert.equal(calls[0].init.method, "POST", "applySuggestion must POST");
  assert.deepEqual(
    JSON.parse(calls[0].init.body),
    { confirm: true },
    "applySuggestion MUST send { confirm: true } or the backend 409s confirmation_required",
  );
}
ok("applySuggestion POSTs /suggestions/{id}/apply with { confirm: true }");
{
  const { fn: dismissSuggestion, calls } = loadObjectMethod("dismissSuggestion");
  await dismissSuggestion(56);
  assert.equal(
    calls[0].path,
    "/ai/marketing-strategist/suggestions/56/dismiss",
    "dismissSuggestion must hit the dismiss endpoint",
  );
  assert.equal(calls[0].init.method, "POST", "dismissSuggestion must POST");
  assert.equal(
    calls[0].init.body,
    undefined,
    "dismiss is not a confirmed action and sends no body",
  );
}
ok("dismissSuggestion POSTs /suggestions/{id}/dismiss with no confirm body");

// ===========================================================================
// 8. The authed export must attach the Bearer token on BOTH the web blob
//    fetch and the native downloadAsync (the endpoint is auth:sanctum).
// ===========================================================================
console.log("[test-strategy-stream] authed export carries the bearer token");
{
  const exportFn = src.slice(
    src.indexOf("export async function exportStrategy"),
  );
  assert.ok(
    /if \(token\) headers\.Authorization = `Bearer \$\{token\}`;/.test(exportFn),
    "exportStrategy must build an Authorization: Bearer header from the stored token",
  );
  assert.ok(
    /const res = await fetch\(url, \{ headers \}\);/.test(exportFn),
    "the web export path must pass the auth headers to fetch",
  );
  assert.ok(
    /downloadAsync\(url, target, \{ headers \}\)/.test(exportFn),
    "the native export path must pass the auth headers to downloadAsync",
  );
  assert.ok(
    /format=\$\{format\}/.test(exportFn),
    "export must forward the requested format (md|pdf) to the endpoint",
  );
}
ok("exportStrategy attaches the Bearer token to both the web and native download");

console.log(`\n[test-strategy-stream] all ${passed} checks passed`);
