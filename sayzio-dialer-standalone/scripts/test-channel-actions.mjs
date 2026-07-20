// Source-driven unit test for the standalone dialer's channel action buttons
// (components/ChannelActions.tsx) — the WhatsApp/Telegram/Signal/Viber/call/
// SMS one-tap row shown across every surface (keypad, favourites, frequent,
// recents, search, contacts, profile, call screens, caller-id).
//
// Channel buttons must respect the user's channel preferences
// (`dialer_channels`, served by GET /api/v1/dialer/channels) on EVERY surface
// after any settings change. Two properties keep that true and are pinned
// here (following the 1inme-mobile scripts/test-*.mjs convention of testing
// the shipped source, not a re-implementation):
//
//   1. Single shared component: every screen that renders channel buttons
//      imports from components/ChannelActions.tsx. No screen builds its own
//      wa.me / t.me / signal.me / viber deep links — a bespoke builder would
//      silently ignore the user's preferences.
//   2. resolveChannels honors prefs: we extract the REAL resolveChannels
//      function from the shipped source (types stripped) and assert it
//      returns exactly the enabled channels, in preference order, dropping
//      unknown keys — so a disabled channel can never render.
//
// The "after any settings change" path is also structural: the channel picker
// save must call publishChannelPrefs so every mounted row updates instantly,
// and every ChannelActions/`resolveChannels` consumer reads through the
// shared prefs store (useChannelPrefs).
//
// Run via `node scripts/test-channel-actions.mjs` (package script
// `test:channel-actions`).

import assert from "node:assert/strict";
import { readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const componentPath = join(root, "components", "ChannelActions.tsx");
const componentSrc = readFileSync(componentPath, "utf8");

// ---------------------------------------------------------------------------
// Collect every .ts/.tsx source file under app/, components/, lib/.
// ---------------------------------------------------------------------------
function walk(dir, out = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    const st = statSync(p);
    if (st.isDirectory()) walk(p, out);
    else if (/\.(ts|tsx)$/.test(name)) out.push(p);
  }
  return out;
}

const allFiles = ["app", "components", "lib"].flatMap((d) =>
  walk(join(root, d)),
);
assert.ok(allFiles.length > 20, "expected to find the app's source files");

// ---------------------------------------------------------------------------
// 1a. No bespoke deep-link building outside the shared component. Any file
//     other than ChannelActions.tsx containing a messaging-app deep-link host
//     or scheme is a preference bypass. (Bare `tel:` / `sms:` OS handoffs are
//     allowed as channel-independent quick actions — the web profile page has
//     the same standalone Call/Message buttons outside the channel row.)
// ---------------------------------------------------------------------------
const DEEP_LINK_PATTERNS = [
  /wa\.me/,
  /api\.whatsapp\.com/,
  /\bt\.me\b/,
  /signal\.me/,
  /viber:\/\//,
];

// Fixed support-contact surfaces (the app's own "Contact us on WhatsApp"
// help link) are not per-number channel actions and carry no preference.
const DEEP_LINK_ALLOWLIST = new Set([join(root, "components", "InfoPage.tsx")]);

for (const file of allFiles) {
  if (file === componentPath || DEEP_LINK_ALLOWLIST.has(file)) continue;
  const src = readFileSync(file, "utf8");
  for (const pat of DEEP_LINK_PATTERNS) {
    assert.ok(
      !pat.test(src),
      `bespoke channel deep-link (${pat}) in ${relative(root, file)} — ` +
        `all channel handoffs must go through components/ChannelActions.tsx ` +
        `(chanOpen), which respects the user's channel preferences`,
    );
  }
}

