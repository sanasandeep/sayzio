// Source-driven drift guard for the mobile About screen's animated hero stat
// counters (task #4444).
//
// The mobile About screen reads its hero stat row (the count-up counters —
// "120,000+ creators", etc.) at runtime from the product app's public
// GET /api/v1/site/about endpoint under `data.hero.stats`, and falls back to a
// hard-coded FALLBACK_HERO_STATS constant when the fetch fails or returns no
// usable rows. The parse itself lives in two canonical places that must stay in
// lockstep:
//
//   1. the product app's PHP source (canonical) —
//      SiteContentController::about() emits `data.hero.stats` as a list of
//      `['value' => ..., 'suffix' => ..., 'label' => ...]` rows.
//   2. the mobile parser
//      (artifacts/1inme-mobile/lib/api/siteContent.ts — guarded HERE) reads
//      `data.hero.stats` and maps each row's `value`/`suffix`/`label` through
//      formatStatValue().
//
// If someone renames or removes a hero-stat key on the API side (#1), or the
// mobile parser drifts to read a different key/path (#2), the mobile screen
// would quietly revert to the hard-coded FALLBACK_HERO_STATS numbers with no
// error — admins would think their edits published when they didn't. This is
// the exact silent-drift regression this guard prevents (same class as
// task #3270's EEFind guard in test-about-eefind-sync.mjs).
//
// Following the convention in test-about-eefind-sync.mjs we avoid a full TS/RN
// runner: we read BOTH the PHP source and the shipped mobile parser source at
// runtime (no hard-coded third copy), compare the hero-stat keys/path both
// sides use, and execute the REAL extracted parser code against a PHP-shaped
// payload so the guard has teeth.
//
// Run via `node scripts/test-about-hero-stats-sync.mjs` (package script
// `test:about-hero-stats`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

// The product app's canonical PHP source, relative to this mobile package.
const SITE_CONTENT_CONTROLLER_PHP = resolve(
  root,
  "..",
  "1inme",
  "app",
  "Modules",
  "Api",
  "Controllers",
  "SiteContentController.php",
);

const MOBILE_SITE_CONTENT_TS = join(root, "lib", "api", "siteContent.ts");

// ---------------------------------------------------------------------------
// Generic bracket-matching slicer: return the balanced block starting at the
// first `open` char at/after `from`.
// ---------------------------------------------------------------------------
function sliceBalanced(src, from, open, close) {
  const start = src.indexOf(open, from);
  if (start === -1) return null;
  let depth = 0;
  for (let i = start; i < src.length; i++) {
    const ch = src[i];
    if (ch === open) depth++;
    else if (ch === close) {
      depth--;
      if (depth === 0) return src.slice(start, i + 1);
    }
  }
  return null;
}

// ---------------------------------------------------------------------------
// 1. Read what SiteContentController::about() emits under data.hero.stats.
// ---------------------------------------------------------------------------
const php = readFileSync(SITE_CONTENT_CONTROLLER_PHP, "utf8");

const aboutSig = "public function about(): JsonResponse";
const aboutStart = php.indexOf(aboutSig);
if (aboutStart === -1) {
  throw new Error(
    "Could not find SiteContentController::about() — did the method get renamed?",
  );
}
const nextFn = php.indexOf("public function", aboutStart + aboutSig.length);
const aboutBody = nextFn === -1 ? php.slice(aboutStart) : php.slice(aboutStart, nextFn);

