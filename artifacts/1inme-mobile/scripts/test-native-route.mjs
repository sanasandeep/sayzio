// Smoke test for nativeRouteFor — the pure helper the notifications screen
// uses to translate a web notification target into a native Expo Router route
// (or null, meaning "fall back to the in-app browser").
//
// This guards notification tap routing (task #3020): a subtle ordering bug
// here can silently send a tap to the wrong screen — e.g. the
// manage-subscription deep-link must NOT match the generic /@handle profile
// rule, so that case has to be checked first. There was no test covering this
// mapping, unlike the push path (scripts/test-push-action.mjs).
//
// Run via `node scripts/test-native-route.mjs` (also wired into the package
// script `test:native-route`). We intentionally avoid a full TS test runner —
// the helper is pure, so we extract its body from notifications.tsx and
// evaluate it in isolation (the rest of the module imports React Native /
// expo-router which we can't load here).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(
  join(__dirname, "..", "app", "notifications.tsx"),
  "utf8",
);

// Pull just the nativeRouteFor body out of notifications.tsx. Capture the
// whole function: up to a `}` alone on its own line followed by a newline
// (the body close). The inner `} catch {` / catch-close braces are indented,
// so they won't match the top-level close.
const re = /function nativeRouteFor\b[\s\S]*?\n\}\n/m;
const m = src.match(re);
if (!m) throw new Error("could not find nativeRouteFor in notifications.tsx");

// Strip the TS type annotations so the body is valid plain JS. The signature
// (`function nativeRouteFor(target: string): string | null {`) runs up to the
// body's opening brace — replace the whole thing with a plain JS header.
const js = m[0].replace(
  /function nativeRouteFor[\s\S]*?\{/m,
  "function nativeRouteFor(target) {",
);

// eslint-disable-next-line no-new-func
const { nativeRouteFor } = new Function(
  `${js}; return { nativeRouteFor };`,
)();

// 1. Public creator profile: /@handle → native profile screen.
assert.equal(
  nativeRouteFor("/@alice"),
  "/profile/alice",
  "/@handle should route to the native profile screen",
);

// 1b. Profile target carrying a post/roadmap hash still resolves to profile.
assert.equal(
  nativeRouteFor("/@bob#post-12"),
  "/profile/bob",
  "/@handle with a hash should still route to the profile screen",
);

// 1c. Absolute URL form (host stripped) resolves the same way.
assert.equal(
  nativeRouteFor("https://1in.me/@carol?ref=push"),
  "/profile/carol",
  "an absolute /@handle URL should resolve to the profile screen",
);

// 2. Manage-subscription deep-link must route to the native
// manage-subscription screen (carrying the creator handle) — and must be
// checked BEFORE the generic /@handle rule or it would wrongly open the
// public profile screen instead. This is the core ordering bug guard.
assert.equal(
  nativeRouteFor("/@dave/manage-subscription"),
  "/manage-subscription?handle=dave",
  "manage-subscription should route to the native screen carrying the handle, not the profile screen",
);
assert.equal(
  nativeRouteFor("https://1in.me/@dave/manage-subscription?plan=pro"),
  "/manage-subscription?handle=dave",
  "absolute manage-subscription URL should also route to the native manage-subscription screen",
);

// 3. In-app dashboard areas that have a native screen.
assert.equal(nativeRouteFor("/user/team"), "/team");
assert.equal(nativeRouteFor("/user/team/invite"), "/team");
assert.equal(nativeRouteFor("/user/posts"), "/posts");
assert.equal(nativeRouteFor("/user/posts/9/edit"), "/posts");
assert.equal(nativeRouteFor("/user/social-accounts"), "/social");
assert.equal(nativeRouteFor("/user/social-accounts/connect"), "/social");
assert.equal(nativeRouteFor("/user/domains"), "/domains");
assert.equal(nativeRouteFor("/user/domains/add"), "/domains");

// 4. A /user/* area without a native screen falls back to the browser.
assert.equal(
  nativeRouteFor("/user/settings"),
  null,
  "an unmapped /user/* route should fall back to the browser",
);

// 5. Unknown / unrelated paths have no native counterpart.
assert.equal(nativeRouteFor("/pricing"), null);
assert.equal(nativeRouteFor("/api-usage"), null);
assert.equal(nativeRouteFor("https://example.com/whatever"), null);

// 6. A bare (slashless) target is normalised with a leading slash first.
assert.equal(
  nativeRouteFor("@erin"),
  "/profile/erin",
  "a target without a leading slash should still resolve",
);

console.log("test-native-route: all assertions passed");
