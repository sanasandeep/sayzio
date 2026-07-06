// Source-driven guard: a fresh FREE-plan account can open EVERY mobile
// "Perfect pairings" create screen (the screen renders its create form)
// instead of hitting an upgrade / paywall or a permission block.
//
// This is the mobile parity for the web guard
//   1inme/tests/Feature/LinkTypePairingsWebRedirectTest.php
//     ::test_fresh_free_user_can_open_every_pairing_create_screen
//
// The "Perfect pairings" catalog is owned by the product app
// (Laravel `SitePagesContent::linkTypePairingsCatalog`) and served to mobile
// under a `pairings` key. Each catalog item is a complementary link type
// ("Add a Reviews Page", "Add a QR Code", ...). On mobile every pairing card
// deep-links to a create screen via `pairingCreatePath` (lib/linkPairings.ts).
//
// The risk this guards: a pairing whose create screen would be UNREACHABLE
// for a brand-new free account — either because
//   (a) mobile's proactive plan gate (`usePlanFeatures.isLinkTypeLocked`)
//       reports it as locked, so the screen shows an upgrade wall, or
//   (b) the free plan does not actually permit that link type per the web's
//       authoritative gating (LinkController::enforceLinkTypeQuota's
//       module/cap map), so a submit would be bounced by the server.
// Any such pairing must be removed from the catalog or have its gating made
// explicit. This test reads the REAL sources (no hard-coded third copy):
//   - the canonical catalog + free-plan grants + gating map from the PHP app
//   - the shipped mobile `pairingCreatePath`, `LINK_KINDS` (kind -> apiType)
//     and `usePlanFeatures.isLinkTypeLocked` (evaluated as they ship)
// and fails if any distinct pairing type is unreachable on the default plan.
//
// Following the convention in test-auth-next.mjs / test-contact-content-sync.mjs
// we avoid a full TS/RN runner: we extract the real logic from source and
// exercise it, so this covers what actually ships.
//
// Run via `node scripts/test-pairing-create-open.mjs` (package script
// `test:pairing-create-open`, chained into `test:unit`).

