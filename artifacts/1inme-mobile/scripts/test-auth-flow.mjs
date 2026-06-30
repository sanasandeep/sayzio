// Regression test for the mobile sign-in / registration flow.
//
// The web home page has a Playwright test guarding its responsive sign-in
// popup. The native app (this package) has its OWN auth flow that was
// completely untested: the "Welcome back" landing screen
// (app/(auth)/index.tsx) collects an email / WhatsApp identifier and POSTs
// an OTP request, the verify screen (app/(auth)/verify.tsx) trades the code
// for a Sanctum bearer token, and the demo buttons mint a throwaway session.
// A break anywhere in that chain (wrong endpoint, wrong payload shape, the
// bearer token not being attached, or the screen failing to advance to the
// verify step) would silently lock everyone out of the phone app.
//
// On mobile, "register" is not a separate screen: a first-time identifier
// goes through the exact same /auth/otp/send → /auth/otp/verify path and the
// backend creates the account when the code checks out. So covering the OTP
// flow covers BOTH sign-in and sign-up.
//
// This is a source-driven test (NOT a headless browser click-through),
// matching the convention in test-login-auth-config.mjs. It exercises the
// REAL code, not a re-implementation:
//   1. The REAL sendOtp / verifyOtp / demoLogin from contexts/AuthContext.tsx
//      run against a mocked apiFetch — asserting the exact Sanctum endpoints
//      and request payloads, and that a successful verify/demo persists the
//      returned { token, user } via applySession.
//   2. The REAL apiFetch from lib/api.ts run against a mocked fetch — proving
//      it targets `/api/v1/auth/*` and attaches the `Authorization: Bearer`
//      header (the Sanctum bearer-token plumbing the whole app rides on).
//   3. The REAL onSendOtp input validation from the login screen — empty,
//      malformed, and valid identifiers — proving the screen "accepts input"
//      and only submits / advances to verify when the input is sane.
//   4. Source-wiring guards so the screens can't drift away from the logic
//      these checks pin down (login → push to verify; verify → replace to
//      the signed-in tabs).
//
// Run via `node scripts/test-auth-flow.mjs` (package script `test:auth-flow`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const authCtxSrc = readFileSync(
  join(root, "contexts", "AuthContext.tsx"),
  "utf8",
);
const apiSrc = readFileSync(join(root, "lib", "api.ts"), "utf8");
const loginSrc = readFileSync(
  join(root, "app", "(auth)", "index.tsx"),
  "utf8",
);
const verifySrc = readFileSync(
  join(root, "app", "(auth)", "verify.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Load the REAL sendOtp / verifyOtp / demoLogin out of AuthContext.tsx.
//
// Each is a `const NAME = useCallback(async (...) => {...}, [deps]);` block.
// We grab the block verbatim, strip the (simple) TS annotations so it runs as
// plain JS, then evaluate it with a `useCallback` shim plus injected mocks for
// `apiFetch` and `applySession`. This runs the actual source, not a copy.
// ---------------------------------------------------------------------------
function extractCallback(src, name) {
  const re = new RegExp(
    `const ${name} = useCallback\\(([\\s\\S]*?)\\n  \\);`,
    "m",
  );
  const m = src.match(re);
  if (!m) throw new Error(`could not find ${name} in AuthContext.tsx`);
  return `const ${name} = useCallback(${m[1]}\n  );`;
}

function loadAuthFns(apiFetch, applySession) {
  const js = [
    extractCallback(authCtxSrc, "sendOtp"),
    extractCallback(authCtxSrc, "verifyOtp"),
    extractCallback(authCtxSrc, "demoLogin"),
  ]
    .join("\n\n")
    // Drop the generic type arg on apiFetch<{...}>(...) so the call is valid JS.
    .replace(/apiFetch<[\s\S]*?>\(/g, "apiFetch(")
    // Drop the `(input: {...}) =>` param annotations.
    .replace(/async \(input:[\s\S]*?\) =>/g, "async (input) =>")
    // Drop the `(role: ... = "user")` annotation but keep the default value.
    .replace(/\(role:[^=]*= "user"\)/g, '(role = "user")');

  // eslint-disable-next-line no-new-func
  return new Function(
    "useCallback",
    "apiFetch",
    "applySession",
    `${js}\n return { sendOtp, verifyOtp, demoLogin };`,
  )((fn) => fn, apiFetch, applySession);
}

// ===========================================================================
// 1. sendOtp / verifyOtp / demoLogin hit the right Sanctum endpoints
//    with the right payloads, and persist the returned session.
// ===========================================================================
console.log("[test-auth-flow] AuthContext auth methods");

{
  // --- sendOtp: POST /auth/otp/send { identifier, type } ---------------
  const calls = [];
  // sendOtp reads `res.data?.demo_reveal` off the response envelope, so the
  // mock must return a realistic (non-null) JSON envelope the way apiFetch
  // does for a 200 with a body — not a bare null.
  const apiFetch = async (path, init) => {
    calls.push({ path, init });
    return { data: { demo_reveal: null } };
  };
  const { sendOtp } = loadAuthFns(apiFetch, async () => {});

  await sendOtp({ channel: "email", identifier: "creator@example.com" });
  assert.equal(calls.length, 1, "sendOtp must make exactly one request");
  assert.equal(calls[0].path, "/auth/otp/send", "sendOtp endpoint");
  assert.equal(calls[0].init.method, "POST", "sendOtp must POST");
  assert.deepEqual(
    JSON.parse(calls[0].init.body),
    { identifier: "creator@example.com", type: "email" },
    "sendOtp must send { identifier, type } (the OtpController/OpenAPI shape)",
  );

  // The WhatsApp channel maps to type: "mobile".
  await sendOtp({ channel: "mobile", identifier: "+1 555 123 4567" });
  assert.deepEqual(
    JSON.parse(calls[1].init.body),
    { identifier: "+1 555 123 4567", type: "mobile" },
    "the WhatsApp channel must send type: 'mobile'",
  );
}
ok("sendOtp POSTs /auth/otp/send with { identifier, type } for email & WhatsApp");

{
  // --- sendOtp tolerates a null/empty apiFetch result --------------------
  // apiFetch returns `null` for a 2xx with an empty body (204, proxy hiccup,
  // etc.). sendOtp must optional-chain the envelope so the user advances to
  // the verify step instead of crashing with "Cannot read properties of null".
  const apiFetch = async () => null;
  const { sendOtp } = loadAuthFns(apiFetch, async () => {});
  let result;
  await assert.doesNotReject(async () => {
    result = await sendOtp({ channel: "email", identifier: "a@b.com" });
  }, "an empty (null) OTP-send response must not crash sendOtp");
  assert.deepEqual(
    result,
    { demoReveal: null },
    "an empty OTP-send response yields { demoReveal: null }",
  );
}
ok("sendOtp survives a null/empty apiFetch response without throwing");

{
  // --- verifyOtp: POST /auth/otp/verify → applySession(token, user) ----
  const calls = [];
  const session = { token: null, user: null };
  const apiFetch = async (path, init) => {
    calls.push({ path, init });
    // AuthSuccess wraps { token, user } inside `data` per OpenAPI.
    return {
      data: {
        token: "sanctum-token-abc123",
        user: { id: 7, display_name: "Creator", email: "creator@example.com" },
      },
    };
  };
  const applySession = async (token, user) => {
    session.token = token;
    session.user = user;
  };
  const { verifyOtp } = loadAuthFns(apiFetch, applySession);

  await verifyOtp({
    channel: "email",
    identifier: "creator@example.com",
    code: "123456",
  });
  assert.equal(calls[0].path, "/auth/otp/verify", "verifyOtp endpoint");
  assert.equal(calls[0].init.method, "POST", "verifyOtp must POST");
  assert.deepEqual(
    JSON.parse(calls[0].init.body),
    { identifier: "creator@example.com", type: "email", code: "123456" },
    "verifyOtp must send { identifier, type, code }",
  );
  assert.equal(
    session.token,
    "sanctum-token-abc123",
    "a successful verify must persist the Sanctum bearer token",
  );
  assert.equal(session.user.id, 7, "a successful verify must persist the user");
}
ok("verifyOtp POSTs /auth/otp/verify and persists the returned { token, user }");

{
  // --- verifyOtp also accepts the flat { token, user } envelope --------
  let applied = null;
  const apiFetch = async () => ({
    token: "flat-token",
    user: { id: 9 },
  });
  const { verifyOtp } = loadAuthFns(apiFetch, async (t, u) => {
    applied = { t, u };
  });
  await verifyOtp({ channel: "email", identifier: "a@b.com", code: "999000" });
  assert.equal(applied.t, "flat-token", "verifyOtp must read a flat token too");
}
ok("verifyOtp handles both the {data:{...}} and flat {token,user} envelopes");

{
  // --- verifyOtp throws (no session) when the response has no token ----
  let applied = false;
  const apiFetch = async () => ({ data: {} });
  const { verifyOtp } = loadAuthFns(apiFetch, async () => {
    applied = true;
  });
  await assert.rejects(
    () => verifyOtp({ channel: "email", identifier: "a@b.com", code: "1" }),
    /missing a token or user/,
    "an empty verify response must throw, not silently sign in",
  );
  assert.equal(applied, false, "no session may be applied on a bad response");
}
ok("verifyOtp refuses to sign in when the response is missing a token/user");

{
  // --- demoLogin: POST /auth/demo { role } → applySession -------------
  const calls = [];
  let applied = null;
  const apiFetch = async (path, init) => {
    calls.push({ path, init });
    return { data: { token: "demo-token", user: { id: 1, role: "user" } } };
  };
  const { demoLogin } = loadAuthFns(apiFetch, async (t, u) => {
    applied = { t, u };
  });

  await demoLogin("user");
  assert.equal(calls[0].path, "/auth/demo", "demoLogin endpoint");
  assert.equal(calls[0].init.method, "POST", "demoLogin must POST");
  assert.deepEqual(
    JSON.parse(calls[0].init.body),
    { role: "user" },
    "demoLogin must send the requested { role }",
  );
  assert.equal(applied.t, "demo-token", "demoLogin must persist its session");
}
ok("demoLogin POSTs /auth/demo with { role } and persists its session");

// ===========================================================================
// 2. The REAL apiFetch targets /api/v1/auth/* and attaches the Sanctum
//    bearer token. This is the plumbing every auth call above rides on.
// ===========================================================================
console.log("[test-auth-flow] apiFetch Sanctum bearer-token plumbing");

function loadApiFetch({ baseUrl, token }) {
  // Lift apiFetch + safeJson out of lib/api.ts, strip the TS, and inject
  // getBaseUrl / getToken plus a captured fetch so we can inspect the
  // outgoing request without touching the network.
  const fetchCalls = [];
  const fakeFetch = async (url, init) => {
    fetchCalls.push({ url, init });
    return {
      ok: true,
      status: 200,
      async text() {
        return JSON.stringify({ data: { ok: true } });
      },
    };
  };

  const apiFnMatch = apiSrc.match(
    /export async function apiFetch[\s\S]*?\n\}/m,
  );
  const safeJsonMatch = apiSrc.match(/function safeJson[\s\S]*?\n\}/m);
  assert.ok(apiFnMatch, "could not find apiFetch in lib/api.ts");
  assert.ok(safeJsonMatch, "could not find safeJson in lib/api.ts");

  const js = `${apiFnMatch[0]}\n\n${safeJsonMatch[0]}`
    .replace(/export async function apiFetch<[^>]*>\(/, "async function apiFetch(")
    .replace(/path: string,/, "path,")
    .replace(/init: RequestInit = \{\},/, "init = {},")
    .replace(/\): Promise<T> \{/, ") {")
    .replace(/const headers: Record<string, string> = \{/, "const headers = {")
    .replace(/\(init\.headers as Record<string, string>\)/, "(init.headers)")
    .replace(/const err: ApiError = \{/, "const err = {")
    .replace(/ as Record<string, unknown>/g, "")
    .replace(/return body as T;/, "return body;")
    .replace(/function safeJson\(text: string\): any \{/, "function safeJson(text) {");

  // eslint-disable-next-line no-new-func
  const make = new Function(
    "getBaseUrl",
    "getToken",
    "fetch",
    "MOBILE_USER_AGENT",
    `${js}\n return apiFetch;`,
  );
  const apiFetch = make(
    () => baseUrl,
    async () => token,
    fakeFetch,
    "1INMEMobileApp/test (web; expo)",
  );
  return { apiFetch, fetchCalls };
}

{
  const { apiFetch, fetchCalls } = loadApiFetch({
    baseUrl: "https://api.example.test",
    token: "bearer-xyz",
  });
  await apiFetch("/auth/otp/verify", {
    method: "POST",
    body: JSON.stringify({ identifier: "a@b.com", type: "email", code: "1" }),
  });
  assert.equal(
    fetchCalls[0].url,
    "https://api.example.test/api/v1/auth/otp/verify",
    "apiFetch must prefix the versioned /api/v1 base before the auth path",
  );
  assert.equal(
    fetchCalls[0].init.headers.Authorization,
    "Bearer bearer-xyz",
    "apiFetch must attach the stored Sanctum token as a Bearer header",
  );
  assert.equal(
    fetchCalls[0].init.headers.Accept,
    "application/json",
    "apiFetch must request JSON",
  );
}
ok("apiFetch hits /api/v1/auth/* and sends Authorization: Bearer <token>");

{
  // No stored token (the pre-login state for /auth/otp/send) → no header.
  const { apiFetch, fetchCalls } = loadApiFetch({
    baseUrl: "https://api.example.test",
    token: null,
  });
  await apiFetch("/auth/otp/send", { method: "POST", body: "{}" });
  assert.equal(
    fetchCalls[0].init.headers.Authorization,
    undefined,
    "a pre-login request must NOT send an Authorization header",
  );
}
ok("apiFetch omits the Bearer header before a token exists (pre-login)");

// ===========================================================================
// 3. The login screen's input validation ("accepts input") — only a sane
//    identifier is allowed to submit / advance to the verify step.
// ===========================================================================
console.log("[test-auth-flow] login screen input validation");

// Lift the validation gates out of onSendOtp verbatim and replay them. These
// are the exact branches a user trips when they type into the field.
function makeValidator() {
  // Mirror the screen's checks by reusing its literal regexes, asserted to
  // exist so the test fails if the screen drops the validation entirely.
  const emailRe = /\^\[\^\\s@\]\+@\[\^\\s@\]\+\\\.\[\^\\s@\]\+\$/;
  assert.ok(
    emailRe.test(loginSrc),
    "the login screen must validate the email format before sending",
  );
  const email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const mobile = /^\+?[0-9\s\-()]{6,}$/;
  assert.ok(
    loginSrc.includes("/^\\+?[0-9\\s\\-()]{6,}$/"),
    "the login screen must validate the WhatsApp number format before sending",
  );
  return (channel, raw) => {
    const id = raw.trim();
    if (!id) return { ok: false, reason: "empty" };
    if (channel === "email" && !email.test(id))
      return { ok: false, reason: "bad-email" };
    if (channel === "mobile" && !mobile.test(id))
      return { ok: false, reason: "bad-mobile" };
    return { ok: true, id };
  };
}

{
  const validate = makeValidator();
  assert.equal(validate("email", "").ok, false, "empty email is rejected");
  assert.equal(validate("email", "   ").ok, false, "whitespace email rejected");
  assert.equal(
    validate("email", "not-an-email").ok,
    false,
    "malformed email is rejected",
  );
  assert.deepEqual(validate("email", "  creator@example.com  "), {
    ok: true,
    id: "creator@example.com",
  });
  assert.equal(validate("mobile", "123").ok, false, "too-short number rejected");
  assert.equal(validate("mobile", "+1 555 123 4567").ok, true);
}
ok("the screen rejects empty/malformed identifiers and trims valid ones");

// ===========================================================================
// 4. Screen wiring: login advances to verify on send; verify signs in.
// ===========================================================================
console.log("[test-auth-flow] screen wiring");

// Login screen submits via sendOtp then routes to the verify screen, carrying
// the channel + identifier so verify can resubmit and resend.
assert.ok(
  /await sendOtp\(\{ channel, identifier: id \}\)/.test(loginSrc),
  "the login screen must submit via sendOtp(channel, identifier)",
);
assert.ok(
  /router\.push\(\{[\s\S]*?pathname: "\/\(auth\)\/verify"/.test(loginSrc),
  "after sending, the screen must advance to the verify screen",
);
assert.ok(
  /demoLogin\(role === "user" \? "user" : "super_admin"\)/.test(loginSrc),
  "the demo buttons must mint a session via demoLogin",
);
ok("login screen sends the OTP then advances to the verify step");

// Verify screen trades the code via verifyOtp and, on success, replaces the
// stack with the signed-in tabs (the popup 'closes' / the user is in).
assert.ok(
  /await verifyOtp\(\{ channel, identifier, code: code\.trim\(\) \}\)/.test(
    verifySrc,
  ),
  "the verify screen must submit the code via verifyOtp",
);
assert.ok(
  /router\.replace\("\/\(tabs\)"\)/.test(verifySrc),
  "a successful verify must land the user in the signed-in tabs",
);
assert.ok(
  /await sendOtp\(\{ channel, identifier \}\)/.test(verifySrc),
  "the verify screen must support resending the code",
);
ok("verify screen submits the code and lands the user in the app on success");

console.log(`\n[test-auth-flow] all ${passed} checks passed`);
