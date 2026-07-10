// Regression test guarding the post-admin-removal state of the mobile app.
//
// The admin-removal work stripped every admin surface out of
// artifacts/1inme-mobile: the "Demo as admin" button is gone, `demoLogin`
// collapsed to a no-arg user-only session, impersonation was removed from the
// auth context, and there are no `/admin`, `/mail-settings`, `/schema-health`,
// `/cron-jobs`, or `/moderation` screens. Typecheck stays green even if one of
// those surfaces silently creeps back, so this test pins the intended shape:
//
//   1. Tapping "Demo as user" mints a *user* session (POST /auth/demo with
//      { role: "user" }, never "admin") and then hands off to redirectAfterAuth,
//      which — with nothing stashed — lands the visitor on the standard signed-in
//      dashboard ("/(tabs)"), i.e. the same place any real user lands.
//   2. None of the removed admin routes exist as Expo Router screens, so any
//      attempt to reach them falls through to the +not-found screen (a 404),
//      rather than rendering an admin surface. This holds for every user.
//   3. The Profile tab exposes no "Admin" section and no "Switch to admin
//      dashboard" row, and the auth context carries no impersonation / admin
//      -switch plumbing.
//
// Source-driven (NOT a headless browser click-through), matching the
// convention in test-auth-flow.mjs / test-login-auth-config.mjs. It runs the
// REAL demoLogin + redirectAfterAuth code against mocks and asserts route-file
// absence + screen wiring on the shipped source.
//
// Run via `node scripts/test-demo-admin-removal.mjs` (script `test:demo-admin-removal`).

import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

import { runExtractedCall, runExtractedStatements } from "./lib/extract.mjs";