import assert from "node:assert/strict";
import { readFileSync, existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

// Canonical PHP sources in the product app, relative to this mobile package.
const APP = resolve(root, "..", "1inme");
const SITE_PAGES_CONTENT_PHP = join(
  APP, "app", "Modules", "Common", "Support", "SitePagesContent.php",
);
const PLANS_SEEDER_PHP = join(
  APP, "database", "seeders", "PlansAndAddonsSeeder.php",
);
const LINK_CONTROLLER_PHP = join(
  APP, "app", "Modules", "User", "Controllers", "LinkController.php",
);

// Shipped mobile sources.
const LINK_PAIRINGS_TS = join(root, "lib", "linkPairings.ts");
const LINK_KINDS_TS = join(root, "lib", "linkKinds.ts");
const USE_PLAN_FEATURES_TS = join(root, "hooks", "usePlanFeatures.ts");

const read = (p) => readFileSync(p, "utf8");

// ---------------------------------------------------------------------------
// 1. Distinct pairing item types from the canonical web catalog.
// ---------------------------------------------------------------------------
function catalogPairingTypes(php) {
  const start = php.indexOf("function linkTypePairingsCatalog");
  assert.notEqual(start, -1, "linkTypePairingsCatalog() not found in SitePagesContent.php");
  // Bound to the method body so an identically-named key elsewhere can't leak in.
  const slice = php.slice(start, php.indexOf("\n    }", start));
  const types = new Set();
  for (const m of slice.matchAll(/'type'\s*=>\s*'([^']+)'/g)) types.add(m[1]);
  return types;
}

const pairingTypes = catalogPairingTypes(read(SITE_PAGES_CONTENT_PHP));

// Explicit expectation (mirrors the web test's route table): the exact set of
// distinct pairing item types mobile must be able to open. If the web catalog
// gains/removes a type this set must be updated in lockstep with the mapping
// tables below — the drift assertion right after forces that.
const EXPECTED_PAIRING_TYPES = [
  "calendar",
  "biolink",
  "reviews",
  "vcf",
  "ics",
  "qr",
  "restaurant_menu",
  "brand_kit",
];

assert.deepEqual(
  [...pairingTypes].sort(),
  [...EXPECTED_PAIRING_TYPES].sort(),
  "Web 'Perfect pairings' catalog types drifted from this mobile guard. " +
    "Update EXPECTED_PAIRING_TYPES and the mapping tables, and confirm the " +
    "new type's mobile create screen is reachable on the free plan.",
);

// ---------------------------------------------------------------------------
// 2. Free-plan grants + the web authoritative link-type gating map (PHP).
// ---------------------------------------------------------------------------
// Free-plan `features` array: the same raw map the API returns as
// `features_map` for a brand-new default account.
function freePlanFeatures(php) {
  const slug = php.indexOf("'slug' => 'free'");
  assert.notEqual(slug, -1, "free plan block not found in PlansAndAddonsSeeder.php");
  const feats = php.indexOf("'features' => [", slug);
  assert.notEqual(feats, -1, "free plan 'features' array not found");
  // Capture up to the closing of the features array (first "\n                ],").
  const body = php.slice(feats, php.indexOf("\n                ],", feats));
  const map = {};
  for (const m of body.matchAll(
    /'([a-z0-9_]+)'\s*=>\s*(-?\d+|true|false)\b/g,
  )) {
    const [, k, raw] = m;
    map[k] = raw === "true" ? true : raw === "false" ? false : Number(raw);
  }
  return map;
}

// LinkController::enforceLinkTypeQuota() gating map: link type -> {module, cap}.
function linkTypeGatingMap(php) {
  const map = {};
  for (const m of php.matchAll(
    /'([a-z_]+)'\s*=>\s*\[\s*'module'\s*=>\s*'([^']+)',\s*'cap'\s*=>\s*'([^']+)'/g,
  )) {
    map[m[1]] = { module: m[2], cap: m[3] };
  }
  return map;
}

const freeFeatures = freePlanFeatures(read(PLANS_SEEDER_PHP));
const gatingMap = linkTypeGatingMap(read(LINK_CONTROLLER_PHP));

// Sanity: the gating map parsed at all and includes the gated pairing types.
for (const t of ["reviews", "restaurant_menu", "brand_kit", "calendar"]) {
  assert.ok(gatingMap[t], `LinkController gating map missing '${t}'`);
}

// ---------------------------------------------------------------------------
// 3. Mobile pairingCreatePath (evaluated as it ships).
// ---------------------------------------------------------------------------
function extractFn(src, signature, file) {
  const at = src.indexOf(signature);
  assert.notEqual(at, -1, `${signature} not found in ${file}`);
  // Balance braces from the first "{" after the signature.
  const open = src.indexOf("{", at);
  let depth = 0, end = -1;
  for (let i = open; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}") { depth--; if (depth === 0) { end = i + 1; break; } }
  }
  assert.notEqual(end, -1, `could not balance braces for ${signature}`);
  return src.slice(at, end);
}

// pairingCreatePath is a plain string switch — strip the TS return type.
const pairingSrc = extractFn(
  read(LINK_PAIRINGS_TS),
  "export function pairingCreatePath",
  "linkPairings.ts",
).replace("export function pairingCreatePath(type: string): string", "function pairingCreatePath(type)");
// eslint-disable-next-line no-new-func
const pairingCreatePath = new Function(`${pairingSrc}; return pairingCreatePath;`)();

// ---------------------------------------------------------------------------
// 4. Mobile LINK_KINDS (kind -> apiType).
// ---------------------------------------------------------------------------
const kindsSrc = read(LINK_KINDS_TS);
const kindToApiType = {};
for (const m of kindsSrc.matchAll(
  /kind:\s*"([^"]+)",\s*\n\s*apiType:\s*"([^"]+)"/g,
)) {
  kindToApiType[m[1]] = m[2];
}
assert.ok(
  Object.keys(kindToApiType).length > 5,
  "failed to parse LINK_KINDS kind -> apiType map",
);

// ---------------------------------------------------------------------------
// 5. Mobile usePlanFeatures.isLinkTypeLocked (the real proactive gate).
// ---------------------------------------------------------------------------
const planSrc = read(USE_PLAN_FEATURES_TS);
const gatedSetSrc = (planSrc.match(
  /const GATED_LINK_TYPES = new Set<string>\(\[[\s\S]*?\]\);/,
) || [])[0];
assert.ok(gatedSetSrc, "GATED_LINK_TYPES not found in usePlanFeatures.ts");
const truthySrc = extractFn(planSrc, "function truthy", "usePlanFeatures.ts")
  .replace("function truthy(v: unknown): boolean", "function truthy(v)");
const lockedSrc = extractFn(planSrc, "function isLinkTypeLocked", "usePlanFeatures.ts")
  .replace("function isLinkTypeLocked(apiType: string): boolean", "function isLinkTypeLocked(apiType)");

// Non-uniform plan-key override maps (module/cap keys that don't follow the
// uniform `module_<apiType>` / `max_<apiType>` convention). Parse them from the
// shipped source so the gate we evaluate below uses the real overrides.
function extractRecord(src, name) {
  const re = new RegExp(
    `const ${name}: Record<string, string> = \\{[\\s\\S]*?\\};`,
  );
  const block = (src.match(re) || [])[0];
  assert.ok(block, `${name} not found in usePlanFeatures.ts`);
  const map = {};
  for (const m of block.matchAll(/(\w+):\s*"([^"]+)"/g)) map[m[1]] = m[2];
  return { block, map };
}
const moduleKeyByType = extractRecord(planSrc, "MODULE_KEY_BY_TYPE");
const capKeyByType = extractRecord(planSrc, "CAP_KEY_BY_TYPE");

// Build the real isLinkTypeLocked with `ready`/`featureMap` injected from the
// closure so we exercise the shipped logic verbatim.
// eslint-disable-next-line no-new-func
const makeIsLinkTypeLocked = new Function(
  "ready",
  "featureMap",
  `${gatedSetSrc.replace("new Set<string>", "new Set")}\n` +
    `${moduleKeyByType.block.replace(": Record<string, string>", "")}\n` +
    `${capKeyByType.block.replace(": Record<string, string>", "")}\n` +
    `${truthySrc}\n${lockedSrc}\n return isLinkTypeLocked;`,
);
// A brand-new account is on the default (free) plan, and plan data is resolved.
const isLinkTypeLocked = makeIsLinkTypeLocked(true, freeFeatures);

// ---------------------------------------------------------------------------
// 6. Assert every distinct pairing type opens on a fresh free account.
// ---------------------------------------------------------------------------
// Screens that legitimately live outside the /links/create/[kind] flow. These
// are reachable regardless of plan; `qr` -> /qr-studio is a platform-level
// WebFeatureRedirect (same for every plan, NOT a paywall).
const STANDALONE_SCREENS = {
  "/calendars/edit": "app/calendars/edit.tsx",
  "/qr-studio": "app/qr-studio.tsx",
};
const CREATE_KIND_SCREEN = "app/links/create/[kind].tsx";

for (const type of EXPECTED_PAIRING_TYPES) {
  const path = pairingCreatePath(type);

  // (i) Every catalog type must map to a dedicated create screen, never the
  //     generic Create tab fallback.
  assert.notEqual(
    path,
    "/(tabs)/create",
    `pairing '${type}' falls back to the generic Create tab (no dedicated create screen)`,
  );

  // (ii) The web free plan must actually permit this link type (module not
  //      explicitly disabled AND cap != 0) so a submit is never server-bounced.
  const gate = gatingMap[type];
  if (gate) {
    const moduleVal = freeFeatures[gate.module];
    assert.notEqual(
      moduleVal, false,
      `free plan disables module '${gate.module}' for pairing '${type}' — create screen would be a dead-end`,
    );
    const capVal = freeFeatures[gate.cap];
    assert.notEqual(
      capVal, 0,
      `free plan sets cap '${gate.cap}' = 0 for pairing '${type}' — fresh account cannot create it`,
    );
  }

  // (iii) Resolve the concrete screen and assert it exists on disk.
  if (STANDALONE_SCREENS[path]) {
    assert.ok(
      existsSync(join(root, STANDALONE_SCREENS[path])),
      `pairing '${type}' -> ${path} but screen file ${STANDALONE_SCREENS[path]} is missing`,
    );
    continue;
  }

  const m = path.match(/^\/links\/create\/([a-z_]+)$/);
  assert.ok(m, `pairing '${type}' -> unexpected path '${path}'`);
  const kind = m[1];
  const apiType = kindToApiType[kind];
  assert.ok(
    apiType,
    `pairing '${type}' -> /links/create/${kind} but '${kind}' is not a known LINK_KINDS kind`,
  );
  assert.ok(
    existsSync(join(root, CREATE_KIND_SCREEN)),
    `create-kind screen ${CREATE_KIND_SCREEN} is missing`,
  );

  // (iv) The mobile proactive plan gate must NOT lock this type for a fresh
  //      free account — otherwise the create screen shows an upgrade wall.
  assert.equal(
    isLinkTypeLocked(apiType),
    false,
    `mobile isLinkTypeLocked('${apiType}') is TRUE on the free plan — ` +
      `pairing '${type}' create screen would show an upgrade/paywall block`,
  );
}

// ---------------------------------------------------------------------------
// 7. Parity: the mobile proactive gated set must not silently drift from the
//    web authoritative gating map (which link types are gated). Every link
//    type the web gates (LinkController::enforceLinkTypeQuota) that mobile can
//    also create must be present in mobile's GATED_LINK_TYPES — otherwise a
//    plan that disables the module or sets the cap to 0 only bounces the user
//    at submit (a server 402) instead of showing a proactive lock. And vice
//    versa: mobile must not gate a type the web doesn't (that would falsely
//    lock it). This complements the free-plan reachability check above.
// ---------------------------------------------------------------------------
// Parse the shipped mobile gated set (apiType strings) from source.
const mobileGated = new Set(
  [...gatedSetSrc.matchAll(/"([a-z_]+)"/g)].map((m) => m[1]),
);
assert.ok(mobileGated.size > 0, "failed to parse GATED_LINK_TYPES entries");

// Web-gated types the mobile gate CANNOT express: their web module_/max_ keys
// aren't the uniform `<type>` form AND the mobile apiType differs from the web
// type, so a plain `module_<apiType>`/`max_<apiType>` derivation would mismatch.
// Such a type must supply a MODULE_KEY_BY_TYPE / CAP_KEY_BY_TYPE override (keyed
// by mobile apiType) so the gate resolves the real web key. Nothing is currently
// unexpressible: `calendar` (mobile apiType `event`) is now expressed via the
// override maps below.
const MOBILE_GATE_UNEXPRESSIBLE = new Set([]);

// Mobile apiType -> web link-type name (the gatingMap key). Kind names in
// LINK_KINDS equal the web type names, so invert kind->apiType. One divergence:
// kind `calendar` maps to apiType `event`, whose web type is `calendar`.
const apiTypeToWebType = {};
for (const [kind, apiType] of Object.entries(kindToApiType)) {
  if (gatingMap[kind]) apiTypeToWebType[apiType] = kind;
}

// Forward: every web-gated, mobile-creatable type must be gated on mobile.
for (const [webType, gate] of Object.entries(gatingMap)) {
  if (MOBILE_GATE_UNEXPRESSIBLE.has(webType)) continue;
  const apiType = kindToApiType[webType];
  if (!apiType) continue; // web-gated but not a mobile create option
  assert.ok(
    mobileGated.has(apiType),
    `web gates link type '${webType}' (module '${gate.module}', cap '${gate.cap}') ` +
      `and mobile can create it (apiType '${apiType}'), but it is missing from ` +
      `GATED_LINK_TYPES in usePlanFeatures.ts — mobile would only bounce the user ` +
      `at submit instead of proactively locking it. Add '${apiType}' to the set.`,
  );
}

// Reverse: mobile must not gate a type the web doesn't gate. Resolve each mobile
// apiType back to its web type name (handles calendar->event) before lookup.
for (const apiType of mobileGated) {
  const webType = apiTypeToWebType[apiType] ?? apiType;
  assert.ok(
    gatingMap[webType],
    `mobile GATED_LINK_TYPES includes '${apiType}' but the web gating map ` +
      `(LinkController::enforceLinkTypeQuota) does not gate it — this would ` +
      `falsely lock the type on mobile. Remove it or add web gating.`,
  );
}

// Parity: the non-uniform override maps must name the REAL web module/cap keys
// (LinkController::enforceLinkTypeQuota). Each override keyed by mobile apiType
// must equal the gating map's module/cap for that apiType's web type — otherwise
// the proactive lock reads the wrong (usually non-existent) plan key and silently
// fails open on an exhausted/disabled paid allowance.
for (const [apiType, moduleKey] of Object.entries(moduleKeyByType.map)) {
  const webType = apiTypeToWebType[apiType] ?? apiType;
  const gate = gatingMap[webType];
  assert.ok(gate, `MODULE_KEY_BY_TYPE gates apiType '${apiType}' the web doesn't`);
  assert.equal(
    moduleKey, gate.module,
    `MODULE_KEY_BY_TYPE['${apiType}'] = '${moduleKey}' but the web module key ` +
      `for '${webType}' is '${gate.module}' — the proactive module lock reads the ` +
      `wrong plan key. Match LinkController::enforceLinkTypeQuota.`,
  );
}
for (const [apiType, capKey] of Object.entries(capKeyByType.map)) {
  const webType = apiTypeToWebType[apiType] ?? apiType;
  const gate = gatingMap[webType];
  assert.ok(gate, `CAP_KEY_BY_TYPE gates apiType '${apiType}' the web doesn't`);
  assert.equal(
    capKey, gate.cap,
    `CAP_KEY_BY_TYPE['${apiType}'] = '${capKey}' but the web cap key for ` +
      `'${webType}' is '${gate.cap}' — the proactive cap lock reads the wrong ` +
      `plan key. Match LinkController::enforceLinkTypeQuota.`,
  );
}

// Calendar (mobile apiType `event`) must be reachable on the free plan
// (seeder grants max_calendars=1, module_calendar not disabled)...
assert.ok(mobileGated.has("event"), "calendar apiType 'event' expected in GATED_LINK_TYPES");
assert.equal(
  isLinkTypeLocked("event"),
  false,
  "mobile isLinkTypeLocked('event') is TRUE on the free plan — Calendar pages " +
    "would be falsely locked for a fresh free account (max_calendars=1)",
);
// ...but proactively locked when a paid plan disables the module or exhausts the cap.
assert.equal(
  makeIsLinkTypeLocked(true, { module_calendar: false })("event"),
  true,
  "mobile isLinkTypeLocked('event') should be TRUE when module_calendar is off",
);
assert.equal(
  makeIsLinkTypeLocked(true, { max_calendars: 0 })("event"),
  true,
  "mobile isLinkTypeLocked('event') should be TRUE when max_calendars cap is 0",
);
// Brand Kit's non-uniform cap key must also lock at 0 (its module is uniform).
assert.equal(
  makeIsLinkTypeLocked(true, { max_brand_kit_pages: 0 })("brand_kit"),
  true,
  "mobile isLinkTypeLocked('brand_kit') should be TRUE when max_brand_kit_pages cap is 0",
);

// Store pages (`store_menu`) are newly gated but must stay reachable on the
// free plan (seeder grants max_store_menu=1 with no disabling module toggle) —
// assert the shipped gate does not falsely lock a fresh free account. This is
// the direct free-plan reachability guard for a gated type that isn't a
// "Perfect pairings" catalog item (so the loop above doesn't cover it).
assert.ok(
  mobileGated.has("store_menu"),
  "store_menu is expected to be in mobile GATED_LINK_TYPES",
);
assert.equal(
  isLinkTypeLocked("store_menu"),
  false,
  "mobile isLinkTypeLocked('store_menu') is TRUE on the free plan — Store " +
    "pages would be falsely locked for a fresh free account",
);

// ---------------------------------------------------------------------------
// 8. Sanity: an unknown pairing type still falls back to the generic Create
//    tab (defensive default, mirrors the shipped switch).
// ---------------------------------------------------------------------------
assert.equal(
  pairingCreatePath("totally_unknown_type"),
  "/(tabs)/create",
  "unknown pairing type should fall back to the generic Create tab",
);

console.log(
  `test-pairing-create-open: OK — ${EXPECTED_PAIRING_TYPES.length} pairing ` +
    "create screens reachable on a fresh free account.",
);
