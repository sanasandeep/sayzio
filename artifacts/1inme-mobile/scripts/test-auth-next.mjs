// Smoke test for the post-auth redirect handoff that sends a guest who taps a
// "Perfect pairings" cross-promo card into signup/OTP and back to the exact
// type-specific create screen afterwards.
//
// This guards the newest guest -> creator conversion surface
// (components/LinkTypePairings.tsx): a card stashes a short-lived post-auth
// path via lib/authNext.ts, and every login-completion path
// (app/(auth)/index.tsx, app/(auth)/verify.tsx, app/oauth-callback.tsx) then
// consumes it via redirectAfterAuth. A silent regression means new users
// finish signup and land in the wrong place, quietly killing the conversion.
//
// Run via `node scripts/test-auth-next.mjs` (also wired into the package
// script `test:auth-next` and the `test:unit` gate). We intentionally avoid a
// full TS test runner — the logic is nearly pure, so we extract the relevant
// bits from source and evaluate them in isolation with an in-memory
// AsyncStorage stand-in (the module imports @react-native-async-storage which
// we can't load here).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

// --- 1. Load lib/authNext.ts with a fake AsyncStorage --------------------

const authNextSrc = readFileSync(join(root, "lib", "authNext.ts"), "utf8");

// Strip the AsyncStorage import (we inject a fake) and remove the TS type
// annotations so the bodies are valid plain JS. Each signature runs up to its
// body's opening brace; redirectAfterAuth's router param type also contains a
// brace, so we anchor on its `): Promise<void> {` tail instead of the first
// `{`.
const authNextJs = authNextSrc
  .replace(/^import AsyncStorage.*$/m, "")
  .replace(/export function sanitizeNext[\s\S]*?\{/, "function sanitizeNext(next) {")
  .replace(
    /export async function setPendingPostAuthNext[\s\S]*?\{/,
    "async function setPendingPostAuthNext(next) {",
  )
  .replace(
    /export async function touchPendingPostAuthNext[\s\S]*?\{/,
    "async function touchPendingPostAuthNext() {",
  )
  .replace(
    /export async function consumePendingPostAuthNext[\s\S]*?\{/,
    "async function consumePendingPostAuthNext(maxAgeMs = MAX_AGE_MS) {",
  )
  .replace(
    /export async function redirectAfterAuth[\s\S]*?\): Promise<void> \{/,
    "async function redirectAfterAuth(router) {",
  )
  .replace(/ as \{ next\?: string; ts\?: number \}/g, "")
  .replace(/ as never/g, "");

function makeFakeStorage() {
  const store = new Map();
  return {
    store,
    async getItem(k) {
      return store.has(k) ? store.get(k) : null;
    },
    async setItem(k, v) {
      store.set(k, v);
    },
    async removeItem(k) {
      store.delete(k);
    },
  };
}

const fakeStorage = makeFakeStorage();

// eslint-disable-next-line no-new-func
const {
  sanitizeNext,
  setPendingPostAuthNext,
  touchPendingPostAuthNext,
  consumePendingPostAuthNext,
  redirectAfterAuth,
} = new Function(
  "AsyncStorage",
  `${authNextJs}
   return { sanitizeNext, setPendingPostAuthNext, touchPendingPostAuthNext, consumePendingPostAuthNext, redirectAfterAuth };`,
)(fakeStorage);

// The AsyncStorage key the module persists under — used to seed a stale entry
// for the expiry test without waiting 10 real minutes.
const KEY = "pending_post_auth_next";

// --- 2. sanitizeNext: only internal absolute paths survive ---------------

assert.equal(
  sanitizeNext("/links/create/biolink"),
  "/links/create/biolink",
  "an internal absolute path should pass through unchanged",
);
assert.equal(sanitizeNext(null), null, "null resolves to null");
assert.equal(sanitizeNext(undefined), null, "undefined resolves to null");
assert.equal(sanitizeNext(""), null, "empty string resolves to null");
assert.equal(
  sanitizeNext("https://evil.example.com"),
  null,
  "an external URL must never be honoured",
);
assert.equal(
  sanitizeNext("//evil.example.com"),
  null,
  "a protocol-relative path must never be honoured",
);
assert.equal(
  sanitizeNext("links/create/biolink"),
  null,
  "a relative (non-absolute) path is rejected",
);

// --- 3. stash + consume round-trip, single-use, and expiry ---------------

// Fresh stash then consume returns the exact path.
await setPendingPostAuthNext("/links/create/reviews");
assert.equal(
  await consumePendingPostAuthNext(),
  "/links/create/reviews",
  "a freshly stashed path should be returned on consume",
);
// Consuming again yields nothing — the value is single-use.
assert.equal(
  await consumePendingPostAuthNext(),
  null,
  "the stashed path is cleared after the first consume",
);

// An unsafe path is never stored in the first place.
await setPendingPostAuthNext("https://evil.example.com");
assert.equal(
  await consumePendingPostAuthNext(),
  null,
  "an unsafe path is never stashed",
);

// A stale entry (older than the freshness window) is dropped on consume.
fakeStorage.store.set(
  KEY,
  JSON.stringify({
    next: "/links/create/vcard",
    ts: Date.now() - 20 * 60 * 1000, // 20 min ago > 10 min max age
  }),
);
assert.equal(
  await consumePendingPostAuthNext(),
  null,
  "a stale stashed path (past the freshness window) must not hijack a later sign-in",
);
// ...and the stale entry is cleared so it can't leak into a later attempt.
assert.equal(
  fakeStorage.store.has(KEY),
  false,
  "a stale entry is removed from storage after being read",
);

// --- 4. redirectAfterAuth: land on the stashed path, else tabs home ------

function captureRouter() {
  return {
    replaced: [],
    replace(href) {
      this.replaced.push(href);
    },
  };
}

// Guest path: a card stashed a create screen -> auth completes -> land there.
await setPendingPostAuthNext("/links/create/store_menu");
const r1 = captureRouter();
await redirectAfterAuth(r1);
assert.deepEqual(
  r1.replaced,
  ["/links/create/store_menu"],
  "after auth, the visitor is replaced onto the stashed create screen",
);

// Nothing pending -> fall back to the tabs home.
const r2 = captureRouter();
await redirectAfterAuth(r2);
assert.deepEqual(
  r2.replaced,
  ["/(tabs)"],
  "with nothing pending, auth completion falls back to the tabs home",
);

// --- 4b. Graceful handling of a stash that outlived the 10-min window -----
//        (the whole point of task #3709: don't silently lose a guest's
//        pairing if they finish signing up more than 10 minutes later.)

const TEN_MIN = 10 * 60 * 1000;

// touchPendingPostAuthNext slides the freshness window forward for an
// in-flight stash: a guest actively working through the auth flow (which
// touches on each surface) keeps their pairing alive well past 10 minutes.
fakeStorage.store.set(
  KEY,
  JSON.stringify({
    next: "/links/create/biolink",
    ts: Date.now() - 30 * 60 * 1000, // 30 min ago: past the 10-min window
  }),
);
await touchPendingPostAuthNext();
assert.equal(
  await consumePendingPostAuthNext(TEN_MIN),
  "/links/create/biolink",
  "an active guest's stash is re-armed by touch and survives the short window",
);

// touch refuses to resurrect a genuinely abandoned stash (past the outer
// resumable bound) — that value must not hijack an unrelated later sign-in.
fakeStorage.store.set(
  KEY,
  JSON.stringify({
    next: "/links/create/biolink",
    ts: Date.now() - 90 * 60 * 1000, // 90 min ago: past the resumable bound
  }),
);
await touchPendingPostAuthNext();
assert.equal(
  fakeStorage.store.has(KEY),
  false,
  "touch drops a stash past the outer resumable window instead of re-arming it",
);

// redirectAfterAuth runs only after a SUCCESSFUL auth, so it honours the
// wider resumable window: a stash 20 minutes old (past the 10-min window but
// well within the hour) still takes the guest to their pairing rather than
// silently dropping them into the generic tabs.
fakeStorage.store.set(
  KEY,
  JSON.stringify({
    next: "/links/create/restaurant_menu",
    ts: Date.now() - 20 * 60 * 1000, // 20 min ago: expired for silent honour
  }),
);
const r3 = captureRouter();
await redirectAfterAuth(r3);
assert.deepEqual(
  r3.replaced,
  ["/links/create/restaurant_menu"],
  "a slow sign-up (past 10 min, within the resumable window) still lands on the stashed create screen",
);

// ...but a truly stale stash (past the resumable window) is NOT honoured on
// completion — it falls back to the tabs home so it can't hijack a much
// later, unrelated sign-in.
fakeStorage.store.set(
  KEY,
  JSON.stringify({
    next: "/links/create/vcard",
    ts: Date.now() - 90 * 60 * 1000, // 90 min ago: past the resumable window
  }),
);
const r4 = captureRouter();
await redirectAfterAuth(r4);
assert.deepEqual(
  r4.replaced,
  ["/(tabs)"],
  "a stash older than the resumable window falls back to the tabs home",
);
assert.equal(
  fakeStorage.store.has(KEY),
  false,
  "the stale stash is cleared after completion so it can't leak into a later attempt",
);

// --- 5. pairingCreatePath: catalog type -> mobile create route -----------

const pairingsSrc = readFileSync(
  join(root, "lib", "linkPairings.ts"),
  "utf8",
);
const pairingPathMatch = pairingsSrc.match(
  /export function pairingCreatePath[\s\S]*?\n\}\n/,
);
if (!pairingPathMatch) {
  throw new Error("could not find pairingCreatePath in lib/linkPairings.ts");
}
const pairingPathJs = pairingPathMatch[0].replace(
  /export function pairingCreatePath[\s\S]*?\{/,
  "function pairingCreatePath(type) {",
);
// eslint-disable-next-line no-new-func
const { pairingCreatePath } = new Function(
  `${pairingPathJs}; return { pairingCreatePath };`,
)();

const expectedRoutes = {
  biolink: "/links/create/biolink",
  reviews: "/links/create/reviews",
  vcf: "/links/create/vcard",
  brand_kit: "/links/create/brand_kit",
  resume: "/links/create/resume",
  restaurant_menu: "/links/create/restaurant_menu",
  store_menu: "/links/create/store_menu",
  ics: "/links/create/calendar",
  calendar: "/calendars/edit",
  qr: "/qr-studio",
};
for (const [type, route] of Object.entries(expectedRoutes)) {
  assert.equal(
    pairingCreatePath(type),
    route,
    `pairing type "${type}" should deep-link to ${route}`,
  );
}
// Unknown / unmapped types fall back to the generic Create tab.
assert.equal(
  pairingCreatePath("something_new"),
  "/(tabs)/create",
  "an unknown pairing type falls back to the generic Create tab",
);
// Every stashable create route survives sanitizeNext (so the handoff can
// actually carry it through auth).
for (const route of [...Object.values(expectedRoutes), "/(tabs)/create"]) {
  assert.equal(
    sanitizeNext(route),
    route,
    `pairing route ${route} must survive the post-auth sanitizer`,
  );
}

// --- 6. LinkTypePairings wiring: guest stashes + routes to auth; ----------
//        logged-in pushes straight to the create screen.

const componentSrc = readFileSync(
  join(root, "components", "LinkTypePairings.tsx"),
  "utf8",
);

// It must resolve the destination via pairingCreatePath.
assert.match(
  componentSrc,
  /const path = pairingCreatePath\(type\);/,
  "the card must resolve its destination via pairingCreatePath",
);
// Logged-in creators are pushed straight to the create screen (no stash).
assert.match(
  componentSrc,
  /if \(loggedIn\) \{[\s\S]*?router\.push\(path[\s\S]*?return;/,
  "a logged-in creator is pushed directly to the create screen",
);
// Guests stash the path, THEN route into the auth flow.
const guestOrder = componentSrc.match(
  /await setPendingPostAuthNext\(path\);[\s\S]*?router\.push\("\/\(auth\)"/,
);
assert.ok(
  guestOrder,
  "a guest must stash the post-auth path BEFORE being routed into /(auth)",
);

// --- 7. Every auth-completion entry point consumes the handoff -----------
//        (OTP login/verify + the OAuth browser round-trip). If any of these
//        stops calling redirectAfterAuth, a guest finishes signup on that
//        path and never reaches the stashed create screen.

for (const rel of [
  ["app", "(auth)", "index.tsx"],
  ["app", "(auth)", "verify.tsx"],
  ["app", "oauth-callback.tsx"],
]) {
  const file = join(root, ...rel);
  const src = readFileSync(file, "utf8");
  assert.match(
    src,
    /await redirectAfterAuth\(router\)/,
    `${rel.join("/")} must complete auth via redirectAfterAuth so the stashed create screen is honoured`,
  );
}

// --- 8. Active auth surfaces slide the stash's freshness window ----------
//        The login landing and OTP verify screens must touch the stash on
//        mount so a guest actively progressing through signup doesn't have
//        their pairing expire out from under them past the 10-min window.

for (const rel of [
  ["app", "(auth)", "index.tsx"],
  ["app", "(auth)", "verify.tsx"],
]) {
  const file = join(root, ...rel);
  const src = readFileSync(file, "utf8");
  assert.match(
    src,
    /touchPendingPostAuthNext\(\)/,
    `${rel.join("/")} must call touchPendingPostAuthNext on mount so an in-flight pairing's window slides forward`,
  );
}

console.log("test-auth-next: all assertions passed");
