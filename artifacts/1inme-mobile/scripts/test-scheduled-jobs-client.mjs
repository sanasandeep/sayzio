// Source-driven guard: the mobile admin "Scheduled Jobs" API client
// (lib/api/scheduledJobs.ts) can't silently drift from the Laravel routes
// or lose its envelope/encoding invariants during a refactor.
//
// Asserts, from the REAL shipped sources (no hard-coded third copy):
//   1. Every endpoint the client calls (method + path) exists verbatim in
//      the Laravel API routes (artifacts/1inme/routes/api.php), and vice
//      versa — every /admin/scheduled-jobs route the server exposes is
//      consumed by the client (no dead server surface, no phantom client
//      endpoint that would 404).
//   2. Every per-job endpoint URI-encodes the job key. Job keys contain ':'
//      (e.g. "contacts:sync") so a raw interpolation would build a path the
//      server route constraint ([A-Za-z0-9:_\-]+) still matches but that an
//      intermediary/URL parser can mangle; encodeURIComponent is the
//      established invariant for these keys.
//   3. Every exported async function unwraps the {data} envelope (returns
//      res.data). apiFetch returns the RAW envelope and the type system
//      will NOT catch a missing unwrap (see mobile-apifetch-envelope memory),
//      so this must be asserted at the source level.
//
// Following the convention in test-pairing-create-open.mjs / test-auth-next.mjs
// we extract the real logic from source rather than running a TS/RN runner.
//
// Run via `node scripts/test-scheduled-jobs-client.mjs` (package script
// `test:scheduled-jobs-client`, chained into `test:unit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const CLIENT_TS = join(root, "lib", "api", "scheduledJobs.ts");
const ROUTES_PHP = resolve(root, "..", "1inme", "routes", "api.php");

const clientSrc = readFileSync(CLIENT_TS, "utf8");
const routesSrc = readFileSync(ROUTES_PHP, "utf8");

// ---------------------------------------------------------------------------
// 1. Laravel routes: every /admin/scheduled-jobs* route (method + template).
//    Normalizes `{key}` to a `:key` placeholder for comparison.
// ---------------------------------------------------------------------------
const serverRoutes = new Set();
for (const m of routesSrc.matchAll(
  /Route::(get|post|put|patch|delete)\s*\(\s*'(\/admin\/scheduled-jobs[^']*)'/g,
)) {
  const method = m[1].toUpperCase();
  const template = m[2].replace(/\{[a-zA-Z_]+\}/g, ":key");
  serverRoutes.add(`${method} ${template}`);
}
assert.ok(
  serverRoutes.size >= 5,
  `expected >=5 /admin/scheduled-jobs routes in api.php, found ${serverRoutes.size} — ` +
    "did the Scheduled Jobs API move or get renamed?",
);

// The per-job routes must keep a `where` constraint that allows ':' in the
// key — job keys like "contacts:sync" would otherwise 404 at the router.
for (const line of routesSrc.split("\n")) {
  if (!line.includes("/admin/scheduled-jobs/{key}")) continue;
  assert.match(
    line,
    /->where\('key',\s*'\[[^']*:[^']*\]\+'\)/,
    `per-job scheduled-jobs route lost its ':'-allowing where('key', ...) constraint:\n${line.trim()}`,
  );
}