// The response must nest the counters under `'hero' => [ 'stats' => ... ]`.
assert.match(
  aboutBody,
  /'hero'\s*=>\s*\[\s*'stats'\s*=>/,
  "about() must emit hero stats under a `'hero' => ['stats' => ...]` container",
);

// Extract the per-row array literal pushed into $heroStats and read its keys.
const pushMarker = aboutBody.indexOf("$heroStats[] =");
if (pushMarker === -1) {
  throw new Error(
    "Could not find the `$heroStats[] = [...]` push in about() — did the hero-stat build change?",
  );
}
const rowLiteral = sliceBalanced(
  aboutBody,
  pushMarker + "$heroStats[] =".length,
  "[",
  "]",
);
if (!rowLiteral) {
  throw new Error("Unterminated `$heroStats[] = [...]` row literal in about().");
}
const phpRowKeys = new Set(
  [...rowLiteral.matchAll(/'([a-z_]+)'\s*=>/g)].map((m) => m[1]),
);
assert.ok(
  phpRowKeys.size > 0,
  "Could not parse any keys from the emitted hero-stat row literal in about().",
);

// ---------------------------------------------------------------------------
// 2. Read the mobile parser's hero-stats mapping from siteContent.ts.
// ---------------------------------------------------------------------------
const ts = readFileSync(MOBILE_SITE_CONTENT_TS, "utf8");

// Isolate the `const heroStats = Array.isArray(data.hero?.stats) ? ... : [];`
// block so key/path lookups can't match the EEFind stats mapping above it.
const heroDecl = "const heroStats = Array.isArray(data.hero?.stats)";
const heroDeclStart = ts.indexOf(heroDecl);
if (heroDeclStart === -1) {
  throw new Error(
    "Could not find the `const heroStats = Array.isArray(data.hero?.stats)` parser block in siteContent.ts — did the parser change?",
  );
}
const heroBlockEnd = ts.indexOf(": [];", heroDeclStart);
if (heroBlockEnd === -1) {
  throw new Error("Could not find the end of the heroStats parser block in siteContent.ts.");
}
const heroBlock = ts.slice(heroDeclStart, heroBlockEnd + ": [];".length);

// The parser must read from the same `data.hero.stats` path the API nests under.
assert.match(
  heroBlock,
  /data\.hero\??\.stats/,
  "The mobile parser must read hero stats from `data.hero.stats`",
);

// Keys the parser reads off each stat entry (`st?.value`, `st?.suffix`, ...).
const parserRowKeys = new Set(
  [...heroBlock.matchAll(/st\?\.([a-zA-Z_]+)/g)].map((m) => m[1]),
);
assert.ok(
  parserRowKeys.size > 0,
  "Could not parse any `st?.<key>` reads from the mobile heroStats parser block.",
);

// ---------------------------------------------------------------------------
// 3. Assert the API-emitted keys and the parser-read keys are in lockstep.
// ---------------------------------------------------------------------------
assert.deepEqual(
  [...parserRowKeys].sort(),
  [...phpRowKeys].sort(),
  "Mobile hero-stat parser keys must match the keys SiteContentController::about() emits under data.hero.stats",
);

// ---------------------------------------------------------------------------
// 4. Execute the REAL extracted parser against a PHP-shaped payload so the
//    guard has teeth (path + formatting integrity, not just key names).
// ---------------------------------------------------------------------------
const fmtSig = "function formatStatValue(";
const fmtStart = ts.indexOf(fmtSig);
if (fmtStart === -1) {
  throw new Error("Could not find formatStatValue() in siteContent.ts.");
}
const fmtBody = sliceBalanced(ts, fmtStart, "{", "}");
if (!fmtBody) {
  throw new Error("Unterminated formatStatValue() body in siteContent.ts.");
}
// Strip the TS type annotations so the plain-JS function body can be eval'd.
const fmtJs = `function formatStatValue(value, suffix)${fmtBody}`.replace(
  /:\s*string/g,
  "",
);

// Build a payload using the exact key names the API emits (asserted equal to
// the parser keys above), so a rename on either side would have already failed.
const sampleRow = {};
for (const key of phpRowKeys) sampleRow[key] = "";
sampleRow.value = "4000";
sampleRow.suffix = "+";
sampleRow.label = "Products";

// eslint-disable-next-line no-new-func
const runParser = new Function(
  "data",
  `${fmtJs}\n${heroBlock}\nreturn heroStats;`,
);

// The extracted block reads `data.hero.stats`, so the runner's `data` param is
// the response `data` object itself (mirroring `const data = json?.data`).
const parsed = runParser({ hero: { stats: [sampleRow] } });
assert.deepEqual(
  parsed,
  [{ value: "4,000+", label: "Products" }],
  "The mobile parser must map data.hero.stats rows into formatted {value,label} counters",
);

// A payload with no hero.stats yields an empty list (caller then keeps the
// bundled FALLBACK_HERO_STATS) — confirms the empty-path branch.
assert.deepEqual(
  runParser({}),
  [],
  "The mobile parser must yield an empty list when data.hero.stats is absent",
);

console.log(
  "ok — mobile About hero-stat parser in sync with SiteContentController::about() data.hero.stats (keys, path, formatting)",
);
