// Source-driven regression test for the mobile dialer's universal finder,
// specifically the "People" group.
//
// Background: the "reachable people" scope is enforced entirely on the server
// (App\Modules\User\Support\DialerSearch), which the web, REST and mobile
// surfaces all call through GET /api/v1/dialer/search. The People group is
// deliberately narrow — self + followed creators + contacts that map to a
// Sayzio user — so name search can NEVER reach an arbitrary stranger.
//
// The backend boundary is covered by feature tests. What is NOT otherwise
// guarded is the mobile *client's* rendering: if the dialer screen ever merged
// the People group with a locally-built directory (device contacts, the
// contacts API, a T9 match list, etc.) or re-queried people from another
// endpoint, it would silently surface strangers the server scope excluded.
//
// This test pins that the mobile dialer renders the universal groups (People
// included) EXCLUSIVELY from the `dialerSearch` API response, never from any
// local list. Following the source-driven convention in test-quick-contact.mjs
// / test-native-route.mjs we read the shipped source and assert on its exact
// wiring rather than re-implementing the screen.
//
// Run via `node scripts/test-dialer-people-scope.mjs` (package script
// `test:dialer-people-scope`, chained into the `test:unit` offline gate).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(join(root, "app", "dialer.tsx"), "utf8");
const apiSrc = readFileSync(join(root, "lib", "api", "dialer.ts"), "utf8");

// ---------------------------------------------------------------------------
// 1. The client talks to the shared server contract: dialerSearch hits
//    GET /dialer/search (under /api/v1) and returns the server's grouped
//    payload verbatim (res.data). This is the ONLY source of People items.
// ---------------------------------------------------------------------------
assert.ok(
  /export async function dialerSearch\(/.test(apiSrc),
  "dialer API client must export dialerSearch",
);
assert.ok(
  /apiFetch<\{ data: DialerSearchResult \}>\(\s*`\/dialer\/search\?/.test(
    apiSrc,
  ),
  "dialerSearch must GET /dialer/search (the shared universal-finder contract)",
);
assert.ok(
  /return res\.data;/.test(
    apiSrc.slice(apiSrc.indexOf("export async function dialerSearch(")),
  ),
  "dialerSearch must return the server payload unchanged (no client re-shaping)",
);

// ---------------------------------------------------------------------------
// 2. The `uni` state (the rendered universal results) is only ever assigned
//    from the dialerSearch result or reset to null. It must NEVER be built
//    from a local list. We collect every setUni(...) call and require its
//    argument to be exactly `res` (the dialerSearch result) or `null`.
// ---------------------------------------------------------------------------
const setUniArgs = [...screenSrc.matchAll(/setUni\(([^)]*)\)/g)].map((m) =>
  m[1].trim(),
);
assert.ok(setUniArgs.length >= 2, "expected setUni to be called at least twice");
for (const arg of setUniArgs) {
  assert.ok(
    arg === "res" || arg === "null",
    `setUni must only be fed the dialerSearch result or null, got setUni(${arg})`,
  );
}

// runUniversal must derive `res` straight from `await dialerSearch(...)` and
// feed it to setUni — proving the People group comes from the server, not a
// local merge.
function extractFn(src, marker) {
  const start = src.indexOf(marker);
  if (start === -1) throw new Error(`could not find ${marker} in dialer.tsx`);
  // Grab a generous slice; the assertions below only look for the wiring
  // tokens, so we don't need exact brace balancing.
  return src.slice(start, start + 900);
}

const runUniversal = extractFn(screenSrc, "const runUniversal = useCallback(");
assert.ok(
  /const res = await dialerSearch\(/.test(runUniversal),
  "runUniversal must fetch results via dialerSearch",
);
assert.ok(
  /setUni\(res\)/.test(runUniversal),
  "runUniversal must store the dialerSearch result as the rendered groups",
);
assert.ok(
  /setUni\(null\)/.test(runUniversal),
  "runUniversal must reset to null on failure — never fall back to a local directory",
);
// The failure path must NOT quietly substitute a locally-built list.
assert.ok(
  !/(listContacts|deviceContacts|appContacts|keypadMatches)/.test(runUniversal),
  "runUniversal must not reference any local contact list",
);

// ---------------------------------------------------------------------------
// 3. The grouped results are rendered directly off the API payload:
//    uni.groups.map(...) -> g.items.map(...). The People group is just one of
//    the server's groups; the client renders whatever the server returned.
// ---------------------------------------------------------------------------
const renderStart = screenSrc.indexOf("uni && uni.groups.length > 0");
assert.ok(renderStart !== -1, "could not find the universal results renderer");
// Bound the render region at the empty-state block that immediately follows it.
const renderEnd = screenSrc.indexOf("uni.groups.length === 0", renderStart);
assert.ok(renderEnd !== -1, "could not find the end of the results renderer");
const renderRegion = screenSrc.slice(renderStart, renderEnd);

assert.ok(
  /uni\.groups\.map\(/.test(renderRegion),
  "the renderer must iterate the server's groups (uni.groups.map)",
);
assert.ok(
  /g\.items\.map\(/.test(renderRegion),
  "the renderer must iterate each server group's items (g.items.map)",
);
// Every rendered field is read off the server item, proving nothing is
// synthesized locally.
for (const field of ["item.title", "item.initials", "item.action"]) {
  assert.ok(
    renderRegion.includes(field),
    `the renderer must read ${field} straight from the server item`,
  );
}

// ---------------------------------------------------------------------------
// 4. The critical guard: the universal renderer must NOT pull in any locally
//    built directory. A regression that merged device contacts, the contacts
//    API, or the (now-dead) T9 match list into this block would let name
//    search reach strangers the server scope excluded.
// ---------------------------------------------------------------------------
for (const local of [
  "deviceContacts",
  "appContacts",
  "keypadMatches",
  "listContacts",
]) {
  assert.ok(
    !renderRegion.includes(local),
    `the universal (People) renderer must not reference ${local} — People come only from the API`,
  );
}

console.log(
  "ok — dialer People group renders only from the dialerSearch API (no local directory)",
);