// ---------------------------------------------------------------------------
// 2. Mobile client: every apiFetch call site (method + path template).
//    Handles both plain string paths and template literals with an
//    encodeURIComponent(key) segment (normalized to `:key`).
// ---------------------------------------------------------------------------
// Split the client into exported async function blocks so per-function
// invariants (unwrap, encoding) can be asserted individually.
const fnBlocks = [];
{
  const re = /export async function (\w+)\s*\(/g;
  const starts = [];
  let m;
  while ((m = re.exec(clientSrc)) !== null) starts.push({ name: m[1], at: m.index });
  for (let i = 0; i < starts.length; i++) {
    const end = i + 1 < starts.length ? starts[i + 1].at : clientSrc.length;
    fnBlocks.push({ name: starts[i].name, body: clientSrc.slice(starts[i].at, end) });
  }
}
assert.ok(
  fnBlocks.length >= 5,
  `expected >=5 exported async functions in scheduledJobs.ts, found ${fnBlocks.length}`,
);

const clientCalls = new Set();
for (const fn of fnBlocks) {
  // Extract the apiFetch path argument: either "..." / '...' or `...`.
  const call = fn.body.match(/apiFetch(?:<[\s\S]*?>)?\(\s*(`[^`]+`|"[^"]+"|'[^']+')/);
  assert.ok(call, `no apiFetch call found in ${fn.name}()`);
  let raw = call[1].slice(1, -1);

  // Method: default GET unless the options object says otherwise.
  const methodMatch = fn.body.match(/method:\s*["'`](\w+)["'`]/);
  const method = (methodMatch ? methodMatch[1] : "GET").toUpperCase();

  // Interpolated key segments MUST be encodeURIComponent(key). Reject any
  // other interpolation (raw `${key}` would ship unencoded ':').
  const interpolations = [...raw.matchAll(/\$\{([^}]*)\}/g)].map((x) => x[1].trim());
  for (const expr of interpolations) {
    assert.match(
      expr,
      /^encodeURIComponent\(\s*key\s*\)$/,
      `${fn.name}() interpolates '\${${expr}}' into the path — job keys contain ':' ` +
        "and must be wrapped in encodeURIComponent(key)",
    );
  }
  const template = raw.replace(/\$\{[^}]*\}/g, ":key");
  clientCalls.add(`${method} ${template}`);

  // Envelope unwrap: apiFetch returns the RAW {data} envelope; every function
  // must `return res.data` (typecheck will NOT catch a missing unwrap).
  assert.match(
    fn.body,
    /return\s+res\.data\s*;/,
    `${fn.name}() does not unwrap the {data} envelope (missing 'return res.data;') — ` +
      "apiFetch returns the raw envelope and TypeScript will not catch this",
  );
  // And the generic must be declared as an envelope, not the bare payload.
  assert.match(
    fn.body,
    /apiFetch<\s*\{\s*\n?\s*data:/,
    `${fn.name}() apiFetch generic is not declared as a {data: ...} envelope`,
  );
}

// Per-job functions must actually encode the key (belt & braces: the
// interpolation check above only fires if an interpolation exists at all —
// a hand-built string concat would slip past it).
for (const fn of fnBlocks) {
  if (!/\(\s*\n?\s*key:\s*string/.test(fn.body)) continue;
  assert.match(
    fn.body,
    /encodeURIComponent\(key\)/,
    `${fn.name}() takes a job key but never calls encodeURIComponent(key)`,
  );
  assert.ok(
    !fn.body.includes("${key}"),
    `${fn.name}() interpolates the raw \${key} — wrap it in encodeURIComponent`,
  );
}

// ---------------------------------------------------------------------------
// 3. Lockstep: client call set === server route set (both directions).
// ---------------------------------------------------------------------------
const missingOnServer = [...clientCalls].filter((c) => !serverRoutes.has(c));
assert.deepEqual(
  missingOnServer,
  [],
  `mobile scheduledJobs.ts calls endpoints that do not exist in routes/api.php ` +
    `(would 404): ${missingOnServer.join(", ")} — server routes: ${[...serverRoutes].join(", ")}`,
);

const unconsumed = [...serverRoutes].filter((r) => !clientCalls.has(r));
assert.deepEqual(
  unconsumed,
  [],
  `routes/api.php exposes /admin/scheduled-jobs endpoints the mobile client ` +
    `never calls: ${unconsumed.join(", ")} — add them to lib/api/scheduledJobs.ts ` +
    "or this screen has silently lost functionality",
);

// ---------------------------------------------------------------------------
// 4. The screen actually consumes this client (a rename/move of the module
//    or a switch to raw apiFetch calls in the screen would bypass all the
//    invariants above).
// ---------------------------------------------------------------------------
const screenSrc = readFileSync(join(root, "app", "admin", "scheduled-jobs.tsx"), "utf8");
assert.match(
  screenSrc,
  /from\s+["']@\/lib\/api\/scheduledJobs["']/,
  "app/admin/scheduled-jobs.tsx no longer imports from @/lib/api/scheduledJobs",
);
for (const fn of fnBlocks) {
  assert.ok(
    screenSrc.includes(fn.name),
    `screen never uses ${fn.name}() — either dead client code or the screen ` +
      "reimplements the call (bypassing the guarded client)",
  );
}

console.log(
  `test-scheduled-jobs-client: OK — ${clientCalls.size} endpoints in lockstep ` +
    "with routes/api.php, keys URI-encoded, {data} envelope unwrapped everywhere.",
);