const TEST = "test-demo-admin-removal";
const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const authCtxSrc = readFileSync(join(root, "contexts", "AuthContext.tsx"), "utf8");
const authNextSrc = readFileSync(join(root, "lib", "authNext.ts"), "utf8");
const loginSrc = readFileSync(join(root, "app", "(auth)", "index.tsx"), "utf8");
const profileSrc = readFileSync(join(root, "app", "(tabs)", "profile.tsx"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Lift the REAL demoLogin arrow function out of AuthContext.tsx. It is a
// `const demoLogin = useCallback(async () => {...}, [applySession]);` block;
// we grab the inner `async () => {...}` verbatim, strip the TS generic on
// apiFetch, and evaluate it with injected mocks so we run the shipped code.
// ---------------------------------------------------------------------------
function extractDemoLoginFn() {
  const m = authCtxSrc.match(
    /const demoLogin = useCallback\(\s*(async \(\) => \{[\s\S]*?\n    \}),\s*\[applySession\],\s*\);/,
  );
  assert.ok(m, "could not find demoLogin useCallback in AuthContext.tsx");
  // Drop the generic type argument on apiFetch<{...}>(...) so it's valid JS.
  return m[1].replace(/apiFetch<[\s\S]*?>\(/g, "apiFetch(");
}

// Lift the REAL redirectAfterAuth body and rebuild it as an async arrow that
// takes `router`, so we can invoke it with a mocked consumePendingPostAuthNext
// and assert the actual replace() target. Strips the `as never` TS cast.
function extractRedirectAfterAuthFn() {
  const m = authNextSrc.match(
    /export async function redirectAfterAuth\([\s\S]*?\): Promise<void> \{([\s\S]*?)\n\}/,
  );
  assert.ok(m, "could not find redirectAfterAuth in lib/authNext.ts");
  const bodyJs = m[1].replace(/ as never/g, "");
  return `async (router) => {${bodyJs}\n}`;
}

// ===========================================================================
// 1. "Demo as user" mints a USER session (never admin) and lands on the tabs.
// ===========================================================================
console.log(`[${TEST}] demo login is user-only and lands on the dashboard`);

{
  const calls = [];
  let applied = null;
  const apiFetch = async (path, init) => {
    calls.push({ path, init });
    return { data: { token: "demo-token", user: { id: 1, role: "user" } } };
  };
  const applySession = async (t, u) => {
    applied = { t, u };
  };

  const demoLogin = runExtractedCall(
    extractDemoLoginFn(),
    { apiFetch, applySession },
    "demoLogin",
    { test: TEST },
  );

  await demoLogin();
  assert.equal(calls.length, 1, "demoLogin must make exactly one request");
  assert.equal(calls[0].path, "/auth/demo", "demoLogin must POST /auth/demo");
  assert.equal(calls[0].init.method, "POST", "demoLogin must POST");
  const body = JSON.parse(calls[0].init.body);
  assert.deepEqual(
    body,
    { role: "user" },
    "demoLogin must request the USER role only",
  );
  assert.notEqual(
    body.role,
    "admin",
    "demoLogin must never request an admin demo session",
  );
  assert.equal(applied.t, "demo-token", "demoLogin must persist its session");
}
ok("demoLogin POSTs /auth/demo with { role: 'user' } (never admin) and signs in");

{
  // demoLogin takes NO arguments post-removal — the two demo buttons collapsed
  // into one. Pin the exported type so a role param can't sneak back.
  assert.ok(
    /demoLogin: \(\) => Promise<void>;/.test(authCtxSrc),
    "the AuthContext demoLogin type must be no-arg () => Promise<void>",
  );
}
ok("the demoLogin context method is no-arg (the admin variant is gone)");

// --- redirectAfterAuth: with nothing stashed, lands on the tabs dashboard ---
{
  // Lift the real redirectAfterAuth body and run it with a mocked
  // consumePendingPostAuthNext so we assert the actual fallback target.
  const bodyJs = extractRedirectAfterAuthFn();

  // No pending destination → the visitor lands on the signed-in tabs, i.e. the
  // same standard dashboard any authenticated user sees.
  const replaced = [];
  const redirectAfterAuth = runExtractedCall(
    bodyJs,
    {
      consumePendingPostAuthNext: async () => null,
      RESUMABLE_MAX_AGE_MS: 1,
    },
    "redirectAfterAuth",
    { test: TEST },
  );
  await redirectAfterAuth({ replace: (href) => replaced.push(href) });
  assert.deepEqual(
    replaced,
    ["/(tabs)"],
    "with nothing stashed, redirectAfterAuth must replace to the tabs dashboard",
  );
}
ok("redirectAfterAuth lands on the /(tabs) dashboard when nothing is stashed");

{
  // A stashed in-app destination is honoured (proves demo/login handoff isn't
  // hardwired to a single screen) — but it's still a plain user route.
  const replaced = [];
  const redirectAfterAuth = runExtractedCall(
    extractRedirectAfterAuthFn(),
    {
      consumePendingPostAuthNext: async () => "/links",
      RESUMABLE_MAX_AGE_MS: 1,
    },
    "redirectAfterAuth",
    { test: TEST },
  );
  await redirectAfterAuth({ replace: (href) => replaced.push(href) });
  assert.deepEqual(
    replaced,
    ["/links"],
    "a stashed post-auth destination must be honoured",
  );
}
ok("redirectAfterAuth honours a stashed in-app destination");

// --- login screen wiring: "Demo as user" → demoLogin() → redirectAfterAuth ---
{
  assert.ok(
    /label="Demo as user"/.test(loginSrc),
    'the login screen must offer a "Demo as user" button',
  );
  assert.ok(
    !/Demo as admin/i.test(loginSrc),
    'the login screen must NOT offer a "Demo as admin" button',
  );
  // onDemo mints the session then hands off to redirectAfterAuth (the router
  // takes the user to the standard dashboard).
  const onDemo = loginSrc.match(/const onDemo = async \(\) => \{[\s\S]*?\n  \};/);
  assert.ok(onDemo, "could not find onDemo in the login screen");
  assert.ok(
    /await demoLogin\(\);/.test(onDemo[0]),
    "onDemo must mint the session via the no-arg demoLogin()",
  );
  assert.ok(
    /await redirectAfterAuth\(router\);/.test(onDemo[0]),
    "onDemo must hand off to redirectAfterAuth so the user reaches the dashboard",
  );
}
ok('the "Demo as user" button runs demoLogin() then redirectAfterAuth(router)');

// ===========================================================================
// 2. No admin routes exist — any attempt to reach them 404s (+not-found).
// ===========================================================================
console.log(`[${TEST}] removed admin routes are unreachable for every user`);

const REMOVED_ROUTES = [
  "admin",
  "mail-settings",
  "schema-health",
  "cron-jobs",
  "moderation",
  "scheduled-jobs",
];

{
  const appDir = join(root, "app");
  for (const route of REMOVED_ROUTES) {
    // Expo Router is file-based: a route exists only if `app/<route>.tsx`,
    // `app/<route>/index.tsx`, or an `app/<route>/` directory does. Absence
    // means the router serves +not-found (a 404) instead of an admin screen.
    const candidates = [
      join(appDir, `${route}.tsx`),
      join(appDir, `${route}.ts`),
      join(appDir, route, "index.tsx"),
      join(appDir, route),
    ];
    for (const p of candidates) {
      assert.ok(
        !existsSync(p),
        `admin route "${route}" must not exist as an Expo Router screen (found ${p})`,
      );
    }
  }
}
ok(`no route file exists for any removed admin screen (${REMOVED_ROUTES.join(", ")})`);

{
  // The +not-found screen is what an unknown (e.g. /admin) route resolves to.
  const notFound = join(root, "app", "+not-found.tsx");
  assert.ok(existsSync(notFound), "app/+not-found.tsx must exist to 404 unknown routes");
  const src = readFileSync(notFound, "utf8");
  assert.ok(
    /doesn['&apos;]*t exist|does not exist/i.test(src),
    "the +not-found screen must render a 'screen doesn't exist' message",
  );
}
ok("unknown routes resolve to the +not-found (404) screen");

// ===========================================================================
// 3. The Profile tab has no Admin section / switch-to-admin row, and the auth
//    context carries no impersonation / admin-switch plumbing.
// ===========================================================================
console.log(`[${TEST}] profile tab & auth context expose no admin surface`);

{
  // No "Admin" section header and no "switch to admin" affordance in Profile.
  assert.ok(
    !/Switch to admin|admin dashboard/i.test(profileSrc),
    "the Profile tab must not offer a switch-to-admin dashboard row",
  );
  // No admin destinations wired into the Profile navigation lists.
  for (const route of REMOVED_ROUTES) {
    assert.ok(
      !new RegExp(`href:\\s*"/${route}"`).test(profileSrc),
      `the Profile tab must not link to the removed admin route "/${route}"`,
    );
  }
  // Guard against an "Admin" section label sneaking back into the sectionLabel
  // list (Appearance / Manage / Settings are the intended sections).
  const sectionLabels = [...profileSrc.matchAll(/sectionLabel[^>]*>\s*([\s\S]*?)\s*</g)]
    .map((m) => m[1].trim())
    .filter(Boolean);
  assert.ok(
    !sectionLabels.some((l) => /admin/i.test(l)),
    `the Profile tab must not render an "Admin" section (saw: ${sectionLabels.join(", ")})`,
  );
}
ok("the Profile tab exposes no Admin section and no switch-to-admin row");

{
  // The auth context must not re-introduce impersonation / admin-switch APIs.
  const forbidden = [
    "impersonate",
    "impersonation",
    "switchToAdmin",
    "adminContext",
    "getAdminContext",
    "asAdmin",
  ];
  for (const token of forbidden) {
    assert.ok(
      !new RegExp(`\\b${token}\\b`).test(authCtxSrc),
      `the auth context must not reference "${token}" (impersonation was removed)`,
    );
  }
}
ok("the auth context carries no impersonation / admin-switch plumbing");

console.log(`\n[${TEST}] all ${passed} checks passed`);