// The shared component itself must handle all six handoffs.
// The "tel" (call) channel routes through placeRealCall so the user's
// calling preference (Direct call vs Open phone app) is respected everywhere.
for (const pat of [/placeRealCall\(/, /sms:\$\{/, /wa\.me/, /t\.me/, /signal\.me/, /viber:\/\//]) {
  assert.ok(
    pat.test(componentSrc),
    `ChannelActions.tsx lost its ${pat} channel handler`,
  );
}

// ---------------------------------------------------------------------------
// 1b. Every screen that renders channel buttons imports the shared component
//     module — and any screen using the low-level chanOpen/resolveChannels
//     helpers imports them from the shared module too (never redefines them).
// ---------------------------------------------------------------------------
const REQUIRED_SURFACES = [
  "app/(tabs)/dialer.tsx",
  "app/(tabs)/contacts.tsx",
  "app/(tabs)/caller-id.tsx",
  "app/search.tsx",
  "app/contacts/_form.tsx",
  "app/dialer-profile.tsx",
  "app/call/incoming.tsx",
];

const IMPORT_RE =
  /import\s*\{[^}]*\}\s*from\s*["']@\/components\/ChannelActions["']/;

for (const rel of REQUIRED_SURFACES) {
  const src = readFileSync(join(root, rel), "utf8");
  assert.ok(
    IMPORT_RE.test(src),
    `${rel} must import from "@/components/ChannelActions" (shared component)`,
  );
  // It actually renders the shared cluster (or maps resolveChannels rows —
  // the dialer/search inline rows — onto chanOpen; both are the shared path).
  assert.ok(
    src.includes("<ChannelActions") || src.includes("resolveChannels("),
    `${rel} imports ChannelActions but never renders channel rows from it`,
  );
}

for (const file of allFiles) {
  if (file === componentPath) continue;
  const src = readFileSync(file, "utf8");
  // No file may redefine the helpers — the shared module is the only source.
  assert.ok(
    !/function\s+(chanOpen|resolveChannels|useChannelPrefs)\b/.test(src),
    `${relative(root, file)} redefines a ChannelActions helper`,
  );
  // Any direct chanOpen(...) caller must pick channels via resolveChannels,
  // so a disabled channel key can never be hard-coded into a button.
  if (/\bchanOpen\(/.test(src)) {
    assert.ok(
      /\bresolveChannels\(/.test(src),
      `${relative(root, file)} calls chanOpen() without resolveChannels() — ` +
        `channel rows must be derived from the user's enabled channels`,
    );
  }
}

// ---------------------------------------------------------------------------
// 2. resolveChannels honors prefs — evaluate the REAL shipped function.
// ---------------------------------------------------------------------------
function extractBlock(src, startMarker) {
  const start = src.indexOf(startMarker);
  assert.notEqual(start, -1, `could not find "${startMarker}" in source`);
  const open = src.indexOf("{", start);
  let depth = 0;
  for (let i = open; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}") {
      depth--;
      if (depth === 0) return src.slice(start, i + 1);
    }
  }
  throw new Error(`unterminated block for "${startMarker}"`);
}

// The FALLBACK_CHANNELS literal doubles as a realistic catalog fixture.
const catalogStart = componentSrc.indexOf("FALLBACK_CHANNELS");
assert.notEqual(catalogStart, -1, "FALLBACK_CHANNELS missing");
const eq = componentSrc.indexOf("= [", catalogStart);
let depth = 0;
let catEnd = -1;
const catOpen = componentSrc.indexOf("[", eq);
for (let i = catOpen; i < componentSrc.length; i++) {
  if (componentSrc[i] === "[") depth++;
  else if (componentSrc[i] === "]") {
    depth--;
    if (depth === 0) {
      catEnd = i;
      break;
    }
  }
}
assert.notEqual(catEnd, -1, "unterminated FALLBACK_CHANNELS literal");
// eslint-disable-next-line no-new-func
const CATALOG = new Function(
  `return ${componentSrc.slice(catOpen, catEnd + 1)};`,
)();
assert.equal(CATALOG.length, 6, "catalog must carry all six channels");
assert.deepEqual(
  CATALOG.map((c) => c.key),
  ["call", "sms", "whatsapp", "telegram", "signal", "viber"],
  "catalog keys drifted from the server DialerChannels catalog",
);

// Extract + type-strip the real resolveChannels function.
let fnSrc = extractBlock(componentSrc, "export function resolveChannels");
const stripped = fnSrc
  .replace("export function", "function")
  .replace("(prefs: ChannelPrefs): DialerChannelDef[]", "(prefs)")
  .replace("(c): c is DialerChannelDef =>", "(c) =>");
assert.ok(
  !stripped.includes(":") || !/ChannelPrefs|DialerChannelDef/.test(stripped),
  "type-stripping resolveChannels failed — update this test's replacements",
);
// eslint-disable-next-line no-new-func
const resolveChannels = new Function(
  `${stripped}; return resolveChannels;`,
)();

// Enabled subset, in preference order — disabled channels never returned.
const picked = resolveChannels({ catalog: CATALOG, enabled: ["viber", "call"] });
assert.deepEqual(
  picked.map((c) => c.key),
  ["viber", "call"],
  "resolveChannels must return exactly the enabled channels, in order",
);
assert.ok(
  picked.every((c) => c.label && c.js && c.feather),
  "resolved rows must be full catalog entries",
);

// A settings change to a different set fully replaces the previous one.
assert.deepEqual(
  resolveChannels({ catalog: CATALOG, enabled: ["telegram", "sms"] }).map(
    (c) => c.key,
  ),
  ["telegram", "sms"],
);

// Unknown keys are dropped, never rendered as broken buttons.
assert.deepEqual(
  resolveChannels({
    catalog: CATALOG,
    enabled: ["whatsapp", "bogus", "signal"],
  }).map((c) => c.key),
  ["whatsapp", "signal"],
);

// Empty selection renders no buttons (ChannelActions returns null).
assert.deepEqual(resolveChannels({ catalog: CATALOG, enabled: [] }), []);

// ---------------------------------------------------------------------------
// 3. The live-update path: rows subscribe through the shared store, and the
//    channel picker save publishes the fresh selection to every mounted row.
// ---------------------------------------------------------------------------
assert.ok(
  /useSyncExternalStore\(subscribe,/.test(componentSrc),
  "useChannelPrefs must read through the shared subscribable store",
);
assert.ok(
  /export function publishChannelPrefs/.test(componentSrc),
  "publishChannelPrefs missing from ChannelActions.tsx",
);

const dialerSrc = readFileSync(join(root, "app", "(tabs)", "dialer.tsx"), "utf8");
assert.ok(
  /updateDialerChannels\(/.test(dialerSrc),
  "the channel picker must save via updateDialerChannels()",
);
assert.ok(
  /publishChannelPrefs\(/.test(dialerSrc),
  "after saving, the picker must publishChannelPrefs() so every visible " +
    "channel row updates instantly",
);

console.log(
  `channel-actions OK: ${REQUIRED_SURFACES.length} surfaces on the shared ` +
    `component, no bespoke deep links in ${allFiles.length} files, ` +
    `resolveChannels honors prefs.`,
);
